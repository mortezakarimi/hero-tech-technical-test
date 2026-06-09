<?php

use App\Models\Event;
use Illuminate\Support\Carbon;

describe('Event Basic Validation', function () {

    test('it creates an event with valid data', function () {
        $payload = [
            'title' => 'Laravel 13 Meetup',
            'description' => 'A deep dive into the new features.',
            'total_capacity' => 100,
            'start_time' => now()->addDays(2)->toDateTimeString(),
            'end_time' => now()->addDays(2)->addHours(3)->toDateTimeString(),
        ];

        $response = $this->postJson('/api/v1/events', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Laravel 13 Meetup')
            ->assertJsonPath('data.available_slots', 100);

        $this->assertDatabaseHas('events', [
            'title' => 'Laravel 13 Meetup',
            'total_capacity' => 100,
            'available_slots' => 100,
        ]);
    });

    test('it requires a title, total_capacity, start_time, and end_time', function () {
        $response = $this->postJson('/api/v1/events', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'total_capacity', 'start_time', 'end_time']);
    });

    test('description is optional', function () {
        $payload = [
            'title' => 'No Description Event',
            'total_capacity' => 50,
            'start_time' => now()->addDays(2)->toDateTimeString(),
            'end_time' => now()->addDays(2)->addHours(2)->toDateTimeString(),
        ];

        $this->postJson('/api/v1/events', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'No Description Event');
    });

    test('title rejects strings over 255 characters', function () {
        $payload = Event::factory()->make([
            'title' => str_repeat('a', 256),
        ])->toArray();

        $this->postJson('/api/v1/events', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    });

    test('total_capacity must be a positive integer', function ($invalidCapacity) {
        $payload = Event::factory()->make(['total_capacity' => $invalidCapacity])->toArray();

        $response = $this->postJson('/api/v1/events', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['total_capacity']);
    })->with([
        0,
        -50,
        'string',
        2.5,
    ]);
});

describe('Event Date & Time Edge Cases', function () {

    test('start_time must be a date in the future', function () {
        $payload = Event::factory()->make([
            'start_time' => now()->subDay()->toDateTimeString(),
            'end_time' => now()->addDay()->toDateTimeString(),
        ])->toArray();

        $response = $this->postJson('/api/v1/events', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_time']);
    });

    test('end_time must strictly be after start_time', function () {
        $start = now()->addDays(2);

        $payload = Event::factory()->make([
            'start_time' => $start->toDateTimeString(),
            'end_time' => $start->copy()->subHour()->toDateTimeString(),
        ])->toArray();

        $response = $this->postJson('/api/v1/events', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_time']);
    });

    test('end_time cannot be exactly the same as start_time', function () {
        $time = now()->addDays(2)->toDateTimeString();

        $payload = Event::factory()->make([
            'start_time' => $time,
            'end_time' => $time,
        ])->toArray();

        $response = $this->postJson('/api/v1/events', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_time']);
    });
});

describe('Event Conflict Edge Cases', function () {

    beforeEach(function () {
        $this->baseStart = Carbon::tomorrow()->setTime(14, 0);
        $this->baseEnd = Carbon::tomorrow()->setTime(16, 0);

        Event::factory()->create([
            'start_time' => $this->baseStart,
            'end_time' => $this->baseEnd,
        ]);
    });

    test('it prevents scheduling an event that starts during an existing event', function () {
        $payload = Event::factory()->make([
            'start_time' => $this->baseStart->copy()->addHour(),
            'end_time' => $this->baseEnd->copy()->addHour(),
        ])->toArray();

        $this->postJson('/api/v1/events', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_time']);
    });

    test('it prevents scheduling an event that ends during an existing event', function () {
        $payload = Event::factory()->make([
            'start_time' => $this->baseStart->copy()->subHour(),
            'end_time' => $this->baseStart->copy()->addHour(),
        ])->toArray();

        $this->postJson('/api/v1/events', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_time']);
    });

    test('it prevents an event that completely encapsulates an existing event', function () {
        $payload = Event::factory()->make([
            'start_time' => $this->baseStart->copy()->subHour(),
            'end_time' => $this->baseEnd->copy()->addHour(),
        ])->toArray();

        $this->postJson('/api/v1/events', $payload)
            ->assertStatus(422);
    });

    test('it prevents an event completely inside an existing event timeframe', function () {
        $payload = Event::factory()->make([
            'start_time' => $this->baseStart->copy()->addMinutes(15),
            'end_time' => $this->baseEnd->copy()->subMinutes(15),
        ])->toArray();

        $this->postJson('/api/v1/events', $payload)
            ->assertStatus(422);
    });

    test('it allows events that abut each other but do not overlap', function () {
        $payloadAfter = Event::factory()->make([
            'start_time' => $this->baseEnd,
            'end_time' => $this->baseEnd->copy()->addHours(2),
        ])->toArray();

        $payloadBefore = Event::factory()->make([
            'start_time' => $this->baseStart->copy()->subHours(2),
            'end_time' => $this->baseStart,
        ])->toArray();

        $this->postJson('/api/v1/events', $payloadAfter)->assertStatus(201);
        $this->postJson('/api/v1/events', $payloadBefore)->assertStatus(201);
    });
});
