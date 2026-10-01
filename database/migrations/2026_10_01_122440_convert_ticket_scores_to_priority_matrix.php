<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maps each level (1 low, 2 medium, 3 high) to the inclusive 1-10 range it absorbs,
     * the same cut the UI already used to label priorities.
     *
     * @var array<int, array{0: int, 1: int}>
     */
    private const LEVEL_RANGES = [
        1 => [1, 3],
        2 => [4, 6],
        3 => [7, 10],
    ];

    /**
     * The importance × urgency matrix, as [importance][urgency] => priority (1 low … 4 critical).
     *
     * Kept here instead of reading App\Enums\TicketPriority so the migration
     * keeps doing what it did at the time it was written.
     *
     * @var array<int, array<int, int>>
     */
    private const PRIORITY_MATRIX = [
        3 => [1 => 2, 2 => 3, 3 => 4],
        2 => [1 => 1, 2 => 2, 3 => 3],
        1 => [1 => 1, 2 => 1, 3 => 2],
    ];

    /**
     * Value each level goes back to on rollback, matching the previous numeric migration.
     *
     * @var array<int, int>
     */
    private const ROLLBACK_SCORES = [
        3 => 8,
        2 => 5,
        1 => 2,
    ];

    /**
     * Run the migrations.
     *
     * The old priority becomes the importance, urgency and impact are bucketed
     * in place, and priority is recomputed from the matrix.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedTinyInteger('importance')->default(2)->after('description');
        });

        $this->backfill();

        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedTinyInteger('priority')->default(2)->change();
            $table->unsignedTinyInteger('urgency')->default(2)->change();
            $table->unsignedTinyInteger('impact')->default(2)->change();
        });
    }

    /**
     * Convert the 1-10 scores of every ticket to levels and derive the priority.
     *
     * Kept apart from the schema changes so it can be exercised on its own.
     */
    private function backfill(): void
    {
        foreach (self::LEVEL_RANGES as $level => $range) {
            DB::table('tickets')->whereBetween('priority', $range)->update(['importance' => $level]);
        }

        // Bucketing in place goes from low to high: a level written in one pass
        // (1, 2 or 3) never falls in a range a later pass rewrites.
        foreach (['urgency', 'impact'] as $column) {
            foreach (self::LEVEL_RANGES as $level => $range) {
                DB::table('tickets')->whereBetween($column, $range)->update([$column => $level]);
            }
        }

        foreach (self::PRIORITY_MATRIX as $importance => $row) {
            foreach ($row as $urgency => $priority) {
                DB::table('tickets')
                    ->where('importance', $importance)
                    ->where('urgency', $urgency)
                    ->update(['priority' => $priority]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Only approximate: three levels cannot give back the original 1-10 scores,
     * so each level goes back to a representative value (low 2, medium 5, high 8)
     * and the old priority is rebuilt from the importance.
     */
    public function down(): void
    {
        // From high to low, so a rewritten value (8, 5, 2) is never picked up again.
        foreach (['urgency', 'impact'] as $column) {
            foreach (self::ROLLBACK_SCORES as $level => $score) {
                DB::table('tickets')->where($column, $level)->update([$column => $score]);
            }
        }

        foreach (self::ROLLBACK_SCORES as $level => $score) {
            DB::table('tickets')->where('importance', $level)->update(['priority' => $score]);
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedTinyInteger('priority')->default(5)->change();
            $table->unsignedTinyInteger('urgency')->default(5)->change();
            $table->unsignedTinyInteger('impact')->default(5)->change();
            $table->dropColumn('importance');
        });
    }
};
