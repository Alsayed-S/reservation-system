<?php

namespace App\Services;

use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdempotencyService
{
    /**
     * Execute an idempotent write operation.
     *
     * The idempotency record and the business mutation
     * are committed in the same database transaction.
     */
    public function execute(
        string $key,
        string $requestMethod,
        string $requestPath,
        string $requestHash,
        callable $operation
    ): mixed {
        return DB::transaction(function () use (
            $key,
            $requestMethod,
            $requestPath,
            $requestHash,
            $operation
        ) {
            /*
             * Attempt to create the idempotency record.
             *
             * A unique constraint on "key" protects against
             * concurrent requests using the same idempotency key.
             */
            DB::table('idempotency_keys')->insertOrIgnore([
                'key' => $key,
                'request_method' => $requestMethod,
                'request_path' => $requestPath,
                'request_hash' => $requestHash,
                'response_status' => null,
                'response_body' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
             * Lock the idempotency row.
             *
             * If another request is currently processing the
             * same key, this waits until that transaction finishes.
             */
            $idempotency = IdempotencyKey::query()
                ->where('key', $key)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * The same key must always represent the same request.
             */
            if ($idempotency->request_hash !== $requestHash) {
                throw ValidationException::withMessages([
                    'Idempotency-Key' => [
                        'The Idempotency-Key has already been used with a different request.',
                    ],
                ]);
            }

            /*
             * A stored response means the operation has already
             * completed successfully.
             */
            if ($idempotency->response_status !== null) {
                return [
                    'replay' => true,
                    'status' => $idempotency->response_status,
                    'body' => $idempotency->response_body,
                ];
            }

            /*
             * Execute the actual business operation.
             */
            $result = $operation();

            /*
             * Store the response before committing the transaction.
             */
            $idempotency->update([
                'response_status' => $result['status'],
                'response_body' => $result['body'],
            ]);

            return [
                'replay' => false,
                'status' => $result['status'],
                'body' => $result['body'],
                'result' => $result['result'] ?? null,
            ];
        });
    }
}