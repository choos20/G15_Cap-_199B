<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceSessionCreationTest extends TestCase
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
            $table->boolean('must_change_password')->default(false);
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('created_by');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('qr_token')->unique();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('radius_meters')->default(50);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('attendance_sessions');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_initiators_cannot_schedule_past_sessions_but_can_schedule_today_or_future(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 14:00:00'));
        $organizer = User::factory()->create([
            'role' => 'student_org',
            'must_change_password' => false,
        ]);
        $yesterdayProject = $this->createApprovedProject($organizer);
        $earlierTodayProject = $this->createApprovedProject($organizer);
        $todayProject = $this->createApprovedProject($organizer);
        $futureProject = $this->createApprovedProject($organizer);

        $this->actingAs($organizer)
            ->from(route('student_org.attendance'))
            ->post(route('student_org.attendance.store'), $this->sessionData($yesterdayProject, '2026-10-27T14:00', '2026-10-27T15:00'))
            ->assertRedirect(route('student_org.attendance'))
            ->assertSessionHasErrors('opens_at');

        $this->actingAs($organizer)
            ->from(route('student_org.attendance'))
            ->post(route('student_org.attendance.store'), $this->sessionData($earlierTodayProject, '2026-10-28T13:59', '2026-10-28T15:00'))
            ->assertRedirect(route('student_org.attendance'))
            ->assertSessionHasErrors('opens_at');

        $this->assertDatabaseCount('attendance_sessions', 0);

        $this->actingAs($organizer)
            ->post(route('student_org.attendance.store'), $this->sessionData($todayProject, '2026-10-28T14:00', '2026-10-28T15:00'))
            ->assertRedirect(route('student_org.attendance'))
            ->assertSessionHasNoErrors();

        $this->actingAs($organizer)
            ->post(route('student_org.attendance.store'), $this->sessionData($futureProject, '2027-03-01T10:00', '2027-03-01T11:00'))
            ->assertRedirect(route('student_org.attendance'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, AttendanceSession::count());
    }

    private function createApprovedProject(User $organizer): Project
    {
        return Project::create([
            'title' => 'Community Activity',
            'created_by' => $organizer->id,
            'status' => 'approved',
        ]);
    }

    private function sessionData(Project $project, string $opensAt, string $closesAt): array
    {
        return [
            'project_id' => $project->id,
            'latitude' => '14.5995',
            'longitude' => '120.9842',
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
        ];
    }
}
