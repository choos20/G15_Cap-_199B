<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\Evaluation;
use App\Models\Project;
use App\Models\User;
use App\Models\VolunteerApplication;
use App\Support\AcademicPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsaReportController extends Controller
{
    private const REPORTS = [
        'accomplishment' => [
            'title' => 'Accomplishment Report',
            'description' => 'Completed activities, volunteer counts, attendance and recorded service hours.',
        ],
        'volunteer-roster' => [
            'title' => 'Volunteer Roster Report',
            'description' => 'Volunteer applications and decisions for student organization activities.',
        ],
        'participation' => [
            'title' => 'Participation Report',
            'description' => 'Unique participants recorded at each student organization activity.',
        ],
        'attendance' => [
            'title' => 'Attendance Report',
            'description' => 'Detailed attendance records linked to student organization projects.',
        ],
        'compliance' => [
            'title' => 'Compliance Report',
            'description' => 'Proposal review status and submitted project attachment counts.',
        ],
        'activity-status' => [
            'title' => 'Activity Status Report',
            'description' => 'Project approval and activity publication status for student organizations.',
        ],
        'evaluation-summary' => [
            'title' => 'Evaluation Summary Report',
            'description' => 'Beneficiary evaluation progress, recorded responses and average ratings.',
        ],
    ];

    private function guard(Request $request): void
    {
        abort_unless($request->user()?->role === 'csa_director', 403, 'Not allowed');
    }

    private function filters(Request $request): array
    {
        $filters = $request->validate([
            'organization' => ['nullable', 'integer'],
            'ay' => ['nullable', 'regex:/^\d{4}-\d{4}$/'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $academicYear = $filters['ay'] ?? AcademicPeriod::current();
        $range = AcademicPeriod::range($academicYear);
        $validAcademicYear = preg_match('/^(\d{4})-(\d{4})$/', $academicYear, $yearParts)
            && (int) $yearParts[2] === (int) $yearParts[1] + 1;

        if (! $range || ! $validAcademicYear) {
            abort(422, 'The selected academic year is invalid.');
        }

        if (! empty($filters['organization'])) {
            User::query()
                ->where('role', 'student_org')
                ->findOrFail($filters['organization']);
        }

        return [
            'organization' => $filters['organization'] ?? null,
            'ay' => $academicYear,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'start' => $range[0],
            'end' => $range[1],
        ];
    }

    private function organizations()
    {
        return User::query()
            ->where('role', 'student_org')
            ->orderByRaw('COALESCE(organization_name, name)')
            ->get(['id', 'name', 'organization_name']);
    }

    private function years(): array
    {
        return AcademicPeriod::labelsFrom(
            Project::query()
                ->whereHas('creator', fn (Builder $query) => $query->where('role', 'student_org'))
                ->pluck('created_at')
        );
    }

    private function applyPeriod(Builder $query, string $column, array $filters): Builder
    {
        return $query
            ->whereBetween($column, [$filters['start'], $filters['end']])
            ->when($filters['from'], fn (Builder $builder) => $builder->whereDate($column, '>=', $filters['from']))
            ->when($filters['to'], fn (Builder $builder) => $builder->whereDate($column, '<=', $filters['to']));
    }

    private function projects(array $filters): Builder
    {
        $query = Project::query()
            ->with('creator')
            ->withCount('attachments')
            ->whereHas('creator', function (Builder $query) use ($filters) {
                $query->where('role', 'student_org')
                    ->when($filters['organization'], fn ($builder) => $builder->whereKey($filters['organization']));
            });

        return $this->applyPeriod($query, 'projects.created_at', $filters);
    }

    private function activities(array $filters): Builder
    {
        return Announcement::query()
            ->with(['creator', 'project.creator'])
            ->withCount(['applications as approved_volunteers' => fn (Builder $query) => $query->where('status', 'approved')])
            ->whereHas('creator', function (Builder $query) use ($filters) {
                $query->where('role', 'student_org')
                    ->when($filters['organization'], fn (Builder $builder) => $builder->whereKey($filters['organization']));
            })
            ->whereHas('project.creator', fn (Builder $query) => $query->where('role', 'student_org'))
            ->whereRaw('COALESCE(announcements.event_date, announcements.published_at, announcements.created_at) BETWEEN ? AND ?', [$filters['start'], $filters['end']])
            ->when($filters['from'], fn (Builder $query) => $query->whereRaw('DATE(COALESCE(announcements.event_date, announcements.published_at, announcements.created_at)) >= ?', [$filters['from']]))
            ->when($filters['to'], fn (Builder $query) => $query->whereRaw('DATE(COALESCE(announcements.event_date, announcements.published_at, announcements.created_at)) <= ?', [$filters['to']]));
    }

    private function attendanceCounts(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $counts = DB::table('attendance_records as records')
            ->join('attendance_sessions as sessions', 'records.session_id', '=', 'sessions.id')
            ->whereIn('sessions.project_id', $projectIds)
            ->whereIn('records.status', ['valid', 'present', 'attended', 'completed'])
            ->selectRaw('sessions.project_id, COUNT(DISTINCT records.user_id) as total')
            ->groupBy('sessions.project_id')
            ->pluck('total', 'project_id')
            ->all();
        $externalCounts = DB::table('project_participant_enrollments')
            ->whereIn('project_id', $projectIds)
            ->whereNotNull('attendance_session_id')
            ->whereIn('attendance_status', ['valid', 'present', 'attended', 'completed'])
            ->selectRaw('project_id, COUNT(DISTINCT id) as total')
            ->groupBy('project_id')
            ->pluck('total', 'project_id');

        foreach ($externalCounts as $projectId => $total) {
            $counts[$projectId] = ($counts[$projectId] ?? 0) + (int) $total;
        }

        return $counts;
    }

    private function participationCounts(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $attendanceParticipants = DB::table('attendance_records as records')
            ->join('attendance_sessions as sessions', 'records.session_id', '=', 'sessions.id')
            ->whereIn('sessions.project_id', $projectIds)
            ->whereIn('records.status', ['valid', 'present', 'attended', 'completed'])
            ->select('sessions.project_id', 'records.user_id');
        $enrolledParticipants = DB::table('project_participant_enrollments')
            ->whereNotNull('user_id')
            ->select('project_id', 'user_id');
        $participants = $attendanceParticipants->union($enrolledParticipants);

        $counts = DB::query()
            ->fromSub($participants, 'activity_participants_union')
            ->join('users as participants', 'activity_participants_union.user_id', '=', 'participants.id')
            ->whereIn('activity_participants_union.project_id', $projectIds)
            ->selectRaw(
                'activity_participants_union.project_id,
                COUNT(DISTINCT activity_participants_union.user_id) as total,
                COUNT(DISTINCT CASE WHEN participants.participant_type = ? THEN activity_participants_union.user_id END) as students,
                COUNT(DISTINCT CASE WHEN participants.participant_type = ? THEN activity_participants_union.user_id END) as employees,
                COUNT(DISTINCT CASE WHEN participants.participant_type = ? THEN activity_participants_union.user_id END) as alumni',
                ['student', 'employee', 'alumni']
            )
            ->groupBy('activity_participants_union.project_id')
            ->get()
            ->keyBy('project_id')
            ->all();
        $guestCounts = DB::table('project_participant_enrollments')
            ->whereNull('user_id')
            ->whereIn('project_id', $projectIds)
            ->selectRaw('project_id, COUNT(DISTINCT id) as guests')
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        foreach ($guestCounts as $projectId => $guestCount) {
            $counts[$projectId] ??= (object) [
                'project_id' => $projectId,
                'total' => 0,
                'students' => 0,
                'employees' => 0,
                'alumni' => 0,
            ];
            $counts[$projectId]->guests = (int) $guestCount->guests;
            $counts[$projectId]->total += (int) $guestCount->guests;
        }

        return $counts;
    }

    private function reportData(string $report, array $filters): array
    {
        return match ($report) {
            'accomplishment' => $this->accomplishmentRows($filters),
            'volunteer-roster' => $this->volunteerRows($filters),
            'participation' => $this->participationRows($filters),
            'attendance' => $this->attendanceRows($filters),
            'compliance' => $this->complianceRows($filters),
            'activity-status' => $this->activityStatusRows($filters),
            'evaluation-summary' => $this->evaluationRows($filters),
        };
    }

    private function accomplishmentRows(array $filters): array
    {
        $activities = $this->activities($filters)
            ->whereHas('project', fn (Builder $query) => $query->where('status', 'csa_cleared'))
            ->whereNotNull('event_date')
            ->whereDate('event_date', '<', now()->toDateString())
            ->orderBy('event_date')
            ->get();
        $attendance = $this->attendanceCounts($activities->pluck('project_id')->unique()->all());

        return [
            'columns' => ['Organization', 'Activity', 'Date conducted', 'Approved volunteers', 'Participants recorded', 'Hours rendered'],
            'rows' => $activities->map(function ($activity) use ($attendance) {
                /** @var Announcement $activity */
                return [
                    $activity->creator->organization_name ?? $activity->creator->name,
                    $activity->title,
                    $activity->event_date?->format('M j, Y') ?? '—',
                    $activity->approved_volunteers,
                    $attendance[$activity->project_id] ?? 0,
                    $activity->project->hours_rendered ?? 0,
                ];
            })->all(),
        ];
    }

    private function volunteerRows(array $filters): array
    {
        $query = VolunteerApplication::query()
            ->with(['applicant', 'announcement.creator'])
            ->whereHas('announcement', function (Builder $query) use ($filters) {
                $query->whereHas('creator', function (Builder $creator) use ($filters) {
                    $creator->where('role', 'student_org')
                        ->when($filters['organization'], fn ($builder) => $builder->whereKey($filters['organization']));
                })
                    ->whereHas('project.creator', fn (Builder $creator) => $creator->where('role', 'student_org'));
            });
        $applications = $this->applyPeriod($query, 'volunteer_applications.created_at', $filters)
            ->orderBy('created_at')
            ->get();

        return [
            'columns' => ['Organization', 'Activity', 'Volunteer', 'ID number', 'Application status', 'Applied'],
            'rows' => $applications->map(function ($application) {
                /** @var VolunteerApplication $application */
                return [
                    $application->announcement->creator->organization_name ?? $application->announcement->creator->name,
                    $application->announcement->title,
                    $application->applicant->name,
                    $application->applicant->id_number ?? '',
                    ucfirst($application->status),
                    $application->created_at->format('M j, Y'),
                ];
            })->all(),
        ];
    }

    private function participationRows(array $filters): array
    {
        $activities = $this->activities($filters)->orderBy('event_date')->get();
        $participation = $this->participationCounts($activities->pluck('project_id')->unique()->all());

        return [
            'columns' => ['Organization', 'Activity', 'Activity date', 'Unique participants', 'Students', 'Employees', 'Alumni', 'Other assigned participants'],
            'rows' => $activities->map(function ($activity) use ($participation) {
                /** @var Announcement $activity */
                return [
                    $activity->creator->organization_name ?? $activity->creator->name,
                    $activity->title,
                    $activity->event_date?->format('M j, Y') ?? 'Not scheduled',
                    $participation[$activity->project_id]->total ?? 0,
                    $participation[$activity->project_id]->students ?? 0,
                    $participation[$activity->project_id]->employees ?? 0,
                    $participation[$activity->project_id]->alumni ?? 0,
                    $participation[$activity->project_id]->guests ?? 0,
                ];
            })->all(),
        ];
    }

    private function attendanceRows(array $filters): array
    {
        $records = AttendanceRecord::query()
            ->join('attendance_sessions as sessions', 'attendance_records.session_id', '=', 'sessions.id')
            ->join('projects', 'sessions.project_id', '=', 'projects.id')
            ->join('users as organizations', 'projects.created_by', '=', 'organizations.id')
            ->join('users as participants', 'attendance_records.user_id', '=', 'participants.id')
            ->where('organizations.role', 'student_org')
            ->when($filters['organization'], fn (Builder $query) => $query->where('organizations.id', $filters['organization']))
            ->whereBetween(DB::raw('COALESCE(attendance_records.scanned_at, attendance_records.created_at)'), [$filters['start'], $filters['end']])
            ->when($filters['from'], fn (Builder $query) => $query->whereDate(DB::raw('COALESCE(attendance_records.scanned_at, attendance_records.created_at)'), '>=', $filters['from']))
            ->when($filters['to'], fn (Builder $query) => $query->whereDate(DB::raw('COALESCE(attendance_records.scanned_at, attendance_records.created_at)'), '<=', $filters['to']))
            ->orderBy('projects.title')
            ->orderBy('participants.name')
            ->get([
                'organizations.organization_name',
                'organizations.name as organization_account_name',
                'projects.title as project_title',
                'participants.name as participant_name',
                'participants.id_number',
                'attendance_records.scanned_at',
                'attendance_records.created_at as recorded_at',
                'attendance_records.status',
            ]);

        $rows = $records->map(fn ($record) => [
                $record->organization_name ?: $record->organization_account_name,
                $record->project_title,
                $record->participant_name,
                $record->id_number ?? '',
                $record->scanned_at || $record->recorded_at
                    ? \Illuminate\Support\Carbon::parse($record->scanned_at ?? $record->recorded_at)->format('M j, Y g:i A')
                    : '—',
                ucfirst(str_replace('_', ' ', $record->status)),
            ]);
        $externalRecords = DB::table('project_participant_enrollments as ap')
            ->join('attendance_sessions as sessions', 'ap.attendance_session_id', '=', 'sessions.id')
            ->join('projects', 'sessions.project_id', '=', 'projects.id')
            ->join('users as organizations', 'projects.created_by', '=', 'organizations.id')
            ->where('organizations.role', 'student_org')
            ->whereNull('ap.user_id')
            ->whereNotNull('ap.attendance_recorded_at')
            ->when($filters['organization'], fn (Builder $query) => $query->where('organizations.id', $filters['organization']))
            ->whereBetween('ap.attendance_recorded_at', [$filters['start'], $filters['end']])
            ->when($filters['from'], fn (Builder $query) => $query->whereDate('ap.attendance_recorded_at', '>=', $filters['from']))
            ->when($filters['to'], fn (Builder $query) => $query->whereDate('ap.attendance_recorded_at', '<=', $filters['to']))
            ->orderBy('projects.title')
            ->orderBy('ap.participant_name')
            ->get([
                'organizations.organization_name',
                'organizations.name as organization_account_name',
                'projects.title as project_title',
                'ap.participant_name',
                'ap.participant_id_number',
                'ap.roster_code',
                'ap.attendance_recorded_at',
                'ap.attendance_status',
            ])
            ->map(fn ($record) => [
                $record->organization_name ?: $record->organization_account_name,
                $record->project_title,
                $record->participant_name,
                $record->participant_id_number ?? $record->roster_code,
                \Illuminate\Support\Carbon::parse($record->attendance_recorded_at)->format('M j, Y g:i A'),
                ucfirst(str_replace('_', ' ', $record->attendance_status)),
            ]);

        return [
            'columns' => ['Organization', 'Project', 'Participant', 'ID number', 'Recorded at', 'Attendance status'],
            'rows' => $rows->concat($externalRecords)->all(),
        ];
    }

    private function complianceRows(array $filters): array
    {
        $projects = $this->projects($filters)->orderBy('created_at')->get();

        return [
            'columns' => ['Organization', 'Proposal', 'Submitted', 'Review status', 'Attachments', 'Latest reviewer note'],
            'rows' => $projects->map(function ($project) {
                /** @var Project $project */
                return [
                    $project->creator->organization_name ?? $project->creator->name,
                    $project->title,
                    $project->created_at->format('M j, Y'),
                    Project::statusLabel($project->status),
                    $project->attachments_count,
                    $project->review_comments ?? '',
                ];
            })->all(),
        ];
    }

    private function activityStatusRows(array $filters): array
    {
        $activities = $this->activities($filters)->orderBy('event_date')->get();

        return [
            'columns' => ['Organization', 'Activity', 'Project review status', 'Publication status', 'Activity date', 'Venue', 'Approved volunteers'],
            'rows' => $activities->map(function ($activity) {
                /** @var Announcement $activity */
                return [
                    $activity->creator->organization_name ?? $activity->creator->name,
                    $activity->title,
                    Project::statusLabel($activity->project->status ?? null),
                    ucfirst($activity->status),
                    $activity->event_date?->format('M j, Y') ?? 'Not scheduled',
                    $activity->event_location ?? '',
                    $activity->approved_volunteers,
                ];
            })->all(),
        ];
    }

    private function evaluationRows(array $filters): array
    {
        $evaluations = Evaluation::query()
            ->with(['announcement.creator', 'announcement.project'])
            ->withCount('responses')
            ->whereHas('announcement', function (Builder $query) use ($filters) {
                $query->whereHas('creator', function (Builder $creator) use ($filters) {
                    $creator->where('role', 'student_org')
                        ->when($filters['organization'], fn (Builder $builder) => $builder->whereKey($filters['organization']));
                })
                    ->whereHas('project.creator', fn (Builder $creator) => $creator->where('role', 'student_org'))
                    ->whereRaw('COALESCE(announcements.event_date, announcements.published_at, announcements.created_at) BETWEEN ? AND ?', [$filters['start'], $filters['end']])
                    ->when($filters['from'], fn (Builder $builder) => $builder->whereRaw('DATE(COALESCE(announcements.event_date, announcements.published_at, announcements.created_at)) >= ?', [$filters['from']]))
                    ->when($filters['to'], fn (Builder $builder) => $builder->whereRaw('DATE(COALESCE(announcements.event_date, announcements.published_at, announcements.created_at)) <= ?', [$filters['to']]));
            })
            ->orderByDesc('evaluations.created_at')
            ->get();
        $averages = DB::table('evaluation_response_ratings as ratings')
            ->join('evaluation_responses as responses', 'ratings.response_id', '=', 'responses.id')
            ->whereIn('responses.evaluation_id', $evaluations->modelKeys())
            ->selectRaw('responses.evaluation_id, responses.respondent_type, ROUND(AVG(ratings.score), 2) as average_score')
            ->groupBy('responses.evaluation_id', 'responses.respondent_type')
            ->get()
            ->keyBy(fn ($average) => $average->evaluation_id.':'.$average->respondent_type);
        $responseCounts = DB::table('evaluation_responses')
            ->whereIn('evaluation_id', $evaluations->modelKeys())
            ->selectRaw('evaluation_id, respondent_type, COUNT(*) as total')
            ->groupBy('evaluation_id', 'respondent_type')
            ->get()
            ->keyBy(fn ($count) => $count->evaluation_id.':'.$count->respondent_type);

        return [
            'columns' => ['Organization', 'Activity', 'Evaluation status', 'Expected beneficiaries', 'Beneficiary responses', 'Volunteer / implementer responses', 'Beneficiary average (1–5)', 'Volunteer / implementer average (1–5)', 'Submitted'],
            'rows' => $evaluations->map(function ($evaluation) use ($averages, $responseCounts) {
                /** @var Evaluation $evaluation */
                return [
                    $evaluation->announcement->creator->organization_name ?? $evaluation->announcement->creator->name,
                    $evaluation->announcement->title,
                    $evaluation->statusLabel(),
                    $evaluation->expected_beneficiaries ?? 'Not recorded',
                    $responseCounts[$evaluation->id.':beneficiary']->total ?? 0,
                    $responseCounts[$evaluation->id.':implementer']->total ?? 0,
                    $averages[$evaluation->id.':beneficiary']->average_score ?? 'No ratings',
                    $averages[$evaluation->id.':implementer']->average_score ?? 'No ratings',
                    $evaluation->submitted_at?->format('M j, Y') ?? 'Not submitted',
                ];
            })->all(),
        ];
    }

    private function csv(string $filename, array $columns, array $rows): StreamedResponse
    {
        return response()->stream(function () use ($columns, $rows) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $columns);
            foreach ($rows as $row) {
                fputcsv($stream, array_map(function ($value) {
                    $value = (string) $value;

                    return preg_match('/^\s*[=+\-@]/', $value) ? "'" . $value : $value;
                }, $row));
            }
            fclose($stream);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function index(Request $request)
    {
        $this->guard($request);
        $filters = $this->filters($request);

        return view('CSA_DIRECTOR.reports.index', [
            'reports' => self::REPORTS,
            'organizations' => $this->organizations(),
            'years' => $this->years(),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, string $report)
    {
        $this->guard($request);
        abort_unless(isset(self::REPORTS[$report]), 404);

        $filters = $this->filters($request);
        $data = $this->reportData($report, $filters);

        if ($request->query('format') === 'csv') {
            return $this->csv($report . '-' . $filters['ay'] . '.csv', $data['columns'], $data['rows']);
        }

        return view('CSA_DIRECTOR.reports.show', [
            'report' => $report,
            'definition' => self::REPORTS[$report],
            'columns' => $data['columns'],
            'rows' => $data['rows'],
            'organizations' => $this->organizations(),
            'years' => $this->years(),
            'filters' => $filters,
        ]);
    }
}
