<?php

namespace Tests\Feature;

use App\Enum\ReservationStatus;
use App\Models\Resource;
use App\Models\User;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReservationServiceTest extends TestCase
{
    use RefreshDatabase;

public function test_user_can_create_reservation(): void
{
    $user = User::factory()->create();

    $resource = Resource::factory()->create([
        'capacity' => 10,
    ]);

    $start = Carbon::parse('2026-09-25 10:00:00');
    $end = Carbon::parse('2026-09-25 11:00:00');

    $reservation = app(ReservationService::class)->create(
        user: $user,
        resourceId: $resource->id,
        units: 6,
        startTime: $start,
        endTime: $end,
        idempotencyKey: 'test-create-reservation-001',
        requestMethod: 'POST',
        requestPath: '/api/reservations',
    );

    $this->assertDatabaseHas('reservations', [
        'id' => $reservation->id,
        'user_id' => $user->id,
        'resource_id' => $resource->id,
        'units' => 6,
        'status' => ReservationStatus::PENDING->value,
    ]);

    $this->assertDatabaseHas('reservation_histories', [
        'reservation_id' => $reservation->id,
        'action' => 'created',
    ]);

    $this->assertDatabaseHas('idempotency_keys', [
        'key' => 'test-create-reservation-001',
        'request_method' => 'POST',
        'request_path' => '/api/reservations',
    ]);
}
}