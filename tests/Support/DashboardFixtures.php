<?php

namespace Tests\Support;

use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use App\Models\VolunteerApplication;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait DashboardFixtures
{
    protected function createDashboardSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('role');
            $table->string('organization_name')->nullable();
            $table->string('participant_type')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('created_by');
            $table->string('status');
            $table->unsignedInteger('volunteers_count')->default(0);
            $table->unsignedInteger('hours_rendered')->default(0);
            $table->text('review_comments')->nullable();
            $table->timestamps();
        });
        Schema::create('project_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id');
        });
        Schema::create('templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('uploaded_by');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('source')->nullable();
            $table->timestamps();
        });
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->nullable();
            $table->foreignId('created_by');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('General');
            $table->string('status');
            $table->unsignedInteger('slots_needed')->nullable();
            $table->timestamp('application_deadline')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('event_date')->nullable();
            $table->string('event_location')->nullable();
            $table->timestamps();
        });
        Schema::create('volunteer_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id');
            $table->foreignId('user_id');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('attendance_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id');
            $table->string('qr_token');
            $table->unsignedInteger('radius_meters')->default(50);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->string('status')->default('closed');
            $table->timestamps();
        });
        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id');
            $table->foreignId('user_id');
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->string('attendance_source')->default('qr');
            $table->string('check_in_status')->nullable();
            $table->string('check_out_status')->nullable();
            $table->string('status')->default('valid');
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable();
            $table->string('actor_name');
            $table->string('actor_role')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->text('summary');
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    protected function seedDashboardFixtures(): array
    {
        Carbon::setTestNow(Carbon::parse('2026-11-27 12:00:00', config('app.timezone')));
        $users = [];
        foreach (['celp_secretary', 'coordinator', 'celp_head', 'csa_director', 'student_org', 'college_department', 'participant'] as $role) {
            $users[$role] = User::factory()->create([
                'role' => $role, 'name' => 'Test '.Str::headline($role),
                'organization_name' => in_array($role, ['student_org', 'college_department']) ? 'Test '.Str::headline($role).' Office' : null,
            ]);
        }
        $other = User::factory()->create(['role' => 'student_org', 'name' => 'Other Organization', 'organization_name' => 'Other Organization']);
        foreach ([$users['student_org'], $users['college_department'], $other] as $ownerIndex => $owner) {
            foreach (array_keys(Project::STATUS_LABELS) as $index => $status) {
                if ($ownerIndex === 1 && $index % 2 === 0) {
                    continue;
                }
                $project = Project::create([
                    'title' => $owner->name.' proposal '.($index + 1), 'created_by' => $owner->id,
                    'status' => $status, 'volunteers_count' => ($index + 1) * ($ownerIndex + 1),
                ]);
                $project->forceFill(['created_at' => now()->startOfMonth()->subMonths($index % 6)->addDays(7), 'updated_at' => now()->subDays($index)])->saveQuietly();
            }
        }
        foreach (['celp_secretary' => 'endorsed_by_secretary', 'coordinator' => 'endorsed_by_coordinator', 'csa_director' => 'csa_cleared'] as $role => $advance) {
            $returned = ['celp_secretary' => 'returned_by_secretary', 'coordinator' => 'returned_by_coordinator', 'csa_director' => 'returned_by_csa'][$role];
            for ($offset = 0; $offset < 6; $offset++) {
                for ($number = 0; $number <= $offset; $number++) {
                    $event = AuditLog::create([
                        'actor_id' => $users[$role]->id, 'actor_name' => $users[$role]->name, 'actor_role' => $role,
                        'action' => $number % 3 === 2 ? 'project.returned' : 'project.advanced', 'summary' => 'Test review event',
                        'context' => ['to_status' => $number % 3 === 2 ? $returned : $advance],
                    ]);
                    $event->forceFill(['created_at' => now()->startOfMonth()->subMonths($offset)->addDays(9)])->saveQuietly();
                }
            }
        }
        for ($index = 0; $index < 5; $index++) {
            $project = Project::create(['title' => 'Attendance test activity '.($index + 1), 'created_by' => $other->id, 'status' => 'csa_cleared']);
            $announcement = Announcement::create([
                'project_id' => $project->id, 'created_by' => $other->id, 'title' => $project->title,
                'status' => 'published', 'category' => 'Volunteer recruitment', 'slots_needed' => 10,
                'event_date' => $index === 4 ? now()->addDays(3) : now()->startOfMonth()->subMonths($index % 3)->addDays(4),
                'published_at' => now()->subDays(5),
            ]);
            VolunteerApplication::create(['announcement_id' => $announcement->id, 'user_id' => $users['participant']->id, 'status' => $index === 3 ? 'pending' : 'approved']);
            if ($index < 4) {
                $session = AttendanceSession::create(['project_id' => $project->id, 'qr_token' => Str::random(32), 'opens_at' => $announcement->event_date]);
                AttendanceRecord::create(['session_id' => $session->id, 'user_id' => $users['participant']->id, 'check_in_at' => $announcement->event_date, 'status' => 'completed']);
            }
        }

        return $users;
    }
}
