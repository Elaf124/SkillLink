<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'role_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'profile_photo',
        'timezone',
        'country',
        'display_currency',
        'account_status',
        'google_id',
        // Profile fields updated from the account settings page
        'city',
        'area',
        'bio',
        'languages',
        'company_name',
        'company_website',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'languages' => 'array',
        'email_verified_at' => 'datetime',
    ];

    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    public function markEmailAsVerified(): void
    {
        $this->forceFill(['email_verified_at' => now()])->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Core relationships
    |--------------------------------------------------------------------------
    */

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function customerProfile()
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function providerProfile()
    {
        return $this->hasOne(ProviderProfile::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Marketplace activity (as a customer)
    |--------------------------------------------------------------------------
    */

    public function jobs()
    {
        return $this->hasMany(Job::class, 'customer_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'customer_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'customer_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Communication
    |--------------------------------------------------------------------------
    */

    public function conversations()
    {
        return $this->hasMany(Conversation::class, 'customer_id');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function appNotifications()
    {
        return $this->hasMany(Notification::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Trust & safety
    |--------------------------------------------------------------------------
    */

    public function reportsFiled()
    {
        return $this->hasMany(UserReport::class, 'reporter_id');
    }

    public function reportsReceived()
    {
        return $this->hasMany(UserReport::class, 'reported_user_id');
    }

    public function disputesRaised()
    {
        return $this->hasMany(Dispute::class, 'raised_by');
    }

    public function disputesResolved()
    {
        return $this->hasMany(Dispute::class, 'resolved_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Admin / audit trail
    |--------------------------------------------------------------------------
    */

    public function bookingHistoryChanges()
    {
        return $this->hasMany(BookingHistory::class, 'changed_by');
    }

    public function reviewedVerifications()
    {
        return $this->hasMany(ProviderVerification::class, 'reviewed_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Role helper methods
    |--------------------------------------------------------------------------
    */

    public function isCustomer(): bool
    {
        return $this->role->name === 'customer';
    }

    public function isProvider(): bool
    {
        return $this->role->name === 'provider';
    }

    public function isAdminSupport(): bool
    {
        return $this->role->name === 'admin_support';
    }

    public function isAdminFinance(): bool
    {
        return $this->role->name === 'admin_finance';
    }

}