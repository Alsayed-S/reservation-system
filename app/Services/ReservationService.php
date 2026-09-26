<?php

namespace App\Services;

use App\Enum\ReservationAction;
use App\Enum\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationHistory;
use App\Models\Resource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function __construct(
        private readonly CapacityService $capacityService,
        private readonly ReservationExpirationService $expirationService,
        private readonly IdempotencyService $idempotencyService,
    ) {
    }

public function create(User $user,int $resourceId,int $units,Carbon $startTime,Carbon $endTime,string $idempotencyKey, string $requestMethod,string $requestPath
): Reservation|array {
    $this->ensureUserCanCreateReservation($user);

    $this->validateTimeRange(
        $startTime,
        $endTime
    );

    $this->validateUnits($units);

    $requestHash = $this->requestHash([
        'resource_id' => $resourceId,
        'units' => $units,
        'start_time' => $startTime->toISOString(),
        'end_time' => $endTime->toISOString(),
    ]);

    return $this->idempotencyService->execute(
        key: $idempotencyKey,
        requestMethod: $requestMethod,
        requestPath: $requestPath,
        requestHash: $requestHash,

        operation: function () use (
            $user,
            $resourceId,
            $units,
            $startTime,
            $endTime
        ) {
            /*
             * Lock the resource before checking capacity.
             *
             * This is the critical concurrency protection.
             */
            $resource = Resource::query()
                ->lockForUpdate()
                ->findOrFail($resourceId);

            /*
             * Release expired pending reservations.
             */
            $this->expirationService
                ->expireForResource($resource);

            /*
             * Capacity is checked while the resource row
             * is locked.
             */
            $this->ensureCapacityAvailable(
                resource: $resource,
                units: $units,
                startTime: $startTime,
                endTime: $endTime
            );

            $reservation = Reservation::create([
                'user_id' => $user->id,
                'resource_id' => $resource->id,
                'units' => $units,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => ReservationStatus::PENDING,
                'expires_at' => now()->addMinutes(2),
            ]);

            $this->recordHistory(
                reservation: $reservation,
                action: ReservationAction::CREATED,
                oldData: null,
                newData: $this->reservationSnapshot(
                    $reservation
                )
            );

            return [
                'result' => $reservation->fresh(),
                'status' => 201,
                'body' => [
                    'reservation_id' => $reservation->id,
                ],
            ];
        }
    );
}
public function confirm(Reservation $reservation,string $idempotencyKey,string $requestMethod,string $requestPath): Reservation|array {
    $requestHash = $this->requestHash([
        'reservation_id' => $reservation->id,
        'action' => 'confirm',
    ]);

    return $this->idempotencyService->execute(
        key: $idempotencyKey,
        requestMethod: $requestMethod,
        requestPath: $requestPath,
        requestHash: $requestHash,

        operation: function () use ($reservation) {
            $resource = Resource::query()
                ->lockForUpdate()
                ->findOrFail($reservation->resource_id);

            $reservation = Reservation::query()
                ->lockForUpdate()
                ->findOrFail($reservation->id);

            $this->expirationService
                ->expireForResource($resource);

            $reservation->refresh();

            if ($reservation->status !== ReservationStatus::PENDING) {
                throw ValidationException::withMessages([
                    'reservation' => [
                        'Only pending reservations can be confirmed.',
                    ],
                ]);
            }

            if (
                $reservation->expires_at !== null
                && $reservation->expires_at->lte(now())
            ) {
                throw ValidationException::withMessages([
                    'reservation' => [
                        'The reservation has expired.',
                    ],
                ]);
            }

            $oldData = $this->reservationSnapshot(
                $reservation
            );

            $reservation->update([
                'status' => ReservationStatus::CONFIRMED,
                'expires_at' => null,
            ]);

            $this->recordHistory(
                reservation: $reservation,
                action: ReservationAction::CONFIRMED,
                oldData: $oldData,
                newData: $this->reservationSnapshot(
                    $reservation
                )
            );

            return [
                'result' => $reservation->fresh(),
                'status' => 200,
                'body' => [
                    'reservation_id' => $reservation->id,
                ],
            ];
        }
    );
}

    /**
     * Cancel a pending or confirmed reservation.
     */
    public function cancel(
        Reservation $reservation,
        string $idempotencyKey,
        string $requestMethod,
        string $requestPath
    ): Reservation|array {
        $requestHash = $this->requestHash([
            'reservation_id' => $reservation->id,
            'action' => 'cancel',
        ]);
    
        return $this->idempotencyService->execute(
            key: $idempotencyKey,
            requestMethod: $requestMethod,
            requestPath: $requestPath,
            requestHash: $requestHash,
    
            operation: function () use ($reservation) {
                $resource = Resource::query()
                    ->lockForUpdate()
                    ->findOrFail($reservation->resource_id);
    
                $reservation = Reservation::query()
                    ->lockForUpdate()
                    ->findOrFail($reservation->id);
    
                /*
                 * Expire stale pending reservations before checking
                 * the current reservation state.
                 */
                $this->expirationService->expireForResource($resource);
    
                $reservation->refresh();
    
                if (!in_array(
                    $reservation->status,
                    [
                        ReservationStatus::PENDING,
                        ReservationStatus::CONFIRMED,
                    ],
                    true
                )) {
                    throw ValidationException::withMessages([
                        'reservation' => [
                            'This reservation cannot be cancelled.',
                        ],
                    ]);
                }
    
                $oldData = $this->reservationSnapshot($reservation);
    
                $reservation->update([
                    'status' => ReservationStatus::CANCELLED,
                    'expires_at' => null,
                ]);
    
                $this->recordHistory(
                    reservation: $reservation,
                    action: ReservationAction::CANCELLED,
                    oldData: $oldData,
                    newData: $this->reservationSnapshot($reservation)
                );
    
                return [
                    'result' => $reservation->fresh(),
                    'status' => 200,
                    'body' => [
                        'reservation_id' => $reservation->id,
                    ],
                ];
            }
        );
    }

    /**
     * Update reservation units and/or time interval.
     */
    public function update(
        Reservation $reservation,
        ?int $units,
        ?Carbon $startTime,
        ?Carbon $endTime,
        string $idempotencyKey,
        string $requestMethod,
        string $requestPath
    ): Reservation|array {
        $requestHash = $this->requestHash([
            'reservation_id' => $reservation->id,
            'units' => $units,
            'start_time' => $startTime?->toISOString(),
            'end_time' => $endTime?->toISOString(),
        ]);
    
        return $this->idempotencyService->execute(
            key: $idempotencyKey,
            requestMethod: $requestMethod,
            requestPath: $requestPath,
            requestHash: $requestHash,
    
            operation: function () use (
                $reservation,
                $units,
                $startTime,
                $endTime
            ) {
                /*
                 * Lock the resource first.
                 *
                 * This guarantees that all reservation writes
                 * for the same resource are serialized.
                 */
                $resource = Resource::query()
                    ->lockForUpdate()
                    ->findOrFail($reservation->resource_id);
    
                $reservation = Reservation::query()
                    ->lockForUpdate()
                    ->findOrFail($reservation->id);
    
                $this->expirationService->expireForResource($resource);
    
                $reservation->refresh();
    
                if (!in_array(
                    $reservation->status,
                    [
                        ReservationStatus::PENDING,
                        ReservationStatus::CONFIRMED,
                    ],
                    true
                )) {
                    throw ValidationException::withMessages([
                        'reservation' => [
                            'This reservation cannot be updated.',
                        ],
                    ]);
                }
    
                $newUnits = $units ?? $reservation->units;
                $newStartTime = $startTime ?? $reservation->start_time;
                $newEndTime = $endTime ?? $reservation->end_time;
    
                $this->validateUnits($newUnits);
    
                $this->validateTimeRange(
                    $newStartTime,
                    $newEndTime
                );
    
                /*
                 * Exclude the current reservation while checking
                 * whether the new values fit within the capacity.
                 */
                $this->ensureCapacityAvailable(
                    resource: $resource,
                    units: $newUnits,
                    startTime: $newStartTime,
                    endTime: $newEndTime,
                    exceptReservationId: $reservation->id
                );
    
                $oldData = $this->reservationSnapshot($reservation);
    
                $reservation->update([
                    'units' => $newUnits,
                    'start_time' => $newStartTime,
                    'end_time' => $newEndTime,
                ]);
    
                $this->recordHistory(
                    reservation: $reservation,
                    action: ReservationAction::UPDATED,
                    oldData: $oldData,
                    newData: $this->reservationSnapshot($reservation)
                );
    
                return [
                    'result' => $reservation->fresh(),
                    'status' => 200,
                    'body' => [
                        'reservation_id' => $reservation->id,
                    ],
                ];
            }
        );
    }

    /**
     * Ensure that the requested reservation fits within resource capacity.
     */
    private function ensureCapacityAvailable(
        Resource $resource,
        int $units,
        Carbon $startTime,
        Carbon $endTime,
        ?int $exceptReservationId = null
    ): void {
        $peakUsage = $this->capacityService
            ->calculatePeakUsageForInterval(
                resource: $resource,
                requestedStart: $startTime,
                requestedEnd: $endTime,
                exceptReservationId: $exceptReservationId
            );

        if (($peakUsage + $units) > $resource->capacity) {
            throw ValidationException::withMessages([
                'units' => 'The requested units exceed the resource capacity.',
            ]);
        }
    }

    /**
     * Ensure the user is allowed to create reservations.
     */
    private function ensureUserCanCreateReservation(User $user): void
    {
        if (!$user->isUser()) {
            throw ValidationException::withMessages([
                'user' => 'Only normal users can create reservations.',
            ]);
        }
    }

    /**
     * Validate reservation time interval.
     *
     * Reservations use the half-open interval:
     * [start_time, end_time)
     */
    private function validateTimeRange(
        Carbon $startTime,
        Carbon $endTime
    ): void {
        if ($startTime->gte($endTime)) {
            throw ValidationException::withMessages([
                'end_time' => 'End time must be after start time.',
            ]);
        }
    }

    /**
     * Validate requested units.
     */
    private function validateUnits(int $units): void
    {
        if ($units < 1) {
            throw ValidationException::withMessages([
                'units' => 'Units must be greater than zero.',
            ]);
        }
    }

    /**
     * Record a reservation change in history.
     */
    private function recordHistory(
        Reservation $reservation,
        ReservationAction $action,
        ?array $oldData,
        ?array $newData
    ): void {
        ReservationHistory::create([
            'reservation_id' => $reservation->id,
            'action' => $action,
            'old_data' => $oldData,
            'new_data' => $newData,
            'created_at' => now(),
        ]);
    }

    /**
     * Create a stable reservation snapshot for history.
     */
    private function reservationSnapshot(
        Reservation $reservation
    ): array {
        return [
            'id' => $reservation->id,
            'user_id' => $reservation->user_id,
            'resource_id' => $reservation->resource_id,
            'units' => $reservation->units,
            'start_time' => $reservation->start_time?->toISOString(),
            'end_time' => $reservation->end_time?->toISOString(),
            'status' => $reservation->status->value,
            'expires_at' => $reservation->expires_at?->toISOString(),
        ];
    }

private function requestHash(array $data): string
{
    return hash(
        'sha256',
        json_encode(
            $data,
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE |
            JSON_PRESERVE_ZERO_FRACTION
        )
    );
}
}