<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('suffix')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('org_logo')->nullable();
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
}
