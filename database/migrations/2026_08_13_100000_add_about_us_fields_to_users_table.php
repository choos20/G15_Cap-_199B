<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "About Us" profile for project initiators (student orgs, colleges and
 * departments). organization_name / org_logo / org_description already exist;
 * this adds the rest of the public profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('org_acronym', 32)->nullable()->after('organization_name');
            $table->string('org_college')->nullable()->after('org_acronym');
            $table->unsignedSmallInteger('org_year_founded')->nullable()->after('org_college');
            $table->text('org_vision')->nullable()->after('org_description');
            $table->text('org_mission')->nullable()->after('org_vision');
            // One goal per line; rendered as a numbered list on the public profile.
            $table->text('org_goals')->nullable()->after('org_mission');
            $table->json('org_advocacies')->nullable()->after('org_goals');
            $table->string('org_contact_email')->nullable()->after('org_advocacies');
            $table->string('org_facebook')->nullable()->after('org_contact_email');
            $table->timestamp('org_profile_updated_at')->nullable()->after('org_facebook');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'org_acronym', 'org_college', 'org_year_founded',
                'org_vision', 'org_mission', 'org_goals', 'org_advocacies',
                'org_contact_email', 'org_facebook', 'org_profile_updated_at',
            ]);
        });
    }
};
