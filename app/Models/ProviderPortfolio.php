<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderPortfolio extends Model
{
    use HasFactory;

  protected $table = 'provider_portfolio';
   
    protected $fillable = [
        'provider_id',
        'title',
        'description',
        'image_url',
    ];


    public function provider()
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_id');
    }
}