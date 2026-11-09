<?php

namespace Tests\Unit;

use App\Models\Announcement;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class ParticipantActivityStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-28 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_deadline_remains_open_for_the_entire_deadline_date(): void
    {
        $announcement = $this->announcement([
            'application_deadline' => now()->endOfDay()->toDateTimeString(),
            'event_date' => now()->addDays(3),
            'slots_needed' => 5,
            'approved_volunteers' => 2,
        ]);

        $this->assertSame('open', $announcement->participantApplicationStatus());
    }

    public function test_deadline_closes_at_the_selected_time(): void
    {
        $announcement = $this->announcement([
            'application_deadline' => now()->addHour()->toDateTimeString(),
            'event_date' => now()->addDays(3),
            'slots_needed' => 5,
            'approved_volunteers' => 2,
        ]);

        $this->assertSame('open', $announcement->participantApplicationStatus());

        Carbon::setTestNow(now()->addHours(2));

        $this->assertSame('closed', $announcement->participantApplicationStatus());
    }

    public function test_activity_scheduled_for_today_is_ongoing(): void
    {
        $announcement = $this->announcement([
            'application_deadline' => now()->endOfDay()->toDateTimeString(),
            'event_date' => now()->subHour(),
            'slots_needed' => 5,
            'approved_volunteers' => 2,
        ]);

        $this->assertSame('ongoing', $announcement->participantApplicationStatus());
    }

    public function test_event_passed_check_uses_manila_local_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-06 06:12:00', 'UTC'));

        $announcement = $this->announcement([
            'event_date' => '2026-11-06 14:10:00',
        ]);

        $this->assertTrue($announcement->eventHasPassed());
    }

    public function test_event_does_not_pass_until_its_manila_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-06 06:09:00', 'UTC'));

        $announcement = $this->announcement([
            'event_date' => '2026-11-06 14:10:00',
        ]);

        $this->assertFalse($announcement->eventHasPassed());
    }

    public function test_expired_deadline_past_activity_and_full_capacity_are_closed(): void
    {
        $expiredDeadline = $this->announcement([
            'application_deadline' => now()->subDay()->toDateString(),
            'event_date' => now()->addDays(3),
            'slots_needed' => 5,
            'approved_volunteers' => 2,
        ]);
        $pastActivity = $this->announcement([
            'application_deadline' => now()->addDay()->toDateString(),
            'event_date' => now()->subDay(),
            'slots_needed' => 5,
            'approved_volunteers' => 2,
        ]);
        $fullActivity = $this->announcement([
            'application_deadline' => now()->addDay()->toDateString(),
            'event_date' => now()->addDays(3),
            'slots_needed' => 2,
            'approved_volunteers' => 2,
        ]);

        $this->assertSame('closed', $expiredDeadline->participantApplicationStatus());
        $this->assertSame('closed', $pastActivity->participantApplicationStatus());
        $this->assertSame('closed', $fullActivity->participantApplicationStatus());
    }

    private function announcement(array $attributes): Announcement
    {
        $announcement = new Announcement;
        $announcement->setRawAttributes($attributes);

        return $announcement;
    }
}
