<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'milestone_id',
        'amount',
        'commission',
        'provider_amount',
        'payment_method',
        'payment_status',
        'transaction_reference',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function milestone()
    {
        return $this->belongsTo(Milestone::class);
    }
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
    public function isPending(): bool
{
    return $this->payment_status === 'pending';
}

public function isFailed(): bool
{
    return $this->payment_status === 'failed';
}

public function isRefunded(): bool
{
    return $this->payment_status === 'refunded';
}
}