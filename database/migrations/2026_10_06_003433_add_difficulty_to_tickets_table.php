<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedTinyInteger('difficulty')->nullable()->after('impact');
        });

        DB::statement("ALTER TABLE ticket_history MODIFY field ENUM('status', 'triage_status', 'validation_status', 'assigned_to', 'details', 'difficulty') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ticket_history')->where('field', 'difficulty')->delete();

        DB::statement("ALTER TABLE ticket_history MODIFY field ENUM('status', 'triage_status', 'validation_status', 'assigned_to', 'details') NOT NULL");

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('difficulty');
        });
    }
};
