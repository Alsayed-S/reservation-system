<?php

namespace App\Services;

use App\Enum\ReservationAction;
use App\Enum\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationHistory;
use App\Models\Resource;

class ReservationExpirationService
{
    /**
     * Expire all pending reservations whose expiration time has passed.
     *
     * The caller must already hold the resource lock.
     */
    public function expireForResource(Resource $resource): void
    {
        $reservations = Reservation::query()
            ->where('resource_id', $resource->id)
            ->where('status', ReservationStatus::PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->lockForUpdate()
            ->get();

        foreach ($reservations as $reservation) {
            $oldData = $this->snapshot($reservation);

            $reservation->update([
                'status' => ReservationStatus::EXPIRED,
                'expires_at' => null,
            ]);

            ReservationHistory::create([
                'reservation_id' => $reservation->id,
                'action' => ReservationAction::EXPIRED,
                'old_data' => $oldData,
                'new_data' => $this->snapshot($reservation),
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Create a stable reservation snapshot for history.
     */
    private function snapshot(Reservation $reservation): array
    {
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
}