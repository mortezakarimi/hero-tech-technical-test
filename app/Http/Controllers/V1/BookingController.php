<?php

namespace App\Http\Controllers\V1;

use App\DTOs\Booking\ListBookingsFilter;
use App\DTOs\Payment\ProcessPaymentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessPaymentRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\PaymentResource;
use App\Models\Booking;
use App\Models\Event;
use App\Services\BookingService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly PaymentService $paymentService,
    ) {}

    #[OA\Get(
        path: '/v1/bookings',
        operationId: 'listBookings',
        description: 'Returns a paginated list of bookings for the authenticated user.',
        summary: 'List user bookings',
        security: [['sanctum' => []]],
        tags: ['bookings'],
    )]
    #[OA\Parameter(
        name: 'filter[status]',
        description: 'Filter by booking status',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['pending', 'confirmed', 'cancelled']),
    )]
    #[OA\Parameter(
        name: 'per_page',
        description: 'Number of bookings per page (max 100)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', example: 15),
    )]
    #[OA\Response(
        response: 200,
        description: 'Bookings retrieved successfully',
        content: new OA\JsonContent(
            required: ['data', 'links', 'meta'],
            properties: [
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Booking'),
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
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')
    )]
    public function index(Request $request): JsonResponse
    {
        $bookings = $this->bookingService->list(
            ListBookingsFilter::fromRequest($request, $request->user()->id),
        );

        return BookingResource::collection($bookings)->response();
    }

    #[OA\Post(
        path: '/v1/events/{event}/bookings',
        operationId: 'createBooking',
        description: 'Creates a pending booking reservation for the authenticated user.',
        summary: 'Book an event',
        security: [['sanctum' => []]],
        tags: ['bookings'],
    )]
    #[OA\Parameter(
        name: 'event',
        description: 'Event ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', format: 'int64'),
    )]
    #[OA\Response(
        response: 201,
        description: 'Booking created successfully',
        content: new OA\JsonContent(
            required: ['data'],
            properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Booking'),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')
    )]
    #[OA\Response(
        response: 404,
        description: 'Event not found',
        content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')
    )]
    #[OA\Response(
        response: 409,
        description: 'User already has an active booking for this event',
        content: new OA\JsonContent(
            required: ['message'],
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'You already have an active booking for this event.',
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Event is full (no remaining slots)',
        content: new OA\JsonContent(
            required: ['message'],
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Event is full.'),
            ]
        )
    )]
    public function store(StoreBookingRequest $request, Event $event): JsonResponse
    {
        $booking = $this->bookingService->create($request->user(), $event);
        $this->paymentService->initiateForBooking($booking);

        return (new BookingResource($booking->load('payment')))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/v1/bookings/{booking}',
        operationId: 'showBooking',
        description: 'Returns details of a specific booking owned by the authenticated user.',
        summary: 'Show a booking',
        security: [['sanctum' => []]],
        tags: ['bookings'],
    )]
    #[OA\Parameter(
        name: 'booking',
        description: 'Booking ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', format: 'int64'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Booking retrieved successfully',
        content: new OA\JsonContent(
            required: ['data'],
            properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Booking'),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden — booking belongs to another user',
        content: new OA\JsonContent(
            required: ['message'],
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'You are not authorized to view this booking.',
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Booking not found',
        content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')
    )]
    public function show(Request $request, Booking $booking): BookingResource
    {
        return new BookingResource($this->bookingService->find($request->user(), $booking));
    }

    #[OA\Post(
        path: '/v1/bookings/{booking}/payment',
        operationId: 'processBookingPayment',
        description: 'Processes a fake payment for a pending booking. Success confirms the booking; failure cancels it.',
        summary: 'Pay for a booking',
        security: [['sanctum' => []]],
        tags: ['bookings'],
    )]
    #[OA\Parameter(
        name: 'booking',
        description: 'Booking ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', format: 'int64'),
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['simulate'],
            properties: [
                new OA\Property(property: 'simulate', type: 'string', enum: ['success', 'failure'], example: 'success'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Payment processed successfully',
        content: new OA\JsonContent(
            required: ['data'],
            properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Payment'),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden — booking belongs to another user',
        content: new OA\JsonContent(
            required: ['message'],
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'You are not authorized to pay for this booking.',
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Booking or payment not found',
        content: new OA\JsonContent(
            required: ['message'],
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'No payment found for this booking.',
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 409,
        description: 'Payment has already been processed',
        content: new OA\JsonContent(
            required: ['message'],
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Payment has already been processed.',
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error or payment window expired',
        content: new OA\JsonContent(
            oneOf: [
                new OA\Schema(ref: '#/components/schemas/ValidationError'),
                new OA\Schema(
                    required: ['message'],
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Payment window has expired. Booking has been cancelled.',
                        ),
                    ]
                ),
            ]
        )
    )]
    public function pay(ProcessPaymentRequest $request, Booking $booking): PaymentResource
    {
        $payment = $this->paymentService->process(
            $request->user(),
            $booking,
            ProcessPaymentData::fromValidated($request->validated()),
        );

        return new PaymentResource($payment);
    }

    #[OA\Post(
        path: '/v1/bookings/{booking}/confirm',
        operationId: 'confirmBooking',
        description: 'Confirms a pending booking for the authenticated user.',
        summary: 'Confirm a booking',
        security: [['sanctum' => []]],
        tags: ['bookings'],
    )]
    #[OA\Parameter(
        name: 'booking',
        description: 'Booking ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', format: 'int64'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Booking confirmed successfully',
        content: new OA\JsonContent(
            required: ['data'],
            properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Booking'),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden — booking belongs to another user',
        content: new OA\JsonContent(
            required: ['message'],
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'You are not authorized to confirm this booking.',
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Booking not found',
        content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')
    )]
    #[OA\Response(
        response: 409,
        description: 'Booking cannot be confirmed (not pending or event is full)',
        content: new OA\JsonContent(
            oneOf: [
                new OA\Schema(
                    required: ['message'],
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Only pending bookings can be confirmed.',
                        ),
                    ]
                ),
                new OA\Schema(
                    required: ['message'],
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Event is full.',
                        ),
                    ]
                ),
            ]
        )
    )]
    public function confirm(Request $request, Booking $booking): BookingResource
    {
        return new BookingResource($this->bookingService->confirm($request->user(), $booking));
    }

    #[OA\Delete(
        path: '/v1/bookings/{booking}',
        operationId: 'cancelBooking',
        description: 'Cancels a booking owned by the authenticated user.',
        summary: 'Cancel a booking',
        security: [['sanctum' => []]],
        tags: ['bookings'],
    )]
    #[OA\Parameter(
        name: 'booking',
        description: 'Booking ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', format: 'int64'),
    )]
    #[OA\Response(response: 204, description: 'Booking cancelled successfully')]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden — booking belongs to another user',
        content: new OA\JsonContent(
            required: ['message'],
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'You are not authorized to cancel this booking.',
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Booking not found',
        content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')
    )]
    #[OA\Response(
        response: 409,
        description: 'Booking is already cancelled',
        content: new OA\JsonContent(
            required: ['message'],
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Booking is already cancelled.',
                ),
            ]
        )
    )]
    public function destroy(Request $request, Booking $booking): JsonResponse|Response
    {
        $this->bookingService->cancel($request->user(), $booking);

        return response()->noContent();
    }
}
