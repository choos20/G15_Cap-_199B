<?php

namespace Tests\Feature;

use App\Models\LoginActivity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoginActivityTest extends TestCase
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
            $table->timestamp('mfa_enabled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('login_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('user_role')->nullable();
            $table->string('status');
            $table->text('ip_address')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->string('location_label')->nullable();
            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            $table->string('operating_system')->nullable();
            $table->boolean('mfa_used')->default(false);
            $table->boolean('is_suspicious')->default(false);
            $table->string('failure_reason')->nullable();
            $table->string('suspicious_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function test_a_user_can_only_see_their_own_login_activity(): void
    {
        $user = User::factory()->create(['role' => 'participant']);
        $other = User::factory()->create(['name' => 'Private User', 'role' => 'participant']);

        LoginActivity::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'status' => LoginActivity::STATUS_SUCCESS,
            'location_label' => 'PH',
            'device_type' => 'Desktop',
            'browser' => 'Chrome',
            'operating_system' => 'Windows',
        ]);
        LoginActivity::create([
            'user_id' => $other->id,
            'user_name' => $other->name,
            'user_email' => $other->email,
            'user_role' => $other->role,
            'status' => LoginActivity::STATUS_SUCCESS,
            'location_label' => 'PH',
        ]);

        $this->actingAs($user)
            ->get(route('account.login_activity'))
            ->assertOk()
            ->assertSee($user->name)
            ->assertDontSee($other->name);
    }

    public function test_celp_head_can_review_staff_login_activity_but_not_participant_activity(): void
    {
        $head = User::factory()->create(['role' => 'celp_head', 'name' => 'CELP Head']);
        $staff = User::factory()->create(['role' => 'celp_secretary', 'name' => 'CELP Secretary']);
        $participant = User::factory()->create(['role' => 'participant', 'name' => 'Participant Private']);

        foreach ([$staff, $participant] as $account) {
            LoginActivity::create([
                'user_id' => $account->id,
                'user_name' => $account->name,
                'user_email' => $account->email,
                'user_role' => $account->role,
                'status' => LoginActivity::STATUS_SUCCESS,
            ]);
        }

        $this->actingAs($head)
            ->get(route('login-audit.index'))
            ->assertOk()
            ->assertSee($staff->name)
            ->assertDontSee($participant->name);
    }

    public function test_participant_cannot_open_the_administrative_login_audit(): void
    {
        $participant = User::factory()->create(['role' => 'participant']);

        $this->actingAs($participant)
            ->get(route('login-audit.index'))
            ->assertForbidden();
    }

    public function test_protected_user_can_view_personal_security_pages_before_mfa_enrollment(): void
    {
        $head = User::factory()->create(['role' => 'celp_head']);

        $this->be($head)
            ->get(route('account.security'))
            ->assertOk();

        $this->be($head)
            ->get(route('account.login_activity'))
            ->assertOk();
    }

    public function test_security_page_combines_mfa_password_and_login_activity_sections(): void
    {
        $user = User::factory()->create(['role' => 'participant']);

        $this->actingAs($user)
            ->get(route('account.security'))
            ->assertOk()
            ->assertSee('Sign-in protection')
            ->assertSee('Change password')
            ->assertSee('Recent login activity')
            ->assertDontSee('Security settings</a>')
            ->assertDontSee('Login activity</a>');
    }

    public function test_a_successful_login_creates_a_private_activity_record(): void
    {
        $user = User::factory()->create([
            'email' => 'participant@example.test',
            'password' => Hash::make('correct-password'),
            'role' => 'participant',
        ]);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0')
            ->post(route('login.submit'), [
                'user' => $user->email,
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('participant.dashboard'));

        $this->assertDatabaseHas('login_activities', [
            'user_id' => $user->id,
            'status' => LoginActivity::STATUS_SUCCESS,
            'device_type' => 'Desktop',
            'browser' => 'Chrome',
            'operating_system' => 'Windows',
            'mfa_used' => false,
        ]);
    }

    public function test_a_successful_login_records_an_ip_based_location_when_the_client_ip_is_forwarded(): void
    {
        Http::fake([
            'https://ipwho.is/*' => Http::response([
                'success' => true,
                'city' => 'Manila',
                'country' => 'Philippines',
                'country_code' => 'PH',
            ]),
        ]);

        $user = User::factory()->create([
            'email' => 'location@example.test',
            'password' => Hash::make('correct-password'),
            'role' => 'participant',
        ]);

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0',
            'X-Forwarded-For' => '8.8.8.8',
        ])->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.5',
        ])->post(route('login.submit'), [
            'user' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('participant.dashboard'));

        $this->assertDatabaseHas('login_activities', [
            'user_id' => $user->id,
            'location_label' => 'Manila, Philippines',
        ]);

        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://ipwho.is/8.8.8.8');
        });
    }

    public function test_login_activity_displays_utc_timestamps_in_philippine_time(): void
    {
        $user = User::factory()->create(['role' => 'participant']);
        $timestamp = Carbon::create(2026, 11, 17, 23, 11, 0, 'UTC');

        $activity = LoginActivity::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'status' => LoginActivity::STATUS_SUCCESS,
        ]);
        $activity->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->saveQuietly();

        $this->actingAs($user)
            ->get(route('account.login_activity'))
            ->assertOk()
            ->assertSee('Nov 18, 2026')
            ->assertSee('7:11 AM PHT');
    }
}
