<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MfaService;
use App\Services\TotpService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MfaFlowTest extends TestCase
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
            $table->text('mfa_secret')->nullable();
            $table->timestamp('mfa_enabled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mfa_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('code_hash');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'used_at']);
        });
    }

    public function test_celp_head_must_enroll_before_reaching_the_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'celp.head@example.test',
            'password' => Hash::make('correct-password'),
            'role' => 'celp_head',
        ]);

        $this->post(route('login.submit'), [
            'user' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('mfa.enroll'));

        $this->get(route('celp.head.dashboard'))
            ->assertRedirect(route('mfa.enroll'));

        $this->get(route('mfa.enroll'))
            ->assertOk()
            ->assertSee('SafeAuth')
            ->assertSee('manual setup key');

        $secret = session('mfa_setup_secret');
        $code = TotpService::code($secret);

        $this->post(route('mfa.enroll.verify'), ['code' => $code])
            ->assertOk()
            ->assertSee('Save your recovery codes');

        $this->assertNotNull($user->fresh()->mfa_enabled_at);
        $this->assertDatabaseCount('mfa_recovery_codes', 10);
        $this->assertSame($user->id, (int) session('mfa_verified_user_id'));
    }

    public function test_csa_director_must_complete_mfa_on_each_new_login(): void
    {
        $user = User::factory()->create([
            'email' => 'csa.director@example.test',
            'password' => Hash::make('correct-password'),
            'role' => 'csa_director',
        ]);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        MfaService::enable($user, $secret);

        $this->post(route('login.submit'), [
            'user' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('mfa.challenge'));

        $this->get(route('csa.director.dashboard'))
            ->assertRedirect(route('mfa.challenge'));

        $this->post(route('mfa.challenge.verify'), [
            'code' => TotpService::code($secret),
        ])->assertRedirect(route('csa.director.dashboard'));

        $this->assertSame($user->id, (int) session('mfa_verified_user_id'));
    }

    public function test_recovery_codes_are_single_use(): void
    {
        $user = User::factory()->create([
            'email' => 'recovery@example.test',
            'password' => Hash::make('correct-password'),
            'role' => 'celp_head',
        ]);
        $codes = MfaService::enable($user, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');

        $this->post(route('login.submit'), [
            'user' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('mfa.challenge'));

        $this->post(route('mfa.recovery.verify'), [
            'recovery_code' => $codes[0],
        ])->assertRedirect(route('celp.head.dashboard'));

        $this->get(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.submit'), [
            'user' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('mfa.challenge'));

        $this->from(route('mfa.challenge'))
            ->post(route('mfa.recovery.verify'), [
                'recovery_code' => $codes[0],
            ])
            ->assertRedirect(route('mfa.challenge'))
            ->assertSessionHasErrors('recovery_code');
    }

    public function test_an_optional_user_can_enable_mfa_from_the_security_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'participant-mfa@example.test',
            'password' => Hash::make('correct-password'),
            'role' => 'participant',
        ]);

        $this->actingAs($user)
            ->get(route('mfa.enroll'))
            ->assertOk()
            ->assertSee('SafeAuth');

        $secret = session('mfa_setup_secret');

        $this->post(route('mfa.enroll.verify'), [
            'code' => TotpService::code($secret),
        ])->assertOk()->assertSee('Save your recovery codes');

        $this->assertNotNull($user->fresh()->mfa_enabled_at);
        $this->assertDatabaseCount('mfa_recovery_codes', 10);
    }

    public function test_an_optional_user_must_verify_password_and_totp_to_disable_mfa(): void
    {
        $user = User::factory()->create([
            'email' => 'participant-disable-mfa@example.test',
            'password' => Hash::make('correct-password'),
            'role' => 'participant',
        ]);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        MfaService::enable($user, $secret);

        $this->actingAs($user)
            ->post(route('account.mfa.disable'), [
                'mfa_current_password' => 'correct-password',
                'mfa_code' => TotpService::code($secret),
            ])
            ->assertRedirect(route('account.security') . '#mfa')
            ->assertSessionHas('status', 'MFA has been disabled for this account.');

        $this->assertNull($user->fresh()->mfa_enabled_at);
        $this->assertNull($user->fresh()->mfa_secret);
        $this->assertDatabaseCount('mfa_recovery_codes', 0);
    }

    public function test_enabled_optional_mfa_is_required_on_the_next_login(): void
    {
        $user = User::factory()->create([
            'email' => 'participant-login-mfa@example.test',
            'password' => Hash::make('correct-password'),
            'role' => 'participant',
        ]);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        MfaService::enable($user, $secret);

        $this->post(route('login.submit'), [
            'user' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('mfa.challenge'));

        $this->get(route('participant.dashboard'))
            ->assertRedirect(route('mfa.challenge'));

        $this->post(route('mfa.challenge.verify'), [
            'code' => TotpService::code($secret),
        ])->assertRedirect(route('participant.dashboard'));
    }

    public function test_a_protected_role_cannot_disable_required_mfa(): void
    {
        $user = User::factory()->create([
            'email' => 'protected-disable-mfa@example.test',
            'password' => Hash::make('correct-password'),
            'role' => 'celp_head',
        ]);
        MfaService::enable($user, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');

        $this->actingAs($user)
            ->post(route('account.mfa.disable'), [
                'mfa_current_password' => 'correct-password',
                'mfa_code' => '123456',
            ])
            ->assertForbidden();

        $this->assertNotNull($user->fresh()->mfa_enabled_at);
    }
}
