<?php

namespace App\DTOs\Payment;

use App\Enums\PaymentOutcome;

readonly class ProcessPaymentData
{
    public function __construct(
        public PaymentOutcome $outcome,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            outcome: PaymentOutcome::from($validated['simulate']),
        );
    }
}
