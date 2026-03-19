/**
 * Tenants Management – Production Version
 * Version: 2.0.0
 *
 * ✅ AdminFramework integration
 * ✅ Tabs: Basic Info | Users | Addresses (embedded sub-fragments)
 * ✅ Translation support via window.TENANTS_TRANSLATIONS
 * ✅ Granular permissions from window.PAGE_PERMISSIONS
 * ✅ RTL/LTR direction switching
 * ✅ Pagination + filters
 */
(function () {
    'use strict';

    const AF  = window.AdminFramework;
    const API = '/api/tenants';

    const TENANT_LOGO_IMAGE_TYPE_ID = window.TENANTS_CONFIG?.logoImageTypeId || 21;

    const state = {
        page: 1,
        perPage: window.TENANTS_CONFIG?.itemsPerPage || 25,
        filters: {},
        permissions: {},
        language: window.USER_LANGUAGE || 'en',
        currentTenantId: null
    };

    let el = {};

    // ─────────────────────────────────────────────
    // TRANSLATION HELPER
    // ─────────────────────────────────────────────
    function t(key, fallback) {
        const keys = key.split('.');
        let val = window.TENANTS_TRANSLATIONS;
        for (const k of keys) {
            if (val && val[k] !== undefined) {
                val = val[k];
            } else {
                return fallback !== undefined ? fallback : key;
            }
        }
        return (typeof val === 'string') ? val : (fallback !== undefined ? fallback : key);
    }

    // ─────────────────────────────────────────────
    // DIRECTION HELPER
    // ─────────────────────────────────────────────
    function setDirection(lang) {
        if (!lang) return;
        const rtlLangs = ['ar', 'he', 'fa', 'ur', 'ps'];
        const isRtl = rtlLangs.includes(String(lang).toLowerCase().substring(0, 2));
        const dir = isRtl ? 'rtl' : 'ltr';
        try { document.documentElement.dir = dir; } catch (e) { /* ignore */ }
        const container = document.getElementById('tenantsPageContainer');
        if (container) {
            container.dir = dir;
            container.classList.toggle('rtl', isRtl);
            container.classList.toggle('ltr', !isRtl);
        }
    }

    // ─────────────────────────────────────────────
    // TABS
    // ─────────────────────────────────────────────
    function activateTab(tabId) {
        document.querySelectorAll('#tenantFormTabs .tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabId);
        });
        document.querySelectorAll('#tenantForm .tab-content').forEach(tc => {
            const show = tc.id === tabId;
            tc.classList.toggle('active', show);
            tc.style.display = show ? '' : 'none';
        });

        // Lazy-load sub-fragments when tab is first opened
        if (tabId === 'tab-domains' && state.currentTenantId) {
            loadDomains(state.currentTenantId);
        }
        if (tabId === 'tab-users' && state.currentTenantId) {
            loadSubFragment('tenantUsersContainer',
                `${window.TENANTS_CONFIG?.tenantUsersUrl || '/admin/fragments/tenant_users.php'}?embedded=1&tenant_id=${state.currentTenantId}&lang=${state.language}`
            );
        }
        if (tabId === 'tab-addresses' && state.currentTenantId) {
            loadSubFragment('tenantAddressesContainer',
                `${window.TENANTS_CONFIG?.addressesUrl || '/admin/fragments/addresses.php'}?embedded=1&owner_type=entity&tenant_id=${state.currentTenantId}&lang=${state.language}`
            );
        }
        if (tabId === 'tab-categories' && state.currentTenantId) {
            loadTenantCategories(state.currentTenantId);
        }
        if (tabId === 'tab-media' && state.currentTenantId) {
            loadTenantLogo(state.currentTenantId);
        }
    }

    function enableSubTabs(tenantId) {
        state.currentTenantId = tenantId;
        const btnDomains     = document.getElementById('tabBtnDomains');
        const btnUsers       = document.getElementById('tabBtnUsers');
        const btnAddr        = document.getElementById('tabBtnAddresses');
        const btnCategories  = document.getElementById('tabBtnCategories');
        const btnMedia       = document.getElementById('tabBtnMedia');
        if (btnDomains)    btnDomains.disabled    = false;
        if (btnUsers)      btnUsers.disabled      = false;
        if (btnAddr)       btnAddr.disabled       = false;
        if (btnCategories) btnCategories.disabled = false;
        if (btnMedia)      btnMedia.disabled      = false;
    }

    async function loadSubFragment(containerId, url) {
        const container = document.getElementById(containerId);
        if (!container) return;
        // Only load once (detect by a data-loaded flag)
        if (container.dataset.loaded) return;
        container.dataset.loaded = '1';

        // Show a simple spinner while loading
        container.innerHTML = '<div class="sub-fragment-loading"><div class="spinner"></div></div>';

        try {
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const html = await res.text();

            // Parse the fetched HTML in a detached document so we can extract scripts/links
            const doc = new DOMParser().parseFromString(html, 'text/html');

            // Inject <link rel="stylesheet"> tags into the parent document <head> (once)
            doc.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
                const href = link.getAttribute('href') || '';
                if (!href) return;
                const absHref = new URL(href, window.location.origin).href;
                if (!document.querySelector(`link[href="${absHref}"], link[href="${href}"]`)) {
                    const el = document.createElement('link');
                    el.rel  = 'stylesheet';
                    el.href = absHref;
                    document.head.appendChild(el);
                }
            });

            // Set the container HTML (only body content, no link/script tags)
            doc.querySelectorAll('link, script').forEach(n => n.remove());
            container.innerHTML = doc.body.innerHTML;

            // Execute inline <script> tags from the original HTML in order
            const scripts = [];
            const tmpDoc  = new DOMParser().parseFromString(html, 'text/html');
            tmpDoc.querySelectorAll('script').forEach(s => scripts.push(s));

            for (const src of scripts) {
                const script = document.createElement('script');
                if (src.src) {
                    // External script: only load once
                    const absSrc = new URL(src.src, window.location.origin).href;
                    if (document.querySelector(`script[src="${absSrc}"]`)) continue;
                    script.src   = absSrc;
                    script.async = false;
                    script.type  = src.type || 'text/javascript';
                    await new Promise(resolve => {
                        script.onload = script.onerror = resolve;
                        document.head.appendChild(script);
                    });
                } else if (src.textContent.trim()) {
                    script.textContent = src.textContent;
                    script.type = src.type || 'text/javascript';
                    document.body.appendChild(script);
                }
            }
        } catch (err) {
            container.innerHTML = `<div class="error-state"><p style="color:var(--danger-color)">${err.message || 'Failed to load'}</p></div>`;
            delete container.dataset.loaded;
        }
    }

    // ─────────────────────────────────────────────
    // DOMAINS
    // ─────────────────────────────────────────────
    const domainsApiUrl = () => window.TENANTS_CONFIG?.domainsApiUrl || '/api/tenant_domains';

    let _domainsLoaded = false;

    async function loadDomains(tenantId, force = false) {
        if (_domainsLoaded && !force) return;
        const list = document.getElementById('domainsList');
        const placeholder = document.getElementById('domainsPlaceholder');
        if (!list) return;

        try {
            list.innerHTML = '<p style="color:var(--text-secondary);padding:1rem;">Loading…</p>';
            const res = await AF.get(`${domainsApiUrl()}?tenant_id=${tenantId}`);
            const items = res?.data?.items || res?.items || [];
            _domainsLoaded = true;

            if (!items.length) {
                list.innerHTML = `<div class="sub-fragment-placeholder" id="domainsPlaceholder">
                    <i class="fas fa-globe fa-2x"></i>
                    <p>${t('domains.no_domains', 'No domains registered yet')}</p>
                </div>`;
                return;
            }

            list.innerHTML = items.map(d => {
                const typeCls = {primary:'badge-primary-type',custom:'badge-custom-type',subdomain:'badge-subdomain-type',alias:'badge-alias-type'}[d.type] || '';
                const sslCls  = {active:'badge-success',pending:'badge-warning',failed:'badge-danger',none:'badge-muted'}[d.ssl_status] || 'badge-muted';
                const sslLabel = t('domains.ssl_' + (d.ssl_status || 'none'), d.ssl_status || 'None');
                const verLabel = d.is_verified ? t('domains.verified','Verified') : t('domains.unverified','Unverified');
                const verCls   = d.is_verified ? 'badge-verified' : 'badge-unverified';
                return `
                <div class="domain-row" data-id="${d.id}">
                    <div class="domain-row-main">
                        <code class="domain-badge">${esc(d.domain)}</code>
                        <span class="badge-status ${typeCls}">${t('domains.' + d.type, d.type)}</span>
                        <span class="badge-status ${verCls}">${verLabel}</span>
                        <span class="badge-status ${sslCls}">${sslLabel}</span>
                    </div>
                    <div class="domain-row-actions">
                        <button class="btn btn-sm btn-outline" onclick="Tenants.editDomain(${d.id})" title="${t('domains.edit', 'Edit')}"><i class="fas fa-edit"></i></button>
                        ${!d.is_verified ? `<button class="btn btn-sm btn-outline" onclick="Tenants.verifyDomain(${d.id})" title="${t('domains.verify', 'Mark Verified')}"><i class="fas fa-check"></i></button>` : ''}
                        ${d.type !== 'primary' ? `<button class="btn btn-sm btn-danger" onclick="Tenants.removeDomain(${d.id})" title="${t('domains.delete', 'Delete')}"><i class="fas fa-trash"></i></button>` : ''}
                    </div>
                </div>`;
            }).join('');

        } catch (err) {
            list.innerHTML = `<p style="color:var(--danger-color);padding:1rem;">${err?.message || 'Failed to load domains'}</p>`;
        }
    }

    async function addDomain() {
        const input            = document.getElementById('newDomainInput');
        const typeEl           = document.getElementById('newDomainType');
        const sslStatusEl      = document.getElementById('newDomainSslStatus');
        const sslExpiresAtEl   = document.getElementById('newDomainSslExpiresAt');
        const verTokenEl       = document.getElementById('newDomainVerificationToken');
        const verifiedAtEl     = document.getElementById('newDomainVerifiedAt');
        const metaEl           = document.getElementById('newDomainMeta');
        const isVerifiedEl     = document.getElementById('newDomainIsVerified');
        const redirectEl       = document.getElementById('newDomainRedirectToPrimary');
        const hiddenIdEl       = document.getElementById('newDomainId');

        const domain = input?.value?.trim();
        const type   = typeEl?.value || 'custom';

        if (!domain) { if (AF.error) AF.error(t('domains.fields.domain.required', 'Please enter a domain')); return; }
        if (!state.currentTenantId) return;

        // Validate meta JSON if provided
        const rawMeta = metaEl?.value?.trim() || '';
        let metaValue = null;
        if (rawMeta) {
            try {
                metaValue = JSON.parse(rawMeta);
            } catch (_) {
                if (AF.error) AF.error(t('domains.fields.meta.invalid_json', 'Meta must be valid JSON'));
                return;
            }
        }

        const payload = {
            tenant_id:            state.currentTenantId,
            domain,
            type,
            ssl_status:           sslStatusEl?.value       || 'none',
            ssl_expires_at:       sslExpiresAtEl?.value    || null,
            verification_token:   verTokenEl?.value?.trim() || null,
            verified_at:          verifiedAtEl?.value       || null,
            is_verified:          isVerifiedEl?.checked    ? 1 : 0,
            redirect_to_primary:  redirectEl?.checked      ? 1 : 0,
            meta:                 metaValue,
        };

        // Clean up empty strings to null
        if (!payload.ssl_expires_at)     payload.ssl_expires_at     = null;
        if (!payload.verification_token) payload.verification_token = null;
        if (!payload.verified_at)        payload.verified_at        = null;

        try {
            const editId = hiddenIdEl?.value ? parseInt(hiddenIdEl.value) : null;
            if (editId) {
                await AF.put(`${domainsApiUrl()}/${editId}`, payload);
                if (AF.success) AF.success(t('domains.messages.updated', 'Domain updated'));
            } else {
                await AF.post(domainsApiUrl(), payload);
                if (AF.success) AF.success(t('domains.messages.added', 'Domain added'));
            }
            // Reset form
            if (input)          input.value          = '';
            if (typeEl)         typeEl.value         = 'custom';
            if (sslStatusEl)    sslStatusEl.value    = 'none';
            if (sslExpiresAtEl) sslExpiresAtEl.value = '';
            if (verTokenEl)     verTokenEl.value     = '';
            if (verifiedAtEl)   verifiedAtEl.value   = '';
            if (metaEl)         metaEl.value         = '';
            if (isVerifiedEl)   isVerifiedEl.checked = false;
            if (redirectEl)     redirectEl.checked   = false;
            if (hiddenIdEl)     hiddenIdEl.value     = '';
            document.getElementById('domainFormInline').style.display = 'none';
            _domainsLoaded = false;
            loadDomains(state.currentTenantId, true);
        } catch (err) {
            if (AF.error) AF.error(err?.message || t('domains.messages.add_failed', 'Failed to save domain'));
        }
    }

    async function verifyDomain(id) {
        try {
            await AF.post(`${domainsApiUrl()}/${id}/verify`, {});
            if (AF.success) AF.success('Domain marked as verified');
            _domainsLoaded = false;
            loadDomains(state.currentTenantId, true);
        } catch (err) {
            if (AF.error) AF.error(err?.message || 'Failed to verify domain');
        }
    }

    async function removeDomain(id) {
        const msg = t('domains.delete_confirm', 'Are you sure you want to remove this domain?');
        const doDelete = async () => {
            try {
                await AF.delete(`${domainsApiUrl()}/${id}`);
                if (AF.success) AF.success('Domain removed');
                _domainsLoaded = false;
                loadDomains(state.currentTenantId, true);
            } catch (err) {
                if (AF.error) AF.error(err?.message || 'Failed to remove domain');
            }
        };
        if (AF.Modal?.confirm) {
            AF.Modal.confirm(msg, doDelete);
        } else if (confirm(msg)) {
            doDelete();
        }
    }

    async function editDomain(id) {
        try {
            const res = await AF.get(`${domainsApiUrl()}/${id}`);
            const d   = res?.data || res;

            const input         = document.getElementById('newDomainInput');
            const typeEl        = document.getElementById('newDomainType');
            const sslStatusEl   = document.getElementById('newDomainSslStatus');
            const sslExpiresEl  = document.getElementById('newDomainSslExpiresAt');
            const verTokenEl    = document.getElementById('newDomainVerificationToken');
            const verifiedAtEl  = document.getElementById('newDomainVerifiedAt');
            const metaEl        = document.getElementById('newDomainMeta');
            const isVerifiedEl  = document.getElementById('newDomainIsVerified');
            const redirectEl    = document.getElementById('newDomainRedirectToPrimary');
            const hiddenIdEl    = document.getElementById('newDomainId');
            const titleEl       = document.getElementById('domainFormTitle');

            // Helper: convert "YYYY-MM-DD HH:MM:SS" → "YYYY-MM-DDTHH:MM"
            const toLocal = v => v ? String(v).replace(' ', 'T').slice(0, 16) : '';

            if (input)        input.value          = d.domain || '';
            if (typeEl)       typeEl.value         = d.type || 'custom';
            if (sslStatusEl)  sslStatusEl.value    = d.ssl_status || 'none';
            if (sslExpiresEl) sslExpiresEl.value   = toLocal(d.ssl_expires_at);
            if (verTokenEl)   verTokenEl.value     = d.verification_token || '';
            if (verifiedAtEl) verifiedAtEl.value   = toLocal(d.verified_at);
            if (metaEl)       metaEl.value         = d.meta ? (typeof d.meta === 'string' ? d.meta : JSON.stringify(d.meta, null, 2)) : '';
            if (isVerifiedEl) isVerifiedEl.checked = !!parseInt(d.is_verified);
            if (redirectEl)   redirectEl.checked   = !!parseInt(d.redirect_to_primary);
            if (hiddenIdEl)   hiddenIdEl.value     = id;
            if (titleEl)      titleEl.textContent  = t('domains.edit', 'Edit Domain');

            const domainFormInline = document.getElementById('domainFormInline');
            if (domainFormInline) {
                domainFormInline.style.display = 'block';
                domainFormInline.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        } catch (err) {
            if (AF.error) AF.error(err?.message || t('domains.messages.load_failed', 'Failed to load domain'));
        }
    }

    // ─────────────────────────────────────────────
    // CATEGORIES  (checkbox tree)
    // ─────────────────────────────────────────────
    const tenantCatApiUrl = () => window.TENANTS_CONFIG?.tenantCategoriesApiUrl || '/api/categories-tenants';
    const allCatApiUrl    = () => window.TENANTS_CONFIG?.categoriesApiUrl       || '/api/categories';

    // Internal state for the tree
    let _catTree = {
        loaded:      false,
        allCats:     [],      // raw flat list from API
        assignedMap: {},      // { category_id: assignment_id } — existing server assignments
        checkedIds:  new Set(),  // category_ids currently checked in UI
        nodeIndex:   {},      // { id: node } for fast lookup
    };

    // ── Build flat→tree index ──────────────────────────────
    function _buildNodeIndex(items) {
        const idx = {};
        items.forEach(c => {
            idx[c.id] = { ...c, children: [] };
        });
        const roots = [];
        items.forEach(c => {
            if (c.parent_id && idx[c.parent_id]) {
                idx[c.parent_id].children.push(idx[c.id]);
            } else {
                roots.push(idx[c.id]);
            }
        });
        return { roots, idx };
    }

    // ── Render tree HTML ───────────────────────────────────
    function _renderNodes(nodes, depth) {
        if (!nodes.length) return '';
        const wrapClass = depth === 0 ? '' : 'cat-tree-children collapsed';
        const wrapId    = depth === 0 ? 'id="catTreeRootList"' : '';
        let html = `<div class="${wrapClass}" ${wrapId}>`;
        nodes.forEach(node => {
            const hasChildren = node.children.length > 0;
            const checked     = _catTree.checkedIds.has(node.id);
            const toggleCls   = hasChildren ? '' : ' leaf';
            const toggleIcon  = 'fa-chevron-right';
            html += `
            <div class="cat-tree-node" data-cat-id="${node.id}" data-parent-id="${node.parent_id || 0}">
                <span class="cat-tree-toggle${toggleCls}" data-toggle="${node.id}"><i class="fas ${toggleIcon}"></i></span>
                <input type="checkbox" class="cat-tree-cb" data-cb="${node.id}" ${checked ? 'checked' : ''}>
                <span class="cat-tree-label">${esc(node.name || String(node.id))}</span>
                ${hasChildren ? `<span class="cat-tree-count" data-count="${node.id}">${node.children.length}</span>` : ''}
            </div>
            ${hasChildren ? _renderNodes(node.children, depth + 1) : ''}`;
        });
        html += '</div>';
        return html;
    }

    // ── Refresh indeterminate states after render ──────────
    function _syncAllParents() {
        // Bottom-up: iterate all nodes that have children
        Object.values(_catTree.nodeIndex).forEach(node => {
            if (node.children.length) _syncParentState(node.id);
        });
    }

    function _syncParentState(parentId) {
        const node = _catTree.nodeIndex[parentId];
        if (!node || !node.children.length) return;
        const cbEl = document.querySelector(`.cat-tree-cb[data-cb="${parentId}"]`);
        if (!cbEl) return;
        const childIds    = _getAllDescendants(node.id);
        const checkedCnt  = childIds.filter(id => _catTree.checkedIds.has(id)).length;
        if (checkedCnt === 0) {
            cbEl.checked       = false;
            cbEl.indeterminate = false;
            _catTree.checkedIds.delete(parentId);
        } else if (checkedCnt === childIds.length) {
            cbEl.checked       = true;
            cbEl.indeterminate = false;
            _catTree.checkedIds.add(parentId);
        } else {
            cbEl.checked       = false;
            cbEl.indeterminate = true;
            _catTree.checkedIds.delete(parentId);
        }
    }

    function _getAllDescendants(nodeId) {
        const node = _catTree.nodeIndex[nodeId];
        if (!node) return [];
        let ids = [];
        node.children.forEach(c => {
            ids.push(c.id);
            ids = ids.concat(_getAllDescendants(c.id));
        });
        return ids;
    }

    // ── Handle a checkbox click ────────────────────────────
    function _onCbClick(catId, checked) {
        const node        = _catTree.nodeIndex[catId];
        if (!node) return;

        // Update self
        if (checked) {
            _catTree.checkedIds.add(catId);
        } else {
            _catTree.checkedIds.delete(catId);
        }

        // Cascade to all descendants
        const descendants = _getAllDescendants(catId);
        descendants.forEach(id => {
            const el = document.querySelector(`.cat-tree-cb[data-cb="${id}"]`);
            if (el) { el.checked = checked; el.indeterminate = false; }
            if (checked) _catTree.checkedIds.add(id);
            else         _catTree.checkedIds.delete(id);
        });

        // Bubble up ancestors
        let parentId = node.parent_id;
        while (parentId) {
            _syncParentState(parentId);
            const p = _catTree.nodeIndex[parentId];
            parentId = p ? p.parent_id : null;
        }
    }

    // ── Toggle expand/collapse ─────────────────────────────
    function _onToggle(catId) {
        const nodeEl     = document.querySelector(`.cat-tree-node[data-cat-id="${catId}"]`);
        if (!nodeEl) return;
        const childrenEl = nodeEl.nextElementSibling;
        if (!childrenEl || !childrenEl.classList.contains('cat-tree-children')) return;
        const isCollapsed = childrenEl.classList.contains('collapsed');
        childrenEl.classList.toggle('collapsed', !isCollapsed);
        const icon = nodeEl.querySelector('.cat-tree-toggle i');
        if (icon) {
            icon.className = isCollapsed ? 'fas fa-chevron-down' : 'fas fa-chevron-right';
        }
    }

    // ── Search / filter ───────────────────────────────────
    function _filterTree(query) {
        const q = query.toLowerCase().trim();
        document.querySelectorAll('#tenantCategoryTree .cat-tree-node').forEach(el => {
            const label = el.querySelector('.cat-tree-label')?.textContent?.toLowerCase() || '';
            el.classList.toggle('hidden', q !== '' && !label.includes(q));
        });
        // Reveal parent nodes if any visible children exist
        if (q !== '') {
            document.querySelectorAll('#tenantCategoryTree .cat-tree-children').forEach(grp => {
                const anyVisible = grp.querySelector('.cat-tree-node:not(.hidden)');
                if (anyVisible) grp.classList.remove('collapsed');
            });
        }
    }

    // ── Main loader ───────────────────────────────────────
    async function loadTenantCategories(tenantId, force = false) {
        if (_catTree.loaded && !force) return;
        const container = document.getElementById('tenantCategoryTree');
        if (!container) return;

        container.innerHTML = '<p class="cat-row-meta" style="padding:1rem;"><i class="fas fa-spinner fa-spin"></i> ' + t('categories.loading', 'Loading…') + '</p>';
        _setTreeStatus('');

        try {
            // Parallel fetch: all categories + existing assignments
            const [allRes, assignedRes] = await Promise.all([
                AF.get(`${allCatApiUrl()}?limit=2000&lang=${state.language}&skip_tc_filter=1`),
                AF.get(`${tenantCatApiUrl()}?tenant_id=${tenantId}&lang=${state.language || 'ar'}`),
            ]);

            const allItems      = allRes?.data?.items || allRes?.data || allRes?.items || [];
            const assignedItems = Array.isArray(assignedRes?.data) ? assignedRes.data : (Array.isArray(assignedRes) ? assignedRes : []);

            if (!allItems.length) {
                container.innerHTML = '<div class="sub-fragment-placeholder" id="tenantCategoriesPlaceholder"><i class="fas fa-tags fa-2x"></i><p>' + t('categories.no_categories', 'No categories assigned yet') + '</p></div>';
                return;
            }

            // Build node index and tree roots
            const { roots, idx } = _buildNodeIndex(allItems);
            _catTree.nodeIndex   = idx;
            _catTree.allCats     = allItems;

            // Build assignedMap  and initial checkedIds
            _catTree.assignedMap = {};
            _catTree.checkedIds  = new Set();
            assignedItems.forEach(a => {
                _catTree.assignedMap[a.category_id] = a.id;   // category_id → assignment row id
                _catTree.checkedIds.add(a.category_id);
            });

            // Render
            container.innerHTML = _renderNodes(roots, 0);

            // Fix indeterminate states
            _syncAllParents();

            // Re-apply checked state DOM (for parents whose children are all checked)
            Object.values(_catTree.nodeIndex).forEach(node => {
                if (node.children.length) {
                    const cbEl = document.querySelector(`.cat-tree-cb[data-cb="${node.id}"]`);
                    if (cbEl && _catTree.checkedIds.has(node.id)) cbEl.checked = true;
                }
            });

            // Attach event delegation to container
            _attachTreeEvents(container);

            _catTree.loaded = true;
        } catch (err) {
            container.innerHTML = `<p class="cat-row-meta" style="padding:1rem;color:var(--danger-color,#ef4444);"><i class="fas fa-exclamation-triangle"></i> ${esc(err?.message || 'Failed to load categories')}</p>`;
        }
    }

    function _attachTreeEvents(container) {
        // Use event delegation – single listener for thousands of nodes
        container.addEventListener('click', e => {
            const cb     = e.target.closest('.cat-tree-cb');
            const toggle = e.target.closest('.cat-tree-toggle:not(.leaf)');

            if (cb) {
                const catId = parseInt(cb.dataset.cb, 10);
                _onCbClick(catId, cb.checked);
                return;
            }
            if (toggle) {
                const catId = parseInt(toggle.dataset.toggle, 10);
                _onToggle(catId);
                return;
            }
            // Click on row itself (not checkbox, not toggle) → toggle checkbox
            const node = e.target.closest('.cat-tree-node');
            if (node && !e.target.classList.contains('cat-tree-cb')) {
                const cb2 = node.querySelector('.cat-tree-cb');
                if (cb2) { cb2.checked = !cb2.checked; _onCbClick(parseInt(cb2.dataset.cb, 10), cb2.checked); }
            }
        }, { passive: true });
    }

    // ── Batch save ────────────────────────────────────────
    async function saveCategoryTree() {
        if (!state.currentTenantId) return;
        const btn = document.getElementById('btnSaveCategoryTree');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }
        _setTreeStatus(t('categories.saving', 'Saving…'));

        try {
            // Single batch-sync call — backend resolves children and diffs
            await AF.post(`${tenantCatApiUrl()}/sync`, {
                tenant_id:        state.currentTenantId,
                category_ids:     [..._catTree.checkedIds],
                include_children: true,
                is_active:        1,
            });
            if (AF.success) AF.success(t('categories.saved', 'Categories saved'));
            _setTreeStatus(t('categories.saved', 'Saved ✓'));
            // Refresh tree to reflect resolved server state
            _catTree.loaded = false;
            loadTenantCategories(state.currentTenantId, true);
        } catch (err) {
            if (AF.error) AF.error(err?.message || t('categories.save_failed', 'Failed to save categories'));
            _setTreeStatus('');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> ' + t('categories.buttons.save', 'Save Categories'); }
        }
    }

    function _setTreeStatus(msg) {
        const el = document.getElementById('catTreeStatus');
        if (el) el.textContent = msg;
    }

    // ── Select All / Deselect All ─────────────────────────
    function _selectAllCategories(checked) {
        document.querySelectorAll('#tenantCategoryTree .cat-tree-cb').forEach(cb => {
            cb.checked        = checked;
            cb.indeterminate  = false;
            const id = parseInt(cb.dataset.cb, 10);
            if (checked) _catTree.checkedIds.add(id);
            else         _catTree.checkedIds.delete(id);
        });
    }

    // ─────────────────────────────────────────────
    // ESCAPE HTML
    // ─────────────────────────────────────────────
    function esc(text) {
        if (text === null || text === undefined) return '';
        const d = document.createElement('div');

        d.textContent = String(text);
        return d.innerHTML;
    }

    // ─────────────────────────────────────────────
    // MEDIA (TENANT LOGO)
    // ─────────────────────────────────────────────
    function openTenantMediaStudio() {
        if (!state.currentTenantId) {
            if (AF.error) AF.error(t('form.media.save_first', 'Please save the tenant first before adding media'));
            return;
        }
        const cfg     = window.TENANTS_CONFIG || {};
        const base    = cfg.mediaStudioBase  || '/admin/fragments/media_studio.php';
        const typeId  = TENANT_LOGO_IMAGE_TYPE_ID;
        const tenantId = window.APP_CONFIG?.TENANT_ID || cfg.tenantId || '';
        const lang    = state.language;

        const frame = document.getElementById('tenantMediaFrame');
        const modal = document.getElementById('tenantMediaModal');
        if (!frame || !modal) return;

        frame.src = `${base}?embedded=1&tenant_id=${tenantId}&lang=${lang}&owner_id=${state.currentTenantId}&image_type_id=${typeId}&owner_type=tenant`;
        modal.style.display = 'flex';
    }

    function closeTenantMediaStudio() {
        const modal = document.getElementById('tenantMediaModal');
        if (modal) modal.style.display = 'none';
    }

    function updateTenantLogoPreview(imageUrl) {
        const previewEl    = document.getElementById('tenantLogoPreview');
        const urlDisplayEl = document.getElementById('tenantLogoUrlDisplay');

        if (previewEl) {
            if (imageUrl) {
                const wrapper = document.createElement('div');
                wrapper.style.cssText = 'position:relative; display:inline-block;';

                const img = document.createElement('img');
                img.src = imageUrl;
                img.style.cssText = 'max-width:100%; max-height:200px; border-radius:4px;';
                wrapper.appendChild(img);

                previewEl.innerHTML = '';
                previewEl.appendChild(wrapper);
            } else {
                previewEl.innerHTML = `<div class="placeholder">${t('form.media.no_logo', 'No logo selected')}</div>`;
            }
        }
        if (urlDisplayEl) urlDisplayEl.value = imageUrl || '';
    }

    async function loadTenantLogo(tenantId) {
        const cfg    = window.TENANTS_CONFIG || {};
        const apiUrl = cfg.imagesApiUrl || '/api/images';
        try {
            const res = await AF.get(`${apiUrl}/by_owner?owner_id=${tenantId}&image_type_id=${TENANT_LOGO_IMAGE_TYPE_ID}`);
            const images = res?.data?.images || res?.data || res?.images || [];
            const main   = Array.isArray(images) ? images.find(i => i.is_main) || images[0] : null;
            updateTenantLogoPreview(main ? (main.url || main.thumb_url || null) : null);
        } catch (_) {
            updateTenantLogoPreview(null);
        }
    }

    // ─────────────────────────────────────────────
    // RENDER TABLE
    // ─────────────────────────────────────────────
    function renderTable(items) {
        if (!items || !items.length) {
            if (el.loading)    el.loading.style.display    = 'none';
            if (el.container)  el.container.style.display  = 'none';
            if (el.empty)      el.empty.style.display      = 'block';
            if (el.error)      el.error.style.display      = 'none';
            return;
        }

        const rows = items.map(item => {
            const isActive   = item.status === 'active';
            const statusText = isActive ? t('table.status.active', 'Active') : t('table.status.suspended', 'Suspended');
            const statusCls  = isActive
                ? 'badge-status badge-active'
                : 'badge-status badge-suspended';

            const ownerDisplay = item.owner_username
                ? `${esc(item.owner_username)} <small>(ID: ${item.owner_user_id})</small>`
                : `<span class="text-muted">ID: ${item.owner_user_id}</span>`;

            const updatedAtDisplay = item.updated_at
                ? `<span class="date-display">${AF.formatDate ? AF.formatDate(item.updated_at) : esc(item.updated_at)}</span>`
                : `<span class="text-muted">—</span>`;

            return `
                <tr>
                    <td>${item.id}</td>
                    <td><strong>${esc(item.name)}</strong></td>
                    <td>${ownerDisplay}</td>
                    <td><span class="${statusCls}">${esc(statusText)}</span></td>
                    <td><span class="date-display">${AF.formatDate ? AF.formatDate(item.created_at) : esc(item.created_at || '')}</span></td>
                    <td>${updatedAtDisplay}</td>
                    <td>
                        <div class="table-actions">
                            ${state.permissions.canEdit
                                ? `<button class="btn btn-sm btn-outline" onclick="Tenants.edit(${item.id})">${t('table.actions.edit', 'Edit')}</button>`
                                : ''}
                            ${state.permissions.canDelete
                                ? `<button class="btn btn-sm btn-danger" onclick="Tenants.remove(${item.id})">${t('table.actions.delete', 'Delete')}</button>`
                                : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        if (el.tbody) el.tbody.innerHTML = rows;

        if (el.loading)   el.loading.style.display   = 'none';
        if (el.container) el.container.style.display = 'block';
        if (el.empty)     el.empty.style.display     = 'none';
        if (el.error)     el.error.style.display     = 'none';
    }

    // ─────────────────────────────────────────────
    // DATA LOADING
    // ─────────────────────────────────────────────
    async function load(page = 1) {
        try {
            console.log('[Tenants] Loading page:', page);

            if (el.loading)   el.loading.style.display   = 'block';
            if (el.container) el.container.style.display = 'none';
            if (el.empty)     el.empty.style.display     = 'none';
            if (el.error)     el.error.style.display     = 'none';

            state.page = page;

            const params = new URLSearchParams({
                page:     page,
                per_page: state.perPage,
                format:   'json',
                ...state.filters
            });

            console.log('[Tenants] URL:', `${API}?${params}`);
            const response = await AF.get(`${API}?${params}`);
            console.log('[Tenants] Response:', response);

            const data  = response?.data || response;
            const items = data?.items || (Array.isArray(data) ? data : []);
            const meta  = data?.meta  || {};

            renderTable(items);

            // Pagination
            if (el.pagination && AF.Table?.renderPagination) {
                AF.Table.renderPagination(el.pagination, el.paginationInfo, meta);
            } else if (el.pagination) {
                renderPagination(meta);
            }

        } catch (err) {
            console.error('[Tenants] Load error:', err);
            if (el.loading)   el.loading.style.display   = 'none';
            if (el.container) el.container.style.display = 'none';
            if (el.empty)     el.empty.style.display     = 'none';
            if (el.error)     el.error.style.display     = 'block';
            if (el.errorMessage) el.errorMessage.textContent = err?.message || t('messages.error.load_failed', 'Failed to load tenants');
        }
    }

    function renderPagination(meta) {
        if (!el.pagination) return;
        const { page = 1, last_page = 1, total = 0, per_page = 25 } = meta;
        if (el.paginationInfo) {
            const from = ((page - 1) * per_page) + 1;
            const to   = Math.min(page * per_page, total);
            el.paginationInfo.textContent = total > 0 ? `${from}–${to} / ${total}` : '';
        }
        const pages = [];
        for (let i = 1; i <= last_page; i++) {
            pages.push(`<button class="pagination-btn${i === page ? ' active' : ''}" data-page="${i}">${i}</button>`);
        }
        el.pagination.innerHTML = pages.join('');
    }

    // ─────────────────────────────────────────────
    // FILTERS
    // ─────────────────────────────────────────────
    function applyFilters() {
        state.filters = {};
        const search = el.searchInput?.value?.trim();
        if (search) state.filters.search = search;
        const status = el.statusFilter?.value;
        if (status) state.filters.status = status;
        const owner = el.ownerFilter?.value?.trim();
        if (owner && !isNaN(owner)) state.filters.owner_user_id = owner;
        load(1);
    }

    function resetFilters() {
        if (el.searchInput)  el.searchInput.value  = '';
        if (el.statusFilter) el.statusFilter.value = '';
        if (el.ownerFilter)  el.ownerFilter.value  = '';
        state.filters = {};
        load(1);
    }

    // ─────────────────────────────────────────────
    // FORM
    // ─────────────────────────────────────────────
    async function save(e) {
        e.preventDefault();
        if (AF.Form?.validate && !AF.Form.validate('tenantForm')) return;

        const formData = AF.Form?.getData ? AF.Form.getData('tenantForm') : getFormData();
        const id       = el.formId?.value?.trim();
        const isEdit   = !!id;

        const data = {
            name:          (formData.name || '').trim(),
            owner_user_id: parseInt(formData.owner_user_id) || 0,
            status:        formData.status || 'active'
        };

        if (!data.name || data.name.length < 3) {
            AF.error ? AF.error(t('form.validation.name_required', 'Please enter a valid tenant name')) : alert(t('form.validation.name_required', 'Please enter a valid name'));
            return;
        }
        if (!data.owner_user_id || data.owner_user_id < 1) {
            AF.error ? AF.error(t('form.validation.owner_required', 'Please enter a valid user ID')) : alert('Invalid owner user ID');
            return;
        }

        if (isEdit) data.id = parseInt(id);

        try {
            if (AF.Loading?.show) AF.Loading.show(el.btnSubmit, isEdit ? t('form.buttons.updating', 'Updating…') : t('form.buttons.saving', 'Saving…'));

            let response;
            if (isEdit) {
                response = await AF.put(`${API}/${data.id}`, data);
            } else {
                response = await AF.post(API, data);
            }

            const savedItem = response?.data || response;
            if (AF.success) AF.success(isEdit ? t('messages.success.updated', 'Tenant updated') : t('messages.success.created', 'Tenant created'));

            // Enable sub-tabs now we have an ID
            const savedId = savedItem?.id || (isEdit ? parseInt(id) : null);
            if (savedId) enableSubTabs(savedId);

            if (AF.Form?.hide) {
                // Don't hide form – stay on Basic tab so user can switch to Users/Addresses
            }
            await load(state.page);

        } catch (err) {
            console.error('[Tenants] Save error:', err);
            if (AF.error) AF.error(err?.message || t('messages.error.save_failed', 'Failed to save tenant'));
        } finally {
            if (AF.Loading?.hide) AF.Loading.hide(el.btnSubmit);
        }
    }

    function getFormData() {
        const form = document.getElementById('tenantForm');
        const data = {};
        new FormData(form).forEach((v, k) => { data[k] = v; });
        return data;
    }

    async function edit(id) {
        console.log('[Tenants] Edit ID:', id);
        try {
            if (AF.Loading?.show) AF.Loading.show(el.btnSubmit, t('page.loading', 'Loading…'));

            const response = await AF.get(`${API}/${id}`);
            const item     = response?.data || response;

            if (!item || !item.id) throw new Error(t('messages.error.not_found', 'Tenant not found'));

            // Reset form
            const form = document.getElementById('tenantForm');
            if (form) {
                form.reset();
                form.classList.remove('was-validated');
            }
            if (el.formId)          el.formId.value          = item.id;
            if (el.formName)        el.formName.value        = item.name || '';
            if (el.formOwnerUserId) el.formOwnerUserId.value = item.owner_user_id || '';
            if (el.formStatus)      el.formStatus.value      = item.status || 'active';

            // Enable sub-tabs
            enableSubTabs(item.id);

            // Pre-load logo in background so it's ready when media tab is opened
            loadTenantLogo(item.id);

            // Switch to Basic tab
            activateTab('tab-basic');

            // Show form
            const titleEl = document.getElementById('formTitle');
            if (titleEl) titleEl.querySelector('span[data-i18n]').textContent = t('form.edit_title', 'Edit Tenant');
            const container = document.getElementById('tenantFormContainer');
            if (container) {
                container.style.display = 'block';
                setTimeout(() => container.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
            }

        } catch (err) {
            console.error('[Tenants] Edit error:', err);
            if (AF.error) AF.error(err?.message || t('messages.error.load_failed', 'Failed to load tenant'));
        } finally {
            if (AF.Loading?.hide) AF.Loading.hide(el.btnSubmit);
        }
    }

    function add() {
        console.log('[Tenants] Add new');
        state.currentTenantId = null;
        _domainsLoaded = false;

        const form = document.getElementById('tenantForm');
        if (form) {
            form.reset();
            form.classList.remove('was-validated');
        }
        if (el.formId) el.formId.value = '';

        // Disable sub-tabs until saved
        const btnDomains    = document.getElementById('tabBtnDomains');
        const btnUsers      = document.getElementById('tabBtnUsers');
        const btnAddr       = document.getElementById('tabBtnAddresses');
        const btnCategories = document.getElementById('tabBtnCategories');
        const btnMedia      = document.getElementById('tabBtnMedia');
        if (btnDomains)    btnDomains.disabled    = true;
        if (btnUsers)      btnUsers.disabled      = true;
        if (btnAddr)       btnAddr.disabled       = true;
        if (btnCategories) btnCategories.disabled = true;
        if (btnMedia)      btnMedia.disabled      = true;

        // Reset sub-fragment containers
        const domainsList       = document.getElementById('domainsList');
        const usersContainer    = document.getElementById('tenantUsersContainer');
        const addrContainer     = document.getElementById('tenantAddressesContainer');
        const catsTree          = document.getElementById('tenantCategoryTree');
        if (domainsList)    domainsList.innerHTML    = '<div class="sub-fragment-placeholder"><i class="fas fa-globe fa-2x"></i><p>' + t('domains.no_domains', 'No domains registered yet') + '</p></div>';
        if (usersContainer) usersContainer.innerHTML = '<div class="sub-fragment-placeholder"><i class="fas fa-users fa-2x"></i><p>' + t('tabs.users', 'Users') + '</p></div>';
        if (addrContainer)  addrContainer.innerHTML  = '<div class="sub-fragment-placeholder"><i class="fas fa-map-marker-alt fa-2x"></i><p>' + t('tabs.addresses', 'Addresses') + '</p></div>';
        if (catsTree)       { catsTree.innerHTML = '<div class="sub-fragment-placeholder" id="tenantCategoriesPlaceholder"><i class="fas fa-tags fa-2x"></i><p>' + t('categories.no_categories', 'No categories assigned yet') + '</p></div>'; }
        // Reset tree state
        _catTree.loaded = false;
        _catTree.checkedIds = new Set();
        _catTree.assignedMap = {};
        _setTreeStatus('');

        activateTab('tab-basic');

        const titleEl = document.getElementById('formTitle');
        if (titleEl) titleEl.querySelector('span[data-i18n]').textContent = t('form.add_title', 'Add Tenant');

        const container = document.getElementById('tenantFormContainer');
        if (container) {
            container.style.display = 'block';
            setTimeout(() => container.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
        }
    }

    async function remove(id) {
        const msg = t('table.actions.confirm_delete', 'Are you sure you want to delete this tenant?');
        if (AF.Modal?.confirm) {
            AF.Modal.confirm(msg, async () => {
                try {
                    await AF.delete(`${API}/${id}`);
                    if (AF.success) AF.success(t('messages.success.deleted', 'Tenant deleted'));
                    load();
                } catch (err) {
                    console.error('[Tenants] Delete error:', err);
                    if (AF.error) AF.error(err?.message || t('messages.error.delete_failed', 'Failed to delete tenant'));
                }
            });
        } else if (confirm(msg)) {
            try {
                await AF.delete(`${API}/${id}`);
                if (AF.success) AF.success(t('messages.success.deleted', 'Tenant deleted'));
                load();
            } catch (err) {
                console.error('[Tenants] Delete error:', err);
                if (AF.error) AF.error(err?.message || t('messages.error.delete_failed', 'Failed to delete'));
            }
        }
    }

    function hideForm() {
        const container = document.getElementById('tenantFormContainer');
        if (container) container.style.display = 'none';
        state.currentTenantId = null;
    }

    // ─────────────────────────────────────────────
    // INITIALIZATION
    // ─────────────────────────────────────────────
    function init() {
        console.log('%c[Tenants] Initializing…', 'color:#3b82f6;font-weight:bold');

        // Set direction
        setDirection(state.language);

        // Gather DOM elements
        el = {
            loading:        document.getElementById('tableLoading'),
            container:      document.getElementById('tableContainer'),
            empty:          document.getElementById('emptyState'),
            error:          document.getElementById('errorState'),
            errorMessage:   document.getElementById('errorMessage'),
            tbody:          document.getElementById('tableBody'),
            pagination:     document.getElementById('pagination'),
            paginationInfo: document.getElementById('paginationInfo'),

            form:           document.getElementById('tenantForm'),
            formId:         document.getElementById('formId'),
            formName:       document.getElementById('formName'),
            formOwnerUserId:document.getElementById('formOwnerUserId'),
            formStatus:     document.getElementById('formStatus'),

            searchInput:    document.getElementById('searchInput'),
            statusFilter:   document.getElementById('statusFilter'),
            ownerFilter:    document.getElementById('ownerFilter'),

            btnSubmit:      document.getElementById('btnSubmitForm'),
            btnAdd:         document.getElementById('btnAddTenant'),
            btnClose:       document.getElementById('btnCloseForm'),
            btnCancel:      document.getElementById('btnCancelForm'),
            btnApply:       document.getElementById('btnApplyFilters'),
            btnReset:       document.getElementById('btnResetFilters'),
            btnRetry:       document.getElementById('btnRetry'),
            btnRefresh:     document.getElementById('btnRefresh')
        };

        // Load permissions
        try {
            const permsEl = document.getElementById('pagePermissions');
            if (permsEl) {
                state.permissions = JSON.parse(permsEl.textContent || '{}');
            } else {
                state.permissions = window.PAGE_PERMISSIONS || {};
            }
        } catch (e) {
            console.warn('[Tenants] Failed to load permissions:', e);
            state.permissions = window.PAGE_PERMISSIONS || {};
        }

        // Tab buttons
        document.querySelectorAll('#tenantFormTabs .tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                if (!this.disabled) activateTab(this.dataset.tab);
            });
        });

        // Form events
        if (el.form)       el.form.onsubmit       = save;
        if (el.btnAdd)     el.btnAdd.onclick       = add;
        if (el.btnClose)   el.btnClose.onclick     = hideForm;
        if (el.btnCancel)  el.btnCancel.onclick    = hideForm;
        if (el.btnApply)   el.btnApply.onclick     = applyFilters;
        if (el.btnReset)   el.btnReset.onclick     = resetFilters;
        if (el.btnRetry)   el.btnRetry.onclick     = () => load(state.page);
        if (el.btnRefresh) el.btnRefresh.onclick   = () => load(state.page);

        // Domain form events
        const btnAddDomain    = document.getElementById('btnAddDomain');
        const btnSaveDomain   = document.getElementById('btnSaveDomain');
        const btnCancelDomain = document.getElementById('btnCancelDomain');
        const domainFormInline = document.getElementById('domainFormInline');
        if (btnAddDomain)    btnAddDomain.onclick    = () => {
            const hiddenIdEl = document.getElementById('newDomainId');
            if (hiddenIdEl) hiddenIdEl.value = '';
            const titleEl = document.getElementById('domainFormTitle');
            if (titleEl) titleEl.textContent = t('domains.add', 'Add Domain');
            if (domainFormInline) domainFormInline.style.display = 'block';
        };
        if (btnCancelDomain) btnCancelDomain.onclick = () => { if (domainFormInline) domainFormInline.style.display = 'none'; };
        if (btnSaveDomain)   btnSaveDomain.onclick   = addDomain;

        // Category tree events
        const btnSaveCategoryTree  = document.getElementById('btnSaveCategoryTree');
        const btnCatSelectAll      = document.getElementById('btnCatSelectAll');
        const btnCatDeselectAll    = document.getElementById('btnCatDeselectAll');
        const catTreeSearchInput   = document.getElementById('catTreeSearch');
        if (btnSaveCategoryTree) btnSaveCategoryTree.onclick = saveCategoryTree;
        if (btnCatSelectAll)     btnCatSelectAll.onclick     = () => _selectAllCategories(true);
        if (btnCatDeselectAll)   btnCatDeselectAll.onclick   = () => _selectAllCategories(false);
        if (catTreeSearchInput) {
            catTreeSearchInput.addEventListener('input', e => _filterTree(e.target.value));
        }

        // Media (logo) events
        const btnSelectLogo    = document.getElementById('btnSelectTenantLogo');
        const btnMediaClose    = document.getElementById('tenantMediaClose');
        const tenantMediaModal = document.getElementById('tenantMediaModal');
        if (btnSelectLogo) btnSelectLogo.onclick = openTenantMediaStudio;
        if (btnMediaClose) btnMediaClose.onclick = closeTenantMediaStudio;
        if (tenantMediaModal) {
            tenantMediaModal.addEventListener('click', e => {
                if (e.target === tenantMediaModal) closeTenantMediaStudio();
            });
        }

        // Listen for media-selected postMessage from media studio iframe
        window.addEventListener('message', function onTenantMediaMessage(e) {
            if (!e.data || e.data.type !== 'media-selected') return;
            const images  = e.data.images || [];
            const typeId  = e.data.imageTypeId;
            if (typeId == TENANT_LOGO_IMAGE_TYPE_ID && images.length) {
                const imageUrl = images[0].url || images[0].thumb_url || '';
                if (imageUrl) updateTenantLogoPreview(imageUrl);
                closeTenantMediaStudio();
            }
        });

        if (el.searchInput) {
            el.searchInput.addEventListener('keypress', e => {
                if (e.key === 'Enter') applyFilters();
            });
        }

        if (el.pagination) {
            el.pagination.addEventListener('click', e => {
                const page = e.target.dataset.page;
                if (page && !e.target.disabled) load(parseInt(page));
            });
        }

        // Initial load
        load(1);

        console.log('%c[Tenants] ✅ Initialized', 'color:#10b981;font-weight:bold');
    }

    // ─────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────
    window.Tenants = { init, load, edit, remove, add, verifyDomain, removeDomain, addDomain, editDomain, loadTenantCategories, saveCategoryTree };

    // Auto-init in standalone mode
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            if (window.AdminFramework) init();
        });
    } else {
        if (window.AdminFramework) init();
    }

})();
