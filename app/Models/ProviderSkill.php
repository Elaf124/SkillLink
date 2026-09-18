<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderSkill extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'skill_id',
        'proficiency_level',
    ];



    public function provider()
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_id');
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }
}