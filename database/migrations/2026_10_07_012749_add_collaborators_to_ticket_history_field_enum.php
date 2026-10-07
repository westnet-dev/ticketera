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
        DB::statement("ALTER TABLE ticket_history MODIFY field ENUM('status', 'triage_status', 'validation_status', 'assigned_to', 'details', 'difficulty', 'collaborators') NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * The rows recording collaborator changes have no place in the narrower
     * enum, so they are dropped before it is restored.
     */
    public function down(): void
    {
        DB::table('ticket_history')->where('field', 'collaborators')->delete();

        DB::statement("ALTER TABLE ticket_history MODIFY field ENUM('status', 'triage_status', 'validation_status', 'assigned_to', 'details', 'difficulty') NOT NULL");
    }
};
