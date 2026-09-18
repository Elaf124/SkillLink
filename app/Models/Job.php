<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'category_id',
        'title',
        'description',
        'budget',
        'location',
        'latitude',
        'longitude',
        'preferred_date',
        'status',
    ];

    protected $casts = [
        'preferred_date' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function attachments()
    {
        return $this->hasMany(JobAttachment::class);
    }

    public function offers()
    {
        return $this->hasMany(Offer::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
     
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
     public function isInProgress(): bool
{
    return $this->status === 'in_progress';
}

public function isClosed(): bool
{
    return $this->status === 'closed';
}

public function isCancelled(): bool
{
    return $this->status === 'cancelled';
}
}