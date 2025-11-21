<?php

namespace App\Services;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\TracerProvider;

/**
 * GraphQL OpenTelemetry Tracing Service
 *
 * Provides detailed instrumentation for GraphQL field resolvers
 */
class GraphQLTracingService
{
    private $tracer;

    public function __construct()
    {
        // Get the global tracer
        $tracerProvider = \OpenTelemetry\API\Globals::tracerProvider();
        $this->tracer = $tracerProvider->getTracer('graphql-resolver');
    }

    /**
     * Trace a GraphQL field resolution
     *
     * @param string $fieldName The GraphQL field being resolved
     * @param string $typeName The parent type name
     * @param callable $resolver The resolver function to execute
     * @param mixed $args Resolver arguments
     * @return mixed The resolver result
     */
    public function traceFieldResolver(string $fieldName, string $typeName, callable $resolver, $args = [])
    {
        $spanName = "graphql.resolve {$typeName}.{$fieldName}";

        $span = $this->tracer->spanBuilder($spanName)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute('graphql.field.name', $fieldName)
            ->setAttribute('graphql.type.name', $typeName)
            ->setAttribute('graphql.args', json_encode($args))
            ->startSpan();

        try {
            $result = $resolver();

            // Add result metadata
            if (is_array($result) || is_object($result)) {
                if (is_array($result)) {
                    $span->setAttribute('graphql.result.count', count($result));
                }
                $span->setAttribute('graphql.result.type', is_array($result) ? 'array' : get_class($result));
            }

            $span->setStatus(StatusCode::STATUS_OK);
            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $span->end();
        }
    }

    /**
     * Trace a GraphQL query execution
     *
     * @param string $queryName The query operation name
     * @param string $query The GraphQL query string
     * @param callable $executor The execution function
     * @return mixed The query result
     */
    public function traceQuery(string $queryName, string $query, callable $executor)
    {
        $spanName = "graphql.execute {$queryName}";

        $span = $this->tracer->spanBuilder($spanName)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute('graphql.operation.name', $queryName)
            ->setAttribute('graphql.operation.type', 'query')
            ->setAttribute('graphql.query', substr($query, 0, 500)) // Limit query string length
            ->startSpan();

        try {
            $result = $executor();
            $span->setStatus(StatusCode::STATUS_OK);
            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $span->end();
        }
    }

    /**
     * Trace a database query from GraphQL resolver
     *
     * @param string $operation The database operation (SELECT, INSERT, etc)
     * @param string $table The table name
     * @param callable $executor The query executor
     * @return mixed The query result
     */
    public function traceDbQuery(string $operation, string $table, callable $executor)
    {
        $spanName = "db.{$operation} {$table}";

        $span = $this->tracer->spanBuilder($spanName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('db.operation.name', $operation)
            ->setAttribute('db.table.name', $table)
            ->startSpan();

        try {
            $result = $executor();
            $span->setStatus(StatusCode::STATUS_OK);
            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $span->end();
        }
    }
}
