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
     * The default categories are seeded here rather than in a seeder so every
     * environment, production included, starts with them after migrating.
     * They go through the query builder to stay independent of the model.
     */
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        $now = now();

        DB::table('ticket_categories')->insert(
            collect(['Error', 'Nueva funcionalidad', 'Consulta'])
                ->map(fn (string $name) => ['name' => $name, 'created_at' => $now, 'updated_at' => $now])
                ->all()
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_categories');
    }
};
