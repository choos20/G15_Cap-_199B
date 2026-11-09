<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationFiltersTest extends TestCase
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
            $table->string('organization_name')->nullable();
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

    public function test_notifications_can_be_filtered_by_read_status_and_searched(): void
    {
        $user = User::factory()->create(['role' => 'participant']);
        $this->createNotification($user, 'Project approved', 'Your project has been approved.');
        $this->createNotification($user, 'Activity reminder', 'The event starts tomorrow.', now());

        $this->actingAs($user)
            ->get(route('notifications.index', ['filter' => 'unread']))
            ->assertOk()
            ->assertViewHas('notifications', function ($notifications) {
                return $notifications->count() === 1
                    && $notifications->first()->data['title'] === 'Project approved';
            });

        $this->actingAs($user)
            ->get(route('notifications.index', ['filter' => 'read']))
            ->assertOk()
            ->assertViewHas('notifications', function ($notifications) {
                return $notifications->count() === 1
                    && $notifications->first()->data['title'] === 'Activity reminder';
            });

        $this->actingAs($user)
            ->get(route('notifications.index', ['search' => 'tomorrow']))
            ->assertOk()
            ->assertViewHas('notifications', function ($notifications) {
                return $notifications->count() === 1
                    && $notifications->first()->data['title'] === 'Activity reminder';
            });

        $this->actingAs($user)
            ->get(route('notifications.index', ['search' => 'PROJECT']))
            ->assertOk()
            ->assertViewHas('notifications', function ($notifications) {
                return $notifications->count() === 1
                    && $notifications->first()->data['title'] === 'Project approved';
            });
    }

    private function createNotification(User $user, string $title, string $body, $readAt = null): void
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'project.update',
            'data' => ['title' => $title, 'body' => $body],
            'read_at' => $readAt,
        ]);
    }
}
