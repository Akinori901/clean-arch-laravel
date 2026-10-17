<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

final class JsonResponder
{
    /** @param array<string, mixed> $data */
    public function ok(array $data): JsonResponse
    {
        return new JsonResponse(['data' => $data]);
    }

    public function notFound(string $message): JsonResponse
    {
        return new JsonResponse(['message' => $message], 404);
    }
}
