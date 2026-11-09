<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_profiles', function (Blueprint $table) {
            $table->bigIncrements('organization_id');
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('organization_name');
            $table->string('acronym', 32)->nullable();
            $table->string('type')->nullable();
            $table->string('org_classification', 20)->nullable();
            $table->unsignedSmallInteger('year_founded')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();
            $table->text('vision')->nullable();
            $table->text('mission')->nullable();
            $table->text('goals')->nullable();
            $table->json('advocacies')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('profile_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('contact_number')->nullable();
            $table->string('avatar_url')->nullable();
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organization_profiles', 'organization_id')
                ->nullOnDelete();
        });

        Schema::create('user_credentials', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('username')->unique();
            $table->string('password');
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('password_updated_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->unsignedSmallInteger('login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
        });

        Schema::create('login_histories', function (Blueprint $table) {
            $table->bigIncrements('login_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('logged_in_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('was_successful')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'logged_in_at']);
        });

        Schema::create('student_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('program')->nullable();
            $table->string('department')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('employment_type', 20)->nullable();
            $table->string('department')->nullable();
            $table->timestamps();
        });

        Schema::create('alumni_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('program_graduated')->nullable();
            $table->unsignedSmallInteger('year_graduated')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_proposals', function (Blueprint $table) {
            $table->bigIncrements('activity_id');
            $table->unsignedBigInteger('legacy_project_id')->nullable()->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organization_profiles', 'organization_id')
                ->nullOnDelete();
            $table->text('extension_agenda')->nullable();
            $table->string('curricular_program')->nullable();
            $table->string('venue')->nullable();
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->string('target_beneficiaries')->nullable();
            $table->unsignedInteger('beneficiaries_count')->nullable();
            $table->string('implementation_status')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->string('status')->default('submitted');
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('activity_approval_workflows', function (Blueprint $table) {
            $table->bigIncrements('workflow_id');
            $table->foreignId('activity_id')
                ->constrained('activity_proposals', 'activity_id')
                ->cascadeOnDelete();
            $table->string('approval_level');
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approval_status')->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['activity_id', 'approval_level']);
        });

        Schema::create('files', function (Blueprint $table) {
            $table->bigIncrements('file_id');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_files', function (Blueprint $table) {
            $table->bigIncrements('activity_file_id');
            $table->foreignId('activity_id')
                ->constrained('activity_proposals', 'activity_id')
                ->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('files', 'file_id')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_category')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'file_id']);
        });

        Schema::create('activity_templates', function (Blueprint $table) {
            $table->bigIncrements('template_id');
            $table->string('template_name');
            $table->string('template_type')->nullable();
            $table->foreignId('file_id')->nullable()->constrained('files', 'file_id')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('student_organization_presidents', function (Blueprint $table) {
            $table->bigIncrements('president_record_id');
            $table->foreignId('organization_id')
                ->constrained('organization_profiles', 'organization_id')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('term_start')->nullable();
            $table->date('term_end')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'user_id']);
        });

        Schema::create('activity_participants', function (Blueprint $table) {
            $table->bigIncrements('activity_participant_id');
            $table->foreignId('application_id')
                ->nullable()
                ->constrained('volunteer_applications')
                ->cascadeOnDelete();
            $table->foreignId('activity_id')
                ->constrained('activity_proposals', 'activity_id')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('participant_type')->nullable();
            $table->dateTime('appointment_date')->nullable();
            $table->string('status')->default('active');
            $table->boolean('medical_clearance')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'user_id']);
        });

        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->bigIncrements('otp_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('otp_code');
            $table->string('purpose');
            $table->boolean('is_used')->default(false);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'purpose', 'expires_at']);
        });

        Schema::create('evaluation_items', function (Blueprint $table) {
            $table->bigIncrements('item_id');
            $table->string('category')->nullable();
            $table->string('item_name');
            $table->string('scale_type')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignId('activity_id')
                ->nullable()
                ->constrained('activity_proposals', 'activity_id')
                ->nullOnDelete();
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->foreignId('activity_id')
                ->nullable()
                ->constrained('activity_proposals', 'activity_id')
                ->nullOnDelete();
        });

        Schema::table('volunteer_applications', function (Blueprint $table) {
            $table->foreignId('activity_id')
                ->nullable()
                ->constrained('activity_proposals', 'activity_id')
                ->nullOnDelete();
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->foreignId('activity_id')
                ->nullable()
                ->constrained('activity_proposals', 'activity_id')
                ->nullOnDelete();
            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('evaluation_responses', function (Blueprint $table) {
            $table->foreignId('item_id')
                ->nullable()
                ->constrained('evaluation_items', 'item_id')
                ->nullOnDelete();
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('remarks')->nullable();
        });

        $this->backfillOrganizations();
        $this->backfillUserData();
        $this->backfillActivities();
        $this->backfillRelatedActivities();
        $this->backfillFilesAndTemplates();
        $this->backfillPresidents();
    }

    private function backfillOrganizations(): void
    {
        DB::table('users')
            ->whereNotNull('organization_name')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $organizationId = DB::table('organization_profiles')->insertGetId([
                        'user_id' => $user->id,
                        'organization_name' => $user->organization_name,
                        'acronym' => $user->org_acronym,
                        'type' => $user->role,
                        'org_classification' => $user->org_classification,
                        'year_founded' => $user->org_year_founded,
                        'logo_path' => $user->org_logo,
                        'description' => $user->org_description,
                        'vision' => $user->org_vision,
                        'mission' => $user->org_mission,
                        'goals' => $user->org_goals,
                        'advocacies' => $user->org_advocacies,
                        'contact_email' => $user->org_contact_email,
                        'status' => $user->is_active ? 'active' : 'inactive',
                        'profile_updated_at' => $user->org_profile_updated_at,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                    ], 'organization_id');

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['organization_id' => $organizationId]);
                }
            });
    }

    private function backfillUserData(): void
    {
        DB::table('users')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    DB::table('user_credentials')->insert([
                        'user_id' => $user->id,
                        'username' => $user->username,
                        'password' => $user->password,
                        'must_change_password' => $user->must_change_password,
                        'password_updated_at' => null,
                        'email_verified_at' => $user->email_verified_at,
                        'remember_token' => $user->remember_token,
                        'login_attempts' => 0,
                        'locked_until' => null,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                    ]);

                    if ($user->participant_type === 'student') {
                        DB::table('student_profiles')->insert([
                            'user_id' => $user->id,
                            'program' => $user->program,
                            'department' => $user->affiliation,
                            'created_at' => $user->created_at,
                            'updated_at' => $user->updated_at,
                        ]);
                    } elseif ($user->participant_type === 'employee') {
                        DB::table('employee_profiles')->insert([
                            'user_id' => $user->id,
                            'employment_type' => $user->employment_type,
                            'department' => $user->affiliation,
                            'created_at' => $user->created_at,
                            'updated_at' => $user->updated_at,
                        ]);
                    } elseif ($user->participant_type === 'alumni') {
                        DB::table('alumni_profiles')->insert([
                            'user_id' => $user->id,
                            'program_graduated' => $user->program,
                            'year_graduated' => $user->year_graduated,
                            'created_at' => $user->created_at,
                            'updated_at' => $user->updated_at,
                        ]);
                    }
                }
            });
    }

    private function backfillActivities(): void
    {
        DB::table('projects')
            ->orderBy('id')
            ->chunkById(200, function ($projects): void {
                foreach ($projects as $project) {
                    $organizationId = DB::table('users')
                        ->where('id', $project->created_by)
                        ->value('organization_id');

                    DB::table('activity_proposals')->insert([
                        'legacy_project_id' => $project->id,
                        'title' => $project->title,
                        'description' => $project->description,
                        'organization_id' => $organizationId,
                        'implementation_status' => $project->status,
                        'created_by' => $project->created_by,
                        'submitted_at' => $project->created_at,
                        'status' => $project->status,
                        'created_at' => $project->created_at,
                        'updated_at' => $project->updated_at,
                    ]);
                }
            });
    }

    private function backfillRelatedActivities(): void
    {
        DB::table('announcements')
            ->whereNotNull('project_id')
            ->orderBy('id')
            ->chunkById(200, function ($announcements): void {
                foreach ($announcements as $announcement) {
                    $activityId = DB::table('activity_proposals')
                        ->where('legacy_project_id', $announcement->project_id)
                        ->value('activity_id');

                    if ($activityId !== null) {
                        DB::table('announcements')
                            ->where('id', $announcement->id)
                            ->update(['activity_id' => $activityId]);
                    }
                }
            });

        DB::table('attendance_sessions')
            ->orderBy('id')
            ->chunkById(200, function ($sessions): void {
                foreach ($sessions as $session) {
                    $activityId = DB::table('activity_proposals')
                        ->where('legacy_project_id', $session->project_id)
                        ->value('activity_id');

                    if ($activityId !== null) {
                        DB::table('attendance_sessions')
                            ->where('id', $session->id)
                            ->update(['activity_id' => $activityId]);
                    }
                }
            });

        DB::table('volunteer_applications')
            ->orderBy('id')
            ->chunkById(200, function ($applications): void {
                $announcement = DB::table('announcements')
                    ->where('id', $applications->first()->announcement_id)
                    ->first();

                foreach ($applications as $application) {
                    if ($announcement === null || $announcement->id !== $application->announcement_id) {
                        $announcement = DB::table('announcements')
                            ->where('id', $application->announcement_id)
                            ->first();
                    }

                    $activityId = $announcement->activity_id ?? null;
                    if ($activityId === null) {
                        continue;
                    }

                    DB::table('volunteer_applications')
                        ->where('id', $application->id)
                        ->update(['activity_id' => $activityId]);

                    DB::table('activity_participants')->updateOrInsert(
                        [
                            'activity_id' => $activityId,
                            'user_id' => $application->user_id,
                        ],
                        [
                            'application_id' => $application->id,
                            'status' => $application->status,
                            'created_at' => $application->created_at,
                            'updated_at' => $application->updated_at,
                        ]
                    );
                }
            });

        DB::table('evaluations')
            ->orderBy('id')
            ->chunkById(200, function ($evaluations): void {
                foreach ($evaluations as $evaluation) {
                    $announcement = DB::table('announcements')
                        ->where('id', $evaluation->announcement_id)
                        ->first();

                    if ($announcement !== null && $announcement->activity_id !== null) {
                        DB::table('evaluations')
                            ->where('id', $evaluation->id)
                            ->update([
                                'activity_id' => $announcement->activity_id,
                                'encoded_by' => $evaluation->submitted_by,
                            ]);
                    }
                }
            });
    }

    private function backfillFilesAndTemplates(): void
    {
        DB::table('project_attachments')
            ->orderBy('id')
            ->chunkById(200, function ($attachments): void {
                foreach ($attachments as $attachment) {
                    $fileId = DB::table('files')->insertGetId([
                        'file_path' => $attachment->file_path,
                        'mime_type' => $attachment->mime_type,
                        'size' => $attachment->file_size,
                        'uploaded_by' => null,
                        'uploaded_at' => $attachment->created_at,
                        'created_at' => $attachment->created_at,
                        'updated_at' => $attachment->updated_at,
                    ], 'file_id');

                    $activityId = DB::table('activity_proposals')
                        ->where('legacy_project_id', $attachment->project_id)
                        ->value('activity_id');

                    if ($activityId) {
                        DB::table('activity_files')->insert([
                            'activity_id' => $activityId,
                            'file_id' => $fileId,
                            'file_category' => 'proposal_attachment',
                            'uploaded_at' => $attachment->created_at,
                            'created_at' => $attachment->created_at,
                            'updated_at' => $attachment->updated_at,
                        ]);
                    }
                }
            });

        DB::table('templates')
            ->orderBy('id')
            ->chunkById(200, function ($templates): void {
                foreach ($templates as $template) {
                    $fileId = DB::table('files')->insertGetId([
                        'file_path' => $template->file_path,
                        'mime_type' => null,
                        'size' => 0,
                        'uploaded_by' => $template->uploaded_by,
                        'uploaded_at' => $template->created_at,
                        'created_at' => $template->created_at,
                        'updated_at' => $template->updated_at,
                    ], 'file_id');

                    DB::table('activity_templates')->insert([
                        'template_name' => $template->original_name,
                        'template_type' => null,
                        'file_id' => $fileId,
                        'uploaded_by' => $template->uploaded_by,
                        'created_at' => $template->created_at,
                        'updated_at' => $template->updated_at,
                    ]);
                }
            });
    }

    private function backfillPresidents(): void
    {
        DB::table('org_officers')
            ->whereRaw('LOWER(position) = ?', ['president'])
            ->orderBy('id')
            ->chunkById(200, function ($officers): void {
                foreach ($officers as $officer) {
                    $organizationId = DB::table('organization_profiles')
                        ->where('user_id', $officer->user_id)
                        ->value('organization_id');

                    if ($organizationId !== null) {
                        DB::table('student_organization_presidents')->insert([
                            'organization_id' => $organizationId,
                            'user_id' => $officer->user_id,
                            'term_start' => null,
                            'term_end' => null,
                            'created_at' => $officer->created_at,
                            'updated_at' => $officer->updated_at,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('evaluation_responses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_id');
            $table->dropColumn(['score', 'remarks']);
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_id');
            $table->dropConstrainedForeignId('encoded_by');
        });

        Schema::table('volunteer_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_id');
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_id');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_id');
        });

        Schema::dropIfExists('otp_verifications');
        Schema::dropIfExists('activity_participants');
        Schema::dropIfExists('student_organization_presidents');
        Schema::dropIfExists('activity_templates');
        Schema::dropIfExists('activity_files');
        Schema::dropIfExists('activity_approval_workflows');
        Schema::dropIfExists('activity_proposals');
        Schema::dropIfExists('files');
        Schema::dropIfExists('evaluation_items');
        Schema::dropIfExists('alumni_profiles');
        Schema::dropIfExists('employee_profiles');
        Schema::dropIfExists('student_profiles');
        Schema::dropIfExists('login_histories');
        Schema::dropIfExists('user_credentials');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['contact_number', 'avatar_url']);
        });

        Schema::dropIfExists('organization_profiles');
    }
};
