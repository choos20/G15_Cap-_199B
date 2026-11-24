<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\AcademicCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AcademicCatalogController extends Controller
{
    private function guard(Request $request): void
    {
        abort_unless(AcademicCatalog::canManage($request->user()), 403);
    }

    public function index(Request $request, AcademicCatalog $catalog)
    {
        $this->guard($request);

        return view('users.academic_catalog', [
            'departments' => $catalog->departments(),
            'programs' => $catalog->programs(),
        ]);
    }

    public function store(Request $request, string $kind)
    {
        $this->guard($request);
        abort_unless(in_array($kind, ['departments', 'programs'], true), 404);
        $table = 'academic_'.$kind;
        $request->merge([
            'code' => is_string($request->input('code')) ? mb_strtoupper(trim($request->input('code'))) : $request->input('code'),
            'name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name'),
        ]);
        $rules = [
            'code' => ['bail', 'required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9_&.\/ -]*$/', Rule::unique($table), function ($attribute, $value, $fail) use ($table) {
                if (DB::table($table)->whereRaw('LOWER(code) = ? OR LOWER(name) = ?', [mb_strtolower($value), mb_strtolower($value)])->exists()) {
                    $fail('That code is already in the list.');
                }
            }],
            'name' => ['bail', 'required', 'string', 'max:255', function ($attribute, $value, $fail) use ($table) {
                if (DB::table($table)->whereRaw('LOWER(name) = ? OR LOWER(code) = ?', [mb_strtolower($value), mb_strtolower($value)])->exists()) {
                    $fail('That name is already in the list. Use its existing code.');
                }
            }],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
        if ($kind === 'programs') {
            $rules['department_id'] = ['required', 'integer', Rule::exists('academic_departments', 'id')->where('is_active', true)];
        }
        $data = $request->validate($rules, [], [
            'code' => $kind === 'programs' ? 'program code' : 'college code',
            'name' => $kind === 'programs' ? 'program name' : 'college name',
            'department_id' => 'college / department', 'is_active' => 'availability',
        ]);
        $id = DB::table($table)->insertGetId($data + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        AuditLog::record('academic_catalog.created', 'Added '.$data['code'].' to the academic '.$kind.' list.', null, ['kind' => $kind, 'id' => $id]);

        return redirect()->route('academic-catalog.index', ['tab' => $kind])
            ->with('status', ($kind === 'programs' ? 'Program ' : 'College / department ').$data['code'].' added successfully.');
    }

    public function update(Request $request, string $kind, int $id)
    {
        $this->guard($request);
        abort_unless(in_array($kind, ['departments', 'programs'], true), 404);
        $table = 'academic_'.$kind;
        $entry = DB::table($table)->find($id);
        abort_unless($entry, 404);
        $request->merge(['name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name')]);
        $data = $request->validate([
            'name' => ['bail', 'required', 'string', 'max:255', function ($attribute, $value, $fail) use ($table, $id) {
                if (DB::table($table)->where('id', '!=', $id)->where(function ($query) use ($value) {
                    $query->whereRaw('LOWER(name) = ? OR LOWER(code) = ?', [mb_strtolower($value), mb_strtolower($value)]);
                })->exists()) {
                    $fail('That name is already in the list.');
                }
            }],
            'is_active' => ['required', 'boolean'],
        ], [], ['name' => $kind === 'programs' ? 'program name' : 'college name', 'is_active' => 'availability']);
        // Codes and program ownership stay stable because profiles store codes.
        DB::table($table)->where('id', $id)->update($data + ['updated_at' => now()]);
        AuditLog::record('academic_catalog.updated', 'Updated '.$entry->code.' in the academic '.$kind.' list.', null, ['kind' => $kind, 'id' => $id]);

        return redirect()->route('academic-catalog.index', ['tab' => $kind])
            ->with('status', ($kind === 'programs' ? 'Program ' : 'College / department ').$entry->code.' updated successfully.');
    }

    public function destroy(Request $request, string $kind, int $id)
    {
        $this->guard($request);
        abort_unless(in_array($kind, ['departments', 'programs'], true), 404);

        $table = 'academic_'.$kind;
        $entry = DB::table($table)->find($id);
        abort_unless($entry, 404);

        if (! $entry->is_active) {
            return redirect()->route('academic-catalog.index', ['tab' => $kind])
                ->with('status', ($kind === 'programs' ? 'Program ' : 'College / department ').$entry->code.' is already inactive.');
        }

        if ($kind === 'departments') {
            $activePrograms = DB::table('academic_programs')
                ->where('department_id', $id)
                ->where('is_active', true)
                ->count();

            if ($activePrograms > 0) {
                return redirect()->route('academic-catalog.index', ['tab' => $kind])
                    ->withErrors([
                        'catalog' => 'Archive the '.$activePrograms.' active program'.($activePrograms === 1 ? '' : 's').' under '.$entry->code.' before archiving this college / department.',
                    ]);
            }
        }

        DB::table($table)->where('id', $id)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);
        AuditLog::record('academic_catalog.archived', 'Archived '.$entry->code.' from the academic '.$kind.' list.', null, ['kind' => $kind, 'id' => $id]);

        return redirect()->route('academic-catalog.index', ['tab' => $kind])
            ->with('status', ($kind === 'programs' ? 'Program ' : 'College / department ').$entry->code.' archived. Existing participant records were preserved.');
    }
}
