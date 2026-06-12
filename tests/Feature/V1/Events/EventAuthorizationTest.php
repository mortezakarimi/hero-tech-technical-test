<?php

use App\Models\Event;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('Event Authorization', function () {
    test('unauthenticated users cannot create events', function () {
        $this->postJson('/api/v1/events', [
            'title' => 'Unauthorized Event',
            'total_capacity' => 10,
            'start_time' => now()->addDay()->toDateTimeString(),
            'end_time' => now()->addDays(1)->addHours(2)->toDateTimeString(),
        ])->assertUnauthorized();
    });

    test('unauthenticated users cannot update events', function () {
        $event = Event::factory()->create();

        $this->putJson("/api/v1/events/{$event->id}", [
            'title' => 'Hacked Title',
            'total_capacity' => $event->total_capacity,
            'start_time' => $event->start_time->toDateTimeString(),
            'end_time' => $event->end_time->toDateTimeString(),
        ])->assertUnauthorized();
    });

    test('unauthenticated users cannot delete events', function () {
        $event = Event::factory()->create();

        $this->deleteJson("/api/v1/events/{$event->id}")->assertUnauthorized();
    });

    test('regular users cannot create events', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/events', [
            'title' => 'User Event',
            'total_capacity' => 10,
            'start_time' => now()->addDay()->toDateTimeString(),
            'end_time' => now()->addDays(1)->addHours(2)->toDateTimeString(),
        ])
            ->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.'])
            ->assertJsonMissing(['exception', 'trace']);
    });

    test('regular users cannot update events', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $this->putJson("/api/v1/events/{$event->id}", [
            'title' => 'User Update',
            'total_capacity' => $event->total_capacity,
            'start_time' => $event->start_time->toDateTimeString(),
            'end_time' => $event->end_time->toDateTimeString(),
        ])->assertForbidden();
    });

    test('regular users cannot delete events', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $this->deleteJson("/api/v1/events/{$event->id}")->assertForbidden();
    });

    test('regular users can list events', function () {
        Event::factory()->count(2)->create();

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/events')
            ->assertSuccessful()
            ->assertJsonCount(2, 'data');
    });

    test('regular users can view a single event', function () {
        $event = Event::factory()->create(['title' => 'Public View Event']);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/v1/events/{$event->id}")
            ->assertSuccessful()
            ->assertJsonPath('data.title', 'Public View Event');
    });

    test('admin can update their own event', function () {
        $admin = actingAsAdmin();
        $event = Event::factory()->for($admin)->create();

        $this->putJson("/api/v1/events/{$event->id}", [
            'title' => 'Admin Updated',
            'total_capacity' => $event->total_capacity,
            'start_time' => $event->start_time->toDateTimeString(),
            'end_time' => $event->end_time->toDateTimeString(),
        ])->assertSuccessful()
            ->assertJsonPath('data.title', 'Admin Updated');
    });

    test('admin can delete their own event', function () {
        $admin = actingAsAdmin();
        $event = Event::factory()->for($admin)->create();

        $this->deleteJson("/api/v1/events/{$event->id}")->assertNoContent();
    });

    test('admin cannot update another admins event', function () {
        $owner = User::factory()->admin()->create();
        $otherAdmin = actingAsAdmin();
        $event = Event::factory()->for($owner)->create();

        $this->putJson("/api/v1/events/{$event->id}", [
            'title' => 'Forbidden Update',
            'total_capacity' => $event->total_capacity,
            'start_time' => $event->start_time->toDateTimeString(),
            'end_time' => $event->end_time->toDateTimeString(),
        ])->assertForbidden();

        expect($otherAdmin->id)->not->toBe($owner->id);
    });

    test('admin cannot delete another admins event', function () {
        $owner = User::factory()->admin()->create();
        actingAsAdmin();
        $event = Event::factory()->for($owner)->create();

        $this->deleteJson("/api/v1/events/{$event->id}")->assertForbidden();

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    });
});
