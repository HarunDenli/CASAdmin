(function () {
    const modal = document.getElementById('type-modal');

    if (!modal) {
        return;
    }

    const dialog = modal.querySelector('.modal-dialog');
    const closeButtons = modal.querySelectorAll('[data-modal-close]');
    const titleEl = document.getElementById('type-modal-title');
    const imageEl = document.getElementById('type-modal-image');
    const captionEl = document.getElementById('type-modal-caption');
    const machinesEl = document.getElementById('type-modal-machines');
    const onlineEl = document.getElementById('type-modal-online');
    const offlineEl = document.getElementById('type-modal-offline');
    const sitesEl = document.getElementById('type-modal-sites');
    const modelsEl = document.getElementById('type-modal-models');
    const unitsEl = document.getElementById('type-modal-units');

    let lastFocus = null;

    function textOrDash(value) {
        const text = String(value == null ? '' : value).trim();
        return text !== '' ? text : '—';
    }

    function clear(node) {
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function addStatList(target, items, emptyLabel) {
        clear(target);

        if (!items || items.length === 0) {
            const li = document.createElement('li');
            li.className = 'type-empty';
            li.textContent = emptyLabel;
            target.appendChild(li);
            return;
        }

        items.forEach(function (item) {
            const li = document.createElement('li');
            const name = document.createElement('span');
            name.textContent = item.name || item.label || '—';
            const meta = document.createElement('strong');
            if (item.machines != null) {
                meta.textContent = item.online + ' online · ' + item.offline + ' offline · ' + item.machines + ' total';
            } else {
                meta.textContent = String(item.count) + (item.count === 1 ? ' unit' : ' units');
            }
            li.appendChild(name);
            li.appendChild(meta);
            target.appendChild(li);
        });
    }

    function fill(type) {
        const label = textOrDash(type.label);
        titleEl.textContent = label;
        imageEl.src = type.image_url || '';
        imageEl.alt = label + ' type';
        captionEl.textContent = label;
        machinesEl.textContent = String(type.machines || 0);
        onlineEl.textContent = String(type.online || 0);
        offlineEl.textContent = String(type.offline || 0);

        addStatList(sitesEl, type.sites || [], 'No Sites');
        addStatList(modelsEl, type.models || [], 'No Device Models');

        clear(unitsEl);
        const units = type.units || [];

        if (units.length === 0) {
            const empty = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 4;
            cell.textContent = 'No Machines Of This Type.';
            empty.appendChild(cell);
            unitsEl.appendChild(empty);
            return;
        }

        units.forEach(function (unit) {
            const row = document.createElement('tr');
            const cells = [
                textOrDash(unit.name),
                textOrDash(unit.vehicle_id),
                textOrDash(unit.site_name),
                null
            ];

            cells.forEach(function (value, index) {
                const td = document.createElement('td');
                if (index === 3) {
                    const pill = document.createElement('span');
                    const online = Number(unit.connected) === 1;
                    pill.className = 'pill ' + (online ? 'pill-on' : 'pill-off');
                    pill.textContent = unit.connected_label || (online ? 'Online' : 'Offline');
                    td.appendChild(pill);
                } else {
                    td.textContent = value;
                }
                row.appendChild(td);
            });

            unitsEl.appendChild(row);
        });
    }

    function open(type) {
        if (!type) {
            return;
        }

        lastFocus = document.activeElement;
        fill(type);
        modal.hidden = false;
        document.body.classList.add('modal-open');
        dialog.focus();
    }

    function close() {
        if (modal.hidden) {
            return;
        }

        modal.hidden = true;
        document.body.classList.remove('modal-open');

        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
    }

    function parseType(el) {
        const raw = el.getAttribute('data-type');

        if (!raw) {
            return null;
        }

        try {
            return JSON.parse(raw);
        } catch (error) {
            return null;
        }
    }

    document.querySelectorAll('[data-type]').forEach(function (el) {
        el.addEventListener('click', function () {
            open(parseType(el));
        });
        el.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open(parseType(el));
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
})();
