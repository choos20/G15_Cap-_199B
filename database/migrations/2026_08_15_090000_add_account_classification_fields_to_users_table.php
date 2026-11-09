<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounts are now created by CELP staff rather than self-registered, and
     * each participant is classified as a Student, Employee or Alumni with the
     * details that classification requires.
     *
     * `participant_type` subdivides the existing participant role rather than
     * splitting it into three roles, so the volunteer feed, applications,
     * scanner and attendance keep working unchanged.
     *
     * Existing columns are reused where they already fit: `id_number` holds the
     * Student/Employee/Alumni ID, and `affiliation` holds the College,
     * Department or Office.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // student | employee | alumni — null for non-participant accounts.
            $table->string('participant_type', 20)->nullable()->after('role');

            // Student: Program/Course. Alumni: Program graduated.
            $table->string('program', 160)->nullable()->after('affiliation');

            // Employee only: teaching | non_teaching.
            $table->string('employment_type', 20)->nullable()->after('program');

            // Alumni only.
            $table->unsignedSmallInteger('year_graduated')->nullable()->after('employment_type');

            // Student organizations only: co_curricular | extra_curricular.
            $table->string('org_classification', 20)->nullable()->after('organization_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('participant_type');
            $table->index('org_classification');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['participant_type']);
            $table->dropIndex(['org_classification']);
            $table->dropColumn([
                'participant_type', 'program', 'employment_type',
                'year_graduated', 'org_classification',
            ]);
        });
    }
};
