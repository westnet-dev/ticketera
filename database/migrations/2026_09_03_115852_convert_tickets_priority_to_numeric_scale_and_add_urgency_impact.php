<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maps the old categorical priority to its numeric (1-10) equivalent.
     *
     * @var array<string, int>
     */
    private const LEGACY_PRIORITY_MAP = [
        'low' => 2,
        'medium' => 5,
        'high' => 8,
        'urgent' => 10,
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ENUM -> TINYINT can't be done with a plain MODIFY: MySQL would cast
        // the non-numeric strings to 0, losing the original value. Rename the
        // old column first so it survives long enough to backfill from it.
        DB::statement("ALTER TABLE tickets CHANGE priority priority_legacy ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'low'");

        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedTinyInteger('priority')->default(5)->after('priority_legacy');
            $table->unsignedTinyInteger('urgency')->default(5)->after('priority');
            $table->unsignedTinyInteger('impact')->default(5)->after('urgency');
        });

        foreach (self::LEGACY_PRIORITY_MAP as $legacy => $numeric) {
            DB::table('tickets')->where('priority_legacy', $legacy)->update([
                'priority' => $numeric,
                'urgency' => $numeric,
                'impact' => $numeric,
            ]);
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('priority_legacy');
        });
    }

    /**
     * Reverse the migrations.
     *
     * This approximates the original category from the numeric priority by
     * range (1-3 low, 4-6 medium, 7-8 high, 9-10 urgent). It cannot recover
     * the exact original category once a value has been edited, and it
     * discards urgency/impact entirely (they didn't exist before this
     * migration).
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE tickets CHANGE priority priority_legacy TINYINT UNSIGNED NOT NULL DEFAULT 5');

        Schema::table('tickets', function (Blueprint $table) {
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('low')->after('priority_legacy');
        });

        DB::table('tickets')->where('priority_legacy', '<=', 3)->update(['priority' => 'low']);
        DB::table('tickets')->whereBetween('priority_legacy', [4, 6])->update(['priority' => 'medium']);
        DB::table('tickets')->whereBetween('priority_legacy', [7, 8])->update(['priority' => 'high']);
        DB::table('tickets')->where('priority_legacy', '>=', 9)->update(['priority' => 'urgent']);

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['priority_legacy', 'urgency', 'impact']);
        });
    }
};
