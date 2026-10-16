(function () {
    const modal = document.getElementById('machine-modal');

    if (!modal) {
        return;
    }

    const dialog = modal.querySelector('.modal-dialog');
    const closeButtons = modal.querySelectorAll('[data-modal-close]');
    const titleEl = document.getElementById('machine-modal-title');
    const imageEl = document.getElementById('machine-modal-image');
    const typeEl = document.getElementById('machine-modal-type');
    const fields = {
        vehicle_id: document.getElementById('machine-modal-vehicle-id'),
        site_name: document.getElementById('machine-modal-site'),
        vehicle_type: document.getElementById('machine-modal-vehicle-type'),
        device_model: document.getElementById('machine-modal-model'),
        status: document.getElementById('machine-modal-status'),
        link: document.getElementById('machine-modal-link'),
        last_log: document.getElementById('machine-modal-last-log'),
        device_uid: document.getElementById('machine-modal-uid'),
        v_computer: document.getElementById('machine-modal-vcomputer'),
        v_daemon: document.getElementById('machine-modal-vdaemon')
    };

    let lastFocus = null;

    function textOrDash(value) {
        const text = String(value == null ? '' : value).trim();
        return text !== '' ? text : '—';
    }

    function fill(machine) {
        const name = textOrDash(machine.name);
        const title = name !== '—' ? name : ('Vehicle ' + textOrDash(machine.vehicle_id));
        const typeLabel = textOrDash(machine.vehicle_type || machine.type);
        const typeDisplay = typeLabel !== '—' ? typeLabel : 'Unknown Type';

        titleEl.textContent = title;
        imageEl.src = machine.image_url || '';
        imageEl.alt = typeDisplay + ' type';
        typeEl.textContent = typeDisplay;

        fields.vehicle_id.textContent = textOrDash(machine.vehicle_id);
        fields.site_name.textContent = textOrDash(machine.site_name);
        fields.vehicle_type.textContent = typeLabel;
        fields.device_model.textContent = textOrDash(machine.device_model);
        fields.status.textContent = textOrDash(machine.status);
        fields.device_uid.textContent = textOrDash(machine.device_uid || machine.uid);
        fields.v_computer.textContent = textOrDash(machine.v_computer);
        fields.v_daemon.textContent = textOrDash(machine.v_daemon);

        const online = Number(machine.connected) === 1;
        fields.link.innerHTML = '';
        const pill = document.createElement('span');
        pill.className = 'pill ' + (online ? 'pill-on' : 'pill-off');
        pill.textContent = machine.connected_label || (online ? 'Online' : 'Offline');
        fields.link.appendChild(pill);

        fields.last_log.innerHTML = '';
        if (!machine.ago) {
            fields.last_log.textContent = '—';
        } else {
            const ago = document.createElement('span');
            ago.className = 'ago' + (machine.ago_class ? ' ' + machine.ago_class : '');
            ago.textContent = machine.ago;
            fields.last_log.appendChild(ago);
            fields.last_log.appendChild(document.createTextNode(' '));
            const when = document.createElement('span');
            when.className = 'ago-time';
            when.textContent = machine.last_log_fmt || '';
            fields.last_log.appendChild(when);
        }
    }

    function open(machine, options) {
        if (!machine) {
            return;
        }

        const opts = options || {};
        lastFocus = document.activeElement;
        fill(machine);
        modal.hidden = false;
        document.body.classList.add('modal-open');
        dialog.focus();

        if (opts.pushUrl) {
            const url = new URL(window.location.href);
            url.searchParams.set('v', String(machine.vehicle_id || ''));
            window.history.replaceState({}, '', url);
        }
    }

    function close() {
        if (modal.hidden) {
            return;
        }

        modal.hidden = true;
        document.body.classList.remove('modal-open');

        const url = new URL(window.location.href);
        if (url.searchParams.has('v')) {
            url.searchParams.delete('v');
            window.history.replaceState({}, '', url);
        }

        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
    }

    function parseMachine(el) {
        const raw = el.getAttribute('data-machine');

        if (!raw) {
            return null;
        }

        try {
            return JSON.parse(raw);
        } catch (error) {
            return null;
        }
    }

    document.querySelectorAll('tr[data-machine]').forEach(function (el) {
        el.addEventListener('click', function (event) {
            if (event.target.closest('.type-row-actions, button, a, form, input, select, label')) {
                return;
            }
            open(parseMachine(el), { pushUrl: true });
        });
        el.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open(parseMachine(el), { pushUrl: true });
            }
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', close);
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            close();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            close();
        }
    });

    const initial = modal.getAttribute('data-open-vehicle');
    if (initial) {
        const match = Array.from(document.querySelectorAll('tr[data-machine]')).find(function (el) {
            const machine = parseMachine(el);
            return machine && String(machine.vehicle_id) === String(initial);
        });

        if (match) {
            open(parseMachine(match), { pushUrl: false });
        }
    }

    window.CAS_MachineModal = {
        open: open,
        close: close
    };
})();
