<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MfaService;
use App\Services\TotpService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
            $table->string('username')->nullable()->unique();
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

    public function test_activity_extends_the_session_but_background_requests_do_not(): void
    {
        $user = User::factory()->create(['role' => 'participant']);
        $this->actingAs($user)->withSession(['last_user_activity_at' => now()->timestamp]);

        $this->travel(14)->minutes();
        $this->get(route('login'))->assertOk();
        $this->postJson(route('session.activity'))->assertOk();
        $this->travel(14)->minutes();
        $this->get(route('login'))->assertOk();
        $this->travel(1)->minutes();
        $this->getJson(route('login'))->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_session_activity_is_available_during_mfa_and_password_setup(): void
    {
        $user = User::factory()->create(['role' => 'participant', 'must_change_password' => true]);
        MfaService::enable($user, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');

        $this->actingAs($user)->postJson(route('session.activity'))->assertOk();
    }

    public function test_mfa_and_recovery_attempts_share_a_five_minute_cooldown(): void
    {
        $user = User::factory()->create(['role' => 'participant']);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $codes = MfaService::enable($user, $secret);
        $this->actingAs($user);

        for ($i = 0; $i < 5; $i++) {
            $this->from(route('mfa.challenge'))->post(route('mfa.recovery.verify'), ['recovery_code' => 'invalid-code'])
                ->assertSessionHasErrors('recovery_code');
        }

        $this->from(route('mfa.challenge'))->post(route('mfa.challenge.verify'), ['code' => TotpService::code($secret)])
            ->assertSessionHasErrors('code');
        $this->assertNull(session('mfa_verified_user_id'));

        $this->travel(5)->minutes();
        $this->post(route('mfa.recovery.verify'), ['recovery_code' => $codes[0]])
            ->assertRedirect(route('participant.dashboard'));
    }

    public function test_login_requires_captcha_before_password_and_cools_down_after_five_failures(): void
    {
        config(['services.turnstile.site_key' => 'test-site', 'services.turnstile.secret_key' => 'test-secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response([
            'success' => true, 'action' => 'login', 'hostname' => '127.0.0.1',
        ])]);
        $user = User::factory()->create(['email' => 'captcha@example.test', 'password' => Hash::make('correct-password')]);

        for ($i = 0; $i < 3; $i++) {
            $this->from(route('login'))->post(route('login.submit'), ['user' => $user->email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors('login');
        }
        $this->from(route('login'))->post(route('login.submit'), ['user' => $user->email, 'password' => 'correct-password'])
            ->assertSessionHas('captcha_required', true)->assertSessionHasErrors('login');
        $this->assertGuest();

        for ($i = 0; $i < 2; $i++) {
            $this->from(route('login'))->post(route('login.submit'), [
                'user' => $user->email, 'password' => 'wrong-password', 'cf-turnstile-response' => 'test-token',
            ])->assertSessionHasErrors('login');
        }
        $this->from(route('login'))->post(route('login.submit'), ['user' => $user->email, 'password' => 'correct-password'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->travel(5)->minutes();
        $this->post(route('login.submit'), ['user' => $user->email, 'password' => 'correct-password', 'cf-turnstile-response' => 'test-token'])
            ->assertRedirect(route('participant.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_suspicious_login_fails_closed_if_captcha_cannot_be_verified(): void
    {
        config(['services.turnstile.site_key' => 'test-site', 'services.turnstile.secret_key' => 'test-secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response([
            'success' => true, 'action' => 'login', 'hostname' => 'wrong.example',
        ])]);
        $user = User::factory()->create(['email' => 'captcha-fail@example.test', 'password' => Hash::make('correct-password')]);
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('login.submit'), ['user' => $user->email, 'password' => 'incorrect']);
        }
        $this->post(route('login.submit'), [
            'user' => $user->email, 'password' => 'correct-password', 'cf-turnstile-response' => 'test-token',
        ])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_email_and_username_share_the_same_login_failure_counter(): void
    {
        $user = User::factory()->create(['username' => 'captchauser', 'email' => 'aliases@example.test', 'password' => Hash::make('correct-password')]);
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('login.submit'), ['user' => $user->email, 'password' => 'incorrect']);
        }
        $this->post(route('login.submit'), ['user' => 'captchauser', 'password' => 'correct-password'])
            ->assertSessionHas('captcha_required', true)->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_repeated_failure_cycles_escalate_cooldowns(): void
    {
        $protection = app(\App\Services\AuthenticationProtection::class);
        $key = $protection->mfaKey(987654);
        foreach ([300, 900, 3600] as $seconds) {
            for ($i = 0; $i < 5; $i++) {
                $remaining = $protection->failed($key);
            }
            $this->assertSame($seconds, $remaining);
            $this->travel($seconds)->seconds();
        }
        $protection->clear($key);
    }

    public function test_expired_session_is_invalidated_and_redirected_to_login(): void
    {
        $user = User::factory()->create(['role' => 'participant']);
        $this->actingAs($user)->withSession([
            'last_user_activity_at' => now()->subMinutes(15)->timestamp,
            'mfa_verified_user_id' => $user->id,
        ])->get(route('login'))
            ->assertRedirect(route('login', ['reason' => 'inactive']))
            ->assertSessionMissing('mfa_verified_user_id');

        $this->assertGuest();
    }

    public function test_celp_head_can_sign_in_without_mfa_and_choose_to_enroll(): void
    {
        $user = User::factory()->create([
            'email' => 'celp.head@example.test',
            'password' => Hash::make('correct-password'),
            'role' => 'celp_head',
        ]);

        $this->post(route('login.submit'), [
            'user' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('celp.head.dashboard'));

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

    public function test_celp_head_can_disable_mfa_and_sign_in_without_a_code(): void
    {
        $user = User::factory()->create([
            'email' => 'protected-disable-mfa@example.test',
            'password' => Hash::make('correct-password'),
            'role' => 'celp_head',
        ]);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        MfaService::enable($user, $secret);

        $this->actingAs($user)
            ->post(route('account.mfa.disable'), [
                'mfa_current_password' => 'correct-password',
                'mfa_code' => TotpService::code($secret),
            ])
            ->assertRedirect(route('account.security') . '#mfa');

        $this->assertNull($user->fresh()->mfa_enabled_at);
        $this->get(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.submit'), [
            'user' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('celp.head.dashboard'));
    }
}
