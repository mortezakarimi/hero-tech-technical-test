<?php

namespace App\Repositories;

use App\DTOs\Booking\CreateBookingData;
use App\DTOs\Booking\ListBookingsFilter;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Repositories\Contracts\BookingRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BookingRepository implements BookingRepositoryInterface
{
    public function create(CreateBookingData $data, BookingStatus $status): Booking
    {
        return Booking::query()->create([
            'user_id' => $data->userId,
            'event_id' => $data->eventId,
            'status' => $status,
        ]);
    }

    public function findActiveForUserAndEvent(int $userId, int $eventId): ?Booking
    {
        return Booking::query()
            ->where('user_id', $userId)
            ->where('event_id', $eventId)
            ->active()
            ->first();
    }

    public function lockForUpdate(Booking $booking): Booking
    {
        return Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
    }

    public function updateStatus(Booking $booking, BookingStatus $status): Booking
    {
        $booking->update(['status' => $status]);

        return $booking->fresh();
    }

    public function listForUser(ListBookingsFilter $filter): LengthAwarePaginator
    {
        $query = Booking::query()
            ->where('user_id', $filter->userId)
            ->with('event')
            ->latest();

        if ($filter->status !== null) {
            $query->where('status', $filter->status);
        }

        return $query->paginate($filter->perPage)->withQueryString();
    }
}
