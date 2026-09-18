<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('job_id')->nullable()->constrained('jobs')->nullOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained('offers')->nullOnDelete();

            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('provider_profiles')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();

            $table->date('booking_date')->nullable();
            $table->time('booking_time')->nullable();

            $table->string('customer_timezone')->nullable();
            $table->string('provider_timezone')->nullable();

            $table->enum('status', [
                'pending',
                'accepted',
                'in_progress',
                'awaiting_confirmation',
                'completed',
                'cancelled_by_customer',
                'cancelled_by_provider',
                'rejected',
                'disputed',
            ])->default('pending');

            $table->decimal('total_amount', 10, 2);

            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};