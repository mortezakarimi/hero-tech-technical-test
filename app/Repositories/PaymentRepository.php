<?php

namespace App\Repositories;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Support\Carbon;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function createForBooking(Booking $booking, int $amount, Carbon $expiresAt): Payment
    {
        return Payment::query()->create([
            'booking_id' => $booking->id,
            'status' => PaymentStatus::PENDING,
            'amount' => $amount,
            'expires_at' => $expiresAt,
        ]);
    }

    public function lockForUpdate(Payment $payment): Payment
    {
        return Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
    }

    public function markSucceeded(Payment $payment): Payment
    {
        $payment->update([
            'status' => PaymentStatus::SUCCEEDED,
            'paid_at' => now(),
        ]);

        return $payment->fresh();
    }

    public function markFailed(Payment $payment): Payment
    {
        $payment->update([
            'status' => PaymentStatus::FAILED,
        ]);

        return $payment->fresh();
    }

    public function markExpired(Payment $payment): Payment
    {
        $payment->update([
            'status' => PaymentStatus::EXPIRED,
        ]);

        return $payment->fresh();
    }

    public function findByBooking(Booking $booking): ?Payment
    {
        return Payment::query()->where('booking_id', $booking->id)->first();
    }
}
