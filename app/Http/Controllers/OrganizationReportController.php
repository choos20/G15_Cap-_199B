<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Support\AcademicPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationReportController extends Controller
{
    private const REPORTS = [
        'activity-completion' => [
            'title' => 'Activity completion',
            'description' => 'Review your activities, event dates, completion status, and service hours.',
        ],
        'participant-involvement' => [
            'title' => 'Participant and volunteer involvement',
            'description' => 'See approved volunteers and assigned participants for each activity.',
        ],
        'attendance' => [
            'title' => 'Attendance & participant roster',
            'description' => 'List the people who joined each activity with their ID, profile, role, and attendance details.',
        ],
        'evaluation-results' => [
            'title' => 'Evaluation results',
            'description' => 'Review evaluation responses, average ratings, and participant feedback.',
        ],
    ];

    private function guard(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['student_org', 'college_department'], true), 403, 'Not allowed');
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'ay' => ['nullable', 'regex:/^\d{4}-\d{4}$/'],
            'term' => ['nullable', Rule::in(array_keys(AcademicPeriod::TERMS))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'project' => ['nullable', 'integer', 'min:1'],
        ]);
        $ay = $validated['ay'] ?? AcademicPeriod::current();
        $range = AcademicPeriod::range($ay, $validated['term'] ?? null);

        abort_unless($range && preg_match('/^(\d{4})-(\d{4})$/', $ay, $parts)
            && (int) $parts[2] === (int) $parts[1] + 1, 422, 'The selected academic year is invalid.');

        return [
            'ay' => $ay,
            'term' => $validated['term'] ?? '',
            'from' => $validated['from'] ?? '',
            'to' => $validated['to'] ?? '',
            'project' => $validated['project'] ?? null,
            'start' => $range[0],
            'end' => $range[1],
        ];
    }

    private function projects(Request $request, array $filters)
    {
        return DB::table('projects as p')
            ->where('p.created_by', $request->user()->id)
            ->whereBetween('p.created_at', [$filters['start'], $filters['end']])
            ->when($filters['project'] ?? null, fn (Builder $query, $projectId) => $query->where('p.id', $projectId))
            ->when($filters['from'], fn (Builder $query) => $query->whereDate('p.created_at', '>=', $filters['from']))
            ->when($filters['to'], fn (Builder $query) => $query->whereDate('p.created_at', '<=', $filters['to']))
            ->select(['p.id', 'p.title', 'p.status', 'p.hours_rendered', 'p.created_at'])
            ->orderByDesc('p.created_at')
            ->get();
    }

    private function csv(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->stream(function () use ($headings, $rows) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $headings);
            foreach ($rows as $row) {
                fputcsv($stream, array_map(function ($value) {
                    $value = (string) $value;

                    return preg_match('/^\s*[=+\-@]/', $value) ? "'".$value : $value;
                }, $row));
            }
            fclose($stream);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function attendanceStatusLabel(?string $status): string
    {
        return match ($status) {
            'valid', 'present', 'attended' => 'Attended',
            'completed' => 'Completed',
            'partial' => 'Partial',
            'absent' => 'Absent',
            'out_of_range' => 'Outside geofence',
            'expired' => 'Session closed',
            'not_enrolled' => 'Not enrolled',
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }

    private function attendanceTime(?string $value): string
    {
        return filled($value) ? \Illuminate\Support\Carbon::parse($value)->format('M j, Y g:i A') : '—';
    }

    private function participantTypeLabel(?string $type, ?string $role = null): string
    {
        return User::PARTICIPANT_TYPES[$type] ?? ($role === 'external_participant' ? 'External participant' : 'Participant');
    }

    public function index(Request $request)
    {
        $this->guard($request);

        return view('STUDENT_ORGANIZATION_PRESIDENT.reports.index', [
            'reports' => self::REPORTS,
        ]);
    }

    public function show(Request $request, string $report)
    {
        $this->guard($request);
        abort_unless(isset(self::REPORTS[$report]), 404);

        $filters = $this->filters($request);
        $projects = $this->projects($request, $filters);
        $projectIds = $projects->pluck('id')->all();
        $rows = collect();
        $summary = [];
        $criteria = collect();
        $feedback = collect();
        $attendanceRows = collect();
        $activityOptions = $report === 'attendance'
            ? $this->projects($request, array_merge($filters, ['project' => null]))
            : collect();

        if ($report === 'activity-completion') {
            $activities = DB::table('projects as p')
                ->leftJoin('announcements as a', 'a.project_id', '=', 'p.id')
                ->leftJoin('evaluations as e', 'e.announcement_id', '=', 'a.id')
                ->whereIn('p.id', $projectIds ?: [0])
                ->select([
                    'p.id as project_id',
                    'p.title as project',
                    'p.status as project_status',
                    'p.hours_rendered',
                    'p.created_at',
                    'a.title as activity',
                    'a.event_date',
                    'e.submitted_at as evaluation_submitted_at',
                ])
                ->orderByDesc('a.event_date')
                ->get();
            $rows = $activities->map(function ($activity) {
                $completion = $activity->event_date
                    ? (now()->greaterThanOrEqualTo($activity->event_date) ? 'Completed' : 'Scheduled')
                    : 'No event date';

                return [
                    $activity->project,
                    $activity->activity ?: 'No activity announcement',
                    $activity->event_date ? \Illuminate\Support\Carbon::parse($activity->event_date)->format('M j, Y') : '—',
                    $completion,
                    Project::statusLabel($activity->project_status),
                    $activity->hours_rendered ?? 0,
                    $activity->evaluation_submitted_at ? 'Submitted' : 'Not submitted',
                ];
            });
            $summary = [
                'Projects' => $projects->count(),
                'Activities completed' => $activities->filter(fn ($activity) => $activity->event_date && now()->greaterThanOrEqualTo($activity->event_date))->count(),
                'Hours rendered' => (int) $projects->sum('hours_rendered'),
            ];
            $headings = ['Project', 'Activity', 'Event date', 'Completion', 'Approval status', 'Hours rendered', 'Evaluation'];
        } elseif ($report === 'participant-involvement') {
            $volunteers = DB::table('volunteer_applications as applications')
                ->join('announcements as a', 'a.id', '=', 'applications.announcement_id')
                ->whereIn('a.project_id', $projectIds ?: [0])
                ->where('applications.status', 'approved')
                ->selectRaw('a.project_id, COUNT(DISTINCT applications.user_id) as total')
                ->groupBy('a.project_id')
                ->pluck('total', 'project_id');
            $participants = DB::table('project_participant_enrollments')
                ->whereIn('project_id', $projectIds ?: [0])
                ->selectRaw('project_id, COUNT(DISTINCT user_id) + SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as total')
                ->groupBy('project_id')
                ->pluck('total', 'project_id');
            $rows = $projects->map(fn ($project) => [
                $project->title,
                (int) ($volunteers[$project->id] ?? 0),
                (int) ($participants[$project->id] ?? 0),
                (int) ($volunteers[$project->id] ?? 0) + (int) ($participants[$project->id] ?? 0),
            ]);
            $summary = [
                'Projects' => $projects->count(),
                'Approved volunteers' => (int) $volunteers->sum(),
                'Assigned participants' => (int) $participants->sum(),
            ];
            $headings = ['Project', 'Approved volunteers', 'Assigned participants', 'Involvement records'];
        } elseif ($report === 'attendance') {
            $attendedStatuses = ['valid', 'present', 'attended', 'completed', 'partial'];
            $volunteerUserIds = DB::table('volunteer_applications as applications')
                ->join('announcements as announcements', 'announcements.id', '=', 'applications.announcement_id')
                ->whereIn('announcements.project_id', $projectIds ?: [0])
                ->where('applications.status', 'approved')
                ->pluck('applications.user_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

            $registeredAttendance = DB::table('attendance_records as records')
                ->join('attendance_sessions as sessions', 'sessions.id', '=', 'records.session_id')
                ->join('projects as projects', 'projects.id', '=', 'sessions.project_id')
                ->join('users as users', 'users.id', '=', 'records.user_id')
                ->whereIn('sessions.project_id', $projectIds ?: [0])
                ->whereIn('records.status', $attendedStatuses)
                ->select([
                    'sessions.project_id',
                    'projects.title as project',
                    'records.status',
                    'records.attendance_source',
                    'records.check_in_status',
                    'records.check_out_status',
                    'records.check_in_at',
                    'records.check_out_at',
                    'records.scanned_at',
                    'records.updated_at',
                    'users.id as user_id',
                    'users.name',
                    'users.id_number',
                    'users.participant_type',
                    'users.program',
                    'users.affiliation',
                    'users.role',
                ])
                ->get()
                ->map(function ($record) use ($volunteerUserIds) {
                    $recordedAt = $record->check_in_at ?: ($record->scanned_at ?: $record->updated_at);
                    $programDepartment = collect([$record->program, $record->affiliation])
                        ->filter(fn ($value) => filled($value))
                        ->unique()
                        ->implode(' / ');

                    return [
                        'dedupe_key' => 'account:'.$record->project_id.':'.$record->user_id,
                        'activity' => $record->project,
                        'id_number' => $record->id_number,
                        'name' => $record->name,
                        'participant_type' => $this->participantTypeLabel($record->participant_type, $record->role),
                        'program_department' => $programDepartment ?: '—',
                        'participation_role' => in_array((int) $record->user_id, $volunteerUserIds, true)
                            ? 'Approved volunteer'
                            : 'Assigned participant',
                        'status' => $this->attendanceStatusLabel($record->status),
                        'check_in' => $record->check_in_at
                            ? $this->attendanceTime($record->check_in_at)
                            : ($record->check_in_status ?: '—'),
                        'check_out' => $record->check_out_at
                            ? $this->attendanceTime($record->check_out_at)
                            : ($record->check_out_status ?: '—'),
                        'recorded_at' => $this->attendanceTime($recordedAt),
                        'source' => in_array($record->attendance_source, AttendanceRecord::CSV_SOURCES, true)
                            ? 'Organizer CSV'
                            : 'QR scan',
                        '_recorded_at' => $recordedAt,
                    ];
                });

            $externalAttendance = DB::table('project_participant_enrollments as participants')
                ->join('projects as projects', 'projects.id', '=', 'participants.project_id')
                ->whereIn('participants.project_id', $projectIds ?: [0])
                ->whereNull('participants.user_id')
                ->whereNotNull('participants.attendance_recorded_at')
                ->whereIn('participants.attendance_status', $attendedStatuses)
                ->select([
                    'participants.id',
                    'participants.project_id',
                    'projects.title as project',
                    'participants.participant_name',
                    'participants.participant_id_number',
                    'participants.roster_code',
                    'participants.attendance_status',
                    'participants.attendance_check_in',
                    'participants.attendance_check_out',
                    'participants.attendance_recorded_at',
                ])
                ->get()
                ->map(fn ($record) => [
                    'dedupe_key' => 'external:'.$record->project_id.':'.$record->id,
                    'activity' => $record->project,
                    'id_number' => $record->participant_id_number ?: $record->roster_code,
                    'name' => $record->participant_name,
                    'participant_type' => 'External participant',
                    'program_department' => '—',
                    'participation_role' => 'External participant',
                    'status' => $this->attendanceStatusLabel($record->attendance_status),
                    'check_in' => $record->attendance_check_in ?: '—',
                    'check_out' => $record->attendance_check_out ?: '—',
                    'recorded_at' => $this->attendanceTime($record->attendance_recorded_at),
                    'source' => 'Organizer CSV',
                    '_recorded_at' => $record->attendance_recorded_at,
                ]);

            $attendanceRows = $registeredAttendance
                ->concat($externalAttendance)
                ->sortByDesc('_recorded_at')
                ->unique('dedupe_key')
                ->sortBy(fn (array $row) => mb_strtolower($row['name'].' '.$row['activity']))
                ->values()
                ->map(function (array $row) {
                    unset($row['dedupe_key'], $row['_recorded_at']);

                    return $row;
                });

            $attendance = DB::table('attendance_records as records')
                ->join('attendance_sessions as sessions', 'sessions.id', '=', 'records.session_id')
                ->whereIn('sessions.project_id', $projectIds ?: [0])
                ->selectRaw('sessions.project_id, COUNT(*) as records, COUNT(DISTINCT CASE WHEN records.status IN (?, ?, ?, ?) THEN records.user_id END) as attended', ['valid', 'present', 'attended', 'completed'])
                ->groupBy('sessions.project_id')
                ->get()
                ->keyBy('project_id');
            $externalAttendance = DB::table('project_participant_enrollments')
                ->whereIn('project_id', $projectIds ?: [0])
                ->whereNotNull('attendance_recorded_at')
                ->selectRaw('project_id, COUNT(*) as records, COUNT(DISTINCT CASE WHEN attendance_status IN (?, ?, ?, ?) THEN id END) as attended', ['valid', 'present', 'attended', 'completed'])
                ->groupBy('project_id')
                ->get()
                ->keyBy('project_id');
            $rows = $attendanceRows->map(fn ($participant) => [
                $participant['activity'],
                $participant['id_number'] ?? '',
                $participant['name'],
                $participant['participant_type'],
                $participant['program_department'],
                $participant['participation_role'],
                $participant['status'],
                $participant['check_in'],
                $participant['check_out'],
                $participant['source'],
            ]);
            $summary = [
                'Projects' => $projects->count(),
                'Attendance records' => (int) $attendance->sum('records') + (int) $externalAttendance->sum('records'),
                'Recorded as attended' => (int) $attendance->sum('attended') + (int) $externalAttendance->sum('attended'),
                'Participants listed' => $attendanceRows->count(),
            ];
            $headings = ['Activity', 'ID number', 'Full name', 'Participant type', 'Program / department', 'Participation role', 'Attendance status', 'Check-in', 'Check-out', 'Source'];
        } else {
            $activities = DB::table('announcements as a')
                ->join('projects as p', 'p.id', '=', 'a.project_id')
                ->leftJoin('evaluations as e', 'e.announcement_id', '=', 'a.id')
                ->whereIn('p.id', $projectIds ?: [0])
                ->select([
                    'a.id as announcement_id',
                    'a.title as activity',
                    'p.title as project',
                    'e.id as evaluation_id',
                    'e.expected_beneficiaries',
                    'e.submitted_at',
                ])
                ->orderBy('a.title')
                ->get();
            $evaluationIds = $activities->pluck('evaluation_id')->filter()->all();
            $averages = DB::table('evaluation_response_ratings as ratings')
                ->join('evaluation_responses as responses', 'responses.id', '=', 'ratings.response_id')
                ->whereIn('responses.evaluation_id', $evaluationIds ?: [0])
                ->selectRaw('responses.evaluation_id, responses.respondent_type, ROUND(AVG(ratings.score), 2) as average')
                ->groupBy('responses.evaluation_id', 'responses.respondent_type')
                ->get()
                ->keyBy(fn ($average) => $average->evaluation_id.':'.$average->respondent_type);
            $responseCountsByType = DB::table('evaluation_responses')
                ->whereIn('evaluation_id', $evaluationIds ?: [0])
                ->selectRaw('evaluation_id, respondent_type, COUNT(*) as total')
                ->groupBy('evaluation_id', 'respondent_type')
                ->get()
                ->keyBy(fn ($count) => $count->evaluation_id.':'.$count->respondent_type);
            $averageByType = DB::table('evaluation_response_ratings as ratings')
                ->join('evaluation_responses as responses', 'responses.id', '=', 'ratings.response_id')
                ->whereIn('responses.evaluation_id', $evaluationIds ?: [0])
                ->selectRaw('responses.respondent_type, ROUND(AVG(ratings.score), 2) as average')
                ->groupBy('responses.respondent_type')
                ->pluck('average', 'respondent_type');
            $criteria = DB::table('evaluation_response_ratings as ratings')
                ->join('evaluation_responses as responses', 'responses.id', '=', 'ratings.response_id')
                ->join('evaluation_criteria as criteria', 'criteria.id', '=', 'ratings.criterion_id')
                ->whereIn('responses.evaluation_id', $evaluationIds ?: [0])
                ->selectRaw('criteria.short_label, responses.respondent_type, ROUND(AVG(ratings.score), 2) as average')
                ->groupBy('criteria.id', 'criteria.short_label', 'criteria.sort_order', 'responses.respondent_type')
                ->orderBy('criteria.sort_order')
                ->get();
            $feedback = DB::table('evaluation_responses as responses')
                ->join('evaluations as e', 'e.id', '=', 'responses.evaluation_id')
                ->join('announcements as a', 'a.id', '=', 'e.announcement_id')
                ->whereIn('e.id', $evaluationIds ?: [0])
                ->where(function (Builder $query) {
                    $query->whereNotNull('responses.suggestions')
                        ->orWhereNotNull('responses.observations');
                })
                ->select('a.title as activity', 'responses.suggestions', 'responses.observations')
                ->orderByDesc('responses.created_at')
                ->get();
            $rows = $activities->map(fn ($activity) => [
                $activity->project,
                $activity->activity,
                $activity->submitted_at ? 'Submitted' : ($activity->evaluation_id ? 'In progress' : 'Not started'),
                (int) ($responseCountsByType[$activity->evaluation_id.':beneficiary']->total ?? 0),
                (int) ($responseCountsByType[$activity->evaluation_id.':implementer']->total ?? 0),
                $activity->expected_beneficiaries ?? '—',
                isset($averages[$activity->evaluation_id.':beneficiary']) ? number_format((float) $averages[$activity->evaluation_id.':beneficiary']->average, 2).' / 5' : '—',
                isset($averages[$activity->evaluation_id.':implementer']) ? number_format((float) $averages[$activity->evaluation_id.':implementer']->average, 2).' / 5' : '—',
            ]);
            $summary = [
                'Activities' => $activities->count(),
                'Evaluations submitted' => $activities->whereNotNull('submitted_at')->count(),
                'Beneficiary responses' => (int) $responseCountsByType->filter(fn ($count) => $count->respondent_type === 'beneficiary')->sum('total'),
                'Volunteer / implementer responses' => (int) $responseCountsByType->filter(fn ($count) => $count->respondent_type === 'implementer')->sum('total'),
                'Beneficiary average' => isset($averageByType['beneficiary']) ? number_format((float) $averageByType['beneficiary'], 2).' / 5' : '—',
                'Volunteer / implementer average' => isset($averageByType['implementer']) ? number_format((float) $averageByType['implementer'], 2).' / 5' : '—',
            ];
            $headings = ['Project', 'Activity', 'Evaluation status', 'Beneficiary responses', 'Volunteer / implementer responses', 'Expected participants', 'Beneficiary average', 'Volunteer / implementer average'];
        }

        if ($request->query('format') === 'csv') {
            if ($report === 'attendance') {
                $csvRows = $attendanceRows->map(fn (array $participant) => [
                    $participant['activity'],
                    filled($participant['id_number']) ? "'".$participant['id_number'] : '',
                    $participant['name'],
                    $participant['participant_type'],
                    $participant['program_department'],
                    $participant['participation_role'],
                    $participant['status'],
                    $participant['check_in'],
                    $participant['check_out'],
                    $participant['source'],
                ]);

                return $this->csv($report.'-'.$filters['ay'].'.csv', $headings, $csvRows);
            }

            return $this->csv($report.'-'.$filters['ay'].'.csv', $headings, $rows);
        }

        return view('STUDENT_ORGANIZATION_PRESIDENT.reports.show', [
            'report' => $report,
            'reportInfo' => self::REPORTS[$report],
            'rows' => $rows,
            'summary' => $summary,
            'headings' => $headings,
            'criteria' => $criteria,
            'feedback' => $feedback,
            'filters' => $filters,
            'activityOptions' => $activityOptions,
            'attendanceRows' => $attendanceRows,
            'years' => AcademicPeriod::labelsFrom(DB::table('projects')->where('created_by', $request->user()->id)->pluck('created_at')),
        ]);
    }
}
