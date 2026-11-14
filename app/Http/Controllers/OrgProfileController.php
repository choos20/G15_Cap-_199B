<?php

namespace App\Http\Controllers;

use App\Models\OrgOfficer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * "About Us" profile owned by a project initiator (student org, college or
 * department). This is the editing side; participants and staff get a
 * read-only view of the same data elsewhere.
 */
class OrgProfileController extends Controller
{
    private function org(Request $request): User
    {
        if (! in_array($request->user()->role, ['student_org', 'college_department'], true)) {
            abort(403, 'Only project initiators have an organization profile.');
        }

        return $request->user();
    }

    private function officerRules(User $org, bool $preventDuplicatePresident = true): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'position' => [
                'required',
                'string',
                'max:80',
                function (string $attribute, mixed $value, \Closure $fail) use ($org, $preventDuplicatePresident): void {
                    if ($preventDuplicatePresident && $org->role === 'student_org'
                        && is_string($value) && strcasecmp(trim($value), 'President') === 0) {
                        $fail('The president is listed automatically from your account. Add other officer positions here.');
                    }
                },
            ],
            'email' => ['nullable', 'email', 'max:150'],
        ];
    }

    public function edit(Request $request)
    {
        $org = $this->org($request);
        $org->load('officers');

        return view('STUDENT_ORGANIZATION_PRESIDENT.about', [
            'org' => $org,
            'completeness' => $org->profileCompleteness(),
            'tab' => in_array($request->query('tab'), ['profile', 'officers', 'contact'], true)
                ? $request->query('tab')
                : 'profile',
        ]);
    }

    public function update(Request $request)
    {
        $org = $this->org($request);

        $validated = $request->validate([
            'org_acronym' => ['nullable', 'string', 'max:32'],
            'org_college' => ['nullable', 'string', 'max:150'],
            // Founding year can't precede the university or sit in the future.
            'org_year_founded' => ['nullable', 'integer', 'min:1900', 'max:' . now()->year],
            'org_description' => ['nullable', 'string', 'max:200'],
            'org_vision' => ['nullable', 'string', 'max:500'],
            'org_mission' => ['nullable', 'string', 'max:500'],
            'org_goals' => ['nullable', 'string', 'max:1000'],
            'org_advocacies' => ['nullable', 'array'],
            'org_advocacies.*' => [Rule::in(array_keys(User::ADVOCACY_AREAS))],
            'org_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'org_advocacies.*.in' => 'That advocacy area is not one of the available options.',
            'org_description.max' => 'Keep the short description to 200 characters.',
            'org_year_founded.max' => 'The founding year cannot be in the future.',
        ]);

        if ($request->hasFile('org_logo')) {
            $this->deleteLogo($org);
            $org->org_logo = null;
            $org->org_logo_data = $request->file('org_logo')->get();
            $org->org_logo_mime = $request->file('org_logo')->getMimeType();
        }

        $org->fill([
            'org_acronym' => $validated['org_acronym'] ?? null,
            'org_college' => $validated['org_college'] ?? null,
            'org_year_founded' => $validated['org_year_founded'] ?? null,
            'org_description' => $validated['org_description'] ?? null,
            'org_vision' => $validated['org_vision'] ?? null,
            'org_mission' => $validated['org_mission'] ?? null,
            'org_goals' => $validated['org_goals'] ?? null,
            // Unchecking every box submits no key at all, which must clear the list.
            'org_advocacies' => $validated['org_advocacies'] ?? [],
            'org_profile_updated_at' => now(),
        ]);
        $org->save();

        return redirect()
            ->route('org.about.edit')
            ->with('status', 'Organization profile updated.');
    }

    public function updateContact(Request $request)
    {
        $org = $this->org($request);

        $validated = $request->validate([
            'org_contact_email' => ['nullable', 'email', 'max:150'],
            'org_facebook' => ['nullable', 'string', 'max:150'],
        ], [
            'org_contact_email.email' => 'Enter a valid contact email address.',
        ]);

        $org->fill($validated + ['org_profile_updated_at' => now()])->save();

        return redirect()
            ->route('org.about.edit', ['tab' => 'contact'])
            ->with('status', 'Contact details updated.');
    }

    public function removeLogo(Request $request)
    {
        $org = $this->org($request);
        $this->deleteLogo($org);

        $org->org_logo = null;
        $org->org_logo_data = null;
        $org->org_logo_mime = null;
        $org->org_profile_updated_at = now();
        $org->save();

        return redirect()->route('org.about.edit')->with('status', 'Logo removed.');
    }

    // ------------------------------------------------------------- officers

    public function storeOfficer(Request $request)
    {
        $org = $this->org($request);

        $validated = $request->validate($this->officerRules($org));

        $org->officers()->create($validated + [
            // New officers land at the bottom of the roster.
            'sort_order' => (int) $org->officers()->max('sort_order') + 1,
        ]);

        $org->update(['org_profile_updated_at' => now()]);

        return redirect()
            ->route('org.about.edit', ['tab' => 'officers'])
            ->with('status', 'Officer added.');
    }

    public function updateOfficer(Request $request, OrgOfficer $officer)
    {
        $org = $this->org($request);
        abort_unless($officer->user_id === $org->id, 403);

        $validated = $request->validate($this->officerRules($org, false));

        $officer->update($validated);
        $org->update(['org_profile_updated_at' => now()]);

        return redirect()
            ->route('org.about.edit', ['tab' => 'officers'])
            ->with('status', 'Officer updated.');
    }

    public function updateOfficerAvatar(Request $request, OrgOfficer $officer)
    {
        $org = $this->org($request);
        abort_unless($org->role === 'student_org', 403, 'Only student organization presidents may manage co-officer photos.');
        abort_unless($officer->user_id === $org->id, 403);

        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:min_width=256,min_height=256', 'max:2048'],
        ]);

        $newPath = $validated['avatar']->store('org_officer_avatars', 'public');
        if (! is_string($newPath) || $newPath === '') {
            throw new \RuntimeException('The officer photo could not be stored.');
        }

        $oldPath = $officer->avatar_path;
        $officer->avatar_path = $newPath;
        $officer->save();
        $this->deleteOfficerAvatar($oldPath);

        $org->update(['org_profile_updated_at' => now()]);

        return redirect()
            ->route('org.about.edit', ['tab' => 'officers'])
            ->with('status', 'Officer photo updated.');
    }

    public function removeOfficerAvatar(Request $request, OrgOfficer $officer)
    {
        $org = $this->org($request);
        abort_unless($org->role === 'student_org', 403, 'Only student organization presidents may manage co-officer photos.');
        abort_unless($officer->user_id === $org->id, 403);

        $oldPath = $officer->avatar_path;
        $officer->avatar_path = null;
        $officer->save();
        $this->deleteOfficerAvatar($oldPath);
        $org->update(['org_profile_updated_at' => now()]);

        return redirect()
            ->route('org.about.edit', ['tab' => 'officers'])
            ->with('status', 'Officer photo removed.');
    }

    public function destroyOfficer(Request $request, OrgOfficer $officer)
    {
        $org = $this->org($request);
        abort_unless($officer->user_id === $org->id, 403);

        $avatarPath = $officer->avatar_path;
        $officer->delete();
        $this->deleteOfficerAvatar($avatarPath);
        $org->update(['org_profile_updated_at' => now()]);

        return redirect()
            ->route('org.about.edit', ['tab' => 'officers'])
            ->with('status', 'Officer removed.');
    }

    private function deleteOfficerAvatar(?string $path): void
    {
        if ($path && str_starts_with($path, 'org_officer_avatars/')) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Move an officer up or down the roster by swapping sort_order with its
     * neighbour, so ordering survives without a drag-and-drop dependency.
     */
    public function moveOfficer(Request $request, OrgOfficer $officer)
    {
        $org = $this->org($request);
        abort_unless($officer->user_id === $org->id, 403);

        $direction = $request->input('direction') === 'up' ? 'up' : 'down';
        $ordered = $org->officers()->get();
        $index = $ordered->search(fn ($o) => $o->id === $officer->id);
        $swapWith = $direction === 'up' ? $ordered->get($index - 1) : $ordered->get($index + 1);

        if ($swapWith && $index !== false) {
            // Positions may collide (all default 0), so renumber from the list
            // order rather than trusting the stored values.
            foreach ($ordered as $i => $o) {
                $o->update(['sort_order' => $i]);
            }
            $officer->refresh();
            $swapWith->refresh();

            $a = $officer->sort_order;
            $officer->update(['sort_order' => $swapWith->sort_order]);
            $swapWith->update(['sort_order' => $a]);
        }

        return redirect()->route('org.about.edit', ['tab' => 'officers']);
    }

    private function deleteLogo(User $org): void
    {
        if ($org->org_logo
            && ! preg_match('#^https?://#i', $org->org_logo)
            && Storage::disk('public')->exists($org->org_logo)) {
            Storage::disk('public')->delete($org->org_logo);
        }
    }
}
