<?php

namespace App\Repositories;

use App\DTOs\Event\CreateEventData;
use App\DTOs\Event\ListEventsFilter;
use App\DTOs\Event\UpdateEventData;
use App\Models\Event;
use App\Repositories\Contracts\EventRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EventRepository implements EventRepositoryInterface
{
    public function create(CreateEventData $data): Event
    {
        return Event::query()->create($data->toArray());
    }

    public function update(Event $event, UpdateEventData $data, int $confirmedBookingsCount): Event
    {
        $event->update([
            'title' => $data->title,
            'description' => $data->description,
            'total_capacity' => $data->totalCapacity,
            'available_slots' => $data->totalCapacity - $confirmedBookingsCount,
            'start_time' => $data->startTime,
            'end_time' => $data->endTime,
        ]);

        return $event->fresh();
    }

    public function delete(Event $event): void
    {
        $event->forceDelete();
    }

    public function list(ListEventsFilter $filter): LengthAwarePaginator
    {
        $query = Event::query();

        if ($filter->upcoming) {
            $query->where('start_time', '>=', now());
        }

        if ($filter->hasSpots) {
            $query->where('available_slots', '>', 0);
        }

        if ($filter->startFrom !== null) {
            $query->whereDate('start_time', '>=', $filter->startFrom);
        }

        if ($filter->startTo !== null) {
            $query->whereDate('start_time', '<=', $filter->startTo);
        }

        if ($filter->search !== null) {
            $query->where(function ($q) use ($filter): void {
                $q->where('title', 'like', "%{$filter->search}%")
                    ->orWhere('description', 'like', "%{$filter->search}%");
            });
        }

        if ($filter->sort === 'start_time') {
            $query->orderBy('start_time', 'asc');
        }

        return $query->paginate($filter->perPage)->withQueryString();
    }

    /**
     * @return Collection<int, Event>
     */
    public function findConflicting(Carbon $start, Carbon $end, ?int $excludeId = null): Collection
    {
        return Event::query()
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->get();
    }

    public function hasConfirmedBookings(Event $event): bool
    {
        return $event->bookings()->confirmed()->exists();
    }

    public function confirmedBookingsCount(Event $event): int
    {
        return $event->bookings()->confirmed()->count();
    }

    public function activeBookingsCount(Event $event): int
    {
        return $event->bookings()->active()->count();
    }

    public function decrementAvailableSlots(Event $event): void
    {
        $event->decrement('available_slots');
    }

    public function incrementAvailableSlots(Event $event): void
    {
        $event->increment('available_slots');
    }

    public function lockForUpdate(Event $event): Event
    {
        return Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
    }
}
