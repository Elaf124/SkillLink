<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'hours_logged',
        'note',
        'status',
        'hourly_rate',
        'reviewed_at',
        'logged_at',
    ];

    protected $casts = [
        'logged_at'    => 'datetime',
        'reviewed_at'  => 'datetime',
        'hours_logged' => 'decimal:2',
        'hourly_rate'  => 'decimal:2',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function getSubtotalAttribute(): float
    {
        return round((float) $this->hours_logged * (float) $this->hourly_rate, 2);
    }
}