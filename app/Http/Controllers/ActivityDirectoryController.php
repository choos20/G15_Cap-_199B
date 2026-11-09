<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Read-only directory of community extension activities.
 *
 * Participants get their own feed (ParticipantController) which is scoped to
 * what they can still apply to and carries the apply form. This is the
 * awareness view for everyone else — CELP staff, CSA, and the organizations
 * themselves — so it deliberately shows the full picture (including full,
 * closed, and past activities) and offers no way to join.
 */
class ActivityDirectoryController extends Controller
{
    /**
     * Participants are bounced to their own feed rather than 403'd: the link is
     * the same idea, theirs just does more.
     */
    private function guard(Request $request)
    {
        if ($request->user()->role === 'participant') {
            return redirect()->route('participant.activities');
        }

        return null;
    }

    /**
     * Published activities attached to a project that cleared review. Anything
     * still in the approval pipeline stays out of the directory.
     */
    private function baseQuery()
    {
        return Announcement::query()
            ->where('status', 'published')
            ->whereNotNull('project_id')
            ->whereHas('project', fn ($q) => $q->whereIn('status', ['approved', 'csa_cleared']));
    }

    public function index(Request $request)
    {
        if ($redirect = $this->guard($request)) {
            return $redirect;
        }

        $term = trim((string) $request->query('q', ''));
        $orgId = $request->query('org');
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['recruiting', 'applications_closed', 'full', 'completed'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $status = $filters['status'] ?? '';
        $from = $filters['from'] ?? '';
        $to = $filters['to'] ?? '';

        // LOWER(...) on both sides: this app runs on Postgres, where a plain
        // LIKE is case-sensitive and would miss most searches.
        $search = function ($query) use ($term) {
            if ($term === '') {
                return;
            }
            $needle = '%'.mb_strtolower($term).'%';
            $query->where(function ($inner) use ($needle) {
                $inner->whereRaw('LOWER(title) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(event_location, \'\')) LIKE ?', [$needle])
                    ->orWhereHas('creator', fn ($c) => $c
                        ->whereRaw('LOWER(COALESCE(organization_name, \'\')) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(name) LIKE ?', [$needle]));
            });
        };

        $approvedCountSql = '(SELECT COUNT(*) FROM volunteer_applications '
            .'WHERE volunteer_applications.announcement_id = announcements.id '
            .'AND volunteer_applications.status = ?)';

        $activities = $this->baseQuery()
            ->with('creator', 'project')
            ->withCount(['applications as approved_volunteers' => fn ($q) => $q->where('status', 'approved')])
            ->where($search)
            ->when($orgId, fn ($q) => $q->where('created_by', $orgId))
            ->when($from, fn ($q) => $q->whereDate('event_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('event_date', '<=', $to))
            ->when($status === 'completed', fn ($q) => $q->where('event_date', '<', now()))
            ->when($status === 'full', fn ($q) => $q
                ->whereNotNull('slots_needed')
                ->where(fn ($query) => $query->whereNull('event_date')->orWhere('event_date', '>=', now()))
                ->whereRaw('slots_needed <= '.$approvedCountSql, ['approved']))
            ->when($status === 'applications_closed', fn ($q) => $q
                ->whereNotNull('slots_needed')
                ->where('application_deadline', '<', now())
                ->where(fn ($query) => $query->whereNull('event_date')->orWhere('event_date', '>=', now()))
                ->whereRaw('slots_needed > '.$approvedCountSql, ['approved']))
            ->when($status === 'recruiting', fn ($q) => $q
                ->whereNotNull('slots_needed')
                ->where(fn ($query) => $query->whereNull('application_deadline')->orWhere('application_deadline', '>=', now()))
                ->where(fn ($query) => $query->whereNull('event_date')->orWhere('event_date', '>=', now()))
                ->whereRaw('slots_needed > '.$approvedCountSql, ['approved']))
            ->orderByRaw('COALESCE(event_date, published_at) DESC')
            ->paginate(12)
            ->withQueryString();

        // Only organizations that actually have something in the directory, so
        // the picker can never lead to an empty result.
        $organizations = User::query()
            ->whereIn('id', $this->baseQuery()->select('created_by'))
            ->orderByRaw('COALESCE(organization_name, name)')
            ->get(['id', 'name', 'organization_name']);

        return view('activities.index', [
            'activities' => $activities,
            'organizations' => $organizations,
            'term' => $term,
            'orgId' => $orgId,
            'status' => $status,
            'from' => $from,
            'to' => $to,
            'totalCount' => $this->baseQuery()->count(),
        ]);
    }

    public function show(Request $request, Announcement $announcement)
    {
        if ($redirect = $this->guard($request)) {
            return $redirect;
        }

        // Reuse the same visibility rule as the listing so a guessed id can't
        // surface an unpublished or still-under-review activity.
        $visible = $this->baseQuery()->whereKey($announcement->getKey())->exists();
        abort_unless($visible, 404);

        $announcement->load('creator', 'project', 'attachments');

        return view('activities.show', [
            'announcement' => $announcement,
            'approvedVolunteers' => $announcement->approvedCount(),
        ]);
    }
}
