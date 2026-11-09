<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only record of consequential actions: account status changes and
     * project review decisions. Entries are never updated or deleted, so the
     * history survives even when the live row is later overwritten.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Who acted. Nullable + nullOnDelete so removing a user never erases
            // the trail of what they did; actor_name keeps it readable after that.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('actor_role')->nullable();

            // What happened, e.g. account.deactivated / project.approved.
            $table->string('action', 64)->index();

            // What it happened to (polymorphic: User, Project, ...).
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();

            // One-line human summary, plus any extra detail worth keeping
            // (review comments, previous status).
            $table->text('summary');
            $table->json('context')->nullable();

            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
