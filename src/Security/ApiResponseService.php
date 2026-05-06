<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiResponseService
{
    public function success(
        array|string|null $data = null,
        string $message = 'Success',
        int $statusCode = Response::HTTP_OK,
        array $extra = []
    ): array {
        $response = [
            'success' => true,
            'status' => 'success',
            'status_code' => $statusCode,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($extra)) {
            $response = array_merge($response, $extra);
        }

        return $response;
    }

    public function error(
        string $message,
        int $statusCode = Response::HTTP_BAD_REQUEST,
        array $extra = []
    ): array {
        $response = [
            'success' => false,
            'status' => 'error',
            'status_code' => $statusCode,
            'message' => $message,
        ];

        if (!empty($extra)) {
            $response = array_merge($response, $extra);
        }

        return $response;
    }
}
