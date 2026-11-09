<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\VolunteerApplication;
use App\Models\VolunteerApplicationAttachment;
use App\Notifications\ProjectStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class ParticipantController extends Controller
{
    private function ensureParticipant(Request $request): void
    {
        if ($request->user()->role !== 'participant') {
            abort(403, 'Unauthorized');
        }
    }

    private function participantOpportunities()
    {
        return Announcement::whereNotNull('project_id')
            ->whereNotNull('slots_needed')
            ->where('category', 'Volunteer recruitment')
            ->where('status', 'published')
            ->whereHas('project', fn ($query) => $query->whereIn('status', ['approved', 'csa_cleared']));
    }

    private function participantAnnouncements()
    {
        return Announcement::where('status', 'published')
            ->where('category', 'General')
            ->where(function ($query) {
                $query->whereNull('project_id')
                    ->orWhereHas('project', fn ($project) => $project->whereIn('status', ['approved', 'csa_cleared']));
            });
    }

    private function filterParticipantOpportunitiesByStatus($query, string $status, string $today)
    {
        if ($status === '') {
            return $query;
        }

        $approvedCountSql = '(SELECT COUNT(*) FROM volunteer_applications
            WHERE volunteer_applications.announcement_id = announcements.id
            AND volunteer_applications.status = ?)';
        $deadlineOpen = fn ($builder) => $builder->where(function ($builder) use ($today) {
            $builder->whereNull('application_deadline')
                ->orWhere('application_deadline', '>=', now());
        });
        $hasCapacity = fn ($builder) => $builder->whereRaw('announcements.slots_needed > '.$approvedCountSql, ['approved']);

        if ($status === 'closed') {
            return $query->where(function ($builder) use ($today, $approvedCountSql) {
                $builder->where('application_deadline', '<', now())
                    ->orWhereDate('event_date', '<', $today)
                    ->orWhereRaw('announcements.slots_needed <= '.$approvedCountSql, ['approved']);
            });
        }

        $deadlineOpen($query);
        $hasCapacity($query);

        if ($status === 'ongoing') {
            return $query
                ->where('event_date', '>=', $today.' 00:00:00')
                ->where('event_date', '<=', now());
        }

        return $query->where(function ($builder) {
            $builder->whereNull('event_date')
                ->orWhere('event_date', '>', now());
        });
    }

    public function dashboard(Request $request)
    {
        $this->ensureParticipant($request);
        $user = $request->user();

        $applications = VolunteerApplication::where('user_id', $user->id)
            ->with('announcement.project', 'announcement.creator')
            ->get();

        $pendingCount = $applications->where('status', 'pending')->count();
        $approvedCount = $applications->where('status', 'approved')->count();

        $nextEnrolled = $applications->where('status', 'approved')
            ->filter(fn ($a) => $a->announcement && $a->announcement->event_date && $a->announcement->event_date->isFuture())
            ->sortBy(fn ($a) => $a->announcement->event_date)
            ->first();

        $feedPreview = $this->filterParticipantOpportunitiesByStatus(
            $this->participantOpportunities(),
            'open',
            now()->toDateString()
        )
            ->withCount(['applications as approved_volunteers' => fn ($query) => $query->where('status', 'approved')])
            ->with('project', 'creator')
            ->orderByDesc('published_at')
            ->take(4)
            ->get();
        $participantAnnouncements = $this->participantAnnouncements()
            ->with('project', 'creator')
            ->orderByDesc('published_at')
            ->take(3)
            ->get();

        $monthStart = now()->startOfMonth()->subMonths(5);
        $participationByMonth = collect();
        $attendanceRecords = AttendanceRecord::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.session_id')
            ->where('attendance_records.user_id', $user->id)
            ->whereBetween('attendance_sessions.opens_at', [$monthStart, now()])
            ->where(function ($query) {
                $query->whereNotNull('attendance_records.check_in_at')
                    ->orWhere(function ($query) {
                        $query->where('attendance_records.attendance_source', 'qr')
                            ->whereIn('attendance_records.status', ['valid', 'completed'])
                            ->whereNotNull('attendance_records.scanned_at');
                    })
                    ->orWhere(function ($query) {
                        $query->whereIn('attendance_records.attendance_source', AttendanceRecord::CSV_SOURCES)
                            ->where(function ($query) {
                                $query->where('attendance_records.check_in_status', 'Present')
                                    ->orWhere('attendance_records.check_out_status', 'Present');
                            });
                    });
            })
            ->get([
                'attendance_sessions.project_id',
                'attendance_sessions.opens_at',
            ]);

        $participationMonths = collect(range(0, 5))
            ->map(function (int $offset) use ($monthStart) {
                $month = $monthStart->copy()->addMonths($offset);

                return [
                    'key' => $month->format('Y-m'),
                    'label' => $month->format('M'),
                    'count' => 0,
                    'bar_height' => 0,
                    'bar_x' => 35 + ($offset * 88),
                ];
            });

        foreach ($attendanceRecords as $record) {
            $monthKey = Carbon::parse($record->opens_at)->format('Y-m');
            $participationByMonth->put(
                $monthKey,
                $participationByMonth->get($monthKey, collect())->push($record->project_id)->unique()
            );
        }

        $participationMonths = $participationMonths->map(function (array $month) use ($participationByMonth) {
            $month['count'] = $participationByMonth->get($month['key'], collect())->count();

            return $month;
        });

        $maxMonthlyActivities = max(1, (int) $participationMonths->max('count'));
        $participationMonths = $participationMonths->map(function (array $month) use ($maxMonthlyActivities) {
            $month['bar_height'] = $month['count'] > 0
                ? max(4, (int) round($month['count'] / $maxMonthlyActivities * 118))
                : 0;

            return $month;
        });

        return view('PARTICIPANT.dashboard', [
            'pendingCount' => $pendingCount,
            'approvedCount' => $approvedCount,
            'totalApplications' => $applications->count(),
            'nextEnrolled' => $nextEnrolled,
            'feedPreview' => $feedPreview,
            'participantAnnouncements' => $participantAnnouncements,
            'participationMonths' => $participationMonths,
            'totalAttendedActivities' => $participationMonths->sum('count'),
        ]);
    }

    public function feed(Request $request)
    {
        $this->ensureParticipant($request);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'in:open,ongoing,closed'],
        ]);
        $search = trim($filters['q'] ?? '');
        $today = now()->toDateString();

        $opportunitiesQuery = $this->participantOpportunities();
        $categories = (clone $opportunitiesQuery)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $filteredOpportunities = $opportunitiesQuery
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('category', 'like', '%'.$search.'%')
                        ->orWhere('event_location', 'like', '%'.$search.'%')
                        ->orWhereHas('creator', fn ($creator) => $creator
                            ->where('organization_name', 'like', '%'.$search.'%')
                            ->orWhere('name', 'like', '%'.$search.'%'));
                });
            })
            ->when(filled($filters['category'] ?? null), fn ($query) => $query->where('category', $filters['category']));

        $statusCounts = [
            'all' => (clone $filteredOpportunities)->count(),
            'open' => $this->filterParticipantOpportunitiesByStatus(clone $filteredOpportunities, 'open', $today)->count(),
            'ongoing' => $this->filterParticipantOpportunitiesByStatus(clone $filteredOpportunities, 'ongoing', $today)->count(),
            'closed' => $this->filterParticipantOpportunitiesByStatus(clone $filteredOpportunities, 'closed', $today)->count(),
        ];
        $selectedStatus = $filters['status'] ?? '';

        $opportunities = $this->filterParticipantOpportunitiesByStatus(clone $filteredOpportunities, $selectedStatus, $today)
            ->withCount(['applications as approved_volunteers' => fn ($query) => $query->where('status', 'approved')])
            ->with('project', 'creator')
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString();
        $participantAnnouncements = $this->participantAnnouncements()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhereHas('creator', fn ($creator) => $creator
                            ->where('organization_name', 'like', '%'.$search.'%')
                            ->orWhere('name', 'like', '%'.$search.'%'));
                });
            })
            ->with('project', 'creator')
            ->orderByDesc('published_at')
            ->paginate(5, ['*'], 'announcements_page')
            ->withQueryString();

        $appliedIds = VolunteerApplication::where('user_id', $request->user()->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereIn('announcement_id', $opportunities->getCollection()->pluck('id'))
            ->pluck('announcement_id');

        return view('PARTICIPANT.feed', [
            'opportunities' => $opportunities,
            'participantAnnouncements' => $participantAnnouncements,
            'appliedIds' => $appliedIds,
            'categories' => $categories,
            'statusCounts' => $statusCounts,
            'filters' => [
                'q' => $search,
                'category' => $filters['category'] ?? '',
                'status' => $selectedStatus,
            ],
        ]);
    }

    public function show(Request $request, Announcement $announcement)
    {
        $this->ensureParticipant($request);

        $isGeneralAnnouncement = $announcement->category === 'General'
            && ($announcement->project_id === null
                || ($announcement->project
                    && in_array($announcement->project->status, ['approved', 'csa_cleared'], true)));
        $isRecruitmentAnnouncement = $announcement->category === 'Volunteer recruitment'
            && $announcement->project_id !== null
            && $announcement->project
            && in_array($announcement->project->status, ['approved', 'csa_cleared'], true);

        if ($announcement->status !== 'published' || (! $isGeneralAnnouncement && ! $isRecruitmentAnnouncement)) {
            abort(404);
        }

        $announcement->load('project', 'creator', 'attachments');

        $myApplication = VolunteerApplication::where('announcement_id', $announcement->id)
            ->where('user_id', $request->user()->id)
            ->first();

        return view('PARTICIPANT.activity_show', [
            'announcement' => $announcement,
            'myApplication' => $myApplication,
        ]);
    }

    public function apply(Request $request, Announcement $announcement)
    {
        $this->ensureParticipant($request);
        $user = $request->user();

        if (
            $announcement->status !== 'published'
            || $announcement->category !== 'Volunteer recruitment'
            || $announcement->project_id === null
            || $announcement->slots_needed === null
            || ! $announcement->project()->whereIn('status', ['approved', 'csa_cleared'])->exists()
        ) {
            abort(404);
        }

        if ($announcement->application_deadline && $announcement->application_deadline->lt(now())) {
            return back()->withErrors(['apply' => 'The application deadline for this activity has passed.']);
        }

        if ($announcement->event_date && $announcement->event_date->isPast()) {
            return back()->withErrors(['apply' => 'This activity has already started. Applications are closed.']);
        }

        $existing = VolunteerApplication::where('announcement_id', $announcement->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && in_array($existing->status, ['pending', 'approved'])) {
            return back()->withErrors(['apply' => 'You have already applied to this activity.']);
        }

        if ($announcement->isFull()) {
            return back()->withErrors(['apply' => 'No available slots remain for this activity.']);
        }

        $request->validate([
            'documents' => 'required|array|min:1|max:3',
            'documents.*' => 'file|mimes:pdf,doc,docx|max:5120',
        ], [
            'documents.required' => 'Please upload at least one supporting document (e.g. parental consent form).',
            'documents.max' => 'You may upload up to 3 documents.',
            'documents.*.mimes' => 'Documents must be PDF, DOC, or DOCX files.',
        ]);

        if ($existing) {
            // Re-applying after a previous withdrawal/decline — replace the old documents.
            foreach ($existing->attachments as $old) {
                Storage::disk('local')->delete($old->file_path);
                $old->delete();
            }
            $existing->update(['status' => 'pending', 'decision_notes' => null, 'decided_by' => null, 'decided_at' => null]);
            $application = $existing;
        } else {
            $application = VolunteerApplication::create([
                'announcement_id' => $announcement->id,
                'user_id' => $user->id,
                'status' => 'pending',
            ]);
        }

        foreach ($request->file('documents') as $file) {
            $path = $file->store('volunteer_documents', 'local');
            VolunteerApplicationAttachment::create([
                'volunteer_application_id' => $application->id,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        if ($announcement->creator) {
            try {
                Notification::send($announcement->creator, new ProjectStatusChanged(
                    $announcement->project,
                    'New volunteer application',
                    "{$user->name} applied to \"{$announcement->title}\".",
                    route('student_org.volunteers.show', $announcement)
                ));
            } catch (\Exception $e) {
                // non-fatal
            }
        }

        return redirect()->route('participant.activities.show', $announcement)->with('status', 'Application submitted. You will be notified once it is reviewed.');
    }

    public function myApplications(Request $request)
    {
        $this->ensureParticipant($request);

        $userId = $request->user()->id;
        $applications = VolunteerApplication::where('user_id', $userId)
            ->with('announcement.project', 'announcement.creator', 'attachments')
            ->orderByDesc('created_at')
            ->get();

        $attendedProjectIds = DB::table('attendance_records as records')
            ->join('attendance_sessions as sessions', 'sessions.id', '=', 'records.session_id')
            ->where('records.user_id', $userId)
            ->whereNotNull('sessions.project_id');
        $this->whereCheckInRecorded($attendedProjectIds, 'records.');

        return view('PARTICIPANT.applications', [
            'applications' => $applications,
            'attendedProjectIds' => $attendedProjectIds->distinct()->pluck('sessions.project_id'),
        ]);
    }

    public function attendanceHistory(Request $request)
    {
        $this->ensureParticipant($request);

        $records = AttendanceRecord::query()
            ->where('user_id', $request->user()->id)
            ->where(function ($query) {
                $query->whereNotNull('check_in_at')
                    ->orWhere(function ($query) {
                        $query->where('attendance_source', 'qr')
                            ->whereIn('status', ['valid', 'completed'])
                            ->whereNotNull('scanned_at');
                    })
                    ->orWhereIn('attendance_source', AttendanceRecord::CSV_SOURCES);
            })
            ->with('session.project')
            ->orderByDesc('check_in_at')
            ->orderByDesc('scanned_at')
            ->paginate(15);

        return view('PARTICIPANT.attendance_history', ['records' => $records]);
    }

    public function withdraw(Request $request, VolunteerApplication $application)
    {
        $this->ensureParticipant($request);

        $result = DB::transaction(function () use ($request, $application): string {
            $lockedApplication = VolunteerApplication::whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedApplication->user_id !== $request->user()->id) {
                abort(403);
            }

            if (! in_array($lockedApplication->status, ['pending', 'approved'], true)) {
                return 'unavailable';
            }

            $projectId = $lockedApplication->announcement()->value('project_id');
            if ($projectId && $this->hasCheckInAttendance($lockedApplication->user_id, $projectId)) {
                return 'attended';
            }

            $lockedApplication->update(['status' => 'withdrawn']);

            return 'withdrawn';
        });

        if ($result === 'attended') {
            return back()->withErrors([
                'withdraw' => 'This application can no longer be withdrawn because attendance has been recorded for this activity.',
            ]);
        }

        if ($result !== 'withdrawn') {
            return back()->withErrors(['withdraw' => 'This application can no longer be withdrawn.']);
        }

        return redirect()->route('participant.applications')->with('status', 'Application withdrawn.');
    }

    private function hasCheckInAttendance(int $userId, int $projectId): bool
    {
        $query = AttendanceRecord::query()
            ->where('user_id', $userId)
            ->whereHas('session', fn ($session) => $session->where('project_id', $projectId));

        $this->whereCheckInRecorded($query);

        return $query->exists();
    }

    private function whereCheckInRecorded($query, string $prefix = '')
    {
        return $query->where(function ($attendance) use ($prefix) {
            $attendance->whereNotNull($prefix.'check_in_at')
                ->orWhere(function ($attendance) use ($prefix) {
                    $attendance->where($prefix.'attendance_source', 'qr')
                        ->whereIn($prefix.'status', ['valid', 'completed'])
                        ->whereNotNull($prefix.'scanned_at');
                })
                ->orWhere(function ($attendance) use ($prefix) {
                    $attendance->whereIn($prefix.'attendance_source', AttendanceRecord::CSV_SOURCES)
                        ->where($prefix.'check_in_status', 'Present');
                });
        });
    }

    public function scanner(Request $request)
    {
        $this->ensureParticipant($request);

        return view('PARTICIPANT.scanner');
    }

    /**
     * Fallback for when the camera can't be used: accepts a pasted scan link
     * or raw token, pulls out the 40-char token, and hands off to the
     * existing geolocation-capture flow in AttendanceController.
     */
    public function resolveScan(Request $request)
    {
        $this->ensureParticipant($request);

        $code = trim((string) $request->query('code', ''));

        if (preg_match('/([a-zA-Z0-9]{40})/', $code, $matches)) {
            return redirect()->route('attendance.scan.view', ['token' => $matches[1]]);
        }

        return redirect()->route('participant.scanner')
            ->withErrors(['code' => "That doesn't look like a valid attendance QR code or link. Please try again."]);
    }

    /**
     * Supporting documents (e.g. parental consent) are private — only the
     * applicant themselves or the org reviewing their application may
     * download them. Not gated to the participant role since orgs need it too.
     */
    public function downloadDocument(Request $request, VolunteerApplicationAttachment $attachment)
    {
        $user = $request->user();
        $application = $attachment->application()->with('announcement')->first();

        $this->authorizeDocumentAccess($application, $user->id);

        if (! Storage::disk('local')->exists($attachment->file_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }

    private function authorizeDocumentAccess(?VolunteerApplication $application, int $userId): void
    {
        $isOwner = $application && $application->user_id === $userId;
        $isReviewingOrg = $application
            && $application->announcement
            && $application->announcement->created_by === $userId;

        abort_unless($isOwner || $isReviewingOrg, 403);
    }
}
