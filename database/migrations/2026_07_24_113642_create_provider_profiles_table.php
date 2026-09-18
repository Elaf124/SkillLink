<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->unique()
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->string('business_name')->nullable();
            $table->text('bio')->nullable();
            $table->string('professional_title')->nullable();
            $table->unsignedSmallInteger('experience_years')->default(0);

            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();

            $table->decimal('average_rating', 3, 2)->default(0);
            $table->unsignedInteger('completed_jobs')->default(0);

            $table->enum('verification_status', ['pending', 'verified', 'suspended'])->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_profiles');
    }
};