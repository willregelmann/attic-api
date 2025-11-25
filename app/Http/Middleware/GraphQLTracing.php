<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanInterface;
use Symfony\Component\HttpFoundation\Response;

class GraphQLTracing
{
    /**
     * Add tracing context to GraphQL requests and responses.
     *
     * - Adds X-Trace-Id response header for correlation
     * - Adds graphql.operation.name attribute to root span
     * - Includes trace_id in GraphQL response extensions
     */
    public function handle(Request $request, Closure $next): Response
    {
        $span = Globals::tracerProvider()
            ->getTracer('graphql-tracing')
            ->spanBuilder('graphql.middleware')
            ->startSpan();

        // Get the current active span (the root HTTP span)
        $currentSpan = \OpenTelemetry\API\Trace\Span::getCurrent();
        $traceId = $currentSpan->getContext()->getTraceId();

        // Parse GraphQL operation name from request body
        $operationName = $this->extractOperationName($request);

        if ($operationName && $currentSpan instanceof SpanInterface) {
            $currentSpan->setAttribute('graphql.operation.name', $operationName);
            $currentSpan->updateName("POST /graphql {$operationName}");
        }

        // Store trace ID for response manipulation
        $request->attributes->set('trace_id', $traceId);

        $span->end();

        $response = $next($request);

        // Add trace ID to response header
        if ($traceId) {
            $response->headers->set('X-Trace-Id', $traceId);
        }

        // Add trace ID to GraphQL response extensions
        if ($traceId && $response->headers->get('Content-Type') === 'application/json') {
            $this->addTraceIdToResponse($response, $traceId);
        }

        return $response;
    }

    /**
     * Extract the GraphQL operation name from the request.
     */
    private function extractOperationName(Request $request): ?string
    {
        $content = $request->getContent();
        if (!$content) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        // Check for explicit operationName field
        if (!empty($data['operationName'])) {
            return $data['operationName'];
        }

        // Try to extract from query string
        $query = $data['query'] ?? '';
        if (preg_match('/(?:query|mutation|subscription)\s+(\w+)/', $query, $matches)) {
            return $matches[1];
        }

        // Detect if it's a mutation or query based on content
        if (str_contains($query, 'mutation')) {
            return 'mutation';
        }

        return 'query';
    }

    /**
     * Add trace_id to GraphQL response extensions.
     */
    private function addTraceIdToResponse(Response $response, string $traceId): void
    {
        $content = $response->getContent();
        if (!$content) {
            return;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return;
        }

        // Add to extensions
        $data['extensions'] = $data['extensions'] ?? [];
        $data['extensions']['tracing'] = [
            'trace_id' => $traceId,
        ];

        $response->setContent(json_encode($data));
    }
}
