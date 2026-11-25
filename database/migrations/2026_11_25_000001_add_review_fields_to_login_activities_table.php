<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_activities', function (Blueprint $table): void {
            $table->string('review_status', 24)->nullable()->after('suspicious_reason');
            $table->timestamp('reviewed_at')->nullable()->after('review_status');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->index(['user_id', 'review_status']);
        });
    }

    public function down(): void
    {
        Schema::table('login_activities', function (Blueprint $table): void {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['user_id', 'review_status']);
            $table->dropColumn(['reviewed_by', 'reviewed_at', 'review_status']);
        });
    }
};
