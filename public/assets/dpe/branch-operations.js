(function () {
    'use strict';
    function values(option, name) { return (option.getAttribute(name) || '').split(' ').filter(Boolean); }
    function restrict(select, predicate) {
        if (!select) { return; }
        Array.from(select.options).forEach(function (option) {
            var allowed = !option.value || predicate(option);
            option.hidden = !allowed;
            option.disabled = !allowed;
        });
        var selected = select.options[select.selectedIndex];
        if (selected && selected.disabled) { select.value = ''; }
    }
    function setup(form) {
        var branch = form.querySelector('[name="branch_id"]');
        var program = form.querySelector('[name="program_id"]');
        var teacher = form.querySelector('[name="teacher_id"], [name="default_teacher_id"]');
        var group = form.querySelector('[name="class_group_id"]');
        var student = form.querySelector('[name="student_id"]');
        var source = form.querySelector('[name="rescheduled_from_session_id"]');
        function sync() {
            if (branch) {
                restrict(teacher, function (o) { return !branch.value || values(o, 'data-branches').includes(branch.value); });
                restrict(group, function (o) { return (!branch.value || o.dataset.branch === branch.value) && (!program || !program.value || o.dataset.program === program.value); });
                restrict(source, function (o) {
                    return (!branch.value || o.dataset.branch === branch.value) && (!program || !program.value || o.dataset.program === program.value)
                        && (!group || (o.dataset.group || '') === group.value);
                });
            }
            if (student && group) {
                restrict(student, function (o) { return !group.value || values(o, 'data-class-groups').includes(group.value); });
            }
        }
        if (branch) { branch.addEventListener('change', sync); }
        if (program) { program.addEventListener('change', sync); }
        if (group) {
            group.addEventListener('change', function () {
                var selected = group.options[group.selectedIndex];
                if (branch && program && group.value && selected) {
                    branch.value = selected.dataset.branch || branch.value;
                    program.value = selected.dataset.program || program.value;
                    if (teacher && !teacher.value && selected.dataset.teacher) { teacher.value = selected.dataset.teacher; }
                }
                sync();
            });
        }
        sync();
    }
    function init() { document.querySelectorAll('[data-branch-operations-form]').forEach(setup); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
    if (typeof module !== 'undefined' && module.exports) { module.exports = { restrict: restrict, setup: setup }; }
}());
