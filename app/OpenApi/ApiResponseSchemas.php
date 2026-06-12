<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ErrorMessage',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'An error occurred.'),
    ]
)]
#[OA\Schema(
    schema: 'ValidationError',
    required: ['message', 'errors'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            example: ['email' => ['The provided credentials are incorrect.']],
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string'),
            ),
        ),
    ]
)]
#[OA\Schema(
    schema: 'UnauthorizedError',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
    ]
)]
#[OA\Schema(
    schema: 'ForbiddenError',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.'),
    ]
)]
#[OA\Schema(
    schema: 'NotFoundError',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'No query results for model.'),
    ]
)]
#[OA\Schema(
    schema: 'TooManyRequestsError',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Too Many Attempts.'),
    ]
)]
class ApiResponseSchemas
{
    //
}
