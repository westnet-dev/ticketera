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
            $table->enum('validation_status', ['not_requested', 'pending', 'confirmed', 'rejected'])
                ->default('not_requested')
                ->after('triage_status');
            $table->unsignedTinyInteger('resolution_rating')->nullable()->after('validation_status');
            $table->timestamp('validated_at')->nullable()->after('resolution_rating');
        });

        DB::statement("ALTER TABLE ticket_history MODIFY field ENUM('status', 'triage_status', 'validation_status', 'assigned_to') NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * The history rows tracking validation changes have no place in the
     * narrower enum, so they are dropped before it is restored.
     */
    public function down(): void
    {
        DB::table('ticket_history')->where('field', 'validation_status')->delete();

        DB::statement("ALTER TABLE ticket_history MODIFY field ENUM('status', 'triage_status', 'assigned_to') NOT NULL");

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['validation_status', 'resolution_rating', 'validated_at']);
        });
    }
};
