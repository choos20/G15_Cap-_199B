<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Project;
use App\Models\Template;
use App\Models\User;
use App\Models\VolunteerApplication;
use App\Notifications\ProjectStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StudentOrgController extends Controller
{
    protected function ensureOrg(Request $request)
    {
        if (! in_array($request->user()->role, ['student_org', 'college_department'])) {
            abort(403, 'Unauthorized');
        }
    }

    public function index(Request $request)
    {
        $this->ensureOrg($request);

        $user = $request->user();

        $proposalCounts = Project::where('created_by', $user->id)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $projectsAwaitingSecretary = $proposalCounts['submitted'] ?? 0;
        $projectsAwaitingCoordinator = $proposalCounts['endorsed_by_secretary'] ?? 0;
        $projectsAwaitingCelpHead = $proposalCounts['endorsed_by_coordinator'] ?? 0;
        $projectsAwaitingCsa = $proposalCounts['approved'] ?? 0;
        $approvedActivities = $proposalCounts['csa_cleared'] ?? 0;
        $totalProposals = array_sum($proposalCounts);
        $projectsInReview = $projectsAwaitingSecretary
            + $projectsAwaitingCoordinator
            + $projectsAwaitingCelpHead
            + $projectsAwaitingCsa;
        $projectsReturned = array_sum(array_intersect_key($proposalCounts, array_flip([
            'returned_by_secretary',
            'returned_by_coordinator',
            'returned_by_celp_head',
            'returned_by_csa',
        ])));

        $workflowStages = [
            ['key' => 'secretary', 'label' => 'CELP Secretary', 'count' => $projectsAwaitingSecretary, 'color' => '#c98208'],
            ['key' => 'coordinator', 'label' => 'CELP Coordinator', 'count' => $projectsAwaitingCoordinator, 'color' => '#2563eb'],
            ['key' => 'celp_head', 'label' => 'CELP Head', 'count' => $projectsAwaitingCelpHead, 'color' => '#7c3aed'],
            ['key' => 'csa', 'label' => 'CSA Director', 'count' => $projectsAwaitingCsa, 'color' => '#078d70'],
            ['key' => 'approved', 'label' => 'Fully approved', 'count' => $approvedActivities, 'color' => '#0f766e'],
            ['key' => 'returned', 'label' => 'Returned for updates', 'count' => $projectsReturned, 'color' => '#d94e74'],
        ];
        $workflowTotal = array_sum(array_column($workflowStages, 'count'));
        foreach ($workflowStages as $index => $stage) {
            $workflowStages[$index]['percent'] = $workflowTotal > 0
                ? (int) round($stage['count'] / $workflowTotal * 100)
                : 0;
        }

        $trendStart = now()->startOfMonth()->subMonths(5);
        $trendProjects = Project::where('created_by', $user->id)
            ->where('created_at', '>=', $trendStart)
            ->get(['created_at', 'status']);
        $projectTrend = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = now()->startOfMonth()->subMonths($offset);
            $projectsInMonth = $trendProjects->filter(
                fn (Project $project) => $project->created_at->format('Y-m') === $month->format('Y-m')
            );

            $projectTrend[] = [
                'label' => $month->format('M'),
                'approved' => $projectsInMonth->filter(
                    fn (Project $project) => in_array($project->status, ['approved', 'csa_cleared'], true)
                )->count(),
                'pending' => $projectsInMonth->filter(
                    fn (Project $project) => in_array($project->status, [
                        'submitted',
                        'endorsed_by_secretary',
                        'endorsed_by_coordinator',
                    ], true)
                )->count(),
                'returned' => $projectsInMonth->filter(
                    fn (Project $project) => str_starts_with($project->status, 'returned_by_')
                )->count(),
            ];
        }

        $templates = Template::with('uploader')->orderBy('created_at', 'desc')->take(4)->get();

        $latestProject = Project::where('created_by', $user->id)
            ->orderBy('updated_at', 'desc')
            ->first();

        $latestProjectStatusLabel = null;
        $latestProjectNextStep = null;

        if ($latestProject) {
            switch ($latestProject->status) {
                case 'submitted':
                    $latestProjectStatusLabel = 'Awaiting CELP Secretary review';
                    $latestProjectNextStep = 'Prepare to respond to secretary comments and requested updates.';
                    break;
                case 'endorsed_by_secretary':
                    $latestProjectStatusLabel = 'Awaiting coordinator review';
                    $latestProjectNextStep = 'Your proposal is now with the coordinator for second checking.';
                    break;
                case 'endorsed_by_coordinator':
                    $latestProjectStatusLabel = 'Awaiting CELP Head review';
                    $latestProjectNextStep = 'Your proposal has been endorsed by the coordinator and is now with the CELP Head for final CELP checking.';
                    break;
                case 'approved':
                    $latestProjectStatusLabel = 'Awaiting CSA Director compliance review';
                    $latestProjectNextStep = 'Your proposal has been approved by the CELP Head and is now with the CSA Director for final compliance clearance.';
                    break;
                case 'csa_cleared':
                    $latestProjectStatusLabel = 'Fully approved';
                    $latestProjectNextStep = 'Your activity is fully approved and may proceed.';
                    break;
                default:
                    $latestProjectStatusLabel = ucwords(str_replace('_', ' ', $latestProject->status));
                    $latestProjectNextStep = 'Review the proposal status and follow the next CELP step.';
                    break;
            }
        }

        return view('STUDENT_ORGANIZATION_PRESIDENT.dashboard', [
            'totalProposals' => $totalProposals,
            'projectsInReview' => $projectsInReview,
            'projectsReturned' => $projectsReturned,
            'projectsAwaitingSecretary' => $projectsAwaitingSecretary,
            'projectsAwaitingCoordinator' => $projectsAwaitingCoordinator,
            'projectsAwaitingCelpHead' => $projectsAwaitingCelpHead,
            'projectsAwaitingCsa' => $projectsAwaitingCsa,
            'approvedActivities' => $approvedActivities,
            'workflowStages' => $workflowStages,
            'workflowTotal' => $workflowTotal,
            'projectTrend' => $projectTrend,
            'templates' => $templates,
            'latestProject' => $latestProject,
            'latestProjectStatusLabel' => $latestProjectStatusLabel,
            'latestProjectNextStep' => $latestProjectNextStep,
        ]);
    }

    public function proposals(Request $request)
    {
        $this->ensureOrg($request);

        $projects = Project::where('created_by', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('STUDENT_ORGANIZATION_PRESIDENT.proposals', ['projects' => $projects]);
    }

    public function announcements(Request $request)
    {
        $this->ensureOrg($request);

        return view('admin.placeholder', [
            'title' => 'Announcements',
            'description' => 'Publish approved activity and volunteer recruitment announcements for your organization.',
        ]);
    }

    public function volunteers(Request $request)
    {
        $this->ensureOrg($request);
        $user = $request->user();

        $announcements = Announcement::where('created_by', $user->id)
            ->whereNotNull('slots_needed')
            ->with('project')
            ->withCount([
                'applications as pending_count' => fn ($q) => $q->where('status', 'pending'),
                'applications as approved_count' => fn ($q) => $q->where('status', 'approved'),
            ])
            ->orderByDesc('created_at')
            ->get();

        return view('STUDENT_ORGANIZATION_PRESIDENT.volunteers', ['announcements' => $announcements]);
    }

    public function showVolunteerApplications(Request $request, Announcement $announcement)
    {
        $this->ensureOrg($request);

        if ($announcement->created_by !== $request->user()->id) {
            abort(403);
        }

        $applications = $announcement->applications()->with('applicant', 'attachments')->orderByDesc('created_at')->get();

        return view('STUDENT_ORGANIZATION_PRESIDENT.volunteer_applicants', [
            'announcement' => $announcement,
            'applications' => $applications,
        ]);
    }

    public function approveVolunteer(Request $request, VolunteerApplication $application)
    {
        $this->ensureOrg($request);
        $user = $request->user();
        $application->load('announcement.project', 'applicant');

        if ($application->announcement->created_by !== $user->id) {
            abort(403);
        }

        if ($application->status !== 'pending') {
            return back()->withErrors(['approve' => 'This application has already been decided.']);
        }

        if ($application->announcement->isFull()) {
            return back()->withErrors(['approve' => 'No slots remain for this activity.']);
        }

        $application->update([
            'status' => 'approved',
            'decided_by' => $user->id,
            'decided_at' => now(),
        ]);

        $this->notifyApplicant($application, 'Volunteer application approved', "Your application for \"{$application->announcement->title}\" has been approved.");

        // If this approval filled the last slot, the remaining pending applicants can no longer be accepted.
        if ($application->announcement->fresh()->isFull()) {
            $remaining = $application->announcement->applications()->where('status', 'pending')->with('applicant', 'announcement.project')->get();
            foreach ($remaining as $pending) {
                $pending->update([
                    'status' => 'declined',
                    'decision_notes' => 'No slots available',
                    'decided_by' => $user->id,
                    'decided_at' => now(),
                ]);
                $this->notifyApplicant($pending, 'No slots available', "\"{$application->announcement->title}\" has reached its volunteer capacity. Your application was not accepted this time.");
            }
        }

        return back()->with('status', 'Application approved.');
    }

    public function declineVolunteer(Request $request, VolunteerApplication $application)
    {
        $this->ensureOrg($request);
        $user = $request->user();
        $application->load('announcement.project', 'applicant');

        if ($application->announcement->created_by !== $user->id) {
            abort(403);
        }

        if ($application->status !== 'pending') {
            return back()->withErrors(['decline' => 'This application has already been decided.']);
        }

        $application->update([
            'status' => 'declined',
            'decided_by' => $user->id,
            'decided_at' => now(),
        ]);

        $this->notifyApplicant($application, 'Volunteer application update', "Your application for \"{$application->announcement->title}\" was not approved this time.");

        return back()->with('status', 'Application declined.');
    }

    private function notifyApplicant(VolunteerApplication $application, string $title, string $body): void
    {
        try {
            Notification::send(
                $application->applicant,
                new ProjectStatusChanged($application->announcement->project, $title, $body, route('participant.applications'))
            );
        } catch (\Exception $e) {
            // non-fatal
        }
    }

    public function attendance(Request $request)
    {
        $this->ensureOrg($request);

        return view('admin.placeholder', [
            'title' => 'Attendance Management',
            'description' => 'Open and close attendance sessions, and generate location-validated QR codes for approved activities.',
        ]);
    }

    // Evaluations moved to EvaluationController; the sidebar route now points
    // there, so the placeholder that used to live here has been removed.

    public function notifications(Request $request)
    {
        $this->ensureOrg($request);

        // Mark unseen returned projects as seen when the user opens the notifications page (only if column exists)
        if (Schema::hasColumn('projects', 'notification_seen_at')) {
            Project::where('created_by', $request->user()->id)
                ->whereIn('status', ['returned_by_secretary', 'returned_by_coordinator', 'returned_by_celp_head', 'returned_by_csa'])
                ->whereNull('notification_seen_at')
                ->update(['notification_seen_at' => now()]);
        }

        $returnedProjects = Project::where('created_by', $request->user()->id)
            ->whereIn('status', ['returned_by_secretary', 'returned_by_coordinator', 'returned_by_celp_head', 'returned_by_csa'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('STUDENT_ORGANIZATION_PRESIDENT.notifications', [
            'returnedProjects' => $returnedProjects,
        ]);
    }
}
