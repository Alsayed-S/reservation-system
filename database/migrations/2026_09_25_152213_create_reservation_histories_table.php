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
        Schema::create('reservation_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reservation_id')
                ->constrained('reservations')
                ->cascadeOnDelete();

            // Action performed on the reservation.
            $table->enum('action', [
                'created',
                'confirmed',
                'cancelled',
                'expired',
                'updated',
            ]);

            // Reservation state before the change.
            $table->json('old_data')->nullable();

            // Reservation state after the change.
            $table->json('new_data')->nullable();

            $table->timestamp('created_at')
                ->useCurrent();

            $table->index([
                'reservation_id',
                'created_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_histories');
    }
};