<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationAttendanceReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-23 10:00:00'));
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('role')->default('participant');
            foreach (['id_number', 'participant_type', 'program', 'affiliation', 'org_college', 'organization_name', 'org_description', 'first_name'] as $field) $table->string($field)->nullable();
            $table->string('employment_type')->nullable();
            $table->unsignedSmallInteger('year_graduated')->nullable();
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('created_by');
            $table->string('status')->default('approved');
            $table->integer('hours_rendered')->default(0);
            $table->timestamps();
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->string('title')->nullable();
            $table->date('event_date')->nullable();
        });
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id');
            $table->timestamp('submitted_at')->nullable();
        });
        Schema::create('volunteer_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id');
            $table->foreignId('user_id');
            $table->string('status');
        });
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
        });
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id');
            $table->foreignId('user_id');
            $table->string('status');
            $table->string('attendance_source')->nullable();
            $table->string('check_in_status')->nullable();
            $table->string('check_out_status')->nullable();
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->dateTime('scanned_at')->nullable();
            $table->timestamps();
        });
        Schema::create('project_participant_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->foreignId('user_id')->nullable();
            foreach (['participant_name', 'participant_id_number', 'roster_code', 'attendance_status', 'attendance_check_in', 'attendance_check_out'] as $field) $table->string($field)->nullable();
            $table->timestamp('attendance_recorded_at')->nullable();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_11_23_000001_create_academic_catalog_tables.php'))->up();
        $department = DB::table('academic_departments')->insertGetId(['code' => 'COECS', 'name' => 'College of Engineering and Computer Studies']);
        DB::table('academic_programs')->insert([
            ['department_id' => $department, 'code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology'],
            ['department_id' => $department, 'code' => 'BSCE', 'name' => 'Bachelor of Science in Civil Engineering'],
        ]);
    }

    private function project(User $owner, string $title = 'HRE Rollout'): int
    {
        return DB::table('projects')->insertGetId(['title' => $title, 'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function attendance(int $project, User $participant, string $status = 'completed'): void
    {
        $session = DB::table('attendance_sessions')->insertGetId(['project_id' => $project]);
        DB::table('attendance_records')->insert([
            'session_id' => $session, 'user_id' => $participant->id, 'status' => $status,
            'check_in_at' => now()->subHour(), 'check_out_at' => $status === 'completed' ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function participant(string $name, ?string $program = 'BSIT', ?string $type = 'student'): User
    {
        return User::factory()->create(['name' => $name, 'role' => 'participant', 'participant_type' => $type, 'program' => $program, 'affiliation' => $program ? 'COECS' : null]);
    }

    private function url(array $filters = []): string
    {
        return route('student_org.reports.show', ['report' => 'attendance'] + $filters);
    }

    public function test_filters_totals_csv_and_print_use_the_same_scoped_roster(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $project = $this->project($owner);
        $student = $this->participant('IT Student');
        $student->update(['id_number' => '000123']);
        $this->attendance($project, $student);
        $this->attendance($project, $student);
        $this->attendance($project, $this->participant('CE Student', 'BSCE'));
        $this->attendance($project, $this->participant('IT Alumni', 'BSIT', 'alumni'));
        $this->attendance($project, $this->participant('Absent Student'), 'absent');
        $other = User::factory()->create(['role' => 'student_org']);
        $this->attendance($this->project($other, 'Private activity'), $this->participant('Private participant', 'PRIVATE'));
        $filters = ['project' => $project, 'department' => ' coecs ', 'program' => 'bachelor of science in information technology', 'participant_type' => 'student'];
        $response = $this->actingAs($owner)->get($this->url($filters));
        $response->assertOk()->assertSee('IT Student')->assertDontSee('CE Student')->assertDontSee('IT Alumni')->assertDontSee('Private participant')->assertDontSee('PRIVATE')
            ->assertSee('College / department: COECS')->assertSee('Program: BSIT')->assertSee('Participant type: Student');
        $this->assertMatchesRegularExpression('/<select[^>]*name="participant_type"[^>]*>(.*?)<\/select>/s', $response->getContent());
        preg_match('/<select[^>]*name="participant_type"[^>]*>(.*?)<\/select>/s', $response->getContent(), $select);
        $this->assertStringNotContainsString('external_participant', $select[1]);
        $this->assertStringNotContainsString('_unspecified', $select[1]);
        foreach (['student', 'employee', 'alumni'] as $type) $this->assertStringContainsString('value="'.$type.'"', $select[1]);
        $this->assertSame(['IT Student'], $response->viewData('attendanceRows')->pluck('name')->all());
        $this->assertSame(['Projects' => 1, 'Attendance records' => 2, 'Recorded as attended' => 1, 'Participants listed' => 1], $response->viewData('summary'));
        $csv = $this->get($this->url($filters + ['format' => 'csv']))->assertOk()->streamedContent();
        $lines = array_map('str_getcsv', explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF"))));
        $this->assertCount(2, $lines);
        $this->assertSame('Program', $lines[0][4]);
        $this->assertSame('College / department', $lines[0][5]);
        $this->assertSame("'000123", $lines[1][1]);
        $this->assertSame(['BSIT', 'COECS'], array_slice($lines[1], 4, 2));
    }

    public function test_missing_details_and_external_people_remain_accessible(): void
    {
        $owner = User::factory()->create(['role' => 'college_department']);
        $project = $this->project($owner);
        $this->attendance($project, $this->participant('Incomplete account', null, null));
        $this->attendance($project, $this->participant('Partial student'), 'partial');
        DB::table('project_participant_enrollments')->insert([
            'project_id' => $project, 'participant_name' => 'External attendee', 'roster_code' => 'EXT-001',
            'attendance_status' => 'completed', 'attendance_recorded_at' => now(),
        ]);
        $response = $this->actingAs($owner)->get($this->url(['department' => '_unspecified', 'program' => '_unspecified']));
        $response->assertOk()->assertSee('Incomplete account')->assertSee('External attendee')->assertDontSee('Partial student');
        $this->assertSame(2, $response->viewData('summary')['Participants listed']);
        $this->get($this->url(['participant_type' => '_unspecified']))->assertOk()->assertSee('Incomplete account')->assertDontSee('External attendee');
        $this->get($this->url(['participant_type' => 'external_participant']))->assertOk()->assertSee('External attendee')->assertDontSee('Incomplete account');
        $partial = $this->get($this->url(['program' => 'BSIT']));
        $partial->assertOk()->assertSee('Partial student');
        $this->assertSame(0, $partial->viewData('summary')['Recorded as attended']);
    }

    public function test_academic_profile_completion_immediately_updates_existing_attendance(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $participant = $this->participant('Previously incomplete', null, null);
        $participant->update(['id_number' => '000456']);
        $this->attendance($this->project($owner), $participant);
        $editor = User::factory()->create(['role' => 'coordinator']);
        $this->actingAs($editor)->post(route('users.update', $participant), [
            'id_number' => '000456', 'participant_type' => 'student', 'affiliation' => 'COECS', 'program' => 'BSIT',
        ])->assertSessionHasNoErrors();
        $response = $this->actingAs($owner)->get($this->url(['department' => 'COECS', 'program' => 'BSIT', 'participant_type' => 'student']));
        $response->assertOk()->assertSee('Previously incomplete');
        $row = $response->viewData('attendanceRows')->sole();
        $this->assertSame('Student', $row['participant_type']);
        $this->assertSame('COECS', $row['department']);
        $this->assertSame('BSIT', $row['program']);
    }

    public function test_volunteer_role_is_scoped_to_each_activity(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $project = $this->project($owner, 'Volunteer activity');
        $otherProject = $this->project($owner, 'Assigned activity');
        $participant = $this->participant('Student');
        $announcement = DB::table('announcements')->insertGetId(['project_id' => $project]);
        DB::table('volunteer_applications')->insert(['announcement_id' => $announcement, 'user_id' => $participant->id, 'status' => 'approved']);
        $this->attendance($project, $participant);
        $this->attendance($otherProject, $participant);
        $rows = $this->actingAs($owner)->get($this->url())->assertOk()->viewData('attendanceRows')->keyBy('activity');
        $this->assertSame('Approved volunteer', $rows['Volunteer activity']['participation_role']);
        $this->assertSame('Participant', $rows['Assigned activity']['participation_role']);
    }

    public function test_involvement_report_counts_approved_applications_without_assignments(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $project = $this->project($owner);
        $announcement = DB::table('announcements')->insertGetId(['project_id' => $project]);
        $approved = $this->participant('Approved applicant');
        $pending = $this->participant('Pending applicant');
        DB::table('volunteer_applications')->insert([
            ['announcement_id' => $announcement, 'user_id' => $approved->id, 'status' => 'approved'],
            ['announcement_id' => $announcement, 'user_id' => $pending->id, 'status' => 'pending'],
        ]);
        DB::table('project_participant_enrollments')->insert(['project_id' => $project, 'participant_name' => 'Historical participant']);
        $url = route('student_org.reports.show', ['report' => 'participant-involvement']);
        $response = $this->actingAs($owner)->get($url)->assertOk()->assertDontSee('Assigned participants');
        $this->assertSame(['Project', 'Approved volunteers'], $response->viewData('headings'));
        $this->assertSame(['HRE Rollout', 1], $response->viewData('rows')->first());
        $csv = $this->get($url.'?format=csv')->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Assigned participants', $csv);
        $this->assertDatabaseHas('project_participant_enrollments', ['participant_name' => 'Historical participant']);
    }

    public function test_activity_completion_filters_summary_print_and_csv_remain_scoped(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $other = User::factory()->create(['role' => 'student_org']);
        $project = $this->project($owner, 'Matching project');
        DB::table('projects')->where('id', $project)->update(['status' => 'csa_cleared']);
        $a = DB::table('announcements')->insertGetId(['project_id' => $project, 'title' => 'Completed activity', 'event_date' => '2026-11-01']);
        DB::table('evaluations')->insert(['announcement_id' => $a, 'submitted_at' => now()]);
        DB::table('announcements')->insert(['project_id' => $project, 'title' => 'Unsubmitted activity', 'event_date' => '2026-12-01']);
        $otherProject = $this->project($other, 'Private project');
        DB::table('projects')->where('id', $otherProject)->update(['status' => 'csa_cleared']);
        $private = DB::table('announcements')->insertGetId(['project_id' => $otherProject, 'title' => 'Private activity']);
        DB::table('evaluations')->insert(['announcement_id' => $private, 'submitted_at' => now()]);

        $params = ['report' => 'activity-completion', 'ay' => '2026-2027', 'approval_status' => 'csa_cleared', 'evaluation_status' => 'submitted'];
        $response = $this->actingAs($owner)->get(route('student_org.reports.show', $params))->assertOk()
            ->assertSee('Completed activity')->assertDontSee('Unsubmitted activity')->assertDontSee('Private project')
            ->assertDontSee('Hours rendered')->assertSee('Approval status: CSA Cleared')->assertSee('Evaluation status: Submitted');
        $this->assertSame(['Projects' => 1, 'Activities completed' => 1], $response->viewData('summary'));
        $this->assertCount(1, $response->viewData('rows'));
        $csv = $this->get(route('student_org.reports.show', $params + ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Completed activity', $csv);
        $this->assertStringNotContainsString('Unsubmitted activity', $csv);
        $this->assertStringNotContainsString('Private project', $csv);
        $this->assertStringNotContainsString('Hours rendered', $csv);
        $this->get(route('student_org.reports.show', array_merge($params, ['evaluation_status' => 'not_submitted'])))
            ->assertOk()->assertSee('Unsubmitted activity')->assertDontSee('Completed activity');
        $this->get(route('student_org.reports.show', array_merge($params, ['approval_status' => 'submitted'])))
            ->assertOk()->assertDontSee('Completed activity');
        $this->get(route('student_org.reports.show', ['report' => 'activity-completion']))
            ->assertOk()->assertSee('Completed activity')->assertSee('Unsubmitted activity');
    }

    public function test_empty_results_and_ownership_and_role_limits(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $other = User::factory()->create(['role' => 'student_org']);
        $project = $this->project($other);
        $this->attendance($project, $this->participant('Private participant'));
        $response = $this->actingAs($owner)->get($this->url(['project' => $project, 'program' => 'BSIT']));
        $response->assertOk()->assertDontSee('Private participant')->assertSee('No participants with recorded attendance match the selected filters.');
        $this->assertSame(['Projects' => 0, 'Attendance records' => 0, 'Recorded as attended' => 0, 'Participants listed' => 0], $response->viewData('summary'));
        $this->get($this->url(['participant_type' => 'invalid']))->assertSessionHasErrors('participant_type');
        foreach (['participant', 'coordinator', 'celp_head', 'csa_director'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get($this->url())->assertForbidden();
        }
    }
}
