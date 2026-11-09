<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per beneficiary form. Each saved form is its own response, so
     * averages are computed by the system and never hand-tallied.
     *
     * `source` records how the form arrived. Everything encoded through the
     * initiator's screen is 'paper'; the column exists so an on-site digital
     * form could be added later without a migration or a change to the
     * summary, which already breaks the count down by source.
     */
    public function up(): void
    {
        Schema::create('evaluation_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();

            $table->string('source', 20)->default('paper');

            // How the respondent heard about the activity. Optional: paper
            // forms are often returned with this item left blank.
            $table->string('heard_about', 40)->nullable();

            $table->text('suggestions')->nullable();
            $table->text('observations')->nullable();

            // Who keyed this form in. Null on delete so removing a staff
            // account never destroys the response itself.
            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['evaluation_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_responses');
    }
};
