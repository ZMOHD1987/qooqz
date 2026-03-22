/**
 * /admin/assets/js/pages/ads.js
 * Ads Management - Campaigns + Ad Units (with Images & Translations)
 */
(function () {
    'use strict';

    /* ──────────────────────────────────────────────
     * Config & state
     * ──────────────────────────────────────────── */
    var CFG, CSRF, STRINGS, CAN_CREATE, CAN_EDIT, CAN_DELETE;
    var PER_PAGE = 25;

    // Campaigns state
    var campaignsPage    = 1;
    var campaignsFilters = {};
    var campaignCache    = [];
    var currencyCache    = [];

    // Ads state
    var adsPage    = 1;
    var adsFilters = {};

    // Ad modal state
    var adSelectedImages = [];

    // Active tab
    var activeTab = 'campaigns';

    // Placements state
    var placementsPage    = 1;
    var placementsFilters = {};
    var currentPlacementId = null;
    var placementItemsPage = 1;

    function reloadConfig() {
        CFG        = window.ADS_CONFIG || {};
        CSRF       = CFG.csrfToken || '';
        STRINGS    = CFG.strings   || {};
        CAN_CREATE = !!CFG.canCreate;
        CAN_EDIT   = !!CFG.canEdit;
        CAN_DELETE = !!CFG.canDelete;
    }
    reloadConfig();

    /* ──────────────────────────────────────────────
     * Translation helper
     * ──────────────────────────────────────────── */
    function t(key, fallback) {
        var keys = key.split('.');
        var val  = STRINGS;
        for (var i = 0; i < keys.length; i++) {
            if (val && typeof val === 'object' && keys[i] in val) {
                val = val[keys[i]];
            } else {
                return fallback || key;
            }
        }
        return (typeof val === 'string') ? val : (fallback || key);
    }

    /* ──────────────────────────────────────────────
     * XSS escape
     * ──────────────────────────────────────────── */
    function esc(str) {
        if (str == null) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    /* ──────────────────────────────────────────────
     * Modal helpers
     * ──────────────────────────────────────────── */
    function openModal(id)  { var el = document.getElementById(id); if (el) el.style.display = 'flex'; }
    function closeModal(id) { var el = document.getElementById(id); if (el) el.style.display = 'none'; }

    /* ──────────────────────────────────────────────
     * Toast notifications
     * ──────────────────────────────────────────── */
    function showNotification(message, type) {
        type = type || 'info';
        var container = document.getElementById('adsNotifications');
        if (!container) {
            container = document.createElement('div');
            container.id = 'adsNotifications';
            container.className = 'ads-notifications';
            document.body.appendChild(container);
        }
        var toast = document.createElement('div');
        toast.className = 'ads-toast ads-toast-' + type;
        toast.textContent = message;
        var close = document.createElement('span');
        close.className = 'ads-toast-close';
        close.textContent = '\u00d7';
        close.onclick = function () { toast.remove(); };
        toast.appendChild(close);
        container.appendChild(toast);
        setTimeout(function () { if (toast.parentNode) toast.remove(); }, 4000);
    }

    /* ──────────────────────────────────────────────
     * Status badge (shared)
     * ──────────────────────────────────────────── */
    function statusBadge(status) {
        var cls = {
            active:    'badge-active',
            paused:    'badge-paused',
            rejected:  'badge-rejected',
            draft:     'badge-draft',
            completed: 'badge-completed'
        }[status] || 'badge-default';
        var label = t('status.' + status, status);
        return '<span class="badge ' + cls + '">' + esc(label) + '</span>';
    }

    /* ──────────────────────────────────────────────
     * TAB SWITCHING
     * ──────────────────────────────────────────── */
    function switchTab(tabName) {
        activeTab = tabName;

        document.querySelectorAll('.ads-tab-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.tab === tabName);
        });
        document.querySelectorAll('.ads-tab-panel').forEach(function (panel) {
            panel.style.display = (panel.id === 'tab' + capitalize(tabName)) ? '' : 'none';
        });

        var btnAddCampaign   = document.getElementById('btnAddCampaign');
        var btnAddAd         = document.getElementById('btnAddAd');
        var btnAddPlacement  = document.getElementById('btnAddPlacement');
        if (btnAddCampaign)  btnAddCampaign.style.display  = (tabName === 'campaigns'  && CAN_CREATE) ? '' : 'none';
        if (btnAddAd)        btnAddAd.style.display        = (tabName === 'ads'        && CAN_CREATE) ? '' : 'none';
        if (btnAddPlacement) btnAddPlacement.style.display = (tabName === 'placements' && CAN_CREATE) ? '' : 'none';
    }

    function capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    /* ══════════════════════════════════════════════
     * CAMPAIGNS
     * ══════════════════════════════════════════ */

    /* Load currencies for campaign modal */
    function loadCurrencies(callback) {
        if (currencyCache.length > 0) { callback(currencyCache); return; }
        var url = (CFG.apiBase || '/api') + '/currencies?limit=200&order_by=code&order_dir=ASC';
        if (CFG.tenantId) url += '&tenant_id=' + CFG.tenantId;
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                currencyCache = (json.data && json.data.items) ? json.data.items : (Array.isArray(json.data) ? json.data : []);
                callback(currencyCache);
            })
            .catch(function () {
                showNotification(t('error_currencies_load', 'Failed to load currencies'), 'error');
                callback([]);
            });
    }

    function populateCurrencySelect(selectEl, selectedId) {
        loadCurrencies(function (currencies) {
            var html = '<option value="">' + esc(t('form.select_currency', '-- Select Currency --')) + '</option>';
            currencies.forEach(function (c) {
                var label = (c.code || '') + (c.name ? ' - ' + c.name : '');
                var sel   = (selectedId && String(c.id) === String(selectedId)) ? ' selected' : '';
                html += '<option value="' + esc(c.id) + '"' + sel + '>' + esc(label) + '</option>';
            });
            selectEl.innerHTML = html;
        });
    }

    /* Load campaigns (used by campaign tab + ads filter + ad form) */
    function loadCampaignsData(callback, ignoreCache) {
        if (!ignoreCache && campaignCache.length > 0) { callback(campaignCache); return; }
        var url = (CFG.apiBase || '/api') + '/ad_campaigns?limit=500&order_by=id&order_dir=ASC';
        if (CFG.tenantId) url += '&tenant_id=' + CFG.tenantId;
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                campaignCache = (json.data && json.data.items) ? json.data.items : [];
                callback(campaignCache);
            })
            .catch(function () {
                showNotification(t('error_campaigns_load', 'Failed to load campaigns'), 'error');
                callback([]);
            });
    }

    /* Load campaigns table */
    function loadCampaigns(params) {
        params = params || {};
        var page    = params.page    || campaignsPage;
        var filters = params.filters || campaignsFilters;
        var offset  = (page - 1) * PER_PAGE;

        var url = (CFG.apiBase || '/api') + '/ad_campaigns?limit=' + PER_PAGE + '&offset=' + offset + '&order_by=id&order_dir=DESC';
        if (CFG.tenantId) url += '&tenant_id=' + CFG.tenantId;
        if (filters.status)        url += '&status='        + encodeURIComponent(filters.status);
        if (filters.pricing_model) url += '&pricing_model=' + encodeURIComponent(filters.pricing_model);
        if (filters.search)        url += '&search='        + encodeURIComponent(filters.search);

        var tbody = document.getElementById('campaignsTableBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center">...</td></tr>';

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                var items = (json.data && json.data.items) ? json.data.items : [];
                var total = (json.data && json.data.meta) ? (json.data.meta.total || 0) : 0;
                renderCampaignsTable(items);
                renderCampaignsPagination(page, total);
                updateCampaignsPaginationInfo(page, items.length, total);
                // Bust cache so new data populates dropdowns
                campaignCache = items;
            })
            .catch(function () {
                showNotification(t('error_campaigns_load', 'Failed to load campaigns'), 'error');
                if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center">' + esc(t('campaigns_table.no_records', 'No campaigns found')) + '</td></tr>';
            });
    }

    function renderCampaignsTable(items) {
        var tbody = document.getElementById('campaignsTableBody');
        if (!tbody) return;
        if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center">' + esc(t('campaigns_table.no_records', 'No campaigns found')) + '</td></tr>';
            return;
        }
        var html = '';
        items.forEach(function (c) {
            var pricingLabel = t('pricing_model.' + c.pricing_model, c.pricing_model || '-');
            var budget = (c.budget != null) ? (parseFloat(c.budget).toFixed(2) + (c.currency_code ? ' ' + c.currency_code : '')) : '-';
            html += '<tr>';
            html += '<td>' + esc(c.id) + '</td>';
            html += '<td><strong>' + esc(c.name) + '</strong></td>';
            html += '<td>' + esc(budget) + '</td>';
            html += '<td>' + esc(c.currency_code || '-') + '</td>';
            html += '<td>' + esc(pricingLabel) + '</td>';
            html += '<td>' + esc(c.start_date ? c.start_date.substring(0, 10) : '-') + '</td>';
            html += '<td>' + esc(c.end_date   ? c.end_date.substring(0, 10)   : '-') + '</td>';
            html += '<td>' + statusBadge(c.status) + '</td>';
            html += '<td><div class="row-actions">';
            if (CAN_EDIT) {
                html += '<button class="btn btn-secondary btn-sm btn-edit-campaign" data-id="' + esc(c.id) + '">' + esc(t('table.edit', 'Edit')) + '</button>';
            }
            if (CAN_DELETE) {
                html += '<button class="btn btn-danger btn-sm btn-delete-campaign" data-id="' + esc(c.id) + '">' + esc(t('table.delete', 'Delete')) + '</button>';
            }
            html += '</div></td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;

        tbody.querySelectorAll('.btn-edit-campaign').forEach(function (btn) {
            btn.addEventListener('click', function () { openEditCampaignModal(parseInt(btn.dataset.id, 10)); });
        });
        tbody.querySelectorAll('.btn-delete-campaign').forEach(function (btn) {
            btn.addEventListener('click', function () { confirmDeleteCampaign(parseInt(btn.dataset.id, 10)); });
        });
    }

    function renderCampaignsPagination(page, total) {
        var totalPages = Math.ceil(total / PER_PAGE) || 1;
        var pg = document.getElementById('campaignsPagination');
        if (!pg) return;
        var html = '';
        html += '<button class="page-btn" ' + (page <= 1 ? 'disabled' : '') + ' data-page="' + (page - 1) + '">' + esc(t('pagination.prev', 'Prev')) + '</button>';
        var start = Math.max(1, page - 2);
        var end   = Math.min(totalPages, start + 4);
        if (end - start < 4) start = Math.max(1, end - 4);
        for (var i = start; i <= end; i++) {
            html += '<button class="page-btn' + (i === page ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }
        html += '<button class="page-btn" ' + (page >= totalPages ? 'disabled' : '') + ' data-page="' + (page + 1) + '">' + esc(t('pagination.next', 'Next')) + '</button>';
        pg.innerHTML = html;
        pg.querySelectorAll('.page-btn:not([disabled])').forEach(function (btn) {
            btn.addEventListener('click', function () {
                campaignsPage = parseInt(btn.dataset.page, 10);
                loadCampaigns({ page: campaignsPage, filters: campaignsFilters });
            });
        });
    }

    function updateCampaignsPaginationInfo(page, count, total) {
        var el = document.getElementById('campaignsPaginationInfo');
        if (!el) return;
        var from = total === 0 ? 0 : (page - 1) * PER_PAGE + 1;
        var to   = (page - 1) * PER_PAGE + count;
        el.textContent = from + '-' + to + ' ' + t('pagination.of', 'of') + ' ' + total;
    }

    /* Campaign Modal - Add */
    function openAddCampaignModal() {
        reloadConfig();
        var form = document.getElementById('campaignForm');
        if (form) form.reset();
        var idEl = document.getElementById('campaignId');
        if (idEl) idEl.value = '';
        var titleEl = document.getElementById('campaignModalTitle');
        if (titleEl) titleEl.textContent = t('modal.add_campaign_title', 'Add Campaign');

        var currSel = document.getElementById('campaignCurrencyId');
        if (currSel) populateCurrencySelect(currSel, null);

        openModal('campaignModal');
    }

    /* Campaign Modal - Edit */
    function openEditCampaignModal(id) {
        reloadConfig();
        var url = (CFG.apiBase || '/api') + '/ad_campaigns?id=' + id;
        if (CFG.tenantId) url += '&tenant_id=' + CFG.tenantId;
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                var c = json.data || json;
                if (!c || !c.id) { showNotification(t('error_campaigns_load', 'Failed to load campaign'), 'error'); return; }

                var idEl = document.getElementById('campaignId');
                if (idEl) idEl.value = c.id;

                var titleEl = document.getElementById('campaignModalTitle');
                if (titleEl) titleEl.textContent = t('modal.edit_campaign_title', 'Edit Campaign');

                var setVal = function (elId, val) { var el = document.getElementById(elId); if (el) el.value = val || ''; };
                setVal('campaignName',         c.name);
                setVal('campaignBudget',       c.budget != null ? c.budget : 0);
                setVal('campaignPricingModel', c.pricing_model);
                setVal('campaignStartDate',    c.start_date ? c.start_date.substring(0, 10) : '');
                setVal('campaignEndDate',      c.end_date   ? c.end_date.substring(0, 10)   : '');
                setVal('campaignStatus',       c.status);

                var currSel = document.getElementById('campaignCurrencyId');
                if (currSel) populateCurrencySelect(currSel, c.currency_id);

                openModal('campaignModal');
            })
            .catch(function () { showNotification(t('error_campaigns_load', 'Failed to load campaign'), 'error'); });
    }

    /* Save Campaign */
    function saveCampaign() {
        var idEl = document.getElementById('campaignId');
        var id   = idEl ? parseInt(idEl.value, 10) : 0;

        var getVal = function (elId) { var el = document.getElementById(elId); return el ? el.value.trim() : ''; };

        var data = {
            name:          getVal('campaignName'),
            budget:        parseFloat(getVal('campaignBudget')) || 0,
            currency_id:   parseInt(getVal('campaignCurrencyId'), 10) || 0,
            pricing_model: getVal('campaignPricingModel'),
            start_date:    getVal('campaignStartDate') || null,
            end_date:      getVal('campaignEndDate')   || null,
            status:        getVal('campaignStatus'),
        };

        if (id > 0) data.id = id;

        var url    = (CFG.apiBase || '/api') + '/ad_campaigns' + (CFG.tenantId ? '?tenant_id=' + CFG.tenantId : '');
        var method = id > 0 ? 'PUT' : 'POST';

        var btn = document.getElementById('campaignSaveBtn');
        if (btn) btn.disabled = true;

        fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success || json.status === 'success') {
                    closeModal('campaignModal');
                    showNotification(t('campaign_saved', 'Campaign saved successfully'), 'success');
                    campaignCache = []; // bust cache
                    loadCampaigns({ page: campaignsPage, filters: campaignsFilters });
                    // Refresh ads campaign filter
                    refreshCampaignFilter();
                } else {
                    var msg = json.message || json.error || t('error_campaign_save', 'Failed to save campaign');
                    showNotification(msg, 'error');
                }
            })
            .catch(function () { showNotification(t('error_campaign_save', 'Failed to save campaign'), 'error'); })
            .finally(function () { if (btn) btn.disabled = false; });
    }

    /* Delete Campaign */
    function confirmDeleteCampaign(id) {
        if (!confirm(t('confirm_campaign_delete', 'Are you sure you want to delete this campaign?'))) return;
        deleteCampaign(id);
    }

    function deleteCampaign(id) {
        var url = (CFG.apiBase || '/api') + '/ad_campaigns' + (CFG.tenantId ? '?tenant_id=' + CFG.tenantId : '');
        fetch(url, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success || json.status === 'success') {
                    showNotification(t('campaign_deleted', 'Campaign deleted successfully'), 'success');
                    campaignCache = [];
                    loadCampaigns({ page: campaignsPage, filters: campaignsFilters });
                    refreshCampaignFilter();
                } else {
                    showNotification(json.message || t('error_campaign_delete', 'Failed to delete campaign'), 'error');
                }
            })
            .catch(function () { showNotification(t('error_campaign_delete', 'Failed to delete campaign'), 'error'); });
    }

    /* Campaign filter */
    function applyCampaignFilters() {
        campaignsPage = 1;
        var getEl = function (id) { return document.getElementById(id); };
        campaignsFilters = {
            search:        (getEl('filterCampaignSearch')       ? getEl('filterCampaignSearch').value.trim()       : ''),
            status:        (getEl('filterCampaignStatus')       ? getEl('filterCampaignStatus').value              : ''),
            pricing_model: (getEl('filterCampaignPricingModel') ? getEl('filterCampaignPricingModel').value        : ''),
        };
        loadCampaigns({ page: campaignsPage, filters: campaignsFilters });
    }

    function clearCampaignFilters() {
        ['filterCampaignSearch', 'filterCampaignStatus', 'filterCampaignPricingModel'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        campaignsFilters = {};
        campaignsPage    = 1;
        loadCampaigns({ page: 1, filters: {} });
    }

    /* ══════════════════════════════════════════════
     * ADS (AD UNITS)
     * ══════════════════════════════════════════ */

    function refreshCampaignFilter() {
        var campFilter = document.getElementById('filterCampaign');
        if (!campFilter) return;
        loadCampaignsData(function (campaigns) {
            var html = '<option value="">' + esc(t('filter.all_campaigns', 'All Campaigns')) + '</option>';
            campaigns.forEach(function (c) {
                html += '<option value="' + esc(c.id) + '">' + esc(c.name || ('#' + c.id)) + '</option>';
            });
            campFilter.innerHTML = html;
        }, true);
    }

    function populateCampaignSelect(selectEl, selectedId) {
        loadCampaignsData(function (campaigns) {
            var html = '<option value="">' + esc(t('form.select_campaign', '-- Select Campaign --')) + '</option>';
            campaigns.forEach(function (c) {
                var sel = (selectedId && String(c.id) === String(selectedId)) ? ' selected' : '';
                html += '<option value="' + esc(c.id) + '"' + sel + '>' + esc(c.name || ('#' + c.id)) + '</option>';
            });
            selectEl.innerHTML = html;
        });
    }

    function loadAds(params) {
        params = params || {};
        var page    = params.page    || adsPage;
        var filters = params.filters || adsFilters;
        var offset  = (page - 1) * PER_PAGE;

        var url = (CFG.apiBase || '/api') + '/ads?limit=' + PER_PAGE + '&offset=' + offset + '&order_by=id&order_dir=DESC';
        if (CFG.tenantId) url += '&tenant_id=' + CFG.tenantId;
        if (filters.status)      url += '&status='      + encodeURIComponent(filters.status);
        if (filters.target_type) url += '&target_type=' + encodeURIComponent(filters.target_type);
        if (filters.campaign_id) url += '&campaign_id=' + encodeURIComponent(filters.campaign_id);
        if (filters.search)      url += '&search='      + encodeURIComponent(filters.search);

        var tbody = document.getElementById('adsTableBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="11" class="text-center">...</td></tr>';

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                var items = (json.data && json.data.items) ? json.data.items : [];
                var total = (json.data && json.data.meta) ? (json.data.meta.total || 0) : 0;
                renderAdsTable(items);
                renderAdsPagination(page, total);
                updateAdsPaginationInfo(page, items.length, total);
            })
            .catch(function () {
                showNotification(t('error_load', 'Failed to load ads'), 'error');
                if (tbody) tbody.innerHTML = '<tr><td colspan="11" class="text-center">' + esc(t('table.no_records', 'No ads found')) + '</td></tr>';
            });
    }

    function renderAdsTable(items) {
        var tbody = document.getElementById('adsTableBody');
        if (!tbody) return;
        if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="11" class="text-center">' + esc(t('table.no_records', 'No ads found')) + '</td></tr>';
            return;
        }
        var html = '';
        items.forEach(function (ad) {
            var thumb = ad.main_image_url || ad.image_url || '';
            var imgCell = thumb
                ? '<img src="' + esc(thumb) + '" alt="" class="ad-thumb-img">'
                : '<span class="ad-no-img">📢</span>';
            html += '<tr>';
            html += '<td>' + imgCell + '</td>';
            html += '<td>' + esc(ad.id) + '</td>';
            html += '<td>' + esc(ad.campaign_name || ad.campaign_id) + '</td>';
            html += '<td>' + esc(t('target_type.' + ad.target_type, ad.target_type)) + '</td>';
            html += '<td><span class="truncate" title="' + esc(ad.target_value) + '">' + esc(ad.target_value || '-') + '</span></td>';
            html += '<td>' + statusBadge(ad.status) + '</td>';
            var adViews  = (ad.views_total  != null) ? parseInt(ad.views_total,  10) : 0;
            var adClicks = (ad.clicks_total != null) ? parseInt(ad.clicks_total, 10) : 0;
            var adCtr    = adViews > 0 ? (adClicks / adViews * 100).toFixed(2) + '%' : '0%';
            html += '<td>' + esc(adViews)  + '</td>';
            html += '<td>' + esc(adClicks) + '</td>';
            html += '<td><span class="ctr-value">' + esc(adCtr) + '</span></td>';
            html += '<td>' + esc((ad.created_at || '').replace('T', ' ').substring(0, 16)) + '</td>';
            html += '<td><div class="row-actions">';
            if (CAN_EDIT) {
                html += '<button class="btn btn-secondary btn-sm btn-edit-ad" data-id="' + esc(ad.id) + '">' + esc(t('table.edit', 'Edit')) + '</button>';
            }
            if (CAN_DELETE) {
                html += '<button class="btn btn-danger btn-sm btn-delete-ad" data-id="' + esc(ad.id) + '">' + esc(t('table.delete', 'Delete')) + '</button>';
            }
            html += '</div></td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;

        tbody.querySelectorAll('.btn-edit-ad').forEach(function (btn) {
            btn.addEventListener('click', function () { openEditAdModal(parseInt(btn.dataset.id, 10)); });
        });
        tbody.querySelectorAll('.btn-delete-ad').forEach(function (btn) {
            btn.addEventListener('click', function () { confirmDeleteAd(parseInt(btn.dataset.id, 10)); });
        });
    }

    function renderAdsPagination(page, total) {
        var totalPages = Math.ceil(total / PER_PAGE) || 1;
        var pg = document.getElementById('adsPagination');
        if (!pg) return;
        var html = '';
        html += '<button class="page-btn" ' + (page <= 1 ? 'disabled' : '') + ' data-page="' + (page - 1) + '">' + esc(t('pagination.prev', 'Prev')) + '</button>';
        var start = Math.max(1, page - 2);
        var end   = Math.min(totalPages, start + 4);
        if (end - start < 4) start = Math.max(1, end - 4);
        for (var i = start; i <= end; i++) {
            html += '<button class="page-btn' + (i === page ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }
        html += '<button class="page-btn" ' + (page >= totalPages ? 'disabled' : '') + ' data-page="' + (page + 1) + '">' + esc(t('pagination.next', 'Next')) + '</button>';
        pg.innerHTML = html;
        pg.querySelectorAll('.page-btn:not([disabled])').forEach(function (btn) {
            btn.addEventListener('click', function () {
                adsPage = parseInt(btn.dataset.page, 10);
                loadAds({ page: adsPage, filters: adsFilters });
            });
        });
    }

    function updateAdsPaginationInfo(page, count, total) {
        var el = document.getElementById('adsPaginationInfo');
        if (!el) return;
        var from = total === 0 ? 0 : (page - 1) * PER_PAGE + 1;
        var to   = (page - 1) * PER_PAGE + count;
        el.textContent = from + '-' + to + ' ' + t('pagination.of', 'of') + ' ' + total;
    }

    function openAddAdModal() {
        reloadConfig();
        var form = document.getElementById('adForm');
        if (form) form.reset();
        var idEl = document.getElementById('adId');
        if (idEl) idEl.value = '';
        var titleEl = document.getElementById('adModalTitle');
        if (titleEl) titleEl.textContent = t('modal.add_title', 'Add Ad Unit');

        var campSel = document.getElementById('adCampaignId');
        if (campSel) populateCampaignSelect(campSel, null);

        // Reset images & translations
        adSelectedImages = [];
        renderAdImagesPreview();
        var transList = document.getElementById('adTranslationsList');
        if (transList) transList.innerHTML = '<p class="ad-trans-empty">' + esc(t('translations.no_records', 'No translations added yet.')) + '</p>';

        // Clear English translation fields
        var enTitleEl = document.getElementById('adEnTitle');
        var enDescEl  = document.getElementById('adEnDescription');
        if (enTitleEl) enTitleEl.value = '';
        if (enDescEl)  enDescEl.value  = '';

        // Reset image type selector
        var imgTypeSel = document.getElementById('adImageType');
        if (imgTypeSel) imgTypeSel.value = '';

        // Pre-select English as default translation language
        var langEl = document.getElementById('adTransLang');
        if (langEl) langEl.value = 'en';

        // Switch to basic tab
        switchAdModalTab('basic');

        openModal('adModal');
    }

    function openEditAdModal(id) {
        reloadConfig();
        var url = (CFG.apiBase || '/api') + '/ads?id=' + id;
        if (CFG.tenantId) url += '&tenant_id=' + CFG.tenantId;
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                var ad = json.data || json;
                if (!ad || !ad.id) { showNotification(t('error_load', 'Failed to load ad'), 'error'); return; }

                var idEl = document.getElementById('adId');
                if (idEl) idEl.value = ad.id;

                var titleEl = document.getElementById('adModalTitle');
                if (titleEl) titleEl.textContent = t('modal.edit_title', 'Edit Ad Unit');

                var campSel = document.getElementById('adCampaignId');
                if (campSel) populateCampaignSelect(campSel, ad.campaign_id);

                var setVal = function (elId, val) { var el = document.getElementById(elId); if (el) el.value = val || ''; };
                setVal('adTargetType',  ad.target_type);
                setVal('adTargetValue', ad.target_value);
                setVal('adStatus',      ad.status);

                // Reset then load images & translations
                adSelectedImages = [];
                renderAdImagesPreview();
                // Reset image type selector to default (thumbnail) and load those images
                var imgTypeSel = document.getElementById('adImageType');
                if (imgTypeSel) imgTypeSel.value = '20';
                loadAdImages(ad.id, 20);
                loadAdTranslations(ad.id);

                // Load English translation for the Basic tab fields
                var enUrl = (CFG.translationsApi || '/api/ad_translations') + '?ad_id=' + ad.id + '&language_code=en';
                fetch(enUrl, { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (json) {
                        var items = (json.data && json.data.items) ? json.data.items : (Array.isArray(json.data) ? json.data : []);
                        var enTrans = null;
                        items.forEach(function (tr) { if (tr.language_code === 'en') enTrans = tr; });
                        var enTitleEl = document.getElementById('adEnTitle');
                        var enDescEl  = document.getElementById('adEnDescription');
                        if (enTitleEl) enTitleEl.value = enTrans ? (enTrans.title       || '') : '';
                        if (enDescEl)  enDescEl.value  = enTrans ? (enTrans.description || '') : '';
                    })
                    .catch(function () {});

                // Switch to basic tab
                switchAdModalTab('basic');

                openModal('adModal');
            })
            .catch(function () { showNotification(t('error_load', 'Failed to load ad'), 'error'); });
    }

    function saveAd() {
        var idEl = document.getElementById('adId');
        var id   = idEl ? parseInt(idEl.value, 10) : 0;

        // Validate English title (required)
        var enTitleEl  = document.getElementById('adEnTitle');
        var enDescEl   = document.getElementById('adEnDescription');
        var enTitleVal = enTitleEl ? enTitleEl.value.trim() : '';
        if (!enTitleVal) {
            showNotification(t('en_translation_required', 'English title is required'), 'warning');
            switchAdModalTab('basic');
            return;
        }

        var getVal = function (elId) { var el = document.getElementById(elId); return el ? el.value.trim() : ''; };

        var data = {
            campaign_id:  parseInt(getVal('adCampaignId'), 10) || 0,
            target_type:  getVal('adTargetType'),
            target_value: getVal('adTargetValue'),
            status:       getVal('adStatus'),
        };

        if (id > 0) data.id = id;

        var url    = (CFG.apiBase || '/api') + '/ads' + (CFG.tenantId ? '?tenant_id=' + CFG.tenantId : '');
        var method = id > 0 ? 'PUT' : 'POST';

        var btn = document.getElementById('adSaveBtn');
        if (btn) btn.disabled = true;

        fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success || json.status === 'success') {
                    // Auto-save English translation
                    var savedId = (json.data && json.data.id) ? json.data.id : (id > 0 ? id : 0);
                    if (savedId && enTitleVal) {
                        var enDescVal = enDescEl ? enDescEl.value.trim() : '';
                        var transData = { ad_id: savedId, language_code: 'en', title: enTitleVal, description: enDescVal };
                        var transUrl  = (CFG.translationsApi || '/api/ad_translations');
                        if (CFG.tenantId) transUrl += '?tenant_id=' + CFG.tenantId;
                        fetch(transUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(transData)
                        }).catch(function () {});
                    }
                    closeModal('adModal');
                    showNotification(t('saved', 'Ad saved successfully'), 'success');
                    loadAds({ page: adsPage, filters: adsFilters });
                } else {
                    var msg = json.message || json.error || t('error_save', 'Failed to save ad');
                    showNotification(msg, 'error');
                }
            })
            .catch(function () { showNotification(t('error_save', 'Failed to save ad'), 'error'); })
            .finally(function () { if (btn) btn.disabled = false; });
    }

    /* ══════════════════════════════════════════════
     * AD MODAL TABS
     * ══════════════════════════════════════════ */
    function switchAdModalTab(tabName) {
        document.querySelectorAll('.ad-modal-tab-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.modalTab === tabName);
        });
        document.querySelectorAll('.ad-modal-tab-content').forEach(function (panel) {
            panel.style.display = (panel.id === 'adTab-' + tabName) ? '' : 'none';
        });
        // Load all images when switching to the Images tab (if ad is saved)
        if (tabName === 'images') {
            var idEl = document.getElementById('adId');
            var adId = idEl ? parseInt(idEl.value, 10) : 0;
            if (adId) loadAllAdImages(adId);
        }
    }

    /* ══════════════════════════════════════════════
     * AD IMAGES
     * ══════════════════════════════════════════ */
    function loadAdImages(adId, imgTypeId) {
        imgTypeId = imgTypeId || (CFG.adImageTypeId || 20);
        var url = (CFG.imagesApi || '/api/images') + '/by_owner?owner_id=' + adId + '&image_type_id=' + imgTypeId;
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                var imgs = Array.isArray(json.data) ? json.data : (json.data && Array.isArray(json.data.items) ? json.data.items : []);
                adSelectedImages = imgs;
                renderAdImagesPreview();
            })
            .catch(function () {
                showNotification(t('error_images_load', 'Failed to load images'), 'warning');
            });
    }

    function renderAdImagesPreview() {
        var container = document.getElementById('adImagesPreview');
        if (!container) return;
        if (!adSelectedImages || adSelectedImages.length === 0) {
            container.innerHTML = '<p style="color:var(--text-secondary,#94a3b8); font-size:0.85rem;">' + esc(t('images.no_images', 'No images yet.')) + '</p>';
            return;
        }
        var hasGroups = adSelectedImages.some(function (img) { return img.image_type_name; });
        var html = '';
        if (hasGroups) {
            var groups = {};
            var groupOrder = [];
            adSelectedImages.forEach(function (img) {
                var grp = img.image_type_name || t('images.label', 'Ad Images');
                if (!groups[grp]) { groups[grp] = []; groupOrder.push(grp); }
                groups[grp].push(img);
            });
            groupOrder.forEach(function (grp) {
                html += '<div class="ad-images-group" style="margin-bottom:1rem;">';
                html += '<h5 style="font-size:0.82rem; color:var(--text-secondary,#94a3b8); margin:0 0 0.35rem; font-weight:600;">' + esc(grp) + '</h5>';
                html += '<div style="display:flex; flex-wrap:wrap; gap:8px;">';
                groups[grp].forEach(function (img) {
                    var idx = adSelectedImages.indexOf(img);
                    var src = img.url || img.thumb_url || img.image_url || '';
                    html += '<div class="ad-image-item" data-index="' + idx + '">';
                    html += '<img src="' + esc(src) + '" alt="">';
                    html += '<button type="button" class="ad-image-remove" data-index="' + idx + '">&times;</button>';
                    html += '</div>';
                });
                html += '</div></div>';
            });
        } else {
            adSelectedImages.forEach(function (img, idx) {
                var src = img.url || img.thumb_url || img.image_url || '';
                html += '<div class="ad-image-item" data-index="' + idx + '">';
                html += '<img src="' + esc(src) + '" alt="">';
                html += '<button type="button" class="ad-image-remove" data-index="' + idx + '">&times;</button>';
                html += '</div>';
            });
        }
        container.innerHTML = html;
        container.querySelectorAll('.ad-image-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var i = parseInt(btn.dataset.index, 10);
                adSelectedImages.splice(i, 1);
                renderAdImagesPreview();
            });
        });
    }

    function loadAllAdImages(adId) {
        var typeIds   = [13, 14, 15, 16, 17, 18, 19, 20];
        var typeNames = {
            13: t('images.types.ad_homepage_banner', 'Homepage Banner'),
            14: t('images.types.ad_section_banner',  'Section Banner'),
            15: t('images.types.ad_square',           'Square Ad'),
            16: t('images.types.ad_store_banner',     'Store Banner'),
            17: t('images.types.ad_small',            'Small Ad'),
            18: t('images.types.ad_search_banner',    'Search Banner'),
            19: t('images.types.ad_mobile_banner',    'Mobile Banner'),
            20: t('images.types.ad_thumb',            'Thumbnail'),
        };
        var promises = typeIds.map(function (typeId) {
            var url = (CFG.imagesApi || '/api/images') + '/by_owner?owner_id=' + adId + '&image_type_id=' + typeId;
            return fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    var imgs = Array.isArray(json.data) ? json.data : (json.data && Array.isArray(json.data.items) ? json.data.items : []);
                    imgs.forEach(function (img) { img.image_type_name = typeNames[typeId] || ('Type ' + typeId); });
                    return imgs;
                })
                .catch(function () { return []; });
        });
        Promise.all(promises).then(function (results) {
            var all = [];
            results.forEach(function (arr) { arr.forEach(function (img) { all.push(img); }); });
            adSelectedImages = all;
            renderAdImagesPreview();
        });
    }

    function openAdMediaStudio() {
        var idEl = document.getElementById('adId');
        var adId = idEl ? parseInt(idEl.value, 10) : 0;
        if (!adId) {
            showNotification(t('images.save_first', 'Please save the ad first before adding images.'), 'warning');
            return;
        }
        var imgTypeSel = document.getElementById('adImageType');
        var imgTypeId  = imgTypeSel ? parseInt(imgTypeSel.value, 10) : 0;
        if (!imgTypeId) {
            showNotification(t('images.select_type_first', 'Please select an image type first.'), 'warning');
            return;
        }
        var overlay = document.getElementById('adMediaStudioModal');
        var frame   = document.getElementById('adMediaStudioFrame');
        if (!overlay || !frame) return;
        frame.src = '/admin/fragments/media_studio.php?embedded=1&tenant_id=' + encodeURIComponent(CFG.tenantId || '') +
                    '&lang=' + encodeURIComponent(CFG.lang || 'en') +
                    '&owner_id=' + adId +
                    '&image_type_id=' + imgTypeId;
        overlay.style.display = 'flex';
    }

    function closeAdMediaStudio() {
        var overlay = document.getElementById('adMediaStudioModal');
        var frame   = document.getElementById('adMediaStudioFrame');
        if (overlay) overlay.style.display = 'none';
        if (frame)   frame.src = 'about:blank';
    }

    /* ══════════════════════════════════════════════
     * AD TRANSLATIONS
     * ══════════════════════════════════════════ */
    function loadAdTranslations(adId) {
        var url = (CFG.translationsApi || '/api/ad_translations') + '?ad_id=' + adId + '&limit=100';
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                var items = (json.data && json.data.items) ? json.data.items : (Array.isArray(json.data) ? json.data : []);
                renderAdTranslationsList(items);
            })
            .catch(function () {
                showNotification(t('error_translations_load', 'Failed to load translations'), 'warning');
            });
    }

    function renderAdTranslationsList(items) {
        var container = document.getElementById('adTranslationsList');
        if (!container) return;
        if (!items || items.length === 0) {
            container.innerHTML = '<p class="ad-trans-empty">' + esc(t('translations.no_records', 'No translations added yet.')) + '</p>';
            return;
        }
        var html = '<table class="data-table ad-translations-table"><thead><tr>' +
            '<th>' + esc(t('translations.language', 'Language')) + '</th>' +
            '<th>' + esc(t('translations.ad_title', 'Title')) + '</th>' +
            '<th>' + esc(t('translations.description', 'Description')) + '</th>' +
            '<th>' + esc(t('table.actions', 'Actions')) + '</th>' +
            '</tr></thead><tbody>';
        items.forEach(function (tr) {
            html += '<tr>';
            html += '<td><strong>' + esc(tr.language_code) + '</strong></td>';
            html += '<td>' + esc(tr.title || '-') + '</td>';
            html += '<td>' + esc(tr.description ? tr.description.substring(0, 60) + (tr.description.length > 60 ? '…' : '') : '-') + '</td>';
            html += '<td><div class="row-actions">';
            if (CAN_EDIT) {
                html += '<button class="btn btn-secondary btn-sm btn-edit-trans" data-id="' + esc(tr.id) + '" data-lang="' + esc(tr.language_code) + '" data-title="' + esc(tr.title || '') + '" data-desc="' + esc(tr.description || '') + '">' + esc(t('table.edit', 'Edit')) + '</button>';
            }
            if (CAN_DELETE) {
                html += '<button class="btn btn-danger btn-sm btn-delete-trans" data-id="' + esc(tr.id) + '">' + esc(t('table.delete', 'Delete')) + '</button>';
            }
            html += '</div></td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
        container.innerHTML = html;

        container.querySelectorAll('.btn-edit-trans').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var langEl  = document.getElementById('adTransLang');
                var titleEl = document.getElementById('adTransTitle');
                var descEl  = document.getElementById('adTransDesc');
                if (langEl)  langEl.value  = btn.dataset.lang  || '';
                if (titleEl) titleEl.value = btn.dataset.title || '';
                if (descEl)  descEl.value  = btn.dataset.desc  || '';
                // Store the translation ID for updating
                var addBtn = document.getElementById('btnAddAdTranslation');
                if (addBtn) addBtn.dataset.editId = btn.dataset.id;
            });
        });
        container.querySelectorAll('.btn-delete-trans').forEach(function (btn) {
            btn.addEventListener('click', function () { deleteAdTranslation(parseInt(btn.dataset.id, 10)); });
        });
    }

    function saveAdTranslation() {
        var idEl   = document.getElementById('adId');
        var adId   = idEl ? parseInt(idEl.value, 10) : 0;
        var langEl = document.getElementById('adTransLang');
        var titEl  = document.getElementById('adTransTitle');
        var desEl  = document.getElementById('adTransDesc');
        var addBtn = document.getElementById('btnAddAdTranslation');

        var langCode = langEl ? langEl.value.trim() : '';
        var title    = titEl  ? titEl.value.trim()  : '';
        var desc     = desEl  ? desEl.value.trim()  : '';
        var editId   = addBtn && addBtn.dataset.editId ? parseInt(addBtn.dataset.editId, 10) : 0;

        if (!langCode) { showNotification(t('translations.select_language', '-- Select Language --'), 'warning'); return; }
        if (!adId) {
            showNotification(t('images.save_first', 'Please save the ad first.'), 'warning');
            return;
        }

        var data = { ad_id: adId, language_code: langCode, title: title, description: desc };
        var method = 'POST';
        if (editId > 0) { data.id = editId; method = 'PUT'; }

        var url = (CFG.translationsApi || '/api/ad_translations');
        if (CFG.tenantId) url += '?tenant_id=' + CFG.tenantId;

        fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success || json.status === 'success') {
                    showNotification(t('translations.saved', 'Translation saved'), 'success');
                    // Clear inputs
                    if (langEl)  langEl.value  = '';
                    if (titEl)   titEl.value   = '';
                    if (desEl)   desEl.value   = '';
                    if (addBtn)  delete addBtn.dataset.editId;
                    loadAdTranslations(adId);
                } else {
                    showNotification(json.message || t('error_translation_save', 'Failed to save translation'), 'error');
                }
            })
            .catch(function () { showNotification(t('error_translation_save', 'Failed to save translation'), 'error'); });
    }

    function deleteAdTranslation(id) {
        if (!confirm(t('translations.confirm_delete', 'Delete this translation?'))) return;
        var url = (CFG.translationsApi || '/api/ad_translations');
        if (CFG.tenantId) url += '?tenant_id=' + CFG.tenantId;
        fetch(url, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success || json.status === 'success') {
                    showNotification(t('translations.deleted', 'Translation deleted'), 'success');
                    var idEl = document.getElementById('adId');
                    var adId = idEl ? parseInt(idEl.value, 10) : 0;
                    if (adId) loadAdTranslations(adId);
                } else {
                    showNotification(json.message || t('error_translation_delete', 'Failed to delete translation'), 'error');
                }
            })
            .catch(function () { showNotification(t('error_translation_delete', 'Failed to delete translation'), 'error'); });
    }

    function confirmDeleteAd(id) {
        if (!confirm(t('confirm_delete', 'Are you sure you want to delete this ad?'))) return;
        deleteAd(id);
    }

    function deleteAd(id) {
        var url = (CFG.apiBase || '/api') + '/ads' + (CFG.tenantId ? '?tenant_id=' + CFG.tenantId : '');
        fetch(url, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success || json.status === 'success') {
                    showNotification(t('deleted', 'Ad deleted successfully'), 'success');
                    loadAds({ page: adsPage, filters: adsFilters });
                } else {
                    showNotification(json.message || t('error_delete', 'Failed to delete ad'), 'error');
                }
            })
            .catch(function () { showNotification(t('error_delete', 'Failed to delete ad'), 'error'); });
    }

    function applyAdsFilters() {
        adsPage = 1;
        var getEl = function (id) { return document.getElementById(id); };
        adsFilters = {
            search:      (getEl('filterSearch')     ? getEl('filterSearch').value.trim()  : ''),
            status:      (getEl('filterStatus')     ? getEl('filterStatus').value         : ''),
            target_type: (getEl('filterTargetType') ? getEl('filterTargetType').value     : ''),
            campaign_id: (getEl('filterCampaign')   ? getEl('filterCampaign').value       : ''),
        };
        loadAds({ page: adsPage, filters: adsFilters });
    }

    function clearAdsFilters() {
        ['filterSearch', 'filterStatus', 'filterTargetType', 'filterCampaign'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        adsFilters = {};
        adsPage    = 1;
        loadAds({ page: 1, filters: {} });
    }

    /* ══════════════════════════════════════════════
     * PLACEMENTS
     * ══════════════════════════════════════════ */

    function loadPlacements(params) {
        params = params || {};
        var page    = params.page    || placementsPage;
        var filters = params.filters || placementsFilters;
        var offset  = (page - 1) * PER_PAGE;

        var url = (CFG.placementsApi || '/api/ad_placements') + '?limit=' + PER_PAGE + '&offset=' + offset + '&order_by=id&order_dir=DESC';
        if (CFG.tenantId) url += '&tenant_id=' + CFG.tenantId;
        if (filters.status) url += '&status=' + encodeURIComponent(filters.status);
        if (filters.search) url += '&search=' + encodeURIComponent(filters.search);

        var tbody = document.getElementById('placementsTableBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center">...</td></tr>';

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                var items = (json.data && json.data.items) ? json.data.items : [];
                var total = (json.data && json.data.meta) ? (json.data.meta.total || 0) : 0;
                renderPlacementsTable(items);
                renderPlacementsPagination(page, total);
                updatePlacementsPaginationInfo(page, items.length, total);
            })
            .catch(function () {
                showNotification(t('error_placements_load', 'Failed to load placements'), 'error');
                if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center">' + esc(t('placements_table.no_records', 'No placements found')) + '</td></tr>';
            });
    }

    function renderPlacementsTable(items) {
        var tbody = document.getElementById('placementsTableBody');
        if (!tbody) return;
        if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">' + esc(t('placements_table.no_records', 'No placements found')) + '</td></tr>';
            return;
        }
        var html = '';
        items.forEach(function (p) {
            html += '<tr>';
            html += '<td>' + esc(p.id) + '</td>';
            html += '<td><strong>' + esc(p.name) + '</strong></td>';
            html += '<td><code>' + esc(p.placement_key) + '</code></td>';
            html += '<td>' + statusBadge(p.status) + '</td>';
            html += '<td>' + esc((p.created_at || '').replace('T', ' ').substring(0, 16)) + '</td>';
            html += '<td><div class="row-actions">';
            if (CAN_EDIT) {
                html += '<button class="btn btn-secondary btn-sm btn-edit-placement" data-id="' + esc(p.id) + '">' + esc(t('table.edit', 'Edit')) + '</button>';
            }
            html += '<button class="btn btn-secondary btn-sm btn-view-placement-items" data-id="' + esc(p.id) + '" data-name="' + esc(p.name) + '">' + esc(t('placement_items_title', 'Items')) + '</button>';
            if (CAN_DELETE) {
                html += '<button class="btn btn-danger btn-sm btn-delete-placement" data-id="' + esc(p.id) + '">' + esc(t('table.delete', 'Delete')) + '</button>';
            }
            html += '</div></td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;

        tbody.querySelectorAll('.btn-edit-placement').forEach(function (btn) {
            btn.addEventListener('click', function () { openEditPlacementModal(parseInt(btn.dataset.id, 10)); });
        });
        tbody.querySelectorAll('.btn-view-placement-items').forEach(function (btn) {
            btn.addEventListener('click', function () {
                viewPlacementItems(parseInt(btn.dataset.id, 10), btn.dataset.name || ('#' + btn.dataset.id));
            });
        });
        tbody.querySelectorAll('.btn-delete-placement').forEach(function (btn) {
            btn.addEventListener('click', function () { confirmDeletePlacement(parseInt(btn.dataset.id, 10)); });
        });
    }

    function renderPlacementsPagination(page, total) {
        var totalPages = Math.ceil(total / PER_PAGE) || 1;
        var pg = document.getElementById('placementsPagination');
        if (!pg) return;
        var html = '';
        html += '<button class="page-btn" ' + (page <= 1 ? 'disabled' : '') + ' data-page="' + (page - 1) + '">' + esc(t('pagination.prev', 'Prev')) + '</button>';
        var start = Math.max(1, page - 2);
        var end   = Math.min(totalPages, start + 4);
        if (end - start < 4) start = Math.max(1, end - 4);
        for (var i = start; i <= end; i++) {
            html += '<button class="page-btn' + (i === page ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }
        html += '<button class="page-btn" ' + (page >= totalPages ? 'disabled' : '') + ' data-page="' + (page + 1) + '">' + esc(t('pagination.next', 'Next')) + '</button>';
        pg.innerHTML = html;
        pg.querySelectorAll('.page-btn:not([disabled])').forEach(function (btn) {
            btn.addEventListener('click', function () {
                placementsPage = parseInt(btn.dataset.page, 10);
                loadPlacements({ page: placementsPage, filters: placementsFilters });
            });
        });
    }

    function updatePlacementsPaginationInfo(page, count, total) {
        var el = document.getElementById('placementsPaginationInfo');
        if (!el) return;
        var from = total === 0 ? 0 : (page - 1) * PER_PAGE + 1;
        var to   = (page - 1) * PER_PAGE + count;
        el.textContent = from + '-' + to + ' ' + t('pagination.of', 'of') + ' ' + total;
    }

    function openAddPlacementModal() {
        reloadConfig();
        var form = document.getElementById('placementForm');
        if (form) form.reset();
        var idEl = document.getElementById('placementId');
        if (idEl) idEl.value = '';
        var titleEl = document.getElementById('placementModalTitle');
        if (titleEl) titleEl.textContent = t('add_placement', 'Add Placement');
        openModal('placementModal');
    }

    function openEditPlacementModal(id) {
        reloadConfig();
        var url = (CFG.placementsApi || '/api/ad_placements') + '?id=' + id;
        if (CFG.tenantId) url += '&tenant_id=' + CFG.tenantId;
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                var p = json.data || json;
                if (!p || !p.id) { showNotification(t('error_placements_load', 'Failed to load placement'), 'error'); return; }
                var setVal = function (elId, val) { var el = document.getElementById(elId); if (el) el.value = val || ''; };
                setVal('placementId',          p.id);
                setVal('placementName',        p.name);
                setVal('placementKey',         p.placement_key);
                setVal('placementDescription', p.description);
                setVal('placementCode',        p.code);
                setVal('placementPage',        p.page);
                setVal('placementWidth',       p.width);
                setVal('placementHeight',      p.height);
                setVal('placementMaxAds',      p.max_ads !== undefined && p.max_ads !== null ? p.max_ads : 1);
                setVal('placementStatus',      p.status);
                var titleEl = document.getElementById('placementModalTitle');
                if (titleEl) titleEl.textContent = t('edit_placement', 'Edit Placement');
                openModal('placementModal');
            })
            .catch(function () { showNotification(t('error_placements_load', 'Failed to load placement'), 'error'); });
    }

    function savePlacement() {
        var idEl = document.getElementById('placementId');
        var id   = idEl ? parseInt(idEl.value, 10) : 0;
        var getVal = function (elId) { var el = document.getElementById(elId); return el ? el.value.trim() : ''; };

        var name = getVal('placementName');
        var key  = getVal('placementKey');
        if (!name) { showNotification(t('placement_form.name', 'Placement Name') + ' is required', 'warning'); return; }
        if (!key)  { showNotification(t('placement_form.placement_key', 'Placement Key') + ' is required', 'warning'); return; }

        var data = {
            name:          name,
            placement_key: key,
            description:   getVal('placementDescription'),
            code:          getVal('placementCode') || null,
            page:          getVal('placementPage') || null,
            width:         getVal('placementWidth') ? parseInt(getVal('placementWidth'), 10) : null,
            height:        getVal('placementHeight') ? parseInt(getVal('placementHeight'), 10) : null,
            max_ads:       getVal('placementMaxAds') ? parseInt(getVal('placementMaxAds'), 10) : 1,
            status:        getVal('placementStatus') || 'active',
        };
        if (id > 0) data.id = id;

        var url    = (CFG.placementsApi || '/api/ad_placements') + (CFG.tenantId ? '?tenant_id=' + CFG.tenantId : '');
        var method = id > 0 ? 'PUT' : 'POST';
        var btn    = document.getElementById('placementSaveBtn');
        if (btn) btn.disabled = true;

        fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success || json.status === 'success') {
                    closeModal('placementModal');
                    showNotification(t('placement_saved', 'Placement saved successfully'), 'success');
                    loadPlacements({ page: placementsPage, filters: placementsFilters });
                } else {
                    showNotification(json.message || t('error_placement_save', 'Failed to save placement'), 'error');
                }
            })
            .catch(function () { showNotification(t('error_placement_save', 'Failed to save placement'), 'error'); })
            .finally(function () { if (btn) btn.disabled = false; });
    }

    function confirmDeletePlacement(id) {
        if (!confirm(t('confirm_placement_delete', 'Are you sure you want to delete this placement?'))) return;
        deletePlacement(id);
    }

    function deletePlacement(id) {
        var url = (CFG.placementsApi || '/api/ad_placements') + (CFG.tenantId ? '?tenant_id=' + CFG.tenantId : '');
        fetch(url, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success || json.status === 'success') {
                    showNotification(t('placement_deleted', 'Placement deleted successfully'), 'success');
                    loadPlacements({ page: placementsPage, filters: placementsFilters });
                    if (currentPlacementId === id) {
                        currentPlacementId = null;
                        var section = document.getElementById('placementItemsSection');
                        if (section) section.style.display = 'none';
                    }
                } else {
                    showNotification(json.message || t('error_placement_delete', 'Failed to delete placement'), 'error');
                }
            })
            .catch(function () { showNotification(t('error_placement_delete', 'Failed to delete placement'), 'error'); });
    }

    function applyPlacementsFilters() {
        placementsPage = 1;
        var getEl = function (id) { return document.getElementById(id); };
        placementsFilters = {
            search: (getEl('filterPlacementsSearch') ? getEl('filterPlacementsSearch').value.trim() : ''),
            status: (getEl('filterPlacementStatus')  ? getEl('filterPlacementStatus').value         : ''),
        };
        loadPlacements({ page: placementsPage, filters: placementsFilters });
    }

    function clearPlacementsFilters() {
        ['filterPlacementsSearch', 'filterPlacementStatus'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        placementsFilters = {};
        placementsPage    = 1;
        loadPlacements({ page: 1, filters: {} });
    }

    /* ══════════════════════════════════════════════
     * PLACEMENT ITEMS
     * ══════════════════════════════════════════ */

    function viewPlacementItems(placementId, placementName) {
        currentPlacementId = placementId;
        var section = document.getElementById('placementItemsSection');
        if (section) section.style.display = '';
        var titleEl = document.getElementById('placementItemsTitle');
        if (titleEl) titleEl.textContent = t('placement_items_title', 'Placement Items') + ': ' + placementName;
        placementItemsPage = 1;
        loadPlacementItems(placementId, 1);
        if (section) section.scrollIntoView({ behavior: 'smooth' });
    }

    function loadPlacementItems(placementId, page) {
        page = page || placementItemsPage;
        var offset = (page - 1) * PER_PAGE;
        var url = (CFG.placementItemsApi || '/api/ad_placement_items') + '?placement_id=' + placementId +
                  '&limit=' + PER_PAGE + '&offset=' + offset + '&order_by=priority&order_dir=ASC';
        if (CFG.tenantId) url += '&tenant_id=' + CFG.tenantId;

        var tbody = document.getElementById('placementItemsTableBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center">...</td></tr>';

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                var items = (json.data && json.data.items) ? json.data.items : (Array.isArray(json.data) ? json.data : []);
                renderPlacementItemsTable(items, placementId);
            })
            .catch(function () {
                showNotification(t('error_placement_items_load', 'Failed to load placement items'), 'error');
                if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center">' + esc(t('placement_items_table.no_records', 'No placement items found')) + '</td></tr>';
            });
    }

    function renderPlacementItemsTable(items, placementId) {
        var tbody = document.getElementById('placementItemsTableBody');
        if (!tbody) return;
        if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">' + esc(t('placement_items_table.no_records', 'No placement items found')) + '</td></tr>';
            return;
        }
        var html = '';
        items.forEach(function (item) {
            html += '<tr>';
            html += '<td>' + esc(item.id) + '</td>';
            html += '<td>' + esc(item.ad_title || ('#' + item.ad_id) || '-') + '</td>';
            html += '<td>' + esc(item.priority != null ? item.priority : 1) + '</td>';
            html += '<td>' + esc(item.weight   != null ? item.weight   : 1) + '</td>';
            html += '<td>' + esc(item.start_date ? item.start_date.substring(0, 10) : '-') + '</td>';
            html += '<td>' + esc(item.end_date   ? item.end_date.substring(0, 10)   : '-') + '</td>';
            html += '<td><div class="row-actions">';
            if (CAN_EDIT) {
                html += '<button class="btn btn-secondary btn-sm btn-edit-placement-item" data-id="' + esc(item.id) + '">' + esc(t('table.edit', 'Edit')) + '</button>';
            }
            if (CAN_DELETE) {
                html += '<button class="btn btn-danger btn-sm btn-delete-placement-item" data-id="' + esc(item.id) + '">' + esc(t('table.delete', 'Delete')) + '</button>';
            }
            html += '</div></td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;

        tbody.querySelectorAll('.btn-edit-placement-item').forEach(function (btn) {
            btn.addEventListener('click', function () { openEditPlacementItemModal(parseInt(btn.dataset.id, 10)); });
        });
        tbody.querySelectorAll('.btn-delete-placement-item').forEach(function (btn) {
            btn.addEventListener('click', function () { confirmDeletePlacementItem(parseInt(btn.dataset.id, 10)); });
        });
    }

    function openAddPlacementItemModal(placementId) {
        reloadConfig();
        var form = document.getElementById('placementItemForm');
        if (form) form.reset();
        var idEl = document.getElementById('placementItemId');
        if (idEl) idEl.value = '';
        var pidEl = document.getElementById('placementItemPlacementId');
        if (pidEl) pidEl.value = placementId || (currentPlacementId || '');
        var priorityEl = document.getElementById('placementItemPriority');
        if (priorityEl) priorityEl.value = '1';
        var weightEl = document.getElementById('placementItemWeight');
        if (weightEl) weightEl.value = '1';
        var titleEl = document.getElementById('placementItemModalTitle');
        if (titleEl) titleEl.textContent = t('add_placement_item', 'Add Item');
        var adSel = document.getElementById('placementItemAdId');
        if (adSel) populateAdSelectForPlacementItem(adSel, null);
        openModal('placementItemModal');
    }

    function openEditPlacementItemModal(id) {
        reloadConfig();
        var url = (CFG.placementItemsApi || '/api/ad_placement_items') + '?id=' + id;
        if (CFG.tenantId) url += '&tenant_id=' + CFG.tenantId;
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                var item = json.data || json;
                if (!item || !item.id) { showNotification(t('error_placement_items_load', 'Failed to load placement item'), 'error'); return; }
                var setVal = function (elId, val) { var el = document.getElementById(elId); if (el) el.value = val || ''; };
                setVal('placementItemId',          item.id);
                setVal('placementItemPlacementId', item.placement_id);
                setVal('placementItemPriority',    item.priority != null ? item.priority : 1);
                setVal('placementItemWeight',      item.weight   != null ? item.weight   : 1);
                setVal('placementItemStartDate',   item.start_date ? item.start_date.substring(0, 10) : '');
                setVal('placementItemEndDate',     item.end_date   ? item.end_date.substring(0, 10)   : '');
                var titleEl = document.getElementById('placementItemModalTitle');
                if (titleEl) titleEl.textContent = t('edit_placement_item', 'Edit Item');
                var adSel = document.getElementById('placementItemAdId');
                if (adSel) populateAdSelectForPlacementItem(adSel, item.ad_id);
                openModal('placementItemModal');
            })
            .catch(function () { showNotification(t('error_placement_items_load', 'Failed to load placement item'), 'error'); });
    }

    function savePlacementItem() {
        var idEl  = document.getElementById('placementItemId');
        var id    = idEl ? parseInt(idEl.value, 10) : 0;
        var pidEl = document.getElementById('placementItemPlacementId');
        var pid   = pidEl ? parseInt(pidEl.value, 10) : 0;
        var getVal = function (elId) { var el = document.getElementById(elId); return el ? el.value.trim() : ''; };

        var adId = parseInt(getVal('placementItemAdId'), 10) || 0;
        if (!adId) { showNotification(t('placement_item_form.ad_id', 'Ad Unit') + ' is required', 'warning'); return; }

        var data = {
            placement_id: pid,
            ad_id:        adId,
            priority:     parseInt(getVal('placementItemPriority'), 10) || 1,
            weight:       parseInt(getVal('placementItemWeight'),   10) || 1,
            start_date:   getVal('placementItemStartDate') || null,
            end_date:     getVal('placementItemEndDate')   || null,
        };
        if (id > 0) data.id = id;

        var url    = (CFG.placementItemsApi || '/api/ad_placement_items') + (CFG.tenantId ? '?tenant_id=' + CFG.tenantId : '');
        var method = id > 0 ? 'PUT' : 'POST';
        var btn    = document.getElementById('placementItemSaveBtn');
        if (btn) btn.disabled = true;

        fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success || json.status === 'success') {
                    closeModal('placementItemModal');
                    showNotification(t('placement_item_saved', 'Placement item saved'), 'success');
                    if (currentPlacementId) loadPlacementItems(currentPlacementId, placementItemsPage);
                } else {
                    showNotification(json.message || t('error_placement_item_save', 'Failed to save placement item'), 'error');
                }
            })
            .catch(function () { showNotification(t('error_placement_item_save', 'Failed to save placement item'), 'error'); })
            .finally(function () { if (btn) btn.disabled = false; });
    }

    function confirmDeletePlacementItem(id) {
        if (!confirm(t('confirm_placement_item_delete', 'Delete this placement item?'))) return;
        deletePlacementItem(id);
    }

    function deletePlacementItem(id) {
        var url = (CFG.placementItemsApi || '/api/ad_placement_items') + (CFG.tenantId ? '?tenant_id=' + CFG.tenantId : '');
        fetch(url, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success || json.status === 'success') {
                    showNotification(t('placement_item_deleted', 'Placement item deleted'), 'success');
                    if (currentPlacementId) loadPlacementItems(currentPlacementId, placementItemsPage);
                } else {
                    showNotification(json.message || t('error_placement_item_delete', 'Failed to delete placement item'), 'error');
                }
            })
            .catch(function () { showNotification(t('error_placement_item_delete', 'Failed to delete placement item'), 'error'); });
    }

    function populateAdSelectForPlacementItem(selectEl, selectedId) {
        var url = (CFG.apiBase || '/api') + '/ads?limit=500&order_by=id&order_dir=ASC';
        if (CFG.tenantId) url += '&tenant_id=' + CFG.tenantId;
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                var ads  = (json.data && json.data.items) ? json.data.items : [];
                var html = '<option value="">' + esc(t('placement_item_form.select_ad', '-- Select Ad --')) + '</option>';
                ads.forEach(function (ad) {
                    var label = '#' + ad.id + (ad.campaign_name ? ' - ' + ad.campaign_name : '') + (ad.target_value ? ' (' + String(ad.target_value).substring(0, 40) + ')' : '');
                    var sel   = (selectedId && String(ad.id) === String(selectedId)) ? ' selected' : '';
                    html += '<option value="' + esc(ad.id) + '"' + sel + '>' + esc(label) + '</option>';
                });
                selectEl.innerHTML = html;
            })
            .catch(function () {
                selectEl.innerHTML = '<option value="">' + esc(t('placement_item_form.select_ad', '-- Select Ad --')) + '</option>';
            });
    }

    /* ──────────────────────────────────────────────
     * Wire DOM events
     * ──────────────────────────────────────────── */
    function bindEvents() {
        var on = function (id, evt, fn) {
            var el = document.getElementById(id);
            if (el) el.addEventListener(evt, fn);
        };

        // Tab switching
        document.querySelectorAll('.ads-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { switchTab(btn.dataset.tab); });
        });

        // Ad modal tab switching
        document.querySelectorAll('.ad-modal-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { switchAdModalTab(btn.dataset.modalTab); });
        });

        // Campaign events
        on('btnAddCampaign',          'click', openAddCampaignModal);
        on('btnCampaignFilter',       'click', applyCampaignFilters);
        on('btnClearCampaignFilters', 'click', clearCampaignFilters);
        on('campaignSaveBtn',         'click', saveCampaign);

        var campSearch = document.getElementById('filterCampaignSearch');
        if (campSearch) campSearch.addEventListener('keydown', function (e) { if (e.key === 'Enter') applyCampaignFilters(); });

        // Ad events
        on('btnAddAd',              'click', openAddAdModal);
        on('btnFilter',             'click', applyAdsFilters);
        on('btnClearFilters',       'click', clearAdsFilters);
        on('adSaveBtn',             'click', saveAd);
        on('btnAddAdTranslation',   'click', saveAdTranslation);
        on('adSelectImageBtn',      'click', openAdMediaStudio);
        on('adMediaStudioClose',    'click', closeAdMediaStudio);

        // Placement events
        on('btnAddPlacement',          'click', openAddPlacementModal);
        on('placementSaveBtn',         'click', savePlacement);
        on('btnPlacementFilter',       'click', applyPlacementsFilters);
        on('btnClearPlacementFilters', 'click', clearPlacementsFilters);
        on('btnAddPlacementItemInline','click', function () { openAddPlacementItemModal(currentPlacementId); });
        on('placementItemSaveBtn',     'click', savePlacementItem);

        var placementSearch = document.getElementById('filterPlacementsSearch');
        if (placementSearch) placementSearch.addEventListener('keydown', function (e) { if (e.key === 'Enter') applyPlacementsFilters(); });

        // Reload images when image type selection changes
        var imgTypeSel = document.getElementById('adImageType');
        if (imgTypeSel) {
            imgTypeSel.addEventListener('change', function () {
                var idEl = document.getElementById('adId');
                var adId = idEl ? parseInt(idEl.value, 10) : 0;
                var typeId = parseInt(imgTypeSel.value, 10);
                if (adId && typeId) {
                    loadAdImages(adId, typeId);
                } else {
                    adSelectedImages = [];
                    renderAdImagesPreview();
                }
            });
        }

        var adSearch = document.getElementById('filterSearch');
        if (adSearch) adSearch.addEventListener('keydown', function (e) { if (e.key === 'Enter') applyAdsFilters(); });

        // Close modal buttons
        document.querySelectorAll('.btn-close-ads-modal').forEach(function (btn) {
            btn.addEventListener('click', function () { closeModal(btn.dataset.modal || 'adModal'); });
        });

        // Close modals on backdrop click
        ['campaignModal', 'adModal', 'placementModal', 'placementItemModal'].forEach(function (modalId) {
            var modal = document.getElementById(modalId);
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) closeModal(modalId);
                });
            }
        });

        // Media studio message: images selected
        window.addEventListener('message', function (e) {
            if (e.data && e.data.type === 'media-selected') {
                adSelectedImages = e.data.images || [];
                renderAdImagesPreview();
                closeAdMediaStudio();
                // Reload images for the selected type to reflect the new uploads
                var idEl       = document.getElementById('adId');
                var adId       = idEl ? parseInt(idEl.value, 10) : 0;
                var imgTypeSel = document.getElementById('adImageType');
                var typeId     = imgTypeSel ? parseInt(imgTypeSel.value, 10) : 0;
                if (adId && typeId) loadAdImages(adId, typeId);
            }
        });
    }

    /* ──────────────────────────────────────────────
     * Initialise
     * ──────────────────────────────────────────── */
    function init() {
        reloadConfig();
        bindEvents();

        // Show correct tab button
        switchTab('campaigns');

        // Populate campaign filter on ads tab
        refreshCampaignFilter();

        // Load data
        loadCampaigns({ page: 1, filters: {} });
        loadAds({ page: 1, filters: {} });
        loadPlacements({ page: 1, filters: {} });
    }

    /* ──────────────────────────────────────────────
     * Expose & boot with i18n guard
     * ──────────────────────────────────────────── */
    window.Ads = { init: init };

    (function boot() {
        function tryInit() {
            if (!window.TRANSLATIONS) return;
            cleanup();
            if (window.ADS_CONFIG && window.ADS_CONFIG.strings) {
                STRINGS = window.ADS_CONFIG.strings;
            }
            init();
        }

        var INIT_TIMEOUT_MS = 6000;
        var poll;
        function cleanup() {
            clearInterval(poll);
            document.removeEventListener('admin:i18n:applied', tryInit);
        }

        document.addEventListener('admin:i18n:applied', tryInit);
        tryInit();
        poll = setInterval(function () {
            if (window.TRANSLATIONS) { tryInit(); }
        }, INIT_TIMEOUT_MS);
        setTimeout(function () { if (!window.TRANSLATIONS) { cleanup(); init(); } }, INIT_TIMEOUT_MS);
    }());

}());