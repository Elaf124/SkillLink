<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                  ->unique()
                  ->constrained('bookings')
                  ->cascadeOnDelete();

            $table->foreignId('customer_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->foreignId('provider_id')
                  ->constrained('provider_profiles')
                  ->cascadeOnDelete();

            $table->unsignedTinyInteger('communication_rating');
            $table->unsignedTinyInteger('quality_rating');
            $table->unsignedTinyInteger('timeliness_rating');
            $table->unsignedTinyInteger('professionalism_rating');
            $table->decimal('overall_rating', 3, 2);

            $table->text('comment')->nullable();
            $table->text('provider_response')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};