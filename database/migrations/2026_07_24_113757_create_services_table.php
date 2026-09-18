<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_id')
                  ->constrained('provider_profiles')
                  ->cascadeOnDelete();

            $table->foreignId('category_id')
                  ->constrained('categories')
                  ->restrictOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->enum('price_type', ['fixed', 'hourly'])->default('fixed');
            $table->unsignedInteger('duration')->nullable(); // minutes, only meaningful if fixed
            $table->enum('service_status', ['active', 'inactive'])->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};