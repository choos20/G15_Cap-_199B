<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_participant_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('participation_type', 30);
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
            $table->index(['project_id', 'participation_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_participant_enrollments');
    }
};
