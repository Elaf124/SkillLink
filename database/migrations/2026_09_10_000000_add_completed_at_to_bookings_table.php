<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('completed_at')->nullable()->after('cancelled_at');
        });

        // Backfill existing completed bookings from their history trail so the
        // dashboard's "hide 24h after completion" rule works retroactively.
        $completed = DB::table('bookings')->where('status', 'completed')->pluck('id');
        foreach ($completed as $bookingId) {
            $changedAt = DB::table('booking_history')
                ->where('booking_id', $bookingId)
                ->where('status', 'completed')
                ->orderByDesc('changed_at')
                ->value('changed_at');

            DB::table('bookings')->where('id', $bookingId)->update([
                'completed_at' => $changedAt ?: DB::table('bookings')->where('id', $bookingId)->value('updated_at'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
