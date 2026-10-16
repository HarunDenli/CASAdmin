(function () {
    var toggle = document.getElementById('nav-toggle');
    var backdrop = document.getElementById('sidebar-backdrop');
    if (!toggle) {
        return;
    }

    function setOpen(open) {
        document.body.classList.toggle('nav-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        if (backdrop) {
            backdrop.hidden = !open;
        }
    }

    function close() {
        setOpen(false);
    }

    toggle.addEventListener('click', function () {
        setOpen(!document.body.classList.contains('nav-open'));
    });

    if (backdrop) {
        backdrop.addEventListener('click', close);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            close();
        }
    });

    window.addEventListener('resize', function () {
        if (window.matchMedia('(min-width: 901px)').matches) {
            close();
        }
    });
})();
