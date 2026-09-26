<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'request_method',
        'request_path',
        'request_hash',
        'response_status',
        'response_body',
    ];

    protected $casts = [
        'response_status' => 'integer',
        'response_body' => 'array',
    ];
}