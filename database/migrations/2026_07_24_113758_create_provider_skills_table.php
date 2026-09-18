<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_skills', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_id')
                  ->constrained('provider_profiles')
                  ->cascadeOnDelete();

            $table->foreignId('skill_id')
                  ->constrained('skills')
                  ->cascadeOnDelete();

            $table->enum('proficiency_level', ['beginner', 'intermediate', 'expert'])->default('beginner');

            $table->unique(['provider_id', 'skill_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_skills');
    }
};