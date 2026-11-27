<?php

namespace App\Http\Controllers;

use App\Exceptions\DocumentPreviewException;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\User;
use App\Notifications\ProjectStatusChanged;
use App\Services\DocumentPreviewService;
use App\Services\ProjectDocumentAccess;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public const MAX_ATTACHMENTS = 10;

    public const MAX_ATTACHMENT_SIZE_KB = 5120;

    public function __construct(
        private readonly DocumentPreviewService $documents,
        private readonly ProjectDocumentAccess $documentAccess,
    ) {}

    public function create(Request $request)
    {
        $user = $request->user();
        if (! in_array($user->role, ['student_org', 'college_department'])) {
            abort(403, 'Only organization accounts can submit projects.');
        }

        $submissionToken = (string) Str::uuid();
        $request->session()->put('project_submission_token', $submissionToken);

        return view('STUDENT_ORGANIZATION_PRESIDENT.projects.create', ['submissionToken' => $submissionToken]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (! in_array($user->role, ['student_org', 'college_department'])) {
            abort(403, 'Only organization accounts can submit projects.');
        }

        // Guard against duplicate submissions from double-clicks or a resubmitted
        // back-button page: each create-form load mints a one-time token that
        // must match what's still in the session.
        $expectedToken = $request->session()->get('project_submission_token');
        if (! $expectedToken || $request->input('submission_token') !== $expectedToken) {
            return redirect()->route('student_org.dashboard')
                ->with('status', 'This proposal was already submitted. Check "My proposals" to confirm, or start a new submission from Create proposal.');
        }

        $request->validate([
            'title' => 'required|string|max:120',
            'description' => 'nullable|string|max:300',
            'attachments' => 'nullable|array|max:'.self::MAX_ATTACHMENTS,
            'attachments.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,webp,zip|max:'.self::MAX_ATTACHMENT_SIZE_KB,
        ], [
            'title.max' => 'Title may not exceed 120 characters.',
            'description.max' => 'Description may not exceed 300 characters.',
            'attachments.max' => 'You may upload up to '.self::MAX_ATTACHMENTS.' files only.',
            'attachments.*.mimes' => 'Only PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, JPEG, WEBP, and ZIP files are allowed.',
            'attachments.*.max' => 'Each file must be 5MB or smaller.',
        ]);

        if ($corruptFile = $this->firstCorruptOfficeAttachment($request->file('attachments', []))) {
            return back()->withErrors([
                'attachments' => 'The file "'.$corruptFile.'" appears corrupted or incomplete. Open it and save a fresh copy before uploading.',
            ])->withInput();
        }

        $project = DB::transaction(function () use ($request, $user): Project {
            $project = Project::create([
                'title' => $request->title,
                'description' => $request->description,
                'created_by' => $user->id,
                'status' => 'submitted',
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if (! $file->isValid()) {
                        continue;
                    }

                    $this->storeProjectAttachment($project->id, $file);
                }
            }

            return $project;
        });

        $request->session()->forget('project_submission_token');

        // Notify CELP Secretary of new submission
        $this->notify(
            User::where('role', 'celp_secretary')->get(),
            $project,
            'New project submitted',
            "{$user->name} submitted a new proposal \"{$project->title}\" for CELP Secretary review.",
            route('projects.secretary_review')
        );

        if (in_array($user->role, ['student_org', 'college_department'])) {
            return redirect()->route('student_org.dashboard')->with('proposal_success', [
                'status' => $project->status,
                'status_label' => $project->status === 'submitted'
                    ? 'Pending review'
                    : Project::statusLabel($project->status),
                'project_id' => $project->id,
            ]);
        }

        return redirect()->route('dashboard')->with('status', 'Project submitted successfully. CELP Secretary will be notified.');
    }

    public function edit(Request $request, Project $project)
    {
        if (! in_array($request->user()->role, ['student_org', 'college_department']) || $project->created_by !== $request->user()->id) {
            abort(403, 'Only the submitting organization may edit this proposal.');
        }

        if (! str_starts_with($project->status, 'returned_by_')) {
            abort(403, 'Only returned proposals can be edited and resubmitted.');
        }

        return view('STUDENT_ORGANIZATION_PRESIDENT.projects.edit', ['project' => $project->load('attachments')]);
    }

    public function update(Request $request, Project $project)
    {
        if (! in_array($request->user()->role, ['student_org', 'college_department']) || $project->created_by !== $request->user()->id) {
            abort(403, 'Only the submitting organization may update this proposal.');
        }

        if (! str_starts_with($project->status, 'returned_by_')) {
            abort(403, 'Only returned proposals can be resubmitted.');
        }

        $request->validate([
            'title' => 'required|string|max:120',
            'description' => 'nullable|string|max:300',
            'attachments' => 'nullable|array|max:'.self::MAX_ATTACHMENTS,
            'attachments.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,webp,zip|max:'.self::MAX_ATTACHMENT_SIZE_KB,
            'remove_attachments' => 'nullable|array',
            'remove_attachments.*' => 'integer|exists:project_attachments,id',
        ], [
            'title.max' => 'Title may not exceed 120 characters.',
            'description.max' => 'Description may not exceed 300 characters.',
            'attachments.max' => 'You may upload up to '.self::MAX_ATTACHMENTS.' files only.',
            'attachments.*.mimes' => 'Only PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, JPEG, WEBP, and ZIP files are allowed.',
            'attachments.*.max' => 'Each file must be 5MB or smaller.',
        ]);

        if ($corruptFile = $this->firstCorruptOfficeAttachment($request->file('attachments', []))) {
            return back()->withErrors([
                'attachments' => 'The file "'.$corruptFile.'" appears corrupted or incomplete. Open it and save a fresh copy before uploading.',
            ])->withInput();
        }

        $requestedRemoveIds = collect($request->input('remove_attachments', []))
            ->map(fn ($id) => (int) $id)
            ->all();
        $removeIds = $project->attachments()
            ->whereIn('id', $requestedRemoveIds)
            ->pluck('id')
            ->all();
        $remainingAttachments = $project->attachments()
            ->when($removeIds, fn ($query) => $query->whereNotIn('id', $removeIds))
            ->count();
        $newFiles = $request->file('attachments') ? count($request->file('attachments')) : 0;

        if ($remainingAttachments + $newFiles > self::MAX_ATTACHMENTS) {
            return back()->withErrors(['attachments' => 'You may upload no more than '.self::MAX_ATTACHMENTS.' files in total.'])->withInput();
        }

        $attachmentsToRemove = $project->attachments()->whereIn('id', $removeIds)->get();
        DB::transaction(function () use ($request, $project, $attachmentsToRemove): void {
            $project->title = $request->title;
            $project->description = $request->description;
            $project->status = 'submitted';
            $project->review_comments = null;
            $project->save();

            foreach ($request->file('attachments', []) as $file) {
                $this->storeProjectAttachment($project->id, $file);
            }
            foreach ($attachmentsToRemove as $attachment) {
                $attachment->delete();
            }
        });
        foreach ($attachmentsToRemove as $attachment) {
            $this->documents->deleteStoredFile($attachment);
        }

        return redirect()->route('student_org.notifications')->with('status', 'Proposal updated and resubmitted. CELP Secretary will be notified.');
    }

    public function destroyOwnProposal(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['student_org', 'college_department'], true), 403);
        abort_unless($project->created_by === $user->id, 403, 'You may only delete your own proposals.');
        abort_unless(
            $project->status === 'submitted' || str_starts_with($project->status, 'returned_by_'),
            403,
            'Only submitted or returned proposals can be deleted.'
        );

        if (Announcement::where('project_id', $project->id)->exists() || $project->attendanceSessions()->exists()) {
            return redirect()->route('student_org.proposals')->withErrors([
                'proposal' => 'This proposal has linked activity or attendance records and cannot be deleted.',
            ]);
        }

        $attachments = $project->attachments()->get();
        foreach ($attachments as $attachment) {
            if (! $this->documents->deleteStoredFile($attachment)) {
                throw new \RuntimeException("Could not remove proposal attachment [{$attachment->file_path}].");
            }
        }

        DB::transaction(fn () => $project->delete());

        AuditLog::record(
            'project.deleted',
            "Deleted the proposal \"{$project->title}\".",
            $project,
            ['status' => $project->status, 'reference' => 'PRP-'.str_pad((string) $project->id, 4, '0', STR_PAD_LEFT)]
        );

        return redirect()->route('student_org.proposals')->with('status', 'Proposal deleted.');
    }

    // Secretary: list projects awaiting initial review
    public function secretaryIndex(Request $request)
    {
        if ($request->user()->role !== 'celp_secretary') {
            abort(403);
        }

        $projects = Project::where('status', 'submitted')
            ->with('creator')
            ->withCount('attachments')
            ->orderByDesc('created_at')
            ->get();

        return view('projects.review', ['projects' => $projects, 'for' => 'secretary']);
    }

    // Coordinator: list projects endorsed by secretary
    public function coordinatorIndex(Request $request)
    {
        if ($request->user()->role !== 'coordinator') {
            abort(403);
        }

        $projects = Project::where('status', 'endorsed_by_secretary')
            ->with('creator')
            ->withCount('attachments')
            ->orderByDesc('created_at')
            ->get();

        return view('projects.review', ['projects' => $projects, 'for' => 'coordinator']);
    }

    public function showForReview(Request $request, Project $project)
    {
        abort_unless($this->documentAccess->canReview($request->user(), $project), 403);

        return view('projects.show_review', ['project' => $project->load('attachments', 'creator')]);
    }

    public function previewAttachment(Request $request, ProjectAttachment $attachment)
    {
        $project = $this->reviewableAttachmentProject($request, $attachment);
        $extension = strtolower(pathinfo($attachment->file_name, PATHINFO_EXTENSION));

        return view('projects.attachment_preview', [
            'project' => $project->load('creator'),
            'attachment' => $attachment,
            'extension' => strtoupper($extension ?: 'FILE'),
            'previewKind' => $this->documents->previewKind($attachment->file_name),
            'contentUrl' => route('projects.attachments.preview.content', $attachment),
            'downloadUrl' => route('projects.attachments.download', $attachment),
        ]);
    }

    public function previewAttachmentContent(Request $request, ProjectAttachment $attachment)
    {
        $this->reviewableAttachmentProject($request, $attachment);

        return $this->documents->response($attachment);
    }

    public function downloadAttachment(Request $request, ProjectAttachment $attachment)
    {
        $this->reviewableAttachmentProject($request, $attachment);

        return $this->documents->response($attachment, true);
    }

    private function storeProjectAttachment(int $projectId, UploadedFile $file): void
    {
        $this->documents->storeUpload($file, new ProjectAttachment(['project_id' => $projectId]), 'project_attachments');
    }

    private function firstCorruptOfficeAttachment(array $files): ?string
    {
        foreach ($files as $file) {
            try {
                $this->documents->validateUpload($file);
            } catch (DocumentPreviewException $error) {
                if ($error->getStatusCode() >= 500) {
                    throw $error;
                }

                return basename($file->getClientOriginalName());
            }
        }

        return null;
    }

    private function reviewableAttachmentProject(Request $request, ProjectAttachment $attachment): Project
    {
        $project = $attachment->project()->first();
        abort_unless($project, 404);
        abort_unless($this->documentAccess->canReview($request->user(), $project), 403);

        return $project;
    }

    /**
     * Write a review decision to the audit log.
     *
     * The reviewer's comment is copied in here on purpose: advancing a project
     * clears `review_comments` on the row (so the initiator isn't shown stale
     * feedback), which used to destroy the record of why it had been returned.
     * The log keeps that reason recoverable after the fact.
     */
    private function logReview(Project $project, string $fromStatus, Request $request): void
    {
        $returned = str_starts_with($project->status, 'returned_by_');
        $humanStatus = str_replace('_', ' ', $project->status);

        AuditLog::record(
            $returned ? 'project.returned' : 'project.advanced',
            $returned
                ? "Returned \"{$project->title}\" to the initiator."
                : "Moved \"{$project->title}\" to {$humanStatus}.",
            $project,
            array_filter([
                'from_status' => $fromStatus,
                'to_status' => $project->status,
                'comments' => $request->input('comments'),
                'organization' => $project->creator->organization_name ?? $project->creator->name ?? null,
            ], fn ($v) => $v !== null && $v !== '')
        );
    }

    public function secretaryReview(Request $request, Project $project)
    {
        if ($request->user()->role !== 'celp_secretary') {
            abort(403);
        }

        $request->validate(['action' => 'required|in:endorse,return', 'comments' => 'nullable|string']);
        $fromStatus = $project->status;

        if ($request->action === 'endorse') {
            $project->status = 'endorsed_by_secretary';
            $project->review_comments = null;
            if (Schema::hasColumn('projects', 'notification_seen_at')) {
                $project->notification_seen_at = null;
            }
            $project->save();

            $this->notify(
                User::where('role', 'coordinator')->get(),
                $project,
                'Project ready for coordinator review',
                "\"{$project->title}\" was endorsed by the CELP Secretary and is now awaiting your review.",
                route('projects.coordinator_review')
            );
            $this->notify(
                $project->creator,
                $project,
                'Proposal endorsed by CELP Secretary',
                "Your proposal \"{$project->title}\" was endorsed by the CELP Secretary and is now with the Coordinator for review.",
                route('student_org.proposals')
            );
        } else {
            $project->status = 'returned_by_secretary';
            $project->review_comments = $request->comments;
            if (Schema::hasColumn('projects', 'notification_seen_at')) {
                $project->notification_seen_at = null;
            }
            $project->save();

            $this->notify(
                $project->creator,
                $project,
                'Proposal returned by CELP Secretary',
                "Your proposal \"{$project->title}\" was returned by the CELP Secretary".($request->comments ? ": {$request->comments}" : '.'),
                route('student_org.notifications')
            );
        }

        $this->logReview($project, $fromStatus, $request);

        return redirect()->route('projects.secretary_review')->with('status', 'Project status updated.');
    }

    public function coordinatorReview(Request $request, Project $project)
    {
        if ($request->user()->role !== 'coordinator') {
            abort(403);
        }

        $request->validate(['action' => 'required|in:approve,return', 'comments' => 'nullable|string']);
        $fromStatus = $project->status;

        if ($request->action === 'approve') {
            $project->status = 'endorsed_by_coordinator';
            $project->review_comments = null;
            if (Schema::hasColumn('projects', 'notification_seen_at')) {
                $project->notification_seen_at = null;
            }
            $project->save();

            $this->notify(
                User::where('role', 'celp_head')->get(),
                $project,
                'Project ready for CELP Head review',
                "\"{$project->title}\" was endorsed by the Coordinator and is now awaiting your final CELP review.",
                route('projects.celp_head_review')
            );
            $this->notify(
                $project->creator,
                $project,
                'Proposal endorsed by Coordinator',
                "Your proposal \"{$project->title}\" was endorsed by the Coordinator and is now with the CELP Head for final CELP checking.",
                route('student_org.proposals')
            );
        } else {
            $project->status = 'returned_by_coordinator';
            $project->review_comments = $request->comments;
            if (Schema::hasColumn('projects', 'notification_seen_at')) {
                $project->notification_seen_at = null;
            }
            $project->save();

            $this->notify(
                $project->creator,
                $project,
                'Proposal returned by Coordinator',
                "Your proposal \"{$project->title}\" was returned by the Coordinator".($request->comments ? ": {$request->comments}" : '.'),
                route('student_org.notifications')
            );
        }

        $this->logReview($project, $fromStatus, $request);

        return redirect()->route('projects.coordinator_review')->with('status', 'Project status updated.');
    }

    // CELP Head: list projects endorsed by coordinator, awaiting final CELP checking
    public function celpHeadIndex(Request $request)
    {
        if ($request->user()->role !== 'celp_head') {
            abort(403);
        }

        $projects = Project::where('status', 'endorsed_by_coordinator')
            ->with('creator')
            ->withCount('attachments')
            ->orderByDesc('created_at')
            ->get();

        return view('projects.review', ['projects' => $projects, 'for' => 'celp_head']);
    }

    /**
     * Browse every project in the system, at any stage.
     *
     * The stage queues each show one status, which left no way to look up a
     * project once it had moved on. This is the "what happened to X" view:
     * filterable by status, searchable by title or organization, paginated
     * because it grows without bound.
     */
    public function allIndex(Request $request)
    {
        if ($request->user()->role !== 'celp_head') {
            abort(403);
        }

        $status = $request->query('status');
        $term = trim((string) $request->query('q', ''));

        // LOWER(...) on both sides rather than LIKE: this app runs on Postgres,
        // where plain LIKE is case-sensitive and would miss most searches.
        $search = function ($query) use ($term) {
            if ($term === '') {
                return;
            }
            $needle = '%'.mb_strtolower($term).'%';
            $query->where(function ($inner) use ($needle) {
                $inner->whereRaw('LOWER(title) LIKE ?', [$needle])
                    ->orWhereHas('creator', fn ($c) => $c
                        ->whereRaw('LOWER(organization_name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(name) LIKE ?', [$needle]));
            });
        };

        $projects = Project::query()
            ->with('creator')
            ->withCount('attachments')
            ->when($status && array_key_exists($status, Project::STATUS_LABELS),
                fn ($q) => $q->where('status', $status))
            ->where($search)
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        // Counts ignore the status filter so every tab keeps showing its true
        // total; they do respect the search term so the tabs match the results.
        $statusCounts = Project::query()
            ->where($search)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('CELP.head.projects.all', [
            'projects' => $projects,
            'statusCounts' => $statusCounts,
            'activeStatus' => $status,
            'term' => $term,
        ]);
    }

    public function celpHeadReview(Request $request, Project $project)
    {
        if ($request->user()->role !== 'celp_head') {
            abort(403);
        }

        $request->validate(['action' => 'required|in:approve,return', 'comments' => 'nullable|string']);
        $fromStatus = $project->status;

        if ($request->action === 'approve') {
            $project->status = 'approved';
            $project->review_comments = null;
            if (Schema::hasColumn('projects', 'notification_seen_at')) {
                $project->notification_seen_at = null;
            }
            $project->save();

            $this->notify(
                User::where('role', 'csa_director')->get(),
                $project,
                'Project ready for CSA compliance review',
                "\"{$project->title}\" was approved by the CELP Head and is now awaiting CSA Director compliance clearance.",
                route('csa.director.dashboard')
            );
            $this->notify(
                $project->creator,
                $project,
                'Proposal approved by CELP Head',
                "Your proposal \"{$project->title}\" was approved by the CELP Head and is now with the CSA Director for final compliance clearance.",
                route('student_org.proposals')
            );
        } else {
            $project->status = 'returned_by_celp_head';
            $project->review_comments = $request->comments;
            if (Schema::hasColumn('projects', 'notification_seen_at')) {
                $project->notification_seen_at = null;
            }
            $project->save();

            $this->notify(
                $project->creator,
                $project,
                'Proposal returned by CELP Head',
                "Your proposal \"{$project->title}\" was returned by the CELP Head".($request->comments ? ": {$request->comments}" : '.'),
                route('student_org.notifications')
            );
        }

        $this->logReview($project, $fromStatus, $request);

        return redirect()->route('projects.celp_head_review')->with('status', 'Project status updated.');
    }

    // CSA Director: list projects approved by CELP Head, awaiting final compliance clearance
    public function csaDirectorIndex(Request $request)
    {
        if ($request->user()->role !== 'csa_director') {
            abort(403);
        }

        $projects = Project::where('status', 'approved')
            ->with('creator')
            ->withCount('attachments')
            ->orderByDesc('created_at')
            ->get();

        return view('projects.review', ['projects' => $projects, 'for' => 'csa_director']);
    }

    public function csaDirectorReview(Request $request, Project $project)
    {
        if ($request->user()->role !== 'csa_director') {
            abort(403);
        }

        $validated = $request->validate([
            'action' => 'required|in:clear,return',
            'comments' => ['required_if:action,return', 'string', 'max:2000'],
        ], [
            'comments.required_if' => 'Please explain what changes are needed before returning this proposal.',
        ]);
        $fromStatus = $project->status;

        if ($request->action === 'clear') {
            $project->status = 'csa_cleared';
            $project->review_comments = null;
            if (Schema::hasColumn('projects', 'notification_seen_at')) {
                $project->notification_seen_at = null;
            }
            $project->save();

            $this->notify(
                $project->creator,
                $project,
                'Proposal fully approved',
                "Your proposal \"{$project->title}\" has been cleared by the CSA Director. Your activity is fully approved and may proceed.",
                route('student_org.proposals')
            );
        } else {
            $project->status = 'returned_by_csa';
            $project->review_comments = $validated['comments'];
            if (Schema::hasColumn('projects', 'notification_seen_at')) {
                $project->notification_seen_at = null;
            }
            $project->save();

            $this->notify(
                $project->creator,
                $project,
                'Proposal returned by CSA Director',
                "Your proposal \"{$project->title}\" was returned by the CSA Director: {$validated['comments']}",
                route('student_org.notifications')
            );
        }

        $this->logReview($project, $fromStatus, $request);

        return redirect()->route('projects.csa_review')->with('status', 'Project status updated.');
    }

    /**
     * Send a ProjectStatusChanged notification to one user or a collection of users.
     * Failures (e.g. mail server unavailable) are non-fatal so review actions never fail because of them.
     */
    private function notify($recipients, Project $project, string $title, string $body, string $url): void
    {
        if ($recipients instanceof Collection && $recipients->isEmpty()) {
            return;
        }

        try {
            Notification::send($recipients, new ProjectStatusChanged(
                $project,
                $title,
                $body,
                $url,
                auth()->user() instanceof User ? auth()->user() : null
            ));
        } catch (\Exception $e) {
            // non-fatal
        }
    }
}
