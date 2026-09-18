<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'offer_id',
        'customer_id',
        'provider_id',
        'service_id',
        'booking_date',
        'booking_time',
        'customer_timezone',
        'provider_timezone',
        'status',
        'total_amount',
        'rejection_reason',
        'cancellation_reason',
        'cancelled_at',
        'completed_at',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function provider()
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function milestones()
    {
        return $this->hasMany(Milestone::class);
    }

    public function history()
    {
        return $this->hasMany(BookingHistory::class);
    }

    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function dispute()
    {
        return $this->hasOne(Dispute::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function timeLogs()
    {
        return $this->hasMany(TimeLog::class);
    }

    
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isAwaitingConfirmation(): bool
    {
        return $this->status === 'awaiting_confirmation';
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['cancelled_by_customer', 'cancelled_by_provider']);
    }
  
public function walletTransactions()
{
    return $this->hasMany(WalletTransaction::class);
}
public function isPending(): bool
{
    return $this->status === 'pending';
}

public function isAccepted(): bool
{
    return $this->status === 'accepted';
}

public function isInProgress(): bool
{
    return $this->status === 'in_progress';
}

public function isRejected(): bool
{
    return $this->status === 'rejected';
}

public function isDisputed(): bool
{
    return $this->status === 'disputed';
}
}
