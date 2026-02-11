(function () {
    'use strict';

    /**
     * /admin/assets/js/pages/entities.js
     * Entities Management Module - Complete Implementation
     * With real-time image updates and address integration
     */

    // ════════════════════════════════════════════════════════════
    // CONFIGURATION & STATE
    // ════════════════════════════════════════════════════════════
    const CONFIG = window.ENTITIES_CONFIG || {};
    const AF = window.AdminFramework || {};
    const PERMS = window.PAGE_PERMISSIONS || {};

    const API = {
        entities: CONFIG.apiUrl || '/api/entities',
        attributes: CONFIG.attributesApi || '/api/entities_attributes',
        attributeValues: CONFIG.attributeValuesApi || '/api/entities_attribute_values',
        settings: CONFIG.settingsApi || '/api/entity_settings',
        workingHours: CONFIG.workingHoursApi || '/api/entities_working_hours',
        languages: CONFIG.languagesApi || '/api/languages',
        tenants: CONFIG.tenantsApi || '/api/tenants',
        entityTypes: CONFIG.entityTypesApi || '/api/entity_types',
        addresses: CONFIG.addressesApi || '/api/addresses',
        images: '/api/images'
    };

    const state = {
        page: 1,
        perPage: CONFIG.itemsPerPage || 25,
        total: 0,
        entities: [],
        languages: [],
        tenants: [],
        entityTypes: [],
        attributes: [],
        filters: {},
        currentEntity: null,
        entityAttributes: [],
        entitySettings: {},
        entityWorkingHours: [],
        addressData: null,
        deletedTranslationIds: [],
        permissions: PERMS,
        language: window.USER_LANGUAGE || CONFIG.lang || 'en',
        direction: window.USER_DIRECTION || 'ltr',
        csrfToken: window.CSRF_TOKEN || CONFIG.csrfToken || '',
        tenantId: window.APP_CONFIG?.TENANT_ID || 1,
        userId: window.APP_CONFIG?.USER_ID || null
    };

    let el = {}; // DOM elements cache
    let translations = {}; // i18n translations
    let _messageListenerAdded = false; // prevent duplicate message listeners
    let _addressMessageListenerAdded = false; // prevent duplicate address message listeners
    let _currentImageType = null; // track current image type for media studio

    // Days of week configuration
    const DAYS_OF_WEEK = [
        { id: 1, name: 'Monday' },
        { id: 2, name: 'Tuesday' },
        { id: 3, name: 'Wednesday' },
        { id: 4, name: 'Thursday' },
        { id: 5, name: 'Friday' },
        { id: 6, name: 'Saturday' },
        { id: 0, name: 'Sunday' }
    ];

    // Image types configuration
    const IMAGE_TYPES = {
        LOGO: 4,
        COVER: 5,
        LICENSE: 6
    };

    // Media URLs state
    const mediaUrls = {
        logo: null,
        cover: null,
        license: null
    };

    // Media preview elements cache
    const mediaPreviews = {
        logo: null,
        cover: null,
        license: null
    };

    // ════════════════════════════════════════════════════════════
    // TRANSLATIONS
    // ════════════════════════════════════════════════════════════
    async function loadTranslations(lang) {
        try {
            const url = `/languages/Entities/${encodeURIComponent(lang || state.language)}.json`;
            console.log('[Entities] Loading translations:', url);
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error(`Failed to load translations: ${res.status}`);
            const raw = await res.json();
            const s = raw.strings || raw;
            translations = buildTranslationsMap(s);
            if (raw.direction) setDirectionForLang(raw.direction === 'rtl' ? 'ar' : 'en');
            console.log('[Entities] Translations loaded');
            applyTranslations();
        } catch (err) {
            console.warn('[Entities] Translation load failed:', err);
            translations = {};
        }
    }

    function buildTranslationsMap(s) {
        const g = s.general || {};
        const cnt = s.contact || {};
        const set = s.settings || {};
        const wh = s.working_hours || {};
        const attr = s.attributes || {};
        const med = s.media || {};
        const addr = s.address || {};
        const tr = s.translations || {};
        const val = s.validation || {};
        const msg = s.messages || {};
        const stats = s.status || {};
        const vendor = s.vendor_type || {};
        const store = s.store_type || {};

        return {
            entities: {
                title: s.entities || 'Entities',
                subtitle: s.manage_entities || 'Manage your entities',
                add_new: s.create,
                loading: s.loading,
                retry: s.refresh || 'Retry',
                found: s.found || 'entities found'
            },
            tabs: {
                basic: g.basic || 'Basic',
                contact: cnt.contact || 'Contact',
                settings: set.settings || 'Settings',
                working_hours: wh.working_hours || 'Working Hours',
                attributes: attr.attributes || 'Attributes',
                media: med.media || 'Media',
                address: addr.address || 'Address',
                translations: tr.translations || 'Translations'
            },
            form: {
                add_title: s.create,
                edit_title: s.edit,
                fields: {
                    store_name: { label: g.store_name, placeholder: g.store_name, required: val.name_required },
                    slug: { label: g.slug, placeholder: g.slug },
                    is_main: { label: g.is_main, yes: g.yes, no: g.no },
                    branch_code: { label: g.branch_code, placeholder: g.branch_code },
                    vendor_type: { label: vendor.vendor_type },
                    store_type: { label: store.store_type },
                    registration_number: { label: g.registration_number, placeholder: g.registration_number },
                    tax_number: { label: g.tax_number, placeholder: g.tax_number },
                    status: {
                        label: stats.status || s.status,
                        pending: stats.pending || s.pending || 'Pending',
                        approved: stats.approved || s.approved || 'Approved',
                        suspended: stats.suspended || s.suspended || 'Suspended',
                        rejected: stats.rejected || s.rejected || 'Rejected'
                    },
                    is_verified: { label: stats.verified || s.verified, yes: s.yes || g.yes, no: s.no || g.no },
                    phone: { label: cnt.phone, placeholder: cnt.phone },
                    mobile: { label: cnt.mobile, placeholder: cnt.mobile },
                    email: { label: cnt.email, placeholder: cnt.email },
                    website: { label: cnt.website, placeholder: cnt.website },
                    suspension_reason: { label: cnt.suspension_reason, placeholder: cnt.suspension_reason },
                    auto_accept_orders: { label: set.auto_accept_orders, yes: g.yes, no: g.no },
                    allow_cod: { label: set.allow_cod, yes: g.yes, no: g.no },
                    min_order_amount: { label: set.min_order_amount },
                    allow_online_booking: { label: set.allow_online_booking, yes: g.yes, no: g.no },
                    booking_window_days: { label: set.booking_window_days },
                    max_bookings_per_slot: { label: set.max_bookings_per_slot },
                    show_reviews: { label: set.show_reviews, yes: g.yes, no: g.no },
                    show_contact_info: { label: set.show_contact_info, yes: g.yes, no: g.no },
                    featured_in_app: { label: set.featured_in_app, yes: g.yes, no: g.no },
                    logo: { label: med.logo },
                    cover: { label: med.cover },
                    license: { label: med.license },
                    store_name_translation: { label: g.store_name },
                    description: { label: g.description },
                    meta_title: { label: tr.meta_title },
                    meta_description: { label: tr.meta_description }
                },
                buttons: {
                    save: s.save,
                    cancel: s.cancel,
                    add_attribute: attr.add_attribute,
                    apply_to_all: wh.apply_to_all || 'Apply to All Days',
                    reset_hours: wh.reset_hours || 'Reset Hours'
                },
                sections: {
                    logo: med.logo,
                    cover: med.cover,
                    license: med.license,
                    address: addr.address,
                    translations: tr.translations,
                    working_hours: wh.working_hours
                },
                translations: {
                    select_lang: tr.select_language || 'Select Language',
                    choose_lang: tr.choose_language || 'Choose language',
                    add_translation: tr.add_translation || 'Add Translation'
                },
                media: {
                    no_logo: med.no_logo || 'No logo selected',
                    no_cover: med.no_cover || 'No cover image selected',
                    no_license: med.no_license || 'No license document selected',
                    logo_url: med.logo_url || 'Logo URL will appear here',
                    cover_url: med.cover_url || 'Cover URL will appear here',
                    license_url: med.license_url || 'License URL will appear here',
                    delete: med.delete || 'Delete image'
                },
                working_hours: {
                    day: wh.day || 'Day',
                    open: wh.open || 'Open',
                    closed: wh.closed || 'Closed',
                    open_time: wh.open_time || 'Open Time',
                    close_time: wh.close_time || 'Close Time',
                    all_day: wh.all_day || '24 Hours',
                    closed_all_day: wh.closed_all_day || 'Closed'
                }
            },
            common: {
                select_image: med.select_from_studio || 'Select from Studio',
                loading: s.loading || 'Loading...'
            },
            filters: {
                search: s.search || 'Search',
                search_placeholder: s.search_placeholder || 'Search entities...',
                tenant_id: g.tenant || 'Tenant ID',
                tenant_placeholder: s.tenant_placeholder || 'Filter by tenant',
                status: stats.status || 'Status',
                vendor_type: vendor.vendor_type || 'Vendor Type',
                store_type: store.store_type || 'Store Type',
                verified: stats.verified || 'Verified',
                status_options: {
                    all: s.all || 'All Status',
                    pending: stats.pending || 'Pending',
                    approved: stats.approved || 'Approved',
                    suspended: stats.suspended || 'Suspended',
                    rejected: stats.rejected || 'Rejected'
                },
                apply: s.apply || 'Apply',
                reset: s.reset || 'Reset'
            },
            table: {
                headers: {
                    id: 'ID',
                    tenant: 'Tenant',
                    logo: med.logo || 'Logo',
                    store_name: g.store_name || 'Store Name',
                    branch_code: g.branch_code || 'Branch Code',
                    vendor_type: vendor.vendor_type || 'Vendor Type',
                    phone: cnt.phone || 'Phone',
                    email: cnt.email || 'Email',
                    status: stats.status || 'Status',
                    verified: stats.verified || 'Verified',
                    actions: s.actions || 'Actions'
                },
                empty: {
                    title: s.no_entities || 'No Entities Found',
                    message: s.create || 'Add your first entity',
                    add_first: s.create || 'Add First Entity'
                },
                actions: {
                    edit: s.edit || 'Edit',
                    duplicate: s.duplicate || 'Duplicate',
                    delete: s.delete || 'Delete'
                }
            },
            pagination: { showing: s.total || 'Showing' },
            messages: {
                error: {
                    load_failed: msg.error?.load_failed || msg.server_error || 'Error loading data',
                    save_failed: msg.error?.save_failed || 'Failed to save',
                    delete_failed: msg.error?.delete_failed || 'Failed to delete',
                    unknown: msg.error?.unknown || 'An unknown error occurred'
                },
                validation_failed: msg.validation_failed || s.validation_failed || 'Please fill all required fields',
                save_entity_first: msg.save_entity_first || 'Please save the entity first to manage addresses',
                confirm_delete_image: msg.confirm_delete_image || 'Are you sure you want to delete this image?',
                created: msg.created || s.save_success || 'Created successfully',
                updated: msg.updated || s.update_success || 'Updated successfully',
                deleted: msg.deleted || s.delete_success || 'Deleted successfully',
                confirm_delete: s.confirm_delete || 'Are you sure?',
                address_saved: msg.address_saved || 'Address saved successfully',
                address_deleted: msg.address_deleted || 'Address deleted successfully',
                image_saved: msg.image_saved || 'Image saved successfully',
                image_deleted: msg.image_deleted || 'Image deleted successfully',
                save_first: s.save_first || 'Please save the entity first',
                try_again: msg.try_again || 'Please try again',
                attribute_exists: s.attribute_exists || 'Attribute already added',
                translation_exists: s.translation_exists || 'Translation already added'
            },
            strings: {
                save_success: s.save_success || 'Entity saved successfully',
                update_success: s.update_success || 'Entity updated successfully',
                delete_confirm: s.delete_confirm || 'Are you sure you want to delete this entity?',
                delete_success: s.delete_success || 'Entity deleted successfully',
                saving: s.saving || 'Saving...',
                loading: s.loading || 'Loading...',
                attribute_exists: s.attribute_exists || 'Attribute already added',
                translation_exists: s.translation_exists || 'Translation already added',
                save_first: s.save_first || 'Please save the entity first',
                confirm_delete: s.confirm_delete || 'Are you sure?',
                address_saved: msg.address_saved || 'Address saved successfully',
                address_deleted: msg.address_deleted || 'Address deleted successfully',
                try_again: msg.try_again || 'Please try again',
                image_saved: msg.image_saved || 'Image saved successfully',
                image_deleted: msg.image_deleted || 'Image deleted successfully'
            }
        };
    }

    function t(key, fallback = '') {
        const keys = key.split('.');
        let val = translations;
        for (const k of keys) {
            if (val && typeof val === 'object' && k in val) {
                val = val[k];
            } else {
                return fallback || key;
            }
        }
        return val !== undefined && val !== null ? String(val) : (fallback || key);
    }

    function applyTranslations() {
        const container = document.getElementById('entitiesPageContainer');
        if (!container) return;

        container.querySelectorAll('[data-i18n]').forEach(elem => {
            const key = elem.getAttribute('data-i18n');
            const txt = t(key);
            if (txt !== key) {
                if (elem.tagName === 'INPUT' && elem.type !== 'submit' && elem.type !== 'button') {
                    if (elem.hasAttribute('placeholder')) elem.placeholder = txt;
                } else {
                    elem.textContent = txt;
                }
            }
        });

        container.querySelectorAll('[data-i18n-placeholder]').forEach(elem => {
            const key = elem.getAttribute('data-i18n-placeholder');
            const txt = t(key);
            if (txt !== key) elem.placeholder = txt;
        });
    }

    function setDirectionForLang(lang) {
        const container = document.getElementById('entitiesPageContainer');
        if (container) {
            container.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');
        }
        state.direction = lang === 'ar' ? 'rtl' : 'ltr';
    }

    // ════════════════════════════════════════════════════════════
    // API HELPERS
    // ════════════════════════════════════════════════════════════
    async function apiCall(url, options = {}) {
        const defaults = {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        if (options.method && options.method !== 'GET') {
            defaults.headers['X-CSRF-Token'] = state.csrfToken;
        }

        const config = { ...defaults, ...options };
        if (config.headers && options.headers) {
            config.headers = { ...defaults.headers, ...options.headers };
        }

        try {
            const res = await fetch(url, config);
            const contentType = res.headers.get('content-type');

            if (contentType && contentType.includes('application/json')) {
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.error || data.message || `HTTP ${res.status}`);
                }
                return data;
            } else {
                const text = await res.text();
                if (!res.ok) {
                    throw new Error(text || `HTTP ${res.status}`);
                }
                try {
                    return JSON.parse(text);
                } catch {
                    return { success: true, data: text };
                }
            }
        } catch (err) {
            console.error('[Entities] API call failed:', url, err);
            throw err;
        }
    }

    // ════════════════════════════════════════════════════════════
    // DATA LOADING
    // ════════════════════════════════════════════════════════════
    async function loadEntities(page = 1) {
        try {
            showLoading();

            state.page = page;
            const params = new URLSearchParams({
                page: page,
                limit: state.perPage,
                tenant_id: state.tenantId,
                lang: state.language,
                format: 'json'
            });

            Object.entries(state.filters).forEach(([key, val]) => {
                if (val !== undefined && val !== null && val !== '') {
                    params.set(key, val);
                }
            });

            const result = await apiCall(`${API.entities}?${params}`);

            if (result.success && result.data) {
                const items = result.data.items || result.data;
                const meta = result.data.meta || result.meta || {};

                state.entities = Array.isArray(items) ? items : [];
                state.total = meta.total || state.entities.length;

                await renderTable(state.entities);
                updatePagination(meta.total !== undefined ? meta : { page, per_page: state.perPage, total: state.total });
                updateResultsCount(state.total);

                showTable();
            } else {
                throw new Error(result.error || result.message || 'Invalid response format');
            }
        } catch (err) {
            console.error('[Entities] Load failed:', err);
            showError(err.message || t('messages.error.load_failed', 'Failed to load entities'));
        }
    }

    async function loadDropdownData() {
        try {
            // Load languages
            const languagesResult = await apiCall(`${API.languages}?format=json`);
            if (languagesResult.success) {
                const langsData = languagesResult.data?.items || languagesResult.data;
                state.languages = Array.isArray(langsData) ? langsData : [];
                populateDropdown(el.entityLangSelect, state.languages, 'code', 'name', t('form.translations.select_lang', 'Select language'));
            }

            // Load attributes
            const attributesResult = await apiCall(`${API.attributes}?format=json&lang=${state.language}`);
            if (attributesResult.success) {
                const attrData = Array.isArray(attributesResult.data) ? attributesResult.data : (attributesResult.data?.items || attributesResult.data?.data || []);
                state.attributes = attrData;
                populateAttributeSelect(state.attributes);
            }
        } catch (err) {
            console.warn('[Entities] Failed to load dropdown data:', err);
        }
    }

    function populateDropdown(selectEl, data, valueKey, textKey, placeholder = '') {
        if (!selectEl) return;

        selectEl.innerHTML = '';

        if (placeholder) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = placeholder;
            selectEl.appendChild(opt);
        }

        data.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item[valueKey];
            opt.textContent = item[textKey];
            selectEl.appendChild(opt);
        });
    }

    function populateAttributeSelect(attributes) {
        if (!el.entityAttrSelect) return;

        el.entityAttrSelect.innerHTML = '<option value="">' + t('form.attributes.select', 'Select attribute') + '</option>';

        attributes.forEach(attr => {
            const opt = document.createElement('option');
            opt.value = attr.id;
            opt.textContent = attr.name || attr.slug;
            opt.dataset.type = attr.attribute_type || 'text';
            el.entityAttrSelect.appendChild(opt);
        });
    }

    // ════════════════════════════════════════════════════════════
    // RENDERING
    // ════════════════════════════════════════════════════════════
    async function renderTable(items) {
        if (!el.tbody) return;

        if (!items || !items.length) {
            showEmpty();
            return;
        }

        const isSuperAdmin = state.permissions.isSuperAdmin;

        el.tbody.innerHTML = items.map(entity => {
            const logo = entity.entity_logo || null;
            const name = entity.store_name || `Entity #${entity.id}`;
            const phone = entity.phone || '-';
            const email = entity.email || '-';

            let statusBadge;
            switch (entity.status) {
                case 'approved':
                    statusBadge = `<span class="badge badge-success">${t('form.fields.status.approved', 'Approved')}</span>`;
                    break;
                case 'pending':
                    statusBadge = `<span class="badge badge-warning">${t('form.fields.status.pending', 'Pending')}</span>`;
                    break;
                case 'suspended':
                    statusBadge = `<span class="badge badge-danger">${t('form.fields.status.suspended', 'Suspended')}</span>`;
                    break;
                case 'rejected':
                    statusBadge = `<span class="badge badge-secondary">${t('form.fields.status.rejected', 'Rejected')}</span>`;
                    break;
                default:
                    statusBadge = `<span class="badge badge-secondary">${esc(entity.status)}</span>`;
            }

            const verifiedBadge = entity.is_verified == 1
                ? `<span class="badge badge-success">${t('form.fields.is_verified.yes', 'Yes')}</span>`
                : `<span class="badge badge-secondary">${t('form.fields.is_verified.no', 'No')}</span>`;

            const canEdit = state.permissions.canEdit || state.permissions.canEditAll ||
                (state.permissions.canEditOwn && entity.user_id == state.userId);
            const canDelete = state.permissions.canDelete || state.permissions.canDeleteAll ||
                (state.permissions.canDeleteOwn && entity.user_id == state.userId);

            return `
                <tr data-id="${entity.id}">
                    <td>${esc(entity.id)}</td>
                    ${isSuperAdmin ? `<td>${esc(entity.tenant_id || '')}</td>` : ''}
                    <td>
                        ${logo ? `<img src="${esc(logo)}" alt="${esc(name)}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">` : '🏢'}
                    </td>
                    <td><strong>${esc(name)}</strong><br><small style="color:var(--text-secondary,#94a3b8);">${esc(entity.branch_code || '')}</small></td>
                    <td>${esc(entity.branch_code || '-')}</td>
                    <td>${esc(entity.vendor_type || '-')}</td>
                    <td>${esc(phone)}</td>
                    <td>${esc(email)}</td>
                    <td>${statusBadge}</td>
                    <td>${verifiedBadge}</td>
                    <td>
                        <div class="table-actions">
                            ${canEdit ? `<button class="btn btn-sm btn-secondary" onclick="Entities.edit(${entity.id})" title="${t('table.actions.edit', 'Edit')}">
                                <i class="fas fa-edit"></i>
                            </button>` : ''}
                            ${canDelete ? `<button class="btn btn-sm btn-danger" onclick="Entities.remove(${entity.id})" title="${t('table.actions.delete', 'Delete')}">
                                <i class="fas fa-trash"></i>
                            </button>` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // ════════════════════════════════════════════════════════════
    // FORM MANAGEMENT
    // ════════════════════════════════════════════════════════════
    function showForm(entity = null) {
        if (!el.formContainer || !el.form) {
            console.error('[Entities] showForm: formContainer or form not found in DOM');
            return;
        }

        state.currentEntity = entity;
        state.entityAttributes = [];
        state.entitySettings = {};
        state.entityWorkingHours = [];
        state.addressData = null;
        state.deletedTranslationIds = [];

        mediaUrls.logo = null;
        mediaUrls.cover = null;
        mediaUrls.license = null;

        el.form.reset();

        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
        const basicTab = document.querySelector('.tab-btn[data-tab="basic"]');
        const basicContent = document.getElementById('tab-basic');
        if (basicTab) basicTab.classList.add('active');
        if (basicContent) basicContent.style.display = 'block';

        if (entity) {
            if (el.formTitle) el.formTitle.textContent = t('form.edit_title', 'Edit Entity');
            if (el.formId) el.formId.value = entity.id || '';

            if (el.entityStoreName) el.entityStoreName.value = entity.original_store_name || entity.store_name || '';
            if (el.entitySlug) el.entitySlug.value = entity.slug || '';
            if (el.entityIsMain) el.entityIsMain.value = entity.is_main ?? '1';
            if (el.entityBranchCode) el.entityBranchCode.value = entity.branch_code || '';
            if (el.entityVendorType) el.entityVendorType.value = entity.vendor_type || 'product_seller';
            if (el.entityStoreType) el.entityStoreType.value = entity.store_type || 'individual';
            if (el.entityRegistrationNumber) el.entityRegistrationNumber.value = entity.registration_number || '';
            if (el.entityTaxNumber) el.entityTaxNumber.value = entity.tax_number || '';
            if (el.entityStatus) el.entityStatus.value = entity.status || 'pending';
            if (el.entityIsVerified) el.entityIsVerified.value = entity.is_verified || '0';

            if (el.entityPhone) el.entityPhone.value = entity.phone || '';
            if (el.entityMobile) el.entityMobile.value = entity.mobile || '';
            if (el.entityEmail) el.entityEmail.value = entity.email || '';
            if (el.entityWebsite) el.entityWebsite.value = entity.website_url || '';
            if (el.entitySuspensionReason) el.entitySuspensionReason.value = entity.suspension_reason || '';

            if (el.btnDeleteEntity) el.btnDeleteEntity.style.display = state.permissions.canDelete ? 'inline-block' : 'none';

            if (entity.id) {
                loadEntityAttributes(entity.id);
                loadEntitySettings(entity.id);
                loadEntityWorkingHours(entity.id);
                loadEntityTranslations(entity.id);
                loadEntityMedia(entity.id);
            }
        } else {
            if (el.formTitle) el.formTitle.textContent = t('form.add_title', 'Add Entity');
            if (el.formId) el.formId.value = '';
            if (el.btnDeleteEntity) el.btnDeleteEntity.style.display = 'none';
            if (el.entityTenantId) el.entityTenantId.value = state.tenantId;
            if (el.entityUserId && state.userId) el.entityUserId.value = state.userId;

            if (el.entityAttributesList) el.entityAttributesList.innerHTML = '';
            if (el.entityTranslations) el.entityTranslations.innerHTML = '';
            renderWorkingHours(getDefaultWorkingHours());
            clearMediaPreviews();
            clearAddress();
        }

        el.formContainer.style.display = 'block';
        el.formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function hideForm() {
        if (el.formContainer) {
            el.formContainer.style.display = 'none';
        }
        state.currentEntity = null;
        if (el.form) el.form.reset();
    }

    // ════════════════════════════════════════════════════════════
    // TAB MANAGEMENT
    // ════════════════════════════════════════════════════════════
    function initTabs() {
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetTab = btn.dataset.tab;

                tabButtons.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.style.display = 'none');

                btn.classList.add('active');
                const targetContent = document.getElementById(`tab-${targetTab}`);
                if (targetContent) targetContent.style.display = 'block';

                if (targetTab === 'address' && state.currentEntity?.id) {
                    setTimeout(() => {
                        loadAddressFragment(state.currentEntity.id);
                    }, 100);
                }
            });
        });
    }

    // ════════════════════════════════════════════════════════════
    // FORM SUBMISSION
    // ════════════════════════════════════════════════════════════
    async function saveEntity(e) {
        e.preventDefault();

        if (!validateForm()) {
            showNotification(t('messages.validation_failed', 'Please fill all required fields'), 'error');
            return;
        }

        try {
            const formData = new FormData(el.form);
            const entityId = el.formId.value;
            const isEdit = !!entityId;

            // Get address data from iframe if available
            let addressDataFromIframe = null;
            const activeTab = document.querySelector('.tab-btn.active');
            if (activeTab && activeTab.dataset.tab === 'address') {
                addressDataFromIframe = await requestAddressDataFromIframe();
                if (addressDataFromIframe) {
                    state.addressData = addressDataFromIframe;
                }
            }

            const entityData = {
                store_name: formData.get('store_name'),
                slug: formData.get('slug') || generateSlug(formData.get('store_name')),
                is_main: formData.get('is_main') ?? '1',
                branch_code: formData.get('branch_code') || null,
                vendor_type: formData.get('vendor_type') || 'product_seller',
                store_type: formData.get('store_type') || 'individual',
                registration_number: formData.get('registration_number') || null,
                tax_number: formData.get('tax_number') || null,
                tenant_id: formData.get('tenant_id') || state.tenantId,
                user_id: formData.get('user_id') || state.userId,
                status: formData.get('status') || 'pending',
                is_verified: formData.get('is_verified') || '0',

                phone: formData.get('phone'),
                mobile: formData.get('mobile') || null,
                email: formData.get('email'),
                website_url: formData.get('website_url') || null,
                suspension_reason: formData.get('suspension_reason') || null,

                entity_logo: mediaUrls.logo,
                entity_cover: mediaUrls.cover,
                entity_license: mediaUrls.license,

                translations: collectTranslations(),
                attributes: state.entityAttributes,
                settings: state.entitySettings,
                working_hours: state.entityWorkingHours,
                address: state.addressData
            };

            if (isEdit) {
                entityData.id = entityId;
            }

            const url = API.entities;
            const method = isEdit ? 'PUT' : 'POST';

            const result = await apiCall(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(entityData)
            });

            if (result.success) {
                const savedEntityId = isEdit ? entityId : (result.data?.id || result.data?.items?.[0]?.id);

                await saveEntitySettings(savedEntityId, isEdit);
                await saveEntityWorkingHours(savedEntityId, isEdit);
                await saveEntityAttributes(savedEntityId, isEdit);

                const translations = collectTranslations();
                if (Object.keys(translations).length > 0 || state.deletedTranslationIds.length > 0) {
                    await saveEntityTranslations(savedEntityId, translations);
                }

                if (state.addressData) {
                    await saveEntityAddress(savedEntityId, isEdit);
                }

                showNotification(
                    isEdit ? t('messages.updated', 'Entity updated successfully') : t('messages.created', 'Entity created successfully'),
                    'success'
                );
                hideForm();
                loadEntities(state.page);
            } else {
                throw new Error(result.error || result.message || 'Save failed');
            }
        } catch (err) {
            console.error('[Entities] Save failed:', err);
            showNotification(err.message || t('messages.error.save_failed', 'Failed to save entity'), 'error');
        }
    }

    function generateSlug(name) {
        if (!name) return '';
        return name.toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .substring(0, 255);
    }

    async function saveEntitySettings(entityId, isEdit = false) {
        try {
            const settings = {
                entity_id: parseInt(entityId),
                auto_accept_orders: document.getElementById('settingAutoAcceptOrders')?.value === '1' ? 1 : 0,
                allow_cod: document.getElementById('settingAllowCod')?.value === '1' ? 1 : 0,
                min_order_amount: parseFloat(document.getElementById('settingMinOrderAmount')?.value || 0) || 0,
                allow_online_booking: document.getElementById('settingAllowOnlineBooking')?.value === '1' ? 1 : 0,
                booking_window_days: parseInt(document.getElementById('settingBookingWindowDays')?.value || 0) || 0,
                max_bookings_per_slot: parseInt(document.getElementById('settingMaxBookingsPerSlot')?.value || 0) || 0,
                show_reviews: document.getElementById('settingShowReviews')?.value === '1' ? 1 : 0,
                show_contact_info: document.getElementById('settingShowContactInfo')?.value === '1' ? 1 : 0,
                featured_in_app: document.getElementById('settingFeaturedInApp')?.value === '1' ? 1 : 0
            };

            await apiCall(API.settings, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(settings)
            });
        } catch (err) {
            console.warn('[Entities] Failed to save settings:', err);
        }
    }

    async function saveEntityWorkingHours(entityId, isEdit = false) {
        try {
            if (!state.entityWorkingHours || state.entityWorkingHours.length === 0) return;

            if (isEdit) {
                try {
                    await apiCall(`${API.workingHours}?entity_id=${entityId}`, {
                        method: 'DELETE'
                    });
                } catch (err) {
                    console.warn('[Entities] Failed to clear old working hours:', err);
                }
            }

            for (const wh of state.entityWorkingHours) {
                const whData = {
                    entity_id: parseInt(entityId),
                    day_of_week: parseInt(wh.day_of_week),
                    is_open: wh.is_open ? 1 : 0,
                    open_time: wh.open_time || null,
                    close_time: wh.close_time || null
                };

                await apiCall(API.workingHours, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(whData)
                });
            }
        } catch (err) {
            console.warn('[Entities] Failed to save working hours:', err);
        }
    }

    async function saveEntityAttributes(entityId, isEdit = false) {
        try {
            if (!state.entityAttributes || state.entityAttributes.length === 0) return;

            if (isEdit) {
                try {
                    await apiCall(`${API.attributeValues}?entity_id=${entityId}`, {
                        method: 'DELETE'
                    });
                } catch (err) {
                    console.warn('[Entities] Failed to clear old attributes:', err);
                }
            }

            for (const attr of state.entityAttributes) {
                if (!attr.attribute_id) continue;

                const attrData = {
                    entity_id: parseInt(entityId),
                    attribute_id: parseInt(attr.attribute_id),
                    value: attr.value || ''
                };

                await apiCall(API.attributeValues, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(attrData)
                });
            }
        } catch (err) {
            console.warn('[Entities] Failed to save attributes:', err);
        }
    }

    async function saveEntityTranslations(entityId, translations) {
        try {
            // Delete removed translations
            for (const deletedId of state.deletedTranslationIds) {
                try {
                    await apiCall(`/api/entity_translations`, {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: deletedId })
                    });
                } catch (e) {
                    console.warn('[Entities] Failed to delete translation:', deletedId, e);
                }
            }
            state.deletedTranslationIds = [];

            // Check existing translations
            let existingTranslations = [];
            try {
                const existing = await apiCall(`${API.entities}/../entity_translations?entity_id=${entityId}`);
                if (existing.success) {
                    existingTranslations = Array.isArray(existing.data) ? existing.data : [];
                }
            } catch (e) {
                console.warn('[Entities] Check existing translations:', e);
            }

            for (const [langCode, trans] of Object.entries(translations)) {
                const transData = {
                    entity_id: parseInt(entityId),
                    language_code: langCode,
                    store_name: trans.store_name || '',
                    description: trans.description || '',
                    meta_title: trans.meta_title || '',
                    meta_description: trans.meta_description || ''
                };

                const existingTrans = existingTranslations.find(t => t.language_code === langCode);

                if (existingTrans) {
                    transData.id = parseInt(existingTrans.id);
                }

                await apiCall(`${API.entities}/../entity_translations`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(transData)
                });
            }
        } catch (err) {
            console.warn('[Entities] Failed to save translations:', err);
        }
    }

    async function saveEntityAddress(entityId, isEdit = false) {
        try {
            if (!state.addressData) return;

            // Prepare address data
            const addressData = {
                ...state.addressData,
                owner_type: 'entity',
                owner_id: parseInt(entityId),
                tenant_id: state.tenantId
            };

            // Check if address already exists for this entity
            if (!addressData.id) {
                try {
                    const existing = await apiCall(`${API.addresses}?owner_type=entity&owner_id=${entityId}&format=json`);
                    if (existing.success && existing.data) {
                        // API might return array or single object depending on pagination
                        const existingItems = Array.isArray(existing.data) ? existing.data : (existing.data.items || []);
                        // Or it could be a single object if get() was called, but here we used list filters
                        // The list endpoint returns { data: [...], meta: ... } usually, or just array

                        // Note: In existing logic, list returns { data: [...], meta: ... } usually.
                        // Let's assume response.data is the array of items based on standard structure
                        let items = [];
                        if (Array.isArray(existing.data)) {
                            items = existing.data;
                        } else if (existing.data && Array.isArray(existing.data.data)) {
                            items = existing.data.data; // Some APIs wrap in data.data
                        } else if (existing.data && Array.isArray(existing.data.items)) {
                            items = existing.data.items;
                        }

                        if (items.length > 0) {
                            addressData.id = items[0].id;
                            console.log('[Entities] Found existing address ID:', addressData.id);
                        }
                    }
                } catch (e) {
                    console.warn('[Entities] Failed to check existing address:', e);
                }
            }

            const method = addressData.id ? 'PUT' : 'POST';
            console.log(`[Entities] Saving address (${method})`, addressData);

            await apiCall(API.addresses, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(addressData)
            });
        } catch (err) {
            console.warn('[Entities] Failed to save address:', err);
        }
    }

    function validateForm() {
        let isValid = true;

        const requiredFields = [el.entityStoreName, el.entityPhone, el.entityEmail];

        requiredFields.forEach(field => {
            if (!field || !field.value.trim()) {
                isValid = false;
                if (field) {
                    field.classList.add('is-invalid');
                    field.addEventListener('input', () => field.classList.remove('is-invalid'), { once: true });
                }
            }
        });

        return isValid;
    }

    // ════════════════════════════════════════════════════════════
    // ATTRIBUTES MANAGEMENT
    // ════════════════════════════════════════════════════════════
    async function addAttribute() {
        if (!el.entityAttrSelect || !el.entityAttrSelect.value) return;

        const attrId = el.entityAttrSelect.value;
        const attrOption = el.entityAttrSelect.options[el.entityAttrSelect.selectedIndex];
        const attrName = attrOption.textContent;
        const attrType = attrOption.dataset.type;

        if (state.entityAttributes.find(a => a.attribute_id == attrId)) {
            showNotification(t('messages.attribute_exists', 'Attribute already added'), 'warning');
            return;
        }

        const attr = {
            attribute_id: attrId,
            attribute_name: attrName,
            attribute_type: attrType,
            value: ''
        };

        state.entityAttributes.push(attr);
        renderAttributes();
        el.entityAttrSelect.value = '';
    }

    function renderAttributes() {
        if (!el.entityAttributesList) return;

        el.entityAttributesList.innerHTML = state.entityAttributes.map((attr, idx) => {
            let inputField;
            switch (attr.attribute_type) {
                case 'select':
                case 'text':
                case 'number':
                case 'boolean':
                default:
                    inputField = `<input type="${attr.attribute_type === 'number' ? 'number' : 'text'}" 
                                         class="form-control" 
                                         value="${esc(attr.value || '')}" 
                                         onchange="Entities.updateAttributeValue(${idx}, this.value)">`;
                    break;
            }

            return `
                <div class="attribute-item" data-index="${idx}" style="margin-bottom:12px; padding:12px; border:1px solid var(--border-color); border-radius:4px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <label style="font-weight:bold;">${esc(attr.attribute_name)}</label>
                        <button type="button" class="btn btn-sm btn-danger" onclick="Entities.removeAttribute(${idx})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div style="margin-top:8px;">
                        ${inputField}
                    </div>
                </div>
            `;
        }).join('');
    }

    function updateAttributeValue(index, value) {
        if (state.entityAttributes[index]) {
            state.entityAttributes[index].value = value;
        }
    }

    function removeAttribute(index) {
        state.entityAttributes.splice(index, 1);
        renderAttributes();
    }

    // ════════════════════════════════════════════════════════════
    // WORKING HOURS MANAGEMENT
    // ════════════════════════════════════════════════════════════
    function getDefaultWorkingHours() {
        return DAYS_OF_WEEK.map(day => ({
            day_of_week: day.id,
            day_name: day.name,
            is_open: true,
            open_time: '09:00',
            close_time: '17:00'
        }));
    }

    function renderWorkingHours(workingHours) {
        if (!el.workingHoursList) return;

        el.workingHoursList.innerHTML = workingHours.map((wh, idx) => `
            <div class="working-hour-item" data-day="${wh.day_of_week}" style="margin-bottom:12px; padding:12px; border:1px solid var(--border-color); border-radius:4px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <strong style="min-width:100px;">${esc(wh.day_name)}</strong>
                        <label style="display:flex; align-items:center; gap:4px;">
                            <input type="checkbox" class="is-open-checkbox" 
                                   data-day="${wh.day_of_week}"
                                   ${wh.is_open ? 'checked' : ''}
                                   onchange="Entities.toggleWorkingDay(${wh.day_of_week}, this.checked)">
                            <span>${t('form.working_hours.open', 'Open')}</span>
                        </label>
                    </div>
                    ${!wh.is_open ? `<span style="color:var(--danger-color);">${t('form.working_hours.closed', 'Closed')}</span>` : ''}
                </div>
                <div style="display:flex; gap:12px; align-items:center; ${!wh.is_open ? 'opacity:0.5; pointer-events:none;' : ''}">
                    <div class="form-group" style="flex:1;">
                        <label>${t('form.working_hours.open_time', 'Open Time')}</label>
                        <input type="time" class="form-control open-time-input" 
                               data-day="${wh.day_of_week}"
                               value="${esc(wh.open_time || '')}"
                               onchange="Entities.updateWorkingTime(${wh.day_of_week}, 'open_time', this.value)">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>${t('form.working_hours.close_time', 'Close Time')}</label>
                        <input type="time" class="form-control close-time-input" 
                               data-day="${wh.day_of_week}"
                               value="${esc(wh.close_time || '')}"
                               onchange="Entities.updateWorkingTime(${wh.day_of_week}, 'close_time', this.value)">
                    </div>
                    <div style="display:flex; gap:4px; padding-top:8px;">
                        <button type="button" class="btn btn-sm btn-outline" onclick="Entities.setAllDay(${wh.day_of_week})">
                            ${t('form.working_hours.all_day', '24 Hours')}
                        </button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="Entities.setClosedAllDay(${wh.day_of_week})">
                            ${t('form.working_hours.closed_all_day', 'Closed')}
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function toggleWorkingDay(dayOfWeek, isOpen) {
        const dayIndex = state.entityWorkingHours.findIndex(wh => wh.day_of_week == dayOfWeek);
        if (dayIndex !== -1) {
            state.entityWorkingHours[dayIndex].is_open = isOpen;
        } else {
            const day = DAYS_OF_WEEK.find(d => d.id == dayOfWeek);
            if (day) {
                state.entityWorkingHours.push({
                    day_of_week: dayOfWeek,
                    day_name: day.name,
                    is_open: isOpen,
                    open_time: '09:00',
                    close_time: '17:00'
                });
            }
        }
        renderWorkingHours(state.entityWorkingHours);
    }

    function updateWorkingTime(dayOfWeek, field, value) {
        const dayIndex = state.entityWorkingHours.findIndex(wh => wh.day_of_week == dayOfWeek);
        if (dayIndex !== -1) {
            state.entityWorkingHours[dayIndex][field] = value;
        } else {
            const day = DAYS_OF_WEEK.find(d => d.id == dayOfWeek);
            if (day) {
                state.entityWorkingHours.push({
                    day_of_week: dayOfWeek,
                    day_name: day.name,
                    is_open: true,
                    open_time: field === 'open_time' ? value : '09:00',
                    close_time: field === 'close_time' ? value : '17:00'
                });
            }
        }
    }

    function setAllDay(dayOfWeek) {
        const dayIndex = state.entityWorkingHours.findIndex(wh => wh.day_of_week == dayOfWeek);
        if (dayIndex !== -1) {
            state.entityWorkingHours[dayIndex].is_open = true;
            state.entityWorkingHours[dayIndex].open_time = '00:00';
            state.entityWorkingHours[dayIndex].close_time = '23:59';
        } else {
            const day = DAYS_OF_WEEK.find(d => d.id == dayOfWeek);
            if (day) {
                state.entityWorkingHours.push({
                    day_of_week: dayOfWeek,
                    day_name: day.name,
                    is_open: true,
                    open_time: '00:00',
                    close_time: '23:59'
                });
            }
        }
        renderWorkingHours(state.entityWorkingHours);
    }

    function setClosedAllDay(dayOfWeek) {
        const dayIndex = state.entityWorkingHours.findIndex(wh => wh.day_of_week == dayOfWeek);
        if (dayIndex !== -1) {
            state.entityWorkingHours[dayIndex].is_open = false;
            state.entityWorkingHours[dayIndex].open_time = null;
            state.entityWorkingHours[dayIndex].close_time = null;
        } else {
            const day = DAYS_OF_WEEK.find(d => d.id == dayOfWeek);
            if (day) {
                state.entityWorkingHours.push({
                    day_of_week: dayOfWeek,
                    day_name: day.name,
                    is_open: false,
                    open_time: null,
                    close_time: null
                });
            }
        }
        renderWorkingHours(state.entityWorkingHours);
    }

    function applyToAllDays() {
        const firstOpenDay = state.entityWorkingHours.find(wh => wh.is_open);
        const openTime = firstOpenDay?.open_time || '09:00';
        const closeTime = firstOpenDay?.close_time || '17:00';
        const isOpen = firstOpenDay ? firstOpenDay.is_open : true;

        state.entityWorkingHours = DAYS_OF_WEEK.map(day => ({
            day_of_week: day.id,
            day_name: day.name,
            is_open: isOpen,
            open_time: isOpen ? openTime : null,
            close_time: isOpen ? closeTime : null
        }));

        renderWorkingHours(state.entityWorkingHours);
    }

    function resetWorkingHours() {
        state.entityWorkingHours = getDefaultWorkingHours();
        renderWorkingHours(state.entityWorkingHours);
    }

    // ════════════════════════════════════════════════════════════
    // MEDIA MANAGEMENT (REAL-TIME UPDATES)
    // ════════════════════════════════════════════════════════════
    function openMediaStudio(imageType) {
        if (!state.currentEntity?.id) {
            showNotification(t('messages.save_first', 'Please save the entity first before adding media'), 'warning');
            return;
        }

        _currentImageType = imageType;

        if (el.mediaModal && el.mediaFrame) {
            el.mediaModal.style.display = 'block';
            el.mediaFrame.src = `${CONFIG.mediaStudioBase}?embedded=1&tenant_id=${state.tenantId}&lang=${state.language}&owner_id=${state.currentEntity.id}&image_type_id=${imageType}`;

            el.mediaFrame.dataset.imageType = imageType;
        }
    }

    function closeMediaStudio() {
        if (el.mediaModal) {
            el.mediaModal.style.display = 'none';
            _currentImageType = null;
        }
    }

    function updateMediaPreview(imageType, imageUrl) {
        const previewId = `${imageType}Preview`;
        const urlDisplayId = `${imageType}UrlDisplay`;

        const previewEl = document.getElementById(previewId);
        const urlDisplayEl = document.getElementById(urlDisplayId);

        if (previewEl) {
            if (imageUrl) {
                const wrapper = document.createElement('div');
                wrapper.style.cssText = 'position:relative; display:inline-block;';

                const img = document.createElement('img');
                img.src = imageUrl;
                img.style.cssText = 'max-width:100%; max-height:200px; border-radius:4px;';
                wrapper.appendChild(img);

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = '✕';
                btn.title = t('form.media.delete', 'Delete image');
                btn.style.cssText = 'position:absolute; top:4px; right:4px; background:rgba(220,38,38,0.9); color:#fff; border:none; border-radius:50%; width:28px; height:28px; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center;';
                btn.addEventListener('click', () => deleteEntityImage(imageType));
                wrapper.appendChild(btn);

                previewEl.innerHTML = '';
                previewEl.appendChild(wrapper);
            } else {
                previewEl.innerHTML = `<div class="placeholder">${t(`form.media.no_${imageType}`, `No ${imageType} selected`)}</div>`;
            }
        }

        if (urlDisplayEl) {
            urlDisplayEl.value = imageUrl || '';
        }

        mediaUrls[imageType] = imageUrl;

        const hiddenField = document.getElementById(`entity${imageType.charAt(0).toUpperCase() + imageType.slice(1)}Url`);
        if (hiddenField) {
            hiddenField.value = imageUrl || '';
        }
    }

    async function handleImageSelected(imageType, imageUrl) {
        if (!state.currentEntity?.id) return;

        try {
            const imageData = {
                owner_id: state.currentEntity.id,
                image_type_id: IMAGE_TYPES[imageType.toUpperCase()],
                tenant_id: state.tenantId,
                user_id: state.userId,
                url: imageUrl,
                filename: imageUrl.split('/').pop(),
                mime_type: 'image/jpeg',
                visibility: 'public',
                is_main: 1,
                sort_order: 0
            };

            const result = await apiCall(API.images, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(imageData)
            });

            if (result.success) {
                updateMediaPreview(imageType, imageUrl);
                showNotification(t('messages.image_saved', 'Image saved successfully'), 'success');

                // Update the entity's media URL in the main form
                const hiddenField = document.getElementById(`entity${imageType.charAt(0).toUpperCase() + imageType.slice(1)}Url`);
                if (hiddenField) {
                    hiddenField.value = imageUrl;
                }

                // Also update the entity record with the media URL
                await updateEntityMediaUrl(imageType, imageUrl);
            }
        } catch (err) {
            console.error('[Entities] Failed to save image:', err);
            showNotification(t('messages.error.save_failed', 'Failed to save image'), 'error');
        }
    }

    async function updateEntityMediaUrl(imageType, imageUrl) {
        if (!state.currentEntity?.id) return;

        try {
            const fieldName = `entity_${imageType}`;
            const updateData = {
                id: state.currentEntity.id,
                [fieldName]: imageUrl
            };

            const result = await apiCall(API.entities, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(updateData)
            });

            if (!result.success) {
                console.warn('[Entities] Failed to update entity media URL:', result.error);
            }
        } catch (err) {
            console.warn('[Entities] Failed to update entity media URL:', err);
        }
    }

    async function deleteEntityImage(imageType) {
        if (!state.currentEntity?.id) return;

        if (!confirm(t('messages.confirm_delete_image', 'Are you sure you want to delete this image?'))) {
            return;
        }

        try {
            const imageTypeId = IMAGE_TYPES[imageType.toUpperCase()];
            if (!imageTypeId) return;

            // Delete from images API
            const deleteParams = new URLSearchParams({
                owner_id: String(state.currentEntity.id),
                image_type_id: String(imageTypeId)
            });
            await apiCall(`/api/images/by_owner?${deleteParams}`, {
                method: 'DELETE'
            });

            // Clear the media URL on the entity
            await updateEntityMediaUrl(imageType, null);

            // Clear preview
            mediaUrls[imageType] = null;
            updateMediaPreview(imageType, null);

            showNotification(t('messages.image_deleted', 'Image deleted successfully'), 'success');
        } catch (err) {
            console.error('[Entities] Failed to delete image:', err);
            showNotification(t('messages.error.delete_failed', 'Failed to delete image'), 'error');
        }
    }

    function clearMediaPreviews() {
        Object.keys(mediaUrls).forEach(key => {
            updateMediaPreview(key, null);
        });
    }

    async function loadEntityMedia(entityId) {
        try {
            console.log('[Entities] Loading media for entity:', entityId);

            // Load logo
            const logoResult = await apiCall(`/api/images/by_owner?owner_id=${entityId}&image_type_id=${IMAGE_TYPES.LOGO}`);
            if (logoResult.success && logoResult.data) {
                const logo = Array.isArray(logoResult.data) ? logoResult.data[0] : logoResult.data;
                if (logo && logo.url) {
                    mediaUrls.logo = logo.url;
                    updateMediaPreview('logo', logo.url);
                }
            }

            // Load cover
            const coverResult = await apiCall(`/api/images/by_owner?owner_id=${entityId}&image_type_id=${IMAGE_TYPES.COVER}`);
            if (coverResult.success && coverResult.data) {
                const cover = Array.isArray(coverResult.data) ? coverResult.data[0] : coverResult.data;
                if (cover && cover.url) {
                    mediaUrls.cover = cover.url;
                    updateMediaPreview('cover', cover.url);
                }
            }

            // Load license
            const licenseResult = await apiCall(`/api/images/by_owner?owner_id=${entityId}&image_type_id=${IMAGE_TYPES.LICENSE}`);
            if (licenseResult.success && licenseResult.data) {
                const license = Array.isArray(licenseResult.data) ? licenseResult.data[0] : licenseResult.data;
                if (license && license.url) {
                    mediaUrls.license = license.url;
                    updateMediaPreview('license', license.url);
                }
            }
        } catch (err) {
            console.warn('[Entities] Failed to load media:', err);
        }
    }

    // ════════════════════════════════════════════════════════════
    // ADDRESS MANAGEMENT (IFRAME INTEGRATION)
    // ════════════════════════════════════════════════════════════
    function loadAddressFragment(entityId) {
        if (!el.addressEmbeddedContainer) return;

        const ownerId = entityId || state.currentEntity?.id;
        if (!ownerId) {
            el.addressEmbeddedContainer.innerHTML = `
                <div class="alert alert-warning">
                    ${t('messages.save_entity_first', 'Please save the entity first to manage addresses')}
                </div>
            `;
            return;
        }

        // Check if iframe is already loaded for this entity
        const existingFrame = document.getElementById('addressFrame');
        if (existingFrame && existingFrame.dataset.ownerId === String(ownerId)) {
            return;
        }

        el.addressEmbeddedContainer.innerHTML = `
            <div class="loading-state" id="addressLoading">
                <div class="spinner"></div>
                <p>${t('common.loading', 'Loading address form...')}</p>
            </div>
        `;

        const iframe = document.createElement('iframe');
        iframe.id = 'addressFrame';
        iframe.dataset.ownerId = String(ownerId);
        iframe.style.cssText = 'width:100%; height:500px; border:none;';
        iframe.onload = () => {
            document.getElementById('addressLoading')?.remove();

            try {
                iframe.contentWindow.postMessage({
                    type: 'set-parent',
                    parentWindow: window.location.href,
                    entityId: ownerId
                }, '*');
            } catch (err) {
                console.warn('[Entities] Failed to send message to address iframe:', err);
            }
        };

        iframe.onerror = () => {
            el.addressEmbeddedContainer.innerHTML = `
                <div class="error-state">
                    <div class="error-icon">⚠️</div>
                    <h3>${t('messages.error.load_failed', 'Failed to load address form')}</h3>
                    <p>${t('messages.try_again', 'Please try again')}</p>
                </div>
            `;
        };

        // Append iframe to DOM first, then set src to trigger loading
        el.addressEmbeddedContainer.appendChild(iframe);
        const addressFragmentUrl = CONFIG.addressesFragment || '/admin/fragments/addresses.php';
        iframe.src = `${addressFragmentUrl}?embedded=1&tenant_id=${state.tenantId}&lang=${state.language}&owner_type=entity&owner_id=${ownerId}`;
    }

    function clearAddress() {
        if (el.addressEmbeddedContainer) {
            el.addressEmbeddedContainer.innerHTML = `
                <div class="loading-state" id="addressLoading">
                    <div class="spinner"></div>
                    <p>${t('common.loading', 'Loading address form...')}</p>
                </div>
            `;
        }
        state.addressData = null;
    }

    async function requestAddressDataFromIframe() {
        const iframe = document.getElementById('addressFrame');
        if (!iframe || !iframe.contentWindow) {
            return null;
        }

        return new Promise((resolve) => {
            const messageHandler = (e) => {
                if (e.data && e.data.type === 'current-address-data') {
                    window.removeEventListener('message', messageHandler);
                    resolve(e.data.addressData);
                }
            };

            setTimeout(() => {
                window.removeEventListener('message', messageHandler);
                resolve(null);
            }, 5000);

            window.addEventListener('message', messageHandler);

            try {
                iframe.contentWindow.postMessage({
                    type: 'get-address-data'
                }, '*');
            } catch (err) {
                console.warn('[Entities] Failed to request address data:', err);
                window.removeEventListener('message', messageHandler);
                resolve(null);
            }
        });
    }

    function handleAddressMessage(e) {
        if (!e.data || typeof e.data !== 'object') return;

        switch (e.data.type) {
            case 'address-saved':
                state.addressData = e.data.addressData || {};
                showNotification(t('messages.address_saved', 'Address saved successfully'), 'success');

                if (e.source && e.source.postMessage) {
                    e.source.postMessage({
                        type: 'address-saved-ack',
                        success: true,
                        timestamp: Date.now()
                    }, e.origin || '*');
                }
                break;

            case 'address-deleted':
                showNotification(t('messages.address_deleted', 'Address deleted successfully'), 'success');
                state.addressData = null;
                break;

            case 'address-form-closed':
                console.log('[Entities] Address form closed');
                break;

            case 'address-loaded':
                console.log('[Entities] Address data loaded in iframe');
                break;

            case 'error':
                showNotification(e.data.message || t('messages.error.unknown', 'An error occurred'), 'error');
                break;

            case 'get-entity-info':
                if (e.source && e.source.postMessage && state.currentEntity) {
                    e.source.postMessage({
                        type: 'entity-info',
                        entityId: state.currentEntity.id,
                        entityName: state.currentEntity.store_name,
                        tenantId: state.tenantId,
                        language: state.language
                    }, e.origin || '*');
                }
                break;

            case 'media-selected':
                // Handle media selection from media studio
                const imageType = e.data.imageTypeId ?
                    (e.data.imageTypeId == IMAGE_TYPES.LOGO ? 'logo' :
                        e.data.imageTypeId == IMAGE_TYPES.COVER ? 'cover' :
                            e.data.imageTypeId == IMAGE_TYPES.LICENSE ? 'license' : null) : null;

                if (imageType && e.data.images && e.data.images[0]) {
                    const imageUrl = e.data.images[0].url || e.data.images[0].thumb_url;
                    handleImageSelected(imageType, imageUrl);
                    closeMediaStudio();
                }
                break;
        }
    }

    // ════════════════════════════════════════════════════════════
    // TRANSLATIONS MANAGEMENT
    // ════════════════════════════════════════════════════════════
    function addTranslation() {
        const langCode = el.entityLangSelect?.value;
        if (!langCode) return;

        const langName = el.entityLangSelect.options[el.entityLangSelect.selectedIndex].textContent;

        const existingPanel = document.querySelector(`[data-lang="${langCode}"]`);
        if (existingPanel) {
            showNotification(t('messages.translation_exists', 'Translation already added'), 'warning');
            return;
        }

        const panel = createTranslationPanel(langCode, langName, {});
        if (el.entityTranslations) {
            el.entityTranslations.appendChild(panel);
        }

        el.entityLangSelect.value = '';
    }

    function createTranslationPanel(langCode, langName, data) {
        const panel = document.createElement('div');
        panel.className = 'translation-panel';
        panel.dataset.lang = langCode;
        if (data.id) {
            panel.dataset.translationId = data.id;
        }

        panel.innerHTML = `
            <div class="translation-panel-header">
                <h5><i class="fas fa-language"></i> ${esc(langName)} (${esc(langCode)})</h5>
                <button type="button" class="btn btn-sm btn-danger btn-remove-translation">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="translation-panel-body">
                <div class="form-group">
                    <label>${t('form.fields.store_name_translation.label', 'Store Name')}</label>
                    <input type="text" class="form-control trans-store-name" value="${esc(data.store_name || '')}" data-lang="${langCode}">
                </div>
                <div class="form-group">
                    <label>${t('form.fields.description.label', 'Description')}</label>
                    <textarea class="form-control trans-desc" rows="4" data-lang="${langCode}">${esc(data.description || '')}</textarea>
                </div>
                <div class="form-group">
                    <label>${t('form.fields.meta_title.label', 'Meta Title')}</label>
                    <input type="text" class="form-control trans-meta-title" value="${esc(data.meta_title || '')}" data-lang="${langCode}">
                </div>
                <div class="form-group">
                    <label>${t('form.fields.meta_description.label', 'Meta Description')}</label>
                    <textarea class="form-control trans-meta-desc" rows="2" data-lang="${langCode}">${esc(data.meta_description || '')}</textarea>
                </div>
            </div>
        `;

        // Attach delete handler with proper tracking
        panel.querySelector('.btn-remove-translation').addEventListener('click', function() {
            const translationId = panel.dataset.translationId;
            if (translationId) {
                state.deletedTranslationIds.push(parseInt(translationId));
            }
            panel.remove();
        });

        return panel;
    }

    function collectTranslations() {
        const translations = {};

        document.querySelectorAll('.translation-panel').forEach(panel => {
            const lang = panel.dataset.lang;
            const storeName = panel.querySelector('.trans-store-name')?.value || '';
            const desc = panel.querySelector('.trans-desc')?.value || '';
            const metaTitle = panel.querySelector('.trans-meta-title')?.value || '';
            const metaDesc = panel.querySelector('.trans-meta-desc')?.value || '';

            if (storeName || desc || metaTitle || metaDesc) {
                translations[lang] = {
                    store_name: storeName,
                    description: desc,
                    meta_title: metaTitle,
                    meta_description: metaDesc
                };
            }
        });

        return translations;
    }

    async function loadEntityTranslations(entityId) {
        try {
            const result = await apiCall(`/api/entity_translations?entity_id=${entityId}&format=json`);
            if (result.success) {
                const items = Array.isArray(result.data) ? result.data : (result.data?.items || []);
                if (el.entityTranslations) el.entityTranslations.innerHTML = '';
                items.forEach(trans => {
                    const langName = state.languages.find(l => l.code === trans.language_code)?.name || trans.language_code;
                    const panel = createTranslationPanel(trans.language_code, langName, {
                        id: trans.id,
                        store_name: trans.store_name || '',
                        description: trans.description || '',
                        meta_title: trans.meta_title || '',
                        meta_description: trans.meta_description || ''
                    });
                    if (el.entityTranslations) el.entityTranslations.appendChild(panel);
                });
            }
        } catch (err) {
            console.warn('[Entities] Failed to load translations:', err);
        }
    }

    async function loadEntityAttributes(entityId) {
        try {
            const result = await apiCall(`${API.attributeValues}?entity_id=${entityId}&format=json`);
            if (result.success) {
                let items = [];
                if (Array.isArray(result.data)) {
                    items = result.data;
                } else if (result.data && Array.isArray(result.data.items)) {
                    items = result.data.items;
                } else if (result.data && Array.isArray(result.data.data)) {
                    items = result.data.data;
                }

                const attrs = [];
                for (const item of items) {
                    const attrInfo = state.attributes.find(a => String(a.id) === String(item.attribute_id));

                    attrs.push({
                        attribute_id: item.attribute_id,
                        attribute_name: attrInfo?.name || item.attribute_name || item.attribute_slug || `Attribute #${item.attribute_id}`,
                        attribute_type: attrInfo?.attribute_type || item.attribute_type || 'text',
                        value: item.value || ''
                    });
                }

                state.entityAttributes = attrs;
                renderAttributes();
            }
        } catch (err) {
            console.warn('[Entities] Failed to load attributes:', err);
        }
    }

    async function loadEntitySettings(entityId) {
        try {
            const result = await apiCall(`${API.settings}?entity_id=${entityId}&format=json`);
            if (result.success && result.data) {
                const settings = result.data;
                state.entitySettings = settings;

                if (el.settingAutoAcceptOrders) el.settingAutoAcceptOrders.value = settings.auto_accept_orders != null ? String(settings.auto_accept_orders) : '0';
                if (el.settingAllowCod) el.settingAllowCod.value = settings.allow_cod != null ? String(settings.allow_cod) : '0';
                if (el.settingMinOrderAmount) el.settingMinOrderAmount.value = settings.min_order_amount != null ? String(settings.min_order_amount) : '0.00';
                if (el.settingAllowOnlineBooking) el.settingAllowOnlineBooking.value = settings.allow_online_booking != null ? String(settings.allow_online_booking) : '0';
                if (el.settingBookingWindowDays) el.settingBookingWindowDays.value = settings.booking_window_days != null ? String(settings.booking_window_days) : '0';
                if (el.settingMaxBookingsPerSlot) el.settingMaxBookingsPerSlot.value = settings.max_bookings_per_slot != null ? String(settings.max_bookings_per_slot) : '0';
                if (el.settingShowReviews) el.settingShowReviews.value = settings.show_reviews != null ? String(settings.show_reviews) : '1';
                if (el.settingShowContactInfo) el.settingShowContactInfo.value = settings.show_contact_info != null ? String(settings.show_contact_info) : '1';
                if (el.settingFeaturedInApp) el.settingFeaturedInApp.value = settings.featured_in_app != null ? String(settings.featured_in_app) : '0';
            }
        } catch (err) {
            console.warn('[Entities] Failed to load settings:', err);
        }
    }

    async function loadEntityWorkingHours(entityId) {
        try {
            const result = await apiCall(`${API.workingHours}?entity_id=${entityId}&format=json`);
            if (result.success) {
                const items = Array.isArray(result.data) ? result.data : [];

                if (items.length > 0) {
                    const workingHours = items.map(item => {
                        const day = DAYS_OF_WEEK.find(d => d.id == item.day_of_week);
                        return {
                            day_of_week: item.day_of_week,
                            day_name: day ? day.name : `Day ${item.day_of_week}`,
                            is_open: item.is_open == 1,
                            open_time: item.open_time || null,
                            close_time: item.close_time || null
                        };
                    });

                    state.entityWorkingHours = workingHours;
                    renderWorkingHours(workingHours);
                } else {
                    resetWorkingHours();
                }
            }
        } catch (err) {
            console.warn('[Entities] Failed to load working hours:', err);
        }
    }

    // ════════════════════════════════════════════════════════════
    // DELETE FUNCTION
    // ════════════════════════════════════════════════════════════
    async function deleteEntity(id) {
        if (!confirm(t('messages.confirm_delete', 'Are you sure you want to delete this entity?'))) {
            return;
        }

        try {
            const result = await apiCall(API.entities, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });

            if (result.success) {
                showNotification(t('messages.deleted', 'Entity deleted successfully'), 'success');
                hideForm();
                loadEntities(state.page);
            } else {
                throw new Error(result.error || 'Delete failed');
            }
        } catch (err) {
            console.error('[Entities] Delete failed:', err);
            showNotification(err.message || t('messages.error.delete_failed', 'Failed to delete entity'), 'error');
        }
    }

    // ════════════════════════════════════════════════════════════
    // FILTERS & PAGINATION
    // ════════════════════════════════════════════════════════════
    function applyFilters() {
        state.filters = {};

        if (el.searchInput?.value) state.filters.search = el.searchInput.value;
        if (el.tenantFilter?.value) state.filters.tenant_id = el.tenantFilter.value;
        if (el.statusFilter?.value) state.filters.status = el.statusFilter.value;
        if (el.vendorTypeFilter?.value) state.filters.vendor_type = el.vendorTypeFilter.value;
        if (el.storeTypeFilter?.value) state.filters.store_type = el.storeTypeFilter.value;
        if (el.verifiedFilter?.value) state.filters.is_verified = el.verifiedFilter.value;

        loadEntities(1);
    }

    function resetFilters() {
        state.filters = {};

        if (el.searchInput) el.searchInput.value = '';
        if (el.tenantFilter) el.tenantFilter.value = state.tenantId;
        if (el.statusFilter) el.statusFilter.value = '';
        if (el.vendorTypeFilter) el.vendorTypeFilter.value = '';
        if (el.storeTypeFilter) el.storeTypeFilter.value = '';
        if (el.verifiedFilter) el.verifiedFilter.value = '';

        loadEntities(1);
    }

    function updatePagination(meta) {
        if (!el.pagination || !el.paginationInfo) return;

        const { page = 1, per_page = 25, total = 0 } = meta;
        const totalPages = Math.ceil(total / per_page);
        const start = total > 0 ? (page - 1) * per_page + 1 : 0;
        const end = Math.min(page * per_page, total);

        el.paginationInfo.textContent = `${start}-${end} of ${total}`;

        if (totalPages <= 1) {
            el.pagination.innerHTML = '';
            return;
        }

        let html = '';

        html += `<button class="pagination-btn" ${page <= 1 ? 'disabled' : ''} onclick="Entities.load(${page - 1})">
            <i class="fas fa-chevron-left"></i>
        </button>`;

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)) {
                html += `<button class="pagination-btn ${i === page ? 'active' : ''}" onclick="Entities.load(${i})">${i}</button>`;
            } else if (i === page - 3 || i === page + 3) {
                html += `<span class="pagination-ellipsis">...</span>`;
            }
        }

        html += `<button class="pagination-btn" ${page >= totalPages ? 'disabled' : ''} onclick="Entities.load(${page + 1})">
            <i class="fas fa-chevron-right"></i>
        </button>`;

        el.pagination.innerHTML = html;
    }

    function updateResultsCount(total) {
        if (el.resultsCount && el.resultsCountText) {
            el.resultsCountText.textContent = `${total} ${t('entities.found', 'entities found')}`;
            el.resultsCount.style.display = total > 0 ? 'block' : 'none';
        }
    }

    // ════════════════════════════════════════════════════════════
    // UI STATE HELPERS
    // ════════════════════════════════════════════════════════════
    function showLoading() {
        if (el.loading) {
            el.loading.innerHTML = `<div class="spinner"></div><p>${t('entities.loading', 'Loading...')}</p>`;
            el.loading.style.display = 'flex';
        }
        if (el.container) el.container.style.display = 'none';
        if (el.empty) el.empty.style.display = 'none';
        if (el.error) el.error.style.display = 'none';
    }

    function showTable() {
        if (el.loading) el.loading.style.display = 'none';
        if (el.container) el.container.style.display = 'block';
        if (el.empty) el.empty.style.display = 'none';
        if (el.error) el.error.style.display = 'none';
    }

    function showEmpty() {
        if (el.loading) el.loading.style.display = 'none';
        if (el.container) el.container.style.display = 'none';
        if (el.error) el.error.style.display = 'none';
        if (el.empty) {
            el.empty.innerHTML = `
                <div class="empty-icon">🏢</div>
                <h3>${t('table.empty.title', 'No Entities Found')}</h3>
                <p>${t('table.empty.message', 'Start by adding your first entity')}</p>
                ${state.permissions.canCreate ? `<button class="btn btn-primary" onclick="Entities.add()">
                    <i class="fas fa-plus"></i> ${t('table.empty.add_first', 'Add First Entity')}
                </button>` : ''}
            `;
            el.empty.style.display = 'flex';
        }
        if (el.tbody) el.tbody.innerHTML = '';
    }

    function showError(message) {
        if (el.loading) el.loading.style.display = 'none';
        if (el.container) el.container.style.display = 'none';
        if (el.empty) el.empty.style.display = 'none';
        if (el.error) {
            if (el.errorMessage) el.errorMessage.textContent = message;
            el.error.style.display = 'flex';
        }
    }

    function showNotification(message, type = 'info') {
        if (AF.notify) {
            AF.notify(message, type);
        } else {
            alert(message);
        }
    }

    // ════════════════════════════════════════════════════════════
    // UTILITIES
    // ════════════════════════════════════════════════════════════
    function esc(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    // ════════════════════════════════════════════════════════════
    // INITIALIZATION
    // ════════════════════════════════════════════════════════════
    async function init() {
        console.log('[Entities] Initializing...');

        const $id = (id) => document.getElementById(id);

        el = {
            // Containers
            container: $id('tableContainer'),
            loading: $id('tableLoading'),
            empty: $id('emptyState'),
            error: $id('errorState'),
            errorMessage: $id('errorMessage'),

            // Form
            formContainer: $id('entityFormContainer'),
            form: $id('entityForm'),
            formTitle: $id('formTitle'),
            formId: $id('formId'),

            // Form fields - Basic
            entityStoreName: $id('entityStoreName'),
            entitySlug: $id('entitySlug'),
            entityIsMain: $id('entityIsMain'),
            entityBranchCode: $id('entityBranchCode'),
            entityVendorType: $id('entityVendorType'),
            entityStoreType: $id('entityStoreType'),
            entityRegistrationNumber: $id('entityRegistrationNumber'),
            entityTaxNumber: $id('entityTaxNumber'),
            entityStatus: $id('entityStatus'),
            entityIsVerified: $id('entityIsVerified'),
            entityTenantId: $id('entityTenantId'),
            entityUserId: $id('entityUserId'),

            // Form fields - Contact
            entityPhone: $id('entityPhone'),
            entityMobile: $id('entityMobile'),
            entityEmail: $id('entityEmail'),
            entityWebsite: $id('entityWebsite'),
            entitySuspensionReason: $id('entitySuspensionReason'),

            // Form fields - Settings
            settingAutoAcceptOrders: $id('settingAutoAcceptOrders'),
            settingAllowCod: $id('settingAllowCod'),
            settingMinOrderAmount: $id('settingMinOrderAmount'),
            settingAllowOnlineBooking: $id('settingAllowOnlineBooking'),
            settingBookingWindowDays: $id('settingBookingWindowDays'),
            settingMaxBookingsPerSlot: $id('settingMaxBookingsPerSlot'),
            settingShowReviews: $id('settingShowReviews'),
            settingShowContactInfo: $id('settingShowContactInfo'),
            settingFeaturedInApp: $id('settingFeaturedInApp'),

            // Working Hours
            workingHoursList: $id('workingHoursList'),
            btnApplyToAll: $id('btnApplyToAll'),
            btnResetHours: $id('btnResetHours'),

            // Attributes
            entityAttrSelect: $id('entityAttrSelect'),
            btnAddEntityAttribute: $id('btnAddEntityAttribute'),
            entityAttributesList: $id('entityAttributesList'),

            // Media
            mediaModal: $id('mediaStudioModal'),
            mediaFrame: $id('mediaStudioFrame'),
            mediaClose: $id('mediaStudioClose'),

            // Address
            addressEmbeddedContainer: $id('addressEmbeddedContainer'),

            // Translations
            entityTranslations: $id('entityTranslations'),
            entityLangSelect: $id('entityLangSelect'),
            entityAddLangBtn: $id('entityAddLangBtn'),

            // Table
            tbody: $id('tableBody'),

            // Filters
            searchInput: $id('searchInput'),
            tenantFilter: $id('tenantFilter'),
            statusFilter: $id('statusFilter'),
            vendorTypeFilter: $id('vendorTypeFilter'),
            storeTypeFilter: $id('storeTypeFilter'),
            verifiedFilter: $id('verifiedFilter'),

            // Buttons
            btnSubmit: $id('btnSubmitForm'),
            btnAdd: $id('btnAddEntity'),
            btnClose: $id('btnCloseForm'),
            btnCancel: $id('btnCancelForm'),
            btnApply: $id('btnApplyFilters'),
            btnReset: $id('btnResetFilters'),
            btnRetry: $id('btnRetry'),
            btnDeleteEntity: $id('btnDeleteEntity'),

            // Pagination
            pagination: $id('pagination'),
            paginationInfo: $id('paginationInfo'),
            resultsCount: $id('resultsCount'),
            resultsCountText: $id('resultsCountText')
        };

        // Load translations
        await loadTranslations(state.language);

        // Setup event listeners
        if (el.form) {
            el.form.onsubmit = saveEntity;
        }
        if (el.btnAdd) {
            el.btnAdd.onclick = () => showForm();
        }
        if (el.btnClose) el.btnClose.onclick = hideForm;
        if (el.btnCancel) el.btnCancel.onclick = hideForm;
        if (el.btnApply) el.btnApply.onclick = applyFilters;
        if (el.btnReset) el.btnReset.onclick = resetFilters;
        if (el.btnRetry) el.btnRetry.onclick = () => loadEntities(state.page);
        if (el.btnDeleteEntity) el.btnDeleteEntity.onclick = () => {
            if (state.currentEntity) deleteEntity(state.currentEntity.id);
        };

        // Working Hours
        if (el.btnApplyToAll) el.btnApplyToAll.onclick = applyToAllDays;
        if (el.btnResetHours) el.btnResetHours.onclick = resetWorkingHours;

        // Attributes
        if (el.btnAddEntityAttribute) el.btnAddEntityAttribute.onclick = addAttribute;

        // Media buttons
        document.querySelectorAll('.btnSelectMedia').forEach(btn => {
            btn.onclick = function () {
                const imageTypeId = this.dataset.imageType;
                openMediaStudio(imageTypeId);
            };
        });

        // Media Studio close
        if (el.mediaClose) el.mediaClose.onclick = closeMediaStudio;

        // Translations
        if (el.entityAddLangBtn) el.entityAddLangBtn.onclick = addTranslation;

        // Message listeners
        if (!_messageListenerAdded) {
            _messageListenerAdded = true;
            window.addEventListener('message', handleAddressMessage);
        }

        // Initialize tabs
        initTabs();

        // Initialize working hours with default values
        if (!state.currentEntity) {
            resetWorkingHours();
        }

        // Load dropdown data
        await loadDropdownData();

        // Load initial data
        await loadEntities(1);

        console.log('[Entities] ✓ Initialized successfully');
    }

    // ════════════════════════════════════════════════════════════
    // PUBLIC API
    // ════════════════════════════════════════════════════════════
    window.Entities = {
        init,
        load: loadEntities,
        add: () => showForm(),
        edit: async (id) => {
            try {
                const result = await apiCall(`${API.entities}?id=${id}&format=json&lang=${state.language}&tenant_id=${state.tenantId}`);
                if (result.success && result.data) {
                    showForm(result.data);
                } else {
                    throw new Error('Entity not found');
                }
            } catch (err) {
                console.error('[Entities] Edit failed:', err);
                showNotification(err.message || t('messages.error.load_failed', 'Failed to load entity'), 'error');
            }
        },
        remove: deleteEntity,
        updateAttributeValue,
        removeAttribute,
        toggleWorkingDay,
        updateWorkingTime,
        setAllDay,
        setClosedAllDay,
        applyToAllDays,
        resetWorkingHours,
        setLanguage: async (lang) => {
            state.language = lang;
            await loadTranslations(lang);
            setDirectionForLang(lang);
            loadEntities(state.page);
        },
        handleImageSelected,
        deleteImage: deleteEntityImage
    };

    // Fragment support
    window.page = { run: init };

    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            if (window.AdminFramework && !window.page.__fragment_init) {
                init().catch(function (e) { console.error('[Entities] Auto-init failed:', e); });
            }
        });
    } else {
        if (window.AdminFramework && !window.page.__fragment_init) {
            init().catch(function (e) { console.error('[Entities] Auto-init failed:', e); });
        }
    }
    window.page.__fragment_init = false;

    console.log('[Entities] Module loaded');

})();