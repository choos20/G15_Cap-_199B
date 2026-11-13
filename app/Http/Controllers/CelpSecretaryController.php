<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Template;
use Illuminate\Http\Request;

class CelpSecretaryController extends Controller
{
    /**
     * Show the CELP Secretary dashboard.
     */
    public function index(Request $request)
    {
        if ($request->user()->role !== 'celp_secretary') {
            abort(403, 'Unauthorized');
        }

        $pending = Project::where('status', 'submitted')->count();
        $returned = Project::where('status', 'returned_by_secretary')->count();
        $templates = Template::count();
        $records = 0; // placeholder: no records/archives model yet

        $workflowStages = [
            ['key' => 'pending', 'label' => 'Awaiting initial review', 'count' => $pending, 'color' => '#c98208'],
            ['key' => 'forwarded', 'label' => 'Sent to Coordinator', 'count' => Project::where('status', 'endorsed_by_secretary')->count(), 'color' => '#078d70'],
            ['key' => 'returned', 'label' => 'Returned to applicants', 'count' => $returned, 'color' => '#d94e74'],
        ];
        $workflowTotal = array_sum(array_column($workflowStages, 'count'));
        foreach ($workflowStages as $index => $stage) {
            $workflowStages[$index]['percent'] = $workflowTotal > 0
                ? (int) round($stage['count'] / $workflowTotal * 100)
                : 0;
        }

        $queue = Project::where('status', 'submitted')
            ->with('creator')
            ->withCount('attachments')
            ->orderBy('created_at')
            ->take(5)
            ->get();

        $oldestPending = $queue->first();

        $recentTemplates = Template::with('uploader')
            ->orderByDesc('created_at')
            ->take(4)
            ->get();

        $trendStart = now()->startOfMonth()->subMonths(5);
        $reviewEvents = AuditLog::where('actor_role', 'celp_secretary')
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
                        && ($event->context['to_status'] ?? null) === 'endorsed_by_secretary'
                )->count(),
                'returned' => $eventsInMonth->filter(
                    fn (AuditLog $event) => $event->action === 'project.returned'
                        && ($event->context['to_status'] ?? null) === 'returned_by_secretary'
                )->count(),
            ];
        }

        return view('CELP.secretary.dashboard', [
            'user' => $request->user(),
            'pendingProposals' => $pending,
            'returnedCount' => $returned,
            'templatesCount' => $templates,
            'recordsCount' => $records,
            'queue' => $queue,
            'oldestPending' => $oldestPending,
            'recentTemplates' => $recentTemplates,
            'workflowStages' => $workflowStages,
            'workflowTotal' => $workflowTotal,
            'reviewTrend' => $reviewTrend,
        ]);
    }
}
