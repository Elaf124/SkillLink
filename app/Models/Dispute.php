<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'raised_by',
        'reason',
        'evidence',
        'status',
        'resolution',
        'resolved_by',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function raisedBy()
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }
}
