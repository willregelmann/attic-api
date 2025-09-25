<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateWithApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // First try Sanctum authentication (for web/cookie based auth)
        if (Auth::guard('sanctum')->check()) {
            return $next($request);
        }

        // Then try API token authentication
        $token = null;
        
        // Check for Bearer token in Authorization header
        if ($request->bearerToken()) {
            $token = $request->bearerToken();
        }
        // Check for token in X-API-Token header
        elseif ($request->header('X-API-Token')) {
            $token = $request->header('X-API-Token');
        }
        // Check for token in query parameter (less secure, but sometimes necessary)
        elseif ($request->query('api_token')) {
            $token = $request->query('api_token');
        }

        if ($token) {
            $apiToken = ApiToken::findByToken($token);
            
            if ($apiToken && !$apiToken->hasExpired()) {
                // Update last used timestamp
                $apiToken->touchLastUsed();
                
                // Set the user for this request
                Auth::login($apiToken->user);
                
                // Store token in request for later access if needed
                $request->merge(['api_token_model' => $apiToken]);
                
                return $next($request);
            }
        }

        // If no valid authentication found, continue without auth
        // (let the @guard directive in GraphQL handle authorization)
        return $next($request);
    }
}