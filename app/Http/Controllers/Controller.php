<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\OpenApi(openapi: OA\OpenApi::VERSION_3_1_0, security: [['sanctum' => []]])]
#[OA\Info(
    version: '1.0.0',
    title: 'Basic single file API',
    attachables: [new OA\Attachable]
)]
#[OA\License(name: 'MIT', identifier: 'MIT')]
#[OA\Server(url: '/api', description: 'API server')]
#[OA\SecurityScheme(securityScheme: 'sanctum', type: 'http', description: 'Sanctum bearer token', bearerFormat: 'JWT', scheme: 'bearer')]
#[OA\Tag(name: 'auth', description: 'Authentication endpoints')]
#[OA\Tag(name: 'events', description: 'Event management endpoints')]
#[OA\Tag(name: 'products', description: 'All about products')]
#[OA\Tag(name: 'catalog', description: 'Catalog API')]
abstract class Controller
{
    //
}
