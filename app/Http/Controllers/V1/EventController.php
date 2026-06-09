<?php

namespace App\Http\Controllers\V1;

use App\DTOs\Event\CreateEventData;
use App\DTOs\Event\ListEventsFilter;
use App\DTOs\Event\UpdateEventData;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EventResource',
    required: ['id', 'title', 'total_capacity', 'available_slots'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', readOnly: true, example: 1),
        new OA\Property(property: 'title', type: 'string', example: 'Laravel 13 Meetup'),
        new OA\Property(property: 'total_capacity', type: 'integer', example: 100),
        new OA\Property(property: 'available_slots', type: 'integer', example: 49),
        new OA\Property(property: 'description', type: 'string', example: 'A deep dive into the new features.', nullable: true),
        new OA\Property(property: 'start_time', type: 'string', format: 'date-time'),
        new OA\Property(property: 'end_time', type: 'string', format: 'date-time'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', readOnly: true),
    ]
)]
class EventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
    ) {}

    #[OA\Get(
        path: '/v1/events',
        operationId: 'listEvents',
        description: 'Returns a list of events with optional filtering, search, and sorting.',
        summary: 'List events',
        security: [],
        tags: ['events'],
    )]
    #[OA\Parameter(
        name: 'filter[upcoming]',
        description: 'Filter to events with a start date in the future',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'boolean'),
    )]
    #[OA\Parameter(
        name: 'filter[has_spots]',
        description: 'Filter to events that still have available capacity',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'boolean'),
    )]
    #[OA\Parameter(
        name: 'filter[start_from]',
        description: 'Filter events starting on or after this date',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'date'),
    )]
    #[OA\Parameter(
        name: 'filter[start_to]',
        description: 'Filter events starting on or before this date',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'date'),
    )]
    #[OA\Parameter(
        name: 'search',
        description: 'Search by title or description',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'sort',
        description: 'Sort field (ascending)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'start_time'),
    )]
    #[OA\Parameter(
        name: 'page',
        description: 'Page number',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', example: 1),
    )]
    #[OA\Parameter(
        name: 'per_page',
        description: 'Number of events per page (max 100)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', example: 15),
    )]
    #[OA\Response(
        response: 200,
        description: 'Events retrieved successfully',
        content: new OA\JsonContent(
            required: ['data', 'links', 'meta'],
            properties: [
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/EventResource'),
                ),
                new OA\Property(
                    property: 'links',
                    properties: [
                        new OA\Property(property: 'first', type: 'string'),
                        new OA\Property(property: 'last', type: 'string'),
                        new OA\Property(property: 'prev', type: 'string', nullable: true),
                        new OA\Property(property: 'next', type: 'string', nullable: true),
                    ],
                    type: 'object',
                ),
                new OA\Property(
                    property: 'meta',
                    properties: [
                        new OA\Property(property: 'current_page', type: 'integer'),
                        new OA\Property(property: 'last_page', type: 'integer'),
                        new OA\Property(property: 'per_page', type: 'integer'),
                        new OA\Property(property: 'total', type: 'integer'),
                    ],
                    type: 'object',
                ),
            ]
        )
    )]
    public function index(Request $request): JsonResponse
    {
        $events = $this->eventService->list(ListEventsFilter::fromRequest($request));

        return EventResource::collection($events)->response();
    }

    #[OA\Post(
        path: '/v1/events',
        operationId: 'createEvent',
        description: 'Creates a new event. Validates required fields, future start time, end after start, and prevents scheduling conflicts with existing events.',
        summary: 'Create an event',
        security: [],
        tags: ['events'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/EventRequest')
    )]
    #[OA\Response(
        response: 201,
        description: 'Event created successfully',
        content: new OA\JsonContent(
            required: ['data'],
            properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/EventResource'),
            ]
        )
    )]
    #[OA\Response(response: 422, description: 'Validation error (invalid fields or scheduling conflict)')]
    public function store(EventRequest $request): JsonResponse
    {
        $event = $this->eventService->create(
            CreateEventData::fromValidated($request->validated()),
        );

        return (new EventResource($event))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/v1/events/{event}',
        operationId: 'showEvent',
        description: 'Returns the details of a specific event.',
        summary: 'Show an event',
        security: [],
        tags: ['events'],
    )]
    #[OA\Parameter(
        name: 'event',
        description: 'Event ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', format: 'int64'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Event retrieved successfully',
        content: new OA\JsonContent(
            required: ['data'],
            properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/EventResource'),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Event not found')]
    public function show(Event $event): EventResource
    {
        return new EventResource($this->eventService->find($event));
    }

    #[OA\Put(
        path: '/v1/events/{event}',
        operationId: 'updateEvent',
        description: 'Updates an existing event. Validates scheduling conflicts and capacity against confirmed bookings.',
        summary: 'Update an event',
        security: [],
        tags: ['events'],
    )]
    #[OA\Parameter(
        name: 'event',
        description: 'Event ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', format: 'int64'),
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/EventRequest')
    )]
    #[OA\Response(
        response: 200,
        description: 'Event updated successfully',
        content: new OA\JsonContent(
            required: ['data'],
            properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/EventResource'),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Event not found')]
    #[OA\Response(response: 422, description: 'Validation error (invalid fields or scheduling conflict)')]
    public function update(EventRequest $request, Event $event): EventResource
    {
        $updatedEvent = $this->eventService->update(
            $event,
            UpdateEventData::fromValidated($request->validated()),
        );

        return new EventResource($updatedEvent);
    }

    #[OA\Delete(
        path: '/v1/events/{event}',
        operationId: 'deleteEvent',
        description: 'Deletes an event. Cannot delete events with active confirmed bookings.',
        summary: 'Delete an event',
        security: [],
        tags: ['events'],
    )]
    #[OA\Parameter(
        name: 'event',
        description: 'Event ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', format: 'int64'),
    )]
    #[OA\Response(response: 204, description: 'Event deleted successfully')]
    #[OA\Response(response: 404, description: 'Event not found')]
    #[OA\Response(
        response: 409,
        description: 'Cannot delete event with active bookings',
        content: new OA\JsonContent(
            required: ['message'],
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Cannot delete event with active bookings.',
                ),
            ]
        )
    )]
    public function destroy(Event $event): JsonResponse|Response
    {
        $this->eventService->delete($event);

        return response()->noContent();
    }
}
