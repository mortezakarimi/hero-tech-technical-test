<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'status' => PaymentStatus::PENDING,
            'amount' => config('payment.amount'),
            'expires_at' => now()->addMinutes(config('payment.timeout_minutes')),
            'paid_at' => null,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::SUCCEEDED,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::FAILED,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::EXPIRED,
            'expires_at' => now()->subMinute(),
        ]);
    }
}
