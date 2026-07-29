<?php

namespace App\Traits\Http\Api;

use Illuminate\Http\Response;
use Illuminate\Validation\Validator;
use Illuminate\Validation\ValidationException;

trait SupportsApiResponses
{
    /**
     * Returns a standardized JSON success response.
     *
     * @param array $data Additional data to include in the response.
     * @param int|string $statusCode HTTP status code (default: 200 OK).
     * @return \Illuminate\Http\JsonResponse
     */
    protected function responseSuccess(array $data = [], int|string $statusCode = Response::HTTP_OK)
    {
        return response()->json(array_merge([
            'status' => 'success',
            'code' => $statusCode
        ], $data), $statusCode);
    }

    /**
     * Returns a standardized JSON error response.
     *
     * @param array $data Additional data to include in the response.
     * @param int|string $statusCode HTTP status code (default: 500 Internal Server Error).
     * @return \Illuminate\Http\JsonResponse
     */
    protected function responseError(array $data = [], int|string $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR)
    {
        return response()->json(array_merge([
            'status' => 'error',
            'code' => $statusCode
        ], $data), $statusCode);
    }

    protected function responseNotFoundError()
    {
        return $this->responseError([
            'data' => null
        ], Response::HTTP_NOT_FOUND);
    }

    protected function responseResourceNotFoundError(string $resourceName, string|int $fieldValue, array $data = [])
    {
        return $this->responseError(array_merge([
            'message' => "Resource [{$resourceName}] with identifier unique [{$fieldValue}] not found",
            'errors' => [
                $resourceName => "Resource [{$resourceName}] with {$fieldValue} not found"
            ]
        ], $data), Response::HTTP_NOT_FOUND);
    }

    /**
     * Returns a standardized JSON error response for unauthorized access.
     *
     * @param array $data Additional data to include in the response.
     * @return \Illuminate\Http\JsonResponse
     */
    protected function responseUnauthorizedError(array $data = [])
    {
        return $this->responseError(array_merge([
            'message' => __('errors.unauthorized')
        ], $data), Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Returns a standardized JSON error response for validation errors.
     *
     * @param array $data Additional data to include in the response.
     * @return \Illuminate\Http\JsonResponse
     */
    protected function responseValidationError(array $data = [])
    {
        return $this->responseError($data, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Throws a validation exception with a standardized JSON response.
     *
     * This method constructs a validation exception using the provided validator
     * and attaches a detailed validation error response, including the first error
     * message and a list of all validation errors.
     *
     * @param \Illuminate\Validation\Validator $validator The validator instance containing validation errors.
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function throwValidationError(Validator $validator)
    {
        throw new ValidationException($validator, $this->responseValidationError([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors()->getMessages()
        ]));
    }
}
