<?php

namespace App\Services;

use App\DTOs\Booking\CreateBookingData;
use App\DTOs\Booking\ListBookingsFilter;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Services\Contracts\EventSlotCounterInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventSlotCounterInterface $slotCounter,
    ) {}

    public function create(User $user, Event $event): Booking
    {
        if ($this->bookingRepository->findActiveForUserAndEvent($user->id, $event->id) !== null) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'You already have an active booking for this event.',
                ], 409)
            );
        }

        if (! $this->slotCounter->reserveSlot($event->id)) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'Event is full.',
                ], 422)
            );
        }

        try {
            return DB::transaction(function () use ($user, $event): Booking {
                $this->eventRepository->lockForUpdate($event);

                if ($this->bookingRepository->findActiveForUserAndEvent($user->id, $event->id) !== null) {
                    throw new HttpResponseException(
                        response()->json([
                            'message' => 'You already have an active booking for this event.',
                        ], 409)
                    );
                }

                return $this->bookingRepository->create(
                    new CreateBookingData($user->id, $event->id),
                    BookingStatus::PENDING,
                );
            });
        } catch (QueryException $exception) {
            $this->slotCounter->releaseSlot($event->id);

            if ($this->isActiveKeyViolation($exception)) {
                throw new HttpResponseException(
                    response()->json([
                        'message' => 'You already have an active booking for this event.',
                    ], 409)
                );
            }

            throw $exception;
        } catch (HttpResponseException $exception) {
            $this->slotCounter->releaseSlot($event->id);

            throw $exception;
        }
    }

    public function confirm(User $user, Booking $booking): Booking
    {
        if ($booking->user_id !== $user->id) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'You are not authorized to confirm this booking.',
                ], 403)
            );
        }

        return $this->confirmAsSystem($booking);
    }

    public function confirmAsSystem(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking): Booking {
            $lockedBooking = $this->bookingRepository->lockForUpdate($booking);

            if ($lockedBooking->status !== BookingStatus::PENDING) {
                throw new HttpResponseException(
                    response()->json([
                        'message' => 'Only pending bookings can be confirmed.',
                    ], 409)
                );
            }

            $event = $this->eventRepository->lockForUpdate($lockedBooking->event);

            if ($event->available_slots <= 0) {
                throw new HttpResponseException(
                    response()->json([
                        'message' => 'Event is full.',
                    ], 409)
                );
            }

            $this->eventRepository->decrementAvailableSlots($event);

            return $this->bookingRepository->updateStatus($lockedBooking, BookingStatus::CONFIRMED);
        });
    }

    public function cancel(User $user, Booking $booking): void
    {
        if ($booking->user_id !== $user->id) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'You are not authorized to cancel this booking.',
                ], 403)
            );
        }

        $this->cancelAsSystem($booking);
    }

    public function cancelAsSystem(Booking $booking): void
    {
        DB::transaction(function () use ($booking): void {
            $lockedBooking = $this->bookingRepository->lockForUpdate($booking);

            if ($lockedBooking->status === BookingStatus::CANCELLED) {
                throw new HttpResponseException(
                    response()->json([
                        'message' => 'Booking is already cancelled.',
                    ], 409)
                );
            }

            $wasConfirmed = $lockedBooking->status === BookingStatus::CONFIRMED;

            $this->bookingRepository->updateStatus($lockedBooking, BookingStatus::CANCELLED);

            $this->slotCounter->releaseSlot($lockedBooking->event_id);

            if ($wasConfirmed) {
                $event = $this->eventRepository->lockForUpdate($lockedBooking->event);
                $this->eventRepository->incrementAvailableSlots($event);
            }
        });
    }

    public function list(ListBookingsFilter $filter): LengthAwarePaginator
    {
        return $this->bookingRepository->listForUser($filter);
    }

    public function find(User $user, Booking $booking): Booking
    {
        if ($booking->user_id !== $user->id) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'You are not authorized to view this booking.',
                ], 403)
            );
        }

        return $booking->load('event');
    }

    private function isActiveKeyViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'active_key')
            || str_contains($message, 'UNIQUE constraint failed');
    }
}
