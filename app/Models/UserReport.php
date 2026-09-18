<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporter_id',
        'reported_user_id',
        'reason',
        'status',
        'type',
        'subject',
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    /** A community report filed against another user. This is the default type. */
    public function scopeUserReports($query)
    {
        return $query->where('type', 'user');
    }

    /** A "contact support" technical inquiry — not about another user. */
    public function scopeTechnicalInquiries($query)
    {
        return $query->where('type', 'technical');
    }
}