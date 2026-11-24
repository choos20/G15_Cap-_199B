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

    public function test_notification_source_falls_back_when_actor_has_been_removed(): void
    {
        $viewer = User::factory()->create(['role' => 'participant']);
        $actor = User::factory()->create(['role' => 'celp_secretary']);
        $actorNotification = $this->createNotification(
            $viewer,
            'Project approved',
            'Your project has been approved.',
            null,
            ['actor_id' => $actor->id, 'actor_type' => 'user']
        );
        $removedActorNotification = $this->createNotification(
            $viewer,
            'Review completed',
            'The review was completed by an account that is no longer available.',
            null,
            ['actor_id' => 999999, 'actor_type' => 'user']
        );

        $this->actingAs($viewer)
            ->get(route('notifications.index'))
            ->assertViewHas('notificationSources', function (array $sources) use ($actor, $actorNotification, $removedActorNotification) {
                $actorSource = $sources[(string) $actorNotification->id] ?? [];
                $removedSource = $sources[(string) $removedActorNotification->id] ?? [];

                return $actorSource['label'] === $actor->name
                    && $actorSource['fallback'] === false
                    && $removedSource['label'] === 'Former account'
                    && $removedSource['fallback'] === true
                    && $removedSource['avatar_url'] === null;
            });
    }

    private function createNotification(User $user, string $title, string $body, $readAt = null, array $extraData = [])
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'project.update',
            'data' => array_merge(['title' => $title, 'body' => $body], $extraData),
            'read_at' => $readAt,
        ]);
    }
}
