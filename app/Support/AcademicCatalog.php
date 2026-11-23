<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AcademicCatalog
{
    public const PROFILE_FIELDS = ['participant_type', 'affiliation', 'program', 'employment_type', 'year_graduated'];

    public static function canManage(?User $user): bool
    {
        return in_array($user?->role, ['celp_head', 'coordinator', 'celp_secretary'], true);
    }

    public function departments(): Collection
    {
        return Schema::hasTable('academic_departments')
            ? DB::table('academic_departments')->orderBy('code')->get()
            : collect();
    }

    public function programs(): Collection
    {
        return Schema::hasTable('academic_programs')
            ? DB::table('academic_programs as programs')
                ->join('academic_departments as departments', 'departments.id', '=', 'programs.department_id')
                ->select(['programs.*', 'departments.code as department', 'departments.name as department_name', 'departments.is_active as department_active'])
                ->orderBy('programs.code')->get()
            : collect();
    }

    public static function key(?string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $value)));
    }

    public static function canonical(?string $value, Collection $options): string
    {
        $key = self::key($value);
        $match = $options->first(fn ($option) => self::key($option->code) === $key || self::key($option->name) === $key);

        return $match?->code ?? trim((string) $value);
    }

    public function choices(?User $user = null): array
    {
        $allDepartments = $this->departments();
        $allPrograms = $this->programs();
        $departments = $allDepartments->where('is_active', true)->values();
        $programs = $allPrograms->where('is_active', true)->where('department_active', true)->values();
        $currentDepartment = self::canonical($user?->affiliation, $allDepartments);
        $currentProgram = self::canonical($user?->program, $allPrograms);

        // Existing values remain selectable while staff move older profiles to
        // the catalog. Omitting academic fields from an ID-only edit preserves them.
        if (filled($currentDepartment) && ! $departments->contains(fn ($row) => $row->code === $currentDepartment)) {
            $departments->push((object) ['code' => $currentDepartment, 'name' => $user->affiliation]);
        }
        if (filled($currentProgram) && ! $programs->contains(fn ($row) => $row->code === $currentProgram)) {
            $programs->push((object) ['code' => $currentProgram, 'name' => $user->program, 'department' => $currentDepartment]);
        }

        return ['departments' => $departments, 'programs' => $programs];
    }

    public function normalize(array $data, ?User $user = null): array
    {
        $choices = $this->choices($user);
        foreach (['affiliation' => 'departments', 'program' => 'programs'] as $field => $options) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = self::canonical($data[$field], $choices[$options]);
            }
        }

        return $data;
    }

    public function rules(array $data, ?User $user = null): array
    {
        $choices = $this->choices($user);
        $department = is_string($data['affiliation'] ?? null) ? $data['affiliation'] : null;
        $programs = $choices['programs']->filter(fn ($program) =>
            self::key($program->department) === self::key($department));

        return [
            'participant_type' => ['required', \Illuminate\Validation\Rule::in(array_keys(User::PARTICIPANT_TYPES))],
            'affiliation' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::in($choices['departments']->pluck('code')->all())],
            'program' => ['exclude_unless:participant_type,student,alumni', 'required', 'string', 'max:160', \Illuminate\Validation\Rule::in($programs->pluck('code')->all())],
            'employment_type' => ['exclude_unless:participant_type,employee', 'required', \Illuminate\Validation\Rule::in(array_keys(User::EMPLOYMENT_TYPES))],
            'year_graduated' => ['exclude_unless:participant_type,alumni', 'required', 'integer', 'min:1900', 'max:'.now()->year],
        ];
    }

    public static function profile(array $data): array
    {
        $type = $data['participant_type'];

        return [
            'participant_type' => $type,
            'affiliation' => $data['affiliation'],
            'program' => in_array($type, ['student', 'alumni'], true) ? ($data['program'] ?? null) : null,
            'employment_type' => $type === 'employee' ? ($data['employment_type'] ?? null) : null,
            'year_graduated' => $type === 'alumni' ? ($data['year_graduated'] ?? null) : null,
        ];
    }
}
