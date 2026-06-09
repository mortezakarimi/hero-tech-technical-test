<?php

namespace App\Services;

use App\Services\Contracts\EventSlotCounterInterface;
use Illuminate\Support\Facades\Redis;

class RedisEventSlotCounter implements EventSlotCounterInterface
{
    private function key(int $eventId): string
    {
        return "event:{$eventId}:slots";
    }

    public function seedSlots(int $eventId, int $capacity): void
    {
        Redis::set($this->key($eventId), $capacity);
    }

    public function reserveSlot(int $eventId): bool
    {
        $remaining = Redis::decr($this->key($eventId));

        if ($remaining < 0) {
            Redis::incr($this->key($eventId));

            return false;
        }

        return true;
    }

    public function releaseSlot(int $eventId): void
    {
        Redis::incr($this->key($eventId));
    }

    public function getRemainingSlots(int $eventId): int
    {
        return (int) Redis::get($this->key($eventId));
    }

    public function resyncSlots(int $eventId, int $remainingSlots): void
    {
        Redis::set($this->key($eventId), max(0, $remainingSlots));
    }
}
