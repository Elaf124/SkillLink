<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'file_url',
    ];


    public function job()
    {
        return $this->belongsTo(Job::class);
    }
}