<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationCode extends Model
{
    protected $table = 'verification_codes';

    protected $fillable = [
        'user_id',
        'email',
        'code',
        'type',
        'expires_at',
        'last_sent_at',
    ];

    protected $casts = [
        'expires_at'   => 'datetime',
        'last_sent_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
