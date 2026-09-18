<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'method_type',
        'bank_name',
        'account_name',
        'account_number',
        'is_default',
    ];

    /** Never leak the full account number to the client. */
    protected $appends = ['account_number_masked'];

    protected $hidden = ['account_number'];

    public function getAccountNumberMaskedAttribute(): string
    {
        $n = (string) $this->account_number;
        if (strlen($n) <= 4) {
            return $n;
        }

        return str_repeat('•', max(0, strlen($n) - 4)) . substr($n, -4);
    }

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function provider()
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_id');
    }
    
}