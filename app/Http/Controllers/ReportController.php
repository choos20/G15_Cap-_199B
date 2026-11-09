<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\ActivityParticipant;
use App\Models\AuditLog;
use App\Models\AttendanceRecord;
use App\Models\Evaluation;
use App\Models\EvaluationCriterion;
use App\Models\Project;
use App\Models\User;
use App\Support\AcademicPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CELP Head accomplishment reports.
 *
 * Each report builds one row collection, then renders it three ways from the
 * same data: on-screen, print (Ctrl+P -> Save as PDF, with signature blocks),
 * or CSV download. Keeping the query in one place is what stops the three
 * outputs from drifting apart.
 *
 * Periods are derived from dates via AcademicPeriod - the schema has no
 * academic_year/term columns.
 */
class ReportController extends Controller
{
    private function guard(Request $request): void
    {
        if ($request->user()->role !== 'celp_head') {
            abort(403, 'Not allowed');
        }
    }

    /**
     * Shared filter state pulled off the query string.
     */
    private function filters(Request $request): array
    {
        $ay = $request->query('ay') ?: AcademicPeriod::current();
        $term = $request->query('term') ?: null;
        $range = AcademicPeriod::range($ay, $term);

        return [
            'ay' => $ay,
            'term' => $term,
            'start' => $range[0] ?? null,
            'end' => $range[1] ?? null,
            'from' => $request->query('from') ?: null,
            'to' => $request->query('to') ?: null,
            'initiator' => $request->query('initiator') ?: null,
            'status' => $request->query('status') ?: null,
            'print' => (bool) $request->query('print'),
        ];
    }

    /**
     * Academic years that actually contain data, for the filter dropdown.
     */
    private function availableYears(): array
    {
        return AcademicPeriod::labelsFrom(
            Project::pluck('created_at')->all()
        );
    }

    private function initiators()
    {
        return User::whereIn('role', ['student_org', 'college_department'])
            ->orderByRaw('COALESCE(organization_name, name)')
            ->get(['id', 'name', 'organization_name', 'role']);
    }

