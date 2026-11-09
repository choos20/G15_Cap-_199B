<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountEmailChangeTest extends TestCase
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
            $table->string('id_number')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('suffix')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('org_logo')->nullable();
            $table->text('org_description')->nullable();
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

        Mail::fake();
    }

    public function test_csa_director_can_change_their_student_organization_email_and_it_requires_verification(): void
    {
        $director = User::factory()->create(['role' => 'csa_director']);
        $organization = User::factory()->create([
            'role' => 'student_org',
            'created_by' => $director->id,
            'email_verified_at' => now(),
            'first_name' => 'Outgoing',
            'middle_name' => null,
            'last_name' => 'President',
            'suffix' => null,
            'name' => 'Outgoing President',
        ]);

        $this->actingAs($director)
            ->post(route('users.update', $organization), [
                'first_name' => 'Incoming',
                'middle_name' => 'M',
                'last_name' => 'President',
                'suffix' => 'Jr.',
                'email' => 'incoming.president@gmail.com',
                'email_confirmation' => 'incoming.president@gmail.com',
            ])
            ->assertRedirect(route('users.edit', $organization))
            ->assertSessionHas('status', 'Account name and email updated. The account holder must verify the new address at their next sign-in.');

        $this->assertDatabaseHas('users', [
            'id' => $organization->id,
            'email' => 'incoming.president@gmail.com',
            'email_verified_at' => null,
            'name' => 'Incoming M President Jr.',
            'first_name' => 'Incoming',
            'middle_name' => 'M',
            'last_name' => 'President',
            'suffix' => 'Jr.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.email_changed',
            'subject_id' => $organization->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.name_changed',
            'subject_id' => $organization->id,
        ]);
    }

    public function test_celp_head_can_change_a_participant_email(): void
    {
        $head = User::factory()->create(['role' => 'celp_head']);
        $participant = User::factory()->create(['role' => 'participant']);

        $this->actingAs($head)
            ->post(route('users.update', $participant), [
                'first_name' => 'Updated',
                'middle_name' => null,
                'last_name' => 'Participant',
                'suffix' => null,
                'email' => 'new.participant@gmail.com',
                'email_confirmation' => 'new.participant@gmail.com',
            ])
            ->assertRedirect(route('users.edit', $participant));

        $this->assertDatabaseHas('users', [
            'id' => $participant->id,
            'email' => 'new.participant@gmail.com',
            'email_verified_at' => null,
            'name' => 'Updated Participant',
        ]);
    }

    public function test_other_editors_cannot_change_an_email(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $participant = User::factory()->create(['role' => 'participant']);
        $originalEmail = $participant->email;

        $this->actingAs($coordinator)
            ->get(route('users.edit', $participant))
            ->assertOk()
            ->assertDontSee('name="email"', false);

        $this->actingAs($coordinator)
            ->post(route('users.update', $participant), [
                'email' => 'unauthorized.change@gmail.com',
                'email_confirmation' => 'unauthorized.change@gmail.com',
            ])
            ->assertRedirect(route('users.edit', $participant));

        $this->assertDatabaseHas('users', [
            'id' => $participant->id,
            'email' => $originalEmail,
        ]);
    }

}
