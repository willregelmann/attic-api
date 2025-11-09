<?php

namespace App\GraphQL\Scalars;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\ScalarType;

class JSON extends ScalarType
{
    /**
     * Serializes an internal value to include in a response.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public function serialize($value)
    {
        return $value;
    }

    /**
     * Parses a value given by the client.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public function parseValue($value)
    {
        return $value;
    }

    /**
     * Parses an externally provided literal value (hardcoded in GraphQL query).
     *
     * @param  \GraphQL\Language\AST\Node  $valueNode
     * @param  array<string, mixed>|null  $variables
     * @return mixed
     */
    public function parseLiteral($valueNode, ?array $variables = null)
    {
        switch ($valueNode->kind) {
            case 'StringValue':
                return json_decode($valueNode->value, true);
            case 'BooleanValue':
                return $valueNode->value;
            case 'IntValue':
                return intval($valueNode->value, 10);
            case 'FloatValue':
                return floatval($valueNode->value);
            case 'ObjectValue':
                $value = [];
                foreach ($valueNode->fields as $field) {
                    $value[$field->name->value] = $this->parseLiteral($field->value, $variables);
                }

                return $value;
            case 'ListValue':
                return array_map(function ($node) use ($variables) {
                    return $this->parseLiteral($node, $variables);
                }, iterator_to_array($valueNode->values));
            case 'NullValue':
                return null;
            case 'Variable':
                return $variables[$valueNode->name->value] ?? null;
            default:
                throw new InvariantViolation('Invalid value kind: '.$valueNode->kind);
        }
    }
}