    /**
     * Stream rows as CSV. A UTF-8 BOM is written first so Excel on Windows
     * renders names like "Napiñas" correctly instead of mojibake.
     */
    private function csv(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->stream(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function index(Request $request)
    {
        $this->guard($request);

        return view('CELP.head.reports.index', [
            'ay' => AcademicPeriod::current(),
        ]);
    }

    // ---------------------------------------------------------------- report 1

    /**
     * Project status summary — the review pipeline for one period, with a
     * like-for-like comparison against the previous academic year.
     */
    public function projectStatus(Request $request)
    {
        $this->guard($request);
        $f = $this->filters($request);

        $scoped = fn ($q) => $q
            ->when($f['start'], fn ($x) => $x->whereBetween('projects.created_at', [$f['start'], $f['end']]))
            ->when($f['initiator'], fn ($x) => $x->where('projects.created_by', $f['initiator']));

        $projects = Project::query()
            ->with('creator')
            ->tap($scoped)
            ->when($f['status'], fn ($q) => $q->where('status', $f['status']))
            ->orderByDesc('created_at')
            ->get();

        // Reviewer comes from the audit trail; only decisions made after the
        // audit log existed can be attributed, so older rows show a dash.
        $reviewers = AuditLog::where('subject_type', Project::class)
            ->where('action', 'like', 'project.%')
            ->orderByDesc('id')
            ->get(['subject_id', 'actor_name'])
            ->unique('subject_id')
            ->pluck('actor_name', 'subject_id');

        $bucket = fn (string $s) => match (true) {
            in_array($s, ['approved', 'csa_cleared'], true) => 'Approved',
            str_starts_with($s, 'returned_by_') => 'Returned',
            default => 'Pending',
        };

        $rows = $projects->map(function ($p) use ($bucket, $reviewers) {
            $decided = !in_array($bucket($p->status), ['Pending'], true);
            return [
                'id' => $p->id,
                'title' => $p->title,
                'initiator' => $p->creator->organization_name ?? $p->creator->name ?? 'Unknown',
                'submitted' => $p->created_at,
                'status' => Project::statusLabel($p->status),
                'group' => $bucket($p->status),
                // Still open: age to date. Decided: how long it took.
                // Carbon 3 returns a float here, so round to whole days -
                // unrounded it prints as 1.7733979285532.
                'days' => (int) round($decided
                    ? $p->created_at->diffInDays($p->updated_at)
                    : $p->created_at->diffInDays(now())),
                'open' => !$decided,
                'reviewer' => $reviewers[$p->id] ?? null,
            ];
        });

        $counts = ['Approved' => 0, 'Pending' => 0, 'Returned' => 0];
        foreach ($rows as $r) {
            $counts[$r['group']]++;
        }
        $total = array_sum($counts);

        // Previous AY, same term and initiator, for the comparison column.
        $prevAy = AcademicPeriod::previousLabel($f['ay']);
        $prevRange = AcademicPeriod::range($prevAy, $f['term']);
        $prevCounts = ['Approved' => 0, 'Pending' => 0, 'Returned' => 0];
        if ($prevRange) {
            $prev = Project::query()
                ->whereBetween('created_at', [$prevRange[0], $prevRange[1]])
                ->when($f['initiator'], fn ($q) => $q->where('created_by', $f['initiator']))
                ->pluck('status');
            foreach ($prev as $s) {
                $prevCounts[$bucket($s)]++;
            }
        }

        if ($request->query('format') === 'csv') {
            return $this->csv(
                "project-status-{$f['ay']}.csv",
                ['Project title', 'Initiator', 'Date submitted', 'Current status', 'Days in queue', 'Reviewer'],
                $rows->map(fn ($r) => [
                    $r['title'], $r['initiator'], $r['submitted']->format('Y-m-d'),
                    $r['status'], $r['days'], $r['reviewer'] ?? '',
                ])
            );
        }

        return view('CELP.head.reports.project_status', [
            'rows' => $rows, 'counts' => $counts, 'total' => $total,
            'prevCounts' => $prevCounts, 'prevAy' => $prevAy,
            'years' => $this->availableYears(), 'initiators' => $this->initiators(),
            'f' => $f,
        ]);
    }

    // ---------------------------------------------------------------- report 2

    /**
     * Activities conducted, grouped by the organization that ran them.
     */
    public function activities(Request $request)
    {
        $this->guard($request);
        $f = $this->filters($request);
        $type = $request->query('type') ?: null;

        $activities = Announcement::query()
            ->with('creator', 'project')
            ->whereNotNull('project_id')
            ->whereHas('project', fn ($q) => $q->whereIn('status', ['approved', 'csa_cleared']))
            ->withCount([
                'applications as volunteers' => fn ($q) => $q->where('status', 'approved'),
            ])
            ->when($f['start'], fn ($q) => $q->whereRaw(
                'COALESCE(event_date, published_at, announcements.created_at) BETWEEN ? AND ?',
                [$f['start'], $f['end']]
            ))
            ->when($f['from'], fn ($q) => $q->whereRaw('COALESCE(event_date, published_at, announcements.created_at) >= ?', [$f['from']]))
            ->when($f['to'], fn ($q) => $q->whereRaw('COALESCE(event_date, published_at, announcements.created_at) <= ?', [$f['to'] . ' 23:59:59']))
            ->when($f['initiator'], fn ($q) => $q->where('announcements.created_by', $f['initiator']))
            ->when($type, fn ($q) => $q->whereHas('creator', fn ($c) => $c->where('role', $type)))
            ->orderByRaw('COALESCE(event_date, published_at, announcements.created_at) DESC')
            ->get();

        // Head count actually recorded on the ground, per project.
        $attended = DB::table('attendance_records')
            ->join('attendance_sessions', 'attendance_records.session_id', '=', 'attendance_sessions.id')
            ->whereIn('attendance_records.status', ['valid', 'present', 'attended', 'completed'])
            ->groupBy('attendance_sessions.project_id')
            // Aliased: pluck() reads the column as a property name, and a bare
            // "count(...)" expression isn't one.
            ->selectRaw('attendance_sessions.project_id, count(distinct attendance_records.user_id) as total')
            ->pluck('total', 'project_id');

        $rows = $activities->map(fn ($a) => [
            'initiator' => $a->creator->organization_name ?? $a->creator->name ?? 'Unknown',
            // Co-curricular / Extra-curricular for student orgs, College /
            // Department for the rest — the taxonomy the CELP Head asked for.
            'type' => $a->creator?->initiatorTypeLabel() ?? role_label($a->creator->role ?? null),
            'title' => $a->title,
            'date' => $a->event_date ?? $a->published_at ?? $a->created_at,
            'dated' => (bool) $a->event_date,
            'venue' => $a->event_location,
            'volunteers' => $a->volunteers,
            'attended' => $attended[$a->project_id] ?? 0,
            'status' => $a->event_date && $a->event_date->isPast() ? 'Conducted' : 'Scheduled',
            'project' => $a->project->title ?? null,
        ]);

        if ($request->query('format') === 'csv') {
            return $this->csv(
                "activities-{$f['ay']}.csv",
                ['Initiator', 'Initiator type', 'Activity title', 'Date conducted', 'Venue', 'Volunteers', 'Attended', 'Status'],
                $rows->map(fn ($r) => [
                    $r['initiator'], $r['type'], $r['title'],
                    $r['dated'] ? $r['date']->format('Y-m-d') : 'Not scheduled',
                    $r['venue'] ?? '', $r['volunteers'], $r['attended'], $r['status'],
                ])
            );
        }

        return view('CELP.head.reports.activities', [
            'groups' => $rows->groupBy('initiator'),
            'rows' => $rows,
            'years' => $this->availableYears(),
            'initiators' => $this->initiators(),
            'type' => $type,
            'f' => $f,
        ]);
    }

    // ---------------------------------------------------------------- report 3

    /**
     * Attendance roster. Printable with a signature column for on-site use.
     */
    public function attendance(Request $request)
    {
        $this->guard($request);
        $f = $this->filters($request);
        $projectId = $request->query('project') ?: null;

        $records = DB::table('attendance_records as ar')
            ->join('attendance_sessions as s', 'ar.session_id', '=', 's.id')
            ->join('projects as p', 's.project_id', '=', 'p.id')
            ->join('users as u', 'ar.user_id', '=', 'u.id')
            ->select(
                'ar.scanned_at', 'ar.updated_at', 'ar.status', 'ar.attendance_source',
                'ar.check_in_status', 'ar.check_out_status',
                'p.id as project_id', 'p.title as activity',
                'u.name', 'u.id_number', 'u.participant_type', 'u.affiliation', 'u.role'
            )
            ->when($f['start'], fn ($q) => $q->whereRaw('COALESCE(ar.scanned_at, ar.updated_at) BETWEEN ? AND ?', [$f['start'], $f['end']]))
            ->when($f['from'], fn ($q) => $q->whereDate(DB::raw('COALESCE(ar.scanned_at, ar.updated_at)'), '>=', $f['from']))
            ->when($f['to'], fn ($q) => $q->whereDate(DB::raw('COALESCE(ar.scanned_at, ar.updated_at)'), '<=', $f['to']))
            ->when($projectId, fn ($q) => $q->where('p.id', $projectId))
            ->when($f['status'], fn ($q) => $q->where('ar.status', $f['status']))
            ->orderBy('p.title')
            ->orderBy('u.name')
            ->get();

        $formatAttendanceRow = fn ($r) => [
            'activity' => $r->activity,
            'name' => $r->name,
            'id_number' => $r->id_number ?? $r->roster_code ?? null,
            // Student / Employee / Alumni now that participants are classified.
            'type' => User::PARTICIPANT_TYPES[$r->participant_type] ?? ($r->role === 'external_participant' ? 'External participant' : role_label($r->role)),
            'department' => $r->affiliation,
            'scanned_at' => ($r->scanned_at ?? $r->updated_at ?? null) ? Carbon::parse($r->scanned_at ?? $r->updated_at) : null,
            'status' => match ($r->status) {
                'valid', 'attended' => 'Attended',
                'completed' => 'Completed',
                'partial' => 'Partial',
                'absent' => 'Absent',
                'out_of_range' => 'Outside geofence',
                'not_enrolled' => 'Not enrolled',
                default => ucfirst(str_replace('_', ' ', $r->status)),
            },
            'attendance_source' => $r->attendance_source,
            'check_in_status' => $r->check_in_status,
            'check_out_status' => $r->check_out_status,
        ];
        $externalRecords = DB::table('project_participant_enrollments as ap')
            ->join('attendance_sessions as s', 'ap.attendance_session_id', '=', 's.id')
            ->join('projects as p', 's.project_id', '=', 'p.id')
            ->join('users as org', 'p.created_by', '=', 'org.id')
            ->whereNull('ap.user_id')
            ->whereNotNull('ap.attendance_recorded_at')
            ->select(
                'ap.attendance_recorded_at as updated_at',
                'ap.attendance_status as status',
                'ap.attendance_check_in as check_in_status',
                'ap.attendance_check_out as check_out_status',
                'p.id as project_id',
                'p.title as activity',
                'ap.participant_name as name',
                'ap.participant_id_number as id_number',
                'ap.roster_code',
            )
            ->selectRaw('NULL as scanned_at, NULL as participant_type, NULL as affiliation, NULL as distance_meters, NULL as scanned_lat, NULL as scanned_lng, ? as attendance_source, ? as role', ['manual_csv', 'external_participant'])
            ->when($f['start'], fn ($q) => $q->whereBetween('ap.attendance_recorded_at', [$f['start'], $f['end']]))
            ->when($f['from'], fn ($q) => $q->whereDate('ap.attendance_recorded_at', '>=', $f['from']))
            ->when($f['to'], fn ($q) => $q->whereDate('ap.attendance_recorded_at', '<=', $f['to']))
            ->when($projectId, fn ($q) => $q->where('p.id', $projectId))
            ->when($f['status'], fn ($q) => $q->where('ap.attendance_status', $f['status']))
            ->get();

        $rows = $records->map($formatAttendanceRow)
            ->concat($externalRecords->map($formatAttendanceRow))
            ->sortBy([['activity', 'asc'], ['name', 'asc']])
            ->values();

        // Only projects that actually have attendance, so the picker can't
        // lead to an empty roster.
        $projects = Project::whereIn('id', DB::table('attendance_sessions')->select('project_id'))
            ->orderBy('title')->get(['id', 'title']);

        if ($request->query('format') === 'csv') {
            return $this->csv(
                "attendance-{$f['ay']}.csv",
                ['Activity', 'Name', 'ID number', 'Participant type', 'Department / College', 'Recorded at', 'Check-In', 'Check-Out', 'Source', 'Status'],
                $rows->map(fn ($r) => [
                    $r['activity'], $r['name'], $r['id_number'] ?? '', $r['type'],
                    $r['department'] ?? '', $r['scanned_at']?->format('Y-m-d H:i') ?? '',
                    $r['check_in_status'] ?? '', $r['check_out_status'] ?? '',
                    \in_array($r['attendance_source'], AttendanceRecord::CSV_SOURCES, true) ? 'Organizer CSV' : 'QR', $r['status'],
                ])
            );
        }

        return view('CELP.head.reports.attendance', [
            'groups' => $rows->groupBy('activity'),
            'rows' => $rows,
            'projects' => $projects,
            'projectId' => $projectId,
            'years' => $this->availableYears(),
            'f' => $f,
        ]);
    }

    // ---------------------------------------------------------------- report 4

    /**
     * Volunteer participation record — consolidated across all volunteers, or
     * one volunteer's full record for clearance/scholarship certification.
     */
    public function volunteers(Request $request)
    {
        $this->guard($request);
        $f = $this->filters($request);
        $volunteerId = $request->query('volunteer') ?: null;

        $joins = DB::table('volunteer_applications as va')
            ->join('announcements as a', 'va.announcement_id', '=', 'a.id')
            ->join('users as u', 'va.user_id', '=', 'u.id')
            ->join('users as org', 'a.created_by', '=', 'org.id')
            ->where('va.status', 'approved')
            ->select(
                'u.id as volunteer_id', 'u.name', 'u.id_number', 'u.affiliation',
                'a.title as activity', 'a.event_date', 'a.published_at',
                'org.name as org_name', 'org.organization_name',
                'va.decided_at',
                DB::raw("'Approved volunteer' as participation_type")
            )
            ->when($f['start'], fn ($q) => $q->whereRaw(
                'COALESCE(a.event_date, a.published_at, va.created_at) BETWEEN ? AND ?',
                [$f['start'], $f['end']]
            ))
            ->when($volunteerId, fn ($q) => $q->where('u.id', $volunteerId))
            ->when($f['initiator'], fn ($q) => $q->where('org.id', $f['initiator']))
            ->orderBy('u.name')
            ->orderByRaw('COALESCE(a.event_date, a.published_at) DESC')
            ->get();

        $approvedRows = $joins->map(fn ($r) => [
            'volunteer_id' => $r->volunteer_id,
            'name' => $r->name,
            'id_number' => $r->id_number,
            'department' => $r->affiliation,
            'activity' => $r->activity,
            'date' => $r->event_date ?? $r->published_at ?? $r->decided_at,
            'initiator' => $r->organization_name ?? $r->org_name,
            'participation_type' => $r->participation_type,
        ]);

        $assignedDateSql = '(SELECT COALESCE(event_date, published_at, created_at) FROM announcements WHERE announcements.project_id = p.id ORDER BY id DESC LIMIT 1)';
        $assigned = DB::table('project_participant_enrollments as ap')
            ->whereNotNull('ap.user_id')
            ->join('projects as p', 'ap.project_id', '=', 'p.id')
            ->join('users as u', 'ap.user_id', '=', 'u.id')
            ->join('users as org', 'p.created_by', '=', 'org.id')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('volunteer_applications as approved')
                    ->join('announcements as approved_announcement', 'approved.announcement_id', '=', 'approved_announcement.id')
                    ->whereColumn('approved.user_id', 'ap.user_id')
                    ->whereColumn('approved_announcement.project_id', 'ap.project_id')
                    ->where('approved.status', 'approved');
            })
            ->select(
                'u.id as volunteer_id',
                'u.name',
                'u.id_number',
                'u.affiliation',
                'p.title as activity',
                'org.name as org_name',
                'org.organization_name',
                'ap.participation_type',
            )
            ->selectRaw("COALESCE({$assignedDateSql}, ap.created_at) as participation_date")
            ->when($f['start'], fn ($q) => $q->whereRaw(
                "COALESCE({$assignedDateSql}, ap.created_at) BETWEEN ? AND ?",
                [$f['start'], $f['end']]
            ))
            ->when($f['from'], fn ($q) => $q->whereRaw("COALESCE({$assignedDateSql}, ap.created_at) >= ?", [$f['from']]))
            ->when($f['to'], fn ($q) => $q->whereRaw("COALESCE({$assignedDateSql}, ap.created_at) <= ?", [$f['to'].' 23:59:59']))
            ->when($volunteerId, fn ($q) => $q->where('u.id', $volunteerId))
            ->when($f['initiator'], fn ($q) => $q->where('org.id', $f['initiator']))
            ->get()
            ->map(fn ($r) => [
                'volunteer_id' => $r->volunteer_id,
                'name' => $r->name,
                'id_number' => $r->id_number,
                'department' => $r->affiliation,
                'activity' => $r->activity,
                'date' => $r->participation_date,
                'initiator' => $r->organization_name ?? $r->org_name,
                'participation_type' => $r->participation_type === ActivityParticipant::ATTENDANCE_REQUIRED
                    ? 'Assigned participant — attendance required'
                    : 'Assigned participant — participation only',
            ]);

        $externalAssigned = DB::table('project_participant_enrollments as ap')
            ->join('projects as p', 'ap.project_id', '=', 'p.id')
            ->join('users as org', 'p.created_by', '=', 'org.id')
            ->whereNull('ap.user_id')
            ->select(
                'ap.participant_name as name',
                'ap.participant_id_number',
                'ap.roster_code',
                'p.title as activity',
                'org.id as organization_id',
                'org.name as org_name',
                'org.organization_name',
                'ap.participation_type',
                'ap.created_at',
            )
            ->selectRaw("COALESCE({$assignedDateSql}, ap.created_at) as participation_date")
            ->when($f['start'], fn ($q) => $q->whereRaw(
                "COALESCE({$assignedDateSql}, ap.created_at) BETWEEN ? AND ?",
                [$f['start'], $f['end']]
            ))
            ->when($f['from'], fn ($q) => $q->whereRaw("COALESCE({$assignedDateSql}, ap.created_at) >= ?", [$f['from']]))
            ->when($f['to'], fn ($q) => $q->whereRaw("COALESCE({$assignedDateSql}, ap.created_at) <= ?", [$f['to'].' 23:59:59']))
            ->when($f['initiator'], fn ($q) => $q->where('org.id', $f['initiator']))
            ->when($volunteerId, fn ($q) => $q->whereRaw('1 = 0'))
            ->get()
            ->map(fn ($r) => [
                'volunteer_id' => null,
                'name' => $r->name,
                'id_number' => $r->participant_id_number ?? $r->roster_code,
                'department' => null,
                'activity' => $r->activity,
                'date' => $r->participation_date,
                'initiator' => $r->organization_name ?? $r->org_name,
                'participation_type' => $r->participation_type === ActivityParticipant::ATTENDANCE_REQUIRED
                    ? 'External participant — attendance required'
                    : 'External participant — participation only',
            ]);

        $rows = $approvedRows->concat($assigned)->concat($externalAssigned)
            ->sortBy([['name', 'asc'], ['date', 'desc']])
            ->values();

        // Anyone with an approved application or an assigned activity entry, for the picker.
        $volunteers = User::where(function ($query) {
                $query->whereIn('id', DB::table('volunteer_applications')->where('status', 'approved')->select('user_id'))
                    ->orWhereIn('id', DB::table('project_participant_enrollments')->whereNotNull('user_id')->select('user_id'));
            })
            ->orderBy('name')->get(['id', 'name', 'id_number']);

        if ($request->query('format') === 'csv') {
            return $this->csv(
                "volunteer-participation-{$f['ay']}.csv",
                ['Participant', 'ID number', 'Department / College', 'Activity', 'Date', 'Initiator', 'Participation type'],
                $rows->map(fn ($r) => [
                    $r['name'], $r['id_number'] ?? '', $r['department'] ?? '',
                    $r['activity'], $r['date'] ? Carbon::parse($r['date'])->format('Y-m-d') : '',
                    $r['initiator'], $r['participation_type'],
                ])
            );
        }

        return view('CELP.head.reports.volunteers', [
            'groups' => $rows->groupBy('name'),
            'rows' => $rows,
            'volunteers' => $volunteers,
            'volunteerId' => $volunteerId,
            'years' => $this->availableYears(),
            'initiators' => $this->initiators(),
            'f' => $f,
            'certifier' => $request->user(),
        ]);
    }

