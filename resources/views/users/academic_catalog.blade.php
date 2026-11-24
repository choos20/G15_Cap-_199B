@extends('layouts.admin')

@section('title', 'Colleges & programs')
@section('subtitle', 'Manage the academic details used in participant accounts and reports')

@section('content')
    @php
        $initialTab = in_array(request('tab'), ['departments', 'programs'], true) ? request('tab') : 'departments';
        $oldText = fn ($key, $default = '') => is_scalar(old($key, $default)) ? (string) old($key, $default) : '';
        $activeDepartments = $departments->where('is_active', true);
        $activePrograms = $programs->where('is_active', true)->where('department_active', true);
        $catalogData = [
            'departments' => $departments->values(),
            'programs' => $programs->values(),
            'initialTab' => $initialTab,
            'storeUrls' => [
                'departments' => route('academic-catalog.store', 'departments'),
                'programs' => route('academic-catalog.store', 'programs'),
            ],
            'updateUrls' => [
                'departments' => $departments->mapWithKeys(fn ($entry) => [$entry->id => route('academic-catalog.update', ['departments', $entry->id])]),
                'programs' => $programs->mapWithKeys(fn ($entry) => [$entry->id => route('academic-catalog.update', ['programs', $entry->id])]),
            ],
            'oldInput' => $errors->any() ? [
                'kind' => $oldText('_catalog_kind', $initialTab),
                'id' => $oldText('_catalog_id'),
                'code' => $oldText('code'),
                'name' => $oldText('name'),
                'department_id' => $oldText('department_id'),
                'is_active' => $oldText('is_active', '1'),
            ] : null,
        ];
    @endphp
    <link rel="stylesheet" href="{{ asset('css/academic-catalog.css') }}?v={{ filemtime(public_path('css/academic-catalog.css')) }}">

    <div class="ac-page" data-academic-catalog>
        <div class="ac-page-intro">
            <div>
                <x-back-button href="{{ route('users.index') }}">Back to user accounts</x-back-button>
                <p>Keep college and program codes separate from their full names. These choices appear on participant profiles and attendance reports.</p>
            </div>
            <span class="ac-context"><x-ui-icon name="settings" :size="16" /> Academic settings</span>
        </div>

        @if(session('status'))
            <div class="ac-feedback ac-feedback-success" role="status"><x-ui-icon name="check-circle" /> <span>{{ session('status') }}</span></div>
        @endif
        @if($errors->any())
            <div class="ac-feedback ac-feedback-error" role="alert" id="ac-errors">
                <x-ui-icon name="clipboard" />
                <div><strong>Please check the highlighted details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            </div>
        @endif

        <div class="ac-workspace">
            <div class="ac-directory">
                <div class="ac-stats" aria-label="Academic directory summary">
                    <div class="ac-stat ac-stat-colleges">
                        <span class="ac-stat-icon"><x-ui-icon name="building" :size="23" /></span>
                        <div><span class="ac-stat-label">Colleges &amp; departments</span><strong>{{ $departments->count() }}</strong><span class="ac-stat-detail">{{ $activeDepartments->count() }} active</span></div>
                    </div>
                    <div class="ac-stat ac-stat-programs">
                        <span class="ac-stat-icon"><x-ui-icon name="clipboard" :size="23" /></span>
                        <div><span class="ac-stat-label">Programs</span><strong>{{ $programs->count() }}</strong><span class="ac-stat-detail">{{ $activePrograms->count() }} available</span></div>
                    </div>
                    <div class="ac-stat ac-stat-active">
                        <span class="ac-stat-icon"><x-ui-icon name="check-circle" :size="23" /></span>
                        <div><span class="ac-stat-label">Active entries</span><strong>{{ $activeDepartments->count() + $activePrograms->count() }}</strong><span class="ac-stat-detail">Ready for assignment</span></div>
                    </div>
                </div>

                <section class="ac-list-card" aria-label="College and program directory">
                    <div class="ac-list-heading">
                        <div class="ac-tabs" role="tablist" aria-label="Academic lists">
                            <button type="button" role="tab" id="ac-tab-departments" aria-controls="ac-panel-departments" aria-selected="true" data-catalog-tab="departments"><x-ui-icon name="building" :size="17" /><span>Colleges &amp; departments</span><span class="ac-tab-count">{{ $departments->count() }}</span></button>
                            <button type="button" role="tab" id="ac-tab-programs" aria-controls="ac-panel-programs" aria-selected="false" tabindex="-1" data-catalog-tab="programs"><x-ui-icon name="clipboard" :size="17" /><span>Programs</span><span class="ac-tab-count">{{ $programs->count() }}</span></button>
                        </div>
                        <button class="ac-button ac-button-primary" type="button" data-catalog-add><span aria-hidden="true">+</span><span data-catalog-add-label>Add college / department</span></button>
                    </div>

                    <div class="ac-toolbar">
                        <div class="ac-search">
                            <label class="ac-sr-only" for="ac-search">Search by code or name</label>
                            <x-ui-icon name="search" :size="18" />
                            <input id="ac-search" type="search" placeholder="Search by code or name..." autocomplete="off" data-catalog-search>
                        </div>
                        <div class="ac-filter">
                            <label for="ac-availability">Availability</label>
                            <select id="ac-availability" data-catalog-availability><option value="all">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
                        </div>
                        <div class="ac-filter">
                            <label for="ac-sort">Sort by</label>
                            <select id="ac-sort" data-catalog-sort><option value="code">Code: A–Z</option><option value="name">Name: A–Z</option><option value="newest">Newest first</option></select>
                        </div>
                    </div>

                    @foreach(['departments' => $departments, 'programs' => $programs] as $kind => $entries)
                        <div id="ac-panel-{{ $kind }}" role="tabpanel" aria-labelledby="ac-tab-{{ $kind }}" tabindex="0" data-catalog-panel="{{ $kind }}" @if($kind === 'programs') hidden @endif>
                            <div class="ac-table-scroll">
                                <table class="ac-table {{ $kind === 'programs' ? 'ac-table-programs' : '' }}">
                                    <caption class="ac-sr-only">{{ $kind === 'programs' ? 'Programs with their college, full name and availability' : 'Colleges and departments with their full name and availability' }}</caption>
                                    <thead><tr>
                                        <th scope="col" class="ac-col-code">{{ $kind === 'programs' ? 'Program code' : 'College code' }}</th>
                                        <th scope="col">{{ $kind === 'programs' ? 'Program name' : 'College name' }}</th>
                                        @if($kind === 'programs')<th scope="col" class="ac-col-college">College</th>@endif
                                        <th scope="col" class="ac-col-status">Availability</th>
                                        <th scope="col" class="ac-col-action">Action</th>
                                    </tr></thead>
                                    <tbody>
                                        @foreach($entries as $entry)
                                            @php
                                                $available = $entry->is_active && ($kind === 'departments' || $entry->department_active);
                                            @endphp
                                            <tr data-catalog-row data-kind="{{ $kind }}" data-id="{{ $entry->id }}">
                                                <td><span class="ac-code">{{ $entry->code }}</span></td>
                                                <td class="ac-entry-name">{{ $entry->name }}</td>
                                                @if($kind === 'programs')<td><span class="ac-college-code" title="{{ $entry->department_name }}">{{ $entry->department }}</span></td>@endif
                                                <td><span class="ac-badge {{ $available ? 'is-active' : 'is-inactive' }}"><span aria-hidden="true"></span>{{ $available ? 'Active' : 'Inactive' }}</span>@if($kind === 'programs' && $entry->is_active && !$entry->department_active)<small class="ac-parent-inactive">College inactive</small>@endif</td>
                                                <td><button type="button" class="ac-edit-button" data-catalog-edit data-kind="{{ $kind }}" data-id="{{ $entry->id }}" aria-label="Edit {{ $kind === 'programs' ? 'program' : 'college' }} {{ $entry->code }}" aria-pressed="false"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m16 3 5 5-12 12-6 1 1-6L16 3Z M14 5l5 5"/></svg></button></td>
                                            </tr>
                                        @endforeach
                                        <tr data-catalog-empty @if($entries->isNotEmpty()) hidden @endif><td colspan="{{ $kind === 'programs' ? 5 : 4 }}"><div class="ac-empty"><x-ui-icon name="search" :size="29" /><strong data-catalog-empty-title>No entries yet</strong><p data-catalog-empty-note>Add your first entry to start this list.</p><button type="button" class="ac-text-button" data-catalog-clear hidden>Clear filters</button></div></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                    <div class="ac-list-footer">
                        <span data-catalog-range role="status" aria-live="polite"></span>
                        <div class="ac-pagination" aria-label="Directory pagination">
                            <button type="button" data-catalog-prev aria-label="Previous page"><span aria-hidden="true">‹</span></button>
                            <span data-catalog-page aria-label="Current page">1</span>
                            <button type="button" data-catalog-next aria-label="Next page"><span aria-hidden="true">›</span></button>
                        </div>
                    </div>
                </section>
                <p class="ac-directory-note"><x-ui-icon name="check-circle" :size="16" /><span><strong>Keep your records consistent.</strong> Set an entry to Inactive when it is no longer offered. Existing participant records keep their saved details.</span></p>
            </div>

            <section class="ac-editor" aria-labelledby="ac-editor-title" id="ac-editor">
                <div class="ac-editor-heading">
                    <span class="ac-editor-icon"><x-ui-icon name="building" :size="22" /></span>
                    <div><span class="ac-eyebrow" data-catalog-editor-mode>NEW ENTRY</span><h3 id="ac-editor-title" data-catalog-editor-title>Add college / department</h3></div>
                </div>
                <p class="ac-editor-description" data-catalog-editor-description>Add the short code and the full official name.</p>
                <form method="POST" action="{{ route('academic-catalog.store', 'departments') }}" class="ac-form" data-catalog-form>
                    @csrf
                    <input type="hidden" name="_method" value="PUT" disabled>
                    <input type="hidden" name="_catalog_kind" value="departments">
                    <input type="hidden" name="_catalog_id" value="">

                    <div class="ac-field" data-catalog-department-field hidden>
                        <label for="ac-department">College / department <span aria-hidden="true">*</span></label>
                        <select id="ac-department" name="department_id" disabled aria-describedby="ac-department-help">
                            <option value="">Choose college / department</option>
                            @foreach($departments as $department)<option value="{{ $department->id }}" data-active="{{ $department->is_active ? '1' : '0' }}">{{ $department->code }}{{ $department->name !== $department->code ? ' — '.$department->name : '' }}</option>@endforeach
                        </select>
                        <p class="ac-field-help" id="ac-department-help" data-catalog-department-help>Choose the college or department offering this program.</p>
                        @error('department_id')<p class="ac-field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="ac-field">
                        <label for="ac-code"><span data-catalog-code-label>College code</span> <span aria-hidden="true">*</span></label>
                        <div class="ac-input-wrap"><input id="ac-code" name="code" maxlength="40" required placeholder="e.g. COECS" autocomplete="off" spellcheck="false" aria-describedby="ac-code-help" @error('code') aria-invalid="true" @enderror><span data-catalog-code-lock hidden><x-ui-icon name="lock" :size="16" /></span></div>
                        <p class="ac-field-help" id="ac-code-help" data-catalog-code-help>Use a short, recognizable code, such as COECS.</p>
                        @error('code')<p class="ac-field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="ac-field">
                        <label for="ac-name"><span data-catalog-name-label>College name</span> <span aria-hidden="true">*</span></label>
                        <textarea id="ac-name" name="name" rows="3" maxlength="255" required placeholder="e.g. College of Engineering and Computer Studies" @error('name') aria-invalid="true" @enderror></textarea>
                        @error('name')<p class="ac-field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="ac-field">
                        <label for="ac-entry-availability">Availability <span aria-hidden="true">*</span></label>
                        <select id="ac-entry-availability" name="is_active" required><option value="1">Active</option><option value="0">Inactive</option></select>
                        @error('is_active')<p class="ac-field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="ac-availability-note"><x-ui-icon name="check-circle" :size="18" /><p data-catalog-availability-note>Active entries can be selected when assigning participant details. Inactive entries stay on existing records.</p></div>
                    <p class="ac-required-note"><span aria-hidden="true">*</span> Required fields</p>
                    <div class="ac-editor-actions"><button type="button" class="ac-button ac-button-secondary" data-catalog-cancel>Clear form</button><button type="submit" class="ac-button ac-button-primary" data-catalog-save>Add college / department</button></div>
                </form>
            </section>
        </div>
        <script type="application/json" data-catalog-data>@json($catalogData)</script>
    </div>
    <script src="{{ asset('js/academic-catalog.js') }}?v={{ filemtime(public_path('js/academic-catalog.js')) }}" defer></script>
@endsection
