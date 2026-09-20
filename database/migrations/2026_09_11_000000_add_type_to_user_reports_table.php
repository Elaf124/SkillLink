<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets `user_reports` also carry the "technical inquiry" contact form from
 * /support — a customer/provider reporting a platform issue rather than
 * another user. `reported_user_id` becomes nullable (a technical inquiry has
 * no reported user) and a `type` + `subject` column are added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_reports', function (Blueprint $table) {
            $table->dropForeign(['reported_user_id']);
        });

        Schema::table('user_reports', function (Blueprint $table) {
            $table->foreignId('reported_user_id')->nullable()->change();
            $table->string('type', 20)->default('user')->after('reported_user_id'); // 'user' | 'technical'
            $table->string('subject', 150)->nullable()->after('type');
        });

        Schema::table('user_reports', function (Blueprint $table) {
            $table->foreign('reported_user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Reuse the existing status enum but add 'resolved' for technical inquiries
        // (user reports keep using pending/reviewed/dismissed as before).
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE user_reports MODIFY status ENUM('pending','reviewed','dismissed','resolved') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        Schema::table('user_reports', function (Blueprint $table) {
            $table->dropForeign(['reported_user_id']);
            $table->dropColumn(['type', 'subject']);
        });

        Schema::table('user_reports', function (Blueprint $table) {
            $table->foreignId('reported_user_id')->nullable(false)->change();
            $table->foreign('reported_user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE user_reports MODIFY status ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending'");
        }
    }
};
