<?php

namespace Tests\Feature;

use App\Exceptions\DocumentPreviewException;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\Template;
use App\Models\User;
use App\Services\DocumentPreviewService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DocumentFixtures;
use Tests\TestCase;

class DocumentPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('role');
            $table->string('organization_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table): void {
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
        foreach (['templates', 'project_attachments'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->id();
                if ($name === 'templates') {
                    $table->string('original_name');
                    $table->foreignId('uploaded_by');
                    $table->string('source')->nullable();
                } else {
                    $table->foreignId('project_id');
                    $table->string('file_name');
                    $table->string('mime_type')->nullable();
                }
                $table->string('file_path');
                $table->binary('file_data')->nullable();
                $table->string('file_mime')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('file_sha256', 64)->nullable();
                $table->string('file_disk', 20)->nullable();
                $table->timestamps();
            });
        }
        Schema::create('notifications', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
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

    public static function reviewers(): array
    {
        return [
            'CELP Secretary' => ['celp_secretary', 'submitted'],
            'Coordinator (existing second review role)' => ['coordinator', 'endorsed_by_secretary'],
            'CELP Head' => ['celp_head', 'endorsed_by_coordinator'],
            'CSA Director' => ['csa_director', 'approved'],
        ];
    }

    #[DataProvider('reviewers')]
    public function test_same_multipage_docx_survives_both_upload_workflows_and_each_reviewer(string $role, string $stage): void
    {
        $bytes = DocumentFixtures::docx();
        $hash = hash('sha256', $bytes);
        $reviewer = User::factory()->create(['role' => $role]);
        $owner = User::factory()->create(['name' => 'Proposal Applicant', 'role' => 'student_org', 'organization_name' => 'Preview Test Organization']);
        $this->actingAs($reviewer)->post(route('templates.store'), [
            'template' => UploadedFile::fake()->createWithContent('same-proposal.docx', $bytes),
        ])->assertSessionHasNoErrors()->assertRedirect(route('templates.manage'));
        $template = Template::firstOrFail();
        $token = (string) Str::uuid();
        $this->actingAs($owner)->withSession(['project_submission_token' => $token])->post(route('projects.store'), [
            'submission_token' => $token, 'title' => 'Shared preview proposal',
            'attachments' => [UploadedFile::fake()->createWithContent('same-proposal.docx', $bytes)],
        ])->assertSessionHasNoErrors()->assertRedirect(route('student_org.dashboard'));
        $attachment = ProjectAttachment::firstOrFail();
        $project = $attachment->project;
        $project->update(['status' => $stage]);
        foreach ([$template, $attachment] as $document) {
            $this->assertSame($bytes, $document->fresh()->file_data);
            $this->assertSame($hash, $document->fresh()->file_sha256);
            $this->assertSame(strlen($bytes), (int) $document->fresh()->file_size);
            $this->assertSame('local', $document->fresh()->file_disk);
            Storage::disk('local')->assertExists($document->file_path);
            Storage::disk('public')->assertMissing($document->file_path);
            // Simulate a new deployment losing the ephemeral disk entirely.
            Storage::disk('local')->delete($document->file_path);
        }
        $this->actingAs($reviewer);
        foreach ([
            route('templates.preview.content', $template), route('templates.download', $template),
            route('projects.attachments.preview.content', $attachment), route('projects.attachments.download', $attachment),
        ] as $url) {
            $this->get($url)->assertOk()->assertContent($bytes)
                ->assertHeader('Content-Length', (string) strlen($bytes))
                ->assertHeader('X-Document-SHA256', $hash)
                ->assertHeader('X-Content-Type-Options', 'nosniff');
        }
        $this->get(route('projects.attachments.download', $attachment))->assertDownload('same-proposal.docx');
        $this->get(route('projects.show_review', $project))->assertOk()
            ->assertSee(route('projects.attachments.preview', $attachment), false)
            ->assertSee(route('projects.attachments.download', $attachment), false)->assertDontSee('target="_blank"', false);
        $this->get(route('projects.attachments.preview', $attachment))->assertOk()
            ->assertSee('Back to Review')->assertSee('same-proposal.docx')->assertSee('DOCX')
            ->assertSee('Proposal Applicant')->assertSee('Preview Test Organization')->assertSee('Shared preview proposal')
            ->assertSee('Loading document preview...')->assertSee('Download original file')
            ->assertSee('aria-label="Zoom in" disabled', false)->assertSee('aria-label="Zoom out" disabled', false)
            ->assertSee('aria-label="Reset zoom" disabled', false)
            ->assertSee(route('projects.show_review', $project), false)->assertDontSee('target="_blank"', false);
        $this->get(route('templates.preview', $template))->assertOk()->assertSee('data-document-viewer', false);
    }

    public function test_stage_and_role_restrictions_apply_to_page_content_and_download_urls(): void
    {
        $document = $this->attachment(DocumentFixtures::docx());
        $urls = [route('projects.show_review', $document->project), route('projects.attachments.preview', $document),
            route('projects.attachments.preview.content', $document), route('projects.attachments.download', $document)];
        foreach ($urls as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $allowed = [
            'celp_secretary' => array_keys(Project::STATUS_LABELS),
            'coordinator' => ['endorsed_by_secretary', 'endorsed_by_coordinator', 'approved', 'csa_cleared', 'returned_by_coordinator', 'returned_by_celp_head', 'returned_by_csa'],
            'celp_head' => array_keys(Project::STATUS_LABELS),
            'csa_director' => ['approved', 'csa_cleared', 'returned_by_csa'],
            'participant' => [], 'student_org' => [], 'college_department' => [], 'celp_director' => [],
        ];
        foreach ($allowed as $role => $stages) {
            $user = User::factory()->create(['role' => $role]);
            foreach (array_keys(Project::STATUS_LABELS) as $stage) {
                $document->project->update(['status' => $stage]);
                $this->actingAs($user);
                foreach ($urls as $url) {
                    $this->get($url)->assertStatus(in_array($stage, $stages, true) ? 200 : 403);
                }
            }
        }
        $this->get(route('projects.attachments.download', 999999))->assertNotFound();
    }

    public function test_legacy_intact_disk_copy_recovers_truncated_database_bytes_without_reupload(): void
    {
        $bytes = DocumentFixtures::docx();
        $document = $this->attachment($bytes);
        $document->update(['file_data' => substr($bytes, 0, 5), 'file_disk' => null, 'file_sha256' => null]);
        Storage::disk('public')->put($document->file_path, $bytes);
        $reviewer = User::factory()->create(['role' => 'celp_secretary']);
        $this->actingAs($reviewer)->get(route('projects.attachments.preview.content', $document))->assertOk()->assertContent($bytes);
        $this->artisan('documents:audit', ['--type' => 'project', '--id' => [$document->id]])->assertSuccessful();
        $this->assertSame(5, strlen($document->fresh()->file_data));
        $this->artisan('documents:audit', ['--type' => 'project', '--id' => [$document->id], '--repair' => true, '--sha256' => hash('sha256', $bytes)])->assertSuccessful();
        $this->assertSame($bytes, $document->fresh()->file_data);
        $this->assertSame(hash('sha256', $bytes), $document->fresh()->file_sha256);
        Storage::disk('local')->assertExists($document->file_path);
        Storage::disk('public')->assertMissing($document->file_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.recovered', 'subject_id' => $document->id]);
    }

    public function test_backup_recovery_requires_matching_path_size_structure_and_optional_checksum(): void
    {
        $bytes = DocumentFixtures::docx();
        $document = $this->attachment($bytes);
        $document->update(['file_data' => null]);
        $backupRoot = Storage::disk('local')->path('trusted-backup');
        Storage::disk('local')->put('trusted-backup/'.$document->file_path, $bytes);
        $arguments = ['--type' => 'project', '--id' => [$document->id], '--repair' => true, '--source-directory' => $backupRoot];
        $this->artisan('documents:audit', $arguments + ['--sha256' => str_repeat('0', 64)])->assertFailed();
        $this->assertNull($document->fresh()->file_data);
        $this->artisan('documents:audit', $arguments + ['--sha256' => hash('sha256', $bytes)])->assertSuccessful();
        $this->assertSame($bytes, $document->fresh()->file_data);
        $this->artisan('documents:audit', ['--type' => 'project', '--id' => [999999], '--repair' => true])->assertFailed();
    }

    public function test_missing_corrupt_mismatched_and_unsupported_documents_have_distinct_errors(): void
    {
        $bytes = DocumentFixtures::docx();
        $document = $this->attachment($bytes);
        $this->actingAs(User::factory()->create(['role' => 'celp_secretary']));
        $url = route('projects.attachments.preview.content', $document);
        $document->update(['file_data' => null]);
        $this->getJson($url)->assertNotFound()->assertHeader('X-Document-Error', 'missing');
        $document->update(['file_data' => substr($bytes, 0, 5)]);
        $this->getJson($url)->assertStatus(422)->assertHeader('X-Document-Error', 'size_mismatch');
        $this->getJson(route('projects.attachments.download', $document))->assertStatus(422);
        $this->artisan('documents:audit', ['--type' => 'project', '--id' => [$document->id], '--repair' => true])->assertFailed();
        $this->assertSame(5, strlen($document->fresh()->file_data));
        $document->update(['file_data' => $bytes, 'file_sha256' => str_repeat('0', 64)]);
        $this->getJson($url)->assertStatus(422)->assertHeader('X-Document-Error', 'checksum_mismatch');
        $document->update(['file_data' => 'broken Office document', 'file_size' => 22, 'file_sha256' => null]);
        $this->getJson($url)->assertStatus(422)->assertHeader('X-Document-Error', 'corrupt');
        $this->get(route('projects.attachments.download', $document))->assertOk()->assertDownload();
        $document->update(['file_name' => 'legacy.doc']);
        $this->getJson($url)->assertStatus(415)->assertHeader('X-Document-Error', 'unsupported');
        $this->get(route('projects.attachments.download', $document))->assertOk()->assertDownload('legacy.doc');
        $document->update(['file_name' => 'large.pdf', 'file_data' => str_repeat('x', DocumentPreviewService::MAX_PREVIEW_BYTES + 1), 'file_size' => null]);
        $this->getJson($url)->assertStatus(413);
    }

    public function test_damaged_office_upload_is_rejected_before_any_record_or_file_is_created(): void
    {
        $bytes = substr(DocumentFixtures::docx(), 0, 200);
        $owner = User::factory()->create(['role' => 'student_org']);
        $this->actingAs($owner)->withSession(['project_submission_token' => 'upload-once'])->post(route('projects.store'), [
            'submission_token' => 'upload-once', 'title' => 'Broken submission',
            'attachments' => [UploadedFile::fake()->createWithContent('damaged.docx', $bytes)],
        ])->assertSessionHasErrors();
        $this->actingAs(User::factory()->create(['role' => 'celp_secretary']))->post(route('templates.store'), [
            'template' => UploadedFile::fake()->createWithContent('damaged.docx', $bytes),
        ])->assertSessionHasErrors();
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('project_attachments', 0);
        $this->assertDatabaseCount('templates', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_other_supported_formats_and_unicode_filenames_preserve_original_bytes(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'celp_secretary']));
        foreach (['document.pdf' => DocumentFixtures::pdf(), 'image.png' => DocumentFixtures::png(), 'budget.xlsx' => DocumentFixtures::xlsx(), 'résumé 100%.docx' => DocumentFixtures::docx()] as $name => $bytes) {
            $document = $this->attachment($bytes, $name);
            $this->get(route('projects.attachments.preview.content', $document))->assertOk()->assertContent($bytes);
            $this->get(route('projects.attachments.download', $document))->assertOk()->assertContent($bytes)->assertHeader('X-Document-SHA256', hash('sha256', $bytes));
        }
    }

    public function test_office_packages_with_missing_parts_invalid_xml_or_damaged_entry_data_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'celp_secretary']));
        foreach (['missing', 'xml', 'crc'] as $damage) {
            $path = tempnam(sys_get_temp_dir(), 'damaged-docx-');
            try {
                file_put_contents($path, DocumentFixtures::docx());
                $zip = new \ZipArchive;
                $zip->open($path);
                if ($damage === 'missing') {
                    $zip->deleteName('word/document.xml');
                } elseif ($damage === 'xml') {
                    $zip->addFromString('word/document.xml', '<w:document>unclosed');
                } else {
                    $zip->setCompressionName('word/document.xml', \ZipArchive::CM_STORE);
                }
                $zip->close();
                $bytes = file_get_contents($path);
                if ($damage === 'crc') {
                    $offset = strpos($bytes, '<w:document');
                    $this->assertNotFalse($offset);
                    $bytes[$offset + 3] = 'X';
                }
                $document = $this->attachment($bytes);
                $this->getJson(route('projects.attachments.preview.content', $document))->assertStatus(422)->assertHeader('X-Document-Error', 'corrupt');
                $this->get(route('projects.attachments.download', $document))->assertOk()->assertContent($bytes);
            } finally {
                @unlink($path);
            }
        }
    }

    public function test_failed_database_round_trip_rolls_back_the_upload_and_removes_the_new_private_copy(): void
    {
        $existing = $this->attachment(DocumentFixtures::docx());
        ProjectAttachment::saving(function (ProjectAttachment $document): void {
            $document->file_data = substr($document->file_data, 0, 5);
        });
        try {
            try {
                app(DocumentPreviewService::class)->storeUpload(
                    UploadedFile::fake()->createWithContent('round-trip.docx', DocumentFixtures::docx()),
                    new ProjectAttachment(['project_id' => $existing->project_id]), 'project_attachments'
                );
                $this->fail('A truncated database write must not be accepted.');
            } catch (DocumentPreviewException $error) {
                $this->assertSame(503, $error->getStatusCode());
                $this->assertSame('storage_failed', $error->reason);
            }
            $this->assertDatabaseCount('project_attachments', 1);
            $this->assertSame([], Storage::disk('local')->allFiles());
        } finally {
            ProjectAttachment::flushEventListeners();
        }
    }

    public function test_endorsement_approval_clearance_returns_notifications_and_resubmission_keep_documents_intact(): void
    {
        $users = [];
        foreach (array_keys(array_column(self::reviewers(), 1, 0)) as $role) {
            $users[$role] = User::factory()->create(['role' => $role]);
        }
        $owner = User::factory()->create(['role' => 'student_org']);
        $bytes = DocumentFixtures::docx();
        $token = (string) Str::uuid();
        $this->actingAs($owner)->withSession(['project_submission_token' => $token])->post(route('projects.store'), [
            'title' => 'Workflow proposal', 'submission_token' => $token,
            'attachments' => [UploadedFile::fake()->createWithContent('workflow.docx', $bytes)],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $document = ProjectAttachment::firstOrFail();
        $project = $document->project;
        $this->assertGreaterThan(0, $users['celp_secretary']->notifications()->count());
        foreach ([
            ['celp_secretary', 'secretary', 'endorse', 'endorsed_by_secretary', 'coordinator'],
            ['coordinator', 'coordinator', 'approve', 'endorsed_by_coordinator', 'celp_head'],
            ['celp_head', 'celp_head', 'approve', 'approved', 'csa_director'],
            ['csa_director', 'csa', 'clear', 'csa_cleared', null],
        ] as [$role, $route, $action, $expected, $nextRole]) {
            $this->actingAs($users[$role])->get(route('projects.attachments.preview.content', $document))->assertOk()->assertContent($bytes);
            $this->post(route('projects.'.$route.'_review.update', $project), ['action' => $action])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame($expected, $project->fresh()->status);
            $this->assertSame($bytes, $document->fresh()->file_data);
            if ($nextRole) {
                $this->assertGreaterThan(0, $users[$nextRole]->notifications()->count());
            }
        }
        $this->assertDatabaseCount('audit_logs', 4);
        $this->assertGreaterThanOrEqual(4, $owner->notifications()->count());
        foreach ([['celp_secretary', 'secretary', 'submitted', 'returned_by_secretary'], ['coordinator', 'coordinator', 'endorsed_by_secretary', 'returned_by_coordinator'], ['celp_head', 'celp_head', 'endorsed_by_coordinator', 'returned_by_celp_head'], ['csa_director', 'csa', 'approved', 'returned_by_csa']] as [$role, $route, $stage, $returned]) {
            $project->update(['status' => $stage]);
            $beforeNotifications = $owner->notifications()->count();
            $this->actingAs($users[$role])->post(route('projects.'.$route.'_review.update', $project), ['action' => 'return', 'comments' => 'Please revise the project schedule.'])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame($returned, $project->fresh()->status);
            $this->assertGreaterThan($beforeNotifications, $owner->notifications()->count());
            $this->actingAs($owner)->post(route('projects.update', $project), ['title' => 'Revised workflow proposal'])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame('submitted', $project->fresh()->status);
            $this->assertSame($bytes, $document->fresh()->file_data);
        }
    }

    protected function attachment(string $bytes, string $name = 'proposal.docx'): ProjectAttachment
    {
        $owner = User::factory()->create(['role' => 'student_org', 'organization_name' => 'Preview Test Organization']);
        $project = Project::create(['title' => 'Shared preview proposal', 'created_by' => $owner->id, 'status' => 'submitted']);

        return ProjectAttachment::create([
            'project_id' => $project->id, 'file_name' => $name, 'file_path' => 'project_attachments/'.Str::uuid().'/'.$name,
            'file_data' => $bytes, 'file_size' => strlen($bytes), 'file_sha256' => hash('sha256', $bytes), 'file_disk' => 'local',
        ]);
    }
}
