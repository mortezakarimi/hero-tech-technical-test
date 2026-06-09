<?php

namespace App\Services;

use App\DTOs\Payment\ProcessPaymentData;
use App\Jobs\ExpirePaymentJob;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Contracts\FakePaymentGatewayInterface;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly FakePaymentGatewayInterface $paymentGateway,
        private readonly BookingService $bookingService,
    ) {}

    public function initiateForBooking(Booking $booking): Payment
    {
        $timeoutMinutes = config('payment.timeout_minutes');
        $expiresAt = now()->addMinutes($timeoutMinutes);

        $payment = $this->paymentRepository->createForBooking(
            $booking,
            config('payment.amount'),
            $expiresAt,
        );

        ExpirePaymentJob::dispatch($payment)->delay($expiresAt);

        return $payment;
    }

    public function process(User $user, Booking $booking, ProcessPaymentData $data): Payment
    {
        if ($booking->user_id !== $user->id) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'You are not authorized to pay for this booking.',
                ], 403)
            );
        }

        $payment = $this->paymentRepository->findByBooking($booking);

        if ($payment === null) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'No payment found for this booking.',
                ], 404)
            );
        }

        if ($payment->isPending() && $payment->hasExpired()) {
            $this->expirePayment($payment);

            throw new HttpResponseException(
                response()->json([
                    'message' => 'Payment window has expired. Booking has been cancelled.',
                ], 422)
            );
        }

        return DB::transaction(function () use ($booking, $data, $payment): Payment {
            $lockedPayment = $this->paymentRepository->lockForUpdate($payment);

            if (! $lockedPayment->isPending()) {
                throw new HttpResponseException(
                    response()->json([
                        'message' => 'Payment has already been processed.',
                    ], 409)
                );
            }

            $succeeded = $this->paymentGateway->process($lockedPayment, $data->outcome);

            if ($succeeded) {
                $this->paymentRepository->markSucceeded($lockedPayment);
                $this->bookingService->confirmAsSystem($booking);

                return $lockedPayment->fresh();
            }

            $this->paymentRepository->markFailed($lockedPayment);
            $this->bookingService->cancelAsSystem($booking);

            return $lockedPayment->fresh();
        });
    }

    public function expirePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $lockedPayment = $this->paymentRepository->lockForUpdate($payment);

            if (! $lockedPayment->isPending()) {
                return;
            }

            if (! $lockedPayment->hasExpired()) {
                return;
            }

            $this->paymentRepository->markExpired($lockedPayment);
            $this->bookingService->cancelAsSystem($lockedPayment->booking);
        });
    }
}
