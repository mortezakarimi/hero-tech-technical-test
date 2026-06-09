<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

describe('List Bookings', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->event = Event::factory()->create();

        Booking::factory()->pending()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        Booking::factory()->confirmed()->create([
            'user_id' => $this->user->id,
            'event_id' => Event::factory()->create()->id,
        ]);

        Booking::factory()->pending()->create([
            'user_id' => $this->otherUser->id,
            'event_id' => $this->event->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);
    });

    test('it lists only the authenticated user bookings', function () {
        $response = $this->getJson('/api/v1/bookings');

        $response->assertSuccessful()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $userIds = collect($response->json('data'))->pluck('user_id')->unique()->values()->all();

        expect($userIds)->toBe([$this->user->id]);
    });

    test('it filters bookings by status', function () {
        $response = $this->getJson('/api/v1/bookings?filter[status]=pending');

        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', BookingStatus::PENDING->value);
    });

    test('it requires authentication to list bookings', function () {
        Auth::forgetGuards();

        $this->getJson('/api/v1/bookings')
            ->assertUnauthorized();
    });
});
