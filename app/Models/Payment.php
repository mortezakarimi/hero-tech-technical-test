<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Payment',
    title: 'Payment',
    description: 'Payment for a booking reservation',
    required: ['id', 'booking_id', 'status', 'amount', 'expires_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', readOnly: true, example: 1),
        new OA\Property(property: 'booking_id', type: 'integer', example: 42),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'succeeded', 'failed', 'expired'], example: 'pending'),
        new OA\Property(property: 'amount', type: 'integer', example: 100),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', readOnly: true),
    ]
)]
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'status',
        'amount',
        'expires_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
