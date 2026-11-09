<?php

namespace App\Http\Controllers;

use App\Models\Project;
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
            'title' => 'Attendance',
            'description' => 'Review attendance recorded for your organization’s activities.',
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
            'start' => $range[0],
            'end' => $range[1],
        ];
    }

    private function projects(Request $request, array $filters)
    {
        return DB::table('projects as p')
            ->where('p.created_by', $request->user()->id)
            ->whereBetween('p.created_at', [$filters['start'], $filters['end']])
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
            $rows = $projects->map(fn ($project) => [
                $project->title,
                (int) ($attendance[$project->id]->attended ?? 0) + (int) ($externalAttendance[$project->id]->attended ?? 0),
                (int) ($attendance[$project->id]->records ?? 0) + (int) ($externalAttendance[$project->id]->records ?? 0),
            ]);
            $summary = [
                'Projects' => $projects->count(),
                'Attendance records' => (int) $attendance->sum('records') + (int) $externalAttendance->sum('records'),
                'Recorded as attended' => (int) $attendance->sum('attended') + (int) $externalAttendance->sum('attended'),
            ];
            $headings = ['Project', 'Attended', 'Attendance records'];
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
            'years' => AcademicPeriod::labelsFrom(DB::table('projects')->where('created_by', $request->user()->id)->pluck('created_at')),
        ]);
    }
}
