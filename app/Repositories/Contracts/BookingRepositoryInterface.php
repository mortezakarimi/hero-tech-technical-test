<?php

namespace App\Repositories\Contracts;

use App\DTOs\Booking\CreateBookingData;
use App\DTOs\Booking\ListBookingsFilter;
use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BookingRepositoryInterface
{
    public function create(CreateBookingData $data, BookingStatus $status): Booking;

    public function findActiveForUserAndEvent(int $userId, int $eventId): ?Booking;

    public function lockForUpdate(Booking $booking): Booking;

    public function updateStatus(Booking $booking, BookingStatus $status): Booking;

    public function listForUser(ListBookingsFilter $filter): LengthAwarePaginator;
}
