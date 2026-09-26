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
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();

            // Unique key provided by the client.
            $table->string('key', 128)
                ->unique();

            // HTTP method of the original request.
            $table->string('request_method', 10);

            // API path of the original request.
            $table->string('request_path', 255);

            // SHA-256 hash of the request payload.
            $table->char('request_hash', 64);

            // Stored response status code.
            $table->unsignedSmallInteger('response_status')
                ->nullable();

            // Stored response body.
            $table->json('response_body')
                ->nullable();

            $table->timestamps();

            $table->index([
                'request_method',
                'request_path',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};