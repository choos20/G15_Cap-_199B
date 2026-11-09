<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\ActivityParticipant;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceCsvImportWorkflowTest extends TestCase
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
            $table->string('participant_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('created_by');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('created_by');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('General');
            $table->string('status')->default('draft');
            $table->unsignedInteger('slots_needed')->nullable();
            $table->dateTime('application_deadline')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('event_date')->nullable();
            $table->string('event_location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('qr_token', 64)->unique();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('radius_meters')->default(50);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
        });
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('scanned_at')->nullable();
            $table->decimal('scanned_lat', 10, 7)->nullable();
            $table->decimal('scanned_lng', 10, 7)->nullable();
            $table->decimal('distance_meters', 8, 2)->nullable();
            $table->string('status')->default('valid');
            $table->string('attendance_source', 20)->default('qr');
            $table->string('check_in_status', 20)->nullable();
            $table->string('check_out_status', 20)->nullable();
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();
            $table->decimal('check_in_distance_meters', 8, 2)->nullable();
            $table->decimal('check_out_distance_meters', 8, 2)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
        Schema::create('volunteer_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('announcement_id');
            $table->unsignedBigInteger('user_id');
            $table->string('status')->default('pending');
            $table->text('decision_notes')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
        Schema::create('project_participant_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('added_by');
            $table->string('participation_type');
            $table->string('participant_name')->nullable();
            $table->string('participant_id_number', 50)->nullable();
            $table->string('roster_code', 30)->nullable();
            $table->unsignedBigInteger('attendance_session_id')->nullable();
            $table->string('attendance_check_in', 20)->nullable();
            $table->string('attendance_check_out', 20)->nullable();
            $table->string('attendance_status', 20)->nullable();
            $table->timestamp('attendance_recorded_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });
    }

    public function test_online_csv_review_validates_rows_and_confirmation_never_overwrites_qr_attendance(): void
    {
        $organizer = User::factory()->create(['role' => 'student_org', 'must_change_password' => false]);
        $participants = collect([
            User::factory()->create(['role' => 'participant', 'must_change_password' => false, 'id_number' => 'P-001']),
            User::factory()->create(['role' => 'participant', 'must_change_password' => false, 'id_number' => 'P-002']),
            User::factory()->create(['role' => 'participant', 'must_change_password' => false, 'id_number' => 'P-003']),
        ]);
        $project = Project::create([
            'title' => 'Community Outreach',
            'created_by' => $organizer->id,
            'status' => 'approved',
        ]);
        $eventDate = now()->addWeek();
        $announcement = Announcement::create([
            'project_id' => $project->id,
            'created_by' => $organizer->id,
            'title' => 'Outreach Volunteers',
            'status' => 'published',
            'slots_needed' => 10,
            'event_date' => $eventDate,
            'event_location' => 'Community Hall',
        ]);
        foreach ($participants as $participant) {
            \App\Models\VolunteerApplication::create([
                'announcement_id' => $announcement->id,
                'user_id' => $participant->id,
                'status' => 'approved',
            ]);
        }
        $participantWithoutId = User::factory()->create([
            'role' => 'participant',
            'must_change_password' => false,
            'id_number' => null,
        ]);
        \App\Models\VolunteerApplication::create([
            'announcement_id' => $announcement->id,
            'user_id' => $participantWithoutId->id,
            'status' => 'approved',
        ]);
        $officer = User::factory()->create([
            'role' => 'participant',
            'must_change_password' => false,
            'id_number' => 'OFF-001',
            'is_active' => true,
        ]);
        $participationOnly = User::factory()->create([
            'role' => 'participant',
            'must_change_password' => false,
            'id_number' => 'ONLY-001',
            'is_active' => true,
        ]);
        $this->actingAs($organizer)
            ->get('/student-org/participants')
            ->assertNotFound();
        $this->get(route('student_org.volunteers'))
            ->assertOk()
            ->assertSee('<span>Volunteer Applications</span>', false)
            ->assertDontSee('<span>People</span>', false)
            ->assertDontSee('Participant enrollment');
        $this->post('/student-org/participants', [])
            ->assertNotFound();
        $this->post('/student-org/participants/external', [])
            ->assertNotFound();
        $this->delete('/student-org/participants/1')
            ->assertNotFound();

        $legacyOfficer = ActivityParticipant::create([
            'project_id' => $project->id,
            'user_id' => $officer->id,
            'added_by' => $organizer->id,
            'participation_type' => 'attendance_required',
        ]);
        ActivityParticipant::create([
            'project_id' => $project->id,
            'user_id' => $participationOnly->id,
            'added_by' => $organizer->id,
            'participation_type' => 'participation_only',
        ]);
        $externalParticipant = ActivityParticipant::create([
            'project_id' => $project->id,
            'user_id' => null,
            'added_by' => $organizer->id,
            'participation_type' => 'attendance_required',
            'participant_name' => 'Guest Officer',
            'roster_code' => 'LEGACY-001',
        ]);
        $this->assertNotEmpty($externalParticipant->roster_code);
        $session = AttendanceSession::create([
            'project_id' => $project->id,
            'qr_token' => Str::random(40),
            'latitude' => 14.6,
            'longitude' => 120.98,
            'radius_meters' => 50,
            'opens_at' => now(),
            'closes_at' => now()->addHours(4),
            'status' => 'open',
        ]);

        $this->actingAs($participants[1])
            ->get(route('participant.dashboard'))
            ->assertOk()
            ->assertSee('My participation')
            ->assertSee('No attendance history yet');

        $csvResponse = $this->actingAs($organizer)
            ->get(route('student_org.attendance.csv-template', $session));
        $csvResponse->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename=attendance-template-'.$session->id.'.csv');
        $csvRows = array_map('str_getcsv', explode("\n", trim($csvResponse->streamedContent())));
        $csvRows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $csvRows[0][0]);
        $this->assertSame(['attendance_session_id', 'activity_title', 'activity_date', 'venue', 'participant_id', 'participant_name', 'check_in', 'check_out'], $csvRows[0]);
        $this->assertCount(7, $csvRows);
        $this->assertContains('OFF-001', array_column($csvRows, 4));
        $this->assertContains($externalParticipant->roster_code, array_column($csvRows, 4));
        $this->assertContains('Guest Officer', array_column($csvRows, 5));
        $this->assertNotContains('ONLY-001', array_column($csvRows, 4));
        $this->assertSame('', $csvRows[1][6]);
        $this->assertSame('', $csvRows[1][7]);

        $signatureResponse = $this->get(route('student_org.attendance.signature-sheet', $session));
        $signatureResponse->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertHeader('Content-Disposition', 'attachment; filename=attendance-signature-sheet-'.$session->id.'.docx');
        $docxPath = tempnam(sys_get_temp_dir(), 'attendance-test-');
        file_put_contents($docxPath, $signatureResponse->streamedContent());
        $docx = new \ZipArchive;
        $this->assertTrue($docx->open($docxPath));
        $documentXml = $docx->getFromName('word/document.xml');
        $docx->close();
        if (file_exists($docxPath)) {
            unlink($docxPath);
        }
        $this->assertStringContainsString('ACTIVITY PARTICIPANT SIGNATURE SHEET', $documentXml);
        $this->assertStringContainsString('P-001', $documentXml);

        $this->get(route('student_org.attendance.import', $session))
            ->assertOk()
            ->assertSee('Record attendance, your way')
            ->assertSee('Internet connection required')
            ->assertSee('Attendance CSV')
            ->assertSee('Upload completed CSV')
            ->assertDontSee('No internet needed for matching')
            ->assertDontSee('Start matching');
        $this->get(route('student_org.attendance'))
            ->assertOk()
            ->assertSee('Upload attendance CSV (online)')
            ->assertSee(route('student_org.attendance.import', $session))
            ->assertSee('Download signature sheet (DOCX)');

        $otherOrganizer = User::factory()->create(['role' => 'student_org', 'must_change_password' => false]);
        $this->actingAs($otherOrganizer)
            ->get(route('student_org.attendance.csv-template', $session))
            ->assertForbidden();
        $this->get(route('student_org.attendance.signature-sheet', $session))
            ->assertForbidden();
        $this->get(route('student_org.attendance.import', $session))
            ->assertForbidden();
        $this->actingAs($organizer);

        $csv = $this->csv($session->id, $eventDate->format('Y-m-d'), [
            ['P-001', 'Present', 'Present'],
            ['ONLY-001', 'Present', 'Present'],
            ['P-002', 'Absent', 'Absent'],
            ['P-001', 'Present', 'Present'],
            ['P-999', 'Present', 'Present'],
            ['P-003', 'Here', 'Present'],
            ['P-003', 'Present', 'Present', 'extra-cell'],
        ]);
        $invalidPreview = $this->post(route('student_org.attendance.preview', $session), [
            'attendance_file' => UploadedFile::fake()->createWithContent('attendance.csv', $csv),
        ]);
        $invalidPreview->assertOk()
            ->assertSee('Invalid / duplicate records')
            ->assertSee('Participant ID appears more than once')
            ->assertSee('Participant ID was not found')
            ->assertSee('extra data beyond the CSV headers')
            ->assertSee('Check-In must be Present, Absent, or blank')
            ->assertSee('3</strong>Matched', false)
            ->assertSee('1</strong>Absent volunteers', false)
            ->assertSee('2</strong>Unmatched participant IDs', false)
            ->assertSee('6</strong>Invalid / duplicate records', false);
        $this->assertDatabaseCount('attendance_records', 0);

        $cleanCsv = $this->csv($session->id, $eventDate->format('Y-m-d'), [
            ['P-002', 'Present', 'Present'],
            ['P-003', 'Absent', 'Absent'],
            ['OFF-001', 'Present', 'Present'],
            [$externalParticipant->roster_code, 'Present', 'Present'],
        ]);
        $firstPreview = $this->post(route('student_org.attendance.preview', $session), [
            'attendance_file' => UploadedFile::fake()->createWithContent('attendance.csv', $cleanCsv),
        ]);
        $firstPreview->assertOk();
        $this->assertDatabaseCount('attendance_records', 0);

        preg_match('/name="preview_token" value="([^"]+)"/', $firstPreview->getContent(), $rosterTokenMatch);
        $this->assertNotEmpty($rosterTokenMatch[1] ?? null);
        \App\Models\VolunteerApplication::where('announcement_id', $announcement->id)
            ->where('user_id', $participants[2]->id)
            ->update(['status' => 'declined']);
        $this->post(route('student_org.attendance.confirm', $session), [
            'preview_token' => $rosterTokenMatch[1],
        ])->assertSessionHasErrors('attendance_file');
        $this->assertDatabaseCount('attendance_records', 0);
        \App\Models\VolunteerApplication::where('announcement_id', $announcement->id)
            ->where('user_id', $participants[2]->id)
            ->update(['status' => 'approved']);

        $qrRecord = AttendanceRecord::create([
            'session_id' => $session->id,
            'user_id' => $participants[1]->id,
            'status' => 'valid',
            'attendance_source' => 'qr',
            'check_in_status' => 'Present',
            'check_in_at' => now(),
        ]);
        $this->actingAs($participants[1])
            ->get(route('participant.dashboard'))
            ->assertOk()
            ->assertSee('You attended 1 activity over the last six months.');
        $this->actingAs($organizer);
        preg_match('/name="preview_token" value="([^"]+)"/', $firstPreview->getContent(), $tokenMatch);
        $this->assertNotEmpty($tokenMatch[1] ?? null);

        $this->post(route('student_org.attendance.confirm', $session), [
            'preview_token' => $tokenMatch[1],
        ])->assertSessionHasErrors('attendance_file');
        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertDatabaseHas('attendance_records', [
            'id' => $qrRecord->id,
            'attendance_source' => 'qr',
            'user_id' => $participants[1]->id,
        ]);

        $latestPreview = $this->post(route('student_org.attendance.preview', $session), [
            'attendance_file' => UploadedFile::fake()->createWithContent('attendance.csv', $cleanCsv),
        ]);
        $latestPreview->assertOk()
            ->assertSee('QR attendance is already recorded')
            ->assertSee('Confirm and save 3 valid row(s)');
        preg_match('/name="preview_token" value="([^"]+)"/', $latestPreview->getContent(), $latestTokenMatch);
        $this->assertNotEmpty($latestTokenMatch[1] ?? null);

        $this->post(route('student_org.attendance.confirm', $session), [
            'preview_token' => $latestTokenMatch[1],
        ])->assertRedirect(route('student_org.attendance.records', $session));

        $this->assertDatabaseCount('attendance_records', 3);
        $this->assertDatabaseHas('attendance_records', [
            'session_id' => $session->id,
            'user_id' => $participants[2]->id,
            'attendance_source' => AttendanceRecord::MANUAL_CSV_SOURCE,
            'check_in_status' => 'Absent',
        ]);
        $this->assertDatabaseHas('attendance_records', [
            'id' => $qrRecord->id,
            'attendance_source' => 'qr',
            'user_id' => $participants[1]->id,
        ]);
        $this->assertDatabaseHas('attendance_records', [
            'session_id' => $session->id,
            'user_id' => $officer->id,
            'attendance_source' => AttendanceRecord::MANUAL_CSV_SOURCE,
            'check_in_status' => 'Present',
        ]);
        $this->assertDatabaseHas('project_participant_enrollments', [
            'id' => $externalParticipant->id,
            'attendance_session_id' => $session->id,
            'attendance_status' => 'completed',
            'attendance_check_in' => 'Present',
            'attendance_check_out' => 'Present',
        ]);
        $this->get(route('student_org.attendance.records', $session))
            ->assertOk()
            ->assertSee('Guest Officer')
            ->assertSee('Organizer CSV');
        $this->assertDatabaseHas('project_participant_enrollments', [
            'id' => $legacyOfficer->id,
            'user_id' => $officer->id,
        ]);
    }

    private function csv(int $sessionId, string $eventDate, array $marks): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, [
            'attendance_session_id',
            'activity_title',
            'activity_date',
            'venue',
            'participant_id',
            'participant_name',
            'check_in',
            'check_out',
        ]);
        foreach ($marks as $mark) {
            [$participantId, $checkIn, $checkOut] = $mark;
            fputcsv($stream, [
                $sessionId,
                'Community Outreach',
                $eventDate,
                'Community Hall',
                $participantId,
                'Participant '.$participantId,
                $checkIn,
                $checkOut,
                $mark[3] ?? null,
            ]);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}
