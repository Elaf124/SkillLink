<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                  ->constrained('bookings')
                  ->cascadeOnDelete();

            $table->string('status');
            $table->text('remarks')->nullable();

            $table->foreignId('changed_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->dateTime('changed_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_history');
    }
};