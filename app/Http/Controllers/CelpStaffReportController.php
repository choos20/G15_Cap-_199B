<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Support\AcademicPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CelpStaffReportController extends Controller
{
    private const REPORTS = [
        'activity-progress' => [
            'title' => 'Activity progress',
            'description' => 'Track project review stages and linked activity announcements.',
        ],
        'participant-involvement' => [
            'title' => 'Participant involvement',
            'description' => 'See approved volunteers and participants assigned to each project.',
        ],
        'attendance' => [
            'title' => 'Attendance',
            'description' => 'Review recorded attendance across projects and activities.',
        ],
        'evaluation-results' => [
            'title' => 'Evaluation results',
            'description' => 'Check evaluation completion, average ratings, and participant feedback.',
        ],
    ];

    private function guard(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['coordinator', 'celp_secretary'], true), 403, 'Not allowed');
    }

    private function reportRoutes(Request $request): array
    {
        $prefix = $request->user()->role === 'celp_secretary'
            ? 'reports.secretary'
            : 'reports.coordinator';

        return [
            'index' => $prefix,
            'show' => $prefix.'.show',
        ];
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'ay' => ['nullable', 'regex:/^\d{4}-\d{4}$/'],
            'term' => ['nullable', Rule::in(array_keys(AcademicPeriod::TERMS))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'initiator' => ['nullable', 'integer'],
        ]);
        $ay = $validated['ay'] ?? AcademicPeriod::current();
        $period = AcademicPeriod::range($ay, $validated['term'] ?? null);

        abort_unless($period && preg_match('/^(\d{4})-(\d{4})$/', $ay, $parts)
            && (int) $parts[2] === (int) $parts[1] + 1, 422, 'The selected academic year is invalid.');

        if (! empty($validated['initiator'])) {
            abort_unless(User::query()
                ->whereKey($validated['initiator'])
                ->whereIn('role', ['student_org', 'college_department'])
                ->exists(), 404);
        }

        return [
            'ay' => $ay,
            'term' => $validated['term'] ?? '',
            'from' => $validated['from'] ?? '',
            'to' => $validated['to'] ?? '',
            'initiator' => $validated['initiator'] ?? '',
            'start' => $period[0],
            'end' => $period[1],
        ];
    }

    private function initiators()
    {
        return User::query()
            ->whereIn('role', ['student_org', 'college_department'])
            ->orderByRaw('COALESCE(organization_name, name)')
            ->get(['id', 'name', 'organization_name']);
    }

    private function projects(array $filters)
    {
        return DB::table('projects as p')
            ->join('users as initiators', 'initiators.id', '=', 'p.created_by')
            ->whereIn('initiators.role', ['student_org', 'college_department'])
            ->whereBetween('p.created_at', [$filters['start'], $filters['end']])
            ->when($filters['from'], fn (Builder $query) => $query->whereDate('p.created_at', '>=', $filters['from']))
            ->when($filters['to'], fn (Builder $query) => $query->whereDate('p.created_at', '<=', $filters['to']))
            ->when($filters['initiator'], fn (Builder $query) => $query->where('initiators.id', $filters['initiator']))
            ->select([
                'p.id',
                'p.title',
                'p.status',
                'p.hours_rendered',
                'p.created_at',
                'initiators.organization_name',
                'initiators.name as initiator_name',
            ])
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

        return view('CELP.coordinator.reports.index', [
            'reports' => self::REPORTS,
            'pageTitle' => $request->user()->role === 'celp_secretary' ? 'Secretary Reports' : 'Coordinator Reports',
            'reportRoutes' => $this->reportRoutes($request),
        ]);
    }

    public function show(Request $request, string $report)
    {
        $this->guard($request);
        abort_unless(isset(self::REPORTS[$report]), 404);

        $filters = $this->filters($request);
        $projects = $this->projects($filters);
        $projectIds = $projects->pluck('id')->all();
        $rows = collect();
        $summary = [];
        $criteria = collect();
        $feedback = collect();

        if ($report === 'activity-progress') {
            $announcementCounts = DB::table('announcements')
                ->whereIn('project_id', $projectIds ?: [0])
                ->selectRaw('project_id, COUNT(*) as total')
                ->groupBy('project_id')
                ->pluck('total', 'project_id');
            $summary = [
                'Projects in period' => $projects->count(),
                'Approved / cleared' => $projects->whereIn('status', ['approved', 'csa_cleared'])->count(),
                'In review' => $projects->whereIn('status', ['submitted', 'endorsed_by_secretary', 'endorsed_by_coordinator'])->count(),
                'Returned' => $projects->filter(fn ($project) => str_starts_with($project->status, 'returned_by_'))->count(),
            ];
            $rows = $projects->map(fn ($project) => [
                $project->organization_name ?: $project->initiator_name,
                $project->title,
                Project::statusLabel($project->status),
                $announcementCounts[$project->id] ?? 0,
                $project->created_at ? \Illuminate\Support\Carbon::parse($project->created_at)->format('M j, Y') : '—',
            ]);
            $headings = ['Initiator', 'Project', 'Review status', 'Linked announcements', 'Submitted'];
        } elseif ($report === 'participant-involvement') {
            $volunteers = DB::table('volunteer_applications as applications')
                ->join('announcements', 'announcements.id', '=', 'applications.announcement_id')
                ->whereIn('announcements.project_id', $projectIds ?: [0])
                ->where('applications.status', 'approved')
                ->selectRaw('announcements.project_id, COUNT(DISTINCT applications.user_id) as total')
                ->groupBy('announcements.project_id')
                ->pluck('total', 'project_id');
            $assigned = DB::table('project_participant_enrollments')
                ->whereIn('project_id', $projectIds ?: [0])
                ->selectRaw('project_id, COUNT(DISTINCT user_id) + SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as total')
                ->groupBy('project_id')
                ->pluck('total', 'project_id');
            $summary = [
                'Projects' => $projects->count(),
                'Approved volunteers' => (int) $volunteers->sum(),
                'Assigned participants' => (int) $assigned->sum(),
            ];
            $rows = $projects->map(fn ($project) => [
                $project->organization_name ?: $project->initiator_name,
                $project->title,
                $volunteers[$project->id] ?? 0,
                $assigned[$project->id] ?? 0,
                ($volunteers[$project->id] ?? 0) + ($assigned[$project->id] ?? 0),
            ]);
            $headings = ['Initiator', 'Project', 'Approved volunteers', 'Assigned participants', 'Involvement records'];
        } elseif ($report === 'attendance') {
            $records = DB::table('attendance_records as records')
                ->join('attendance_sessions as sessions', 'sessions.id', '=', 'records.session_id')
                ->whereIn('sessions.project_id', $projectIds ?: [0])
                ->selectRaw('sessions.project_id, COUNT(*) as total, COUNT(DISTINCT CASE WHEN records.status IN (?, ?, ?, ?) THEN records.user_id END) as attended', ['valid', 'present', 'attended', 'completed'])
                ->groupBy('sessions.project_id')
                ->get()
                ->keyBy('project_id');
            $external = DB::table('project_participant_enrollments')
                ->whereIn('project_id', $projectIds ?: [0])
                ->whereNotNull('attendance_recorded_at')
                ->selectRaw('project_id, COUNT(*) as total, COUNT(DISTINCT CASE WHEN attendance_status IN (?, ?, ?, ?) THEN id END) as attended', ['valid', 'present', 'attended', 'completed'])
                ->groupBy('project_id')
                ->get()
                ->keyBy('project_id');
            $summary = [
                'Projects' => $projects->count(),
                'Attendance records' => (int) $records->sum('total') + (int) $external->sum('total'),
                'Recorded as attended' => (int) $records->sum('attended') + (int) $external->sum('attended'),
            ];
            $rows = $projects->map(fn ($project) => [
                $project->organization_name ?: $project->initiator_name,
                $project->title,
                ($records[$project->id]->attended ?? 0) + ($external[$project->id]->attended ?? 0),
                ($records[$project->id]->total ?? 0) + ($external[$project->id]->total ?? 0),
            ]);
            $headings = ['Initiator', 'Project', 'Attended', 'Attendance records'];
        } else {
            $activities = DB::table('announcements as announcements')
                ->join('users as initiators', 'initiators.id', '=', 'announcements.created_by')
                ->join('projects', 'projects.id', '=', 'announcements.project_id')
                ->leftJoin('evaluations', 'evaluations.announcement_id', '=', 'announcements.id')
                ->whereIn('announcements.project_id', $projectIds ?: [0])
                ->select([
                    'announcements.id',
                    'announcements.project_id',
                    'announcements.title',
                    'announcements.event_date',
                    'announcements.created_at',
                    'initiators.name as initiator_name',
                    'initiators.organization_name',
                    'evaluations.id as evaluation_id',
                    'evaluations.expected_beneficiaries',
                    'evaluations.submitted_at',
                ])
                ->orderByDesc('announcements.event_date')
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
            $criteriaScores = DB::table('evaluation_response_ratings as ratings')
                ->join('evaluation_responses as responses', 'responses.id', '=', 'ratings.response_id')
                ->join('evaluation_criteria as criteria', 'criteria.id', '=', 'ratings.criterion_id')
                ->whereIn('responses.evaluation_id', $evaluationIds ?: [0])
                ->selectRaw('criteria.short_label, responses.respondent_type, ROUND(AVG(ratings.score), 2) as average, COUNT(*) as ratings')
                ->groupBy('criteria.id', 'criteria.short_label', 'criteria.sort_order', 'responses.respondent_type')
                ->orderBy('criteria.sort_order')
                ->get();
            $feedback = DB::table('evaluation_responses as responses')
                ->join('evaluations', 'evaluations.id', '=', 'responses.evaluation_id')
                ->join('announcements', 'announcements.id', '=', 'evaluations.announcement_id')
                ->whereIn('evaluations.id', $evaluationIds ?: [0])
                ->where(function (Builder $query) {
                    $query->whereNotNull('responses.suggestions')
                        ->orWhereNotNull('responses.observations');
                })
                ->select('announcements.title as activity', 'responses.suggestions', 'responses.observations')
                ->orderByDesc('responses.created_at')
                ->get();
            $criteria = $criteriaScores;
            $summary = [
                'Activities' => $activities->count(),
                'Evaluations submitted' => $activities->whereNotNull('submitted_at')->count(),
                'Responses recorded' => (int) $responseCountsByType->sum('total'),
                'Beneficiary average' => isset($averageByType['beneficiary']) ? number_format((float) $averageByType['beneficiary'], 2).' / 5' : '—',
                'Volunteer / implementer average' => isset($averageByType['implementer']) ? number_format((float) $averageByType['implementer'], 2).' / 5' : '—',
            ];
            $rows = $activities->map(fn ($activity) => [
                $activity->organization_name ?: $activity->initiator_name,
                $activity->title,
                $activity->submitted_at ? 'Submitted' : ($activity->evaluation_id ? 'In progress' : 'Not started'),
                $activity->evaluation_id ? ($responseCountsByType[$activity->evaluation_id.':beneficiary']->total ?? 0) : 0,
                $activity->evaluation_id ? ($responseCountsByType[$activity->evaluation_id.':implementer']->total ?? 0) : 0,
                $activity->expected_beneficiaries ?? '—',
                isset($averages[$activity->evaluation_id.':beneficiary']) ? number_format((float) $averages[$activity->evaluation_id.':beneficiary']->average, 2).' / 5' : '—',
                isset($averages[$activity->evaluation_id.':implementer']) ? number_format((float) $averages[$activity->evaluation_id.':implementer']->average, 2).' / 5' : '—',
            ]);
            $headings = ['Initiator', 'Activity', 'Evaluation status', 'Beneficiary responses', 'Volunteer / implementer responses', 'Expected participants', 'Beneficiary average', 'Volunteer / implementer average'];
        }

        if ($request->query('format') === 'csv') {
            return $this->csv(
                $report.'-'.$filters['ay'].'.csv',
                $headings,
                $rows
            );
        }

        return view('CELP.coordinator.reports.show', [
            'report' => $report,
            'reportInfo' => self::REPORTS[$report],
            'summary' => $summary,
            'rows' => $rows,
            'headings' => $headings,
            'criteria' => $criteria,
            'feedback' => $feedback,
            'years' => AcademicPeriod::labelsFrom(DB::table('projects')->pluck('created_at')),
            'initiators' => $this->initiators(),
            'filters' => $filters,
            'reportRoutes' => $this->reportRoutes($request),
        ]);
    }
}
