<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EvaluationCorrectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('email')->unique(); $t->timestamp('email_verified_at')->nullable(); $t->string('password'); $t->string('role'); $t->rememberToken(); $t->timestamps(); });
        Schema::create('projects', function (Blueprint $t) { $t->id(); $t->string('status'); $t->timestamps(); });
        Schema::create('announcements', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('created_by'); $t->unsignedBigInteger('project_id'); $t->string('title'); $t->dateTime('event_date'); $t->timestamps(); });
        Schema::create('evaluations', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('announcement_id'); $t->unsignedInteger('expected_beneficiaries')->nullable(); $t->unsignedInteger('paper_beneficiary_count')->nullable(); $t->unsignedInteger('paper_implementer_count')->nullable(); $t->boolean('correction_in_progress')->default(false); $t->timestamp('submitted_at')->nullable(); $t->unsignedBigInteger('submitted_by')->nullable(); $t->timestamps(); });
        Schema::create('evaluation_responses', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('evaluation_id'); $t->string('respondent_type'); $t->timestamps(); });
        Schema::create('evaluation_history', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('evaluation_id'); $t->unsignedBigInteger('user_id')->nullable(); $t->string('action'); $t->string('reason')->nullable(); $t->unsignedInteger('beneficiary_count'); $t->unsignedInteger('implementer_count'); $t->timestamps(); });
        $this->withoutMiddleware([\App\Http\Middleware\EnsureMfaVerified::class]);
    }

    public function test_owner_can_reopen_and_resubmit_without_losing_responses(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $project = DB::table('projects')->insertGetId(['status' => 'approved']);
        $activity = DB::table('announcements')->insertGetId(['created_by' => $owner->id, 'project_id' => $project, 'title' => 'Activity', 'event_date' => now()->subDay()]);
        $batch = DB::table('evaluations')->insertGetId(['announcement_id' => $activity, 'submitted_at' => now(), 'submitted_by' => $owner->id]);
        DB::table('evaluation_responses')->insert(['evaluation_id' => $batch, 'respondent_type' => 'beneficiary']);

        $this->actingAs($owner)->post(route('evaluations.reopen', $activity), ['reason' => 'More forms received'])->assertRedirect();
        $this->assertDatabaseHas('evaluations', ['id' => $batch, 'submitted_at' => null, 'correction_in_progress' => true]);
        $this->assertDatabaseHas('evaluation_history', ['evaluation_id' => $batch, 'action' => 'reopened', 'reason' => 'More forms received', 'beneficiary_count' => 1]);
        $this->assertDatabaseCount('evaluation_responses', 1);
        $this->actingAs($owner)->post(route('evaluations.paper-counts', $activity), ['paper_beneficiary_count' => 20, 'paper_implementer_count' => 0])->assertRedirect();
        $this->actingAs($owner)->post(route('evaluations.finish', $activity), ['confirm_incomplete' => '1'])->assertRedirect();
        $this->assertDatabaseHas('evaluations', ['id' => $batch, 'paper_beneficiary_count' => 20, 'correction_in_progress' => false]);
        $this->assertDatabaseHas('evaluation_history', ['evaluation_id' => $batch, 'action' => 'resubmitted', 'beneficiary_count' => 1]);
    }

    public function test_original_submitter_can_reopen_and_continue_a_correction(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $submitter = User::factory()->create(['role' => 'student_org']);
        $project = DB::table('projects')->insertGetId(['status' => 'approved']);
        $activity = DB::table('announcements')->insertGetId(['created_by' => $owner->id, 'project_id' => $project, 'title' => 'Activity', 'event_date' => now()->subDay()]);
        $batch = DB::table('evaluations')->insertGetId([
            'announcement_id' => $activity,
            'submitted_at' => now(),
            'submitted_by' => $submitter->id,
        ]);
        DB::table('evaluation_responses')->insert(['evaluation_id' => $batch, 'respondent_type' => 'beneficiary']);

        $this->actingAs($submitter)->post(route('evaluations.reopen', $activity), ['reason' => 'The paper batch was incomplete'])->assertRedirect();
        $this->assertDatabaseHas('evaluations', [
            'id' => $batch,
            'submitted_at' => null,
            'submitted_by' => $submitter->id,
            'correction_in_progress' => true,
        ]);

        $this->actingAs($submitter)->post(route('evaluations.paper-counts', $activity), [
            'paper_beneficiary_count' => 21,
        ])->assertRedirect();
    }

    public function test_incomplete_batch_requires_explicit_confirmation(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $project = DB::table('projects')->insertGetId(['status' => 'approved']);
        $activity = DB::table('announcements')->insertGetId(['created_by' => $owner->id, 'project_id' => $project, 'title' => 'Activity', 'event_date' => now()->subDay()]);
        $batch = DB::table('evaluations')->insertGetId([
            'announcement_id' => $activity,
            'paper_beneficiary_count' => 21,
            'correction_in_progress' => true,
            'submitted_by' => $owner->id,
        ]);
        DB::table('evaluation_responses')->insert(['evaluation_id' => $batch, 'respondent_type' => 'beneficiary']);

        $this->actingAs($owner)
            ->post(route('evaluations.finish', $activity))
            ->assertRedirect()
            ->assertSessionHasErrors('batch');
        $this->assertDatabaseHas('evaluations', ['id' => $batch, 'submitted_at' => null, 'correction_in_progress' => true]);

        $this->actingAs($owner)
            ->post(route('evaluations.finish', $activity), ['confirm_incomplete' => '1'])
            ->assertRedirect();
        $this->assertDatabaseHas('evaluations', ['id' => $batch, 'correction_in_progress' => false]);
    }

    public function test_other_organization_cannot_reopen_a_batch(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $other = User::factory()->create(['role' => 'student_org']);
        $project = DB::table('projects')->insertGetId(['status' => 'approved']);
        $activity = DB::table('announcements')->insertGetId(['created_by' => $owner->id, 'project_id' => $project, 'title' => 'Activity', 'event_date' => now()->subDay()]);
        DB::table('evaluations')->insert(['announcement_id' => $activity, 'submitted_at' => now(), 'submitted_by' => $owner->id]);
        $this->actingAs($other)->post(route('evaluations.reopen', $activity), ['reason' => 'Correction'])->assertForbidden();
        $this->assertDatabaseCount('evaluation_history', 0);
    }
}
