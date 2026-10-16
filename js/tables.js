(function () {
    function cellKey(td) {
        if (!td) {
            return '';
        }

        if (td.hasAttribute('data-sort')) {
            return String(td.getAttribute('data-sort') || '');
        }

        return (td.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function parsed(raw) {
        if (raw === '' || raw === '—') {
            return { empty: true, num: false, n: 0, s: '' };
        }

        const compact = raw.replace(/,/g, '');

        if (/^-?\d+(\.\d+)?$/.test(compact)) {
            return { empty: false, num: true, n: Number(compact), s: raw };
        }

        return { empty: false, num: false, n: 0, s: raw };
    }

    function compare(a, b, dir) {
        const pa = parsed(a);
        const pb = parsed(b);
        let result = 0;

        if (pa.empty !== pb.empty) {
            result = pa.empty ? 1 : -1;
        } else if (pa.num && pb.num) {
            result = pa.n - pb.n;
        } else {
            result = pa.s.localeCompare(pb.s, undefined, { numeric: true, sensitivity: 'base' });
        }

        return dir === 'desc' ? -result : result;
    }

    function sortTable(table, index, th, heads) {
        const body = table.tBodies[0];

        if (!body) {
            return;
        }

        const next = th.getAttribute('aria-sort') === 'ascending' ? 'desc' : 'asc';

        heads.forEach(function (head) {
            head.removeAttribute('aria-sort');
            head.classList.remove('sorted-asc', 'sorted-desc');
        });

        th.setAttribute('aria-sort', next === 'asc' ? 'ascending' : 'descending');
        th.classList.add(next === 'asc' ? 'sorted-asc' : 'sorted-desc');

        const data = [];
        const sticky = [];

        Array.from(body.rows).forEach(function (row) {
            if (row.cells.length === 1 && row.cells[0].colSpan > 1) {
                sticky.push(row);
            } else {
                data.push(row);
            }
        });

        data.sort(function (left, right) {
            return compare(cellKey(left.cells[index]), cellKey(right.cells[index]), next);
        });

        data.concat(sticky).forEach(function (row) {
            body.appendChild(row);
        });
    }

    function enhance(table) {
        const headRow = table.tHead && table.tHead.rows[0] ? table.tHead.rows[0] : null;
        const body = table.tBodies[0];

        if (!headRow || !body) {
            return;
        }

        const heads = Array.from(headRow.cells);

        heads.forEach(function (th, index) {
            const label = (th.textContent || '').trim();

            if (!label || th.getAttribute('data-nosort') === '1') {
                return;
            }

            th.classList.add('sortable');
            th.tabIndex = 0;
            th.setAttribute('role', 'columnheader');
            th.addEventListener('click', function () {
                sortTable(table, index, th, heads);
            });
            th.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    th.click();
                }
            });
        });
    }

    document.querySelectorAll('table.data-table').forEach(enhance);
})();
