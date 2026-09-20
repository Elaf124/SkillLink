<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'bio',
        'professional_title',
        'experience_years',
        'experience',
        'education',
        'latitude',
        'longitude',
        'average_rating',
        'completed_jobs',
        'verification_status',
    ];

    protected $casts = [
        'experience' => 'array',
        'education'  => 'array',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

public function services()
{
    return $this->hasMany(Service::class, 'provider_id');
}

public function providerSkills()
{
    return $this->hasMany(ProviderSkill::class, 'provider_id');
}

public function skills()
{
    return $this->belongsToMany(Skill::class, 'provider_skills','provider_id', 'skill_id')
                ->withPivot('proficiency_level')
                ->withTimestamps();
}
public function portfolio()
{
    return $this->hasMany(ProviderPortfolio::class, 'provider_id');
}

public function verifications()
{
    return $this->hasMany(ProviderVerification::class, 'provider_id');
}

public function offers()
{
    return $this->hasMany(Offer::class, 'provider_id');
}

public function bookings()
{
    return $this->hasMany(Booking::class, 'provider_id');
}

public function availability()
{
    return $this->hasMany(ProviderAvailability::class, 'provider_id');
}

public function conversations()
{
    return $this->hasMany(Conversation::class, 'provider_id');
}

public function reviews()
{
    return $this->hasMany(Review::class, 'provider_id');
}

public function favoritedBy()
{
    return $this->hasMany(Favorite::class, 'provider_id');
}

public function wallet()
{
    return $this->hasOne(Wallet::class, 'provider_id');
}

public function payoutMethods()
{
    return $this->hasMany(PayoutMethod::class, 'provider_id');
}

public function payouts()
{
    return $this->hasMany(Payout::class, 'provider_id');
}

public function isVerified(): bool
{
    return $this->verification_status === 'verified';
}
public function isPending(): bool
{
    return $this->verification_status === 'pending';
}

public function isSuspended(): bool
{
    return $this->verification_status === 'suspended';
}
}