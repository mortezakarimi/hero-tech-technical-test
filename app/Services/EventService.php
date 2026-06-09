<?php

namespace App\Services;

use App\DTOs\Event\CreateEventData;
use App\DTOs\Event\ListEventsFilter;
use App\DTOs\Event\UpdateEventData;
use App\Models\Event;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Services\Contracts\EventSlotCounterInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Exceptions\HttpResponseException;

class EventService
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventSlotCounterInterface $slotCounter,
    ) {}

    public function create(CreateEventData $data): Event
    {
        $event = $this->eventRepository->create($data);

        $this->slotCounter->seedSlots($event->id, $event->total_capacity);

        return $event;
    }

    public function update(Event $event, UpdateEventData $data): Event
    {
        $confirmedCount = $this->eventRepository->confirmedBookingsCount($event);

        $updatedEvent = $this->eventRepository->update($event, $data, $confirmedCount);

        $activeCount = $this->eventRepository->activeBookingsCount($updatedEvent);
        $remainingSlots = $data->totalCapacity - $activeCount;
        $this->slotCounter->resyncSlots($updatedEvent->id, $remainingSlots);

        return $updatedEvent;
    }

    public function delete(Event $event): void
    {
        if ($this->eventRepository->hasConfirmedBookings($event)) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'Cannot delete event with active bookings.',
                ], 409)
            );
        }

        $this->eventRepository->delete($event);
    }

    public function list(ListEventsFilter $filter): LengthAwarePaginator
    {
        return $this->eventRepository->list($filter);
    }

    public function find(Event $event): Event
    {
        return $event;
    }
}
