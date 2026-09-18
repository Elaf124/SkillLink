<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_id')
                  ->constrained('provider_profiles')
                  ->cascadeOnDelete();

            $table->foreignId('payout_method_id')
                  ->nullable()
                  ->constrained('payout_methods')
                  ->nullOnDelete();

            $table->decimal('amount', 12, 2);
            $table->string('reference')->unique();
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->string('destination')->nullable(); // snapshot, e.g. "Telebirr ••••4567"
            $table->dateTime('processed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
