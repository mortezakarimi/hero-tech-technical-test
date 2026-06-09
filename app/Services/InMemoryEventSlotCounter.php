<?php

namespace App\Services;

use App\Services\Contracts\EventSlotCounterInterface;

class InMemoryEventSlotCounter implements EventSlotCounterInterface
{
    /** @var array<int, int> */
    private static array $slots = [];

    public function seedSlots(int $eventId, int $capacity): void
    {
        self::$slots[$eventId] = $capacity;
    }

    public function reserveSlot(int $eventId): bool
    {
        if (! isset(self::$slots[$eventId])) {
            return false;
        }

        self::$slots[$eventId]--;

        if (self::$slots[$eventId] < 0) {
            self::$slots[$eventId]++;

            return false;
        }

        return true;
    }

    public function releaseSlot(int $eventId): void
    {
        if (isset(self::$slots[$eventId])) {
            self::$slots[$eventId]++;
        }
    }

    public function getRemainingSlots(int $eventId): int
    {
        return self::$slots[$eventId] ?? 0;
    }

    public function resyncSlots(int $eventId, int $remainingSlots): void
    {
        self::$slots[$eventId] = max(0, $remainingSlots);
    }

    public static function reset(): void
    {
        self::$slots = [];
    }
}
