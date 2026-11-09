<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Public directory of registered project initiators.
 *
 * An organization's About Us profile is written in OrgProfileController; this
 * is the read-only side every other role sees. Its job is discovery — before
 * this existed, a profile could be filled in and still be unreachable.
 */
class OrganizationDirectoryController extends Controller
{
    /** Roles that own an organization profile. */
    private const ORG_ROLES = ['student_org', 'college_department'];

    /**
     * Deactivated accounts are excluded: the directory is a public-facing list,
     * and a suspended org should not keep advertising itself.
     */
    private function baseQuery()
    {
        return User::query()
            ->whereIn('role', self::ORG_ROLES)
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            });
    }

    public function index(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        $advocacy = $request->query('advocacy');
        $filters = $request->validate([
            'classification' => ['nullable', Rule::in(array_keys(User::ORG_CLASSIFICATIONS))],
        ]);
        $classification = $filters['classification'] ?? null;

        $organizations = $this->baseQuery()
            ->withCount(['officers'])
            ->when($term !== '', function ($q) use ($term) {
                // LOWER() on both sides — Postgres LIKE is case-sensitive.
                $needle = '%' . mb_strtolower($term) . '%';
                $q->where(function ($inner) use ($needle) {
                    $inner->whereRaw('LOWER(COALESCE(organization_name, name)) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(org_acronym, \'\')) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(org_college, \'\')) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(org_description, \'\')) LIKE ?', [$needle]);
                });
            })
            ->when($advocacy && array_key_exists($advocacy, User::ADVOCACY_AREAS),
                // org_advocacies is a JSON array of keys.
                fn ($q) => $q->whereJsonContains('org_advocacies', $advocacy))
            ->when($classification, fn ($q) => $q
                ->where('role', 'student_org')
                ->where('org_classification', $classification))
            ->orderByRaw('COALESCE(organization_name, name)')
            ->paginate(12)
            ->withQueryString();

        // Activity counts in one query rather than per card. The aggregate needs
        // an alias — pluck() reads it as a property name, and "count(*)" isn't one.
        $activityCounts = Announcement::query()
            ->whereNotNull('project_id')
            ->where('status', 'published')
            ->whereHas('project', fn ($q) => $q->whereIn('status', ['approved', 'csa_cleared']))
            ->selectRaw('created_by, count(*) as total')
            ->groupBy('created_by')
            ->pluck('total', 'created_by');

        // Only offer advocacy filters that at least one organization claims.
        $claimed = $this->baseQuery()->pluck('org_advocacies')->flatten()->filter()->unique();
        $advocacyOptions = collect(User::ADVOCACY_AREAS)
            ->filter(fn ($label, $key) => $claimed->contains($key));

        return view('organizations.index', [
            'organizations' => $organizations,
            'activityCounts' => $activityCounts,
            'advocacyOptions' => $advocacyOptions,
            'advocacy' => $advocacy,
            'classification' => $classification,
            'term' => $term,
            'totalCount' => $this->baseQuery()->count(),
        ]);
    }

    public function show(Request $request, User $organization)
    {
        // Same visibility rule as the listing, so a guessed id can't surface a
        // deactivated org or a non-initiator account.
        $visible = $this->baseQuery()->whereKey($organization->getKey())->exists();
        abort_unless($visible, 404);

        $organization->load('officers');

        $activities = Announcement::query()
            ->where('created_by', $organization->id)
            ->whereNotNull('project_id')
            ->where('status', 'published')
            ->whereHas('project', fn ($q) => $q->whereIn('status', ['approved', 'csa_cleared']))
            ->with('project')
            ->orderByRaw('COALESCE(event_date, published_at, created_at) DESC')
            ->take(8)
            ->get();

        // Head count actually recorded on the ground, keyed by project.
        $attended = DB::table('attendance_records')
            ->join('attendance_sessions', 'attendance_records.session_id', '=', 'attendance_sessions.id')
            ->join('projects', 'attendance_sessions.project_id', '=', 'projects.id')
            ->where('projects.created_by', $organization->id)
            ->whereIn('attendance_records.status', ['valid', 'present', 'attended', 'completed'])
            ->groupBy('attendance_sessions.project_id')
            ->selectRaw('attendance_sessions.project_id, count(distinct attendance_records.user_id) as total')
            ->pluck('total', 'project_id');

        return view('organizations.show', [
            'org' => $organization,
            'activities' => $activities,
            'attended' => $attended,
            'projectCount' => Project::where('created_by', $organization->id)
                ->whereIn('status', ['approved', 'csa_cleared'])->count(),
            // An org viewing its own profile gets a shortcut back to editing.
            'isOwner' => $request->user()->id === $organization->id,
        ]);
    }
}