    public function evaluations(Request $request)
    {
        $this->guard($request);
        $f = $this->filters($request);

        $evaluations = Evaluation::query()
            ->with(['announcement.creator', 'announcement.project', 'responses.ratings.criterion'])
            ->whereHas('announcement', function ($query) use ($f) {
                $query->whereHas('creator', fn ($creator) => $creator
                    ->whereIn('role', ['student_org', 'college_department'])
                    ->when($f['initiator'], fn ($builder) => $builder->whereKey($f['initiator'])))
                    ->whereHas('project', fn ($project) => $project->whereIn('status', ['approved', 'csa_cleared']))
                    ->when($f['start'], fn ($builder) => $builder->whereRaw(
                        'COALESCE(announcements.event_date, announcements.published_at, announcements.created_at) BETWEEN ? AND ?',
                        [$f['start'], $f['end']]
                    ))
                    ->when($f['from'], fn ($builder) => $builder->whereDate(
                        DB::raw('COALESCE(announcements.event_date, announcements.published_at, announcements.created_at)'),
                        '>=',
                        $f['from']
                    ))
                    ->when($f['to'], fn ($builder) => $builder->whereDate(
                        DB::raw('COALESCE(announcements.event_date, announcements.published_at, announcements.created_at)'),
                        '<=',
                        $f['to']
                    ));
            })
            ->orderByDesc('evaluations.created_at')
            ->get();

        $criteria = EvaluationCriterion::query()->orderBy('sort_order')->get();
        $ratingScores = [];
        $ratingScoresByType = ['beneficiary' => [], 'implementer' => []];
        $responseCount = 0;
        $responseCountsByType = ['beneficiary' => 0, 'implementer' => 0];
        $submittedCount = 0;
        $feedback = ['suggestions' => [], 'observations' => []];
        $rows = $evaluations->map(function (Evaluation $evaluation) use (&$ratingScores, &$ratingScoresByType, &$responseCount, &$responseCountsByType, &$submittedCount, &$feedback) {
            $announcement = $evaluation->announcement;
            $responses = $evaluation->responses;
            $responseCount += $responses->count();
            $submittedCount += $evaluation->isSubmitted() ? 1 : 0;
            $evaluationFeedback = ['suggestions' => [], 'observations' => []];
            $scoresByType = ['beneficiary' => collect(), 'implementer' => collect()];
            foreach ($responses as $response) {
                if (isset($responseCountsByType[$response->respondent_type])) {
                    $responseCountsByType[$response->respondent_type]++;
                }
                foreach ($response->ratings as $rating) {
                    $ratingScores[$rating->criterion_id][] = $rating->score;
                    $ratingScoresByType[$response->respondent_type][] = $rating->score;
                    $scoresByType[$response->respondent_type]->push($rating->score);
                }
            }

            foreach ($responses as $response) {
                foreach (['suggestions', 'observations'] as $field) {
                    $text = trim((string) $response->{$field});
                    if ($text !== '') {
                        $evaluationFeedback[$field][] = $text;
                        $feedback[$field][] = [
                            'activity' => $announcement->title,
                            'initiator' => $announcement->creator->organization_name ?? $announcement->creator->name ?? 'Unknown',
                            'text' => $text,
                        ];
                    }
                }
            }

            return [
                'initiator' => $announcement->creator->organization_name ?? $announcement->creator->name ?? 'Unknown',
                'activity' => $announcement->title,
                'date' => $announcement->event_date ?? $announcement->published_at ?? $announcement->created_at,
                'status' => $evaluation->statusLabel(),
                'expected' => $evaluation->expected_beneficiaries,
                'beneficiary_responses' => $responses->where('respondent_type', 'beneficiary')->count(),
                'implementer_responses' => $responses->where('respondent_type', 'implementer')->count(),
                'beneficiary_average' => $scoresByType['beneficiary']->isEmpty() ? null : round((float) $scoresByType['beneficiary']->avg(), 2),
                'implementer_average' => $scoresByType['implementer']->isEmpty() ? null : round((float) $scoresByType['implementer']->avg(), 2),
                'suggestions' => implode(' | ', $evaluationFeedback['suggestions']),
                'observations' => implode(' | ', $evaluationFeedback['observations']),
            ];
        });

        $overallAverageByType = [];
        foreach ($ratingScoresByType as $type => $scores) {
            $overallAverageByType[$type] = $scores === []
                ? null
                : round((float) collect($scores)->avg(), 2);
        }
        $criterionAverages = $criteria->map(function (EvaluationCriterion $criterion) use ($ratingScores) {
            $scores = collect($ratingScores[$criterion->id] ?? []);

            return [
                'label' => $criterion->short_label,
                'respondent_type' => $criterion->respondent_type,
                'average' => $scores->isEmpty() ? null : round((float) $scores->avg(), 2),
                'count' => $scores->count(),
            ];
        });

        if ($request->query('format') === 'csv') {
            $csvSafe = fn ($value) => is_string($value) && preg_match('/^\s*[=+\-@]/', $value)
                ? "'".$value
                : $value;

            return $this->csv(
                "participant-feedback-{$f['ay']}.csv",
                ['Initiator', 'Activity', 'Activity date', 'Evaluation status', 'Expected beneficiaries', 'Beneficiary responses', 'Volunteer / implementer responses', 'Beneficiary average (1–5)', 'Volunteer / implementer average (1–5)', 'Suggestions', 'Observations'],
                $rows->map(fn ($row) => array_map($csvSafe, [
                    $row['initiator'],
                    $row['activity'],
                    $row['date']?->format('Y-m-d') ?? '',
                    $row['status'],
                    $row['expected'] ?? '',
                    $row['beneficiary_responses'],
                    $row['implementer_responses'],
                    $row['beneficiary_average'] ?? 'No ratings',
                    $row['implementer_average'] ?? 'No ratings',
                    $row['suggestions'],
                    $row['observations'],
                ]))
            );
        }

        return view('CELP.head.reports.evaluations', [
            'rows' => $rows,
            'criteria' => $criterionAverages,
            'feedback' => $feedback,
            'evaluationCount' => $evaluations->count(),
            'responseCount' => $responseCount,
            'submittedCount' => $submittedCount,
            'responseCountsByType' => $responseCountsByType,
            'overallAverageByType' => $overallAverageByType,
            'years' => $this->availableYears(),
            'initiators' => $this->initiators(),
            'f' => $f,
        ]);
    }
}
