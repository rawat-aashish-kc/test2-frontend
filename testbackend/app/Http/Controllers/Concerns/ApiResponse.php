<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Enforces the one response shape from CLAUDE.md/CONTRACT.md:
 * success {data, message}, error {message, errors?}.
 */
trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'message' => $message], $status);
    }

    protected function error(string $message, int $status = 422, ?array $errors = null): JsonResponse
    {
        $body = ['message' => $message];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }
}
