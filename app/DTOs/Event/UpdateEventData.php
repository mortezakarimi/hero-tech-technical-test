<?php

namespace App\DTOs\Event;

use Illuminate\Support\Carbon;

readonly class UpdateEventData
{
    public function __construct(
        public string $title,
        public ?string $description,
        public int $totalCapacity,
        public Carbon $startTime,
        public Carbon $endTime,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            title: $validated['title'],
            description: $validated['description'] ?? null,
            totalCapacity: (int) $validated['total_capacity'],
            startTime: Carbon::parse($validated['start_time']),
            endTime: Carbon::parse($validated['end_time']),
        );
    }
}
