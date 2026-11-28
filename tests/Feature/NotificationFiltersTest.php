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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->string('title');
            $table->string('status');
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
        $actor = User::factory()->create([
            'role' => 'student_org',
            'name' => 'Maria B. Representative',
            'organization_name' => 'United Balilinhon Students Association',
        ]);
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

                return $actorSource['label'] === $actor->organization_name
                    && $actorSource['initials'] === 'UB'
                    && $actorSource['avatar_type'] === 'organization'
                    && $actorSource['fallback'] === false
                    && $removedSource['label'] === 'Former account'
                    && $removedSource['fallback'] === true
                    && $removedSource['avatar_url'] === null;
            });
    }

    public function test_initiator_filters_combine_and_are_scoped_to_the_recipient(): void
    {
        $viewer = User::factory()->create(['role' => 'student_org']);
        $other = User::factory()->create(['role' => 'student_org']);
        $secretary = User::factory()->create(['role' => 'celp_secretary', 'name' => 'Leah Secretary']);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $this->createNotification($viewer, 'Proposal endorsed', 'Review complete', null, ['actor_id' => $secretary->id, 'category' => 'proposal_updates']);
        $this->createNotification($viewer, 'Another update', 'Review complete', now(), ['actor_id' => $secretary->id, 'category' => 'proposal_updates']);
        $this->createNotification($viewer, 'Application', 'Volunteer joined', null, ['actor_id' => $coordinator->id, 'category' => 'volunteer_applications']);
        $this->createNotification($viewer, 'Old notice', 'Legacy record');
        $this->createNotification($other, 'Private notice', 'Not yours', null, ['actor_role' => 'csa_director', 'category' => 'security']);

        $this->actingAs($viewer)->get(route('notifications.index', [
            'filter' => 'unread', 'sender' => 'celp_secretary', 'category' => 'proposal_updates', 'search' => 'Leah', 'date' => 'today',
        ]))->assertOk()->assertViewHas('notifications', fn ($rows) => $rows->total() === 1 && $rows->first()->data['title'] === 'Proposal endorsed')
            ->assertViewHas('senderOptions', fn ($options) => ! $options->contains('csa_director'))
            ->assertViewHas('categoryOptions', fn ($options) => ! $options->contains('security'));

        $this->actingAs($viewer)->get(route('notifications.index', ['sender' => 'unknown', 'category' => 'uncategorized']))
            ->assertOk()->assertViewHas('notifications', fn ($rows) => $rows->total() === 1 && $rows->first()->data['title'] === 'Old notice');
        $this->actingAs($viewer)->get(route('notifications.index', ['category' => 'security']))
            ->assertOk()->assertViewHas('notifications', fn ($rows) => $rows->total() === 0);
    }

    public function test_notification_avatar_uses_only_logo_or_initials_fallback(): void
    {
        $logoMarkup = view('components.notification-avatar', [
            'source' => [
                'initials' => 'UB',
                'avatar_url' => 'https://example.test/organization-logo.png',
                'avatar_type' => 'organization',
                'fallback' => false,
            ],
            'size' => 44,
        ])->render();

        $this->assertStringContainsString('<img src=', $logoMarkup);
        $this->assertStringContainsString('--notification-avatar-size: 44px', $logoMarkup);
        $this->assertStringContainsString('https://example.test/organization-logo.png', $logoMarkup);
        $this->assertStringNotContainsString('class="notification-avatar__initials"', $logoMarkup);

        $initialsMarkup = view('components.notification-avatar', [
            'source' => [
                'initials' => 'UB',
                'avatar_url' => null,
                'avatar_type' => 'organization',
                'fallback' => false,
            ],
            'size' => 44,
        ])->render();

        $this->assertStringContainsString('class="notification-avatar__initials"', $initialsMarkup);
        $this->assertStringContainsString('>UB</span>', $initialsMarkup);
        $this->assertStringNotContainsString('<img', $initialsMarkup);
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
