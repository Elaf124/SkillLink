<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderVerification extends Model
{
    use HasFactory;

    protected $table = 'provider_verification';

    protected $fillable = [
        'provider_id',
        'document_category',
        'document_type',
        'document_name',
        'file_url',
        'issuing_organization',
        'issue_date',
        'credential_url',
        'verification_status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'reviewed_at' => 'datetime',
    ];



    public function provider()
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeCertifications($query)
    {
        return $query->where('document_category', 'certification');
    }

    public function scopeVerificationDocs($query)
    {
        return $query->where('document_category', 'verification');
    }
}