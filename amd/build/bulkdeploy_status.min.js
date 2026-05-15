const POLL_MS = 5000;

export const init = (jobid, sesskey) => {
    const root = document.querySelector('.mod-topomojo-bulkdeploy-status');
    if (!root) {
        return;
    }

    let timer = null;

    const stop = () => {
        if (timer) {
            window.clearTimeout(timer);
            timer = null;
        }
    };

    const setText = (selector, value) => {
        const el = root.querySelector(selector);
        if (el) {
            el.textContent = String(value);
        }
    };

    const renderRow = (row) => {
        const tr = root.querySelector(`tr[data-rowid="${row.rowid}"]`);
        if (!tr) return;
        const statusCell = tr.querySelector('[data-role="row-status"]');
        const gsCell = tr.querySelector('[data-role="row-gamespace"]');
        const errCell = tr.querySelector('[data-role="row-error"]');
        if (statusCell) statusCell.textContent = row.status;
        if (gsCell) gsCell.textContent = row.gamespaceid;
        if (errCell) errCell.textContent = row.errormessage;
    };

    const poll = async () => {
        try {
            const url = M.cfg.wwwroot + '/mod/topomojo/bulkdeploy_status_ajax.php?jobid='
                + encodeURIComponent(jobid) + '&sesskey=' + encodeURIComponent(sesskey);
            const resp = await fetch(url, {credentials: 'same-origin'});
            const data = await resp.json();

            setText('[data-role="job-status"]', data.status);
            setText('[data-role="count-ready"]', data.counts.ready || 0);
            setText('[data-role="count-failed"]', data.counts.failed || 0);
            setText('[data-role="count-skipped"]', data.counts.skipped || 0);
            setText('[data-role="count-cancelled"]', data.counts.cancelled || 0);
            (data.rows || []).forEach(renderRow);

            const terminal = ['completed', 'cancelled', 'failed'];
            if (terminal.indexOf(data.status) === -1) {
                timer = window.setTimeout(poll, POLL_MS);
            } else {
                stop();
            }
        } catch (e) {
            stop();
        }
    };

    timer = window.setTimeout(poll, POLL_MS);
};
