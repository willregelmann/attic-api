<?php

namespace App\GraphQL\Queries\DatabaseOfThings;

use App\Services\DatabaseOfThingsService;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class SemanticSearch
{
    protected $databaseOfThings;

    public function __construct(DatabaseOfThingsService $databaseOfThings)
    {
        $this->databaseOfThings = $databaseOfThings;
    }

    /**
     * Perform semantic search using vector embeddings
     *
     * @param  mixed  $rootValue
     * @return array
     */
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $queryText = $args['query'];
        $entityType = $args['type'] ?? null;
        $limit = $args['first'] ?? 20;

        // Results are already normalized by the service (includes image_url normalization)
        $results = $this->databaseOfThings->semanticSearch($queryText, $entityType, $limit);

        // Transform results to match GraphQL Entity type
        return array_map(function ($result) {
            return [
                'id' => $result['id'],
                'type' => $result['type'],
                'name' => $result['name'],
                'year' => $result['year'],
                'country' => $result['country'],
                'attributes' => $result['attributes'] ?? [],
                'image_url' => $result['image_url'] ?? null, // Already normalized by service
                'thumbnail_url' => $result['thumbnail_url'] ?? null,
                'external_ids' => [],
                'created_at' => null,
                'updated_at' => null,
                'similarity' => $result['similarity'] ?? 0,
            ];
        }, $results);
    }
}
