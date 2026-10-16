(() => {
    const SQL_JS = 'https://cdnjs.cloudflare.com/ajax/libs/sql.js/1.11.0/sql-wasm.js';
    const SQL_WASM = 'https://cdnjs.cloudflare.com/ajax/libs/sql.js/1.11.0/';
    const MIN_TIME = 1420070400000;
    const TRAIL_POINTS = 40;

    const fileInput = document.getElementById('log-file');
    const statusEl = document.getElementById('replay-status');
    const playBtn = document.getElementById('replay-play');
    const pauseBtn = document.getElementById('replay-pause');
    const speedEl = document.getElementById('replay-speed');
    const scrubEl = document.getElementById('replay-scrub');
    const basemapEl = document.getElementById('replay-basemap');
    const statMachine = document.getElementById('stat-machine');
    const statTime = document.getElementById('stat-time');
    const statSpeed = document.getElementById('stat-speed');
    const statHeading = document.getElementById('stat-heading');
    const statNear = document.getElementById('stat-near');
    const nearList = document.getElementById('near-list');
    const statSamples = document.getElementById('stat-samples');
    const fleet = window.CAS_FLEET || {};
    const fleetByName = {};
    Object.keys(fleet).forEach(function (key) {
        const machine = fleet[key];
        const name = String(machine && machine.name ? machine.name : '').trim().toLowerCase();
        if (name) {
            fleetByName[name] = machine;
        }
    });

    const map = L.map('replay-map', { zoomControl: true }).setView([-26.24, 29.78], 14);
    const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    });
    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
        attribution: 'Tiles &copy; Esri — Esri, Maxar, Earthstar Geographics',
    });
    let baseLayer = satelliteLayer.addTo(map);

    basemapEl.addEventListener('change', () => {
        map.removeLayer(baseLayer);
        baseLayer = basemapEl.value === 'map' ? osmLayer : satelliteLayer;
        baseLayer.addTo(map);
    });

    const othersLayer = L.layerGroup().addTo(map);
    const trailLine = L.polyline([], { color: '#7ec8ea', weight: 3, opacity: 0.9 }).addTo(map);
    let hostMarker = null;

    let sqlReady = null;
    let times = [];
    let frames = [];
    let hostId = 0;
    let hostLabel = '';
    let hostLogName = '';
    let index = 0;
    let playing = false;
    let cursorLog = 0;
    let lastWall = 0;
    let trail = [];

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setStatus(text) {
        statusEl.textContent = text;
    }

    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const el = document.createElement('script');
            el.src = src;
            el.onload = resolve;
            el.onerror = () => reject(new Error('Could not load the SQLite engine.'));
            document.head.appendChild(el);
        });
    }

    async function sqlEngine() {
        if (!sqlReady) {
            await loadScript(SQL_JS);
            sqlReady = initSqlJs({ locateFile: (file) => SQL_WASM + file });
        }
        return sqlReady;
    }

    function validPoint(x, y, t) {
        return Number.isFinite(x) && Number.isFinite(y) && Number.isFinite(t)
            && x > -180 && x < 180 && y > -90 && y < 90
            && Math.abs(x) !== 999 && Math.abs(y) !== 999
            && t >= MIN_TIME;
    }

    function fleetMachine(id, logName) {
        const fromLog = String(logName || '').trim();
        if (fromLog && fromLog !== '-') {
            const byName = fleetByName[fromLog.toLowerCase()];
            if (byName) {
                return byName;
            }
        }
        const fromId = fleet[String(id)] || null;
        if (fromId && fromLog && fromLog !== '-') {
            const fleetName = String(fromId.name || '').trim();
            if (fleetName && fleetName.toLowerCase() !== fromLog.toLowerCase()) {
                // Log name disagrees with fleet row for this ID — do not treat as a match.
                return null;
            }
        }
        return fromId;
    }

    function machineLabel(id, logName) {
        const fromLog = String(logName || '').trim();
        const fromFleet = fleetMachine(id, fromLog);
        const name = (fromLog && fromLog !== '-') ? fromLog : (fromFleet && fromFleet.name ? fromFleet.name : '');
        if (name) {
            return name + ' · ' + id;
        }
        return 'ID ' + id;
    }

    function machineSub(id, logName) {
        const fromFleet = fleetMachine(id, logName);
        if (!fromFleet) {
            return '';
        }
        return fromFleet.type || fromFleet.vehicle_type || '';
    }

    function openFleetMachine(id, logName) {
        if (playing) {
            return;
        }

        const machine = fleetMachine(id, logName);
        if (!machine || !window.CAS_MachineModal) {
            return;
        }
        window.CAS_MachineModal.open(machine, { pushUrl: false });
    }

    function setHostMachineLabel(id, label, logName) {
        const known = !!fleetMachine(id, logName);
        const clickable = known && !playing;
        statMachine.textContent = '';
        if (!clickable) {
            statMachine.textContent = label;
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'replay-machine-link';
        button.textContent = label;
        button.title = 'View Fleet Details';
        button.addEventListener('click', function () {
            openFleetMachine(id, logName);
        });
        statMachine.appendChild(button);
    }

    function pickHost(named, counts) {
        let bestNamed = null;
        let bestNamedN = 0;
        named.forEach((n, id) => {
            if (n > bestNamedN) {
                bestNamed = id;
                bestNamedN = n;
            }
        });
        if (bestNamed !== null) {
            return bestNamed;
        }
        let best = 0;
        let bestN = 0;
        counts.forEach((n, id) => {
            if (n > bestN) {
                best = id;
                bestN = n;
            }
        });
        return best;
    }

    function hostIcon(heading) {
        return L.divIcon({
            className: 'cas-host-icon',
            html: '<div class="cas-host" style="transform:rotate(' + Number(heading) + 'deg)"></div>',
            iconSize: [16, 16],
            iconAnchor: [8, 8],
        });
    }

    function typeMapIcon(id, logName, heading) {
        const machine = fleetMachine(id, logName);
        if (!machine || !machine.icon_url) {
            return null;
        }

        const deg = Number(heading) || 0;
        return L.divIcon({
            className: 'cas-type-icon',
            html: '<img src="' + escapeHtml(machine.icon_url) + '" alt="" style="transform:rotate(' + deg + 'deg)">',
            iconSize: [16, 16],
            iconAnchor: [8, 8],
            tooltipAnchor: [8, 0],
        });
    }

    function render(i) {
        if (!times.length) {
            return;
        }
        index = Math.max(0, Math.min(i, times.length - 1));
        scrubEl.value = String(index);
        const t = times[index];
        const frame = frames[index];
        const host = frame.get(hostId);
        othersLayer.clearLayers();
        let near = 0;
        const nearby = [];

        frame.forEach((pos, id) => {
            if (id === hostId) {
                return;
            }
            near += 1;
            const logName = pos.name;
            const label = machineLabel(id, logName);
            const known = !!fleetMachine(id, logName);
            const clickable = known && !playing;
            nearby.push({ id: id, logName: logName, title: label, sub: machineSub(id, logName), clickable: clickable });
            const typeIcon = typeMapIcon(id, logName, pos.h);
            const marker = typeIcon
                ? L.marker([pos.y, pos.x], { icon: typeIcon, keyboard: false })
                : L.marker([pos.y, pos.x], { icon: hostIcon(pos.h), keyboard: false });
            marker.bindTooltip(label + (known ? ' · fleet' : ''), {
                permanent: true,
                direction: 'right',
                offset: [8, 0],
                className: 'cas-label',
                opacity: 0.95,
            }).addTo(othersLayer);

            if (clickable) {
                marker.on('click', function () {
                    openFleetMachine(id, logName);
                });
            }
        });
        nearby.sort((a, b) => a.title.localeCompare(b.title));
        nearList.innerHTML = '';
        if (!nearby.length) {
            nearList.innerHTML = '<li>None In This Time Step</li>';
        } else {
            nearby.forEach(function (item) {
                const li = document.createElement('li');
                if (item.clickable) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'replay-machine-link';
                    button.textContent = item.title;
                    button.title = 'View Fleet Details';
                    button.addEventListener('click', function () {
                        openFleetMachine(item.id, item.logName);
                    });
                    li.appendChild(button);
                } else {
                    li.appendChild(document.createTextNode(item.title));
                }
                if (item.sub) {
                    const sub = document.createElement('span');
                    sub.textContent = item.sub;
                    li.appendChild(sub);
                }
                nearList.appendChild(li);
            });
        }

        if (host) {
            const latlng = [host.y, host.x];
            trail.push(latlng);
            if (trail.length > TRAIL_POINTS) {
                trail.shift();
            }
            trailLine.setLatLngs(trail);
            const hostTypeIcon = typeMapIcon(hostId, hostLogName, host.h);
            const nextHostIcon = hostTypeIcon || hostIcon(host.h);
            if (!hostMarker) {
                hostMarker = L.marker(latlng, { icon: nextHostIcon, zIndexOffset: 600 }).addTo(map);
                hostMarker.bindTooltip(hostLabel, {
                    permanent: true,
                    direction: 'top',
                    offset: [0, -12],
                    className: 'cas-label',
                });
                hostMarker.on('click', function () {
                    openFleetMachine(hostId, hostLogName);
                });
            } else {
                hostMarker.setLatLng(latlng);
                hostMarker.setIcon(nextHostIcon);
                hostMarker.setTooltipContent(hostLabel);
            }
            setHostMachineLabel(hostId, hostLabel, hostLogName);
            statTime.textContent = host.d || new Date(t).toISOString().replace('T', ' ').slice(0, 19);
            statSpeed.textContent = Math.round(host.s) + ' km/h';
            statHeading.textContent = Math.round(host.h) + '°';
        } else {
            setHostMachineLabel(hostId, hostLabel || '—', hostLogName);
            statTime.textContent = new Date(t).toISOString().replace('T', ' ').slice(0, 19);
            statSpeed.textContent = '—';
            statHeading.textContent = '—';
        }
        statNear.textContent = String(near);
    }

    function tick(now) {
        if (!playing) {
            return;
        }
        const dt = lastWall ? (now - lastWall) : 16;
        lastWall = now;
        const speed = Number(speedEl.value) || 1;
        cursorLog += dt * speed;
        while (index < times.length - 1 && times[index] < cursorLog) {
            index += 1;
        }
        if (index >= times.length - 1) {
            playing = false;
            index = times.length - 1;
            render(index);
            return;
        }
        render(index);
        requestAnimationFrame(tick);
    }

    function enableControls(on) {
        playBtn.disabled = !on;
        pauseBtn.disabled = !on;
        speedEl.disabled = !on;
        scrubEl.disabled = !on;
    }

    playBtn.addEventListener('click', () => {
        if (!times.length) {
            return;
        }
        if (window.CAS_MachineModal) {
            window.CAS_MachineModal.close();
        }
        playing = true;
        cursorLog = times[index];
        lastWall = 0;
        render(index);
        requestAnimationFrame(tick);
    });

    pauseBtn.addEventListener('click', () => {
        playing = false;
        render(index);
    });

    scrubEl.addEventListener('input', () => {
        playing = false;
        trail = [];
        const i = Number(scrubEl.value);
        cursorLog = times[i] || 0;
        render(i);
    });

    fileInput.addEventListener('change', async () => {
        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            return;
        }
        playing = false;
        times = [];
        frames = [];
        trail = [];
        othersLayer.clearLayers();
        trailLine.setLatLngs([]);
        if (hostMarker) {
            map.removeLayer(hostMarker);
            hostMarker = null;
        }
        enableControls(false);
        setStatus('Reading ' + file.name + '…');

        try {
            const SQL = await sqlEngine();
            const buf = new Uint8Array(await file.arrayBuffer());
            const db = new SQL.Database(buf);
            const tables = db.exec("SELECT name FROM sqlite_master WHERE type='table'");
            const names = tables.length ? tables[0].values.map((row) => String(row[0])) : [];
            if (!names.includes('replay_glog')) {
                db.close();
                setStatus('That file is not a valid CAS log.');
                return;
            }

            const stmt = db.prepare(`
                SELECT vehicle_id, vehicle_name, locationx, locationy, heading, speed, devlogtime, devlogdate
                FROM replay_glog
                ORDER BY devlogtime
            `);
            const byTime = new Map();
            const named = new Map();
            const counts = new Map();
            const labels = new Map();
            let kept = 0;
            let scanned = 0;

            while (stmt.step()) {
                const row = stmt.getAsObject();
                scanned += 1;
                if (scanned % 80000 === 0) {
                    setStatus('Parsing samples… ' + scanned.toLocaleString());
                    await new Promise((resolve) => setTimeout(resolve, 0));
                }
                const x = Number(row.locationx);
                const y = Number(row.locationy);
                const t = Number(row.devlogtime);
                if (!validPoint(x, y, t)) {
                    continue;
                }
                const id = Number(row.vehicle_id);
                const name = String(row.vehicle_name || '').trim() || '-';
                kept += 1;
                counts.set(id, (counts.get(id) || 0) + 1);
                if (name !== '-') {
                    named.set(id, (named.get(id) || 0) + 1);
                    labels.set(id, name);
                }
                if (!byTime.has(t)) {
                    byTime.set(t, new Map());
                }
                byTime.get(t).set(id, {
                    x,
                    y,
                    h: Number(row.heading) || 0,
                    s: Number(row.speed) || 0,
                    name,
                    d: String(row.devlogdate || ''),
                });
            }
            stmt.free();
            db.close();

            times = Array.from(byTime.keys()).sort((a, b) => a - b);
            frames = times.map((t) => byTime.get(t));
            hostId = pickHost(named, counts);
            hostLogName = labels.get(hostId) || '';
            hostLabel = machineLabel(hostId, hostLogName);

            if (!times.length) {
                setStatus('No usable location data was found in that file.');
                return;
            }

            const hostPts = [];
            frames.forEach((frame) => {
                const host = frame.get(hostId);
                if (host) {
                    hostPts.push([host.y, host.x]);
                }
            });
            if (hostPts.length) {
                map.fitBounds(L.latLngBounds(hostPts), { padding: [24, 24], maxZoom: 16 });
            }

            scrubEl.max = String(times.length - 1);
            scrubEl.value = '0';
            index = 0;
            cursorLog = times[0];
            trail = [];
            enableControls(true);
            setTimeout(() => map.invalidateSize(), 200);
            render(0);
            setStatus('Loaded ' + hostLabel + ' · ' + times.length.toLocaleString() + ' steps.');
            statSamples.textContent = kept.toLocaleString();
        } catch (err) {
            setStatus(err.message || 'Could not read that file.');
            enableControls(false);
        }
    });
})();
