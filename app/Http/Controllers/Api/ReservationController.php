<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\StoreReservationRequest;
use App\Http\Requests\Reservation\UpdateReservationRequest;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService
    ) {
    }

    /**
     * Create a new reservation.
     */
    public function store(
        StoreReservationRequest $request
    ): JsonResponse {
        $data = $request->validated();

        /*
         * Authentication is intentionally not used in this assessment.
         *
         * The user is identified using the user_id
         * provided in the request.
         */
        $user = User::findOrFail(
            (int) $data['user_id']
        );

        $result = $this->reservationService->create(
            user: $user,
            resourceId: (int) $data['resource_id'],
            units: (int) $data['units'],
            startTime: Carbon::parse($data['start_time']),
            endTime: Carbon::parse($data['end_time']),
            idempotencyKey: $request->header('Idempotency-Key'),
            requestMethod: $request->method(),
            requestPath: $request->path(),
        );

        return $this->reservationResponse(
            result: $result,
            message: 'Reservation created successfully.',
            defaultStatus: 201
        );
    }

    /**
     * Get a reservation.
     */
    public function show(
        Reservation $reservation
    ): JsonResponse {
        return ResponseHelper::success(
            $reservation->load([
                'user',
                'resource',
                'histories',
            ]),
            'Reservation retrieved successfully.'
        );
    }

    /**
     * Update reservation.
     */
    public function update(
        UpdateReservationRequest $request,
        Reservation $reservation
    ): JsonResponse {
        $data = $request->validated();

        $result = $this->reservationService->update(
            reservation: $reservation,

            units: isset($data['units'])
                ? (int) $data['units']
                : null,

            startTime: isset($data['start_time'])
                ? Carbon::parse($data['start_time'])
                : null,

            endTime: isset($data['end_time'])
                ? Carbon::parse($data['end_time'])
                : null,

            idempotencyKey: $request->header('Idempotency-Key'),
            requestMethod: $request->method(),
            requestPath: $request->path(),
        );

        return $this->reservationResponse(
            result: $result,
            message: 'Reservation updated successfully.',
            defaultStatus: 200
        );
    }

    /**
     * Confirm reservation.
     */
    public function confirm(
        Request $request,
        Reservation $reservation
    ): JsonResponse {
        $result = $this->reservationService->confirm(
            reservation: $reservation,
            idempotencyKey: $request->header('Idempotency-Key'),
            requestMethod: $request->method(),
            requestPath: $request->path(),
        );

        return $this->reservationResponse(
            result: $result,
            message: 'Reservation confirmed successfully.',
            defaultStatus: 200
        );
    }

    /**
     * Cancel reservation.
     */
    public function cancel(
        Request $request,
        Reservation $reservation
    ): JsonResponse {
        $result = $this->reservationService->cancel(
            reservation: $reservation,
            idempotencyKey: $request->header('Idempotency-Key'),
            requestMethod: $request->method(),
            requestPath: $request->path(),
        );

        return $this->reservationResponse(
            result: $result,
            message: 'Reservation cancelled successfully.',
            defaultStatus: 200
        );
    }

    /**
     * Convert a service result into an API response.
     *
     * IdempotencyService returns an array both for a new request
     * and for a replayed request.
     */
    private function reservationResponse(
        Reservation|array $result,
        string $message,
        int $defaultStatus
    ): JsonResponse {
        /*
         * IdempotencyService response.
         */
        if (is_array($result)) {
            $reservation = Reservation::findOrFail(
                $result['body']['reservation_id']
            );

            return ResponseHelper::success(
                $reservation,
                $message,
                $result['status'] ?? $defaultStatus
            );
        }

        /*
         * Fallback for direct service results.
         */
        return ResponseHelper::success(
            $result,
            $message,
            $defaultStatus
        );
    }
}