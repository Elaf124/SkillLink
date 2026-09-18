<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_portfolio', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_id')
                  ->constrained('provider_profiles')
                  ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_portfolio');
    }
};