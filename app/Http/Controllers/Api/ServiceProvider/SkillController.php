<?php

namespace App\Http\Controllers\Api\ServiceProvider;

use App\Http\Controllers\Controller;
use App\Models\Skill;

class SkillController extends Controller
{

    public function index()
    {
        $skills = Skill::orderBy('skill_name')->get();

        return response()->json(['data' => $skills]);
    }
}