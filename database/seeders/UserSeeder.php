<?php

namespace Database\Seeders;

use App\Enum\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed application users.
     */
    public function run(): void
    {
        // Create the admin user.
        User::updateOrCreate(
            [
                'email' => 'admin@example.com',
            ],
            [
                'name' => 'System Admin',
                'password' => Hash::make('12345678'),
                'role' => UserRole::ADMIN,
            ]
        );

        // Create a normal user.
        User::updateOrCreate(
            [
                'email' => 'user@example.com',
            ],
            [
                'name' => 'Test User',
                'password' => Hash::make('12345678'),
                'role' => UserRole::USER,
            ]
        );

        // Create another normal user for concurrency testing.
        User::updateOrCreate(
            [
                'email' => 'user2@example.com',
            ],
            [
                'name' => 'Test User 2',
                'password' => Hash::make('12345678'),
                'role' => UserRole::USER,
            ]
        );
    }
}