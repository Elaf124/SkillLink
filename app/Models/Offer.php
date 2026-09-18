<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'provider_id',
        'proposed_price',
        'message',
        'estimated_days',
        'status',
    ];


    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function provider()
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_id');
    }

    public function booking()
    {
        return $this->hasOne(Booking::class);
    }
    public function isPending(): bool
{
    return $this->status === 'pending';
}

public function isAccepted(): bool
{
    return $this->status === 'accepted';
}

public function isRejected(): bool
{
    return $this->status === 'rejected';
}
}