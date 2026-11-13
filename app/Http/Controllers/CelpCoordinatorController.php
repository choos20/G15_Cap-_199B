<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Project;
use Illuminate\Http\Request;

class CelpCoordinatorController extends Controller
{
    /**
     * Show the CELP Coordinator dashboard.
     */
    public function index(Request $request)
    {
        if ($request->user()->role !== 'coordinator') {
            abort(403, 'Unauthorized');
        }

        $pending = Project::where('status', 'endorsed_by_secretary')->count();
        $active = Project::whereIn('status', ['approved', 'csa_cleared'])->count();
        $evals = 0; // no evaluation results model yet

        $returned = Project::where('status', 'returned_by_coordinator')->count();
        $forwarded = Project::where('status', 'endorsed_by_coordinator')->count();
        $workflowStages = [
            ['key' => 'pending', 'label' => 'Awaiting second review', 'count' => $pending, 'color' => '#c98208'],
            ['key' => 'forwarded', 'label' => 'Sent to CELP Head', 'count' => $forwarded, 'color' => '#078d70'],
            ['key' => 'returned', 'label' => 'Returned to applicants', 'count' => $returned, 'color' => '#d94e74'],
        ];
        $workflowTotal = array_sum(array_column($workflowStages, 'count'));
        foreach ($workflowStages as $index => $stage) {
            $workflowStages[$index]['percent'] = $workflowTotal > 0
                ? (int) round($stage['count'] / $workflowTotal * 100)
                : 0;
        }

        $queue = Project::where('status', 'endorsed_by_secretary')
            ->with('creator')
            ->withCount('attachments')
            ->orderBy('created_at')
            ->take(5)
            ->get();

        $oldestPending = $queue->first();

        $announcements = Announcement::whereNull('project_id')
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->take(3)
            ->get();

        $trendStart = now()->startOfMonth()->subMonths(5);
        $reviewEvents = AuditLog::where('actor_role', 'coordinator')
            ->whereIn('action', ['project.advanced', 'project.returned'])
            ->where('created_at', '>=', $trendStart)
            ->orderBy('created_at')
            ->get(['action', 'context', 'created_at']);
        $reviewTrend = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = now()->startOfMonth()->subMonths($offset);
            $eventsInMonth = $reviewEvents->filter(
                fn (AuditLog $event) => $event->created_at?->format('Y-m') === $month->format('Y-m')
            );

            $reviewTrend[] = [
                'label' => $month->format('M'),
                'advanced' => $eventsInMonth->filter(
                    fn (AuditLog $event) => $event->action === 'project.advanced'
                        && ($event->context['to_status'] ?? null) === 'endorsed_by_coordinator'
                )->count(),
                'returned' => $eventsInMonth->filter(
                    fn (AuditLog $event) => $event->action === 'project.returned'
                        && ($event->context['to_status'] ?? null) === 'returned_by_coordinator'
                )->count(),
            ];
        }

        return view('CELP.coordinator.dashboard', [
            'user' => $request->user(),
            'pendingSecondReview' => $pending,
            'activeActivities' => $active,
            'evaluationSummaries' => $evals,
            'workflowStages' => $workflowStages,
            'workflowTotal' => $workflowTotal,
            'queue' => $queue,
            'oldestPending' => $oldestPending,
            'announcements' => $announcements,
            'reviewTrend' => $reviewTrend,
        ]);
    }
}
