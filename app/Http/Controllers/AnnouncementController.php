<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $approvedProjects = Project::where('created_by', $user->id)
            ->whereIn('status', ['approved', 'csa_cleared'])
            ->orderBy('title')
            ->get();

        $announcements = Announcement::where('created_by', $user->id)
            ->orderByDesc('created_at')
            ->with('project', 'attachments')
            ->withCount(['applications as approved_volunteers' => fn ($query) => $query->where('status', 'approved')])
            ->get();

        $existingRecruitments = Announcement::query()
            ->whereIn('project_id', $approvedProjects->modelKeys())
            ->where('category', 'Volunteer recruitment')
            ->get(['id', 'project_id', 'title', 'status'])
            ->groupBy('project_id')
            ->map(fn ($recruitments) => $recruitments->map(fn (Announcement $announcement) => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'status' => $announcement->status,
                'url' => route('student_org.announcements.edit', $announcement),
            ])->values());

        $broadcastAnnouncements = Announcement::whereNull('project_id')
            ->where('status', 'published')
            ->with('creator', 'attachments')
            ->orderByDesc('published_at')
            ->take(10)
            ->get();

        return view('STUDENT_ORGANIZATION_PRESIDENT.announcements', [
            'approvedProjects' => $approvedProjects,
            'announcements' => $announcements,
            'existingRecruitments' => $existingRecruitments,
            'broadcastAnnouncements' => $broadcastAnnouncements,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $category = $request->input('category');
        $isRecruitment = $category === 'Volunteer recruitment';

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:300',
            'category' => ['required', Rule::in(['Volunteer recruitment', 'General'])],
            'project_id' => [
                $isRecruitment ? 'required' : 'nullable',
                'integer',
                Rule::exists('projects', 'id')->where(fn ($query) => $query
                    ->where('created_by', $user->id)
                    ->whereIn('status', ['approved', 'csa_cleared'])),
            ],
            'slots_needed' => [Rule::excludeIf(! $isRecruitment), Rule::requiredIf($isRecruitment), 'integer', 'min:1'],
            'application_deadline' => [
                Rule::excludeIf(! $isRecruitment),
                ...$this->applicationDeadlineRules(),
            ],
            'event_date' => [Rule::excludeIf(! $isRecruitment), ...$this->eventDateRules()],
            'event_location' => [Rule::excludeIf(! $isRecruitment), 'nullable', 'string', 'max:255'],
            'latitude' => [Rule::excludeIf(! $isRecruitment), 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => [Rule::excludeIf(! $isRecruitment), 'nullable', 'numeric', 'between:-180,180'],
            'status' => 'required|in:draft,published',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpg,png,pdf|max:5120',
        ], $this->announcementValidationMessages());

        $validated['created_by'] = $user->id;
        $validated['project_id'] = $validated['project_id'] ?? null;
        if ($isRecruitment && ! empty($validated['application_deadline'])) {
            $validated['application_deadline'] = Carbon::createFromFormat(
                'Y-m-d\TH:i',
                $validated['application_deadline'],
                'Asia/Manila'
            )->utc();
        }
        if (! $isRecruitment) {
            $validated = array_merge($validated, [
                'slots_needed' => null,
                'application_deadline' => null,
                'event_date' => null,
                'event_location' => null,
                'latitude' => null,
                'longitude' => null,
            ]);
        }

        if ($validated['status'] === 'published') {
            $validated['published_at'] = now();
        }

        $announcement = DB::transaction(function () use ($validated, $isRecruitment, $user): Announcement {
            if ($isRecruitment) {
                Project::query()
                    ->where('created_by', $user->id)
                    ->whereIn('status', ['approved', 'csa_cleared'])
                    ->lockForUpdate()
                    ->findOrFail($validated['project_id']);

                $alreadyHasRecruitment = Announcement::query()
                    ->where('project_id', $validated['project_id'])
                    ->where('category', 'Volunteer recruitment')
                    ->exists();

                if ($alreadyHasRecruitment) {
                    throw ValidationException::withMessages([
                        'project_id' => 'This activity already has a volunteer-recruitment announcement. Edit the existing announcement instead.',
                    ]);
                }
            }

            return Announcement::create($validated);
        });

        $this->storeAttachments($request, $announcement);

        return redirect()->route('student_org.announcements')->with('status', 'Announcement saved successfully.');
    }

    public function edit(Request $request, Announcement $announcement)
    {
        $this->ensureAnnouncementOwner($request, $announcement);

        return view('STUDENT_ORGANIZATION_PRESIDENT.announcement_edit', [
            'announcement' => $announcement->load('attachments', 'project'),
            'projects' => Project::where('created_by', $request->user()->id)
                ->whereIn('status', ['approved', 'csa_cleared'])
                ->orderBy('title')
                ->get(),
        ]);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $this->ensureAnnouncementOwner($request, $announcement);
        $user = $request->user();
        $category = $request->input('category');
        $isRecruitment = $category === 'Volunteer recruitment';

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:300',
            'category' => ['required', Rule::in(['Volunteer recruitment', 'General'])],
            'project_id' => [
                $isRecruitment ? 'required' : 'nullable',
                'integer',
                Rule::exists('projects', 'id')->where(fn ($query) => $query
                    ->where('created_by', $user->id)
                    ->whereIn('status', ['approved', 'csa_cleared'])),
            ],
            'slots_needed' => [Rule::excludeIf(! $isRecruitment), Rule::requiredIf($isRecruitment), 'integer', 'min:1'],
            'application_deadline' => [
                Rule::excludeIf(! $isRecruitment),
                ...$this->applicationDeadlineRules($announcement),
            ],
            'event_date' => [Rule::excludeIf(! $isRecruitment), ...$this->eventDateRules($announcement)],
            'event_location' => [Rule::excludeIf(! $isRecruitment), 'nullable', 'string', 'max:255'],
            'latitude' => [Rule::excludeIf(! $isRecruitment), 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => [Rule::excludeIf(! $isRecruitment), 'nullable', 'numeric', 'between:-180,180'],
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpg,png,pdf|max:5120',
        ], $this->announcementValidationMessages());

        if (
            $announcement->category === 'Volunteer recruitment'
            && (
                ! $isRecruitment
                || (int) ($validated['project_id'] ?? 0) !== (int) $announcement->project_id
            )
        ) {
            throw ValidationException::withMessages([
                'category' => 'A volunteer-recruitment announcement must remain linked to its activity. Edit its details instead of changing its type or activity.',
            ]);
        }

        if ($isRecruitment && (int) $validated['slots_needed'] < $announcement->approvedCount()) {
            return back()
                ->withErrors([
                    'slots_needed' => 'Slots needed cannot be fewer than the volunteers already approved.',
                ])
                ->withInput();
        }

        $newFileCount = count($request->file('attachments', []));
        if ($announcement->attachments()->count() + $newFileCount > 5) {
            return back()
                ->withErrors(['attachments' => 'You may have up to 5 files attached. Remove an existing file before adding another.'])
                ->withInput();
        }

        DB::transaction(function () use ($announcement, $validated, $isRecruitment, $user): void {
            $projectId = $validated['project_id'] ?? null;

            if ($isRecruitment && $announcement->category !== 'Volunteer recruitment') {
                Project::query()
                    ->where('created_by', $user->id)
                    ->whereIn('status', ['approved', 'csa_cleared'])
                    ->lockForUpdate()
                    ->findOrFail($projectId);

                $alreadyHasRecruitment = Announcement::query()
                    ->where('project_id', $projectId)
                    ->where('category', 'Volunteer recruitment')
                    ->where('id', '<>', $announcement->id)
                    ->exists();

                if ($alreadyHasRecruitment) {
                    throw ValidationException::withMessages([
                        'project_id' => 'This activity already has a volunteer-recruitment announcement. Edit that announcement instead.',
                    ]);
                }
            }

            $announcement->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'category' => $validated['category'],
                'project_id' => $projectId,
                'slots_needed' => $isRecruitment ? ($validated['slots_needed'] ?? null) : null,
                'application_deadline' => $isRecruitment && ! empty($validated['application_deadline'])
                    ? Carbon::createFromFormat('Y-m-d\TH:i', $validated['application_deadline'], 'Asia/Manila')->utc()
                    : null,
                'event_date' => $isRecruitment ? ($validated['event_date'] ?? null) : null,
                'event_location' => $isRecruitment ? ($validated['event_location'] ?? null) : null,
                'latitude' => $isRecruitment ? ($validated['latitude'] ?? null) : null,
                'longitude' => $isRecruitment ? ($validated['longitude'] ?? null) : null,
            ]);
        });

        $this->storeAttachments($request, $announcement);

        return redirect()
            ->route('student_org.announcements.show', $announcement)
            ->with('status', 'Announcement updated successfully.');
    }

    public function updateStatus(Request $request, Announcement $announcement)
    {
        $user = $request->user();
        if (! in_array($user->role, ['student_org', 'college_department'], true)) {
            abort(403, 'Unauthorized');
        }

        if ($announcement->created_by !== $user->id) {
            abort(403, 'You may only update your own announcements.');
        }

        $validated = $request->validate([
            'status' => 'required|in:draft,published',
        ]);

        $updateResult = DB::transaction(function () use ($announcement, $validated, $user): string {
            if ($validated['status'] === 'published' && $announcement->category === 'Volunteer recruitment') {
                $project = Project::query()
                    ->where('created_by', $user->id)
                    ->lockForUpdate()
                    ->findOrFail($announcement->project_id);

                if (! in_array($project->status, ['approved', 'csa_cleared'], true)) {
                    return 'project_not_approved';
                }

                if (Announcement::query()
                    ->where('project_id', $announcement->project_id)
                    ->where('category', 'Volunteer recruitment')
                    ->where('id', '<>', $announcement->id)
                    ->exists()) {
                    return 'duplicate';
                }
            }

            $announcement->update([
                'status' => $validated['status'],
                'published_at' => $validated['status'] === 'published' ? now() : null,
            ]);

            return 'updated';
        });

        if ($updateResult === 'duplicate') {
            return back()->withErrors([
                'status' => 'Another volunteer-recruitment announcement already exists for this activity. Edit that post instead.',
            ]);
        }
        if ($updateResult === 'project_not_approved') {
            return back()->withErrors([
                'status' => 'This activity must pass CELP/CSA approval before its recruitment announcement can be published.',
            ]);
        }

        $message = $validated['status'] === 'published'
            ? 'Announcement published successfully.'
            : 'Announcement unpublished and saved as a draft.';

        return redirect()->route('student_org.announcements')->with('status', $message);
    }

    public function show(Request $request, Announcement $announcement)
    {
        $user = $request->user();

        if ($announcement->created_by !== $user->id) {
            abort(403, 'You may only view your own announcements.');
        }

        $announcement->load('project', 'attachments');

        return view('STUDENT_ORGANIZATION_PRESIDENT.announcement_show', [
            'announcement' => $announcement,
        ]);
    }

    public function destroyAttachment(Request $request, Announcement $announcement, AnnouncementAttachment $attachment)
    {
        $this->ensureAnnouncementOwner($request, $announcement);

        $attachment = $announcement->attachments()->whereKey($attachment->id)->firstOrFail();

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        $destination = $request->input('return_to') === 'edit'
            ? route('student_org.announcements.edit', $announcement)
            : route('student_org.announcements.show', $announcement);

        return redirect($destination)->with('status', 'Attachment removed successfully.');
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        $this->ensureAnnouncementOwner($request, $announcement);

        if ($announcement->category === 'Volunteer recruitment') {
            return back()->withErrors([
                'announcement' => 'Volunteer-recruitment announcements are retained for this activity. Unpublish or edit the existing post instead of removing it.',
            ]);
        }

        foreach ($announcement->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $announcement->delete();

        return redirect()->route('student_org.announcements')->with('status', 'Announcement removed successfully.');
    }

    private function ensureAnnouncementOwner(Request $request, Announcement $announcement): void
    {
        if ($announcement->created_by !== $request->user()->id) {
            abort(403, 'You may only manage your own announcements.');
        }
    }

    private function eventDateRules(?Announcement $announcement = null): array
    {
        return [
            'bail',
            'nullable',
            'date',
            function (string $attribute, mixed $value, \Closure $fail) use ($announcement): void {
                if ($announcement?->event_date?->format('Y-m-d\TH:i') === $value) {
                    return;
                }

                if (Carbon::parse($value, 'Asia/Manila')->isPast()) {
                    $fail('The event date and time must be now or in the future.');
                }
            },
        ];
    }

    private function announcementValidationMessages(): array
    {
        return [
            'slots_needed.required' => 'Enter how many volunteers are needed for this recruitment.',
            'slots_needed.integer' => 'The number of volunteer slots must be a whole number.',
            'slots_needed.min' => 'At least one volunteer slot is required.',
            'application_deadline.date_format' => 'Enter the deadline date and time.',
            'attachments.max' => 'You may upload up to 5 files only.',
            'attachments.*.mimes' => 'Attachments must be JPG, PNG or PDF files.',
            'attachments.*.max' => 'Each attachment must be 5MB or smaller.',
            'event_date.date' => 'Please enter a valid event date and time.',
        ];
    }

    private function applicationDeadlineRules(?Announcement $announcement = null): array
    {
        return [
            'bail',
            'nullable',
            'date_format:Y-m-d\TH:i',
            function (string $attribute, mixed $value, \Closure $fail) use ($announcement): void {
                if ($value === null || $value === '') {
                    return;
                }

                $deadline = Carbon::createFromFormat('Y-m-d\TH:i', $value, 'Asia/Manila');
                if ($announcement?->application_deadline?->timezone('Asia/Manila')->format('Y-m-d\TH:i') === $value) {
                    return;
                }
                if ($deadline->lt(now('Asia/Manila')->startOfMinute())) {
                    $fail('The application deadline must be now or in the future.');
                } elseif ($deadline->gt(now('Asia/Manila')->addYear()->startOfMinute())) {
                    $fail('The application deadline must be within one year.');
                }
            },
        ];
    }

    private function storeAttachments(Request $request, Announcement $announcement): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('announcement_attachments', 'public');
            AnnouncementAttachment::create([
                'announcement_id' => $announcement->id,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    // CELP Secretary: general broadcast notices, not tied to a specific org project.

    public function secretaryIndex(Request $request)
    {
        if ($request->user()->role !== 'celp_secretary') {
            abort(403);
        }

        $announcements = Announcement::whereNull('project_id')
            ->where('created_by', $request->user()->id)
            ->orderByDesc('created_at')
            ->with('attachments')
            ->get();

        return view('CELP.secretary.announcements', [
            'announcements' => $announcements,
        ]);
    }

    public function secretaryStore(Request $request)
    {
        if ($request->user()->role !== 'celp_secretary') {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|string|max:50',
            'status' => 'required|in:draft,published',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpg,png,pdf|max:5120',
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['project_id'] = null;

        if ($validated['status'] === 'published') {
            $validated['published_at'] = now();
        }

        $announcement = Announcement::create($validated);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('announcement_attachments', 'public');
                AnnouncementAttachment::create([
                    'announcement_id' => $announcement->id,
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        return redirect()->route('celp.secretary.announcements')->with('status', 'Announcement saved successfully.');
    }

    public function secretaryShow(Request $request, Announcement $announcement)
    {
        $user = $request->user();

        if ($user->role !== 'celp_secretary' || $announcement->created_by !== $user->id) {
            abort(403, 'You may only view your own announcements.');
        }

        $announcement->load('attachments');

        return view('CELP.secretary.announcement_show', [
            'announcement' => $announcement,
        ]);
    }

    public function secretaryDestroy(Request $request, Announcement $announcement)
    {
        $user = $request->user();

        if ($user->role !== 'celp_secretary' || $announcement->created_by !== $user->id) {
            abort(403, 'You may only remove your own announcements.');
        }

        foreach ($announcement->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $announcement->delete();

        return redirect()->route('celp.secretary.announcements')->with('status', 'Announcement removed successfully.');
    }
}
