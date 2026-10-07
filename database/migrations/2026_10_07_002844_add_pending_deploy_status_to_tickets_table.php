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
        DB::statement("ALTER TABLE tickets MODIFY status ENUM('draft', 'open', 'in_progress', 'paused', 'awaiting_response', 'pending_deploy', 'resolved', 'cancelled') NOT NULL DEFAULT 'open'");
    }

    /**
     * Reverse the migrations.
     *
     * `pending_deploy` rows are moved to `in_progress`, since the team still
     * owes them the deploy, before the value is dropped.
     */
    public function down(): void
    {
        DB::table('tickets')->where('status', 'pending_deploy')->update(['status' => 'in_progress']);

        DB::statement("ALTER TABLE tickets MODIFY status ENUM('draft', 'open', 'in_progress', 'paused', 'awaiting_response', 'resolved', 'cancelled') NOT NULL DEFAULT 'open'");
    }
};
