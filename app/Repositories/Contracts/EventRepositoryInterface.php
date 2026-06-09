<?php

namespace App\Repositories\Contracts;

use App\DTOs\Event\CreateEventData;
use App\DTOs\Event\ListEventsFilter;
use App\DTOs\Event\UpdateEventData;
use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface EventRepositoryInterface
{
    public function create(CreateEventData $data): Event;

    public function update(Event $event, UpdateEventData $data, int $confirmedBookingsCount): Event;

    public function delete(Event $event): void;

    public function list(ListEventsFilter $filter): LengthAwarePaginator;

    /**
     * @return Collection<int, Event>
     */
    public function findConflicting(Carbon $start, Carbon $end, ?int $excludeId = null): Collection;

    public function hasConfirmedBookings(Event $event): bool;

    public function confirmedBookingsCount(Event $event): int;

    public function activeBookingsCount(Event $event): int;

    public function decrementAvailableSlots(Event $event): void;

    public function incrementAvailableSlots(Event $event): void;

    public function lockForUpdate(Event $event): Event;
}
