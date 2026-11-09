<?php

namespace Tests\Feature;

use App\Http\Controllers\CelpCoordinatorController;
use App\Http\Controllers\CelpSecretaryController;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CelpRoleDashboardMetricsTest extends TestCase
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
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('created_by');
            $table->string('status')->default('submitted');
            $table->timestamps();
        });
        Schema::create('project_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
        });
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by');
            $table->timestamps();
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable();
            $table->string('status');
            $table->timestamp('published_at')->nullable();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable();
            $table->string('actor_name');
            $table->string('actor_role')->nullable();
            $table->string('action');
            $table->text('summary');
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_secretary_and_coordinator_dashboards_only_show_their_workflow_and_review_events(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00'));

        $secretary = User::factory()->create(['role' => 'celp_secretary']);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $initiator = User::factory()->create(['role' => 'student_org']);

        foreach ([
            'submitted',
            'endorsed_by_secretary',
            'returned_by_secretary',
            'endorsed_by_secretary',
            'endorsed_by_coordinator',
            'returned_by_coordinator',
            'returned_by_celp_head',
        ] as $status) {
            Project::create([
                'title' => 'Project '.$status,
                'created_by' => $initiator->id,
                'status' => $status,
            ]);
        }

        $this->recordReview($secretary, 'project.advanced', 'endorsed_by_secretary', '2026-09-10 10:00:00');
        $this->recordReview($secretary, 'project.returned', 'returned_by_secretary', '2026-09-12 10:00:00');
        $this->recordReview($secretary, 'project.advanced', 'endorsed_by_coordinator', '2026-09-13 10:00:00');
        $this->recordReview($coordinator, 'project.advanced', 'endorsed_by_coordinator', '2026-09-15 10:00:00');
        $this->recordReview($coordinator, 'project.returned', 'returned_by_coordinator', '2026-09-18 10:00:00');
        $this->recordReview($coordinator, 'project.returned', 'returned_by_secretary', '2026-09-19 10:00:00');

        $secretaryRequest = Request::create('/celp-secretary');
        $secretaryRequest->setUserResolver(fn () => $secretary);
        $secretaryData = app(CelpSecretaryController::class)->index($secretaryRequest)->getData();
        $secretaryStages = collect($secretaryData['workflowStages'])->keyBy('key');
        $secretarySeptember = collect($secretaryData['reviewTrend'])->firstWhere('label', 'Sep');

        $this->assertSame(1, $secretaryStages['pending']['count']);
        $this->assertSame(2, $secretaryStages['forwarded']['count']);
        $this->assertSame(1, $secretaryStages['returned']['count']);
        $this->assertSame(1, $secretarySeptember['advanced']);
        $this->assertSame(1, $secretarySeptember['returned']);

        $coordinatorRequest = Request::create('/celp-coordinator');
        $coordinatorRequest->setUserResolver(fn () => $coordinator);
        $coordinatorData = app(CelpCoordinatorController::class)->index($coordinatorRequest)->getData();
        $coordinatorStages = collect($coordinatorData['workflowStages'])->keyBy('key');
        $coordinatorSeptember = collect($coordinatorData['reviewTrend'])->firstWhere('label', 'Sep');

        $this->assertSame(2, $coordinatorStages['pending']['count']);
        $this->assertSame(1, $coordinatorStages['forwarded']['count']);
        $this->assertSame(1, $coordinatorStages['returned']['count']);
        $this->assertSame(1, $coordinatorSeptember['advanced']);
        $this->assertSame(1, $coordinatorSeptember['returned']);
    }

    private function recordReview(User $actor, string $action, string $toStatus, string $createdAt): void
    {
        $event = AuditLog::create([
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => $actor->role,
            'action' => $action,
            'summary' => 'Recorded test review.',
            'context' => ['to_status' => $toStatus],
        ]);
        $event->forceFill(['created_at' => Carbon::parse($createdAt)])->saveQuietly();
    }
}
