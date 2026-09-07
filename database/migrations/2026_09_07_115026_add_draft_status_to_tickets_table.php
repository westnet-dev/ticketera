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
        DB::statement("ALTER TABLE tickets MODIFY status ENUM('draft', 'open', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open'");
    }

    /**
     * Reverse the migrations.
     *
     * `draft` has no equivalent in the previous enum, so any draft tickets
     * are moved to `open` before the column is narrowed back down.
     */
    public function down(): void
    {
        DB::table('tickets')->where('status', 'draft')->update(['status' => 'open']);

        DB::statement("ALTER TABLE tickets MODIFY status ENUM('open', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open'");
    }
};
