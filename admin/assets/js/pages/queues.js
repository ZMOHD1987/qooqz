/**
 * /admin/assets/js/pages/queues.js
 * Queue Management – Client Logic
 */
(function () {
    'use strict';

    var CFG, S, CAN_DELETE;
    var PER_PAGE = 25;
    var currentPage = 1;
    var currentFilters = {};
    var LOOKUP_CACHE = {};

    var STATUS_MAP = {
        0: 'pending',
        1: 'working',
        2: 'done',
        3: 'failed'
    };

    function reloadConfig() {
        CFG = window.QUEUES_CONFIG || {};
        S = CFG.strings || {};
        CAN_DELETE = !!CFG.canDelete;
    }

    function t(key, fallback) {
        var keys = key.split('.');
        var val = S;
        for (var i = 0; i < keys.length; i++) {
            if (val && typeof val === 'object' && keys[i] in val) {
                val = val[keys[i]];
            } else {
                return fallback || key;
            }
        }
        return typeof val === 'string' ? val : (fallback || key);
    }

    function esc(str) {
        if (str == null) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    /* ── Notification ── */
    function showNotification(msg, type) {
        type = type || 'info';
        var container = document.getElementById('queuesPageContainer');
        if (!container) return;
        var el = document.createElement('div');
        el.className = 'notification notification-' + type;
        el.textContent = msg;
        container.insertBefore(el, container.firstChild);
        setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 4000);
    }

    /* ── Modal helpers ── */
    function openModal(id) { var m = document.getElementById(id); if (m) m.style.display = 'block'; }
    function closeModal(id) { var m = document.getElementById(id); if (m) m.style.display = 'none'; }

    /* ── Stats ── */
    function loadStats() {
        fetch('/api/queues/stats')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success && d.data) {
                    var s = d.data;
                    setText('statTotal', s.total);
                    setText('statPending', s.pending);
                    setText('statWorking', s.working);
                    setText('statDone', s.done);
                    setText('statFailed', s.failed);
                }
            })
            .catch(function (err) { console.warn('Stats load failed:', err.message); });
    }

    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    /* ── Queue names for filter ── */
    function loadQueueNames() {
        fetch('/api/queues/names')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var sel = document.getElementById('filterQueue');
                if (!sel) return;
                var names = [];
                if (d.success && Array.isArray(d.data)) {
                    names = d.data;
                } else if (d.success && d.data && Array.isArray(d.data.items)) {
                    names = d.data.items;
                }
                names.forEach(function (n) {
                    var opt = document.createElement('option');
                    opt.value = n;
                    opt.textContent = n;
                    sel.appendChild(opt);
                });
            })
            .catch(function (err) { console.warn('Queue names load failed:', err.message); });
    }

    /* ── Load jobs ── */
    function loadJobs(page) {
        page = page || 1;
        currentPage = page;
        var offset = (page - 1) * PER_PAGE;

        var params = 'limit=' + PER_PAGE + '&offset=' + offset;
        if (currentFilters.queue) params += '&queue=' + encodeURIComponent(currentFilters.queue);
        if (currentFilters.status !== undefined && currentFilters.status !== '') params += '&status=' + encodeURIComponent(currentFilters.status);
        if (currentFilters.priority) params += '&priority=' + encodeURIComponent(currentFilters.priority);
        if (currentFilters.search) params += '&search=' + encodeURIComponent(currentFilters.search);

        fetch('/api/queues?' + params)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var tbody = document.getElementById('queuesBody');
                if (!tbody) return;
                tbody.innerHTML = '';

                var items = [];
                var total = 0;
                if (d.success && d.data) {
                    if (Array.isArray(d.data.items)) {
                        items = d.data.items;
                    } else if (Array.isArray(d.data)) {
                        items = d.data;
                    }
                    if (d.data.meta) {
                        total = d.data.meta.total || 0;
                    }
                }

                if (items.length === 0) {
                    var tr = document.createElement('tr');
                    var td = document.createElement('td');
                    td.colSpan = 10;
                    td.className = 'text-center';
                    td.textContent = t('table.no_records', 'No jobs found');
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                } else {
                    items.forEach(function (job) {
                        var tr = document.createElement('tr');

                        var statusKey = STATUS_MAP[job.status] || 'unknown';
                        var statusText = t('status.' + statusKey, statusKey);
                        var errText = job.error ? (job.error.length > 60 ? job.error.substring(0, 60) + '...' : job.error) : '';

                        var priorityKey = job.priority || 'normal';
                        var priorityText = t('status.priority.' + priorityKey, priorityKey);
                        var entityId = job.entity_id;
                        var entityType = job.entity_type;
                        var entityDisplay = entityId ? '<span class="entity-lookup" data-type="' + esc(entityType) + '" data-id="' + esc(entityId) + '">' + esc(entityId) + '</span>' : '—';

                        tr.innerHTML =
                            '<td>' + esc(job.id) + '</td>' +
                            '<td>' + esc(job.queue) + '</td>' +
                            '<td>' + esc(job.job_type || '—') + '</td>' +
                            '<td>' + entityDisplay + '</td>' +
                            '<td><span class="priority-badge priority-' + priorityKey + '">' + esc(priorityText) + '</span></td>' +
                            '<td><span class="badge badge-' + statusKey + '">' + esc(statusText) + '</span></td>' +
                            '<td>' + esc(job.attempts) + '</td>' +
                            '<td class="error-cell" title="' + esc(job.error) + '">' + esc(errText) + '</td>' +
                            '<td>' + esc(job.created_at) + '</td>' +
                            '<td class="actions-cell"></td>';

                        var actionsCell = tr.querySelector('.actions-cell');

                        // View button
                        var btnView = document.createElement('button');
                        btnView.className = 'btn btn-sm btn-info';
                        btnView.textContent = t('actions.view', 'View');
                        btnView.setAttribute('data-id', job.id);
                        btnView.addEventListener('click', function () { viewJob(job.id); });
                        actionsCell.appendChild(btnView);

                        // Retry button (only for failed)
                        if (parseInt(job.status) === 3) {
                            var btnRetry = document.createElement('button');
                            btnRetry.className = 'btn btn-sm btn-warning';
                            btnRetry.textContent = t('actions.retry', 'Retry');
                            btnRetry.addEventListener('click', function () { retryJob(job.id); });
                            actionsCell.appendChild(btnRetry);
                        }

                        // Delete button
                        if (CAN_DELETE) {
                            var btnDel = document.createElement('button');
                            btnDel.className = 'btn btn-sm btn-danger';
                            btnDel.textContent = t('actions.delete', 'Delete');
                            btnDel.addEventListener('click', function () { deleteJob(job.id); });
                            actionsCell.appendChild(btnDel);
                        }

                        tbody.appendChild(tr);
                    });
                }

                renderPagination(total, page);
                triggerLookups();
            })
            .catch(function (err) {
                showNotification('Error loading jobs: ' + err.message, 'error');
            });
    }

    /* ── Pagination ── */
    function renderPagination(total, page) {
        var totalPages = Math.ceil(total / PER_PAGE) || 1;
        var info = document.getElementById('paginationInfo');
        var container = document.getElementById('pagination');
        if (!container) return;

        var start = total > 0 ? ((page - 1) * PER_PAGE + 1) : 0;
        var end = Math.min(page * PER_PAGE, total);
        if (info) info.textContent = start + '-' + end + ' ' + t('pagination.of', 'of') + ' ' + total;

        container.innerHTML = '';
        if (totalPages <= 1) return;

        // Prev
        var prev = document.createElement('button');
        prev.className = 'btn btn-sm' + (page <= 1 ? ' disabled' : '');
        prev.textContent = '‹';
        prev.disabled = page <= 1;
        prev.addEventListener('click', function () { if (page > 1) loadJobs(page - 1); });
        container.appendChild(prev);

        // Pages
        for (var i = 1; i <= totalPages; i++) {
            if (totalPages > 7 && i > 2 && i < totalPages - 1 && Math.abs(i - page) > 1) {
                if (i === 3 || i === totalPages - 2) {
                    var dots = document.createElement('span');
                    dots.className = 'pagination-ellipsis';
                    dots.textContent = '…';
                    container.appendChild(dots);
                }
                continue;
            }
            var btn = document.createElement('button');
            btn.className = 'btn btn-sm' + (i === page ? ' btn-primary active' : '');
            btn.textContent = i;
            btn.addEventListener('click', (function (p) { return function () { loadJobs(p); }; })(i));
            container.appendChild(btn);
        }

        // Next
        var next = document.createElement('button');
        next.className = 'btn btn-sm' + (page >= totalPages ? ' disabled' : '');
        next.textContent = '›';
        next.disabled = page >= totalPages;
        next.addEventListener('click', function () { if (page < totalPages) loadJobs(page + 1); });
        container.appendChild(next);
    }

    /* ── View job ── */
    function viewJob(id) {
        fetch('/api/queues?id=' + id)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var container = document.getElementById('viewJobContent');
                if (!container) return;
                if (!d.success || !d.data) {
                    container.innerHTML = '<p>Not found</p>';
                    openModal('viewJobModal');
                    return;
                }
                var job = d.data;
                var statusKey = STATUS_MAP[job.status] || 'unknown';
                var payload = '';
                try {
                    payload = JSON.stringify(JSON.parse(job.payload), null, 2);
                } catch (e) {
                    payload = job.payload || '';
                }
                container.innerHTML =
                    '<div class="detail-row"><strong>' + t('table.id', 'ID') + ':</strong> ' + esc(job.id) + '</div>' +
                    '<div class="detail-row"><strong>' + t('table.queue', 'Queue') + ':</strong> ' + esc(job.queue) + '</div>' +
                    '<div class="detail-row"><strong>' + t('table.job_type', 'Job Type') + ':</strong> ' + esc(job.job_type || '—') + '</div>' +
                    '<div class="detail-row"><strong>' + t('table.entity', 'Entity') + ':</strong> ' + (job.entity_id ? esc(job.entity_type) + ' (' + esc(job.entity_id) + ')' : '—') + '</div>' +
                    '<div class="detail-row"><strong>' + t('table.priority', 'Priority') + ':</strong> <span class="priority-badge priority-' + (job.priority || 'normal') + '">' + esc(t('status.priority.' + (job.priority || 'normal'), job.priority || 'normal')) + '</span></div>' +
                    '<div class="detail-row"><strong>' + t('table.status', 'Status') + ':</strong> <span class="badge badge-' + statusKey + '">' + esc(t('status.' + statusKey, statusKey)) + '</span></div>' +
                    '<div class="detail-row"><strong>' + t('table.attempts', 'Attempts') + ':</strong> ' + esc(job.attempts) + '</div>' +
                    '<div class="detail-row"><strong>' + t('table.created_at', 'Created') + ':</strong> ' + esc(job.created_at) + '</div>' +
                    '<div class="detail-row"><strong>' + t('table.processed_at', 'Processed') + ':</strong> ' + esc(job.processed_at || '—') + '</div>' +
                    '<div class="detail-row"><strong>' + t('table.updated_at', 'Updated') + ':</strong> ' + esc(job.updated_at) + '</div>' +
                    (job.error ? '<div class="detail-row"><strong>' + t('table.error', 'Error') + ':</strong><pre class="error-pre">' + esc(job.error) + '</pre></div>' : '') +
                    '<div class="detail-row"><strong>' + t('table.payload', 'Payload') + ':</strong><pre class="payload-pre">' + esc(payload) + '</pre></div>';
                openModal('viewJobModal');
            })
            .catch(function (err) {
                showNotification('Error: ' + err.message, 'error');
            });
    }

    /* ── Retry job ── */
    function retryJob(id) {
        fetch('/api/queues/retry', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showNotification(t('messages.retry_success', 'Job queued for retry'), 'success');
                    loadJobs(currentPage);
                    loadStats();
                } else {
                    showNotification(d.message || t('messages.retry_failed', 'Failed'), 'error');
                }
            })
            .catch(function () { showNotification(t('messages.retry_failed', 'Failed'), 'error'); });
    }

    /* ── Delete job ── */
    function deleteJob(id) {
        if (!confirm(t('messages.confirm_delete', 'Delete this job?'))) return;
        fetch('/api/queues?id=' + id, { method: 'DELETE' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showNotification(t('messages.delete_success', 'Job deleted'), 'success');
                    loadJobs(currentPage);
                    loadStats();
                } else {
                    showNotification(d.message || t('messages.delete_failed', 'Failed'), 'error');
                }
            })
            .catch(function () { showNotification(t('messages.delete_failed', 'Failed'), 'error'); });
    }

    /* ── Archive ── */
    function archiveDone() {
        if (!confirm(t('messages.confirm_archive', 'Archive all completed jobs?'))) return;
        fetch('/api/queues/archive', { method: 'POST' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showNotification(t('messages.archive_success', 'Archived') + ' (' + (d.data.archived || 0) + ')', 'success');
                    loadJobs(1);
                    loadStats();
                } else {
                    showNotification(d.message || t('messages.archive_failed', 'Failed'), 'error');
                }
            })
            .catch(function () { showNotification(t('messages.archive_failed', 'Failed'), 'error'); });
    }

    /* ── Purge ── */
    function confirmPurge() {
        var status = document.getElementById('purgeStatus').value;
        var days = parseInt(document.getElementById('purgeDays').value) || 30;

        fetch('/api/queues/purge', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: status, days: days })
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                closeModal('purgeModal');
                if (d.success) {
                    showNotification(t('messages.purge_success', 'Purged') + ' (' + (d.data.purged || 0) + ')', 'success');
                    loadJobs(1);
                    loadStats();
                } else {
                    showNotification(d.message || t('messages.purge_failed', 'Failed'), 'error');
                }
            })
            .catch(function () { showNotification(t('messages.purge_failed', 'Failed'), 'error'); });
    }

    /* ── Filter ── */
    function applyFilters() {
        currentFilters = {
            search: (document.getElementById('filterSearch') || {}).value || '',
            queue: (document.getElementById('filterQueue') || {}).value || '',
            priority: (document.getElementById('filterPriority') || {}).value || '',
            status: (document.getElementById('filterStatus') || {}).value
        };
        loadJobs(1);
    }

    function clearFilters() {
        var s = document.getElementById('filterSearch'); if (s) s.value = '';
        var q = document.getElementById('filterQueue'); if (q) q.value = '';
        var p = document.getElementById('filterPriority'); if (p) p.value = '';
        var statusSelect = document.getElementById('filterStatus'); if (statusSelect) statusSelect.value = '';
        currentFilters = {};
        loadJobs(1);
    }

    /* ── Init ── */
    function init() {
        reloadConfig();
        loadStats();
        loadQueueNames();
        loadJobs(1);

        var btnRefresh = document.getElementById('btnRefresh');
        var btnArchiveDone = document.getElementById('btnArchiveDone');
        var btnOpenPurge = document.getElementById('btnOpenPurge');
        var btnFilter = document.getElementById('btnFilter');
        var btnClearFilters = document.getElementById('btnClearFilters');
        var btnConfirmPurge = document.getElementById('btnConfirmPurge');

        if (btnRefresh) btnRefresh.addEventListener('click', function () { loadStats(); loadJobs(currentPage); });
        if (btnArchiveDone) btnArchiveDone.addEventListener('click', archiveDone);
        if (btnOpenPurge) btnOpenPurge.addEventListener('click', function () { openModal('purgeModal'); });
        if (btnFilter) btnFilter.addEventListener('click', applyFilters);
        if (btnClearFilters) btnClearFilters.addEventListener('click', clearFilters);
        if (btnConfirmPurge) btnConfirmPurge.addEventListener('click', confirmPurge);

        // Close modal buttons
        document.querySelectorAll('.btn-close-modal').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modalId = btn.getAttribute('data-modal');
                if (modalId) closeModal(modalId);
            });
        });
    }

    // Run
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /* ── Entity Lookups ── */
    function triggerLookups() {
        var elements = document.querySelectorAll('.entity-lookup');
        elements.forEach(function (el) {
            var type = el.getAttribute('data-type');
            var id = el.getAttribute('data-id');
            if (!type || !id) return;

            var cacheKey = type + ':' + id;
            if (LOOKUP_CACHE[cacheKey]) {
                el.innerHTML = esc(LOOKUP_CACHE[cacheKey]) + ' <small>(' + esc(id) + ')</small>';
                return;
            }

            var url = '';
            if (type === 'tenant') {
                url = '/api/tenants/' + id;
            } else if (type === 'entity') {
                url = '/api/entities?id=' + id;
            } else if (type === 'user') {
                url = '/api/user?id=' + id;
            }

            if (!url) return;

            fetch(url)
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var name = '';
                    if (d.success && d.data) {
                        if (type === 'tenant') name = d.data.name;
                        else if (type === 'entity') name = d.data.store_name || d.data.name;
                        else if (type === 'user') name = d.data.username || d.data.name;
                    }
                    if (name) {
                        LOOKUP_CACHE[cacheKey] = name;
                        el.innerHTML = esc(name) + ' <small>(' + esc(id) + ')</small>';
                    }
                })
                .catch(function () { });
        });
    }

    // Admin framework support
    if (typeof window.Admin !== 'undefined' && window.Admin.page) {
        window.Admin.page.register('queues', init);
    }
    window.page = { run: init };

})();