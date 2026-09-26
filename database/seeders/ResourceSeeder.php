<?php

namespace Database\Seeders;

use App\Enum\UserRole;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class ResourceSeeder extends Seeder
{
    /**
     * Seed application resources.
     */
    public function run(): void
    {
        $admin = User::query()
            ->where('role', UserRole::ADMIN)
            ->where('email', 'admin@example.com')
            ->first();

        if (!$admin) {
            throw new RuntimeException(
                'Admin user must exist before seeding resources.'
            );
        }

        // Create the default resource used for development and testing.
        Resource::updateOrCreate(
            [
                'name' => 'Main Cinema Hall',
            ],
            [
                'created_by' => $admin->id,
                'capacity' => 10,
            ]
        );
    }
}