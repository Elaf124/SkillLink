<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The booking lifecycle was trimmed from
     *   pending -> accepted -> in_progress -> awaiting_confirmation -> completed
     * to
     *   pending -> accepted -> awaiting_confirmation -> completed
     *
     * The provider-driven "in_progress" step was redundant with "accepted", so it
     * is being removed. Existing bookings sitting in "in_progress" are moved back
     * to "accepted" (the frontend already renders legacy in_progress bookings as
     * accepted, and the provider can then mark them awaiting confirmation).
     */
    private array $newStatuses = [
        'pending',
        'accepted',
        'awaiting_confirmation',
        'completed',
        'cancelled_by_customer',
        'cancelled_by_provider',
        'rejected',
        'disputed',
    ];

    private array $oldStatuses = [
        'pending',
        'accepted',
        'in_progress',
        'awaiting_confirmation',
        'completed',
        'cancelled_by_customer',
        'cancelled_by_provider',
        'rejected',
        'disputed',
    ];

    public function up(): void
    {
        // 1. Data migration: in_progress -> accepted (while the enum still allows both).
        DB::table('bookings')->where('status', 'in_progress')->update(['status' => 'accepted']);

        // 2. Tighten the column definition where the driver supports enums.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $this->setMysqlEnum($this->newStatuses);
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $this->setMysqlEnum($this->oldStatuses);
        }
    }

    private function setMysqlEnum(array $statuses): void
    {
        $list = collect($statuses)
            ->map(fn ($s) => "'" . $s . "'")
            ->implode(', ');

        DB::statement(
            "ALTER TABLE bookings MODIFY status ENUM($list) NOT NULL DEFAULT 'pending'"
        );
    }
};
