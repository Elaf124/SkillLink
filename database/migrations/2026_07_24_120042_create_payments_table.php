<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                  ->constrained('bookings')
                  ->cascadeOnDelete();

            $table->foreignId('milestone_id')
                  ->nullable()
                  ->constrained('milestones')
                  ->nullOnDelete();

            $table->decimal('amount', 10, 2);
            $table->decimal('commission', 10, 2);
            $table->decimal('provider_amount', 10, 2);

            $table->enum('payment_method', ['chapa_sim', 'telebirr_sim', 'cbe_birr_sim', 'cash_sim']);
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');

            $table->string('transaction_reference')->nullable();
            $table->dateTime('paid_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};