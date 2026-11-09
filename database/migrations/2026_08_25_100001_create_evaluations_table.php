<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One evaluation per completed activity — the container the individual
     * beneficiary responses hang off.
     *
     * Beneficiaries answer on paper at the venue; the project initiator
     * encodes those forms here afterwards. This row tracks how many forms
     * are expected and whether the batch has been closed.
     */
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();

            // An activity is evaluated once. Unique keeps a second batch from
            // being opened alongside the first.
            $table->foreignId('announcement_id')->unique()->constrained()->cascadeOnDelete();

            // How many beneficiaries attended, so progress reads "12 of 38".
            // Null until the initiator records the headcount.
            $table->unsignedInteger('expected_beneficiaries')->nullable();

            // Set when the initiator closes the batch; null while encoding.
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
