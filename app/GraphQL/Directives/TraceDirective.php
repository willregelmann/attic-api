<?php

namespace App\GraphQL\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

/**
 * GraphQL @trace directive
 *
 * Automatically instruments field resolvers with OpenTelemetry tracing
 *
 * Usage in schema:
 * ```graphql
 * type Query {
 *   myField: String @trace
 * }
 * ```
 */
class TraceDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Trace this field resolver with OpenTelemetry
"""
directive @trace on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $previousResolver = $fieldValue->getResolver();

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver) {
            $tracer = Globals::tracerProvider()->getTracer('graphql-field');

            // Build span name from type and field
            $typeName = $resolveInfo->parentType->name;
            $fieldName = $resolveInfo->fieldName;
            $spanName = "graphql.resolve {$typeName}.{$fieldName}";

            $span = $tracer->spanBuilder($spanName)
                ->setSpanKind(SpanKind::KIND_INTERNAL)
                ->setAttribute('graphql.field.name', $fieldName)
                ->setAttribute('graphql.type.name', $typeName)
                ->setAttribute('graphql.field.path', implode('.', $resolveInfo->path))
                ->startSpan();

            // Add arguments if present
            if (!empty($args)) {
                $span->setAttribute('graphql.args', json_encode($args, JSON_UNESCAPED_SLASHES));
                $span->setAttribute('graphql.args.count', count($args));
            }

            $scope = $span->activate();

            try {
                $result = $previousResolver($root, $args, $context, $resolveInfo);

                // Add result metadata
                if (is_array($result)) {
                    $span->setAttribute('graphql.result.count', count($result));
                    $span->setAttribute('graphql.result.type', 'array');
                } elseif (is_object($result)) {
                    $span->setAttribute('graphql.result.type', get_class($result));
                } elseif ($result === null) {
                    $span->setAttribute('graphql.result.type', 'null');
                } else {
                    $span->setAttribute('graphql.result.type', gettype($result));
                }

                $span->setStatus(StatusCode::STATUS_OK);

                return $result;
            } catch (\Throwable $e) {
                $span->recordException($e);
                $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
                throw $e;
            } finally {
                $scope->detach();
                $span->end();
            }
        });

        return $next($fieldValue);
    }
}
