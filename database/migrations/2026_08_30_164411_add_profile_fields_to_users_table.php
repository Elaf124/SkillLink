<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('city', 100)->nullable()->after('phone');
            $table->string('area', 100)->nullable()->after('city');
            $table->text('bio')->nullable()->after('area');
            $table->json('languages')->nullable()->after('bio');
            $table->string('company_name')->nullable()->after('languages');
            $table->string('company_website')->nullable()->after('company_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['city', 'area', 'bio', 'languages', 'company_name', 'company_website']);
        });
    }
};
