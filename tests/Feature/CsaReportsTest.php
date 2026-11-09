<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CsaReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('role')->default('participant');
            $table->string('organization_name')->nullable();
            $table->string('id_number')->nullable();
            $table->string('participant_type')->nullable();
            $table->string('affiliation')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('created_by');
            $table->string('status')->default('draft');
            $table->integer('volunteers_count')->default(0);
            $table->integer('hours_rendered')->default(0);
            $table->text('review_comments')->nullable();
            $table->timestamps();
        });
        Schema::create('project_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->foreignId('created_by');
            $table->string('title');
            $table->string('status')->default('draft');
            $table->dateTime('event_date')->nullable();
            $table->string('event_location')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
        });
        Schema::create('volunteer_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id');
            $table->foreignId('user_id');
            $table->string('status')->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
        Schema::create('project_participant_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->foreignId('user_id')->nullable();
            $table->foreignId('added_by')->nullable();
            $table->string('participation_type');
            $table->string('participant_name')->nullable();
            $table->string('participant_id_number', 50)->nullable();
            $table->string('roster_code', 30)->nullable();
            $table->foreignId('attendance_session_id')->nullable();
            $table->string('attendance_check_in', 20)->nullable();
            $table->string('attendance_check_out', 20)->nullable();
            $table->string('attendance_status', 20)->nullable();
            $table->timestamp('attendance_recorded_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->timestamps();
        });
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id');
            $table->foreignId('user_id');
            $table->dateTime('scanned_at')->nullable();
            $table->string('status')->default('valid');
            $table->string('attendance_source')->nullable();
            $table->string('check_in_status')->nullable();
            $table->string('check_out_status')->nullable();
            $table->timestamps();
        });
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id');
            $table->unsignedInteger('expected_beneficiaries')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('evaluation_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id');
            $table->string('respondent_type')->default('beneficiary');
            $table->string('source')->default('paper');
            $table->json('heard_about_sources')->nullable();
            $table->string('volunteer_interest', 10)->nullable();
            $table->text('suggestions')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });
        Schema::create('evaluation_response_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id');
            $table->foreignId('criterion_id')->nullable();
            $table->unsignedTinyInteger('score');
            $table->timestamps();
        });
        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('short_label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('respondent_type')->default('beneficiary');
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_csa_reports_are_role_restricted_and_limited_to_student_organizations(): void
    {
        $director = User::factory()->create(['role' => 'csa_director']);
        User::factory()->create([
            'role' => 'student_org',
            'organization_name' => 'Student Organization',
        ]);
        User::factory()->create([
            'role' => 'college_department',
            'organization_name' => 'College Department',
        ]);

        $response = $this->actingAs($director)->get(route('csa.reports.index'));

        $response->assertOk()
            ->assertSee('Student Organization')
            ->assertDontSee('College Department')
            ->assertSee('Accomplishment Report')
            ->assertSee('Evaluation Summary Report');

        $celpHead = User::factory()->create(['role' => 'celp_head']);

        $this->actingAs($celpHead)
            ->get(route('csa.reports.index'))
            ->assertForbidden();

        $college = User::factory()->create(['role' => 'college_department']);

        $this->actingAs($director)
            ->get(route('csa.reports.show', [
                'report' => 'compliance',
                'organization' => $college->id,
            ]))
            ->assertNotFound();

        $this->actingAs($director)
            ->get(route('csa.reports.show', ['report' => 'compliance']))
            ->assertOk()
            ->assertSee('No records match the selected organization and reporting period.');

        $studentOrg = User::query()->where('role', 'student_org')->firstOrFail();
        $projectId = \Illuminate\Support\Facades\DB::table('projects')->insertGetId([
            'title' => 'Student Org Project',
            'created_by' => $studentOrg->id,
            'status' => 'csa_cleared',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('projects')->insert([
            'title' => 'College Project Must Stay Hidden',
            'created_by' => $college->id,
            'status' => 'csa_cleared',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $announcementId = \Illuminate\Support\Facades\DB::table('announcements')->insertGetId([
            'project_id' => $projectId,
            'created_by' => $studentOrg->id,
            'title' => 'Student Org Activity',
            'status' => 'published',
            'event_date' => now()->subDay(),
            'event_location' => 'Campus',
            'published_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        $volunteer = User::factory()->create([
            'name' => 'Volunteer Person',
            'role' => 'participant',
            'participant_type' => 'student',
            'id_number' => 'S-100',
        ]);
        \Illuminate\Support\Facades\DB::table('volunteer_applications')->insert([
            'announcement_id' => $announcementId,
            'user_id' => $volunteer->id,
            'status' => 'approved',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        $assignedParticipant = User::factory()->create([
            'name' => 'Assigned Officer',
            'role' => 'participant',
            'participant_type' => 'student',
            'id_number' => 'S-200',
        ]);
        \Illuminate\Support\Facades\DB::table('project_participant_enrollments')->insert([
            'project_id' => $projectId,
            'user_id' => null,
            'added_by' => $studentOrg->id,
            'participation_type' => 'participation_only',
            'participant_name' => $assignedParticipant->name,
            'participant_id_number' => null,
            'roster_code' => 'GUEST-TEST-CODE',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        $sessionId = \Illuminate\Support\Facades\DB::table('attendance_sessions')->insertGetId([
            'project_id' => $projectId,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        \Illuminate\Support\Facades\DB::table('project_participant_enrollments')
            ->where('roster_code', 'GUEST-TEST-CODE')
            ->update([
                'attendance_session_id' => $sessionId,
                'attendance_check_in' => 'Present',
                'attendance_check_out' => 'Present',
                'attendance_status' => 'completed',
                'attendance_recorded_at' => now()->subDay(),
            ]);
        \Illuminate\Support\Facades\DB::table('attendance_records')->insert([
            'session_id' => $sessionId,
            'user_id' => $volunteer->id,
            'scanned_at' => now()->subDay(),
            'status' => 'valid',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        $evaluationId = \Illuminate\Support\Facades\DB::table('evaluations')->insertGetId([
            'announcement_id' => $announcementId,
            'expected_beneficiaries' => 5,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $responseId = \Illuminate\Support\Facades\DB::table('evaluation_responses')->insertGetId([
            'evaluation_id' => $evaluationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('evaluation_response_ratings')->insert([
            'response_id' => $responseId,
            'score' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($director)
            ->get(route('csa.reports.show', ['report' => 'compliance']))
            ->assertOk()
            ->assertSee('Student Org Project')
            ->assertDontSee('College Project Must Stay Hidden');

        foreach (['accomplishment', 'volunteer-roster', 'participation', 'attendance', 'activity-status', 'evaluation-summary'] as $report) {
            $response = $this->actingAs($director)
                ->get(route('csa.reports.show', ['report' => $report]))
                ->assertOk();
            $expectedTitle = $report === 'attendance' ? 'Student Org Project' : 'Student Org Activity';
            $this->assertStringContainsString($expectedTitle, $response->getContent(), 'Missing student record in report: ' . $report);
            $this->assertStringNotContainsString('College Project Must Stay Hidden', $response->getContent(), $report . ' report must exclude college projects.');
        }

        $attendanceCsv = $this->actingAs($director)
            ->get(route('csa.reports.show', ['report' => 'attendance', 'format' => 'csv']));
        $attendanceCsv->assertOk();
        $this->assertStringContainsString('Assigned Officer', $attendanceCsv->streamedContent());

        $this->actingAs($celpHead)
            ->get(route('reports.attendance'))
            ->assertOk()
            ->assertSee('Assigned Officer')
            ->assertSee('GUEST-TEST-CODE');

        $this->actingAs($director)
            ->get(route('csa.reports.show', ['report' => 'participation']))
            ->assertSee('Students')
            ->assertSee('2');

        $participationCsv = $this->actingAs($director)
            ->get(route('csa.reports.show', ['report' => 'participation', 'format' => 'csv']));
        $participationCsv->assertOk();
        $this->assertStringContainsString('2,1,0,0,1', $participationCsv->streamedContent());

        $celpHead = User::factory()->create(['role' => 'celp_head']);
        $this->actingAs($celpHead)
            ->get(route('reports.volunteers'))
            ->assertOk()
            ->assertSee('Assigned Officer')
            ->assertSee('External participant — participation only')
            ->assertSee('GUEST-TEST-CODE');

        $this->actingAs($director)
            ->get(route('csa.reports.show', ['report' => 'evaluation-summary']))
            ->assertSee('Expected beneficiaries')
            ->assertSee('4');

        $this->actingAs($director)
            ->get(route('csa.reports.show', ['report' => 'compliance', 'format' => 'csv']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_celp_head_can_view_activity_evaluation_ratings_and_feedback(): void
    {
        $head = User::factory()->create(['role' => 'celp_head']);
        $initiator = User::factory()->create([
            'role' => 'college_department',
            'organization_name' => 'Community Extension Office',
        ]);
        $projectId = \Illuminate\Support\Facades\DB::table('projects')->insertGetId([
            'title' => 'Community Health Project',
            'created_by' => $initiator->id,
            'status' => 'csa_cleared',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $announcementId = \Illuminate\Support\Facades\DB::table('announcements')->insertGetId([
            'project_id' => $projectId,
            'created_by' => $initiator->id,
            'title' => 'Community Health Activity',
            'status' => 'published',
            'event_date' => now(),
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $evaluationId = \Illuminate\Support\Facades\DB::table('evaluations')->insertGetId([
            'announcement_id' => $announcementId,
            'expected_beneficiaries' => 10,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $criterionId = \Illuminate\Support\Facades\DB::table('evaluation_criteria')->insertGetId([
            'label' => 'Overall benefit',
            'short_label' => 'Overall benefit gained',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $responseId = \Illuminate\Support\Facades\DB::table('evaluation_responses')->insertGetId([
            'evaluation_id' => $evaluationId,
            'source' => 'paper',
            'suggestions' => 'More hands-on sessions',
            'observations' => 'Participants were engaged',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('evaluation_response_ratings')->insert([
            'response_id' => $responseId,
            'criterion_id' => $criterionId,
            'score' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($head)
            ->get(route('reports.evaluations'))
            ->assertOk()
            ->assertSee('Participant Feedback Report')
            ->assertSee('Community Health Activity')
            ->assertSee('More hands-on sessions')
            ->assertSee('4.00 / 5')
            ->assertSee('Overall benefit gained');
    }
}
