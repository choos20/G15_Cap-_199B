<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DocumentFixtures;
use Tests\TestCase;

class TemplateManagementAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

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

        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('file_path');
            $table->binary('file_data')->nullable();
            $table->string('file_mime')->nullable();
            $table->foreignId('uploaded_by');
            $table->string('source')->nullable();
            $table->timestamps();
        });
    }

    public function test_managers_can_only_replace_or_remove_their_own_templates_except_celp_head(): void
    {
        Storage::fake('public');

        $csaDirector = User::factory()->create(['role' => 'csa_director']);
        $secretary = User::factory()->create(['role' => 'celp_secretary']);
        $celpTemplate = $this->templateUploadedBy($secretary);
        $csaTemplate = $this->templateUploadedBy($csaDirector);
        Storage::disk('public')->put($celpTemplate->file_path, 'old CELP template');
        Storage::disk('public')->put($csaTemplate->file_path, 'old CSA template');

        $this->actingAs($csaDirector)
            ->get(route('templates.manage'))
            ->assertOk()
            ->assertSee(route('templates.download', $celpTemplate), false)
            ->assertDontSee('action="'.route('templates.replace', $celpTemplate).'"', false)
            ->assertDontSee('action="'.route('templates.destroy', $celpTemplate).'"', false)
            ->assertSee('action="'.route('templates.replace', $csaTemplate).'"', false)
            ->assertSee('action="'.route('templates.destroy', $csaTemplate).'"', false);

        $this->post(route('templates.replace', $celpTemplate))
            ->assertForbidden();
        $this->delete(route('templates.destroy', $celpTemplate))
            ->assertForbidden();

        $head = User::factory()->create(['role' => 'celp_head']);
        $this->actingAs($head)
            ->get(route('templates.manage'))
            ->assertOk()
            ->assertSee('action="'.route('templates.replace', $celpTemplate).'"', false)
            ->assertSee('action="'.route('templates.destroy', $celpTemplate).'"', false)
            ->assertSee('action="'.route('templates.replace', $csaTemplate).'"', false)
            ->assertSee('action="'.route('templates.destroy', $csaTemplate).'"', false);

        $this->post(route('templates.replace', $celpTemplate), [
            'template' => UploadedFile::fake()->createWithContent(
                'updated-requirements.pdf',
                "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
            ),
        ])->assertRedirect(route('templates.manage'));

        $this->assertDatabaseHas('templates', [
            'id' => $celpTemplate->id,
            'uploaded_by' => $secretary->id,
            'source' => 'celp',
            'original_name' => 'updated-requirements.pdf',
        ]);

        $this->delete(route('templates.destroy', $csaTemplate))
            ->assertRedirect(route('templates.manage'));

        $this->assertDatabaseMissing('templates', ['id' => $csaTemplate->id]);
    }

    public function test_preview_and_download_share_authenticated_read_access(): void
    {
        Storage::fake('public');

        $participant = User::factory()->create(['role' => 'participant']);
        $template = Template::create([
            'original_name' => 'celp-requirements.docx',
            'file_path' => 'templates/celp-requirements.docx',
            'uploaded_by' => $participant->id,
            'source' => 'celp',
        ]);
        Storage::disk('public')->put($template->file_path, DocumentFixtures::docx());

        $this->actingAs($participant)
            ->get(route('templates.preview', $template))
            ->assertOk()
            ->assertSee('Loading document preview...')
            ->assertSee('Uploaded by')
            ->assertSee('Responsible office');

        $this->get(route('templates.preview.content', $template))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertHeader('Content-Disposition', 'inline; filename=celp-requirements.docx');

        $this->get(route('templates.download', $template))
            ->assertOk();
    }

    public function test_guests_cannot_preview_or_download_templates(): void
    {
        $template = Template::create([
            'original_name' => 'guest-check.pdf',
            'file_path' => 'templates/guest-check.pdf',
            'uploaded_by' => User::factory()->create()->id,
            'source' => 'celp',
        ]);

        $this->get(route('templates.preview', $template))->assertRedirect(route('login'));
        $this->get(route('templates.preview.content', $template))->assertRedirect(route('login'));
        $this->get(route('templates.download', $template))->assertRedirect(route('login'));
    }

    private function templateUploadedBy(User $uploader): Template
    {
        return Template::create([
            'original_name' => 'requirements-'.$uploader->id.'.pdf',
            'file_path' => 'templates/requirements-'.$uploader->id.'.pdf',
            'uploaded_by' => $uploader->id,
            'source' => $uploader->role === 'csa_director' ? 'csa' : 'celp',
        ]);
    }
}
