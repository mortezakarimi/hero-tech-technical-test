<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Event;

describe('Delete Event Edge Cases', function () {

    beforeEach(function () {
        $this->admin = actingAsAdmin();
    });

    test('it can delete an event with no bookings', function () {
        $event = Event::factory()->for($this->admin)->create();

        $this->deleteJson("/api/v1/events/{$event->id}")->assertStatus(204);

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    });

    test('it cannot delete an event that has active confirmed bookings', function () {
        $event = Event::factory()->for($this->admin)->create();

        Booking::factory()->create([
            'event_id' => $event->id,
            'status' => BookingStatus::CONFIRMED,
        ]);

        $this->deleteJson("/api/v1/events/{$event->id}")
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'Cannot delete event with active bookings.']);

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    });

    test('it returns 404 when deleting a non-existent event', function () {
        $this->deleteJson('/api/v1/events/9999')
            ->assertStatus(404);
    });

    test('it can delete an event with only pending bookings', function () {
        $event = Event::factory()->for($this->admin)->create();

        Booking::factory()->create([
            'event_id' => $event->id,
            'status' => BookingStatus::PENDING,
        ]);

        $this->deleteJson("/api/v1/events/{$event->id}")->assertStatus(204);

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    });

    test('it can delete an event if all bookings are cancelled', function () {
        $event = Event::factory()->for($this->admin)->create();

        Booking::factory()->create([
            'event_id' => $event->id,
            'status' => BookingStatus::CANCELLED,
        ]);

        $this->deleteJson("/api/v1/events/{$event->id}")->assertStatus(204);

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    });
});
