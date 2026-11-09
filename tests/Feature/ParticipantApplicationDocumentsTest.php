<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Project;
use App\Models\User;
use App\Models\VolunteerApplication;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParticipantApplicationDocumentsTest extends TestCase
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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('created_by');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('created_by');
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
            $table->unsignedBigInteger('announcement_id');
            $table->string('original_name');
            $table->string('file_path');
            $table->timestamps();
        });
        Schema::create('volunteer_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('announcement_id');
            $table->unsignedBigInteger('user_id');
            $table->string('status')->default('pending');
            $table->text('decision_notes')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
        Schema::create('volunteer_application_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('volunteer_application_id');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function test_applications_accept_at_most_three_supporting_documents(): void
    {
        Storage::fake('local');
        Notification::fake();

        $organizer = User::factory()->create(['role' => 'student_org']);
        $participant = User::factory()->create([
            'role' => 'participant',
            'must_change_password' => false,
        ]);
        $project = Project::create([
            'title' => 'Community Activity',
            'created_by' => $organizer->id,
            'status' => 'approved',
        ]);
        $announcement = Announcement::create([
            'project_id' => $project->id,
            'created_by' => $organizer->id,
            'title' => 'Volunteer Opportunity',
            'status' => 'published',
            'slots_needed' => 10,
            'event_date' => now()->addWeek(),
        ]);

        $this->actingAs($participant)
            ->get(route('participant.activities.show', $announcement))
            ->assertOk()
            ->assertSee('Upload 1–3 files')
            ->assertSee('application-documents-list')
            ->assertSee('View')
            ->assertSee('Remove');

        $this->post(route('participant.activities.apply', $announcement), [
            'documents' => [
                UploadedFile::fake()->create('one.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('two.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('three.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('four.pdf', 10, 'application/pdf'),
            ],
        ])->assertSessionHasErrors('documents');

        $this->assertDatabaseCount('volunteer_applications', 0);

        $this->post(route('participant.activities.apply', $announcement), [
            'documents' => [
                UploadedFile::fake()->create('one.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('two.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('three.pdf', 10, 'application/pdf'),
            ],
        ])->assertRedirect(route('participant.activities.show', $announcement));

        $this->assertDatabaseCount('volunteer_applications', 1);
        $this->assertDatabaseCount('volunteer_application_attachments', 3);
        $this->assertCount(3, Storage::disk('local')->allFiles('volunteer_documents'));

        $application = VolunteerApplication::firstOrFail();
        $this->get(route('participant.applications'))
            ->assertOk()
            ->assertSee('one.pdf')
            ->assertSee('two.pdf')
            ->assertSee('three.pdf')
            ->assertDontSee('>View</a>', false)
            ->assertDontSee('>Remove</button>', false);

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('volunteer_applications.documents.view'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('participant.applications.documents.remove'));
        $this->assertDatabaseCount('volunteer_application_attachments', 3);
        $this->assertCount(3, Storage::disk('local')->allFiles('volunteer_documents'));
    }
}
