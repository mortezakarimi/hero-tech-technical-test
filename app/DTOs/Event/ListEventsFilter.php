<?php

namespace App\DTOs\Event;

use Illuminate\Http\Request;

readonly class ListEventsFilter
{
    public const int DEFAULT_PER_PAGE = 15;

    public const int MAX_PER_PAGE = 100;

    public function __construct(
        public bool $upcoming = false,
        public bool $hasSpots = false,
        public ?string $startFrom = null,
        public ?string $startTo = null,
        public ?string $search = null,
        public ?string $sort = null,
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $perPage = (int) $request->input('per_page', self::DEFAULT_PER_PAGE);
        $perPage = min(max($perPage, 1), self::MAX_PER_PAGE);

        return new self(
            upcoming: filter_var($request->input('filter.upcoming'), FILTER_VALIDATE_BOOLEAN),
            hasSpots: filter_var($request->input('filter.has_spots'), FILTER_VALIDATE_BOOLEAN),
            startFrom: $request->input('filter.start_from'),
            startTo: $request->input('filter.start_to'),
            search: $request->input('search'),
            sort: $request->input('sort'),
            perPage: $perPage,
        );
    }
}
