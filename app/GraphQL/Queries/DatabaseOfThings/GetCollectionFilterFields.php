<?php

namespace App\GraphQL\Queries\DatabaseOfThings;

use App\Services\DatabaseOfThingsService;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Cache;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class GetCollectionFilterFields
{
    protected $databaseOfThings;

    public function __construct(DatabaseOfThingsService $databaseOfThings)
    {
        $this->databaseOfThings = $databaseOfThings;
    }

    /**
     * Get filterable fields for a collection (recursively from all descendants)
     * Results are cached for 1 hour
     *
     * @param  mixed  $rootValue
     * @return array
     */
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $collectionId = $args['collection_id'];

        // Cache key for this collection's filterable fields
        $cacheKey = "collection_filter_fields_{$collectionId}";

        // Try to get from cache first (1 hour TTL)
        return Cache::remember($cacheKey, 3600, function () use ($collectionId) {
            return $this->discoverFilterableFields($collectionId);
        });
    }

    /**
     * Discover filterable fields from collection's direct children only
     */
    private function discoverFilterableFields(string $collectionId): array
    {
        // Fetch items from this collection only (no recursive traversal)
        $result = $this->databaseOfThings->getCollectionItems($collectionId, 1000, null);
        $items = $result['items'];

        if (empty($items)) {
            return [];
        }

        // Extract entities from items
        $entities = array_map(function ($item) {
            return $item['entity'];
        }, $items);

        // Discover fields from direct children only
        return $this->extractFilterableFields($entities);
    }

    /**
     * Extract filterable fields from a list of items
     */
    private function extractFilterableFields(array $items): array
    {
        $fields = [];

        // Standard top-level fields
        $standardFields = [
            ['field' => 'type', 'label' => 'Type', 'priority' => 1],
            ['field' => 'year', 'label' => 'Year', 'priority' => 2],
            ['field' => 'country', 'label' => 'Country', 'priority' => 3],
        ];

        foreach ($standardFields as $fieldDef) {
            $values = $this->extractFieldValues($items, $fieldDef['field']);
            if (! empty($values)) {
                $fields[] = [
                    'field' => $fieldDef['field'],
                    'label' => $fieldDef['label'],
                    'type' => $this->inferFieldType($values),
                    'values' => array_values($values),
                    'count' => count($values),
                    'priority' => $fieldDef['priority'],
                ];
            }
        }

        // Discover attribute fields
        $attributeFields = $this->discoverAttributeFields($items);
        $fields = array_merge($fields, $attributeFields);

        // Sort by priority and label
        usort($fields, function ($a, $b) {
            if ($a['priority'] !== $b['priority']) {
                return $a['priority'] - $b['priority'];
            }

            return strcmp($a['label'], $b['label']);
        });

        return $fields;
    }

    /**
     * Extract unique values for a field from items
     */
    private function extractFieldValues(array $items, string $field): array
    {
        $values = [];

        foreach ($items as $item) {
            $value = $this->getNestedValue($item, $field);

            if ($value !== null && $value !== '') {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        if ($v !== null && $v !== '') {
                            $values[$v] = true;
                        }
                    }
                } else {
                    $values[$value] = true;
                }
            }
        }

        $uniqueValues = array_keys($values);
        sort($uniqueValues);

        return $uniqueValues;
    }

    /**
     * Get nested value from array using dot notation
     * Handles JSON-encoded strings by decoding them
     *
     * @return mixed
     */
    private function getNestedValue(array $item, string $path)
    {
        $parts = explode('.', $path);
        $value = $item;

        foreach ($parts as $part) {
            // Decode JSON strings if encountered
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $value = $decoded;
                } else {
                    return null;
                }
            }

            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Discover filterable fields from item attributes and top-level fields
     */
    private function discoverAttributeFields(array $items): array
    {
        $allPaths = [];
        $standardFields = ['id', 'type', 'name', 'year', 'country', 'image_url', 'thumbnail_url', 'external_ids', 'created_at', 'updated_at'];

        foreach ($items as $item) {
            // Check attributes field
            $attributes = $item['attributes'] ?? null;
            if ($attributes) {
                if (is_string($attributes)) {
                    $attributes = json_decode($attributes, true);
                }
                if (is_array($attributes)) {
                    $this->collectPaths($attributes, 'attributes', $allPaths, 3, 1);
                }
            }

            // Also check for top-level custom fields (not in standard fields list)
            foreach ($item as $key => $value) {
                if (in_array($key, $standardFields) || $value === null) {
                    continue;
                }

                // Skip if it's a complex object/array (except attributes which we already handled)
                if ($key === 'attributes') {
                    continue;
                }

                // If it's a simple value or array of simple values, add it
                if (is_scalar($value) || (is_array($value) && ! empty($value) && is_scalar($value[0] ?? null))) {
                    $allPaths[$key] = true;
                }
            }
        }

        // Convert paths to field descriptors
        $fields = [];
        foreach ($allPaths as $path => $_) {
            $values = $this->extractFieldValues($items, $path);

            if (! empty($values)) {
                $fields[] = [
                    'field' => $path,
                    'label' => $this->formatAttributeLabel($path),
                    'type' => $this->inferFieldType($values),
                    'values' => array_values($values),
                    'count' => count($values),
                    'priority' => 10,
                ];
            }
        }

        return $fields;
    }

    /**
     * Recursively collect all paths in an object
     */
    private function collectPaths(array $obj, string $prefix, array &$paths, int $maxDepth, int $currentDepth): void
    {
        if ($currentDepth > $maxDepth) {
            return;
        }

        foreach ($obj as $key => $value) {
            $path = "{$prefix}.{$key}";

            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                // If array contains primitive values, add the path
                if (! empty($value) && (! is_array($value[0]) || $value[0] === null)) {
                    $paths[$path] = true;
                } elseif (! empty($value) && is_array($value[0])) {
                    // Recurse into array of objects
                    $this->collectPaths($value[0], $path, $paths, $maxDepth, $currentDepth + 1);
                }
            } elseif (is_object($value)) {
                $this->collectPaths((array) $value, $path, $paths, $maxDepth, $currentDepth + 1);
            } else {
                // Primitive value
                $paths[$path] = true;
            }
        }
    }

    /**
     * Format attribute path into a readable label
     */
    private function formatAttributeLabel(string $path): string
    {
        // Remove "attributes." prefix
        $withoutPrefix = preg_replace('/^attributes\./', '', $path);

        // Split on dots and camelCase
        $parts = array_map(function ($part) {
            // Insert space before capital letters
            $part = preg_replace('/([A-Z])/', ' $1', $part);
            // Split on underscores
            $part = str_replace('_', ' ', $part);

            return trim($part);
        }, explode('.', $withoutPrefix));

        // Capitalize each part
        $parts = array_map(function ($part) {
            return ucwords(strtolower($part));
        }, $parts);

        return implode(' > ', $parts);
    }

    /**
     * Infer the data type of field values
     */
    private function inferFieldType(array $values): string
    {
        if (empty($values)) {
            return 'string';
        }

        $firstValue = $values[0];

        if (is_numeric($firstValue)) {
            return 'number';
        }
        if (is_bool($firstValue)) {
            return 'boolean';
        }

        return 'string';
    }
}
