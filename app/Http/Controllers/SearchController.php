<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Proposal;
use App\Models\Template;
use App\Models\Announcement;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\User;
use Illuminate\Support\Carbon\Carbon;

class SearchController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = trim($request->query('q', ''));
        if ($query === '') {
            return response()->json(['groups' => []]);
        }

        $queryTerm = "%{$query}%";

        $proposals = Proposal::where(function ($q) use ($query, $queryTerm) {
            $q->where('title', 'like', $queryTerm)
                ->orWhere('description', 'like', $queryTerm)
                ->orWhere('status', 'like', $queryTerm);

            if (is_numeric($query)) {
                $q->orWhere('id', $query);
            }
        })
        ->orderBy('updated_at', 'desc')
        ->take(5)
        ->get();

        $templates = Template::where('original_name', 'like', $queryTerm)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $announcements = Announcement::where(function ($q) use ($queryTerm) {
            $q->where('title', 'like', $queryTerm)
                ->orWhere('description', 'like', $queryTerm);
        })
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get();

        $projects = Project::where(function ($q) use ($query, $queryTerm) {
            $q->where('title', 'like', $queryTerm)
                ->orWhere('description', 'like', $queryTerm);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $query)) {
                $q->orWhereDate('created_at', $query);
            }
        })
        ->orderBy('updated_at', 'desc')
        ->take(5)
        ->get();

        $attachments = ProjectAttachment::where('file_name', 'like', $queryTerm)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $participants = User::whereIn('role', ['participant', 'volunteer'])
            ->where(function ($q) use ($query, $queryTerm) {
                $q->where('name', 'like', $queryTerm)
                    ->orWhere('username', 'like', $queryTerm)
                    ->orWhere('email', 'like', $queryTerm);

                if (is_numeric($query)) {
                    $q->orWhere('id', $query);
                }
            })
            ->orderBy('name')
            ->take(5)
            ->get();

        return response()->json([
            'groups' => [
                [
                    'label' => 'Proposals',
                    'count' => $proposals->count(),
                    'items' => $proposals->map(fn($proposal) => [
                        'title' => $proposal->title,
                        'subtitle' => "Proposal #{$proposal->id} · {$proposal->status}",
                        'url' => route('student_org.proposals'),
                    ])->values(),
                ],
                [
                    'label' => 'Documents',
                    'count' => $attachments->count(),
                    'items' => $attachments->map(fn($attachment) => [
                        'title' => $attachment->file_name,
                        'subtitle' => "Document for project #{$attachment->project_id}",
                        'url' => route('student_org.proposals'),
                    ])->values(),
                ],
                [
                    'label' => 'Templates',
                    'count' => $templates->count(),
                    'items' => $templates->map(fn($template) => [
                        'title' => $template->original_name,
                        'subtitle' => 'Template file',
                        'url' => route('templates.download', $template->id),
                    ])->values(),
                ],
                [
                    'label' => 'Announcements',
                    'count' => $announcements->count(),
                    'items' => $announcements->map(fn($announcement) => [
                        'title' => $announcement->title,
                        'subtitle' => trim($announcement->description) ?: 'Announcement',
                        'url' => route('student_org.announcements'),
                    ])->values(),
                ],
                [
                    'label' => 'Participants',
                    'count' => $participants->count(),
                    'items' => $participants->map(fn($participant) => [
                        'title' => $participant->name,
                        'subtitle' => "ID #{$participant->id} · {$participant->role}",
                        'url' => route('student_org.volunteers'),
                    ])->values(),
                ],
                [
                    'label' => 'Activities',
                    'count' => $projects->count(),
                    'items' => $projects->map(fn($project) => [
                        'title' => $project->title,
                        'subtitle' => trim($project->description) ?: 'Project activity',
                        'url' => route('student_org.proposals'),
                    ])->values(),
                ],
            ],
        ]);
    }
}
