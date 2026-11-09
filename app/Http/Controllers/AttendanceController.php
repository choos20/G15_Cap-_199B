<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\ActivityParticipant;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Project;
use App\Models\User;
use App\Models\VolunteerApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class AttendanceController extends Controller
{
    protected function ensureOrg(Request $request)
    {
        if (! \in_array($request->user()->role, ['student_org', 'college_department'])) {
            abort(403, 'Unauthorized');
        }
    }

    protected function assertValidScanToken(string $token): void
    {
        if (! preg_match('/^[a-zA-Z0-9]{40}$/', $token)) {
            abort(404, 'Invalid attendance token.');
        }
    }

    public function index(Request $request)
    {
        $this->ensureOrg($request);

        $user = $request->user();

        $projectsWithSessions = AttendanceSession::whereHas('project', function ($query) use ($user) {
            $query->where('created_by', $user->id);
        })->pluck('project_id');

        $approvedProjects = Project::where('created_by', $user->id)
            ->whereIn('status', ['approved', 'csa_cleared'])
            ->whereNotIn('id', $projectsWithSessions)
            ->orderBy('updated_at', 'desc')
            ->get();

        $sessions = AttendanceSession::with('project')
            ->whereHas('project', function ($query) use ($user) {
                $query->where('created_by', $user->id);
            })
            ->orderByDesc('created_at')
            ->get();
        $now = now();
        $minOpenAt = $now->copy()->startOfMinute();
        if ($now->second !== 0 || $now->micro !== 0) {
            $minOpenAt->addMinute();
        }

        return view('STUDENT_ORGANIZATION_PRESIDENT.attendance', [
            'approvedProjects' => $approvedProjects,
            'sessions' => $sessions,
            'minOpenAt' => $minOpenAt->format('Y-m-d\TH:i'),
            'minCloseAt' => $minOpenAt->copy()->addMinute()->format('Y-m-d\TH:i'),
            'maxCloseAt' => $now->copy()->addYear()->format('Y-m-d\TH:i'),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureOrg($request);

        $now = now();
        $minOpenAt = $now->format('Y-m-d H:i:s');
        $maxCloseAt = $now->copy()->addYear()->format('Y-m-d H:i:s');

        $data = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'opens_at' => 'required|date|after_or_equal:'.$minOpenAt,
            'closes_at' => 'required|date|after:opens_at|before_or_equal:'.$maxCloseAt,
        ], [
            'project_id.required' => 'Please select an approved activity.',
            'latitude.required' => 'Activity latitude is required.',
            'longitude.required' => 'Activity longitude is required.',
            'opens_at.required' => 'Opening time is required.',
            'opens_at.after_or_equal' => 'Opening time cannot be in the past. Same-day sessions must start now or later.',
            'closes_at.required' => 'Closing time is required.',
            'closes_at.after' => 'Closing time must be after the opening time.',
            'closes_at.before_or_equal' => 'Closing time must be within one year from today.',
        ]);

        $project = Project::where('id', $data['project_id'])
            ->where('created_by', $request->user()->id)
            ->whereIn('status', ['approved', 'csa_cleared'])
            ->firstOrFail();

        if (AttendanceSession::where('project_id', $project->id)->exists()) {
            throw ValidationException::withMessages([
                'project_id' => 'This activity already has an attendance session. Once it is closed, a new session cannot be created.',
            ]);
        }

        AttendanceSession::create([
            'project_id' => $project->id,
            'qr_token' => Str::random(40),
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'radius_meters' => AttendanceSession::RADIUS_METERS,
            'opens_at' => $data['opens_at'],
            'closes_at' => $data['closes_at'],
            'status' => 'open',
        ]);

        return Redirect::route('student_org.attendance')->with('status', 'Attendance session opened and QR code generated.');
    }

    public function regenerate(Request $request, AttendanceSession $attendanceSession)
    {
        $this->ensureOrg($request);

        abort_unless($attendanceSession->project && $attendanceSession->project->created_by === $request->user()->id, 403);
        abort_if($attendanceSession->isClosed(), 403, 'Closed sessions cannot be modified.');

        $attendanceSession->update(['qr_token' => Str::random(40)]);

        return Redirect::back()->with('status', 'QR token rotated for this session.');
    }

    public function close(Request $request, AttendanceSession $attendanceSession)
    {
        $this->ensureOrg($request);

        abort_unless($attendanceSession->project && $attendanceSession->project->created_by === $request->user()->id, 403);
        abort_if($attendanceSession->isClosed(), 403, 'This session is already closed.');

        $attendanceSession->update(['status' => 'closed']);

        return Redirect::back()->with('status', 'Attendance session closed permanently.');
    }

    public function records(Request $request, AttendanceSession $attendanceSession)
    {
        $this->ensureOrg($request);

        abort_unless($attendanceSession->project && $attendanceSession->project->created_by === $request->user()->id, 403);

        $records = AttendanceRecord::where('session_id', $attendanceSession->id)
            ->with('user')
            ->orderByDesc('scanned_at')
            ->get();
        $externalRecords = ActivityParticipant::where('attendance_session_id', $attendanceSession->id)
            ->whereNull('user_id')
            ->whereNotNull('attendance_recorded_at')
            ->get()
            ->map(function ($participant) {
                /** @var ActivityParticipant $participant */
                return (object) [
                    'status' => $participant->attendance_status,
                    'check_in_status' => $participant->attendance_check_in,
                    'check_out_status' => $participant->attendance_check_out,
                    'attendance_source' => AttendanceRecord::MANUAL_CSV_SOURCE,
                    'distance_meters' => null,
                    'scanned_lat' => null,
                    'scanned_lng' => null,
                    'scanned_at' => null,
                    'updated_at' => $participant->attendance_recorded_at,
                    'user' => (object) [
                        'name' => $participant->participant_name,
                        'email' => null,
                    ],
                ];
            });
        $records = $records->concat($externalRecords)
            ->sortByDesc(fn ($record) => $record->scanned_at ?? $record->updated_at)
            ->values();

        return view('STUDENT_ORGANIZATION_PRESIDENT.attendance_records', [
            'session' => $attendanceSession->load('project'),
            'records' => $records,
        ]);
    }

    public function downloadAttendanceCsv(Request $request, AttendanceSession $attendanceSession)
    {
        $this->ensureOrg($request);
        $this->assertSessionOwner($request, $attendanceSession);

        $project = $attendanceSession->project;
        $announcement = Announcement::where('project_id', $project->id)
            ->orderByDesc('id')
            ->first();
        $activityDate = $announcement?->event_date?->format('Y-m-d') ?? '';
        $venue = $announcement?->event_location ?? '';

        $participants = $this->attendanceParticipants($attendanceSession);

        return response()->streamDownload(function () use ($participants, $attendanceSession, $project, $activityDate, $venue): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'attendance_session_id',
                'activity_title',
                'activity_date',
                'venue',
                'participant_id',
                'participant_name',
                'check_in',
                'check_out',
            ]);

            foreach ($participants as $participant) {
                fputcsv($output, [
                    $attendanceSession->id,
                    $this->spreadsheetSafeValue($project->title),
                    $activityDate,
                    $this->spreadsheetSafeValue($venue),
                    $this->spreadsheetSafeValue($participant->id_number ?? ''),
                    $this->spreadsheetSafeValue($participant->name),
                    '',
                    '',
                ]);
            }

            fclose($output);
        }, 'attendance-template-'.$attendanceSession->id.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadSignatureSheet(Request $request, AttendanceSession $attendanceSession)
    {
        $this->ensureOrg($request);
        $this->assertSessionOwner($request, $attendanceSession);

        $project = $attendanceSession->project;
        $announcement = Announcement::where('project_id', $project->id)
            ->orderByDesc('id')
            ->first();

        $word = new PhpWord;
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);
        $section = $word->addSection([
            'orientation' => 'landscape',
            'marginLeft' => 600,
            'marginRight' => 600,
            'marginTop' => 600,
            'marginBottom' => 600,
        ]);
        $section->addText('ACTIVITY PARTICIPANT SIGNATURE SHEET', [
            'bold' => true,
            'size' => 16,
            'color' => '17365D',
        ]);
        $section->addText('Activity: '.$project->title, ['bold' => true, 'size' => 11]);
        $section->addText('Date: '.($announcement?->event_date?->format('F j, Y') ?? 'Not specified'));
        $section->addText('Venue: '.($announcement?->event_location ?: 'Not specified'));
        $section->addText(
            'Collect signatures below for specimen and attendance documentation. Record check-in and check-out separately in the CSV attendance template.',
            ['italic' => true, 'size' => 9, 'color' => '475569'],
            ['spaceAfter' => 240],
        );

        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '94A3B8',
            'cellMargin' => 90,
        ]);
        $table->addRow(420, ['tblHeader' => true]);
        foreach ([
            ['No.', 600],
            ['Participant ID', 2400],
            ['Participant name', 5200],
            ['Specimen signature', 7200],
        ] as [$heading, $width]) {
            $table->addCell($width, ['bgColor' => 'E2E8F0'])->addText($heading, ['bold' => true]);
        }

        foreach ($this->attendanceParticipants($attendanceSession)->values() as $index => $participant) {
            $table->addRow(640);
            $table->addCell(600)->addText((string) ($index + 1));
            $table->addCell(2400)->addText((string) ($participant->id_number ?? ''));
            $table->addCell(5200)->addText((string) $participant->name);
            $table->addCell(7200)->addText('');
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'attendance-signatures-');
        if ($temporaryFile === false) {
            throw new \RuntimeException('Unable to create the attendance signature sheet.');
        }

        try {
            IOFactory::createWriter($word, 'Word2007')->save($temporaryFile);
        } catch (\Throwable $exception) {
            unlink($temporaryFile);
            throw $exception;
        }

        return response()->streamDownload(function () use ($temporaryFile): void {
            try {
                $contents = file_get_contents($temporaryFile);
                if ($contents === false) {
                    throw new \RuntimeException('Unable to read the generated attendance signature sheet.');
                }
                echo $contents;
            } finally {
                if (file_exists($temporaryFile)) {
                    unlink($temporaryFile);
                }
            }
        }, 'attendance-signature-sheet-'.$attendanceSession->id.'.docx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function attendanceImport(Request $request, AttendanceSession $attendanceSession)
    {
        $this->ensureOrg($request);
        $this->assertSessionOwner($request, $attendanceSession);

        return view('STUDENT_ORGANIZATION_PRESIDENT.attendance_csv_import', [
            'attendanceSession' => $attendanceSession->load('project'),
        ]);
    }

    public function previewAttendanceImport(Request $request, AttendanceSession $attendanceSession)
    {
        $this->ensureOrg($request);
        $this->assertSessionOwner($request, $attendanceSession);

        $validated = $request->validate([
            'attendance_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $parsed = $this->parseAttendanceCsv($validated['attendance_file']->getRealPath());
        $rows = $this->buildAttendanceImportRows($parsed, $attendanceSession);
        $token = (string) Str::uuid();
        Cache::put($this->attendanceImportCacheKey($request, $token), [
            'session_id' => $attendanceSession->id,
            'rows' => $rows,
        ], now()->addMinutes(30));

        return response()->view('STUDENT_ORGANIZATION_PRESIDENT.attendance_import_preview', [
            'attendanceSession' => $attendanceSession->load('project'),
            'rows' => $rows,
            ...$this->attendanceImportSummary($rows),
            'token' => $token,
        ]);
    }

    private function buildAttendanceImportRows(array $parsed, AttendanceSession $attendanceSession): array
    {
        $project = $attendanceSession->project;
        $announcement = Announcement::where('project_id', $project->id)
            ->orderByDesc('id')
            ->first();
        $activityDate = $announcement?->event_date?->format('Y-m-d') ?? '';
        $venue = $announcement?->event_location ?? '';

        $participantsById = $this->attendanceParticipants($attendanceSession)
            ->filter(fn ($participant) => filled($participant->id_number))
            ->groupBy(fn ($participant) => $this->normalizeParticipantId($participant->id_number));

        $participantIdCounts = collect($parsed)
            ->map(fn ($row) => $this->normalizeParticipantId($row['participant_id']))
            ->filter()
            ->countBy();
        $qrAttendance = AttendanceRecord::where('session_id', $attendanceSession->id)
            ->where('attendance_source', 'qr')
            ->whereIn('status', ['valid', 'completed', 'attended'])
            ->pluck('user_id')
            ->mapWithKeys(fn ($userId) => [(int) $userId => true]);
        $rows = array_map(
            fn (array $row): array => $this->reviewAttendanceImportRow(
                $row,
                $attendanceSession,
                $project,
                $activityDate,
                $venue,
                $participantsById,
                $participantIdCounts,
                $qrAttendance,
            ),
            $parsed,
        );

        return $rows;
    }

    private function attendanceImportSummary(array $rows): array
    {
        $validRows = collect($rows)->where('valid', true);

        return [
            'matchedCount' => collect($rows)->where('matched', true)->map(
                fn ($row) => $row['user_id'] ? 'user:'.$row['user_id'] : 'enrollment:'.$row['enrollment_id']
            )->unique()->count(),
            'importableCount' => $validRows->count(),
            'unmatchedCount' => collect($rows)->where('matched', false)->count(),
            'invalidCount' => collect($rows)->where('valid', false)->count(),
            'presentCount' => $validRows->where('check_in', 'Present')->count(),
            'absentCount' => $validRows->where('check_in', 'Absent')->count(),
            'partialCount' => $validRows->where('status', 'partial')->count(),
        ];
    }

    /**
     * @param Collection<string, Collection<int, object>> $participantsById
     * @param Collection<string, int> $participantIdCounts
     * @param Collection<int, bool> $qrAttendance
     */
    private function reviewAttendanceImportRow(
        array $row,
        AttendanceSession $attendanceSession,
        Project $project,
        string $activityDate,
        string $venue,
        Collection $participantsById,
        Collection $participantIdCounts,
        Collection $qrAttendance,
    ): array {
        $issues = [];
        $warnings = [];
        $sessionId = trim($row['attendance_session_id']);

        if ($sessionId !== (string) $attendanceSession->id) {
            $issues[] = 'This row belongs to a different attendance session.';
        }
        if (mb_strtolower(trim($row['activity_title'])) !== mb_strtolower(trim($project->title))) {
            $issues[] = 'Activity title does not match this session.';
        }
        if (! $this->activityDateMatches($row['activity_date'], $activityDate)) {
            $issues[] = 'Activity date does not match this session.';
        }
        if (mb_strtolower(trim($row['venue'])) !== mb_strtolower(trim($venue))) {
            $issues[] = 'Venue does not match this session.';
        }
        if ($row['_has_extra_data'] ?? false) {
            $issues[] = 'This row contains extra data beyond the CSV headers.';
        }

        $participantId = $this->normalizeParticipantId($row['participant_id']);
        $matches = $participantId === '' ? collect() : $participantsById->get($participantId, collect());
        $participant = null;
        if ($participantId === '') {
            $issues[] = 'Participant ID is required.';
        } elseif ($matches->count() > 1) {
            $issues[] = 'Participant ID is ambiguous; ask an administrator to correct duplicate IDs.';
        } elseif ($matches->isEmpty()) {
            $issues[] = 'Participant ID was not found on this activity roster.';
        } else {
            $participant = $matches->first();
            if ($participantIdCounts->get($participantId, 0) > 1) {
                $issues[] = 'Participant ID appears more than once in the file; remove duplicate rows before importing.';
            }
            if ($participant->user_id !== null && $qrAttendance->has((int) $participant->user_id)) {
                $issues[] = 'QR attendance is already recorded for this participant; the CSV cannot overwrite it.';
            }
        }

        $checkIn = $this->normalizeAttendanceMark($row['check_in']);
        $checkOut = $this->normalizeAttendanceMark($row['check_out']);
        if ($checkIn === null && trim($row['check_in']) !== '') {
            $issues[] = 'Check-In must be Present, Absent, or blank.';
        } elseif ($checkIn === null) {
            $warnings[] = 'Check-In is missing; this record will be Partial.';
        }
        if ($checkOut === null && trim($row['check_out']) !== '') {
            $issues[] = 'Check-Out must be Present, Absent, or blank.';
        } elseif ($checkOut === null) {
            $warnings[] = 'Check-Out is missing; this record will be Partial.';
        }

        return [
            'line' => $row['line'],
            'participant_id' => trim($row['participant_id']),
            'submitted_name' => trim($row['participant_name']),
            'matched_name' => $participant?->name,
            'check_in' => $checkIn ?? trim($row['check_in']),
            'check_out' => $checkOut ?? trim($row['check_out']),
            'status' => $this->attendanceStatus($checkIn, $checkOut),
            'user_id' => $participant?->user_id,
            'enrollment_id' => $participant?->enrollment_id,
            'matched' => $participant !== null,
            'issues' => $issues,
            'warnings' => $warnings,
            'valid' => $issues === [],
        ];
    }

    public function confirmAttendanceImport(Request $request, AttendanceSession $attendanceSession)
    {
        $this->ensureOrg($request);
        $this->assertSessionOwner($request, $attendanceSession);
        $validated = $request->validate([
            'preview_token' => ['required', 'uuid'],
        ]);

        $cacheKey = $this->attendanceImportCacheKey($request, $validated['preview_token']);
        $preview = Cache::get($cacheKey);
        if (! $preview || (int) $preview['session_id'] !== $attendanceSession->id) {
            throw ValidationException::withMessages([
                'attendance_file' => 'This attendance preview expired or is no longer available. Upload the CSV again.',
            ]);
        }

        $validRows = collect($preview['rows'])->where('valid', true)->values();
        if ($validRows->isEmpty()) {
            throw ValidationException::withMessages([
                'attendance_file' => 'There are no valid matched rows to import. Correct the CSV and upload it again.',
            ]);
        }

        $accountUserIds = $validRows->pluck('user_id')->filter()->unique()->values();
        $qrConflictExists = AttendanceRecord::where('session_id', $attendanceSession->id)
            ->where('attendance_source', 'qr')
            ->whereIn('status', ['valid', 'completed', 'attended'])
            ->whereIn('user_id', $accountUserIds)
            ->exists();
        if ($qrConflictExists) {
            throw ValidationException::withMessages([
                'attendance_file' => 'A QR attendance record was added after the preview. Upload the CSV again to review the latest records.',
            ]);
        }

        $currentlyEligibleIds = $this->attendanceParticipantIds(
            $attendanceSession->project_id,
            $attendanceSession->id
        );
        $currentlyEligibleEnrollmentIds = $this->attendanceEnrollmentIds($attendanceSession->project_id);

        if ($validRows->contains(fn ($row) => $row['user_id'] !== null
            ? ! $currentlyEligibleIds->contains((int) $row['user_id'])
            : ! $currentlyEligibleEnrollmentIds->contains((int) $row['enrollment_id']))) {
            throw ValidationException::withMessages([
                'attendance_file' => 'The activity roster changed after the preview. Upload the CSV again to refresh participant matches.',
            ]);
        }

        $savedCount = DB::transaction(function () use ($validRows, $attendanceSession, $accountUserIds): int {
            $lockedSession = AttendanceSession::whereKey($attendanceSession->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentlyEligibleIds = $this->attendanceParticipantIds(
                $lockedSession->project_id,
                $lockedSession->id,
                true
            );
            $currentlyEligibleEnrollmentIds = $this->attendanceEnrollmentIds(
                $lockedSession->project_id,
                true
            );

            if ($validRows->contains(fn ($row) => $row['user_id'] !== null
                ? ! $currentlyEligibleIds->contains((int) $row['user_id'])
                : ! $currentlyEligibleEnrollmentIds->contains((int) $row['enrollment_id']))) {
                throw ValidationException::withMessages([
                    'attendance_file' => 'The activity roster changed while saving. No CSV records were saved; upload the CSV again to refresh participant matches.',
                ]);
            }

            $qrConflictExists = AttendanceRecord::where('session_id', $lockedSession->id)
                ->where('attendance_source', 'qr')
                ->whereIn('status', ['valid', 'completed', 'attended'])
                ->whereIn('user_id', $accountUserIds)
                ->lockForUpdate()
                ->exists();

            if ($qrConflictExists) {
                throw ValidationException::withMessages([
                    'attendance_file' => 'A QR attendance record was added while saving. No CSV records were saved; upload the CSV again to review the latest records.',
                ]);
            }

            foreach ($validRows as $row) {
                if ($row['enrollment_id'] !== null) {
                    $enrollment = ActivityParticipant::whereKey($row['enrollment_id'])
                        ->where('project_id', $lockedSession->project_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $enrollment->update([
                        'attendance_session_id' => $lockedSession->id,
                        'attendance_status' => $row['status'],
                        'attendance_check_in' => $row['check_in'],
                        'attendance_check_out' => $row['check_out'],
                        'attendance_recorded_at' => now(),
                    ]);

                    continue;
                }

                $record = AttendanceRecord::where('session_id', $lockedSession->id)
                    ->where('user_id', $row['user_id'])
                    ->whereIn('attendance_source', AttendanceRecord::CSV_SOURCES)
                    ->first();

                $attributes = [
                    'status' => $row['status'],
                    'attendance_source' => AttendanceRecord::MANUAL_CSV_SOURCE,
                    'check_in_status' => $row['check_in'],
                    'check_out_status' => $row['check_out'],
                    'note' => 'Imported from manually completed attendance CSV.',
                ];

                if ($record) {
                    $record->update($attributes);
                } else {
                    AttendanceRecord::create($attributes + [
                        'session_id' => $lockedSession->id,
                        'user_id' => $row['user_id'],
                    ]);
                }
            }

            return $validRows->count();
        });

        Cache::forget($cacheKey);

        return Redirect::route('student_org.attendance.records', $attendanceSession)
            ->with('status', "{$savedCount} attendance record(s) imported from the attendance CSV.");
    }

    private function assertSessionOwner(Request $request, AttendanceSession $attendanceSession): void
    {
        abort_unless(
            $attendanceSession->project && $attendanceSession->project->created_by === $request->user()->id,
            403
        );
    }

    private function attendanceParticipants(AttendanceSession $attendanceSession)
    {
        $participantIds = $this->attendanceParticipantIds(
            $attendanceSession->project_id,
            $attendanceSession->id
        );

        $accountParticipants = User::whereIn('id', $participantIds)
            ->orderBy('name')
            ->get(['id', 'name', 'id_number'])
            ->map(function ($participant) {
                /** @var User $participant */
                return (object) [
                    'user_id' => (int) $participant->id,
                    'enrollment_id' => null,
                    'id_number' => $participant->id_number,
                    'name' => $participant->name,
                ];
            });
        $externalParticipants = ActivityParticipant::where('project_id', $attendanceSession->project_id)
            ->whereNull('user_id')
            ->where('participation_type', ActivityParticipant::ATTENDANCE_REQUIRED)
            ->orderBy('participant_name')
            ->get()
            ->map(function ($participant) {
                /** @var ActivityParticipant $participant */
                return (object) [
                    'user_id' => null,
                    'enrollment_id' => (int) $participant->id,
                    'id_number' => $participant->participant_id_number ?? $participant->roster_code,
                    'name' => $participant->participant_name,
                ];
            });

        return $accountParticipants->concat($externalParticipants)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function attendanceParticipantIds(int $projectId, ?int $sessionId = null, bool $lock = false): Collection
    {
        $approvedVolunteerIds = VolunteerApplication::query()
            ->where('status', 'approved')
            ->whereHas('announcement', fn ($query) => $query->where('project_id', $projectId));
        if ($lock) {
            $approvedVolunteerIds->lockForUpdate();
        }

        $assignedAttendeeIds = ActivityParticipant::query()
            ->where('project_id', $projectId)
            ->whereNotNull('user_id')
            ->where('participation_type', ActivityParticipant::ATTENDANCE_REQUIRED);
        if ($lock) {
            $assignedAttendeeIds->lockForUpdate();
        }

        $participantIds = $approvedVolunteerIds->pluck('user_id')
            ->merge($assignedAttendeeIds->pluck('user_id'));

        if ($sessionId !== null) {
            $existingRecordIds = AttendanceRecord::where('session_id', $sessionId)
                ->where('status', 'valid');
            if ($lock) {
                $existingRecordIds->lockForUpdate();
            }
            $participantIds = $participantIds->merge($existingRecordIds->pluck('user_id'));
        }

        return $participantIds->filter()->map(fn ($id) => (int) $id)->unique()->values();
    }

    private function attendanceEnrollmentIds(int $projectId, bool $lock = false): Collection
    {
        $query = ActivityParticipant::where('project_id', $projectId)
            ->whereNull('user_id')
            ->where('participation_type', ActivityParticipant::ATTENDANCE_REQUIRED);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->values();
    }

    private function parseAttendanceCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'attendance_file' => 'The uploaded CSV could not be read.',
            ]);
        }

        try {
            $headers = fgetcsv($handle);
            if (! \is_array($headers)) {
                throw ValidationException::withMessages([
                    'attendance_file' => 'The uploaded CSV is empty.',
                ]);
            }

            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);
            $requiredHeaders = [
                'attendance_session_id',
                'activity_title',
                'activity_date',
                'venue',
                'participant_id',
                'participant_name',
                'check_in',
                'check_out',
            ];
            foreach ($requiredHeaders as $header) {
                if (\count(array_keys($headers, $header, true)) > 1) {
                    throw ValidationException::withMessages([
                        'attendance_file' => "The CSV has more than one '{$header}' column. Keep only one of each required column.",
                    ]);
                }
            }
            $positions = [];
            foreach ($requiredHeaders as $header) {
                $position = array_search($header, $headers, true);
                if ($position === false) {
                    throw ValidationException::withMessages([
                        'attendance_file' => "The CSV is missing the required '{$header}' column. Download a fresh attendance template.",
                    ]);
                }
                $positions[$header] = $position;
            }

            $rows = [];
            $line = 1;
            while (($values = fgetcsv($handle)) !== false) {
                $line++;
                $hasExtraData = \count($values) > \count($headers)
                    && \count(array_filter(
                        \array_slice($values, \count($headers)),
                        fn ($value) => trim((string) $value) !== ''
                    )) > 0;
                $values = array_pad($values, \count($headers), '');
                $row = ['line' => $line];
                foreach ($positions as $header => $position) {
                    $row[$header] = trim((string) ($values[$position] ?? ''));
                }
                $row['_has_extra_data'] = $hasExtraData;

                if (\count(array_filter($row, fn ($value, $key) => $key !== 'line' && $value !== '', ARRAY_FILTER_USE_BOTH)) === 0) {
                    continue;
                }
                if (\count($rows) >= 5000) {
                    throw ValidationException::withMessages([
                        'attendance_file' => 'The CSV contains more than 5,000 participant rows. Split it into smaller files.',
                    ]);
                }
                $rows[] = $row;
            }

            if ($rows === []) {
                throw ValidationException::withMessages([
                    'attendance_file' => 'The CSV has a header but no participant rows.',
                ]);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function normalizeParticipantId(string $participantId): string
    {
        $participantId = trim($participantId);
        if (preg_match("/^'(?=[=+@-])/", $participantId)) {
            $participantId = substr($participantId, 1);
        }

        return mb_strtoupper($participantId);
    }

    private function normalizeAttendanceMark(string $mark): ?string
    {
        return match (mb_strtolower(trim($mark))) {
            'present' => 'Present',
            'absent' => 'Absent',
            default => null,
        };
    }

    private function activityDateMatches(string $date, string $expectedDate): bool
    {
        $date = trim($date);
        if ($expectedDate === '') {
            return $date === '';
        }

        foreach (['Y-m-d', 'm/d/Y', 'd/m/Y', 'm-d-Y', 'd-m-Y'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!'.$format, $date);
            $errors = \DateTimeImmutable::getLastErrors();
            $dateHasNoParseErrors = $errors === false
                || $errors['warning_count'] === 0 && $errors['error_count'] === 0;
            if ($parsed && $dateHasNoParseErrors) {
                if ($parsed->format('Y-m-d') === $expectedDate) {
                    return true;
                }
            }
        }

        return false;
    }

    private function attendanceStatus(?string $checkIn, ?string $checkOut): string
    {
        if ($checkIn === 'Present' && $checkOut === 'Present') {
            return 'completed';
        }
        if ($checkIn === 'Present' && $checkOut === 'Absent') {
            return 'attended';
        }
        if ($checkIn === 'Absent' && $checkOut === 'Absent') {
            return 'absent';
        }

        return 'partial';
    }

    private function spreadsheetSafeValue(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    private function attendanceImportCacheKey(Request $request, string $token): string
    {
        return 'attendance-import-preview:'.$request->user()->id.':'.$token;
    }

    private function issueScanChallenge(Request $request, AttendanceSession $session): string
    {
        DB::table('attendance_scan_challenges')
            ->where('expires_at', '<', now())
            ->delete();

        $scanChallenge = Str::random(64);
        DB::table('attendance_scan_challenges')->insert([
            'attendance_session_id' => $session->id,
            'user_id' => $request->user()->id,
            'challenge_hash' => hash('sha256', $scanChallenge),
            'expires_at' => now()->addMinutes(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $scanChallenge;
    }

    private function participantAttendanceRecord(AttendanceSession $session, int $userId, bool $lockForUpdate = false): ?AttendanceRecord
    {
        $query = AttendanceRecord::query()
            ->where('session_id', $session->id)
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query->whereNotNull('check_in_at')
                    ->orWhere(function ($query) {
                        $query->where('attendance_source', 'qr')
                            ->whereIn('status', ['valid', 'completed'])
                            ->whereNotNull('scanned_at');
                    })
                    ->orWhere(function ($query) {
                        $query->whereIn('attendance_source', AttendanceRecord::CSV_SOURCES)
                            ->where('check_in_status', 'Present');
                    });
            })
            ->orderByDesc('check_in_at')
            ->orderByDesc('scanned_at');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function scanView(Request $request, string $token)
    {
        $this->assertValidScanToken($token);

        $session = AttendanceSession::with('project')->where('qr_token', $token)->firstOrFail();
        $attendanceRecord = $this->participantAttendanceRecord($session, $request->user()->id);
        $hasCheckIn = $attendanceRecord && (
            $attendanceRecord->check_in_at !== null
            || $attendanceRecord->attendance_source === 'qr' && $attendanceRecord->scanned_at !== null
            || \in_array($attendanceRecord->attendance_source, AttendanceRecord::CSV_SOURCES, true) && $attendanceRecord->check_in_status === 'Present'
        );
        $hasCheckOut = $attendanceRecord && (
            $attendanceRecord->check_out_at !== null
            || \in_array($attendanceRecord->attendance_source, AttendanceRecord::CSV_SOURCES, true) && $attendanceRecord->check_out_status === 'Present'
        );
        $isQrRecord = $attendanceRecord && ! \in_array($attendanceRecord->attendance_source, AttendanceRecord::CSV_SOURCES, true);

        return view('attendance.scan', [
            'session' => $session,
            'scanChallenge' => $this->issueScanChallenge($request, $session),
            'canCheckIn' => ! $attendanceRecord,
            'canCheckOut' => (bool) ($hasCheckIn && ! $hasCheckOut && $isQrRecord),
            'attendanceRecord' => $attendanceRecord,
        ]);
    }

    public function scan(Request $request, string $token)
    {
        $this->assertValidScanToken($token);

        $validated = $request->validate([
            'action' => ['required', 'in:check_in,check_out'],
            'scan_challenge' => ['required', 'string', 'size:64', 'regex:/^[a-zA-Z0-9]+$/'],
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);
        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];

        $session = AttendanceSession::with('project')->where('qr_token', $token)->firstOrFail();
        $user = $request->user();
        $challengeConsumed = DB::table('attendance_scan_challenges')
            ->where('attendance_session_id', $session->id)
            ->where('user_id', $user->id)
            ->where('challenge_hash', hash('sha256', $validated['scan_challenge']))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update([
                'used_at' => now(),
                'updated_at' => now(),
            ]);

        if ($challengeConsumed !== 1) {
            return response()->json([
                'status' => 'challenge_expired',
                'message' => 'This scan request expired or was already used. Reload the QR scan page and try again.',
            ], 419);
        }

        $action = $request->input('action');
        $distance = self::haversineDistance(
            $session->latitude,
            $session->longitude,
            $latitude,
            $longitude,
        );
        $result = DB::transaction(function () use ($session, $user, $action, $distance, $latitude, $longitude): array {
            $lockedSession = AttendanceSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $attendanceRecord = $this->participantAttendanceRecord($lockedSession, $user->id, true);
            $hasCheckIn = $attendanceRecord && (
                $attendanceRecord->check_in_at !== null
                || $attendanceRecord->attendance_source === 'qr' && $attendanceRecord->scanned_at !== null
                || \in_array($attendanceRecord->attendance_source, AttendanceRecord::CSV_SOURCES, true) && $attendanceRecord->check_in_status === 'Present'
            );
            $hasCheckOut = $attendanceRecord && (
                $attendanceRecord->check_out_at !== null
                || \in_array($attendanceRecord->attendance_source, AttendanceRecord::CSV_SOURCES, true) && $attendanceRecord->check_out_status === 'Present'
            );
            $isQrRecord = $attendanceRecord && ! \in_array($attendanceRecord->attendance_source, AttendanceRecord::CSV_SOURCES, true);
            $isEnrolled = $this->attendanceParticipantIds($lockedSession->project_id)
                ->contains((int) $user->id);

            if (! $isEnrolled) {
                $status = 'not_enrolled';
                $message = 'You must be an approved volunteer or enrolled for attendance to check in.';
            } elseif ($action === 'check_in' && $attendanceRecord) {
                $status = 'duplicate';
                $message = 'You already have an attendance record for this session.';
            } elseif ($action === 'check_out' && ! $hasCheckIn) {
                $status = 'no_check_in';
                $message = 'You must check in before you can check out.';
            } elseif ($action === 'check_out' && ! $isQrRecord) {
                $status = 'manual_record';
                $message = 'Your attendance was recorded by the organizer and cannot be checked out by QR.';
            } elseif ($action === 'check_out' && $hasCheckOut) {
                $status = 'duplicate';
                $message = 'You already checked out for this session.';
            } elseif (! $lockedSession->isActive()) {
                $status = 'expired';
                $message = 'This attendance session is no longer active.';
            } elseif ($distance > $lockedSession->radius_meters) {
                $status = 'out_of_range';
                $attendanceAction = $action === 'check_out' ? 'check out' : 'check in';
                $message = 'You are too far from the activity location to '.$attendanceAction.'. You must be within '.$lockedSession->radius_meters.' meters.';
            } elseif ($action === 'check_in') {
                $now = now();
                AttendanceRecord::create([
                    'session_id' => $lockedSession->id,
                    'user_id' => $user->id,
                    'scanned_at' => $now,
                    'scanned_lat' => $latitude,
                    'scanned_lng' => $longitude,
                    'distance_meters' => round($distance, 2),
                    'status' => 'valid',
                    'attendance_source' => 'qr',
                    'check_in_status' => 'Present',
                    'check_in_at' => $now,
                    'check_in_lat' => $latitude,
                    'check_in_lng' => $longitude,
                    'check_in_distance_meters' => round($distance, 2),
                ]);
                $status = 'valid';
                $message = 'Check-in recorded successfully.';
            } else {
                $now = now();
                $attendanceRecord->update([
                    'status' => 'completed',
                    'check_in_status' => $attendanceRecord->check_in_status ?? 'Present',
                    'check_in_at' => $attendanceRecord->check_in_at ?? $attendanceRecord->scanned_at ?? $now,
                    'check_out_status' => 'Present',
                    'check_out_at' => $now,
                    'check_out_lat' => $latitude,
                    'check_out_lng' => $longitude,
                    'check_out_distance_meters' => round($distance, 2),
                ]);
                $status = 'completed';
                $message = 'Check-out recorded successfully.';
            }

            if (\in_array($status, ['duplicate', 'expired', 'out_of_range'], true)) {
                AttendanceRecord::create([
                    'session_id' => $lockedSession->id,
                    'user_id' => $user->id,
                    'scanned_at' => now(),
                    'scanned_lat' => $latitude,
                    'scanned_lng' => $longitude,
                    'distance_meters' => round($distance, 2),
                    'status' => $status,
                    'attendance_source' => 'qr',
                    'note' => $status === 'duplicate' ? 'Duplicate scan' : null,
                ]);
            }

            $attendanceRecord = $this->participantAttendanceRecord($lockedSession, $user->id, true);
            $hasCheckIn = $attendanceRecord && (
                $attendanceRecord->check_in_at !== null
                || $attendanceRecord->attendance_source === 'qr' && $attendanceRecord->scanned_at !== null
                || \in_array($attendanceRecord->attendance_source, AttendanceRecord::CSV_SOURCES, true) && $attendanceRecord->check_in_status === 'Present'
            );
            $hasCheckOut = $attendanceRecord && (
                $attendanceRecord->check_out_at !== null
                || \in_array($attendanceRecord->attendance_source, AttendanceRecord::CSV_SOURCES, true) && $attendanceRecord->check_out_status === 'Present'
            );

            return [
                'status' => $status,
                'message' => $message,
                'can_check_in' => ! $attendanceRecord,
                'can_check_out' => (bool) ($hasCheckIn && ! $hasCheckOut && $attendanceRecord->attendance_source === 'qr'),
            ];
        });

        return response()->json([
            ...$result,
            'distance_meters' => round($distance, 2),
            'scan_challenge' => $this->issueScanChallenge($request, $session),
        ]);
    }

    protected static function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
