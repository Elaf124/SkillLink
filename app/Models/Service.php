<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'category_id',
        'title',
        'description',
        'price',
        'price_type',
        'duration',
        'service_status',
    ];


    public function provider()
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
public function isHourly(): bool
    {
        return $this->price_type === 'hourly';
    }
    public function isFixedPrice(): bool
{
    return $this->price_type === 'fixed';
}
}