(() => {
    'use strict';
    const words = value => (value || '').split(/\s+/).filter(Boolean);
    document.querySelectorAll('[data-student-account]').forEach(panel => {
        const branch=panel.querySelector('[data-account-branch]');
        const teacher=panel.querySelector('[data-account-teacher]');
        const rows=[...panel.querySelectorAll('[data-account-slot]')];
        const update=() => {
            [...teacher.options].forEach(option => {
                if(!option.value) return;
                const visible=words(option.dataset.branches).includes(branch.value);
                option.hidden=!visible; option.disabled=!visible;
            });
            if(teacher.selectedOptions[0]?.disabled) teacher.value='';
            let visibleCount=0;
            rows.forEach(row => {
                const visible=!!branch.value && !!teacher.value && row.dataset.branch===branch.value && words(row.dataset.teachers).includes(teacher.value);
                row.hidden=!visible;
                const box=row.querySelector('input');box.disabled=!visible;
                if(!visible) box.checked=false; else visibleCount++;
            });
            panel.querySelectorAll('[data-account-day]').forEach(day=>{
                day.hidden=![...day.querySelectorAll('[data-account-slot]')].some(row=>!row.hidden);
            });
            panel.querySelector('[data-account-empty]').hidden=visibleCount>0;
        };
        branch.addEventListener('change',update);teacher.addEventListener('change',update);update();
    });
    document.querySelectorAll('[data-role-account]').forEach(form => {
        const role=form.querySelector('[name="role_code"]');
        const student=form.querySelector('[data-student-panel]');
        const general=form.querySelector('[data-general-panel]');
        const update=() => { const isStudent=role.value==='student';student.hidden=!isStudent;student.disabled=!isStudent;general.hidden=isStudent;general.disabled=isStudent; };
        role.addEventListener('change',update);update();
    });
})();
