<?php

namespace App\Services\Contracts;

use App\Enums\PaymentOutcome;
use App\Models\Payment;

interface FakePaymentGatewayInterface
{
    public function process(Payment $payment, PaymentOutcome $outcome): bool;
}
