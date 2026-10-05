<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_linear_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('linear_issue_id');
            $table->string('identifier', 32);
            $table->text('title');
            $table->string('url', 2048);
            $table->string('state_name', 100);
            $table->string('state_type', 20);
            $table->string('assignee_name')->nullable();
            // A string rather than an enum column, so a new source never needs an ALTER on the enum.
            $table->string('source', 20);
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['ticket_id', 'linear_issue_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_linear_links');
    }
};
