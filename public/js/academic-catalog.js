document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-academic-catalog]');
    if (!root) return;
    const data = JSON.parse(root.querySelector('[data-catalog-data]').textContent);
    const find = selector => root.querySelector(selector);
    const form = find('[data-catalog-form]');
    const field = name => form.elements.namedItem(name);
    const tabs = Array.from(root.querySelectorAll('[data-catalog-tab]'));
    const search = find('[data-catalog-search]');
    const availability = find('[data-catalog-availability]');
    const sort = find('[data-catalog-sort]');
    const state = { kind: 'departments', page: 1, editing: null };
    const pageSize = 8;
    const normalize = value => String(value ?? '').trim().toLocaleLowerCase();
    const isAvailable = entry => Boolean(entry.is_active) && (state.kind === 'departments' || Boolean(entry.department_active));
    const isProgram = () => state.kind === 'programs';

    function updateSelection() {
        root.querySelectorAll('[data-catalog-row]').forEach(row => {
            const selected = row.dataset.kind === state.kind && row.dataset.id === String(state.editing?.id);
            row.classList.toggle('is-selected', selected);
            row.querySelector('[data-catalog-edit]').setAttribute('aria-pressed', String(selected));
        });
    }

    function renderRows() {
        const panel = find(`[data-catalog-panel="${state.kind}"]`);
        const query = normalize(search.value);
        const entries = data[state.kind].filter(entry => {
            const text = [entry.code, entry.name, entry.department, entry.department_name].map(normalize).join(' ');
            return text.includes(query) && (availability.value === 'all' || isAvailable(entry) === (availability.value === 'active'));
        }).sort((a, b) => {
            if (sort.value === 'newest') return (Date.parse(b.created_at) || 0) - (Date.parse(a.created_at) || 0) || b.id - a.id;
            const key = sort.value === 'name' ? 'name' : 'code';
            return a[key].localeCompare(b[key], undefined, { numeric: true, sensitivity: 'base' }) || a.id - b.id;
        });
        const pageCount = Math.max(1, Math.ceil(entries.length / pageSize));
        state.page = Math.min(state.page, pageCount);
        const start = (state.page - 1) * pageSize;
        const visible = new Set(entries.slice(start, start + pageSize).map(entry => String(entry.id)));
        const rows = new Map(Array.from(panel.querySelectorAll('[data-catalog-row]')).map(row => [row.dataset.id, row]));
        rows.forEach(row => { row.hidden = !visible.has(row.dataset.id); });
        const tbody = panel.querySelector('tbody');
        entries.forEach(entry => tbody.append(rows.get(String(entry.id))));
        const empty = panel.querySelector('[data-catalog-empty]');
        empty.hidden = entries.length > 0;
        const hasEntries = data[state.kind].length > 0;
        empty.querySelector('[data-catalog-empty-title]').textContent = hasEntries ? 'No matching entries' : (isProgram() ? 'No programs yet' : 'No colleges or departments yet');
        empty.querySelector('[data-catalog-empty-note]').textContent = hasEntries
            ? 'Try another search or change the availability filter.'
            : (isProgram() ? 'Add an official program and link it to its college or department.' : 'Add a college or department to start building your program list.');
        empty.querySelector('[data-catalog-clear]').hidden = !hasEntries;
        tbody.append(empty);
        const noun = isProgram() ? 'programs' : 'colleges / departments';
        find('[data-catalog-range]').textContent = `Showing ${entries.length ? start + 1 : 0}–${Math.min(start + pageSize, entries.length)} of ${entries.length} ${noun}`;
        find('[data-catalog-page]').textContent = String(state.page);
        find('[data-catalog-page]').setAttribute('aria-label', `Page ${state.page} of ${pageCount}`);
        find('[data-catalog-prev]').disabled = state.page <= 1;
        find('[data-catalog-next]').disabled = state.page >= pageCount;
        updateSelection();
    }

    function updateAvailabilityNote() {
        const department = data.departments.find(entry => String(entry.id) === field('department_id').value);
        const collegeInactive = isProgram() && department && !department.is_active;
        find('[data-catalog-availability-note]').textContent = collegeInactive
            ? 'This college / department is inactive. Its programs cannot be assigned to new participants until the college / department is active again.'
            : field('is_active').value === '0'
                ? 'Inactive entries stay on existing participant records. They are unavailable for new assignments.'
                : 'Active entries can be selected when assigning participant details. Inactive entries stay on existing records.';
        if (isProgram() && !state.editing) {
            find('[data-catalog-department-help]').textContent = department?.name
                || (data.departments.some(entry => entry.is_active) ? 'Choose the college or department offering this program.' : 'Add an active college / department before adding a program.');
        }
    }

    function setEditor(entry = null, { focus = false, recovered = null } = {}) {
        state.editing = entry;
        const program = isProgram();
        const editing = Boolean(entry);
        const noun = program ? 'program' : 'college / department';
        form.action = editing ? data.updateUrls[state.kind][entry.id] : data.storeUrls[state.kind];
        field('_method').disabled = !editing;
        field('_catalog_kind').value = state.kind;
        field('_catalog_id').value = entry?.id ?? '';
        field('code').value = entry?.code ?? recovered?.code ?? '';
        field('code').readOnly = editing;
        field('code').placeholder = program ? 'e.g. BSIT' : 'e.g. COECS';
        field('name').value = recovered?.name ?? entry?.name ?? '';
        field('name').placeholder = program ? 'e.g. Bachelor of Science in Information Technology' : 'e.g. College of Engineering and Computer Studies';
        field('is_active').value = recovered?.is_active ?? (entry ? (entry.is_active ? '1' : '0') : '1');
        const department = field('department_id');
        Array.from(department.options).forEach(option => {
            option.disabled = Boolean(option.value) && option.dataset.active !== '1' && (!editing || option.value !== String(entry.department_id));
        });
        department.value = String(entry?.department_id ?? recovered?.department_id ?? '');
        department.disabled = !program || editing;
        department.required = program && !editing;
        find('[data-catalog-department-field]').hidden = !program;
        find('[data-catalog-department-help]').textContent = editing ? 'The college assignment is fixed to keep existing participant records consistent.' : 'Choose the college or department offering this program.';
        find('[data-catalog-editor-mode]').textContent = editing ? `EDITING ${entry.code}` : 'NEW ENTRY';
        find('[data-catalog-editor-title]').textContent = `${editing ? 'Edit' : 'Add'} ${noun}`;
        find('[data-catalog-editor-description]').textContent = editing ? 'Update the full name or availability, then save your changes.' : 'Add the short code and the full official name.';
        find('[data-catalog-code-label]').textContent = program ? 'Program code' : 'College code';
        find('[data-catalog-name-label]').textContent = program ? 'Program name' : 'College name';
        find('[data-catalog-code-lock]').hidden = !editing;
        find('[data-catalog-code-help]').textContent = editing ? 'The code is fixed to keep existing participant records consistent.' : `Use a short, recognizable code, such as ${program ? 'BSIT' : 'COECS'}.`;
        find('[data-catalog-cancel]').textContent = editing ? 'Cancel' : 'Clear form';
        find('[data-catalog-save]').textContent = editing ? 'Save changes' : `Add ${noun}`;
        find('[data-catalog-save]').disabled = program && !editing && !data.departments.some(item => item.is_active);
        // Once the user selects another entry, previous validation messages no longer apply to its fields.
        form.querySelectorAll('.ac-field-error').forEach(error => {
            error.hidden = !recovered;
            if (recovered) error.closest('.ac-field').querySelector('input, select, textarea')?.setAttribute('aria-invalid', 'true');
        });
        form.querySelectorAll('[aria-invalid]').forEach(input => {
            if (!recovered) input.removeAttribute('aria-invalid');
        });
        updateAvailabilityNote();
        updateSelection();
        if (focus) {
            const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
            find('#ac-editor').scrollIntoView({ behavior: reducedMotion ? 'instant' : 'smooth', block: 'nearest' });
            field(editing ? 'name' : 'code').focus({ preventScroll: true });
        }
    }

    function setTab(kind, { focus = false } = {}) {
        state.kind = kind === 'programs' ? 'programs' : 'departments';
        state.page = 1;
        search.value = '';
        availability.value = 'all';
        tabs.forEach(tab => {
            const selected = tab.dataset.catalogTab === state.kind;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
            if (selected && focus) tab.focus();
        });
        root.querySelectorAll('[data-catalog-panel]').forEach(panel => { panel.hidden = panel.dataset.catalogPanel !== state.kind; });
        find('[data-catalog-add-label]').textContent = isProgram() ? 'Add program' : 'Add college / department';
        setEditor();
        renderRows();
    }

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => setTab(tab.dataset.catalogTab));
        tab.addEventListener('keydown', event => {
            const offset = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
            if (!offset && !['Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + offset + tabs.length) % tabs.length;
            setTab(tabs[next].dataset.catalogTab, { focus: true });
        });
    });
    root.querySelectorAll('[data-catalog-edit]').forEach(button => button.addEventListener('click', () => {
        const entry = data[state.kind].find(item => String(item.id) === button.dataset.id);
        if (entry) setEditor(entry, { focus: true });
    }));
    find('[data-catalog-add]').addEventListener('click', () => setEditor(null, { focus: true }));
    find('[data-catalog-cancel]').addEventListener('click', () => setEditor(null, { focus: true }));
    [search, availability, sort].forEach(control => control.addEventListener(control === search ? 'input' : 'change', () => { state.page = 1; renderRows(); }));
    root.querySelectorAll('[data-catalog-clear]').forEach(button => button.addEventListener('click', () => { search.value = ''; availability.value = 'all'; state.page = 1; renderRows(); search.focus(); }));
    find('[data-catalog-prev]').addEventListener('click', () => { state.page -= 1; renderRows(); });
    find('[data-catalog-next]').addEventListener('click', () => { state.page += 1; renderRows(); });
    field('is_active').addEventListener('change', updateAvailabilityNote);
    field('department_id').addEventListener('change', updateAvailabilityNote);
    field('code').addEventListener('blur', () => { field('code').value = field('code').value.trim().toLocaleUpperCase(); });
    form.addEventListener('submit', () => {
        find('[data-catalog-save]').disabled = true;
        find('[data-catalog-save]').textContent = 'Saving...';
    });

    setTab(data.oldInput?.kind ?? data.initialTab);
    if (data.oldInput) {
        const entry = data[state.kind].find(item => String(item.id) === data.oldInput.id);
        setEditor(entry ?? null, { recovered: data.oldInput });
    }
});
