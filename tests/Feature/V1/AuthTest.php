<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Auth::forgetGuards();

    Route::middleware(['auth:sanctum', 'admin'])->get('/api/v1/_test/admin', fn () => response()->noContent());
});

it('registers a user and returns a token with user role', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'mobile-app',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']])
        ->assertJsonPath('user.role', 'user');

    $this->assertDatabaseHas('users', [
        'email' => 'jane@example.com',
        'role' => 'user',
    ]);
});

it('logs in a regular user with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'user@example.com',
        'password' => 'password',
        'device_name' => 'postman',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['token', 'user'])
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.role', 'user');
});

it('logs in an admin with valid credentials', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
        'device_name' => 'postman',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('user.id', $admin->id)
        ->assertJsonPath('user.role', 'admin');
});

it('fails login with invalid credentials', function () {
    User::factory()->create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'user@example.com',
        'password' => 'wrong-password',
        'device_name' => 'postman',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns the authenticated user profile', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/v1/me');

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.role', 'user');
});

it('requires authentication to access me endpoint', function () {
    $response = $this->getJson('/api/v1/me');

    $response->assertUnauthorized();
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-device', ['*'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/logout')
        ->assertNoContent();

    Auth::forgetGuards();

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});

it('denies non-admin users via admin middleware', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/_test/admin')
        ->assertForbidden();
});

it('allows admin users via admin middleware', function () {
    $admin = User::factory()->admin()->create();

    Sanctum::actingAs($admin, ['*']);

    $this->getJson('/api/v1/_test/admin')
        ->assertNoContent();
});
