<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;

class CelpHeadController extends Controller
{
    /**
     * Show the CELP Head (System Administrator) dashboard.
     */
    public function index(Request $request)
    {
        if ($request->user()->role !== 'celp_head') {
            abort(403, 'Unauthorized');
        }

        $pending = Project::where('status', 'endorsed_by_coordinator')->count();
        $totalUsers = User::count();

        // Only count projects that were created by organization initiators
        $orgRoles = ['student_org', 'college_department'];

        $totalProjects = Project::whereHas('creator', function ($q) use ($orgRoles) {
            $q->whereIn('role', $orgRoles);
        })->count();

        $totalVolunteers = Project::whereHas('creator', function ($q) use ($orgRoles) {
            $q->whereIn('role', $orgRoles);
        })->sum('volunteers_count');

        $approvedCount = Project::whereIn('status', ['approved', 'csa_cleared'])->count();
        $pendingCount = Project::whereIn('status', ['submitted', 'endorsed_by_secretary', 'endorsed_by_coordinator'])->count();
        $returnedCount = Project::where('status', 'like', 'returned_by_%')->count();
        $statusTotal = max($approvedCount + $pendingCount + $returnedCount, 1);

        $queue = Project::where('status', 'endorsed_by_coordinator')
            ->with('creator')
            ->withCount('attachments')
            ->orderBy('created_at')
            ->take(5)
            ->get();

        $oldestPending = $queue->first();

        $recentProjects = Project::whereHas('creator', function ($q) use ($orgRoles) {
            $q->whereIn('role', $orgRoles);
        })
            ->with('creator')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        $trendStart = now()->startOfMonth()->subMonths(6);
        $trendProjects = Project::whereHas('creator', function ($query) use ($orgRoles) {
            $query->whereIn('role', $orgRoles);
        })
            ->where('created_at', '>=', $trendStart)
            ->get(['created_at', 'status']);
        $trendAccounts = User::where('created_at', '>=', $trendStart)->get(['created_at']);
        $projectTrend = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = now()->startOfMonth()->subMonths($offset);
            $projectsInMonth = $trendProjects->filter(
                fn (Project $project) => $project->created_at->format('Y-m') === $month->format('Y-m')
            );

            $projectTrend[] = [
                'label' => $month->format('M'),
                'approved' => $projectsInMonth->filter(fn (Project $project) => in_array($project->status, ['approved', 'csa_cleared'], true))->count(),
                'pending' => $projectsInMonth->filter(fn (Project $project) => in_array($project->status, ['submitted', 'endorsed_by_secretary', 'endorsed_by_coordinator'], true))->count(),
                'returned' => $projectsInMonth->filter(fn (Project $project) => str_starts_with($project->status, 'returned_by_'))->count(),
            ];
        }

        $completedMonthProjectCounts = [];
        $completedMonthAccountCounts = [];
        for ($offset = 6; $offset >= 1; $offset--) {
            $month = now()->startOfMonth()->subMonths($offset);
            $completedMonthProjectCounts[] = $trendProjects->filter(
                fn (Project $project) => $project->created_at->format('Y-m') === $month->format('Y-m')
            )->count();
            $completedMonthAccountCounts[] = $trendAccounts->filter(
                fn (User $account) => $account->created_at->format('Y-m') === $month->format('Y-m')
            )->count();
        }

        $summarizeMonthlyTrend = function (array $values): array {
            $previous = $values[count($values) - 2];
            $latest = $values[count($values) - 1];

            if ($previous === 0) {
                $label = $latest > 0 ? 'New this month' : 'No change from last month';
                $direction = $latest > 0 ? 'up' : 'flat';
            } else {
                $change = (int) round((($latest - $previous) / $previous) * 100);
                $label = $change === 0
                    ? 'No change from last month'
                    : sprintf('%s%d%% from last month', $change > 0 ? '+' : '', $change);
                $direction = $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat');
            }

            return [
                'values' => $values,
                'label' => $label,
                'direction' => $direction,
            ];
        };

        return view('CELP.head.dashboard', [
            'user' => $request->user(),
            'pendingProposals' => $pending,
            'totalUsers' => $totalUsers,
            'totalProjects' => $totalProjects,
            'totalVolunteers' => $totalVolunteers,
            'approvedCount' => $approvedCount,
            'pendingCount' => $pendingCount,
            'returnedCount' => $returnedCount,
            'statusTotal' => $statusTotal,
            'queue' => $queue,
            'oldestPending' => $oldestPending,
            'recentProjects' => $recentProjects,
            'projectTrend' => $projectTrend,
            'projectSubmissionTrend' => $summarizeMonthlyTrend($completedMonthProjectCounts),
            'accountCreationTrend' => $summarizeMonthlyTrend($completedMonthAccountCounts),
        ]);
    }
}
