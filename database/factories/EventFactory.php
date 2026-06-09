<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $startTime = Carbon::parse($this->faker->dateTimeBetween('+1 day', '+1 year'));
        $totalCapacity = $this->faker->numberBetween(1, 10000);

        return [
            'title' => $this->faker->word(),
            'description' => $this->faker->text(),
            'total_capacity' => $totalCapacity,
            'available_slots' => $totalCapacity,
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addHour(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
