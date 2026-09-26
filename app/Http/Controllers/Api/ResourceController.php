<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Resource\UpdateResourceCapacityRequest;
use App\Models\Resource;
use App\Services\ResourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    public function __construct(
        private readonly ResourceService $resourceService
    ) {
    }

    /**
     * Update resource capacity.
     */
    public function updateCapacity(
        UpdateResourceCapacityRequest $request,
        Resource $resource
    ): JsonResponse {
        $data = $request->validated();

        $updatedResource = $this->resourceService->updateCapacity(
            admin: $request->user(),
            resource: $resource,
            newCapacity: (int) $data['capacity'],
        );

        return ResponseHelper::success(
            $updatedResource,
            'Resource capacity updated successfully.'
        );
    }
}