<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpirePaymentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Payment $payment,
    ) {}

    public function handle(PaymentService $paymentService): void
    {
        $paymentService->expirePayment($this->payment);
    }
}
