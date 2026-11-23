document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-participant-academics]').forEach(form => {
        const department = form.querySelector('[name="affiliation"]');
        const program = form.querySelector('[name="program"]');
        if (!department || !program) return;
        const programHelp = form.querySelector('[data-program-help]');

        const key = value => value.trim().replace(/\s+/g, ' ').toLocaleLowerCase();
        const types = {
            student: { id: 'Student ID No.', department: 'College / Department', program: 'Program' },
            employee: { id: 'Employee ID No.', department: 'College / Department or Office' },
            alumni: { id: 'Alumni ID No.', department: 'College / Department', program: 'Program' },
        };
        const update = (resetProgram = false) => {
            const active = form.dataset.participantAccount === '1'
                || form.querySelector('[name="role"]:checked')?.value === 'participant';
            const type = form.querySelector('select[name="participant_type"]')?.value
                || form.querySelector('[name="participant_type"]:checked')?.value;
            const spec = types[type];
            department.required = active && Boolean(spec);
            department.disabled = !active;

            Array.from(program.options).forEach(option => {
                const available = !option.value || key(option.dataset.department || '') === key(department.value);
                option.hidden = !available;
                option.disabled = !available;
            });
            if (resetProgram && program.selectedOptions[0]?.disabled) program.value = '';

            [department, program].forEach(select => {
                const summary = form.querySelector(`[data-academic-selection="${select.name}"]`);
                if (summary) summary.textContent = select.value
                    ? select.selectedOptions[0]?.dataset.academicName || select.value
                    : 'Select an option above to see its full name.';
            });

            const hasPrograms = Array.from(program.options).some(option => option.value && !option.disabled);
            program.disabled = !active || !spec?.program || !department.value || !hasPrograms;
            program.required = !program.disabled;
            let placeholder = 'Choose program';
            let help = type === 'alumni'
                ? 'Select the program this participant graduated from.'
                : 'Select the participant\'s program. CELP staff can update it when needed.';
            if (!spec) {
                placeholder = 'Choose participant type first';
                help = 'Choose Student or Alumni and a college / department to select a program.';
            } else if (!department.value) {
                placeholder = 'Choose college / department first';
                help = 'Choose a college / department to see its programs.';
            } else if (!hasPrograms) {
                placeholder = 'No programs available';
                help = 'No programs are listed for this college / department. Add a program using Manage colleges & programs, then reload this page.';
            }
            program.options[0].textContent = placeholder;
            if (programHelp) programHelp.textContent = help;

            const fields = { 'program-field': type !== 'employee', 'employment-field': type === 'employee', 'year-field': type === 'alumni' };
            Object.entries(fields).forEach(([id, visible]) => {
                const field = form.querySelector(`#${id}`);
                if (!field) return;
                field.style.display = visible ? 'block' : 'none';
                if (id !== 'program-field') field.querySelectorAll('input,select').forEach(input => {
                    input.disabled = !active || !visible;
                    input.required = active && visible;
                });
            });
            if (spec) {
                const labels = { id_number_label: spec.id, affiliation_label: spec.department, program_label: spec.program };
                Object.entries(labels).forEach(([id, text]) => {
                    const label = form.querySelector(`#${id}`);
                    if (label && text) label.textContent = text;
                });
            }
        };
        department.addEventListener('change', () => update(true));
        program.addEventListener('change', () => update());
        form.querySelectorAll('[name="participant_type"], [name="role"]').forEach(input => input.addEventListener('change', () => update()));
        update();
    });
});
