<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Event;

describe('Update Event Edge Cases', function () {

    beforeEach(function () {
        $this->admin = actingAsAdmin();

        $this->event = Event::factory()->for($this->admin)->create([
            'title' => 'Original Title',
            'total_capacity' => 100,
            'available_slots' => 100,
            'start_time' => now()->addDays(2)->setTime(10, 0),
            'end_time' => now()->addDays(2)->setTime(12, 0),
        ]);
    });

    test('it updates an event successfully', function () {
        $payload = [
            'title' => 'Updated Title',
            'description' => 'Updated description.',
            'total_capacity' => 150,
            'start_time' => $this->event->start_time->toDateTimeString(),
            'end_time' => $this->event->end_time->toDateTimeString(),
        ];

        $response = $this->putJson("/api/v1/events/{$this->event->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Title');

        $this->assertDatabaseHas('events', ['title' => 'Updated Title']);
    });

    test('it allows an event to update without triggering a self-conflict', function () {
        $payload = [
            'title' => 'New Title',
            'description' => $this->event->description,
            'total_capacity' => $this->event->total_capacity,
            'start_time' => $this->event->start_time->toDateTimeString(),
            'end_time' => $this->event->end_time->toDateTimeString(),
        ];

        $this->putJson("/api/v1/events/{$this->event->id}", $payload)
            ->assertStatus(200);
    });

    test('it prevents updating to a time that conflicts with another event', function () {
        $otherEvent = Event::factory()->create([
            'start_time' => now()->addDays(3)->setTime(14, 0),
            'end_time' => now()->addDays(3)->setTime(16, 0),
        ]);

        $payload = [
            'title' => $this->event->title,
            'description' => $this->event->description,
            'total_capacity' => $this->event->total_capacity,
            'start_time' => $otherEvent->start_time->copy()->addMinutes(30)->toDateTimeString(),
            'end_time' => $otherEvent->end_time->copy()->addMinutes(30)->toDateTimeString(),
        ];

        $this->putJson("/api/v1/events/{$this->event->id}", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_time']);
    });

    test('it returns 404 when updating a non-existent event', function () {
        $this->putJson('/api/v1/events/9999', [
            'title' => 'Ghost Event',
            'total_capacity' => 10,
            'start_time' => now()->addDay()->toDateTimeString(),
            'end_time' => now()->addDays(1)->addHours(2)->toDateTimeString(),
        ])->assertStatus(404);
    });

    test('it allows total_capacity exactly equal to confirmed booking count', function () {
        Booking::factory()->count(5)->create([
            'event_id' => $this->event->id,
            'status' => BookingStatus::CONFIRMED,
        ]);

        $payload = [
            'title' => $this->event->title,
            'description' => $this->event->description,
            'total_capacity' => 5,
            'start_time' => $this->event->start_time->toDateTimeString(),
            'end_time' => $this->event->end_time->toDateTimeString(),
        ];

        $this->putJson("/api/v1/events/{$this->event->id}", $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.total_capacity', 5)
            ->assertJsonPath('data.available_slots', 0);
    });

    test('pending bookings do not block lowering total_capacity', function () {
        Booking::factory()->count(3)->create([
            'event_id' => $this->event->id,
            'status' => BookingStatus::PENDING,
        ]);

        $payload = [
            'title' => $this->event->title,
            'description' => $this->event->description,
            'total_capacity' => 10,
            'start_time' => $this->event->start_time->toDateTimeString(),
            'end_time' => $this->event->end_time->toDateTimeString(),
        ];

        $this->putJson("/api/v1/events/{$this->event->id}", $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.total_capacity', 10);
    });

    test('it validates start_time must be in the future when changing times', function () {
        $payload = [
            'title' => $this->event->title,
            'description' => $this->event->description,
            'total_capacity' => $this->event->total_capacity,
            'start_time' => now()->subDay()->toDateTimeString(),
            'end_time' => now()->addDay()->toDateTimeString(),
        ];

        $this->putJson("/api/v1/events/{$this->event->id}", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_time']);
    });

    test('it cannot lower total_capacity below the number of current confirmed bookings', function () {
        Booking::factory()->count(5)->create([
            'event_id' => $this->event->id,
            'status' => BookingStatus::CONFIRMED,
        ]);

        $payload = [
            'title' => $this->event->title,
            'description' => $this->event->description,
            'total_capacity' => 3,
            'start_time' => $this->event->start_time->toDateTimeString(),
            'end_time' => $this->event->end_time->toDateTimeString(),
        ];

        $this->putJson("/api/v1/events/{$this->event->id}", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['total_capacity']);
    });
});
