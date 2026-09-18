<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('job_id')
                  ->constrained('jobs')
                  ->cascadeOnDelete();

            $table->foreignId('provider_id')
                  ->constrained('provider_profiles')
                  ->cascadeOnDelete();

            $table->decimal('proposed_price', 10, 2);
            $table->text('message')->nullable();
            $table->unsignedInteger('estimated_days')->nullable();

            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};