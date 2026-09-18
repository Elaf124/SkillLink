<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->dateTime('paid_at')->nullable()->after('completed_at');
        });

        Schema::table('time_logs', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('note');
            $table->decimal('hourly_rate', 10, 2)->default(0)->after('status');
            $table->dateTime('reviewed_at')->nullable()->after('hourly_rate');
        });
    }

    public function down(): void
    {
        Schema::table('milestones', fn (Blueprint $t) => $t->dropColumn('paid_at'));
        Schema::table('time_logs', fn (Blueprint $t) => $t->dropColumn(['status', 'hourly_rate', 'reviewed_at']));
    }
};
