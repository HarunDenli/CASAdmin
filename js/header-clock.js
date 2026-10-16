(function () {
    var el = document.getElementById('header-clock');
    if (!el) {
        return;
    }

    var dateFmt = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Africa/Johannesburg',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
    var dateFmtShort = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Africa/Johannesburg',
        day: 'numeric',
        month: 'short',
    });
    var timeFmt = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Africa/Johannesburg',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    });
    var timeFmtShort = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Africa/Johannesburg',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });

    function isCompact() {
        return window.matchMedia('(max-width: 900px)').matches;
    }

    function tick() {
        var now = new Date();
        var compact = isCompact();
        var datePart = compact ? dateFmtShort.format(now) : dateFmt.format(now);
        var timePart = compact ? timeFmtShort.format(now) : timeFmt.format(now);
        el.textContent = datePart + ' · ' + timePart;
        el.setAttribute('datetime', now.toISOString());
    }

    tick();
    setInterval(tick, 1000);
    window.addEventListener('resize', tick);
})();
