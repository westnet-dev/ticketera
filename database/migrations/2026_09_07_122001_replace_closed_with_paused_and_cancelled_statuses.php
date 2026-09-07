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
        DB::table('tickets')->where('status', 'closed')->update(['status' => 'resolved']);

        DB::statement("ALTER TABLE tickets MODIFY status ENUM('draft', 'open', 'in_progress', 'paused', 'resolved', 'cancelled') NOT NULL DEFAULT 'open'");
    }

    /**
     * Reverse the migrations.
     *
     * This only reopens the enum to re-admit `closed`; it does not attempt
     * to un-merge the `closed` -> `resolved` backfill from up(), and any
     * `paused`/`cancelled` rows are moved to `open` since neither has an
     * equivalent in the previous enum.
     */
    public function down(): void
    {
        DB::table('tickets')->where('status', 'paused')->update(['status' => 'open']);
        DB::table('tickets')->where('status', 'cancelled')->update(['status' => 'open']);

        DB::statement("ALTER TABLE tickets MODIFY status ENUM('draft', 'open', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open'");
    }
};
