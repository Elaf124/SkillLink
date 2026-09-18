<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->foreignId('role_id')
                  ->constrained('roles')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');
            $table->string('profile_photo')->nullable();

            $table->string('timezone')->default('Africa/Addis_Ababa');
            $table->string('country', 100)->nullable();
            $table->string('display_currency', 3)->default('ETB');

            $table->enum('account_status', ['active', 'inactive', 'suspended'])->default('active');

            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};