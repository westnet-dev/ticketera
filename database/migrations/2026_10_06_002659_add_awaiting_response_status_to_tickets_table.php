<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE tickets MODIFY status ENUM('draft', 'open', 'in_progress', 'paused', 'awaiting_response', 'resolved', 'cancelled') NOT NULL DEFAULT 'open'");
    }

    /**
     * Reverse the migrations.
     *
     * `awaiting_response` rows are moved to `paused`, the closest status in
     * the previous enum, before the value is dropped.
     */
    public function down(): void
    {
        DB::table('tickets')->where('status', 'awaiting_response')->update(['status' => 'paused']);

        DB::statement("ALTER TABLE tickets MODIFY status ENUM('draft', 'open', 'in_progress', 'paused', 'resolved', 'cancelled') NOT NULL DEFAULT 'open'");
    }
};
