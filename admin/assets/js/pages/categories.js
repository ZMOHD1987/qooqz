/**
 * /admin/assets/js/pages/categories.js — Production v2.0
 *
 * ─ إصلاحات جوهرية ──────────────────────────────────────────
 * ✅ زر الحفظ: استبدل AF.Form.validate() الصامت بفحص صريح مع رسالة
 * ✅ استبدل AF.api() بـ fetch مباشر — لا تبعية على AdminFramework للحفظ
 * ✅ الترجمات من CONFIG.strings فقط — لا fetch مكرّر
 * ✅ notify() بـ cat- prefix يتطابق مع CSS
 * ✅ showState() موحّدة (catLoading/catEmpty/catError/catTableContainer)
 * ✅ credentials: 'same-origin' على كل fetch
 * ✅ ESC يُغلق form card
 * ✅ Admin.page.register
 * ✅ Excel import محفوظ كاملاً
 */
(function () {
    'use strict';

    const CFG  = window.CATEGORIES_CONFIG || {};
    const API  = CFG.apiUrl       || '/api/categories';
    const LANG_API    = CFG.languagesApi  || '/api/languages';
    const IMG_TYPES_API = CFG.imageTypesApi || '/api/image-types';
    const IMAGES_API  = CFG.imagesApi     || '/api/images';

    // ── i18n ──────────────────────────────────────────────────
    // الترجمات مُحقَنة من PHP في CONFIG.strings — لا fetch مكرّر
    const S = CFG.strings || {};
    function t(key, fallback) {
        const parts = key.split('.');
        let val = S;
        for (const k of parts) {
            if (val && typeof val === 'object' && k in val) val = val[k];
            else return fallback || key;
        }
        return typeof val === 'string' ? val : (fallback || key);
    }
    function tReplace(key, map) {
        let text = t(key);
        for (const [k, v] of Object.entries(map)) {
            text = text.replace(new RegExp(`{${k}}`, 'g'), v);
        }
        return text;
    }

    // ── State ─────────────────────────────────────────────────
    const state = {
        page:        1,
        perPage:     25,
        filters:     {},
        parents:     [],
        deletedTrans:[],
    };

    let el             = {};
    let availableLangs = [];
    let imageTypes     = [];

    // ════════════════════════════════════════════════════════
    // FETCH HELPER
    // ════════════════════════════════════════════════════════
    async function apiFetch(url, options = {}) {
        const defaults = {
            credentials: 'same-origin',
            headers: {
                'Content-Type':     'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token':     CFG.csrfToken || '',
            },
        };
        const config = {
            ...defaults,
            ...options,
            headers: { ...defaults.headers, ...(options.headers || {}) },
        };
        const res  = await fetch(url, config);
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || data.error || `HTTP ${res.status}`);
        return data;
    }

    function extractItems(result) {
        if (!result)                             return [];
        if (Array.isArray(result))               return result;
        if (Array.isArray(result.data))          return result.data;
        if (Array.isArray(result.data?.items))   return result.data.items;
        if (Array.isArray(result.data?.data))    return result.data.data;
        if (Array.isArray(result.items))         return result.items;
        return [];
    }

    function tenantParam() {
        if (CFG.isSuperAdmin) return {};
        return { tenant_id: CFG.tenantId || 1 };
    }

    // ════════════════════════════════════════════════════════
    // TOAST NOTIFICATIONS  (cat- prefix → matches CSS)
    // ════════════════════════════════════════════════════════
    function notify(message, type = 'info') {
        const AF = window.AdminFramework;
        if (AF) {
            if (type === 'success' && AF.success) return AF.success(message);
            if (type === 'error'   && AF.error)   return AF.error(message);
            if (type === 'warning' && AF.warning)  return AF.warning(message);
            if (AF.notify) return AF.notify(message, type);
        }
        let container = document.getElementById('catNotifications');
        if (!container) {
            container = document.createElement('div');
            container.id = 'catNotifications';
            container.className = 'cat-notifications';
            const page = document.getElementById('categoriesPageContainer');
            (page || document.body).insertBefore(container, (page || document.body).firstChild);
        }
        const toast = document.createElement('div');
        toast.className = `cat-toast cat-toast-${type}`;
        toast.setAttribute('role', 'alert');
        const msg = document.createElement('span');
        msg.textContent = message;
        toast.appendChild(msg);
        const close = document.createElement('button');
        close.className = 'cat-toast-close';
        close.setAttribute('aria-label', 'Close');
        close.textContent = '\u00d7';
        close.addEventListener('click', () => toast.remove());
        toast.appendChild(close);
        container.appendChild(toast);
        setTimeout(() => { if (toast.parentNode) toast.remove(); }, 4500);
    }

    // ════════════════════════════════════════════════════════
    // TABLE STATE
    // ════════════════════════════════════════════════════════
    function showState(which, msg = '') {
        const loading   = document.getElementById('catLoading');
        const empty     = document.getElementById('catEmpty');
        const error     = document.getElementById('catError');
        const container = document.getElementById('catTableContainer');
        const errMsg    = document.getElementById('catErrorMessage');

        [loading, empty, error, container].forEach(e => { if (e) e.style.display = 'none'; });

        switch (which) {
            case 'loading': if (loading)   loading.style.display   = 'flex';  break;
            case 'empty':   if (empty)     empty.style.display     = 'flex';  break;
            case 'error':
                if (error)  error.style.display = 'flex';
                if (errMsg && msg) errMsg.textContent = msg;
                break;
            default:        if (container) container.style.display = 'block'; break;
        }
    }

    // ════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════
    function esc(txt) {
        if (txt == null) return '';
        const d = document.createElement('div');
        d.textContent = String(txt);
        return d.innerHTML;
    }

    function slugify(text) {
        return text.toLowerCase().trim()
            .replace(/[\s_]+/g, '-')
            .replace(/[^\w-]/g, '')
            .replace(/--+/g, '-')
            .replace(/^-|-$/g, '');
    }

    // ════════════════════════════════════════════════════════
    // LOAD LANGUAGES
    // ════════════════════════════════════════════════════════
    async function loadLanguages() {
        if (!el.langSelect) return;
        el.langSelect.innerHTML = `<option value="">${t('form.translations.choose_lang','Choose language')}</option>`;
        try {
            const data = await apiFetch(`${LANG_API}?format=json`);
            availableLangs = extractItems(data);
            availableLangs.forEach(l => {
                const o = document.createElement('option');
                o.value       = l.code;
                o.textContent = `${l.code.toUpperCase()} — ${l.name}`;
                el.langSelect.appendChild(o);
            });
        } catch (e) {
            console.warn('[Categories] loadLanguages:', e);
        }
    }

    // ════════════════════════════════════════════════════════
    // LOAD IMAGE TYPES
    // ════════════════════════════════════════════════════════
    async function loadImageTypes() {
        if (!el.imageTypeSelect) return;
        try {
            const data = await apiFetch(IMG_TYPES_API);
            imageTypes = extractItems(data);
            el.imageTypeSelect.innerHTML = '';
            imageTypes.forEach(type => {
                const o = document.createElement('option');
                o.value = type.id;
                o.textContent = type.name;
                o.dataset.description = type.description || '';
                el.imageTypeSelect.appendChild(o);
                if (type.name === 'category') {
                    el.imageTypeSelect.value = type.id;
                    if (el.imageTypeDesc) el.imageTypeDesc.textContent = type.description || '';
                }
            });
            el.imageTypeSelect.onchange = () => {
                const sel = imageTypes.find(tp => tp.id == el.imageTypeSelect.value);
                if (el.imageTypeDesc) el.imageTypeDesc.textContent = sel?.description || '';
            };
        } catch (e) {
            console.warn('[Categories] loadImageTypes:', e);
            el.imageTypeSelect.innerHTML = '<option value="1">category</option>';
        }
    }

    // ════════════════════════════════════════════════════════
    // LOAD PARENTS
    // ════════════════════════════════════════════════════════
    async function loadParents() {
        try {
            const params = new URLSearchParams({ parents: '1', limit: 1000, lang: CFG.lang || 'en', format: 'json', ...tenantParam() });
            const data   = await apiFetch(`${API}?${params}`);
            state.parents = extractItems(data);

            [el.formParentId, el.parentFilter].forEach(select => {
                if (!select) return;
                const first = select.options[0];
                select.innerHTML = '';
                if (first) select.appendChild(first);
                state.parents.forEach(p => {
                    const o = document.createElement('option');
                    o.value       = p.id;
                    o.textContent = p.name || `Category ${p.id}`;
                    select.appendChild(o);
                });
            });
        } catch (e) {
            console.warn('[Categories] loadParents:', e);
        }
    }

    // ════════════════════════════════════════════════════════
    // TRANSLATION PANELS
    // ════════════════════════════════════════════════════════
    function createTranslationPanel(code, data = {}) {
        if (!el.translations) return;
        const existing = el.translations.querySelector(`[data-lang="${code}"]`);
        if (existing) existing.remove();

        const langUpper = code.toUpperCase();
        const isDefault = code === 'en';

        const div = document.createElement('div');
        div.className = 'cat-translation-panel';
        div.dataset.lang = code;
        div.innerHTML = `
            <div class="cat-translation-header">
                <h5>
                    <i class="fas fa-globe" aria-hidden="true"></i>
                    ${esc(langUpper)}
                    ${isDefault ? '<small style="color:var(--success-color,#10b981);font-size:0.75rem;">(default)</small>' : ''}
                </h5>
                ${isDefault ? '' : `<button type="button" class="btn btn-sm btn-danger cat-remove-trans"
                                            data-lang="${esc(code)}" aria-label="Remove">
                                        <i class="fas fa-times" aria-hidden="true"></i>
                                    </button>`}
            </div>
            <div class="cat-translation-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Name ${isDefault ? '*' : ''}</label>
                        <input class="form-control" name="translations[${code}][name]"
                               value="${esc(data.name || '')}" ${isDefault ? 'required' : ''}>
                    </div>
                    <div class="form-group">
                        <label>Slug</label>
                        <input class="form-control" name="translations[${code}][slug]"
                               value="${esc(data.slug || '')}">
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" name="translations[${code}][description]" rows="2">${esc(data.description || '')}</textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Meta Title</label>
                        <input class="form-control" name="translations[${code}][meta_title]"
                               value="${esc(data.meta_title || '')}">
                    </div>
                    <div class="form-group">
                        <label>Meta Keywords</label>
                        <input class="form-control" name="translations[${code}][meta_keywords]"
                               value="${esc(data.meta_keywords || '')}">
                    </div>
                </div>
                <div class="form-group">
                    <label>Meta Description</label>
                    <textarea class="form-control" name="translations[${code}][meta_description]" rows="2">${esc(data.meta_description || '')}</textarea>
                </div>
            </div>`;

        if (!isDefault) {
            div.querySelector('.cat-remove-trans')?.addEventListener('click', () => {
                const catId = el.formId?.value ? parseInt(el.formId.value) : null;
                state.deletedTrans.push({ language_code: code, category_id: catId });
                div.remove();
            });
        }

        el.translations.appendChild(div);
    }

    // ════════════════════════════════════════════════════════
    // LOAD DATA
    // ════════════════════════════════════════════════════════
    async function load(page = 1) {
        showState('loading');
        state.page = page;

        const params = new URLSearchParams({ page, limit: state.perPage, lang: CFG.lang || 'en', format: 'json', ...tenantParam(), ...state.filters });

        try {
            const result = await apiFetch(`${API}?${params}`);
            const items  = extractItems(result);
            const meta   = result.data?.meta || result.meta || {};
            const total  = meta.total ?? items.length;

            // Fetch images
            if (items.length > 0) {
                await Promise.all(items.map(async item => {
                    if (item.image_url) return;
                    try {
                        const r = await fetch(`${IMAGES_API}/by_owner?owner_id=${item.id}&image_type_id=1`, { credentials: 'same-origin' });
                        const d = await r.json();
                        const imgs = Array.isArray(d?.data) ? d.data : [];
                        if (imgs.length) item.image_url = imgs[0].url;
                    } catch (_) {}
                }));
            }

            if (items.length === 0) {
                showState('empty');
            } else {
                showState('table');
                renderTable(items);
                renderPagination(page, total);
            }
        } catch (e) {
            console.error('[Categories] load:', e);
            showState('error', e.message || t('messages.error.load_failed', 'Failed to load'));
        }
    }

    // ════════════════════════════════════════════════════════
    // RENDER TABLE
    // ════════════════════════════════════════════════════════
    function renderTable(items) {
        const tbody = document.getElementById('catTableBody');
        if (!tbody) return;

        tbody.innerHTML = items.map(item => {
            const img       = item.image_url
                ? `<img src="${esc(item.image_url)}" alt="" loading="lazy">`
                : `<img src="/assets/images/no-image.png" alt="" loading="lazy">`;
            const statusCls = item.is_active ? 'badge-active' : 'badge-inactive';
            const statusTxt = item.is_active ? t('table.status.active','Active') : t('table.status.inactive','Inactive');
            const tenantCol = CFG.isSuperAdmin ? `<td>${esc(item.tenant_id)}</td>` : '';

            // ✅ btn-primary للتعديل
            const editBtn = CFG.permissions?.canEdit
                ? `<button class="btn btn-sm btn-primary cat-edit-btn" data-id="${esc(item.id)}" aria-label="${t('table.actions.edit','Edit')}">
                       <i class="fas fa-edit" aria-hidden="true"></i>
                   </button>`
                : '';
            const delBtn = CFG.permissions?.canDelete
                ? `<button class="btn btn-sm btn-danger cat-del-btn" data-id="${esc(item.id)}" aria-label="${t('table.actions.delete','Delete')}">
                       <i class="fas fa-trash" aria-hidden="true"></i>
                   </button>`
                : '';

            return `<tr data-id="${esc(item.id)}">
                <td>${esc(item.id)}</td>
                ${tenantCol}
                <td>${img}</td>
                <td><strong>${esc(item.name || '')}</strong></td>
                <td>${esc(item.slug || '')}</td>
                <td>${esc(item.parent_name || 'Root')}</td>
                <td>${esc(item.sort_order ?? 0)}</td>
                <td><span class="badge ${statusCls}">${statusTxt}</span></td>
                <td>${item.is_featured ? t('form.fields.featured.yes','Yes') : t('form.fields.featured.no','No')}</td>
                <td><div class="table-actions">${editBtn}${delBtn}</div></td>
            </tr>`;
        }).join('');

        tbody.querySelectorAll('.cat-edit-btn').forEach(b =>
            b.addEventListener('click', () => editCategory(b.dataset.id)));
        tbody.querySelectorAll('.cat-del-btn').forEach(b =>
            b.addEventListener('click', () => removeCategory(b.dataset.id)));
    }

    // ════════════════════════════════════════════════════════
    // PAGINATION
    // ════════════════════════════════════════════════════════
    function renderPagination(page, total) {
        const totalPages = Math.max(1, Math.ceil(total / state.perPage));
        const start = total > 0 ? (page - 1) * state.perPage + 1 : 0;
        const end   = Math.min(page * state.perPage, total);

        const infoEl = document.getElementById('catPaginationInfo');
        if (infoEl) infoEl.textContent = total > 0 ? `${start}–${end} / ${total}` : t('table.empty.title','No records');

        const pagEl = document.getElementById('catPagination');
        if (!pagEl) return;
        pagEl.innerHTML = '';
        if (totalPages <= 1) return;

        const makeBtn = (label, target, active = false, disabled = false) => {
            const btn = document.createElement('button');
            btn.className = 'pagination-btn' + (active ? ' active' : '');
            btn.innerHTML = label;
            btn.disabled  = disabled;
            if (!disabled) btn.addEventListener('click', () => load(target));
            return btn;
        };

        pagEl.appendChild(makeBtn('&laquo;', page - 1, false, page <= 1));
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)) {
                pagEl.appendChild(makeBtn(String(i), i, i === page, i === page));
            } else if (i === page - 3 || i === page + 3) {
                const sp = document.createElement('span');
                sp.className = 'pagination-dots';
                sp.textContent = '\u2026';
                pagEl.appendChild(sp);
            }
        }
        pagEl.appendChild(makeBtn('&raquo;', page + 1, false, page >= totalPages));
    }

    // ════════════════════════════════════════════════════════
    // FORM HELPERS
    // ════════════════════════════════════════════════════════
    function showForm() {
        const card = document.getElementById('categoryFormContainer');
        if (card) {
            card.style.display = 'block';
            setTimeout(() => card.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
        }
    }

    function hideForm() {
        const card = document.getElementById('categoryFormContainer');
        if (card) card.style.display = 'none';
    }

    function resetForm() {
        if (el.form)     el.form.reset();
        if (el.formId)   el.formId.value = '';
        if (el.imageId)  el.imageId.value = '';
        if (el.imagePreview) el.imagePreview.src = '/assets/images/no-image.png';
        const links = document.getElementById('catImageLinks');
        if (links) links.innerHTML = '';
        if (el.translations) el.translations.innerHTML = '';
        state.deletedTrans = [];
    }

    function collectTranslations() {
        const translations = [];
        el.translations?.querySelectorAll('[data-lang]').forEach(panel => {
            const code = panel.dataset.lang;
            const get  = name => panel.querySelector(`[name="translations[${code}][${name}]"]`)?.value?.trim() || '';
            translations.push({
                language_code:    code,
                name:             get('name'),
                slug:             get('slug'),
                description:      get('description'),
                meta_title:       get('meta_title'),
                meta_description: get('meta_description'),
                meta_keywords:    get('meta_keywords'),
            });
        });
        return translations;
    }

    // ════════════════════════════════════════════════════════
    // ADD
    // ════════════════════════════════════════════════════════
    function addCategory() {
        resetForm();
        if (el.formId) el.formId.value = '';
        if (el.btnDelete) el.btnDelete.style.display = 'none';
        const title = document.getElementById('formTitle');
        if (title) title.textContent = t('form.add_title', 'Add Category');
        createTranslationPanel('en', {});
        showForm();
    }

    // ════════════════════════════════════════════════════════
    // EDIT
    // ════════════════════════════════════════════════════════
    async function editCategory(id) {
        try {
            const tenantQs = CFG.isSuperAdmin ? '' : `&tenant_id=${CFG.tenantId || 1}`;
            const result   = await apiFetch(`${API}/${id}?format=json&lang=${CFG.lang}&all_translations=1${tenantQs}`);
            const payload  = result.data || result;
            const item     = Array.isArray(payload) ? payload[0] : payload;
            if (!item) throw new Error(t('messages.error.not_found', 'Not found'));

            resetForm();
            if (el.formId)       el.formId.value          = String(item.id);
            if (el.formName)     el.formName.value         = item.name          || '';
            if (el.formSlug)     el.formSlug.value         = item.slug          || '';
            if (el.formParentId) el.formParentId.value     = item.parent_id     ? String(item.parent_id) : '';
            if (el.formSortOrder)el.formSortOrder.value    = String(item.sort_order ?? 0);
            if (el.formIsActive) el.formIsActive.value     = item.is_active     ? '1' : '0';
            if (el.formIsFeatured)el.formIsFeatured.value  = item.is_featured   ? '1' : '0';
            if (el.formDesc)     el.formDesc.value         = item.description   || '';
            if (el.imageId)      el.imageId.value          = item.image_id      ? String(item.image_id) : '';
            if (el.imagePreview && item.image_url) el.imagePreview.src = item.image_url;

            const title = document.getElementById('formTitle');
            if (title) title.textContent = t('form.edit_title', 'Edit Category');
            if (el.btnDelete) el.btnDelete.style.display = 'inline-flex';

            // Translations
            const trans = item.translations;
            if (Array.isArray(trans)) {
                trans.forEach(tr => createTranslationPanel(tr.language_code, tr));
            } else if (trans && typeof trans === 'object') {
                Object.entries(trans).forEach(([code, tr]) => createTranslationPanel(code, tr));
            }
            if (el.translations && !el.translations.querySelector('[data-lang="en"]')) {
                createTranslationPanel('en', {});
            }

            showForm();
        } catch (e) {
            console.error('[Categories] editCategory:', e);
            notify(t('messages.error.load_failed', 'Failed to load'), 'error');
        }
    }

    // ════════════════════════════════════════════════════════
    // SAVE — إصلاح زر الحفظ
    // ════════════════════════════════════════════════════════
    async function saveCategory(e) {
        e.preventDefault();

        // ── فحص صريح للحقول المطلوبة ──
        const name = el.formName?.value?.trim();
        const slug = el.formSlug?.value?.trim();
        if (!name) {
            notify(t('form.fields.name.required', 'Name is required'), 'error');
            el.formName?.focus();
            return;
        }
        if (!slug) {
            notify(t('form.fields.slug.required', 'Slug is required'), 'warning');
            el.formSlug?.focus();
            return;
        }

        const id     = el.formId?.value?.trim() || '';
        const isEdit = !!id;

        const body = {
            tenant_id:          parseInt(el.formTenantId?.value || CFG.tenantId) || CFG.tenantId || 1,
            name,
            slug,
            parent_id:          el.formParentId?.value  ? parseInt(el.formParentId.value) : null,
            sort_order:         parseInt(el.formSortOrder?.value || 0) || 0,
            is_active:          el.formIsActive?.value   === '1' ? 1 : 0,
            is_featured:        el.formIsFeatured?.value === '1' ? 1 : 0,
            description:        el.formDesc?.value?.trim()        || '',
            image_id:           el.imageId?.value ? parseInt(el.imageId.value) : null,
            translations:       collectTranslations(),
            deleted_translations: [...state.deletedTrans],
        };
        if (isEdit) body.id = parseInt(id);

        // Disable save button
        if (el.btnSubmit) {
            el.btnSubmit.disabled = true;
            el.btnSubmit.innerHTML = `<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> ${isEdit ? t('form.buttons.updating','Updating…') : t('form.buttons.saving','Saving…')}`;
        }

        try {
            const url    = isEdit ? `${API}/${body.id}` : API;
            const result = await apiFetch(url, { method: isEdit ? 'PUT' : 'POST', body: JSON.stringify(body) });

            if (result.success !== false) {
                notify(isEdit
                    ? t('messages.success.updated', 'Updated successfully')
                    : t('messages.success.created', 'Created successfully'),
                    'success'
                );
                state.deletedTrans = [];
                hideForm();
                await load(state.page);
            } else {
                notify(result.message || t('messages.error.save_failed', 'Save failed'), 'error');
            }
        } catch (err) {
            console.error('[Categories] saveCategory:', err);
            notify(err.message || t('messages.error.save_failed', 'Save failed'), 'error');
        } finally {
            if (el.btnSubmit) {
                el.btnSubmit.disabled = false;
                el.btnSubmit.innerHTML = `<i class="fas fa-save" aria-hidden="true"></i> ${t('form.buttons.save','Save')}`;
            }
        }
    }

    // ════════════════════════════════════════════════════════
    // DELETE
    // ════════════════════════════════════════════════════════
    async function removeCategory(id) {
        if (!confirm(t('table.actions.confirm_delete', 'Delete this category?'))) return;
        try {
            await apiFetch(`${API}/${id}`, { method: 'DELETE', body: JSON.stringify({ id, ...tenantParam() }) });
            notify(t('messages.success.deleted', 'Deleted successfully'), 'success');
            hideForm();
            await load(state.page);
        } catch (e) {
            console.error('[Categories] removeCategory:', e);
            notify(t('messages.error.delete_failed', 'Delete failed'), 'error');
        }
    }

    // ════════════════════════════════════════════════════════
    // FILTERS
    // ════════════════════════════════════════════════════════
    function applyFilters() {
        state.filters = {};
        const search = el.searchInput?.value?.trim();
        if (search) state.filters.search = search;
        const parent = el.parentFilter?.value;
        if (parent) state.filters.parent_id = parent;
        const status = el.statusFilter?.value;
        if (status !== '') state.filters.is_active = status;
        const featured = el.featuredFilter?.value;
        if (featured !== '') state.filters.is_featured = featured;
        if (CFG.isSuperAdmin && el.tenantFilter?.value) {
            state.filters.tenant_id = el.tenantFilter.value;
        }
        load(1);
    }

    function resetFilters() {
        if (el.searchInput)   el.searchInput.value   = '';
        if (el.parentFilter)  el.parentFilter.value  = '';
        if (el.statusFilter)  el.statusFilter.value  = '';
        if (el.featuredFilter)el.featuredFilter.value = '';
        if (el.tenantFilter)  el.tenantFilter.value   = CFG.isSuperAdmin ? '' : String(CFG.tenantId || 1);
        state.filters = {};
        load(1);
    }

    // ════════════════════════════════════════════════════════
    // IMAGE SELECTION
    // ════════════════════════════════════════════════════════
    function openMediaStudio() {
        const modal  = document.getElementById('catMediaModal');
        const iframe = document.getElementById('catMediaFrame');
        if (iframe) {
            const ownerId = el.formId?.value || 0;
            iframe.src = `/admin/fragments/media_studio.php?embedded=1&tenant_id=${CFG.tenantId}&owner_id=${ownerId}&image_type_id=1&mode=select`;
        }
        if (modal) modal.style.display = 'flex';

        // Listen for image selection from iframe
        const onMessage = (evt) => {
            try {
                const frame = document.getElementById('catMediaFrame');
                if (!frame) return;
                const studioWin = frame.contentWindow;
                if (!studioWin) return;
                studioWin.addEventListener('ImageStudio:selected', (e) => {
                    const img = e.detail;
                    if (el.imageId)      el.imageId.value      = img.id;
                    if (el.imagePreview) el.imagePreview.src   = img.thumb_url || img.url;
                    const links = document.getElementById('catImageLinks');
                    if (links) {
                        links.innerHTML =
                            `<a href="${esc(img.url)}" target="_blank"><i class="fas fa-expand" aria-hidden="true"></i> Large</a>
                             <a href="${esc(img.thumb_url || img.url)}" target="_blank"><i class="fas fa-compress" aria-hidden="true"></i> Thumb</a>`;
                    }
                    if (modal) modal.style.display = 'none';
                });
                studioWin.addEventListener('ImageStudio:close', () => {
                    if (modal) modal.style.display = 'none';
                });
            } catch (_) {}
        };
        document.getElementById('catMediaFrame')?.addEventListener('load', onMessage, { once: true });
    }

    // ════════════════════════════════════════════════════════
    // TENANT VERIFY
    // ════════════════════════════════════════════════════════
    async function verifyTenant() {
        const id = el.formTenantId?.value?.trim();
        if (!id || isNaN(id)) { if (el.tenantInfo) el.tenantInfo.innerHTML = ''; return; }
        try {
            const data = await apiFetch(`${CFG.tenantsApi}/${id}`);
            const tenant = data.data || data;
            if (el.tenantInfo) {
                el.tenantInfo.innerHTML = tenant
                    ? `<small style="color:var(--success-color,#10b981);">${esc(tenant.name)}</small>`
                    : `<small style="color:var(--danger-color,#ef4444);">Invalid tenant</small>`;
            }
        } catch (_) {
            if (el.tenantInfo) el.tenantInfo.innerHTML = `<small style="color:var(--danger-color,#ef4444);">Error verifying tenant</small>`;
        }
    }

    // ════════════════════════════════════════════════════════
    // EXCEL IMPORT (preserved from original)
    // ════════════════════════════════════════════════════════
    let _excelRows = [], _excelImporting = false;

    function openExcelImport() {
        _excelRows = []; _excelImporting = false;
        const fileInput = document.getElementById('catExcelFileInput');
        if (fileInput) fileInput.value = '';
        document.getElementById('catExcelPreviewInfo')?.style && (document.getElementById('catExcelPreviewInfo').style.display = 'none');
        document.getElementById('catExcelProgressArea')?.style && (document.getElementById('catExcelProgressArea').style.display = 'none');
        const result = document.getElementById('catExcelResultSummary');
        if (result) result.style.display = 'none';
        const startBtn = document.getElementById('catExcelImportStart');
        if (startBtn) startBtn.disabled = true;
        const modal = document.getElementById('catExcelModal');
        if (modal) modal.style.display = 'flex';
        if (fileInput) fileInput.onchange = onExcelFileChange;
        document.getElementById('catExcelClose')?.addEventListener('click', closeExcelImport, { once: true });
        document.getElementById('catExcelImportCancel')?.addEventListener('click', closeExcelImport, { once: true });
        document.getElementById('catExcelImportStart')?.addEventListener('click', startExcelImport, { once: true });
        document.getElementById('catExcelDownloadSample')?.addEventListener('click', downloadExcelSample, { once: true });
    }

    function closeExcelImport() {
        if (_excelImporting) return;
        const modal = document.getElementById('catExcelModal');
        if (modal) modal.style.display = 'none';
    }

    function downloadExcelSample() {
        const header = 'name,parent_name,level,slug,description,sort_order,is_active,is_featured,en_name,en_slug,ar_name,ar_slug';
        const rows = [
            'Electronics,,1,electronics,Electronic products,0,1,0,Electronics,electronics,الإلكترونيات,al-iktruniyat',
            'Smartphones,Electronics,2,smartphones,Mobile phones,0,1,1,Smartphones,smartphones,الهواتف الذكية,al-hawatif',
        ];
        const blob = new Blob(['\uFEFF' + header + '\n' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const a    = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: 'categories_sample.csv' });
        a.click();
        URL.revokeObjectURL(a.href);
    }

    async function onExcelFileChange(e) {
        const file = e.target.files[0];
        if (!file) return;
        const startBtn    = document.getElementById('catExcelImportStart');
        const previewInfo = document.getElementById('catExcelPreviewInfo');
        const previewText = document.getElementById('catExcelPreviewText');
        try {
            const ext = file.name.split('.').pop().toLowerCase();
            _excelRows = (ext === 'csv' || ext === 'txt') ? parseCsv(await file.text()) : await parseXlsx(file);
            if (!_excelRows.length) { if (previewText) previewText.textContent = 'No rows found.'; return; }
            if (previewText) previewText.innerHTML = `Found <strong>${_excelRows.length}</strong> rows. First: ${esc(_excelRows[0].name || 'N/A')}`;
            if (previewInfo) previewInfo.style.display = 'block';
            if (startBtn) startBtn.disabled = false;
        } catch (err) {
            if (previewText) previewText.textContent = 'Error: ' + err.message;
            if (previewInfo) previewInfo.style.display = 'block';
            if (startBtn) startBtn.disabled = true;
        }
    }

    function parseCsv(text) {
        const lines   = text.split(/\r?\n/).filter(l => l.trim());
        if (lines.length < 2) return [];
        const headers = lines[0].split(',').map(h => h.replace(/^"|"$/g, '').trim().toLowerCase());
        const rows    = [];
        for (let i = 1; i < lines.length; i++) {
            const vals = []; let inQ = false, cur = '';
            for (const ch of lines[i]) {
                if (ch === '"') { inQ = !inQ; }
                else if (ch === ',' && !inQ) { vals.push(cur.trim()); cur = ''; }
                else cur += ch;
            }
            vals.push(cur.trim());
            const row = {};
            headers.forEach((h, idx) => { row[h] = (vals[idx] || '').replace(/^"|"$/g, '').trim(); });
            if (row.name) rows.push(row);
        }
        return rows;
    }

    async function parseXlsx(file) {
        if (!window.XLSX) {
            await new Promise((res, rej) => {
                const s = Object.assign(document.createElement('script'), {
                    src: 'https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js',
                    onload: res, onerror: () => rej(new Error('Failed to load SheetJS')),
                });
                document.head.appendChild(s);
            });
        }
        const wb   = window.XLSX.read(await file.arrayBuffer(), { type: 'array' });
        const json = window.XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { defval: '' });
        return json.filter(r => r.name || r.Name).map(r => {
            const n = {};
            Object.keys(r).forEach(k => { n[k.toLowerCase().replace(/\s+/g, '_')] = String(r[k] || '').trim(); });
            return n;
        });
    }

    async function startExcelImport() {
        if (_excelImporting || !_excelRows.length) return;
        _excelImporting = true;

        const progressArea = document.getElementById('catExcelProgressArea');
        const progressBar  = document.getElementById('catExcelProgressBar');
        const progressPct  = document.getElementById('catExcelProgressPct');
        const progressLabel= document.getElementById('catExcelProgressLabel');
        const progressLog  = document.getElementById('catExcelProgressLog');
        const resultSummary= document.getElementById('catExcelResultSummary');

        document.getElementById('catExcelImportStart')?.setAttribute('disabled', '');
        if (progressArea)  progressArea.style.display  = 'block';
        if (resultSummary) resultSummary.style.display  = 'none';
        if (progressLog)   progressLog.textContent     = '';

        const log = (msg) => { if (progressLog) progressLog.textContent += msg + '\n'; };
        const tenantId = CFG.tenantId || 1;

        // Load existing categories for parent lookup
        const nameToId = {};
        try {
            const qs  = CFG.isSuperAdmin ? '?per_page=9999' : `?per_page=9999&tenant_id=${tenantId}`;
            const data = await apiFetch(API + qs);
            extractItems(data).forEach(c => { nameToId[c.name.toLowerCase()] = c.id; });
            log(`Loaded ${Object.keys(nameToId).length} existing categories.`);
        } catch (e) { log('Warning: ' + e.message); }

        // Sort by level
        const sorted = [..._excelRows].sort((a, b) =>
            (parseInt(a.level) || (a.parent_name ? 2 : 1)) - (parseInt(b.level) || (b.parent_name ? 2 : 1)));

        let created = 0, skipped = 0, failed = 0;

        for (let i = 0; i < sorted.length; i++) {
            const row  = sorted[i];
            const name = (row.name || '').trim();
            if (!name) { skipped++; continue; }

            const pct = Math.round(((i + 1) / sorted.length) * 100);
            if (progressBar)  progressBar.style.width  = pct + '%';
            if (progressPct)  progressPct.textContent  = pct + '%';
            if (progressLabel)progressLabel.textContent= `Importing ${i + 1}/${sorted.length}…`;

            const parentName = (row.parent_name || '').trim();
            const parentId   = parentName ? (nameToId[parentName.toLowerCase()] || null) : null;
            const slug       = (row.slug || '').trim() || slugify(name);

            // Build translations
            const translations = {};
            Object.keys(row).forEach(col => {
                const m = col.match(/^([a-z]{2,3})_(name|slug|description|meta_title|meta_description|meta_keywords)$/);
                if (m) {
                    const lang = m[1];
                    if (!translations[lang]) translations[lang] = {};
                    translations[lang][m[2]] = row[col];
                }
            });
            if (!translations.en) {
                translations.en = { name, slug, description: (row.description || '').trim(), meta_title: '', meta_description: '', meta_keywords: '' };
            }

            try {
                const result = await apiFetch(API, {
                    method: 'POST',
                    body: JSON.stringify({
                        tenant_id: tenantId, name, slug,
                        parent_id: parentId,
                        sort_order: parseInt(row.sort_order) || 0,
                        is_active:  row.is_active === '' ? 1 : (row.is_active === '1' ? 1 : 0),
                        is_featured:row.is_featured === '1' ? 1 : 0,
                        description:(row.description || '').trim(),
                        translations: Object.entries(translations).map(([code, tr]) => ({
                            language_code: code, name: tr.name || name, slug: tr.slug || slug,
                            description: tr.description || '', meta_title: tr.meta_title || '',
                            meta_description: tr.meta_description || '', meta_keywords: tr.meta_keywords || '',
                        })),
                    }),
                });
                if (result.success !== false && result.data?.id) {
                    nameToId[name.toLowerCase()] = result.data.id;
                    created++;
                    log(`✓ Created: "${name}" (ID: ${result.data.id})`);
                } else {
                    failed++;
                    log(`✗ Failed: "${name}" — ${result.message || 'Unknown error'}`);
                }
            } catch (err) {
                failed++;
                log(`✗ Error: "${name}" — ${err.message}`);
            }
            await new Promise(r => setTimeout(r, 80));
        }

        if (progressBar)  progressBar.style.width  = '100%';
        if (progressPct)  progressPct.textContent  = '100%';
        if (progressLabel)progressLabel.textContent = 'Import complete!';

        if (resultSummary) {
            resultSummary.style.display = 'block';
            resultSummary.className = `cat-excel-result ${failed > 0 ? 'is-warning' : 'is-success'}`;
            resultSummary.innerHTML = `<strong>Import Complete</strong><br>✓ Created: <strong>${created}</strong> &nbsp; ✗ Failed: <strong>${failed}</strong> &nbsp; ⊘ Skipped: <strong>${skipped}</strong>`;
        }

        _excelImporting = false;
        document.getElementById('catExcelImportStart')?.removeAttribute('disabled');
        await load(1);
    }

    // ════════════════════════════════════════════════════════
    // INIT
    // ════════════════════════════════════════════════════════
    async function init() {
        el = {
            form:           document.getElementById('categoryForm'),
            formId:         document.getElementById('formId'),
            formName:       document.getElementById('catName'),
            formSlug:       document.getElementById('catSlug'),
            formParentId:   document.getElementById('catParentId'),
            formSortOrder:  document.getElementById('catSortOrder'),
            formIsActive:   document.getElementById('catIsActive'),
            formIsFeatured: document.getElementById('catIsFeatured'),
            formDesc:       document.getElementById('catDescription'),
            formTenantId:   document.getElementById('catTenantId'),
            tenantInfo:     document.getElementById('tenantInfo'),
            imageId:        document.getElementById('catImageId'),
            imagePreview:   document.getElementById('catImagePreview'),
            imageTypeSelect:document.getElementById('catImageType'),
            imageTypeDesc:  document.getElementById('catImageTypeDesc'),
            translations:   document.getElementById('catTranslations'),
            langSelect:     document.getElementById('catLangSelect'),
            searchInput:    document.getElementById('searchInput'),
            tenantFilter:   document.getElementById('tenantFilter'),
            parentFilter:   document.getElementById('parentFilter'),
            statusFilter:   document.getElementById('statusFilter'),
            featuredFilter: document.getElementById('featuredFilter'),
            btnSubmit:      document.getElementById('btnSubmitForm'),
            btnDelete:      document.getElementById('btnDeleteCategory'),
        };

        // ESC closes form
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            const card = document.getElementById('categoryFormContainer');
            if (card && card.style.display !== 'none') hideForm();
            ['catMediaModal','catExcelModal'].forEach(id => {
                const m = document.getElementById(id);
                if (m && m.style.display !== 'none') m.style.display = 'none';
            });
        });

        if (el.form) el.form.addEventListener('submit', saveCategory);

        // Slug auto-generate from name
        if (el.formName) {
            el.formName.addEventListener('input', () => {
                if (el.formSlug && !el.formSlug.dataset.manual) {
                    el.formSlug.value = slugify(el.formName.value);
                }
            });
        }
        if (el.formSlug) {
            el.formSlug.addEventListener('input', () => {
                el.formSlug.dataset.manual = el.formSlug.value ? '1' : '';
            });
        }

        document.getElementById('btnAddCategory')?.addEventListener('click', addCategory);
        document.getElementById('btnAddCategoryEmpty')?.addEventListener('click', addCategory);
        document.getElementById('btnCloseForm')?.addEventListener('click', hideForm);
        document.getElementById('btnCancelForm')?.addEventListener('click', hideForm);
        document.getElementById('btnApplyFilters')?.addEventListener('click', applyFilters);
        document.getElementById('btnResetFilters')?.addEventListener('click', resetFilters);
        document.getElementById('btnRetry')?.addEventListener('click', () => load(state.page));
        document.getElementById('catSelectImageBtn')?.addEventListener('click', openMediaStudio);
        document.getElementById('catMediaClose')?.addEventListener('click', () => {
            document.getElementById('catMediaModal').style.display = 'none';
        });
        document.getElementById('btnImportExcel')?.addEventListener('click', openExcelImport);
        document.getElementById('catAddLangBtn')?.addEventListener('click', () => {
            const code = el.langSelect?.value;
            if (code) createTranslationPanel(code, {});
        });
        if (el.btnDelete) {
            el.btnDelete.addEventListener('click', () => {
                const id = el.formId?.value;
                if (id) removeCategory(id);
            });
        }
        if (el.formTenantId) el.formTenantId.addEventListener('input', verifyTenant);

        el.searchInput?.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); applyFilters(); }
        });

        await loadImageTypes();
        await loadLanguages();
        await loadParents();
        await load();
    }

    // ════════════════════════════════════════════════════════
    // REGISTER
    // ════════════════════════════════════════════════════════
    window.Categories = { init, load, add: addCategory, edit: editCategory, remove: removeCategory };
    window.page = { run: init };

    if (window.Admin?.page?.register) {
        window.Admin.page.register('categories', init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => init().catch(console.error));
    } else {
        init().catch(console.error);
    }

}());