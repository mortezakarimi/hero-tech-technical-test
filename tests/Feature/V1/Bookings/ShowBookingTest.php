<?php

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

describe('Show Booking', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->event = Event::factory()->create();

        $this->booking = Booking::factory()->pending()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);
    });

    test('it shows a booking for the owner', function () {
        $response = $this->getJson("/api/v1/bookings/{$this->booking->id}");

        $response->assertSuccessful()
            ->assertJsonPath('data.id', $this->booking->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.event_id', $this->event->id);
    });

    test('it denies viewing another user booking', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser, ['*']);

        $this->getJson("/api/v1/bookings/{$this->booking->id}")
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'You are not authorized to view this booking.']);
    });

    test('it requires authentication to view a booking', function () {
        Auth::forgetGuards();

        $this->getJson("/api/v1/bookings/{$this->booking->id}")
            ->assertUnauthorized();
    });
});
