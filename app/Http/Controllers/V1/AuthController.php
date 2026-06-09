<?php

namespace App\Http\Controllers\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AuthTokenResponse',
    required: ['token', 'user'],
    properties: [
        new OA\Property(property: 'token', type: 'string', example: '1|plainTextToken...'),
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
    ]
)]
class AuthController extends Controller
{
    #[OA\Post(
        path: '/v1/register',
        operationId: 'register',
        description: 'Creates a new user account with the user role and returns a Sanctum API token.',
        summary: 'Register a new user',
        security: [],
        tags: ['auth'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/RegisterRequest')
    )]
    #[OA\Response(
        response: 201,
        description: 'User registered successfully',
        content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenResponse')
    )]
    #[OA\Response(response: 422, description: 'Validation error')]
    #[OA\Response(response: 429, description: 'Too many requests')]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => UserRole::User,
        ]);

        $token = $user->createToken($request->validated('device_name'));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($user),
        ], 201);
    }

    #[OA\Post(
        path: '/v1/login',
        operationId: 'login',
        description: 'Authenticates an admin or user and returns a Sanctum API token.',
        summary: 'Login',
        security: [],
        tags: ['auth'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest')
    )]
    #[OA\Response(
        response: 200,
        description: 'Login successful',
        content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenResponse')
    )]
    #[OA\Response(response: 422, description: 'Invalid credentials or validation error')]
    #[OA\Response(response: 429, description: 'Too many requests')]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken($request->validated('device_name'), ['*']);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    #[OA\Post(
        path: '/v1/logout',
        operationId: 'logout',
        description: 'Revokes the current Sanctum API token.',
        summary: 'Logout',
        tags: ['auth'],
    )]
    #[OA\Response(response: 204, description: 'Token revoked successfully')]
    #[OA\Response(response: 401, description: 'Unauthenticated')]
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    #[OA\Get(
        path: '/v1/me',
        operationId: 'me',
        description: 'Returns the profile of the currently authenticated user.',
        summary: 'Get authenticated user',
        tags: ['auth'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Authenticated user profile',
        content: new OA\JsonContent(
            required: ['data'],
            properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/User'),
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthenticated')]
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
