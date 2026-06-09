<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Services\Contracts\EventSlotCounterInterface;
use App\Services\InMemoryEventSlotCounter;
use Laravel\Sanctum\Sanctum;

describe('Cancel Booking', function () {

    beforeEach(function () {
        InMemoryEventSlotCounter::reset();

        $this->user = User::factory()->create();
        $this->event = Event::factory()->create([
            'total_capacity' => 10,
            'available_slots' => 10,
        ]);

        seedEventSlots($this->event, 9);

        Sanctum::actingAs($this->user, ['*']);
    });

    test('it cancels a pending booking and restores the reserved slot', function () {
        $booking = Booking::factory()->pending()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        $this->deleteJson("/api/v1/bookings/{$booking->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CANCELLED->value,
        ]);

        expect(app(EventSlotCounterInterface::class)->getRemainingSlots($this->event->id))->toBe(10);
        expect($this->event->fresh()->available_slots)->toBe(10);
    });

    test('it cancels a confirmed booking and restores available slots', function () {
        $this->event->update(['available_slots' => 9]);

        $booking = Booking::factory()->confirmed()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        $this->deleteJson("/api/v1/bookings/{$booking->id}")
            ->assertNoContent();

        expect($this->event->fresh()->available_slots)->toBe(10);
        expect(app(EventSlotCounterInterface::class)->getRemainingSlots($this->event->id))->toBe(10);
    });

    test('it denies cancellation for another user', function () {
        $booking = Booking::factory()->pending()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser, ['*']);

        $this->deleteJson("/api/v1/bookings/{$booking->id}")
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'You are not authorized to cancel this booking.']);
    });

    test('it rejects cancelling an already cancelled booking', function () {
        $booking = Booking::factory()->cancelled()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        $this->deleteJson("/api/v1/bookings/{$booking->id}")
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'Booking is already cancelled.']);
    });
});
