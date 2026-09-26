<?php

namespace App\Models;

use App\Enum\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'resource_id',
        'units',
        'start_time',
        'end_time',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'units' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'expires_at' => 'datetime',
        'status' => ReservationStatus::class,
    ];

    /**
     * Get the user who created the reservation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the resource being reserved.
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /**
     * Get the complete history of this reservation.
     */
    public function histories(): HasMany
    {
        return $this->hasMany(ReservationHistory::class);
    }
}