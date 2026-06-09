<?php

namespace App\DTOs\Booking;

readonly class CreateBookingData
{
    public function __construct(
        public int $userId,
        public int $eventId,
    ) {}
}
