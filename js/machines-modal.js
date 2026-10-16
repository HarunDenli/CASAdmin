(function () {
    const modal = document.getElementById('machine-admin-modal');

    if (!modal) {
        return;
    }

    const dialog = modal.querySelector('.modal-dialog');
    const titleEl = document.getElementById('machine-admin-modal-title');
    const leadEl = document.getElementById('machine-admin-modal-lead');
    const actionEl = document.getElementById('machine-admin-action');
    const idEl = document.getElementById('machine-admin-id');
    const vehicleIdEl = document.getElementById('machine-admin-vehicle-id');
    const nameEl = document.getElementById('machine-admin-name');
    const siteEl = document.getElementById('machine-admin-site');
    const siteHiddenEl = document.getElementById('machine-admin-site-hidden');
    const typeEl = document.getElementById('machine-admin-type');
    const modelEl = document.getElementById('machine-admin-model');
    const statusEl = document.getElementById('machine-admin-status');
    const uidEl = document.getElementById('machine-admin-uid');
    const vComputerEl = document.getElementById('machine-admin-vcomputer');
    const vDaemonEl = document.getElementById('machine-admin-vdaemon');
    const sourceEl = document.getElementById('machine-admin-source');
    const linkEl = document.getElementById('machine-admin-link');
    const lastLogEl = document.getElementById('machine-admin-last-log');
    const submitEl = document.getElementById('machine-admin-submit');
    const deleteWrap = document.getElementById('machine-admin-delete-wrap');
    const deleteIdEl = document.getElementById('machine-admin-delete-id');
    const closeButtons = modal.querySelectorAll('[data-modal-close]');

    const canPickSite = modal.getAttribute('data-can-pick-site') === '1';
    const forcedSiteId = modal.getAttribute('data-forced-site-id') || '';
    const forcedSiteName = modal.getAttribute('data-forced-site-name') || '';

    const editableFields = [vehicleIdEl, nameEl, typeEl, modelEl, statusEl, uidEl, vComputerEl, vDaemonEl];
    if (canPickSite && siteEl) {
        editableFields.push(siteEl);
    }

    let lastFocus = null;

    function openModal() {
        lastFocus = document.activeElement;
        modal.hidden = false;
        document.body.classList.add('modal-open');
        dialog.focus();
    }

    function closeModal() {
        if (modal.hidden) {
            return;
        }

        modal.hidden = true;
        document.body.classList.remove('modal-open');

        const url = new URL(window.location.href);
        let changed = false;
        ['id', 'new'].forEach(function (key) {
            if (url.searchParams.has(key)) {
                url.searchParams.delete(key);
                changed = true;
            }
        });
        if (changed) {
            window.history.replaceState({}, '', url);
        }

        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
    }

    function setEditable(on) {
        editableFields.forEach(function (el) {
            if (!el) {
                return;
            }
            if (el.tagName === 'SELECT') {
                el.disabled = !on;
            } else {
                el.readOnly = !on;
            }
        });
        if (!canPickSite && siteEl) {
            siteEl.disabled = true;
        }
    }

    function setSelectValue(el, value) {
        if (!el) {
            return;
        }
        const wanted = String(value || '');
        el.value = wanted;
        if (wanted && el.value !== wanted) {
            const option = document.createElement('option');
            option.value = wanted;
            option.textContent = wanted;
            el.appendChild(option);
            el.value = wanted;
        }
    }

    function fillForm(data) {
        vehicleIdEl.value = data.vehicle_id || '';
        nameEl.value = data.name || '';
        uidEl.value = data.device_uid || '';
        setSelectValue(typeEl, data.vehicle_type || '');
        setSelectValue(modelEl, data.device_model || '');
        setSelectValue(statusEl, data.status || '');
        setSelectValue(vComputerEl, data.v_computer || '');
        setSelectValue(vDaemonEl, data.v_daemon || '');

        if (canPickSite && siteEl) {
            siteEl.value = data.site_id != null ? String(data.site_id) : '';
        } else if (siteHiddenEl) {
            siteHiddenEl.value = forcedSiteId;
            if (siteEl) {
                siteEl.value = forcedSiteName;
            }
        }
    }

    function openCreate(posted) {
        titleEl.textContent = 'Add Machine';
        leadEl.textContent = '';
        actionEl.value = 'create';
        idEl.value = '';
        sourceEl.value = 'Manual';
        linkEl.value = 'Offline';
        lastLogEl.value = '—';
        leadEl.hidden = true;
        deleteWrap.hidden = true;
        submitEl.hidden = false;
        submitEl.textContent = 'Create Machine';
        setEditable(true);
        fillForm(posted || {});
        if (!canPickSite && siteHiddenEl) {
            siteHiddenEl.value = forcedSiteId;
            if (siteEl) {
                siteEl.value = forcedSiteName;
            }
        }
        vehicleIdEl.required = true;
        nameEl.required = true;
        if (canPickSite && siteEl) {
            siteEl.required = true;
        }
        openModal();
        vehicleIdEl.focus();
    }

    function openEdit(machine) {
        if (!machine) {
            return;
        }

        const isManual = String(machine.source || '') === 'manual';

        titleEl.textContent = machine.name || 'Machine';
        if (isManual) {
            leadEl.textContent = '';
            leadEl.hidden = true;
        } else {
            leadEl.textContent = 'Imported machines cannot be edited.';
            leadEl.hidden = false;
        }

        actionEl.value = 'update';
        idEl.value = String(machine.id || '');
        sourceEl.value = isManual ? 'Manual' : 'Imported';
        linkEl.value = Number(machine.connected) === 1 ? 'Online' : 'Offline';
        lastLogEl.value = machine.last_log_fmt || '—';
        deleteWrap.hidden = !isManual;
        if (deleteIdEl) {
            deleteIdEl.value = String(machine.id || '');
        }
        submitEl.hidden = !isManual;
        submitEl.textContent = 'Save Machine';
        setEditable(isManual);
        fillForm(machine);
        vehicleIdEl.required = isManual;
        nameEl.required = isManual;
        if (canPickSite && siteEl) {
            siteEl.required = isManual;
        }
        openModal();
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

    document.querySelectorAll('[data-open-machine-edit]').forEach(function (el) {
        el.addEventListener('click', function () {
            openEdit(parseMachine(el));
        });
    });

    document.querySelectorAll('[data-open-machine-create]').forEach(function (el) {
        el.addEventListener('click', function () {
            openCreate();
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            closeModal();
        });
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    const initial = modal.getAttribute('data-open');
    if (initial === 'create') {
        let posted = null;
        const rawPosted = modal.getAttribute('data-open-posted');
        if (rawPosted) {
            try {
                posted = JSON.parse(rawPosted);
            } catch (error) {
                posted = null;
            }
        }
        openCreate(posted);
    } else if (initial === 'edit') {
        const raw = modal.getAttribute('data-open-machine');
        if (raw) {
            try {
                openEdit(JSON.parse(raw));
            } catch (error) {
                // ignore
            }
        }
    }
})();
