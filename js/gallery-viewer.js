(function () {
    const grid = document.querySelector('.gallery-grid');
    const viewer = document.getElementById('gallery-viewer');

    if (!grid || !viewer) {
        return;
    }

    const items = Array.from(grid.querySelectorAll('[data-gallery-index]'));
    const imageEl = document.getElementById('gallery-viewer-image');
    const counterEl = document.getElementById('gallery-viewer-counter');
    const prevBtn = document.getElementById('gallery-viewer-prev');
    const nextBtn = document.getElementById('gallery-viewer-next');
    const closeButtons = viewer.querySelectorAll('[data-gallery-close]');

    if (!items.length || !imageEl) {
        return;
    }

    let index = 0;
    let lastFocus = null;

    function openAt(i) {
        index = (i + items.length) % items.length;
        const item = items[index];
        const src = item.getAttribute('data-src') || '';
        const alt = item.getAttribute('data-alt') || '';

        imageEl.src = src;
        imageEl.alt = alt;
        if (counterEl) {
            counterEl.textContent = (index + 1) + ' / ' + items.length;
        }

        if (viewer.hidden) {
            lastFocus = document.activeElement;
            viewer.hidden = false;
            document.body.classList.add('gallery-viewer-open');
        }

        if (nextBtn) {
            nextBtn.focus();
        }
    }

    function closeViewer() {
        if (viewer.hidden) {
            return;
        }
        viewer.hidden = true;
        document.body.classList.remove('gallery-viewer-open');
        imageEl.removeAttribute('src');
        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
    }

    function showNext() {
        openAt(index + 1);
    }

    function showPrev() {
        openAt(index - 1);
    }

    items.forEach(function (item) {
        item.addEventListener('click', function () {
            const i = Number(item.getAttribute('data-gallery-index') || 0);
            openAt(i);
        });
    });

    if (prevBtn) {
        prevBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            showPrev();
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            showNext();
        });
    }

    closeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            closeViewer();
        });
    });

    viewer.addEventListener('click', function (event) {
        if (event.target === viewer) {
            closeViewer();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (viewer.hidden) {
            return;
        }
        if (event.key === 'Escape') {
            closeViewer();
        } else if (event.key === 'ArrowRight') {
            showNext();
        } else if (event.key === 'ArrowLeft') {
            showPrev();
        }
    });
})();
