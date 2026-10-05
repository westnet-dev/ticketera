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
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * A single column cannot hold several areas, so rolling back keeps only
     * the lowest area id each user has and drops the rest.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        DB::table('users')->update([
            'area_id' => DB::raw('(select min(area_user.area_id) from area_user where area_user.user_id = users.id)'),
        ]);
    }
};
