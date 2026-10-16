(function () {
    const modal = document.getElementById('machine-type-modal');

    if (!modal) {
        return;
    }

    const dialog = modal.querySelector('.modal-dialog');
    const titleEl = document.getElementById('machine-type-modal-title');
    const leadEl = document.getElementById('machine-type-modal-lead');
    const actionEl = document.getElementById('machine-type-action');
    const typeIdEl = document.getElementById('machine-type-id');
    const nameEl = document.getElementById('machine-type-name');
    const sourceEl = document.getElementById('machine-type-source');
    const photoPreview = document.getElementById('machine-type-photo-preview');
    const iconPreview = document.getElementById('machine-type-icon-preview');
    const photoInput = document.getElementById('machine-type-photo');
    const iconInput = document.getElementById('machine-type-icon');
    const clearPhotoWrap = document.getElementById('machine-type-clear-photo-wrap');
    const clearIconWrap = document.getElementById('machine-type-clear-icon-wrap');
    const clearPhoto = document.getElementById('machine-type-clear-photo');
    const clearIcon = document.getElementById('machine-type-clear-icon');
    const submitEl = document.getElementById('machine-type-submit');
    const deleteWrap = document.getElementById('machine-type-delete-wrap');
    const deleteIdEl = document.getElementById('machine-type-delete-id');
    const closeButtons = modal.querySelectorAll('[data-modal-close]');

    const canManageCatalog = modal.getAttribute('data-can-manage') === '1';
    const defaultPhoto = modal.getAttribute('data-default-photo') || '';
    const defaultIcon = modal.getAttribute('data-default-icon') || '';
    const clientLead = modal.getAttribute('data-client-lead') || '';

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

    function setPreview(img, url) {
        img.src = url || '';
    }

    function resetFiles() {
        if (photoInput) {
            photoInput.value = '';
        }
        if (iconInput) {
            iconInput.value = '';
        }
        if (clearPhoto) {
            clearPhoto.checked = false;
        }
        if (clearIcon) {
            clearIcon.checked = false;
        }
    }

    function openCreate() {
        if (!canManageCatalog) {
            return;
        }

        titleEl.textContent = 'Add Machine Type';
        leadEl.textContent = '';
        actionEl.value = 'create';
        typeIdEl.value = '';
        nameEl.value = '';
        nameEl.readOnly = false;
        nameEl.required = true;
        sourceEl.value = 'Manual';
        leadEl.hidden = true;
        setPreview(photoPreview, defaultPhoto);
        setPreview(iconPreview, defaultIcon);
        clearPhotoWrap.hidden = true;
        clearIconWrap.hidden = true;
        deleteWrap.hidden = true;
        submitEl.textContent = 'Create Type';
        resetFiles();
        openModal();
        nameEl.focus();
    }

    function openEdit(type) {
        if (!type) {
            return;
        }

        const isManual = String(type.source || '') === 'manual';
        const canRename = canManageCatalog && isManual;

        titleEl.textContent = type.name || 'Machine Type';
        if (!canManageCatalog && clientLead) {
            leadEl.textContent = clientLead;
            leadEl.hidden = false;
        } else if (isManual) {
            leadEl.textContent = '';
            leadEl.hidden = true;
        } else {
            leadEl.textContent = 'You can change the photo and icon.';
            leadEl.hidden = false;
        }

        actionEl.value = 'update';
        typeIdEl.value = String(type.id || '');
        nameEl.value = type.name || '';
        nameEl.required = canRename;
        nameEl.readOnly = !canRename;
        sourceEl.value = isManual ? 'Manual' : 'Imported';
        setPreview(photoPreview, type.photo_url || defaultPhoto);
        setPreview(iconPreview, type.icon_url || defaultIcon);
        clearPhotoWrap.hidden = !type.has_photo;
        clearIconWrap.hidden = !type.has_icon;
        deleteWrap.hidden = !(canManageCatalog && isManual);
        if (deleteIdEl) {
            deleteIdEl.value = String(type.id || '');
        }
        submitEl.textContent = 'Save Type';
        resetFiles();
        openModal();
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

    document.querySelectorAll('[data-open-type-edit]').forEach(function (el) {
        el.addEventListener('click', function () {
            openEdit(parseType(el));
        });
    });

    document.querySelectorAll('[data-open-type-create]').forEach(function (el) {
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

    if (photoInput) {
        photoInput.addEventListener('change', function () {
            const file = photoInput.files && photoInput.files[0];
            if (file) {
                setPreview(photoPreview, URL.createObjectURL(file));
            }
        });
    }

    if (iconInput) {
        iconInput.addEventListener('change', function () {
            const file = iconInput.files && iconInput.files[0];
            if (file) {
                setPreview(iconPreview, URL.createObjectURL(file));
            }
        });
    }

    const initial = modal.getAttribute('data-open');
    if (initial === 'create') {
        openCreate();
        const postedName = modal.getAttribute('data-open-name');
        if (postedName) {
            nameEl.value = postedName;
        }
    } else if (initial === 'edit') {
        const raw = modal.getAttribute('data-open-type');
        if (raw) {
            try {
                openEdit(JSON.parse(raw));
            } catch (error) {
                // ignore
            }
        }
    }
})();
