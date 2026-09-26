<?php

namespace App\Services;

use App\Enum\UserRole;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResourceService
{
    public function __construct(
        private readonly CapacityService $capacityService,
        private readonly ReservationExpirationService $expirationService,
    ) {
    }

    /**
     * Create a new resource.
     */
    public function create(
        User $admin,
        string $name,
        int $capacity
    ): Resource {
        $this->ensureAdmin($admin);
        $this->validateName($name);
        $this->validateCapacity($capacity);

        return DB::transaction(function () use (
            $admin,
            $name,
            $capacity
        ) {
            return Resource::create([
                'created_by' => $admin->id,
                'name' => $name,
                'capacity' => $capacity,
            ]);
        });
    }

    /**
     * Update resource capacity.
     */
    public function updateCapacity(
        User $admin,
        Resource $resource,
        int $newCapacity
    ): Resource {
        $this->ensureAdmin($admin);
        $this->validateCapacity($newCapacity);

        return DB::transaction(function () use (
            $resource,
            $newCapacity
        ) {
            /*
             * The same resource lock is used by reservation writes.
             * Therefore capacity changes and reservation creation
             * cannot pass each other unsafely.
             */
            $resource = Resource::query()
                ->lockForUpdate()
                ->findOrFail($resource->id);

            /*
             * Expire stale pending reservations before calculating
             * current capacity usage.
             */
            $this->expirationService->expireForResource($resource);

            $peakUsage = $this->capacityService
                ->calculateResourcePeakUsage($resource);

            if ($newCapacity < $peakUsage) {
                throw ValidationException::withMessages([
                    'capacity' => sprintf(
                        'Capacity cannot be reduced below the current peak usage of %d units.',
                        $peakUsage
                    ),
                ]);
            }

            $resource->update([
                'capacity' => $newCapacity,
            ]);

            return $resource->fresh();
        });
    }

    /**
     * Ensure that only admins can manage resources.
     */
    private function ensureAdmin(User $user): void
    {
        if ($user->role !== UserRole::ADMIN) {
            throw ValidationException::withMessages([
                'user' => 'Only administrators can manage resources.',
            ]);
        }
    }

    /**
     * Validate resource name.
     */
    private function validateName(string $name): void
    {
        if (trim($name) === '') {
            throw ValidationException::withMessages([
                'name' => 'Resource name is required.',
            ]);
        }
    }

    /**
     * Validate resource capacity.
     */
    private function validateCapacity(int $capacity): void
    {
        if ($capacity < 1) {
            throw ValidationException::withMessages([
                'capacity' => 'Capacity must be greater than zero.',
            ]);
        }
    }
}