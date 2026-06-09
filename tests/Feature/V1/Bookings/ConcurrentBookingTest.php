<?php

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Services\InMemoryEventSlotCounter;
use Laravel\Sanctum\Sanctum;

describe('Concurrent Booking', function () {

    test('exactly one booking succeeds when ten users compete for one slot', function () {
        InMemoryEventSlotCounter::reset();

        $event = Event::factory()->create([
            'total_capacity' => 1,
            'available_slots' => 1,
        ]);

        seedEventSlots($event, 1);

        $users = User::factory()->count(10)->create();
        $statusCodes = [];

        foreach ($users as $user) {
            Sanctum::actingAs($user, ['*']);

            $response = $this->postJson("/api/v1/events/{$event->id}/bookings");
            $statusCodes[] = $response->status();
        }

        expect(collect($statusCodes)->filter(fn (int $status) => $status === 201)->count())->toBe(1);
        expect(collect($statusCodes)->filter(fn (int $status) => in_array($status, [422, 409], true))->count())->toBe(9);

        expect(Booking::query()->active()->where('event_id', $event->id)->count())->toBe(1);
    });

    test('exactly one confirmation succeeds when ten users confirm competing pending bookings', function () {
        InMemoryEventSlotCounter::reset();

        $event = Event::factory()->create([
            'total_capacity' => 1,
            'available_slots' => 1,
        ]);

        seedEventSlots($event, 0);

        $users = User::factory()->count(10)->create();

        foreach ($users as $user) {
            Booking::factory()->pending()->create([
                'user_id' => $user->id,
                'event_id' => $event->id,
            ]);
        }

        $statusCodes = [];

        foreach ($users as $user) {
            $booking = Booking::query()
                ->where('user_id', $user->id)
                ->where('event_id', $event->id)
                ->firstOrFail();

            Sanctum::actingAs($user, ['*']);

            $response = $this->postJson("/api/v1/bookings/{$booking->id}/confirm");
            $statusCodes[] = $response->status();
        }

        expect(collect($statusCodes)->filter(fn (int $status) => $status === 200)->count())->toBe(1);
        expect(collect($statusCodes)->filter(fn (int $status) => $status === 409)->count())->toBe(9);

        expect(Booking::query()->confirmed()->where('event_id', $event->id)->count())->toBe(1);
        expect($event->fresh()->available_slots)->toBe(0);
    });
});
