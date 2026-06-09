<?php

namespace App\DTOs\Event;

use Illuminate\Support\Carbon;

readonly class CreateEventData
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'total_capacity' => $this->totalCapacity,
            'available_slots' => $this->totalCapacity,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
        ];
    }
}
