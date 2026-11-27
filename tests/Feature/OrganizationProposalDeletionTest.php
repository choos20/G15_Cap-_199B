<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationProposalDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

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
            $table->foreignId('created_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('created_by');
            $table->string('status')->default('submitted');
            $table->unsignedInteger('volunteers_count')->default(0);
            $table->unsignedInteger('hours_rendered')->default(0);
            $table->text('review_comments')->nullable();
            $table->timestamp('notification_seen_at')->nullable();
            $table->timestamps();
        });
        Schema::create('project_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by');
            $table->string('title');
            $table->string('status')->default('draft');
            $table->timestamps();
        });
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('qr_token')->unique();
            $table->decimal('latitude', 10, 7)->default(0);
            $table->decimal('longitude', 10, 7)->default(0);
            $table->unsignedSmallInteger('radius_meters')->default(100);
            $table->string('status')->default('open');
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable();
            $table->string('actor_name');
            $table->string('actor_role')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->text('summary');
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    public function test_proposal_deletion_is_scoped_to_own_submitted_or_returned_proposals(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $projectId = $this->createProject($owner, 'returned_by_csa');
        Storage::disk('public')->put('project_attachments/old.pdf', 'test file');
        DB::table('project_attachments')->insert([
            'project_id' => $projectId,
            'file_name' => 'old.pdf',
            'file_path' => 'project_attachments/old.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 9,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->delete(route('student_org.projects.destroy', $projectId))
            ->assertRedirect(route('student_org.proposals'))
            ->assertSessionHas('status', 'Proposal deleted.');

        $this->assertDatabaseMissing('projects', ['id' => $projectId]);
        $this->assertDatabaseMissing('project_attachments', ['project_id' => $projectId]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'project.deleted',
            'subject_id' => $projectId,
        ]);
        Storage::disk('public')->assertMissing('project_attachments/old.pdf');

        $submittedId = $this->createProject($owner, 'submitted');
        $this->actingAs($owner)
            ->delete(route('student_org.projects.destroy', $submittedId))
            ->assertRedirect(route('student_org.proposals'))
            ->assertSessionHas('status', 'Proposal deleted.');
        $this->assertDatabaseMissing('projects', ['id' => $submittedId]);

        $otherOwner = User::factory()->create(['role' => 'college_department']);
        $approvedId = $this->createProject($owner, 'approved');
        $otherProjectId = $this->createProject($otherOwner, 'submitted');

        $this->actingAs($owner)
            ->delete(route('student_org.projects.destroy', $approvedId))
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('student_org.projects.destroy', $otherProjectId))
            ->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $approvedId, 'status' => 'approved']);
        $this->assertDatabaseHas('projects', ['id' => $otherProjectId, 'status' => 'submitted']);

        $projectId = $this->createProject($owner, 'returned_by_secretary');
        DB::table('announcements')->insert([
            'project_id' => $projectId,
            'created_by' => $owner->id,
            'title' => 'Linked activity',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->delete(route('student_org.projects.destroy', $projectId))
            ->assertRedirect(route('student_org.proposals'))
            ->assertSessionHasErrors('proposal');

        $this->assertDatabaseHas('projects', ['id' => $projectId]);
    }

    public function test_authorized_reviewer_can_load_a_project_docx_for_preview(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $reviewer = User::factory()->create(['role' => 'celp_secretary']);
        $projectId = $this->createProject($owner, 'submitted');
        $filePath = 'project_attachments/review.docx';
        $fileContents = $this->minimalDocxBytes();

        Storage::disk('public')->put($filePath, $fileContents);
        $attachmentId = DB::table('project_attachments')->insertGetId([
            'project_id' => $projectId,
            'file_name' => 'review.docx',
            'file_path' => $filePath,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => strlen($fileContents),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->get(route('projects.attachments.preview.content', $attachmentId))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertContent($fileContents);
    }

    public function test_incomplete_project_docx_is_not_rendered_but_remains_downloadable(): void
    {
        $owner = User::factory()->create(['role' => 'student_org']);
        $reviewer = User::factory()->create(['role' => 'celp_secretary']);
        $projectId = $this->createProject($owner, 'submitted');
        $filePath = 'project_attachments/incomplete.docx';
        $fileContents = 'incomplete docx bytes';

        Storage::disk('public')->put($filePath, $fileContents);
        $attachmentId = DB::table('project_attachments')->insertGetId([
            'project_id' => $projectId,
            'file_name' => 'incomplete.docx',
            'file_path' => $filePath,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => strlen($fileContents),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->get(route('projects.attachments.preview.content', $attachmentId))
            ->assertStatus(422);

        $this->actingAs($reviewer)
            ->get(route('projects.attachments.download', $attachmentId))
            ->assertOk()
            ->assertDownload('incomplete.docx');
    }

    private function minimalDocxBytes(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'projectlink-docx-');
        $zip = new \ZipArchive();
        $opened = $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $this->assertTrue($opened === true || $opened === \ZipArchive::ER_OK);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>');
        $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Preview test</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        $bytes = file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    private function createProject(User $owner, string $status): int
    {
        return DB::table('projects')->insertGetId([
            'title' => 'Test proposal title',
            'created_by' => $owner->id,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
