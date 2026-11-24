<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ParticipantIdAccountEditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('role')->default('participant');
            $table->string('id_number')->nullable();
            $table->string('participant_type')->nullable();
            $table->string('affiliation')->nullable();
            $table->string('program')->nullable();
            $table->string('employment_type')->nullable();
            $table->unsignedSmallInteger('year_graduated')->nullable();
            $table->string('org_college')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('suffix')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('org_logo')->nullable();
            $table->binary('org_logo_data')->nullable();
            $table->string('org_logo_mime')->nullable();
            $table->string('org_classification')->nullable();
            $table->text('org_description')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->boolean('is_active')->default(true);
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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        (require database_path('migrations/2026_11_23_000001_create_academic_catalog_tables.php'))->up();
        $college = DB::table('academic_departments')->insertGetId(['code' => 'COECS', 'name' => 'College of Engineering and Computer Studies']);
        $business = DB::table('academic_departments')->insertGetId(['code' => 'CBA', 'name' => 'College of Business']);
        DB::table('academic_programs')->insert([
            ['department_id' => $college, 'code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology'],
            ['department_id' => $college, 'code' => 'BSCE', 'name' => 'Bachelor of Science in Civil Engineering'],
            ['department_id' => $business, 'code' => 'BSBA', 'name' => 'Bachelor of Science in Business Administration'],
        ]);
    }

    public function test_celp_staff_can_complete_and_validate_participant_id_in_account_edit(): void
    {
        $participant = User::factory()->create([
            'role' => 'participant',
            'participant_type' => 'student',
            'id_number' => null,
        ]);
        $anotherParticipant = User::factory()->create([
            'role' => 'participant',
            'id_number' => 'STU-001',
        ]);
        $head = User::factory()->create(['role' => 'celp_head']);

        $this->actingAs($head)
            ->post(route('users.update', $participant), ['id_number' => ''])
            ->assertSessionHasErrors('id_number');
        $this->post(route('users.update', $participant), ['id_number' => $anotherParticipant->id_number])
            ->assertSessionHasErrors('id_number');

        foreach (['celp_head', 'coordinator', 'celp_secretary'] as $role) {
            $editor = User::factory()->create(['role' => $role]);

            $this->actingAs($editor)
                ->get(route('users.edit', $participant))
                ->assertOk()
                ->assertSee('Student ID No.')
                ->assertSee('name="id_number"', false)
                ->assertSee('Required for matching this participant to the attendance CSV roster.');

            $this->post(route('users.update', $participant), [
                'id_number' => '00123-A',
                'email' => $participant->email,
                'email_confirmation' => $participant->email,
                'first_name' => $participant->first_name ?: 'Participant',
            ])
                ->assertRedirect(route('users.edit', $participant))
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('users', [
                'id' => $participant->id,
                'id_number' => '00123-A',
            ]);
        }

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.participant_id_changed',
            'subject_id' => $participant->id,
        ]);
    }

    public function test_staff_can_complete_missing_academic_details_and_normalize_full_names(): void
    {
        $participant = User::factory()->create(['role' => 'participant', 'id_number' => '00123', 'first_name' => 'Student']);

        foreach (['celp_head', 'coordinator', 'celp_secretary'] as $role) {
            $editor = User::factory()->create(['role' => $role]);
            $this->actingAs($editor)->get(route('users.edit', $participant))
                ->assertOk()->assertSee('name="participant_type"', false)->assertSee('name="affiliation"', false)->assertSee('name="program"', false);
            $this->post(route('users.update', $participant), [
                'academic_details' => '1', 'participant_type' => 'student', 'id_number' => '00123',
                'affiliation' => ' college of engineering and computer studies ',
                'program' => ' bachelor of science in information technology ',
                'email' => $participant->email, 'email_confirmation' => $participant->email,
                'first_name' => 'Student',
            ])->assertSessionHasNoErrors()->assertRedirect(route('users.edit', $participant));
            $this->assertDatabaseHas('users', ['id' => $participant->id, 'participant_type' => 'student', 'affiliation' => 'COECS', 'program' => 'BSIT']);
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.academic_details_changed', 'subject_id' => $participant->id]);
    }

    public function test_celp_staff_can_correct_a_saved_program_and_reload_it_without_changing_other_details(): void
    {
        $participant = User::factory()->create([
            'role' => 'participant', 'id_number' => '00123', 'participant_type' => 'student',
            'affiliation' => 'COECS', 'program' => 'BSIT', 'name' => 'Program Student',
            'first_name' => 'Program', 'last_name' => 'Student',
            'organization_name' => 'Legacy organization value', 'org_description' => 'Retain existing information',
        ]);
        $email = $participant->email;
        $verifiedAt = $participant->email_verified_at;

        foreach ([['celp_head', 'COECS', 'BSCE'], ['coordinator', 'CBA', 'BSBA'], ['celp_secretary', 'COECS', 'BSIT']] as [$role, $college, $program]) {
            $editor = User::factory()->create(['role' => $role]);
            $this->actingAs($editor)->post(route('users.update', $participant), [
                'academic_details' => '1', 'id_number' => '00123', 'participant_type' => 'student',
                'affiliation' => $college, 'program' => $program,
                'first_name' => 'Program', 'last_name' => 'Student', 'email' => $email,
            ])->assertSessionHasNoErrors()->assertRedirect(route('users.edit', $participant));

            $this->assertDatabaseHas('users', [
                'id' => $participant->id, 'program' => $program, 'affiliation' => $college,
                'id_number' => '00123', 'name' => 'Program Student', 'email' => $email,
                'role' => 'participant', 'organization_name' => 'Legacy organization value',
                'org_description' => 'Retain existing information',
            ]);
            $this->assertTrue($participant->fresh()->email_verified_at->equalTo($verifiedAt));
            $page = $this->get(route('users.edit', $participant))->assertOk();
            $document = new \DOMDocument();
            @$document->loadHTML($page->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame($program, $xpath->evaluate('string(//select[@id="program"]/option[@selected]/@value)'));
        }
    }

    public function test_program_must_belong_to_the_selected_college_and_student_details_are_required(): void
    {
        $participant = User::factory()->create(['role' => 'participant', 'id_number' => '00123']);
        $editor = User::factory()->create(['role' => 'coordinator']);
        $this->actingAs($editor)->post(route('users.update', $participant), [
            'id_number' => '00123', 'participant_type' => 'student', 'affiliation' => 'CBA', 'program' => 'BSIT',
        ])->assertSessionHasErrors('program');
        $this->post(route('users.update', $participant), [
            'id_number' => '00123', 'participant_type' => 'student', 'affiliation' => 'COECS',
        ])->assertSessionHasErrors('program');
        $this->post(route('users.update', $participant), [
            'id_number' => '00123', 'participant_type' => 'student', 'affiliation' => ['COECS'], 'program' => 'BSIT',
        ])->assertSessionHasErrors('affiliation');
        $this->assertNull($participant->fresh()->program);
    }

    public function test_employee_and_alumni_details_follow_the_selected_type(): void
    {
        $participant = User::factory()->create(['role' => 'participant', 'id_number' => '00123', 'participant_type' => 'student', 'program' => 'BSIT', 'affiliation' => 'COECS']);
        $editor = User::factory()->create(['role' => 'celp_secretary']);
        $this->actingAs($editor)->post(route('users.update', $participant), [
            'id_number' => '00123', 'participant_type' => 'employee', 'affiliation' => 'COECS',
        ])->assertSessionHasErrors('employment_type');
        $this->post(route('users.update', $participant), [
            'id_number' => '00123', 'participant_type' => 'employee', 'affiliation' => 'COECS', 'employment_type' => 'teaching', 'program' => 'obsolete hidden input',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $participant->id, 'participant_type' => 'employee', 'program' => null, 'employment_type' => 'teaching']);
        $this->post(route('users.update', $participant), [
            'id_number' => '00123', 'participant_type' => 'alumni', 'affiliation' => 'COECS', 'program' => 'BSCE', 'year_graduated' => now()->year + 1,
        ])->assertSessionHasErrors('year_graduated');
        $this->post(route('users.update', $participant), [
            'id_number' => '00123', 'participant_type' => 'alumni', 'affiliation' => 'COECS', 'program' => 'BSCE', 'year_graduated' => 2020,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $participant->id, 'participant_type' => 'alumni', 'program' => 'BSCE', 'employment_type' => null, 'year_graduated' => 2020]);
    }

    public function test_participants_cannot_modify_their_own_academic_details(): void
    {
        $participant = User::factory()->create(['role' => 'participant', 'id_number' => '00123']);
        $this->actingAs($participant)->get(route('users.edit', $participant))->assertOk()->assertDontSee('name="participant_type"', false);
        $this->post(route('users.update', $participant), [
            'id_number' => '00123', 'participant_type' => 'student', 'affiliation' => 'COECS', 'program' => 'BSIT',
        ])->assertForbidden();
        $this->assertNull($participant->fresh()->affiliation);
    }

    public function test_catalog_is_staff_managed_and_inactive_programs_cannot_be_newly_assigned(): void
    {
        $editor = User::factory()->create(['role' => 'coordinator']);
        $this->actingAs($editor)->get(route('academic-catalog.index'))->assertOk()->assertSee('Colleges &amp; programs', false);
        $this->post(route('academic-catalog.store', 'departments'), ['code' => ' cas ', 'name' => 'College of Arts and Sciences'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('academic_departments', ['code' => 'CAS']);
        $departmentId = DB::table('academic_departments')->where('code', 'CAS')->value('id');
        $this->post(route('academic-catalog.store', 'programs'), ['code' => 'bsbio', 'name' => 'BS Biology', 'department_id' => $departmentId])->assertSessionHasNoErrors();
        $programId = DB::table('academic_programs')->where('code', 'BSBIO')->value('id');
        $this->put(route('academic-catalog.update', ['programs', $programId]), ['name' => 'Bachelor of Science in Biology', 'is_active' => '0'])->assertSessionHasNoErrors();
        $this->post(route('academic-catalog.store', 'departments'), ['code' => 'cas', 'name' => 'Duplicate College'])->assertSessionHasErrors('code');
        $participant = User::factory()->create(['role' => 'participant', 'id_number' => '00123']);
        $this->post(route('users.update', $participant), ['id_number' => '00123', 'participant_type' => 'student', 'affiliation' => 'CAS', 'program' => 'BSBIO'])->assertSessionHasErrors('program');
        foreach (['participant', 'student_org', 'college_department', 'csa_director'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('academic-catalog.index'))->assertForbidden();
            $this->post(route('academic-catalog.store', 'departments'), ['code' => 'BAD', 'name' => 'Unauthorized'])->assertForbidden();
            $this->put(route('academic-catalog.update', ['programs', $programId]), ['name' => 'Unauthorized', 'is_active' => '1'])->assertForbidden();
        }
        $this->assertDatabaseHas('academic_programs', ['id' => $programId, 'code' => 'BSBIO', 'is_active' => false]);
    }

    public function test_staff_can_archive_catalog_entries_without_deleting_records(): void
    {
        $editor = User::factory()->create(['role' => 'coordinator']);
        $participant = User::factory()->create([
            'role' => 'participant',
            'participant_type' => 'student',
            'affiliation' => 'COECS',
            'program' => 'BSIT',
        ]);
        $collegeId = DB::table('academic_departments')->where('code', 'COECS')->value('id');
        $programId = DB::table('academic_programs')->where('code', 'BSIT')->value('id');

        $this->actingAs(User::factory()->create(['role' => 'participant']))
            ->delete(route('academic-catalog.destroy', ['programs', $programId]))
            ->assertForbidden();

        $this->actingAs($editor)
            ->delete(route('academic-catalog.destroy', ['departments', $collegeId]))
            ->assertSessionHasErrors('catalog');
        $this->assertDatabaseHas('academic_departments', ['id' => $collegeId, 'is_active' => true]);

        $this->actingAs($editor)
            ->delete(route('academic-catalog.destroy', ['programs', $programId]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('academic-catalog.index', ['tab' => 'programs']));
        $this->assertDatabaseHas('academic_programs', ['id' => $programId, 'is_active' => false]);
        $this->assertDatabaseHas('academic_programs', ['id' => $programId, 'code' => 'BSIT']);
        $this->assertSame('BSIT', $participant->fresh()->program);
        $this->assertDatabaseHas('audit_logs', ['action' => 'academic_catalog.archived']);

        DB::table('academic_programs')->where('department_id', $collegeId)->update(['is_active' => false]);
        $this->actingAs($editor)
            ->delete(route('academic-catalog.destroy', ['departments', $collegeId]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('academic-catalog.index', ['tab' => 'departments']));
        $this->assertDatabaseHas('academic_departments', ['id' => $collegeId, 'is_active' => false]);
    }

    public function test_existing_inactive_and_legacy_values_can_be_preserved(): void
    {
        DB::table('academic_programs')->where('code', 'BSIT')->update(['is_active' => false]);
        $participant = User::factory()->create(['role' => 'participant', 'id_number' => '00123', 'participant_type' => 'student', 'affiliation' => 'COECS', 'program' => 'BSIT']);
        $editor = User::factory()->create(['role' => 'coordinator']);
        $this->actingAs($editor)->post(route('users.update', $participant), ['id_number' => '00123', 'participant_type' => 'student', 'affiliation' => 'COECS', 'program' => 'BSIT'])->assertSessionHasNoErrors();
        $this->assertSame('BSIT', $participant->fresh()->program);
    }

    public function test_catalog_availability_is_saved_and_validation_keeps_the_correct_editor_context(): void
    {
        $editor = User::factory()->create(['role' => 'coordinator']);
        $this->actingAs($editor)->post(route('academic-catalog.store', 'departments'), [
            'code' => 'COL', 'name' => 'College of Law', 'is_active' => '0',
        ])->assertSessionHasNoErrors()->assertRedirect(route('academic-catalog.index', ['tab' => 'departments']));
        $this->assertDatabaseHas('academic_departments', ['code' => 'COL', 'is_active' => false]);
        $this->post(route('academic-catalog.store', 'departments'), [
            'code' => 'INVALID', 'name' => 'Invalid availability', 'is_active' => 'other',
        ])->assertSessionHasErrors('is_active');
        $this->assertDatabaseMissing('academic_departments', ['code' => 'INVALID']);

        $programId = DB::table('academic_programs')->where('code', 'BSIT')->value('id');
        $pageUrl = route('academic-catalog.index', ['tab' => 'programs']);
        $this->from($pageUrl)->put(route('academic-catalog.update', ['programs', $programId]), [
            '_catalog_kind' => 'programs', '_catalog_id' => (string) $programId,
            'code' => 'CHANGED', 'name' => 'Bachelor of Science in Civil Engineering', 'is_active' => '0',
        ])->assertSessionHasErrors('name')->assertRedirect($pageUrl);
        $page = $this->get($pageUrl)->assertOk();
        $document = new \DOMDocument();
        @$document->loadHTML($page->getContent());
        $xpath = new \DOMXPath($document);
        $data = json_decode($xpath->evaluate('string(//script[@data-catalog-data])'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('programs', $data['oldInput']['kind']);
        $this->assertSame((string) $programId, $data['oldInput']['id']);
        $this->assertSame('Bachelor of Science in Civil Engineering', $data['oldInput']['name']);
        $this->assertSame('0', $data['oldInput']['is_active']);
        $this->assertDatabaseHas('academic_programs', ['id' => $programId, 'code' => 'BSIT', 'is_active' => true]);
    }

    public function test_account_creation_uses_the_same_catalog_validation_and_normalization(): void
    {
        Mail::fake();
        $editor = User::factory()->create(['role' => 'coordinator']);
        $payload = [
            'username' => 'catalog.student', 'firstname' => 'Catalog', 'lastname' => 'Student',
            'email' => 'catalog.student@example.com', 'email_confirmation' => 'catalog.student@example.com',
            'role' => 'participant', 'participant_type' => 'student', 'id_number' => '000765',
            'affiliation' => 'COECS', 'program' => 'BSBA',
        ];
        $mismatchedEmailPayload = $payload;
        $mismatchedEmailPayload['program'] = 'BSIT';
        $mismatchedEmailPayload['email_confirmation'] = 'different@example.com';
        $this->actingAs($editor)
            ->post(route('users.store'), $mismatchedEmailPayload)
            ->assertSessionHasErrors('email');
        $this->actingAs($editor)->post(route('users.store'), $payload)->assertSessionHasErrors('program');
        $payload['affiliation'] = ' college of engineering and computer studies ';
        $payload['program'] = ' bachelor of science in information technology ';
        $this->post(route('users.store'), $payload)->assertSessionHasNoErrors()->assertRedirect(route('users.created'));
        $this->assertDatabaseHas('users', ['username' => 'catalog.student', 'participant_type' => 'student', 'affiliation' => 'COECS', 'program' => 'BSIT', 'id_number' => '000765']);
        Mail::assertSent(\App\Mail\AccountCreated::class);
    }
}
