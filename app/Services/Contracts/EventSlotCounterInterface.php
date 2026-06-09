<?php

namespace App\Services\Contracts;

interface EventSlotCounterInterface
{
    public function seedSlots(int $eventId, int $capacity): void;

    public function reserveSlot(int $eventId): bool;

    public function releaseSlot(int $eventId): void;

    public function getRemainingSlots(int $eventId): int;

    public function resyncSlots(int $eventId, int $remainingSlots): void;
}
