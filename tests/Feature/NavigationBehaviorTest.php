<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NavigationBehaviorTest extends TestCase
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
            $table->string('role');
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
            $table->string('status');
        });

        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('file_path');
            $table->foreignId('uploaded_by');
            $table->string('source')->nullable();
            $table->timestamps();
        });
    }

    public function test_navigation_and_document_actions_stay_in_the_current_tab_for_every_role(): void
    {
        foreach (['participant', 'student_org', 'college_department', 'celp_secretary', 'coordinator', 'celp_head', 'csa_director'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $template = Template::create([
                'original_name' => 'Requirements & forms.pdf',
                'file_path' => 'templates/requirements.pdf',
                'uploaded_by' => $user->id,
                'source' => 'celp',
            ]);

            $response = $this->actingAs($user)->followingRedirects()->get(route('templates.index'))->assertOk();
            $xpath = $this->document((string) $response->getContent());
            $navigation = $xpath->query('//nav[contains(concat(" ", normalize-space(@class), " "), " nav ")]//a');
            $this->assertGreaterThan(3, $navigation->length, $role);

            // Normal anchors and forms retain native navigation and history.
            foreach ($xpath->query('//a | //form | //button') as $element) {
                $this->assertContains($element->getAttribute('target'), ['', '_self'], $role);
                $this->assertContains($element->getAttribute('formtarget'), ['', '_self'], $role);
            }

            $preview = $xpath->query('//a[@data-file-preview and @href="'.route('templates.preview', $template).'"]');
            $this->assertSame(1, $preview->length, $role);
            $this->assertSame('Requirements & forms.pdf', $preview->item(0)->getAttribute('data-preview-name'));
            $this->assertSame(route('templates.preview.content', $template), $preview->item(0)->getAttribute('data-preview-url'));
            $this->assertSame(route('templates.download', $template), $preview->item(0)->getAttribute('data-download-url'));
            $this->assertSame(1, $xpath->query('//a[@href="'.route('templates.download', $template).'" and not(@data-file-preview)]')->length);
            $this->assertSame(1, $xpath->query('//script[@type="module" and contains(@src, "proposal-upload-preview")]')->length);
        }
    }

    public function test_template_management_keeps_preview_and_download_as_separate_actions(): void
    {
        $user = User::factory()->create(['role' => 'celp_head']);
        $template = Template::create([
            'original_name' => 'requirements.docx',
            'file_path' => 'templates/requirements.docx',
            'uploaded_by' => $user->id,
            'source' => 'celp',
        ]);

        $response = $this->actingAs($user)->get(route('templates.manage'))->assertOk();
        $xpath = $this->document((string) $response->getContent());

        $this->assertSame(1, $xpath->query('//a[@data-file-preview and @data-preview-url="'.route('templates.preview.content', $template).'"]')->length);
        $this->assertSame(1, $xpath->query('//a[@href="'.route('templates.download', $template).'" and not(@data-file-preview)]')->length);
        $this->assertSame(0, $xpath->query('//form[@target and @target!="_self"] | //button[@formtarget and @formtarget!="_self"]')->length);
    }

    private function document(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8">'.$html);

            return new DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
