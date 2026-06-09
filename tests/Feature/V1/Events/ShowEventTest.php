<?php

use App\Models\Event;

describe('View Events (Show)', function () {

    test('it can view details of a specific event', function () {
        $event = Event::factory()->create([
            'title' => 'Go Kubernetes Workshop',
            'total_capacity' => 50,
        ]);

        $response = $this->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Go Kubernetes Workshop')
            ->assertJsonPath('data.total_capacity', 50);
    });

    test('it returns 404 when viewing a non-existent event', function () {
        $this->getJson('/api/v1/events/9999')->assertStatus(404);
    });

    test('it returns 404 when viewing a soft-deleted event', function () {
        $event = Event::factory()->create();
        $event->delete();

        $this->getJson("/api/v1/events/{$event->id}")->assertStatus(404);
    });
});
