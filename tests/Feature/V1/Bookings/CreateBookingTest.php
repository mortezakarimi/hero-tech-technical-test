<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Services\Contracts\EventSlotCounterInterface;
use App\Services\InMemoryEventSlotCounter;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

describe('Create Booking', function () {

    beforeEach(function () {
        InMemoryEventSlotCounter::reset();

        $this->user = User::factory()->create();
        $this->event = Event::factory()->create([
            'total_capacity' => 10,
            'available_slots' => 10,
        ]);

        seedEventSlots($this->event);

        Sanctum::actingAs($this->user, ['*']);
    });

    test('it books an event for an authenticated user', function () {
        $response = $this->postJson("/api/v1/events/{$this->event->id}/bookings");

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.event_id', $this->event->id)
            ->assertJsonPath('data.user_id', $this->user->id);

        $this->assertDatabaseHas('bookings', [
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'status' => BookingStatus::PENDING->value,
        ]);

        expect(app(EventSlotCounterInterface::class)->getRemainingSlots($this->event->id))->toBe(9);
    });

    test('it requires authentication to book an event', function () {
        Auth::forgetGuards();

        $this->postJson("/api/v1/events/{$this->event->id}/bookings")
            ->assertUnauthorized();
    });

    test('it rejects booking when the event is sold out', function () {
        seedEventSlots($this->event, 0);

        $this->postJson("/api/v1/events/{$this->event->id}/bookings")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Event is full.']);

        $this->assertDatabaseMissing('bookings', [
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);
    });

    test('it rejects duplicate active bookings for the same user and event', function () {
        Booking::factory()->pending()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        $this->postJson("/api/v1/events/{$this->event->id}/bookings")
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'You already have an active booking for this event.']);
    });

    test('it allows re-booking after a cancelled booking', function () {
        Booking::factory()->cancelled()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        $this->postJson("/api/v1/events/{$this->event->id}/bookings")
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');
    });

    test('it returns 404 when booking a non-existent event', function () {
        $this->postJson('/api/v1/events/9999/bookings')
            ->assertNotFound();
    });
});
