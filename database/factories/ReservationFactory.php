<?php

namespace Database\Factories;

use App\Enum\ReservationStatus;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $start = now()->addHour();

        return [
            'user_id' => User::factory(),
            'resource_id' => Resource::factory(),
            'units' => 1,
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'status' => ReservationStatus::PENDING,
            'expires_at' => now()->addMinutes(2),
        ];
    }

    /**
     * Create a confirmed reservation.
     */
    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::CONFIRMED,
            'expires_at' => null,
        ]);
    }

    /**
     * Create an expired reservation.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::EXPIRED,
            'expires_at' => null,
        ]);
    }

    /**
     * Create a cancelled reservation.
     */
    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::CANCELLED,
            'expires_at' => null,
        ]);
    }
}