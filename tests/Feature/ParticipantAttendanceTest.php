<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Project;
use App\Models\User;
use App\Models\VolunteerApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ParticipantAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_check_in_and_check_out_with_the_session_qr(): void
    {
        [$participant, $session] = $this->createAttendanceSetup();

        $checkIn = $this->postJson(route('attendance.scan.submit', $session->qr_token), [
            'action' => 'check_in',
            'scan_challenge' => $this->issueChallenge($session, $participant),
            'latitude' => $session->latitude,
            'longitude' => $session->longitude,
        ]);

        $checkIn->assertOk()
            ->assertJsonPath('status', 'valid')
            ->assertJsonPath('can_check_in', false)
            ->assertJsonPath('can_check_out', true);

        $checkOut = $this->postJson(route('attendance.scan.submit', $session->qr_token), [
            'action' => 'check_out',
            'scan_challenge' => $checkIn->json('scan_challenge'),
            'latitude' => $session->latitude,
            'longitude' => $session->longitude,
        ]);

        $checkOut->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('can_check_in', false)
            ->assertJsonPath('can_check_out', false);

        $record = AttendanceRecord::where('session_id', $session->id)
            ->where('user_id', $participant->id)
            ->whereNotNull('check_in_at')
            ->firstOrFail();

        $this->assertNotNull($record->check_in_at);
        $this->assertNotNull($record->check_out_at);
        $this->assertSame('Present', $record->check_in_status);
        $this->assertSame('Present', $record->check_out_status);
        $this->assertSame('completed', $record->status);

        $this->actingAs($participant)
            ->get(route('participant.attendance'))
            ->assertOk()
            ->assertSee('Community Activity')
            ->assertSee('Completed');
    }

    public function test_check_out_without_check_in_and_duplicate_check_in_are_rejected(): void
    {
        [$participant, $session] = $this->createAttendanceSetup();

        $checkOut = $this->postJson(route('attendance.scan.submit', $session->qr_token), [
            'action' => 'check_out',
            'scan_challenge' => $this->issueChallenge($session, $participant),
            'latitude' => $session->latitude,
            'longitude' => $session->longitude,
        ]);

        $checkOut->assertOk()->assertJsonPath('status', 'no_check_in');
        $this->assertDatabaseMissing('attendance_records', [
            'session_id' => $session->id,
            'user_id' => $participant->id,
        ]);

        $checkIn = $this->postJson(route('attendance.scan.submit', $session->qr_token), [
            'action' => 'check_in',
            'scan_challenge' => $checkOut->json('scan_challenge'),
            'latitude' => $session->latitude,
            'longitude' => $session->longitude,
        ]);
        $checkIn->assertOk()->assertJsonPath('status', 'valid');

        $duplicateCheckIn = $this->postJson(route('attendance.scan.submit', $session->qr_token), [
            'action' => 'check_in',
            'scan_challenge' => $checkIn->json('scan_challenge'),
            'latitude' => $session->latitude,
            'longitude' => $session->longitude,
        ]);
        $duplicateCheckIn->assertOk()->assertJsonPath('status', 'duplicate');

        $this->assertSame(
            1,
            AttendanceRecord::where('session_id', $session->id)
                ->where('user_id', $participant->id)
                ->whereNotNull('check_in_at')
                ->count()
        );
    }

    public function test_attendance_history_only_shows_the_signed_in_participants_records(): void
    {
        [$participant, $session] = $this->createAttendanceSetup();
        $otherParticipant = User::factory()->create([
            'role' => 'participant',
            'must_change_password' => false,
        ]);

        AttendanceRecord::create([
            'session_id' => $session->id,
            'user_id' => $participant->id,
            'status' => 'valid',
            'attendance_source' => 'qr',
            'check_in_status' => 'Present',
            'check_in_at' => now(),
        ]);

        $this->actingAs($otherParticipant)
            ->get(route('participant.attendance'))
            ->assertOk()
            ->assertSee('No attendance records yet.')
            ->assertDontSee('Community Activity');
    }

    public function test_participant_cannot_withdraw_application_after_check_in(): void
    {
        [$participant, $session] = $this->createAttendanceSetup();
        $application = VolunteerApplication::where('user_id', $participant->id)->firstOrFail();

        $checkIn = $this->postJson(route('attendance.scan.submit', $session->qr_token), [
            'action' => 'check_in',
            'scan_challenge' => $this->issueChallenge($session, $participant),
            'latitude' => $session->latitude,
            'longitude' => $session->longitude,
        ]);
        $checkIn->assertOk()->assertJsonPath('status', 'valid');

        $this->from(route('participant.applications'))
            ->post(route('participant.applications.withdraw', $application))
            ->assertRedirect(route('participant.applications'))
            ->assertSessionHasErrors('withdraw');

        $this->assertDatabaseHas('volunteer_applications', [
            'id' => $application->id,
            'status' => 'approved',
        ]);

        $this->get(route('participant.applications'))
            ->assertOk()
            ->assertSee('Withdrawal unavailable: attendance has been recorded for this activity.')
            ->assertDontSee('data-confirm-title="Withdraw application?"');
    }

    public function test_participant_cannot_withdraw_application_after_check_out(): void
    {
        [$participant, $session] = $this->createAttendanceSetup();
        $application = VolunteerApplication::where('user_id', $participant->id)->firstOrFail();

        $checkIn = $this->postJson(route('attendance.scan.submit', $session->qr_token), [
            'action' => 'check_in',
            'scan_challenge' => $this->issueChallenge($session, $participant),
            'latitude' => $session->latitude,
            'longitude' => $session->longitude,
        ]);
        $checkIn->assertOk();

        $checkOut = $this->postJson(route('attendance.scan.submit', $session->qr_token), [
            'action' => 'check_out',
            'scan_challenge' => $checkIn->json('scan_challenge'),
            'latitude' => $session->latitude,
            'longitude' => $session->longitude,
        ]);
        $checkOut->assertOk()->assertJsonPath('status', 'completed');

        $this->from(route('participant.applications'))
            ->post(route('participant.applications.withdraw', $application))
            ->assertRedirect(route('participant.applications'))
            ->assertSessionHasErrors('withdraw');

        $this->assertDatabaseHas('volunteer_applications', [
            'id' => $application->id,
            'status' => 'approved',
        ]);
    }

    public function test_participant_can_withdraw_application_before_attendance_is_recorded(): void
    {
        [$participant] = $this->createAttendanceSetup();
        $application = VolunteerApplication::where('user_id', $participant->id)->firstOrFail();

        $this->from(route('participant.applications'))
            ->post(route('participant.applications.withdraw', $application))
            ->assertRedirect(route('participant.applications'))
            ->assertSessionHas('status', 'Application withdrawn.');

        $this->assertDatabaseHas('volunteer_applications', [
            'id' => $application->id,
            'status' => 'withdrawn',
        ]);
    }

    public function test_attendance_for_another_project_does_not_prevent_withdrawal(): void
    {
        [$participant, $session] = $this->createAttendanceSetup();
        $application = VolunteerApplication::where('user_id', $participant->id)->firstOrFail();
        $otherProject = Project::create([
            'title' => 'Another Activity',
            'created_by' => $session->project->created_by,
            'status' => 'approved',
        ]);
        $otherSession = AttendanceSession::create([
            'project_id' => $otherProject->id,
            'qr_token' => Str::random(40),
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'radius_meters' => 50,
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHour(),
            'status' => 'open',
        ]);
        AttendanceRecord::create([
            'session_id' => $otherSession->id,
            'user_id' => $participant->id,
            'status' => 'valid',
            'attendance_source' => 'qr',
            'check_in_status' => 'Present',
            'check_in_at' => now(),
        ]);

        $this->from(route('participant.applications'))
            ->post(route('participant.applications.withdraw', $application))
            ->assertRedirect(route('participant.applications'))
            ->assertSessionHas('status', 'Application withdrawn.');

        $this->assertDatabaseHas('volunteer_applications', [
            'id' => $application->id,
            'status' => 'withdrawn',
        ]);
    }

    private function createAttendanceSetup(): array
    {
        $organizer = User::factory()->create(['role' => 'student_org']);
        $participant = User::factory()->create([
            'role' => 'participant',
            'must_change_password' => false,
        ]);
        $project = Project::create([
            'title' => 'Community Activity',
            'created_by' => $organizer->id,
            'status' => 'approved',
        ]);
        $announcement = Announcement::create([
            'project_id' => $project->id,
            'created_by' => $organizer->id,
            'title' => 'Volunteer Opportunity',
            'status' => 'published',
            'slots_needed' => 10,
        ]);
        VolunteerApplication::create([
            'announcement_id' => $announcement->id,
            'user_id' => $participant->id,
            'status' => 'approved',
        ]);
        $session = AttendanceSession::create([
            'project_id' => $project->id,
            'qr_token' => Str::random(40),
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'radius_meters' => 50,
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHour(),
            'status' => 'open',
        ]);

        $this->actingAs($participant);

        return [$participant, $session];
    }

    private function issueChallenge(AttendanceSession $session, User $participant): string
    {
        $challenge = Str::random(64);
        DB::table('attendance_scan_challenges')->insert([
            'attendance_session_id' => $session->id,
            'user_id' => $participant->id,
            'challenge_hash' => hash('sha256', $challenge),
            'expires_at' => now()->addMinutes(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $challenge;
    }
}
