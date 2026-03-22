/**
 * admin/assets/js/pages/banners.js
 * Banners management — rebuilt following brands.js / products.js pattern
 *
 * - Uses window.BANNERS_CONFIG, window.PAGE_PERMISSIONS, window.ADMIN_UI
 * - Images stored in unified images table (image_type_id = 9)
 * - Translations: EN required, AR optional (stored in banner_translations)
 * - Fully i18n-aware via window.BANNERS_TRANSLATIONS
 */
(function () {
    'use strict';

    // ═══════════════════════════════════════════════════════
    // CONFIG
    // ═══════════════════════════════════════════════════════
    const cfg = window.BANNERS_CONFIG || {};
    const API_URL          = cfg.apiUrl          || '/api/banners';
    const IMAGES_API       = cfg.imagesApi       || '/api/images';
    const LANGUAGES_API    = cfg.languagesApi    || '/api/languages';
    const BUTTON_STYLES_API = cfg.buttonStylesApi || '/api/button_styles';
    const IMAGE_TYPE_ID    = cfg.imageTypeId || 9;
    const CSRF_TOKEN       = cfg.csrfToken   || window.CSRF_TOKEN || '';
    const LANG             = cfg.lang        || window.USER_LANGUAGE || 'en';
    const ITEMS_PER_PAGE   = cfg.itemsPerPage || 25;

    const perms = window.PAGE_PERMISSIONS || {};
    const CAN_CREATE = !!perms.canCreate;
    const CAN_EDIT   = !!perms.canEdit;
    const CAN_DELETE = !!perms.canDelete;
    const IS_SUPER   = !!perms.isSuperAdmin;

    // ═══════════════════════════════════════════════════════
    // STATE
    // ═══════════════════════════════════════════════════════
    let state = {
        items:           [],
        currentPage:     1,
        totalItems:      0,
        editingId:       null,
        selectedImageId: null,
        languages:       [],
        filters: {
            search:   '',
            position: '',
            status:   ''
        }
    };

    // ═══════════════════════════════════════════════════════
    // i18n
    // ═══════════════════════════════════════════════════════
    function t(key, fallback) {
        const tr = window.BANNERS_TRANSLATIONS || {};
        const val = key.split('.').reduce((o, k) => (o && o[k] !== undefined) ? o[k] : null, tr);
        if (val !== null && val !== undefined) return String(val);
        return fallback || key.split('.').pop().replace(/_/g, ' ');
    }

    // ═══════════════════════════════════════════════════════
    // DOM HELPERS
    // ═══════════════════════════════════════════════════════
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    // ═══════════════════════════════════════════════════════
    // FETCH HELPERS
    // ═══════════════════════════════════════════════════════
    async function apiFetch(url, opts = {}) {
        opts.credentials = opts.credentials || 'same-origin';
        if (!opts.headers) opts.headers = {};
        opts.headers['Accept'] = 'application/json';
        if (opts.method && opts.method !== 'GET') {
            opts.headers['X-CSRF-Token'] = CSRF_TOKEN;
        }
        const res = await fetch(url, opts);
        const text = await res.text();
        let json;
        try { json = JSON.parse(text); } catch (e) { throw new Error('Invalid JSON: ' + text.slice(0, 200)); }
        if (!res.ok) {
            const msg = (json && (json.message || json.error)) || `HTTP ${res.status}`;
            throw Object.assign(new Error(msg), { status: res.status, data: json });
        }
        return json;
    }

    async function apiGet(url) {
        return apiFetch(url);
    }

    async function apiPost(url, body) {
        return apiFetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
    }

    async function apiPut(url, body) {
        return apiFetch(url, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
    }

    async function apiDelete(url, body) {
        return apiFetch(url, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
    }

    // ═══════════════════════════════════════════════════════
    // TOAST
    // ═══════════════════════════════════════════════════════
    function showToast(msg, type = 'success') {
        const el = $('bannersToast');
        if (!el) return;
        el.innerHTML = `<div class="toast toast-${esc(type)}" style="
            padding:12px 20px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,.25);
            background:${type === 'success' ? 'var(--success-color,#10b981)' : 'var(--danger-color,#ef4444)'};
            color:#fff; font-size:0.9rem;">
            ${esc(msg)}
        </div>`;
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 3500);
    }

    // ═══════════════════════════════════════════════════════
    // LOAD BANNERS
    // ═══════════════════════════════════════════════════════
    async function loadBanners() {
        const tbody = $('bannersTbody');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:24px;">${esc(t('banners.loading', 'Loading...'))}</td></tr>`;
        }

        try {
            const params = new URLSearchParams();
            params.set('lang', LANG);
            params.set('all_translations', '1');
            if (state.filters.position) params.set('position', state.filters.position);
            if (state.filters.status !== '')  params.set('is_active', state.filters.status);

            const data = await apiGet(`${API_URL}?${params.toString()}`);
            // Support both array response and {data:[...]} wrapper
            const items = Array.isArray(data) ? data : (data.data || data.items || []);
            state.items      = items;
            state.totalItems = items.length;
            renderTable();
        } catch (err) {
            console.error('[Banners] loadBanners failed:', err);
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:24px;color:var(--danger-color,#ef4444);">${esc(t('messages.error.load_failed', 'Failed to load data'))}: ${esc(err.message)}</td></tr>`;
            }
        }
    }

    // ═══════════════════════════════════════════════════════
    // RENDER TABLE
    // ═══════════════════════════════════════════════════════
    async function renderTable() {
        const tbody = $('bannersTbody');
        if (!tbody) return;

        let items = state.items;

        // Client-side search filter
        const search = state.filters.search.trim().toLowerCase();
        if (search) {
            items = items.filter(b =>
                (b.title || '').toLowerCase().includes(search) ||
                (b.subtitle || '').toLowerCase().includes(search)
            );
        }

        if (!items.length) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:32px;">
                <div style="color:var(--text-secondary,#94a3b8);">
                    <i class="fas fa-image" style="font-size:2rem;margin-bottom:8px;display:block;"></i>
                    ${esc(t('table.empty.title', 'No Banners Found'))}
                </div>
            </td></tr>`;
            return;
        }

        const rows = await Promise.all(items.map(async (banner) => {
            // Fetch image for this banner
            let imgHtml = `<span style="color:var(--text-secondary,#94a3b8);">—</span>`;
            try {
                const imgData = await apiGet(`${IMAGES_API}/by_owner?owner_id=${banner.id}&image_type_id=${IMAGE_TYPE_ID}`);
                const images  = Array.isArray(imgData) ? imgData : (imgData.data || []);
                if (images.length) {
                    const img = images[0];
                    const url = img.url || img.thumb_url || '';
                    if (url) {
                        imgHtml = `<img src="${esc(url)}" alt="" style="width:80px;height:32px;object-fit:cover;border-radius:4px;">`;
                    }
                }
            } catch (_) { /* no image */ }

            const activeClass = banner.is_active ? 'badge-success' : 'badge-warning';
            const activeText  = banner.is_active
                ? t('table.status.active',   'Active')
                : t('table.status.inactive', 'Inactive');

            const dateStr = [banner.start_date, banner.end_date]
                .filter(Boolean)
                .map(d => d.slice(0, 10))
                .join(' → ') || '—';

            const editBtn   = CAN_EDIT   ? `<button class="btn btn-sm btn-outline btn-edit"   data-id="${banner.id}"><i class="fas fa-edit"></i></button>`   : '';
            const deleteBtn = CAN_DELETE ? `<button class="btn btn-sm btn-danger btn-delete"  data-id="${banner.id}"><i class="fas fa-trash"></i></button>` : '';

            return `<tr data-id="${banner.id}">
                <td style="padding:10px 12px;">${esc(banner.id)}</td>
                <td style="padding:10px 12px;">${imgHtml}</td>
                <td style="padding:10px 12px;">
                    <strong>${esc(banner.title || '')}</strong>
                    ${banner.subtitle ? `<br><small style="color:var(--text-secondary,#94a3b8);">${esc(banner.subtitle)}</small>` : ''}
                </td>
                <td style="padding:10px 12px;">${esc(banner.position || '')}</td>
                <td style="padding:10px 12px;">${esc(banner.sort_order ?? 0)}</td>
                <td style="padding:10px 12px;"><span class="badge ${activeClass}">${esc(activeText)}</span></td>
                <td style="padding:10px 12px; font-size:0.82rem;">${esc(dateStr)}</td>
                <td style="padding:10px 12px;">
                    <div style="display:flex;gap:4px;">
                        ${editBtn}
                        ${deleteBtn}
                    </div>
                </td>
            </tr>`;
        }));

        tbody.innerHTML = rows.join('');
        bindTableActions();
    }

    function bindTableActions() {
        document.querySelectorAll('#bannersTable .btn-edit').forEach(btn => {
            btn.addEventListener('click', () => openEditForm(parseInt(btn.dataset.id, 10)));
        });
        document.querySelectorAll('#bannersTable .btn-delete').forEach(btn => {
            btn.addEventListener('click', () => confirmDelete(parseInt(btn.dataset.id, 10)));
        });
    }

    // ═══════════════════════════════════════════════════════
    // FORM OPEN / CLOSE
    // ═══════════════════════════════════════════════════════
    function openAddForm() {
        resetForm();
        state.editingId      = null;
        state.selectedImageId = null;
        const titleEl = $('bannerFormTitle');
        if (titleEl) titleEl.textContent = t('form.add_title', 'Add Banner');
        const formContainer = $('bannerFormContainer');
        if (formContainer) {
            formContainer.style.display = 'block';
            formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    async function openEditForm(id) {
        resetForm();
        state.editingId = id;
        const titleEl = $('bannerFormTitle');
        if (titleEl) titleEl.textContent = t('form.edit_title', 'Edit Banner');
        const formContainer = $('bannerFormContainer');
        if (formContainer) formContainer.style.display = 'block';

        try {
            const data = await apiGet(`${API_URL}/${id}?all_translations=1`);
            const banner = data.data || data;
            populateForm(banner);
            if (formContainer) formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (err) {
            showToast(t('messages.error.load_failed', 'Failed to load data') + ': ' + err.message, 'error');
            if (formContainer) formContainer.style.display = 'none';
        }
    }

    function closeForm() {
        const formContainer = $('bannerFormContainer');
        if (formContainer) formContainer.style.display = 'none';
        resetForm();
    }

    function resetForm() {
        const form = $('bannerForm');
        if (form) form.reset();
        const idEl = $('formId');
        if (idEl) idEl.value = '';
        const imgId = $('bannerImageId');
        if (imgId) imgId.value = '';
        state.selectedImageId  = null;
        state.editingId        = null;
        // reset image preview
        const preview = $('bannerImagePreview');
        if (preview) { preview.src = ''; preview.style.display = 'none'; }
        const links = $('bannerImageLinks');
        if (links) links.innerHTML = '';
        // reset color inputs
        const bg = $('bannerBgColor');
        if (bg) bg.value = '#FFFFFF';
        const tc = $('bannerTextColor');
        if (tc) tc.value = '#000000';
        // reset translation fields
        const enLangFields = ['title', 'subtitle', 'link_text'];
        enLangFields.forEach(f => {
            const el = $(`trans_en_${f}`);
            if (el) el.value = '';
        });
        // clear dynamic translation panels
        const dynPanels = $('bannerTranslations');
        if (dynPanels) dynPanels.innerHTML = '';
        // clear validation
        document.querySelectorAll('#bannerForm .is-invalid').forEach(el => el.classList.remove('is-invalid'));
    }

    function populateForm(banner) {
        const set = (id, val) => { const el = $(id); if (el) el.value = val == null ? '' : val; };

        set('formId',          banner.id);
        set('bannerTitle',     banner.title || '');
        set('bannerSubtitle',  banner.subtitle || '');
        set('bannerLinkUrl',   banner.link_url || '');
        set('bannerLinkText',  banner.link_text || '');
        set('bannerSortOrder', banner.sort_order ?? 0);
        set('bannerBgColor',   banner.background_color || '#FFFFFF');
        set('bannerTextColor', banner.text_color || '#000000');
        set('bannerButtonStyle', banner.button_style || '');

        // Position
        const posEl = $('bannerPosition');
        if (posEl) posEl.value = banner.position || 'homepage_main';

        // Status
        const statusEl = $('bannerIsActive');
        if (statusEl) statusEl.value = String(banner.is_active ?? 1);

        // Dates (convert to datetime-local format)
        if (banner.start_date) {
            const startEl = $('bannerStartDate');
            if (startEl) startEl.value = banner.start_date.slice(0, 16);
        }
        if (banner.end_date) {
            const endEl = $('bannerEndDate');
            if (endEl) endEl.value = banner.end_date.slice(0, 16);
        }

        // Translations
        const translations = banner.translations || {};

        // EN always in fixed fields — if no EN translation record, fall back to
        // the top-level banner fields so the fields are pre-filled for the user.
        const enTrans = translations['en'] || {};
        const set_trans_en = (field, fallback) => {
            const el = $(`trans_en_${field}`);
            if (el) el.value = enTrans[field] || fallback || '';
        };
        set_trans_en('title',     banner.title);
        set_trans_en('subtitle',  banner.subtitle);
        set_trans_en('link_text', banner.link_text);

        // Other languages → dynamic panels
        const dynPanels = $('bannerTranslations');
        if (dynPanels) dynPanels.innerHTML = '';
        Object.entries(translations).forEach(([langCode, lt]) => {
            if (langCode === 'en') return;
            const langName = (state.languages.find(l => l.code === langCode) || {}).name || langCode.toUpperCase();
            const panel = createBannerTranslationPanel(langCode, langName, lt);
            if (dynPanels) dynPanels.appendChild(panel);
        });

        // Load image
        loadBannerImage(banner.id);
    }

    async function loadBannerImage(bannerId) {
        try {
            const data = await apiGet(`${IMAGES_API}/by_owner?owner_id=${encodeURIComponent(bannerId)}&image_type_id=${IMAGE_TYPE_ID}`);
            const images = Array.isArray(data) ? data : (data.data || []);
            if (images.length) {
                const img = images[0];
                state.selectedImageId = img.id;
                const imgIdEl = $('bannerImageId');
                if (imgIdEl) imgIdEl.value = img.id;
                const url = img.url || img.thumb_url || '';
                const preview = $('bannerImagePreview');
                if (preview && url) {
                    preview.src = url;
                    preview.style.display = 'block';
                }
                const links = $('bannerImageLinks');
                if (links && url) {
                    links.innerHTML = `<a href="${esc(url)}" target="_blank" style="font-size:0.8rem;color:var(--primary-color,#3b82f6);">View</a>`;
                }
            }
        } catch (_) { /* no image found, that's ok */ }
    }

    // ═══════════════════════════════════════════════════════
    // FORM SUBMIT
    // ═══════════════════════════════════════════════════════
    async function handleFormSubmit(e) {
        e.preventDefault();

        // Validate EN title
        const enTitleEl = $('trans_en_title');
        if (!enTitleEl || !enTitleEl.value.trim()) {
            if (enTitleEl) enTitleEl.classList.add('is-invalid');
            showToast(t('messages.error.en_required', 'English title is required'), 'error');
            enTitleEl && enTitleEl.focus();
            return;
        }
        if (enTitleEl) enTitleEl.classList.remove('is-invalid');

        const saveBtn  = $('bannerSaveBtn');
        const saveTxt  = $('bannerSaveBtnText');
        if (saveBtn)  saveBtn.disabled = true;
        if (saveTxt)  saveTxt.textContent = t('form.buttons.saving', 'Saving...');

        try {
            const payload = buildPayload();

            let result;
            if (state.editingId) {
                result = await apiPut(`${API_URL}/${state.editingId}`, payload);
            } else {
                result = await apiPost(API_URL, payload);
            }

            const savedBanner = result.data || result;
            showToast(
                state.editingId
                    ? t('messages.success.updated', 'Banner updated successfully')
                    : t('messages.success.created', 'Banner created successfully'),
                'success'
            );
            closeForm();
            await loadBanners();
        } catch (err) {
            console.error('[Banners] save failed:', err);
            showToast(t('messages.error.save_failed', 'Failed to save data') + ': ' + err.message, 'error');
        } finally {
            if (saveBtn)  saveBtn.disabled = false;
            if (saveTxt)  saveTxt.textContent = t('form.buttons.save', 'Save');
        }
    }

    function buildPayload() {
        const get = (id) => { const el = $(id); return el ? el.value.trim() : ''; };

        // Build translations object
        const translations = {};

        // EN from fixed translation fields — fall back to main banner fields so EN
        // translation is always saved even when the user only fills the top fields.
        const enTitle    = get('trans_en_title')    || get('bannerTitle');
        const enSubtitle = get('trans_en_subtitle') || get('bannerSubtitle');
        const enLinkText = get('trans_en_link_text') || get('bannerLinkText');
        if (enTitle || enSubtitle || enLinkText) {
            translations['en'] = { title: enTitle, subtitle: enSubtitle, link_text: enLinkText };
        }

        // Collect from dynamic translation panels
        document.querySelectorAll('#bannerTranslations .banner-translation-panel').forEach(panel => {
            const langCode = panel.dataset.lang;
            if (!langCode || langCode === 'en') return;
            const title    = (panel.querySelector('.btrans-title')    || {}).value || '';
            const subtitle = (panel.querySelector('.btrans-subtitle') || {}).value || '';
            const linkText = (panel.querySelector('.btrans-link-text')|| {}).value || '';
            if (title || subtitle || linkText) {
                translations[langCode] = { title, subtitle, link_text: linkText };
            }
        });

        // Use EN translation title as main title (guaranteed above), fallback to bannerTitle
        const mainTitle = (translations.en && translations.en.title) || get('bannerTitle');

        return {
            title:            mainTitle,
            subtitle:         (translations.en && translations.en.subtitle) || get('bannerSubtitle'),
            link_url:         get('bannerLinkUrl'),
            link_text:        (translations.en && translations.en.link_text) || get('bannerLinkText'),
            position:         get('bannerPosition') || 'homepage_main',
            background_color: get('bannerBgColor') || '#FFFFFF',
            text_color:       get('bannerTextColor') || '#000000',
            button_style:     get('bannerButtonStyle'),
            sort_order:       parseInt(get('bannerSortOrder'), 10) || 0,
            is_active:        parseInt(get('bannerIsActive'), 10) ?? 1,
            start_date:       get('bannerStartDate') || null,
            end_date:         get('bannerEndDate') || null,
            image_id:         state.selectedImageId || null,
            translations:     translations
        };
    }

    // ═══════════════════════════════════════════════════════
    // DELETE
    // ═══════════════════════════════════════════════════════
    async function confirmDelete(id) {
        const confirmed = window.confirm(t('table.actions.confirm_delete', 'Are you sure you want to delete this banner?'));
        if (!confirmed) return;

        try {
            await apiDelete(`${API_URL}/${id}`);
            showToast(t('messages.success.deleted', 'Banner deleted successfully'), 'success');
            await loadBanners();
        } catch (err) {
            console.error('[Banners] delete failed:', err);
            showToast(t('messages.error.delete_failed', 'Failed to delete data') + ': ' + err.message, 'error');
        }
    }

    // ═══════════════════════════════════════════════════════
    // IMAGE SELECT (Inline Media Studio iframe)
    // ═══════════════════════════════════════════════════════
    function openMediaStudio() {
        const tenantId = (window.APP_CONFIG && window.APP_CONFIG.TENANT_ID) || 1;
        const ownerId  = state.editingId || 0;
        const lang     = LANG || 'en';
        const src      = `/admin/fragments/media_studio.php?embedded=1&tenant_id=${encodeURIComponent(tenantId)}&owner_id=${encodeURIComponent(ownerId)}&image_type_id=${IMAGE_TYPE_ID}&lang=${encodeURIComponent(lang)}&mode=select&limit=1`;

        const overlay = $('bannerMediaStudioOverlay');
        const frame   = $('bannerMediaStudioFrame');
        if (!overlay || !frame) return;

        frame.src = src;
        overlay.style.display = 'flex';
    }

    function closeMediaStudio() {
        const overlay = $('bannerMediaStudioOverlay');
        const frame   = $('bannerMediaStudioFrame');
        if (overlay) overlay.style.display = 'none';
        // Reset src to stop any pending requests
        if (frame) frame.src = 'about:blank';
    }

    function removeImage() {
        state.selectedImageId = null;
        const imgIdEl  = $('bannerImageId');
        if (imgIdEl)  imgIdEl.value = '';
        const preview = $('bannerImagePreview');
        if (preview)  { preview.src = ''; preview.style.display = 'none'; }
        const links   = $('bannerImageLinks');
        if (links)    links.innerHTML = '';
    }

    // Listen for media studio ImageStudio:selected CustomEvent (dispatched to window.parent)
    window.addEventListener('ImageStudio:selected', function (ev) {
        const img = ev.detail;
        if (!img) return;
        // media_studio.js dispatches a single object when selectionLimit=1, but can dispatch
        // an array when multi-select is allowed. We handle both defensively.
        const selected = Array.isArray(img) ? img[0] : img;
        if (!selected) return;

        state.selectedImageId = selected.id;
        const imgIdEl = $('bannerImageId');
        if (imgIdEl) imgIdEl.value = selected.id;

        const url = selected.url || selected.thumb_url || '';
        const preview = $('bannerImagePreview');
        if (preview && url) {
            preview.src = url;
            preview.style.display = 'block';
        }

        const links = $('bannerImageLinks');
        if (links && url) {
            links.innerHTML = `<a href="${esc(url)}" target="_blank" style="font-size:0.8rem;color:var(--primary-color,#3b82f6);">View</a>`;
        }
        closeMediaStudio();
    });

    // Listen for media studio close event
    window.addEventListener('ImageStudio:close', function () {
        closeMediaStudio();
    });

    // ═══════════════════════════════════════════════════════
    // TRANSLATIONS
    // ═══════════════════════════════════════════════════════
    async function loadLanguages() {
        try {
            const res = await apiGet(`${LANGUAGES_API}?format=json`);
            const items = res?.data?.items || res?.data || res || [];
            state.languages = Array.isArray(items) ? items : [];
            const langSelect = $('bannerLangSelect');
            if (!langSelect) return;
            langSelect.innerHTML = `<option value="">${t('form.translations.choose_language', 'Choose language')}</option>`;
            state.languages.forEach(lang => {
                if (lang.code === 'en') return; // EN is always shown as fixed panel
                const opt = document.createElement('option');
                opt.value = lang.code;
                opt.textContent = lang.name || lang.code;
                langSelect.appendChild(opt);
            });
        } catch (err) {
            console.warn('[Banners] Failed to load languages:', err);
        }
    }

    async function loadButtonStyles() {
        const select = $('bannerButtonStyle');
        if (!select) return;
        // If already populated by PHP (more than 1 option), skip
        if (select.options.length > 1) return;
        try {
            const res = await apiGet(`${BUTTON_STYLES_API}?format=json&is_active=1`);
            const items = res?.data?.items || res?.data || (Array.isArray(res) ? res : []);
            if (!Array.isArray(items) || items.length === 0) return;
            items.forEach(bs => {
                const slug = bs.slug || bs.id || '';
                const name = bs.name || bs.slug || slug;
                if (!slug) return;
                const opt = document.createElement('option');
                opt.value = slug;
                opt.textContent = name;
                select.appendChild(opt);
            });
        } catch (err) {
            console.warn('[Banners] Failed to load button styles:', err);
        }
    }

    function createBannerTranslationPanel(langCode, langName, data) {
        const panel = document.createElement('div');
        panel.className = 'translation-panel banner-translation-panel';
        panel.dataset.lang = langCode;
        const dir = (state.languages.find(l => l.code === langCode) || {}).direction || 'ltr';
        panel.innerHTML = `
            <div class="translation-panel-header">
                <span class="lang-badge">${esc(langCode.toUpperCase())}</span>
                <span>${esc(langName)}</span>
                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.banner-translation-panel').remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="translation-panel-body">
                <div class="form-group">
                    <label>${t('form.translations.title_label', 'Title')}</label>
                    <input type="text" class="form-control btrans-title" dir="${esc(dir)}" value="${esc(data.title || '')}" placeholder="${t('form.translations.title_in_lang', 'Title in')} ${esc(langName)}">
                </div>
                <div class="form-group">
                    <label>${t('form.translations.subtitle_label', 'Subtitle')}</label>
                    <input type="text" class="form-control btrans-subtitle" dir="${esc(dir)}" value="${esc(data.subtitle || '')}">
                </div>
                <div class="form-group">
                    <label>${t('form.translations.link_text_label', 'Button Text')}</label>
                    <input type="text" class="form-control btrans-link-text" dir="${esc(dir)}" value="${esc(data.link_text || '')}">
                </div>
            </div>
        `;
        return panel;
    }

    function addBannerTranslation() {
        const langSelect = $('bannerLangSelect');
        if (!langSelect || !langSelect.value) return;
        const langCode = langSelect.value;
        const langName = langSelect.options[langSelect.selectedIndex].textContent;

        // Prevent duplicates
        if (document.querySelector(`#bannerTranslations [data-lang="${langCode}"]`)) {
            showToast(t('messages.translation_exists', 'Translation already added'), 'warning');
            return;
        }

        const panel = createBannerTranslationPanel(langCode, langName, {});
        const container = $('bannerTranslations');
        if (container) container.appendChild(panel);
        langSelect.value = '';
    }

    // ═══════════════════════════════════════════════════════
    // FILTER / SEARCH
    // ═══════════════════════════════════════════════════════
    function bindFilters() {
        const searchEl   = $('bannerSearch');
        const posEl      = $('bannerFilterPosition');
        const statusEl   = $('bannerFilterStatus');
        const refreshBtn = $('btnRefresh');

        if (searchEl) {
            searchEl.addEventListener('input', () => {
                state.filters.search = searchEl.value;
                renderTable();
            });
        }
        if (posEl) {
            posEl.addEventListener('change', () => {
                state.filters.position = posEl.value;
                loadBanners();
            });
        }
        if (statusEl) {
            statusEl.addEventListener('change', () => {
                state.filters.status = statusEl.value;
                loadBanners();
            });
        }
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => loadBanners());
        }
    }

    // ═══════════════════════════════════════════════════════
    // BIND UI EVENTS
    // ═══════════════════════════════════════════════════════
    // Shared escape-key handler (hoisted outside bindEvents to avoid multiple registrations)
    function handleEscapeKey(e) {
        if (e.key === 'Escape') {
            const overlay = $('bannerMediaStudioOverlay');
            if (overlay && overlay.style.display !== 'none') closeMediaStudio();
        }
    }

    function bindEvents() {
        const addBtn       = $('btnAddBanner');
        const closeBtn     = $('btnCloseForm');
        const cancelBtn    = $('btnCancelForm');
        const form         = $('bannerForm');
        const selectImgBtn = $('bannerSelectImageBtn');
        const removeImgBtn = $('bannerRemoveImageBtn');
        const msCloseBtn   = $('bannerMediaStudioClose');
        const msOverlay    = $('bannerMediaStudioOverlay');
        const addLangBtn   = $('bannerAddLangBtn');

        if (addBtn)       addBtn.addEventListener('click', openAddForm);
        if (closeBtn)     closeBtn.addEventListener('click', closeForm);
        if (cancelBtn)    cancelBtn.addEventListener('click', closeForm);
        if (form)         form.addEventListener('submit', handleFormSubmit);
        if (selectImgBtn) selectImgBtn.addEventListener('click', openMediaStudio);
        if (removeImgBtn) removeImgBtn.addEventListener('click', removeImage);
        if (addLangBtn)   addLangBtn.addEventListener('click', addBannerTranslation);
        if (msCloseBtn)   msCloseBtn.addEventListener('click', closeMediaStudio);

        // Click on overlay backdrop closes modal
        if (msOverlay) {
            msOverlay.addEventListener('click', function (e) {
                if (e.target === msOverlay) closeMediaStudio();
            });
        }

        // Escape key closes media studio modal (registered once via named function)
        document.removeEventListener('keydown', handleEscapeKey);
        document.addEventListener('keydown', handleEscapeKey);

        bindFilters();
    }

    // ═══════════════════════════════════════════════════════
    // INIT
    // ═══════════════════════════════════════════════════════
    async function init() {
        bindEvents();
        await Promise.all([
            loadBanners(),
            loadLanguages(),
            loadButtonStyles()
        ]);
        console.log('[Banners] ✓ Initialized');
    }

    // Expose module
    window.Banners = { init };

    // Auto-init if DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // give translations a chance to load first
        setTimeout(init, 100);
    }

})();