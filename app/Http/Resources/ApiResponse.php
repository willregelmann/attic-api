<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Create a successful API response
     *
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    public static function success($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Create an error API response
     *
     * @param string $message
     * @param int $code
     * @param string|null $errorCode
     * @param array $errors
     * @return JsonResponse
     */
    public static function error(
        string $message,
        int $code = 400,
        ?string $errorCode = null,
        array $errors = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'statusCode' => $code,
            'timestamp' => now()->toISOString(),
        ];

        if ($errorCode) {
            $response['code'] = $errorCode;
        }

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Create a validation error response
     *
     * @param array $errors
     * @param string $message
     * @return JsonResponse
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return self::error($message, 422, 'VALIDATION_ERROR', $errors);
    }

    /**
     * Create an authentication error response
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function authenticationError(
        string $message = 'Authentication failed'
    ): JsonResponse {
        return self::error($message, 401, 'AUTHENTICATION_ERROR');
    }

    /**
     * Create an authorization error response
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function authorizationError(
        string $message = 'Access denied'
    ): JsonResponse {
        return self::error($message, 403, 'AUTHORIZATION_ERROR');
    }

    /**
     * Create a not found error response
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function notFound(
        string $message = 'Resource not found'
    ): JsonResponse {
        return self::error($message, 404, 'NOT_FOUND');
    }

    /**
     * Create a server error response
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function serverError(
        string $message = 'Internal server error'
    ): JsonResponse {
        return self::error($message, 500, 'SERVER_ERROR');
    }
}