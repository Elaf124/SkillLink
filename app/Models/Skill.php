<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    use HasFactory;

    protected $fillable = [
        'skill_name',
    ];

    

    public function providerSkills()
    {
        return $this->hasMany(ProviderSkill::class);
    }

    // convenience: get all providers who have this skill, through the pivot
    public function providers()
    {
        return $this->belongsToMany(ProviderProfile::class, 'provider_skills','skill_id', 'provider_id')
                    ->withPivot('proficiency_level')
                    ->withTimestamps();
    }
}