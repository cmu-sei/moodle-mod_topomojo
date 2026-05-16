define([], function() {
    const POLL_MS = 5000;

    return {
        init: function(jobid, sesskey) {
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
                const actionsCell = tr.querySelector('[data-role="row-actions"]');

                if (statusCell) statusCell.textContent = row.status;
                if (gsCell) gsCell.textContent = row.gamespaceid;
                if (errCell) errCell.textContent = row.errormessage;

                // Update actions cell - show appropriate button based on status
                if (actionsCell) {
                    if (row.status === 'ready' && row.gamespaceid) {
                        // For ready status, button is already set server-side based on attempt existence
                        // Don't overwrite it unless we have attempt data in the AJAX response
                        if (row.hasattempt !== undefined) {
                            const endUrl = M.cfg.wwwroot + '/mod/topomojo/bulkdeploy_end_gamespace.php'
                                + '?jobid=' + encodeURIComponent(jobid)
                                + '&rowid=' + encodeURIComponent(row.rowid)
                                + '&gamespaceid=' + encodeURIComponent(row.gamespaceid)
                                + '&sesskey=' + encodeURIComponent(sesskey);
                            const launchUrl = M.cfg.wwwroot + '/mod/topomojo/bulkdeploy_launch.php'
                                + '?jobid=' + encodeURIComponent(jobid)
                                + '&rowid=' + encodeURIComponent(row.rowid)
                                + '&gamespaceid=' + encodeURIComponent(row.gamespaceid)
                                + '&sesskey=' + encodeURIComponent(sesskey);

                            if (row.hasattempt) {
                                actionsCell.innerHTML = '<a href="' + endUrl + '" class="btn btn-sm btn-secondary">End</a>';
                            } else {
                                actionsCell.innerHTML = '<a href="' + launchUrl + '" class="btn btn-sm btn-primary" target="_blank">Launch</a>';
                            }
                        }
                    } else if ((row.status === 'pending' || row.status === 'launched') && row.gamespaceid) {
                        const endUrl = M.cfg.wwwroot + '/mod/topomojo/bulkdeploy_end_gamespace.php'
                            + '?jobid=' + encodeURIComponent(jobid)
                            + '&rowid=' + encodeURIComponent(row.rowid)
                            + '&gamespaceid=' + encodeURIComponent(row.gamespaceid)
                            + '&sesskey=' + encodeURIComponent(sesskey);
                        actionsCell.innerHTML = '<a href="' + endUrl + '" class="btn btn-sm btn-danger">Cancel</a>';
                    } else {
                        actionsCell.innerHTML = '';
                    }
                }
            };

            const poll = async () => {
                try {
                    const url = M.cfg.wwwroot + '/mod/topomojo/bulkdeploy_status_ajax.php?jobid='
                        + encodeURIComponent(jobid) + '&sesskey=' + encodeURIComponent(sesskey);
                    console.log('[BulkDeploy] Polling status for job', jobid);
                    const resp = await fetch(url, {credentials: 'same-origin'});
                    const data = await resp.json();
                    console.log('[BulkDeploy] Status:', data.status, 'Ready:', data.counts.ready, 'Failed:', data.counts.failed);

                    setText('[data-role="job-status"]', data.status);
                    setText('[data-role="count-ready"]', data.counts.ready || 0);
                    setText('[data-role="count-failed"]', data.counts.failed || 0);
                    setText('[data-role="count-skipped"]', data.counts.skipped || 0);
                    setText('[data-role="count-cancelled"]', data.counts.cancelled || 0);
                    (data.rows || []).forEach(renderRow);

                    const terminal = ['completed', 'cancelled', 'failed'];
                    if (terminal.indexOf(data.status) === -1) {
                        console.log('[BulkDeploy] Scheduling next poll in', POLL_MS / 1000, 'seconds');
                        timer = window.setTimeout(poll, POLL_MS);
                    } else {
                        console.log('[BulkDeploy] Job terminal, stopping poll');
                        stop();
                        // Hide cancel button when job is terminal
                        const cancelForm = root.querySelector('form[action*="bulkdeploy_cancel"]');
                        if (cancelForm) {
                            cancelForm.style.display = 'none';
                        }
                    }
                } catch (e) {
                    stop();
                }
            };

            timer = window.setTimeout(poll, POLL_MS);
        }
    };
});
