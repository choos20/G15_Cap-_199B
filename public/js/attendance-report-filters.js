document.addEventListener('DOMContentLoaded', () => {
    const department = document.querySelector('[data-attendance-department]');
    const program = document.querySelector('[data-attendance-program]');
    if (!department || !program) return;
    const key = value => value.trim().replace(/\s+/g, ' ').toLocaleLowerCase();
    const update = (reset = false) => {
        Array.from(program.options).forEach(option => {
            const departments = JSON.parse(option.dataset.departments || '[]');
            const selectedDepartment = department.value === '_unspecified' ? '' : department.value;
            const available = !department.value || !option.value || option.value === '_unspecified'
                || departments.some(value => key(value) === key(selectedDepartment))
                || (!reset && option.selected);
            option.hidden = !available;
            option.disabled = !available;
        });
        if (reset && program.selectedOptions[0]?.disabled) program.value = '';
    };
    department.addEventListener('change', () => update(true));
    update();
});
