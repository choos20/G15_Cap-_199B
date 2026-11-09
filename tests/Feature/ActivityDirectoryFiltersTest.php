<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivityDirectoryFiltersTest extends TestCase
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
            $table->string('title');
            $table->foreignId('created_by');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->foreignId('created_by');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('status');
            $table->unsignedInteger('slots_needed')->nullable();
            $table->dateTime('application_deadline')->nullable();
            $table->dateTime('event_date')->nullable();
            $table->string('event_location')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
        });
        Schema::create('volunteer_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id');
            $table->foreignId('user_id');
            $table->string('status');
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

    public function test_activity_directory_filters_by_status_and_event_date(): void
    {
        Carbon::setTestNow('2026-10-28 10:00:00');
        $viewer = User::factory()->create(['role' => 'celp_head']);

        $this->createActivity('Recruiting activity', now()->addDays(2), now()->addDay(), 2);
        $full = $this->createActivity('Full activity', now()->addDays(3), now()->addDay(), 1);
        DB::table('volunteer_applications')->insert([
            'announcement_id' => $full,
            'user_id' => $viewer->id,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createActivity('Completed activity', now()->subDay(), now()->subDays(2), 2);
        $this->createActivity('Applications closed activity', now()->addDays(4), now()->subDay(), 2);

        $this->actingAs($viewer)
            ->get(route('activities.index', [
                'status' => 'recruiting',
                'from' => now()->addDays(2)->toDateString(),
                'to' => now()->addDays(2)->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Recruiting activity')
            ->assertDontSee('Full activity')
            ->assertDontSee('Completed activity');

        $this->actingAs($viewer)
            ->get(route('activities.index', ['status' => 'full']))
            ->assertOk()
            ->assertSee('Full activity')
            ->assertDontSee('Recruiting activity');

        $this->actingAs($viewer)
            ->get(route('activities.index', ['status' => 'completed']))
            ->assertOk()
            ->assertSee('Completed activity');

        $this->actingAs($viewer)
            ->get(route('activities.index', ['status' => 'applications_closed']))
            ->assertOk()
            ->assertSee('Applications closed activity')
            ->assertDontSee('Recruiting activity');
    }

    private function createActivity(string $title, Carbon $eventDate, Carbon $deadline, int $slots): int
    {
        $organization = User::factory()->create([
            'role' => 'student_org',
            'organization_name' => 'Test Organization',
        ]);
        $projectId = DB::table('projects')->insertGetId([
            'title' => $title,
            'created_by' => $organization->id,
            'status' => 'csa_cleared',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('announcements')->insertGetId([
            'project_id' => $projectId,
            'created_by' => $organization->id,
            'title' => $title,
            'status' => 'published',
            'slots_needed' => $slots,
            'application_deadline' => $deadline->format('Y-m-d H:i:s'),
            'event_date' => $eventDate,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
