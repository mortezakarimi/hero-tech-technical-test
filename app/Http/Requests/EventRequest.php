<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Repositories\Contracts\EventRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EventRequest',
    required: ['title', 'total_capacity', 'start_time', 'end_time'],
    properties: [
        new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Laravel 13 Meetup'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'A deep dive into the new features.'),
        new OA\Property(property: 'total_capacity', type: 'integer', minimum: 1, example: 100),
        new OA\Property(property: 'start_time', type: 'string', format: 'date-time', example: '2026-06-10 14:00:00'),
        new OA\Property(property: 'end_time', type: 'string', format: 'date-time', example: '2026-06-10 17:00:00'),
    ]
)]
class EventRequest extends FormRequest
{
    public function rules(): array
    {
        $event = $this->route('event');

        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'total_capacity' => [
                'required',
                'integer',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail) use ($event): void {
                    if ($event instanceof Event) {
                        $confirmedCount = $event->bookings()->confirmed()->count();

                        if ((int) $value < $confirmedCount) {
                            $fail('The total capacity cannot be lower than the number of confirmed bookings.');
                        }
                    }
                },
            ],
            'start_time' => [
                'required',
                'date',
                Rule::when(
                    ! $this->isUpdatingWithSameStartTime($event),
                    ['after:now'],
                ),
            ],
            'end_time' => [
                'required',
                'date',
                'after:start_time',
            ],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $start = $this->input('start_time');
                $end = $this->input('end_time');

                if ($start === null || $end === null) {
                    return;
                }

                $start = Carbon::parse($start);
                $end = Carbon::parse($end);

                $excludeId = $this->route('event')?->id;

                $conflictingEvents = app(EventRepositoryInterface::class)
                    ->findConflicting($start, $end, $excludeId);

                foreach ($conflictingEvents as $existing) {
                    if ($start->gte($existing->start_time) && $start->lt($existing->end_time)) {
                        $validator->errors()->add('start_time', 'The selected time slot conflicts with an existing event.');

                        return;
                    }

                    if ($end->gt($existing->start_time) && $end->lte($existing->end_time)) {
                        $validator->errors()->add('end_time', 'The selected time slot conflicts with an existing event.');

                        return;
                    }

                    $validator->errors()->add('start_time', 'The selected time slot conflicts with an existing event.');

                    return;
                }
            },
        ];
    }

    private function isUpdatingWithSameStartTime(?Event $event): bool
    {
        if (! $event instanceof Event) {
            return false;
        }

        $startTime = $this->input('start_time');

        if ($startTime === null) {
            return false;
        }

        return Carbon::parse($startTime)->equalTo($event->start_time);
    }
}
