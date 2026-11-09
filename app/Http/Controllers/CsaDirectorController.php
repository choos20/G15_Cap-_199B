<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\Request;

class CsaDirectorController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role !== 'csa_director') {
            abort(403, 'Unauthorized');
        }

        $orgCount = User::where('role', 'student_org')
            ->where('created_by', $request->user()->id)
            ->count();
        $pendingCompliance = Project::where('status', 'approved')->count();
        $approvedActivities = Project::where('status', 'csa_cleared')->count();
        $templatesCount = Template::count();

        $returnedCompliance = Project::where('status', 'returned_by_csa')->count();
        $workflowStages = [
            ['key' => 'pending', 'label' => 'Awaiting compliance review', 'count' => $pendingCompliance, 'color' => '#c98208'],
            ['key' => 'cleared', 'label' => 'Cleared by CSA', 'count' => $approvedActivities, 'color' => '#078d70'],
            ['key' => 'returned', 'label' => 'Returned to applicants', 'count' => $returnedCompliance, 'color' => '#d94e74'],
        ];
        $workflowTotal = array_sum(array_column($workflowStages, 'count'));
        foreach ($workflowStages as $index => $stage) {
            $workflowStages[$index]['percent'] = $workflowTotal > 0
                ? (int) round($stage['count'] / $workflowTotal * 100)
                : 0;
        }

        $trendStart = now()->startOfMonth()->subMonths(5);
        $reviewEvents = AuditLog::where('actor_role', 'csa_director')
            ->whereIn('action', ['project.advanced', 'project.returned'])
            ->where('created_at', '>=', $trendStart)
            ->orderBy('created_at')
            ->get(['action', 'context', 'created_at']);
        $reviewTrend = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = now()->startOfMonth()->subMonths($offset);
            $eventsInMonth = $reviewEvents->filter(
                fn (AuditLog $event) => $event->created_at->format('Y-m') === $month->format('Y-m')
            );

            $reviewTrend[] = [
                'label' => $month->format('M'),
                'cleared' => $eventsInMonth->filter(
                    fn (AuditLog $event) => $event->action === 'project.advanced'
                        && ($event->context['to_status'] ?? null) === 'csa_cleared'
                )->count(),
                'returned' => $eventsInMonth->filter(
                    fn (AuditLog $event) => $event->action === 'project.returned'
                        && ($event->context['to_status'] ?? null) === 'returned_by_csa'
                )->count(),
            ];
        }

        $queue = Project::where('status', 'approved')
            ->with('creator')
            ->withCount('attachments')
            ->orderBy('created_at')
            ->take(5)
            ->get();

        $recentOrgs = User::where('role', 'student_org')
            ->where('created_by', $request->user()->id)
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        return view('CSA_DIRECTOR.dashboard', [
            'orgCount' => $orgCount,
            'pendingCompliance' => $pendingCompliance,
            'approvedActivities' => $approvedActivities,
            'templatesCount' => $templatesCount,
            'workflowStages' => $workflowStages,
            'workflowTotal' => $workflowTotal,
            'reviewTrend' => $reviewTrend,
            'queue' => $queue,
            'recentOrgs' => $recentOrgs,
        ]);
    }
}
