<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            // User who created the reservation.
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            // Resource being reserved.
            $table->foreignId('resource_id')->constrained('resources')->restrictOnDelete();

            // Number of units reserved.
            $table->unsignedInteger('units');

            // Reservation interval: [start_time, end_time)
            $table->dateTime('start_time');
            $table->dateTime('end_time');

            // Current reservation status.
            $table->enum('status', ['pending','confirmed','cancelled','expired'])->default('pending');

            // Pending reservations expire after two minutes.
            $table->dateTime('expires_at')->nullable();

            $table->timestamps();

            // Used for time-overlap queries.
            $table->index([
                'resource_id',
                'start_time',
                'end_time',
            ]);

            // Used when calculating occupied capacity.
            $table->index([
                'resource_id',
                'status',
            ]);

            // Used by expiration processing.
            $table->index('expires_at');

            // Useful for querying a user's reservations.
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};