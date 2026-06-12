<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Attributes as OA;

#[OA\OpenApi(openapi: OA\OpenApi::VERSION_3_1_0, security: [['sanctum' => []]])]
#[OA\Info(
    version: '1.0.0',
    title: 'HeroTech Event Booking API',
    description: 'Versioned REST API for event management and concurrent booking.',
    attachables: [new OA\Attachable]
)]
#[OA\License(name: 'MIT', identifier: 'MIT')]
#[OA\Server(url: '/api', description: 'API server')]
#[OA\SecurityScheme(securityScheme: 'sanctum', type: 'http', description: 'Sanctum bearer token', bearerFormat: 'JWT', scheme: 'bearer')]
#[OA\Tag(name: 'auth', description: 'Authentication endpoints')]
#[OA\Tag(name: 'events', description: 'Event management endpoints')]
#[OA\Tag(name: 'bookings', description: 'Booking and payment endpoints')]
abstract class Controller
{
    use AuthorizesRequests;
}
