<?php

namespace App\Services;

use App\Enum\ReservationStatus;
use App\Models\Reservation;
use App\Models\Resource;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AvailabilityService
{
    public function __construct(
        private readonly CapacityService $capacityService,
    ) {
    }

    /**
     * Check resource availability for a requested interval.
     */
    public function check(
        Resource $resource,
        int $units,
        Carbon $startTime,
        Carbon $endTime
    ): array {
        $this->validateTimeRange($startTime, $endTime);
        $this->validateUnits($units);

        /*
         * Availability is a read operation, so we do not lock the resource.
         *
         * Important:
         * This result must not be trusted as authorization for a later write.
         * The actual reservation creation performs its own locked capacity check.
         */
        $peakUsage = $this->capacityService
            ->calculatePeakUsageForInterval(
                resource: $resource,
                requestedStart: $startTime,
                requestedEnd: $endTime
            );

        $availableUnits = max(
            0,
            $resource->capacity - $peakUsage
        );

        return [
            'resource_id' => $resource->id,
            'resource_name' => $resource->name,
            'capacity' => $resource->capacity,
            'requested_units' => $units,
            'peak_reserved_units' => $peakUsage,
            'available_units' => $availableUnits,
            'is_available' => ($peakUsage + $units) <= $resource->capacity,
            'start_time' => $startTime->toISOString(),
            'end_time' => $endTime->toISOString(),
        ];
    }

    /**
     * Validate requested interval.
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
}