<?php

namespace App\Services;

use App\Enum\ReservationStatus;
use App\Models\Reservation;
use App\Models\Resource;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CapacityService
{
    /**
     * Calculate the peak usage inside a requested interval.
     *
     * This method is used when checking whether a new or updated
     * reservation can fit within the resource capacity.
     */
    public function calculatePeakUsageForInterval(
        Resource $resource,
        Carbon $requestedStart,
        Carbon $requestedEnd,
        ?int $exceptReservationId = null
    ): int {
        $reservations = Reservation::query()
            ->where('resource_id', $resource->id)
            ->whereIn('status', [
                ReservationStatus::PENDING,
                ReservationStatus::CONFIRMED,
            ])
            ->where('start_time', '<', $requestedEnd)
            ->where('end_time', '>', $requestedStart)
            ->when(
                $exceptReservationId !== null,
                fn ($query) => $query->whereKeyNot($exceptReservationId)
            )
            ->get([
                'id',
                'units',
                'start_time',
                'end_time',
            ]);

        return $this->calculatePeakUsage(
            $reservations,
            $requestedStart,
            $requestedEnd
        );
    }

    /**
     * Calculate the peak usage of the entire resource.
     *
     * Used when an admin wants to reduce resource capacity.
     */
    public function calculateResourcePeakUsage(
        Resource $resource
    ): int {
        $reservations = Reservation::query()
            ->where('resource_id', $resource->id)
            ->whereIn('status', [
                ReservationStatus::PENDING,
                ReservationStatus::CONFIRMED,
            ])
            ->get([
                'id',
                'units',
                'start_time',
                'end_time',
            ]);

        return $this->calculatePeakUsage($reservations);
    }

    /**
     * Calculate maximum simultaneous usage using a sweep-line algorithm.
     */
    private function calculatePeakUsage(
        Collection $reservations,
        ?Carbon $requestedStart = null,
        ?Carbon $requestedEnd = null
    ): int {
        $events = [];

        foreach ($reservations as $reservation) {
            $start = $reservation->start_time;
            $end = $reservation->end_time;

            /*
             * When calculating an interval-specific peak,
             * clip each reservation to the requested interval.
             */
            if ($requestedStart !== null && $start->lt($requestedStart)) {
                $start = $requestedStart->copy();
            }

            if ($requestedEnd !== null && $end->gt($requestedEnd)) {
                $end = $requestedEnd->copy();
            }

            if ($start->gte($end)) {
                continue;
            }

            $events[] = [
                'timestamp' => $start->timestamp,
                'units' => $reservation->units,
            ];

            $events[] = [
                'timestamp' => $end->timestamp,
                'units' => -$reservation->units,
            ];
        }

        /*
         * For equal timestamps, process negative events first.
         *
         * This preserves the half-open interval:
         *
         * [start_time, end_time)
         */
        usort($events, function (array $first, array $second): int {
            if ($first['timestamp'] === $second['timestamp']) {
                return $first['units'] <=> $second['units'];
            }

            return $first['timestamp'] <=> $second['timestamp'];
        });

        $currentUsage = 0;
        $peakUsage = 0;

        foreach ($events as $event) {
            $currentUsage += $event['units'];

            $peakUsage = max(
                $peakUsage,
                $currentUsage
            );
        }

        return $peakUsage;
    }
}