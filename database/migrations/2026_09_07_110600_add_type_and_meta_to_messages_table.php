<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // 'text' = normal chat, 'proposal' = price quote, 'system' = generated notice
            $table->string('type')->default('text')->after('message');
            // structured payload for proposal messages (amount, note, status, ...)
            $table->json('meta')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['type', 'meta']);
        });
    }
};
