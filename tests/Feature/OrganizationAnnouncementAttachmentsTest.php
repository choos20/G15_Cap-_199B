<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationAnnouncementAttachmentsTest extends TestCase
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

        Schema::create('notifications', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by');
            $table->string('title')->default('Test activity');
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

        Schema::create('announcement_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function test_attachment_limit_and_owner_removal_are_enforced(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $otherOrg = User::factory()->create(['role' => 'student_org']);
        $projectId = (int) DB::table('projects')->insertGetId([
            'created_by' => $owner->id,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $files = array_map(
            fn ($number) => UploadedFile::fake()->create("file-{$number}.pdf", 1, 'application/pdf'),
            range(1, 6)
        );

        $this->actingAs($owner)
            ->from(route('student_org.announcements'))
            ->post(route('student_org.announcements.store'), [
                'title' => 'Announcement with too many files',
                'description' => 'Testing file count limit',
                'category' => 'General',
                'project_id' => $projectId,
                'status' => 'draft',
                'attachments' => $files,
            ])
            ->assertRedirect(route('student_org.announcements'))
            ->assertSessionHasErrors('attachments');

        $this->assertDatabaseCount('announcements', 0);

        Storage::fake('public');
        $announcement = Announcement::create([
            'project_id' => $projectId,
            'created_by' => $owner->id,
            'title' => 'My announcement',
            'category' => 'General',
            'status' => 'draft',
        ]);
        $attachment = AnnouncementAttachment::create([
            'announcement_id' => $announcement->id,
            'original_name' => 'poster.pdf',
            'file_path' => 'announcement_attachments/poster.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);
        Storage::disk('public')->put($attachment->file_path, 'test file');

        $this->actingAs($otherOrg)
            ->delete(route('student_org.announcements.attachments.destroy', [$announcement, $attachment]))
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('student_org.announcements.attachments.destroy', [$announcement, $attachment]))
            ->assertRedirect(route('student_org.announcements.show', $announcement))
            ->assertSessionHas('status', 'Attachment removed successfully.');

        $this->assertDatabaseMissing('announcement_attachments', ['id' => $attachment->id]);
        Storage::disk('public')->assertMissing($attachment->file_path);

        $this->actingAs($owner)
            ->get(route('student_org.announcements.show', $announcement))
            ->assertOk()
            ->assertSee(route('student_org.announcements.edit', $announcement), false)
            ->assertSee(route('student_org.announcements.destroy', $announcement), false);

        $this->actingAs($otherOrg)
            ->get(route('student_org.announcements.edit', $announcement))
            ->assertForbidden();

        $updateData = [
            'title' => 'Updated announcement',
            'description' => 'Updated details',
            'category' => 'Volunteer recruitment',
            'project_id' => $projectId,
            'status' => 'draft',
            'event_date' => now('Asia/Manila')->addDays(2)->format('Y-m-d\TH:i'),
            'attachments' => [
                UploadedFile::fake()->create('poster.pdf', 1, 'application/pdf'),
            ],
        ];

        $this->put(route('student_org.announcements.update', $announcement), $updateData)
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('student_org.announcements.edit', $announcement))
            ->assertOk()
            ->assertSee('Edit Announcement')
            ->assertSee('Current attachments (0/5)');

        $pastDateData = $updateData;
        $pastDateData['event_date'] = '2000-11-20T07:30';
        $this->put(route('student_org.announcements.update', $announcement), $pastDateData)
            ->assertRedirect()
            ->assertSessionHasErrors('event_date');

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'My announcement',
        ]);

        $this->put(route('student_org.announcements.update', $announcement), $updateData)
            ->assertRedirect(route('student_org.announcements.show', $announcement))
            ->assertSessionHas('status', 'Announcement updated successfully.');

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'Updated announcement',
            'description' => 'Updated details',
        ]);
        $this->assertDatabaseHas('announcement_attachments', [
            'announcement_id' => $announcement->id,
            'original_name' => 'poster.pdf',
        ]);
        $this->assertCount(1, Storage::disk('public')->allFiles('announcement_attachments'));

        $announcement->event_date = \Illuminate\Support\Carbon::parse('2000-11-20 07:30:00');
        $announcement->save();
        $legacyDateData = $updateData;
        unset($legacyDateData['attachments']);
        $legacyDateData['title'] = 'Updated legacy announcement';
        $legacyDateData['event_date'] = '2000-11-20T07:30';

        $this->put(route('student_org.announcements.update', $announcement), $legacyDateData)
            ->assertRedirect(route('student_org.announcements.show', $announcement))
            ->assertSessionHas('status', 'Announcement updated successfully.');

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'Updated legacy announcement',
            'event_date' => '2000-11-20 07:30:00',
        ]);
    }
}
