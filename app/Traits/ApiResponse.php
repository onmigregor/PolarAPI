<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponse
{
    /**
     * Success Response
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $code
     * @return JsonResponse
     */
    protected function success(mixed $data, ?string $message = null, int $code = Response::HTTP_OK): JsonResponse
    {
        $response = [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ];

        if ($data instanceof ResourceCollection && $data->resource instanceof AbstractPaginator) {
            $paginated = $data->response()->getData(true);

            $response['data'] = $paginated['data'];
            $response['links'] = $paginated['links'] ?? [];
            $response['meta'] = $paginated['meta'] ?? [];
        }

        return response()->json($response, $code);
    }

    /**
     * Error Response
     *
     * @param string $message
     * @param int $code
     * @param array|null $errors
     * @return JsonResponse
     */
    protected function error(string $message, int $code = Response::HTTP_BAD_REQUEST, ?array $errors = null): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}
