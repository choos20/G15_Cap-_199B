<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One score per criterion per form, on the 1-5 scale printed on the
     * paper instrument (1 Poor to 5 Outstanding).
     *
     * A row per score rather than five columns on the response, so the set
     * of criteria can change over time without a schema change and the
     * summary can average each criterion with a plain group-by.
     */
    public function up(): void
    {
        Schema::create('evaluation_response_ratings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('response_id')
                ->constrained('evaluation_responses')
                ->cascadeOnDelete();

            // Restrict, not cascade: a criterion that has been scored must be
            // retired via is_active, never deleted out from under its scores.
            $table->foreignId('criterion_id')
                ->constrained('evaluation_criteria')
                ->restrictOnDelete();

            $table->unsignedTinyInteger('score');

            $table->timestamps();

            // A form carries at most one score per criterion.
            $table->unique(['response_id', 'criterion_id']);
            $table->index('criterion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_response_ratings');
    }
};
