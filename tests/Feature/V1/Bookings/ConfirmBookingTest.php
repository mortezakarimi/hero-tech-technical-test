<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Services\InMemoryEventSlotCounter;
use Laravel\Sanctum\Sanctum;

describe('Confirm Booking', function () {

    beforeEach(function () {
        InMemoryEventSlotCounter::reset();

        $this->user = User::factory()->create();
        $this->event = Event::factory()->create([
            'total_capacity' => 10,
            'available_slots' => 10,
        ]);

        seedEventSlots($this->event, 9);

        $this->booking = Booking::factory()->pending()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);
    });

    test('it confirms a pending booking for the owner', function () {
        $response = $this->postJson("/api/v1/bookings/{$this->booking->id}/confirm");

        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'status' => BookingStatus::CONFIRMED->value,
        ]);

        expect($this->event->fresh()->available_slots)->toBe(9);
    });

    test('it denies confirmation for another user', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser, ['*']);

        $this->postJson("/api/v1/bookings/{$this->booking->id}/confirm")
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'You are not authorized to confirm this booking.']);
    });

    test('it rejects confirmation of an already confirmed booking', function () {
        $this->booking->update(['status' => BookingStatus::CONFIRMED]);

        $this->postJson("/api/v1/bookings/{$this->booking->id}/confirm")
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'Only pending bookings can be confirmed.']);
    });

    test('it rejects confirmation when no confirmed slots remain', function () {
        $this->event->update(['available_slots' => 0]);

        $this->postJson("/api/v1/bookings/{$this->booking->id}/confirm")
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'Event is full.']);
    });
});
