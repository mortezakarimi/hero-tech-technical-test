<?php

namespace App\Repositories\Contracts;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Carbon;

interface PaymentRepositoryInterface
{
    public function createForBooking(Booking $booking, int $amount, Carbon $expiresAt): Payment;

    public function lockForUpdate(Payment $payment): Payment;

    public function markSucceeded(Payment $payment): Payment;

    public function markFailed(Payment $payment): Payment;

    public function markExpired(Payment $payment): Payment;

    public function findByBooking(Booking $booking): ?Payment;
}
