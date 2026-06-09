<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Booking',
    title: 'Booking Model',
    description: 'Represents a user booking for a specific event',
    required: ['user_id', 'event_id', 'status'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', readOnly: true, example: 42),
        new OA\Property(property: 'user_id', description: 'ID of the attending user', type: 'integer', example: 7),
        new OA\Property(property: 'event_id', description: 'ID of the booked event', type: 'integer', example: 1),
        new OA\Property(property: 'status', type: 'string', example: 'confirmed', enum: ['pending', 'confirmed', 'cancelled']),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', readOnly: true),
    ]
)]
class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'event_id',
        'status',
        'active_key',
    ];

    protected $casts = [
        'status' => BookingStatus::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (Booking $booking): void {
            $booking->active_key = $booking->isActive()
                ? "{$booking->user_id}-{$booking->event_id}"
                : null;
        });
    }

    public function isActive(): bool
    {
        return in_array($this->status, [BookingStatus::PENDING, BookingStatus::CONFIRMED], true);
    }

    /**
     * Local scope to only fetch active (pending or confirmed) bookings.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [BookingStatus::PENDING, BookingStatus::CONFIRMED]);
    }

    /**
     * Local scope to only fetch confirmed bookings.
     * Essential for tracking strict capacity limits.
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', BookingStatus::CONFIRMED);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
