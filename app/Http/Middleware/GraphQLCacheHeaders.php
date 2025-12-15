<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GraphQLCacheHeaders
{
    /**
     * Add appropriate Cache-Control headers to GraphQL responses.
     *
     * DBoT queries are read-only and can be cached aggressively.
     * User queries must remain private/no-cache.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only process GraphQL POST requests
        if ($request->method() !== 'POST') {
            return $response;
        }

        $operationName = $request->input('operationName', '');

        // DBoT queries are read-only, safe to cache
        if ($this->isDatabaseOfThingsQuery($operationName)) {
            $response->headers->set(
                'Cache-Control',
                'public, max-age=3600, stale-while-revalidate=86400'
            );
        }
        // User queries and mutations remain private (Laravel default)

        return $response;
    }

    /**
     * Check if operation is a Database of Things query.
     */
    private function isDatabaseOfThingsQuery(string $operationName): bool
    {
        // Match both "databaseOfThings..." and "GetDatabaseOfThings..." patterns
        return str_starts_with($operationName, 'databaseOfThings')
            || str_starts_with($operationName, 'GetDatabaseOfThings');
    }
}
