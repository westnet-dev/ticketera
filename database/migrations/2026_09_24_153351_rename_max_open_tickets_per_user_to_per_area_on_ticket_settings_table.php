<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The configured value is carried over as is: there is no sensible
     * conversion from "5 per person" to a per-area cap, so picking a new
     * number stays a business decision rather than a migration concern.
     */
    public function up(): void
    {
        Schema::table('ticket_settings', function (Blueprint $table) {
            $table->renameColumn('max_open_tickets_per_user', 'max_open_tickets_per_area');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_settings', function (Blueprint $table) {
            $table->renameColumn('max_open_tickets_per_area', 'max_open_tickets_per_user');
        });
    }
};
