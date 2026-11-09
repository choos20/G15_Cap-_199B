<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_participant_enrollments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('participant_name')->nullable();
            $table->string('participant_id_number', 50)->nullable();
            $table->string('roster_code', 30)->nullable()->unique();
            $table->foreignId('attendance_session_id')->nullable()->constrained('attendance_sessions')->nullOnDelete();
            $table->string('attendance_check_in', 20)->nullable();
            $table->string('attendance_check_out', 20)->nullable();
            $table->string('attendance_status', 20)->nullable();
            $table->timestamp('attendance_recorded_at')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('project_participant_enrollments')->whereNull('user_id')->exists()) {
            throw new \RuntimeException('Remove accountless participant enrollments before rolling back this migration.');
        }

        Schema::table('project_participant_enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_session_id');
            $table->dropUnique(['roster_code']);
            $table->dropColumn([
                'participant_name',
                'participant_id_number',
                'roster_code',
                'attendance_check_in',
                'attendance_check_out',
                'attendance_status',
                'attendance_recorded_at',
            ]);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
