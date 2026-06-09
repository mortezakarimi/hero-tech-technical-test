<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentOutcome;
use App\Enums\PaymentStatus;
use App\Jobs\ExpirePaymentJob;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Payment;
use App\Models\User;
use App\Services\Contracts\EventSlotCounterInterface;
use App\Services\InMemoryEventSlotCounter;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

describe('Booking Payment', function () {

    beforeEach(function () {
        InMemoryEventSlotCounter::reset();

        config(['payment.timeout_minutes' => 15]);

        $this->user = User::factory()->create();
        $this->event = Event::factory()->create([
            'total_capacity' => 10,
            'available_slots' => 10,
        ]);

        seedEventSlots($this->event);

        Sanctum::actingAs($this->user, ['*']);
    });

    test('it creates a pending payment when booking an event', function () {
        Queue::fake();

        $response = $this->postJson("/api/v1/events/{$this->event->id}/bookings");

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment.status', 'pending')
            ->assertJsonPath('data.payment.amount', config('payment.amount'));

        $this->assertDatabaseHas('payments', [
            'status' => PaymentStatus::PENDING->value,
            'amount' => config('payment.amount'),
        ]);

        Queue::assertPushed(ExpirePaymentJob::class);
    });

    test('it confirms the booking when payment succeeds', function () {
        $booking = Booking::factory()->pending()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        Payment::factory()->create([
            'booking_id' => $booking->id,
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/payment", [
            'simulate' => PaymentOutcome::SUCCESS->value,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'succeeded');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CONFIRMED->value,
        ]);

        expect($this->event->fresh()->available_slots)->toBe(9);
    });

    test('it cancels the booking when payment fails', function () {
        $booking = Booking::factory()->pending()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        Payment::factory()->create([
            'booking_id' => $booking->id,
            'expires_at' => now()->addMinutes(15),
        ]);

        seedEventSlots($this->event, 9);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/payment", [
            'simulate' => PaymentOutcome::FAILURE->value,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'failed');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CANCELLED->value,
        ]);

        expect(app(EventSlotCounterInterface::class)->getRemainingSlots($this->event->id))->toBe(10);
    });

    test('it cancels the booking when payment times out', function () {
        $booking = Booking::factory()->pending()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        $payment = Payment::factory()->create([
            'booking_id' => $booking->id,
            'expires_at' => now()->subMinute(),
        ]);

        seedEventSlots($this->event, 9);

        app(PaymentService::class)->expirePayment($payment);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::EXPIRED->value,
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CANCELLED->value,
        ]);
    });

    test('it rejects payment after the timeout window via the payment endpoint', function () {
        $booking = Booking::factory()->pending()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        Payment::factory()->create([
            'booking_id' => $booking->id,
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson("/api/v1/bookings/{$booking->id}/payment", [
            'simulate' => PaymentOutcome::SUCCESS->value,
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Payment window has expired. Booking has been cancelled.']);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CANCELLED->value,
        ]);
    });

    test('it denies payment for another user booking', function () {
        $booking = Booking::factory()->pending()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        Payment::factory()->create([
            'booking_id' => $booking->id,
            'expires_at' => now()->addMinutes(15),
        ]);

        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser, ['*']);

        $this->postJson("/api/v1/bookings/{$booking->id}/payment", [
            'simulate' => PaymentOutcome::SUCCESS->value,
        ])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'You are not authorized to pay for this booking.']);
    });

    test('it rejects processing an already completed payment', function () {
        $booking = Booking::factory()->confirmed()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        Payment::factory()->succeeded()->create([
            'booking_id' => $booking->id,
        ]);

        $this->postJson("/api/v1/bookings/{$booking->id}/payment", [
            'simulate' => PaymentOutcome::SUCCESS->value,
        ])
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'Payment has already been processed.']);
    });

    test('it validates the simulate field is required', function () {
        $booking = Booking::factory()->pending()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        Payment::factory()->create([
            'booking_id' => $booking->id,
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->postJson("/api/v1/bookings/{$booking->id}/payment", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['simulate']);
    });
});
