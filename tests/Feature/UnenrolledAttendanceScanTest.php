<?php

namespace Tests\Feature;

use App\Http\Controllers\AttendanceController;
use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnenrolledAttendanceScanTest extends TestCase
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
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('created_by');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->foreignId('created_by');
            $table->string('title');
            $table->string('status');
            $table->unsignedInteger('slots_needed')->nullable();
            $table->timestamps();
        });
        Schema::create('volunteer_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id');
            $table->foreignId('user_id');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('project_participant_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->foreignId('user_id')->nullable();
            $table->foreignId('added_by')->nullable();
            $table->string('participation_type');
            $table->string('participant_name')->nullable();
            $table->string('participant_id_number', 50)->nullable();
            $table->string('roster_code', 30)->nullable();
            $table->foreignId('attendance_session_id')->nullable();
            $table->string('attendance_check_in', 20)->nullable();
            $table->string('attendance_check_out', 20)->nullable();
            $table->string('attendance_status', 20)->nullable();
            $table->timestamp('attendance_recorded_at')->nullable();
            $table->timestamps();
        });
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->string('qr_token')->unique();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('radius_meters');
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id');
            $table->foreignId('user_id');
            $table->timestamp('scanned_at')->nullable();
            $table->decimal('scanned_lat', 10, 7)->nullable();
            $table->decimal('scanned_lng', 10, 7)->nullable();
            $table->decimal('distance_meters', 8, 2)->nullable();
            $table->string('status');
            $table->string('attendance_source')->nullable();
            $table->string('check_in_status')->nullable();
            $table->string('check_out_status')->nullable();
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
        Schema::create('attendance_scan_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id');
            $table->foreignId('user_id');
            $table->string('challenge_hash');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_unenrolled_participant_gets_a_clear_message_without_creating_an_attendance_record(): void
    {
        $organizer = User::factory()->create(['role' => 'student_org']);
        $participant = User::factory()->create(['role' => 'participant']);
        $projectId = DB::table('projects')->insertGetId([
            'title' => 'Community Activity',
            'created_by' => $organizer->id,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('announcements')->insert([
            'project_id' => $projectId,
            'created_by' => $organizer->id,
            'title' => 'Volunteer Opportunity',
            'status' => 'published',
            'slots_needed' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $token = Str::random(40);
        $session = AttendanceSession::create([
            'project_id' => $projectId,
            'qr_token' => $token,
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'radius_meters' => 50,
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHour(),
            'status' => 'open',
        ]);
        $challenge = Str::random(64);
        DB::table('attendance_scan_challenges')->insert([
            'attendance_session_id' => $session->id,
            'user_id' => $participant->id,
            'challenge_hash' => hash('sha256', $challenge),
            'expires_at' => now()->addMinutes(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/attendance/scan/'.$token, 'POST', [
            'action' => 'check_in',
            'scan_challenge' => $challenge,
            'latitude' => $session->latitude,
            'longitude' => $session->longitude,
        ]);
        $request->setUserResolver(fn () => $participant);
        $response = app(AttendanceController::class)->scan($request, $token);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('not_enrolled', $response->getData(true)['status']);
        $this->assertSame(
            'You must be an approved volunteer or enrolled for attendance to check in.',
            $response->getData(true)['message']
        );
        $this->assertTrue($response->getData(true)['can_check_in']);
        $this->assertFalse($response->getData(true)['can_check_out']);
        $this->assertDatabaseMissing('attendance_records', [
            'session_id' => $session->id,
            'user_id' => $participant->id,
        ]);

        $assignedAttendee = User::factory()->create(['role' => 'participant']);
        DB::table('project_participant_enrollments')->insert([
            'project_id' => $projectId,
            'user_id' => $assignedAttendee->id,
            'added_by' => $organizer->id,
            'participation_type' => 'attendance_required',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $assignedChallenge = Str::random(64);
        DB::table('attendance_scan_challenges')->insert([
            'attendance_session_id' => $session->id,
            'user_id' => $assignedAttendee->id,
            'challenge_hash' => hash('sha256', $assignedChallenge),
            'expires_at' => now()->addMinutes(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $assignedRequest = Request::create('/attendance/scan/'.$token, 'POST', [
            'action' => 'check_in',
            'scan_challenge' => $assignedChallenge,
            'latitude' => $session->latitude,
            'longitude' => $session->longitude,
        ]);
        $assignedRequest->setUserResolver(fn () => $assignedAttendee);
        $assignedResponse = app(AttendanceController::class)->scan($assignedRequest, $token);
        $this->assertSame('valid', $assignedResponse->getData(true)['status']);

        $participationOnly = User::factory()->create(['role' => 'participant']);
        DB::table('project_participant_enrollments')->insert([
            'project_id' => $projectId,
            'user_id' => $participationOnly->id,
            'added_by' => $organizer->id,
            'participation_type' => 'participation_only',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $participationChallenge = Str::random(64);
        DB::table('attendance_scan_challenges')->insert([
            'attendance_session_id' => $session->id,
            'user_id' => $participationOnly->id,
            'challenge_hash' => hash('sha256', $participationChallenge),
            'expires_at' => now()->addMinutes(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $participationRequest = Request::create('/attendance/scan/'.$token, 'POST', [
            'action' => 'check_in',
            'scan_challenge' => $participationChallenge,
            'latitude' => $session->latitude,
            'longitude' => $session->longitude,
        ]);
        $participationRequest->setUserResolver(fn () => $participationOnly);
        $participationResponse = app(AttendanceController::class)->scan($participationRequest, $token);
        $this->assertSame('not_enrolled', $participationResponse->getData(true)['status']);
        $this->assertDatabaseHas('attendance_records', [
            'session_id' => $session->id,
            'user_id' => $assignedAttendee->id,
            'status' => 'valid',
        ]);
        $this->assertDatabaseMissing('attendance_records', [
            'session_id' => $session->id,
            'user_id' => $participationOnly->id,
        ]);
    }
}
