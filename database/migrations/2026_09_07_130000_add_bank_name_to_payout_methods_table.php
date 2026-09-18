<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_methods', function (Blueprint $table) {
            // "Telebirr", "CBE Birr", "Commercial Bank of Ethiopia", "Awash Bank", ...
            $table->string('bank_name')->nullable()->after('method_type');
        });
    }

    public function down(): void
    {
        Schema::table('payout_methods', function (Blueprint $table) {
            $table->dropColumn('bank_name');
        });
    }
};
