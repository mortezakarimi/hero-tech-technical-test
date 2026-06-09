<?php

namespace App\DTOs\Booking;

use App\Enums\BookingStatus;
use Illuminate\Http\Request;

readonly class ListBookingsFilter
{
    public const int DEFAULT_PER_PAGE = 15;

    public const int MAX_PER_PAGE = 100;

    public function __construct(
        public int $userId,
        public ?BookingStatus $status = null,
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {}

    public static function fromRequest(Request $request, int $userId): self
    {
        $perPage = (int) $request->input('per_page', self::DEFAULT_PER_PAGE);
        $perPage = min(max($perPage, 1), self::MAX_PER_PAGE);

        $status = $request->input('filter.status');
        $statusEnum = $status !== null ? BookingStatus::tryFrom($status) : null;

        return new self(
            userId: $userId,
            status: $statusEnum,
            perPage: $perPage,
        );
    }
}
