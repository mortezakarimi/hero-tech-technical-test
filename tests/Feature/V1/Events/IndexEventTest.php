<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Event;

describe('View Events List (Index)', function () {
    beforeEach(function () {
        $this->pastEvent = Event::factory()->create([
            'title' => 'Legacy PHP Conference',
            'start_time' => now()->subDays(5),
            'end_time' => now()->subDays(4),
            'total_capacity' => 100,
            'available_slots' => 100,
        ]);

        $this->availableEvent = Event::factory()->create([
            'title' => 'Laravel 13 Meetup',
            'description' => 'A deep dive into new features.',
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHours(3),
            'total_capacity' => 50,
            'available_slots' => 49,
        ]);

        Booking::factory()->create([
            'event_id' => $this->availableEvent->id,
            'status' => BookingStatus::CONFIRMED,
        ]);

        $this->soldOutEvent = Event::factory()->create([
            'title' => 'Go Kubernetes Workshop',
            'description' => 'Mastering scaling.',
            'start_time' => now()->addDays(5),
            'end_time' => now()->addDays(6),
            'total_capacity' => 2,
            'available_slots' => 0,
        ]);

        Booking::factory()->count(2)->create([
            'event_id' => $this->soldOutEvent->id,
            'status' => BookingStatus::CONFIRMED,
        ]);
    });

    test('it can filter events by upcoming dates only', function () {
        $response = $this->getJson('/api/v1/events?filter[upcoming]=true');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['id' => $this->pastEvent->id])
            ->assertJsonFragment(['id' => $this->availableEvent->id])
            ->assertJsonFragment(['id' => $this->soldOutEvent->id]);
    });

    test('it can filter out sold out events to only show available ones', function () {
        $response = $this->getJson('/api/v1/events?filter[has_spots]=true');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['id' => $this->soldOutEvent->id]);
    });

    test('it can search by title or description', function () {
        $response = $this->getJson('/api/v1/events?search=Kubernetes');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $this->soldOutEvent->id])
            ->assertJsonMissing(['id' => $this->availableEvent->id]);
    });

    test('it can filter events by a specific date range', function () {
        $from = now()->addDays(1)->toDateString();
        $to = now()->addDays(3)->toDateString();

        $response = $this->getJson("/api/v1/events?filter[start_from]={$from}&filter[start_to]={$to}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $this->availableEvent->id])
            ->assertJsonMissing(['id' => $this->soldOutEvent->id])
            ->assertJsonMissing(['id' => $this->pastEvent->id]);
    });

    test('it can sort events by start_time ascending (closest first)', function () {
        $response = $this->getJson('/api/v1/events?sort=start_time');

        $response->assertStatus(200);

        $dataIds = collect($response->json('data'))->pluck('id')->toArray();

        expect($dataIds)->toBe([
            $this->pastEvent->id,
            $this->availableEvent->id,
            $this->soldOutEvent->id,
        ]);
    });

    test('it returns an empty list when no events exist', function () {
        Event::query()->forceDelete();

        $this->getJson('/api/v1/events')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    });

    test('it can apply upcoming and has_spots filters together', function () {
        $response = $this->getJson('/api/v1/events?filter[upcoming]=true&filter[has_spots]=true');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $this->availableEvent->id])
            ->assertJsonMissing(['id' => $this->pastEvent->id])
            ->assertJsonMissing(['id' => $this->soldOutEvent->id]);
    });

    test('pending bookings do not affect available_slots in the index payload', function () {
        $event = Event::factory()->create([
            'total_capacity' => 10,
            'available_slots' => 10,
        ]);

        Booking::factory()->create([
            'event_id' => $event->id,
            'status' => BookingStatus::PENDING,
        ]);

        $this->getJson('/api/v1/events')
            ->assertStatus(200)
            ->assertJsonFragment([
                'id' => $event->id,
                'available_slots' => 10,
            ]);
    });

    test('it returns available_slots in the index payload', function () {
        $response = $this->getJson('/api/v1/events');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $this->availableEvent->id,
                'total_capacity' => 50,
                'available_slots' => 49,
            ])
            ->assertJsonFragment([
                'id' => $this->soldOutEvent->id,
                'total_capacity' => 2,
                'available_slots' => 0,
            ]);
    });

    test('it paginates events with meta and links', function () {
        Event::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/events?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 23);
    });

    test('it can navigate to a specific page', function () {
        Event::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/events?per_page=10&page=2');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3);
    });

    test('it applies filters before paginating results', function () {
        Event::factory()->count(5)->create([
            'start_time' => now()->addDays(10),
            'end_time' => now()->addDays(10)->addHours(2),
        ]);

        $response = $this->getJson('/api/v1/events?filter[upcoming]=true&per_page=2');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 7)
            ->assertJsonPath('meta.per_page', 2);
    });
});
