(() => {
    'use strict';
    document.querySelectorAll('[data-session-picker]').forEach(picker => {
        const group=picker.closest('form').querySelector('[name="class_group_id"]');
        const select=picker.querySelector('[data-session-existing]');
        const name=picker.querySelector('[data-session-new]');
        const label=picker.querySelector('[data-session-new-label]');
        const update=() => {
            [...select.options].forEach(option=>{if(option.value){option.hidden=option.dataset.group!==group.value;option.disabled=option.hidden;}});
            if(select.selectedOptions[0]?.disabled)select.value='';
            select.disabled=!group.value;
            name.hidden=!!select.value || !group.value;label.hidden=name.hidden;name.disabled=name.hidden;name.required=!name.hidden;
        };
        group.addEventListener('change',update);select.addEventListener('change',update);update();
    });
    document.querySelectorAll('[data-membership-sessions]').forEach(panel=> {
        const group=panel.closest('form').querySelector('[name="class_group_id"]');
        const enable=panel.querySelector('[data-membership-enable]');
        const fields=panel.querySelector('[data-membership-options]');
        if(!fields)return;
        const update=() => {
            const on=!enable || enable.checked;fields.hidden=!on;fields.disabled=!on;
            panel.querySelectorAll('[data-membership-slot]').forEach(row=>{
                const visible=row.dataset.group===group.value;row.hidden=!visible;
                row.querySelector('input').disabled=!visible || !on;
            });
            panel.querySelectorAll('[data-membership-day]').forEach(day=>{
                day.hidden=![...day.querySelectorAll('[data-membership-slot]')].some(row=>!row.hidden);
            });
            const empty=panel.querySelector('[data-membership-empty]');
            if(empty)empty.hidden=[...panel.querySelectorAll('[data-membership-slot]')].some(row=>!row.hidden);
        };
        group.addEventListener('change',update);enable?.addEventListener('change',update);update();
    });
})();
