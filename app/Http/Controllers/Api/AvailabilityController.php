<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availabilityService
    ) {
    }

    /**
     * Check resource availability for a requested interval.
     */
    public function show(
        Request $request,
        Resource $resource
    ): JsonResponse {
        $validated = $request->validate([
            'units' => [
                'required',
                'integer',
                'min:1',
            ],

            'start_time' => [
                'required',
                'date',
            ],

            'end_time' => [
                'required',
                'date',
                'after:start_time',
            ],
        ]);

        $result = $this->availabilityService->check(
            resource: $resource,
            units: (int) $validated['units'],
            startTime: Carbon::parse($validated['start_time']),
            endTime: Carbon::parse($validated['end_time']),
        );

        return ResponseHelper::success(
            $result,
            'Availability checked successfully.'
        );
    }
}