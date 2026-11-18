<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->unsignedInteger('paper_beneficiary_count')->nullable();
            $table->unsignedInteger('paper_implementer_count')->nullable();
            $table->boolean('correction_in_progress')->default(false);
        });
        Schema::create('evaluation_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('reason', 500)->nullable();
            $table->unsignedInteger('beneficiary_count');
            $table->unsignedInteger('implementer_count');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_history');
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropColumn(['paper_beneficiary_count', 'paper_implementer_count', 'correction_in_progress']);
        });
    }
};
