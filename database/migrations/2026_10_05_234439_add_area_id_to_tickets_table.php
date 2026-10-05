<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Existing tickets inherit their author's current area, which is what the
     * area they belonged to meant until now. Runs before users.area_id is
     * dropped. Deleting an area with tickets is blocked by AreaPolicy; the
     * restrictOnDelete backs that up at the database level.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('user_id')->constrained()->restrictOnDelete();
        });

        DB::table('tickets')->update([
            'area_id' => DB::raw('(select users.area_id from users where users.id = tickets.user_id)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
        });
    }
};
