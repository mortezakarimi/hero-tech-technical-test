<?php

namespace App\Services;

use App\Enums\PaymentOutcome;
use App\Models\Payment;
use App\Services\Contracts\FakePaymentGatewayInterface;

class FakePaymentGateway implements FakePaymentGatewayInterface
{
    public function process(Payment $payment, PaymentOutcome $outcome): bool
    {
        return $outcome === PaymentOutcome::SUCCESS;
    }
}
