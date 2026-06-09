<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Event',
    title: 'Event Model',
    description: 'Represents an individual event with a capacity limit',
    required: ['title', 'total_capacity', 'available_slots'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', readOnly: true, example: 1),
        new OA\Property(property: 'title', description: 'The title of the event', type: 'string', example: 'HeroTech Backend Conference'),
        new OA\Property(property: 'description', description: 'The description of the event', type: 'string', example: 'This is a fancy conference for developers', nullable: true),
        new OA\Property(property: 'total_capacity', description: 'Maximum available spots', type: 'integer', example: 150),
        new OA\Property(property: 'available_slots', description: 'Remaining available spots', type: 'integer', example: 49),
        new OA\Property(property: 'start_time', type: 'string', format: 'date-time'),
        new OA\Property(property: 'end_time', type: 'string', format: 'date-time'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', readOnly: true),
    ]
)]
class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'total_capacity',
        'available_slots',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
