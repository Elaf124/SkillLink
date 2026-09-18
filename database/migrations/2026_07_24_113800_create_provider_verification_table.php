<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_verification', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_id')
                  ->constrained('provider_profiles')
                  ->cascadeOnDelete();

            $table->enum('document_category', ['verification', 'certification'])->default('verification');

            $table->string('document_type');
            $table->string('document_name');
            $table->string('file_url')->nullable();

            $table->string('issuing_organization')->nullable(); // certifications only
            $table->date('issue_date')->nullable();              // certifications only
            $table->string('credential_url')->nullable();        // certifications only

            $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->foreignId('reviewed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_verification');
    }
};