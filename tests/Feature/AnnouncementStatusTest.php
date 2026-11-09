<?php

namespace Tests\Feature;

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ParticipantController;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AnnouncementStatusTest extends TestCase
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
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by');
            $table->string('title')->default('Approved project');
            $table->string('status')->default('approved');
            $table->timestamps();
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable();
            $table->foreignId('created_by');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('General');
            $table->string('status')->default('draft');
            $table->unsignedInteger('slots_needed')->nullable();
            $table->dateTime('application_deadline')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('event_date')->nullable();
            $table->string('event_location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
        Schema::create('announcement_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id');
            $table->string('original_name')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });
        Schema::create('volunteer_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id');
            $table->foreignId('user_id');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_create_form_draft_intent_is_saved_as_draft(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $projectId = $this->createProject($owner);
        $this->mockAnnouncementRedirect();
        $deadline = now('Asia/Manila')->addDay()->setTime(18, 30);
        $request = Request::create('/student-org/announcements', 'POST', [
            'title' => 'Upcoming activity',
            'description' => 'Activity information',
            'category' => 'Volunteer recruitment',
            'project_id' => $projectId,
            'slots_needed' => 5,
            'status' => 'draft',
        ]);
        $request->setUserResolver(fn () => $owner);

        app(AnnouncementController::class)->store($request);

        $this->assertDatabaseHas('announcements', [
            'title' => 'Upcoming activity',
            'created_by' => $owner->id,
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function test_application_deadline_saves_the_selected_manila_time(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $projectId = $this->createProject($owner);
        $this->mockAnnouncementRedirect();
        $request = Request::create('/student-org/announcements', 'POST', [
            'title' => 'Volunteer recruitment',
            'category' => 'Volunteer recruitment',
            'project_id' => $projectId,
            'slots_needed' => 5,
            'application_deadline' => $deadline->format('Y-m-d\TH:i'),
            'status' => 'draft',
        ]);
        $request->setUserResolver(fn () => $owner);

        app(AnnouncementController::class)->store($request);

        $this->assertDatabaseHas('announcements', [
            'title' => 'Volunteer recruitment',
            'application_deadline' => $deadline->copy()->utc()->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_volunteer_recruitment_requires_a_positive_whole_number_of_slots(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $projectId = $this->createProject($owner);

        foreach ([null, 0, '1.5'] as $slotsNeeded) {
            $request = Request::create('/student-org/announcements', 'POST', [
                'title' => 'Volunteer recruitment',
                'category' => 'Volunteer recruitment',
                'project_id' => $projectId,
                'slots_needed' => $slotsNeeded,
                'status' => 'draft',
            ]);
            $request->setUserResolver(fn () => $owner);

            try {
                app(AnnouncementController::class)->store($request);
                $this->fail('Volunteer recruitment must require at least one whole-number slot.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $this->assertArrayHasKey('slots_needed', $exception->errors());
            }
        }

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_general_announcement_can_be_created_without_a_project(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $this->mockAnnouncementRedirect();
        $request = Request::create('/student-org/announcements', 'POST', [
            'title' => 'Community notice',
            'description' => 'A notice for all participants',
            'category' => 'General',
            'status' => 'published',
        ]);
        $request->setUserResolver(fn () => $owner);

        app(AnnouncementController::class)->store($request);

        $this->assertDatabaseHas('announcements', [
            'title' => 'Community notice',
            'category' => 'General',
            'project_id' => null,
            'status' => 'published',
        ]);
    }

    public function test_general_announcement_can_optionally_link_an_activity_without_recruitment_fields(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $projectId = $this->createProject($owner);
        $this->mockAnnouncementRedirect();
        $request = Request::create('/student-org/announcements', 'POST', [
            'title' => 'Community notice',
            'category' => 'General',
            'project_id' => $projectId,
            'slots_needed' => 5,
            'application_deadline' => now()->addWeek()->toDateString(),
            'event_date' => now()->addDays(10)->toDateTimeString(),
            'event_location' => 'Campus',
            'latitude' => 14.6,
            'longitude' => 121.0,
            'status' => 'published',
        ]);
        $request->setUserResolver(fn () => $owner);

        app(AnnouncementController::class)->store($request);

        $this->assertDatabaseHas('announcements', [
            'title' => 'Community notice',
            'category' => 'General',
            'project_id' => $projectId,
            'slots_needed' => null,
            'application_deadline' => null,
            'event_date' => null,
            'event_location' => null,
            'latitude' => null,
            'longitude' => null,
        ]);
    }

    public function test_participant_cannot_apply_to_a_general_announcement(): void
    {
        $participant = User::factory()->create(['role' => 'participant']);
        $announcement = new Announcement([
            'category' => 'General',
            'status' => 'published',
            'slots_needed' => null,
        ]);
        $request = Request::create('/participant/activities/1/apply', 'POST');
        $request->setUserResolver(fn () => $participant);

        try {
            app(ParticipantController::class)->apply($request, $announcement);
            $this->fail('Participants must not apply to a General announcement.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_participant_can_open_a_published_general_announcement(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $participant = User::factory()->create(['role' => 'participant']);
        $announcement = Announcement::create([
            'project_id' => null,
            'created_by' => $owner->id,
            'title' => 'Community notice',
            'category' => 'General',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $request = Request::create('/participant/activities/'.$announcement->id);
        $request->setUserResolver(fn () => $participant);

        $response = app(ParticipantController::class)->show($request, $announcement);

        $this->assertSame('PARTICIPANT.activity_show', $response->name());
    }

    public function test_participant_can_open_a_general_announcement_linked_to_an_approved_activity(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $participant = User::factory()->create(['role' => 'participant']);
        $projectId = $this->createProject($owner);
        $announcement = Announcement::create([
            'project_id' => $projectId,
            'created_by' => $owner->id,
            'title' => 'Activity information',
            'category' => 'General',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $request = Request::create('/participant/activities/'.$announcement->id);
        $request->setUserResolver(fn () => $participant);

        $response = app(ParticipantController::class)->show($request, $announcement);

        $this->assertSame('PARTICIPANT.activity_show', $response->name());
    }

    public function test_owner_can_unpublish_and_participants_cannot_open_the_draft(): void
    {
        $owner = User::factory()->create(['role' => 'college_department']);
        $projectId = $this->createProject($owner);
        $announcement = Announcement::create([
            'project_id' => $projectId,
            'created_by' => $owner->id,
            'title' => 'Upcoming activity',
            'category' => 'Volunteer recruitment',
            'status' => 'published',
            'slots_needed' => 5,
            'published_at' => now(),
        ]);
        $this->mockAnnouncementRedirect();
        $request = Request::create('/student-org/announcements/'.$announcement->id.'/status', 'PATCH', [
            'status' => 'draft',
        ]);
        $request->setUserResolver(fn () => $owner);

        app(AnnouncementController::class)->updateStatus($request, $announcement);

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'status' => 'draft',
            'published_at' => null,
        ]);

        $participant = User::factory()->create(['role' => 'participant']);
        $participantRequest = Request::create('/participant/activities/'.$announcement->id);
        $participantRequest->setUserResolver(fn () => $participant);

        try {
            app(ParticipantController::class)->show($participantRequest, $announcement->fresh());
            $this->fail('Unpublished announcements must not be visible to participants.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    private function createProject(User $owner): int
    {
        return (int) DB::table('projects')->insertGetId([
            'created_by' => $owner->id,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function mockAnnouncementRedirect(): void
    {
        $redirector = \Mockery::mock(Redirector::class);
        $response = new RedirectResponse('/student-org/announcements');
        $response->setSession($this->app['session.store']);
        $redirector->shouldReceive('route')
            ->once()
            ->with('student_org.announcements')
            ->andReturn($response);

        $this->app->instance('redirect', $redirector);
    }
}
