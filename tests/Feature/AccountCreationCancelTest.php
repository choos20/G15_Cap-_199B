<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountCreationCancelTest extends TestCase
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
            $table->string('profile_photo_path')->nullable();
            $table->boolean('must_change_password')->default(false);
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
    }

    public function test_csa_director_must_confirm_before_leaving_account_creation(): void
    {
        $director = User::factory()->create(['role' => 'csa_director']);

        $this->actingAs($director)
            ->get(route('users.create'))
            ->assertOk()
            ->assertSee('href="'.route('csa.director.dashboard').'" class="cu-btn-secondary"', false)
            ->assertSee('data-confirm-title="Cancel account creation?"', false)
            ->assertSee('data-confirm-body="Any information you entered will be lost."', false)
            ->assertSee('data-confirm-ok="Leave page"', false)
            ->assertSee('data-confirm-cancel="Keep creating"', false)
            ->assertSee('data-confirm-navigation', false)
            ->assertSee('data-confirm-title="Leave account creation?"', false)
            ->assertSee('data-confirm-body="You have unsaved changes. If you leave now, they will be lost."', false);

        foreach (['celp_head', 'coordinator', 'celp_secretary'] as $role) {
            $creator = User::factory()->create(['role' => $role]);

            $this->actingAs($creator)
                ->get(route('users.create'))
                ->assertOk()
                ->assertSee('data-confirm-navigation', false)
                ->assertSee('data-confirm-title="Leave account creation?"', false)
                ->assertSee('data-confirm-body="You have unsaved changes. If you leave now, they will be lost."', false);
        }
    }
}
