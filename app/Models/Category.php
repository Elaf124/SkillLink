<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'parent_category_id',
    ];

   

    // self-referencing: a category can have a parent
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_category_id');
    }

    // self-referencing: a category can have many sub-categories
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_category_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function jobs()
    {
        return $this->hasMany(Job::class);
    }
}