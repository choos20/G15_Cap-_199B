<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnnouncementEventDateValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-05 13:30:00', 'Asia/Manila'));

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
            $table->foreignId('created_by');
            $table->string('status')->default('approved');
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->foreignId('created_by');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category');
            $table->string('status');
            $table->unsignedInteger('slots_needed')->nullable();
            $table->dateTime('application_deadline')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('event_date')->nullable();
            $table->string('event_location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_org_cannot_create_an_announcement_with_a_past_event_datetime(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $projectId = (int) \Illuminate\Support\Facades\DB::table('projects')->insertGetId([
            'created_by' => $owner->id,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->from(route('student_org.announcements'))
            ->post(route('student_org.announcements.store'), [
                'title' => 'Past event',
                'category' => 'General',
                'project_id' => $projectId,
                'status' => 'draft',
                'event_date' => '2000-11-20T07:30',
            ])
            ->assertRedirect(route('student_org.announcements'))
            ->assertSessionHasErrors([
                'event_date' => 'The event date and time must be now or in the future.',
            ]);

        $this->assertDatabaseCount('announcements', 0);
    }
}
