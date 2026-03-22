/**
 * /admin/assets/js/pages/jobs.js
 * Jobs Management — Full Rebuild
 * Uses AdminFramework (AF) — same pattern as brands.js
 */
(function () {
    'use strict';

    const AF = window.AdminFramework;
    const CFG = window.JOBS_CONFIG || {};

    // ─── State ────────────────────────────────────────────────────────────────
    const state = {
        lang: window.USER_LANGUAGE || CFG.lang || 'ar',
        tenant: window.APP_CONFIG?.TENANT_ID || CFG.tenantId || 1,
        userId: window.APP_CONFIG?.USER_ID || CFG.userId || null,
        csrf: window.APP_CONFIG?.CSRF_TOKEN || CFG.csrfToken || '',
        i18n: {},
        perms: {},
        // per-module
        jobs: { page: 1, items: [], total: 0, filters: {}, loaded: false },
        apps: { page: 1, items: [], total: 0, filters: {}, loaded: false },
        interviews: { page: 1, items: [], total: 0, filters: {}, loaded: false },
        alerts: { page: 1, items: [], total: 0, filters: {}, loaded: false },
        questions: { page: 1, items: [], total: 0, filters: {}, loaded: false }
    };

    const LIMIT = 25;

    const URL = {
        jobs: '/api/jobs',
        apps: '/api/job_applications',
        interviews: '/api/job_interviews',
        alerts: '/api/job_alerts',
        questions: '/api/job_application_questions',
        skills: '/api/job_skills',
        langs: '/api/languages',
        cats: '/api/job_categories',
        countries: '/api/countries',
        cities: '/api/cities',
        currencies: '/api/currencies'
    };

    // ─── Helpers ──────────────────────────────────────────────────────────────
    function $(id) { return AF ? AF.$(id) : document.getElementById(id); }

    function esc(v) {
        if (v == null) return '';
        const d = document.createElement('div');
        d.textContent = String(v);
        return d.innerHTML;
    }

    /** Convert HTML datetime-local (2024-02-15T23:59) to MySQL (2024-02-15 23:59:00) */
    function toDbDatetime(v) {
        if (!v) return null;
        // already correct format
        if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(v)) return v;
        // HTML datetime-local: 2024-02-15T23:59  or  2024-02-15T23:59:00
        const m = String(v).match(/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})(:\d{2})?/);
        if (m) return `${m[1]} ${m[2]}${m[3] || ':00'}`;
        // date only: 2024-02-15
        if (/^\d{4}-\d{2}-\d{2}$/.test(v)) return `${v} 00:00:00`;
        return null;
    }

    function t(key, fb) {
        const v = key.split('.').reduce((o, k) => (o && o[k] !== undefined ? o[k] : null), state.i18n);
        return (v !== null && v !== undefined) ? String(v) : (fb || key);
    }

    function notify(msg, type) {
        if (AF) {
            if (type === 'success') AF.success(msg);
            else if (type === 'error') AF.error(msg);
            else if (type === 'warning') AF.warning ? AF.warning(msg) : AF.error(msg);
            else AF.success(msg);
        } else { console.log(`[Jobs][${type}] ${msg}`); }
    }

    // AF-compatible fetch: try AF.get/AF.api first, fallback to raw fetch
    async function apiFetch(url, opts = {}) {
        if (AF && opts.method && opts.method !== 'GET') {
            // POST/PUT/DELETE
            const method = opts.method;
            const body = opts.json || opts.body;
            const r = await AF.api(url, {
                method,
                headers: { 'Content-Type': 'application/json' },
                body: body && typeof body !== 'string' ? JSON.stringify(body) : body
            });
            return r;
        } else if (AF && (!opts.method || opts.method === 'GET')) {
            return await AF.get(url);
        } else {
            const h = { 'X-Requested-With': 'XMLHttpRequest' };
            if (opts.method && opts.method !== 'GET') h['X-CSRF-Token'] = state.csrf;
            if (opts.json) { h['Content-Type'] = 'application/json'; opts.body = JSON.stringify(opts.json); delete opts.json; }
            const r = await fetch(url, { credentials: 'same-origin', ...opts, headers: { ...h, ...(opts.headers || {}) } });
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return await r.json();
        }
    }

    function buildQS(mod) {
        const p = new URLSearchParams({
            page: mod.page,
            limit: LIMIT,
            tenant_id: state.tenant,
            lang: state.lang,
            format: 'json'
        });
        Object.entries(mod.filters || {}).forEach(([k, v]) => { if (v !== '' && v != null) p.set(k, v); });
        return p.toString();
    }

    /** Normalise any API response shape into { items, total } */
    function normalize(r) {
        let items = [], total = 0;
        const d = r?.data ?? r;
        if (Array.isArray(d)) { items = d; total = r?.meta?.total ?? r?.pagination?.total ?? d.length; }
        else if (d && Array.isArray(d.items)) { items = d.items; total = d.meta?.total ?? d.pagination?.total ?? items.length; }
        else if (d && Array.isArray(d.data)) { items = d.data; total = d.meta?.total ?? items.length; }
        else if (d && typeof d === 'object' && d.id) { items = [d]; total = 1; }
        return { items, total };
    }

    function renderPagination(containerId, infoId, total, page, onPage) {
        const info = $(infoId);
        if (info) {
            const s = total ? (page - 1) * LIMIT + 1 : 0, e = Math.min(page * LIMIT, total);
            info.textContent = total ? `${s}–${e} / ${total}` : '0';
        }
        const el = $(containerId);
        if (!el) return;
        const pages = Math.ceil(total / LIMIT) || 1;
        if (pages <= 1) { el.innerHTML = ''; return; }
        let h = '<ul>';
        h += `<li class="${page <= 1 ? 'disabled' : ''}"><a href="#" data-p="${page - 1}">‹</a></li>`;
        for (let i = Math.max(1, page - 2); i <= Math.min(pages, page + 2); i++) {
            h += i === page ? `<li class="active"><span>${i}</span></li>` : `<li><a href="#" data-p="${i}">${i}</a></li>`;
        }
        h += `<li class="${page >= pages ? 'disabled' : ''}"><a href="#" data-p="${page + 1}">›</a></li></ul>`;
        el.innerHTML = h;
        el.querySelectorAll('a[data-p]').forEach(a => a.addEventListener('click', ev => {
            ev.preventDefault(); onPage(parseInt(a.dataset.p));
        }));
    }

    function badge(status) {
        const map = {
            draft: 'secondary', published: 'success', closed: 'danger', filled: 'info', cancelled: 'warning',
            submitted: 'secondary', under_review: 'primary', shortlisted: 'info',
            interview_scheduled: 'primary', interviewed: 'info', offered: 'success',
            accepted: 'success', rejected: 'danger', withdrawn: 'warning',
            scheduled: 'primary', confirmed: 'success', completed: 'info',
            rescheduled: 'warning', no_show: 'danger', active: 'success', inactive: 'secondary'
        };
        const label = status ? status.replace(/_/g, ' ') : '';
        return `<span class="badge badge-${map[status] || 'secondary'}">${esc(label)}</span>`;
    }

    function exportCSV(items, fname, cols) {
        if (!items.length) { notify(t('table.actions.no_data', 'No data'), 'warning'); return; }
        const rows = [cols.map(c => c.label).join(','),
        ...items.map(r => cols.map(c => {
            let v = String(r[c.field] ?? '');
            if (v.includes(',') || v.includes('"')) v = `"${v.replace(/"/g, '""')}"`;
            return v;
        }).join(','))
        ].join('\n');
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(['\uFEFF' + rows], { type: 'text/csv' }));
        a.download = fname; a.click();
    }

    // ─── Translation Loader ───────────────────────────────────────────────────
    async function loadTranslations() {
        try {
            const res = await fetch(`/languages/Jobs/${encodeURIComponent(state.lang)}.json`, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('status ' + res.status);
            state.i18n = await res.json();
            applyTranslations();
        } catch (e) {
            console.warn('[Jobs] Translations failed, falling back to en:', e);
            if (state.lang !== 'en') {
                try {
                    const res = await fetch('/languages/Jobs/en.json', { credentials: 'same-origin' });
                    if (res.ok) { state.i18n = await res.json(); applyTranslations(); }
                } catch (_) { }
            }
        }
    }

    function applyTranslations() {
        const container = document.getElementById('jobsPageContainer');
        if (!container) return;
        container.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            const val = key.split('.').reduce((o, k) => (o && o[k] !== undefined ? o[k] : null), state.i18n);
            if (val !== null && typeof val === 'string') {
                if (el.tagName === 'INPUT' && el.hasAttribute('placeholder')) el.placeholder = val;
                else el.textContent = val;
            }
        });
    }

    // ─── Dropdown Loaders ─────────────────────────────────────────────────────
    async function loadDropdown(apiUrl, selIds, valueField, labelField, firstOption) {
        try {
            const r = await apiFetch(`${apiUrl}?limit=5000&tenant_id=${state.tenant}&lang=${state.lang}&format=json`);
            const { items } = normalize(r);
            selIds.forEach(id => {
                const sel = $(id); if (!sel) return;
                const saved = sel.value;
                if (firstOption !== undefined) { sel.innerHTML = ''; const o = document.createElement('option'); o.value = ''; o.textContent = firstOption; sel.appendChild(o); }
                items.forEach(item => { const o = document.createElement('option'); o.value = item[valueField] || item.id; o.textContent = item[labelField] || item.name || item.id; sel.appendChild(o); });
                if (saved) sel.value = saved;
            });
        } catch (e) { console.warn('[Jobs] Dropdown load failed:', apiUrl, e); }
    }

    async function loadAllDropdowns() {
        await Promise.allSettled([
            loadDropdown(URL.cats, ['jobCategory', 'jobsCatFilter'], 'id', 'name', '-- All --'),
            loadDropdown(URL.countries, ['jobCountryId'], 'id', 'name', '-- Country --'),
            loadDropdown(URL.currencies, ['jobSalaryCurrency'], 'code', 'code', null),
            loadDropdown(URL.langs, ['translationLanguage'], 'code', 'name', '-- Language --')
        ]);
        // Jobs for application/question dropdowns
        await loadJobsDropdown();
    }

    async function loadJobsDropdown() {
        try {
            const r = await apiFetch(`${URL.jobs}?limit=500&tenant_id=${state.tenant}&format=json`);
            const { items } = normalize(r);
            ['appsJobFilter', 'questionsJobFilter', 'questionJobId'].forEach(id => {
                const sel = $(id); if (!sel) return;
                const first = sel.options[0]?.cloneNode(true);
                sel.innerHTML = ''; if (first) sel.appendChild(first);
                items.forEach(j => { const o = document.createElement('option'); o.value = j.id; o.textContent = j.job_title; sel.appendChild(o); });
            });
            // also for interview app selector
            try {
                const ra = await apiFetch(`${URL.apps}?limit=500&tenant_id=${state.tenant}&format=json`);
                const { items: apps } = normalize(ra);
                const sel = $('interviewAppId'); if (sel) {
                    sel.innerHTML = '<option value="">-- Application --</option>';
                    apps.forEach(a => { const o = document.createElement('option'); o.value = a.id; o.textContent = `#${a.id} – ${a.full_name || a.email}`; sel.appendChild(o); });
                }
            } catch (_) { }
        } catch (e) { console.warn('[Jobs] Jobs dropdown load failed:', e); }
    }

    async function loadCities(countryId, selId = 'jobCityId') {
        const sel = $(selId); if (!sel) return;
        sel.innerHTML = '<option value="">-- City --</option>';
        if (!countryId) return;
        try {
            const r = await apiFetch(`${URL.cities}?country_id=${countryId}&limit=2000&lang=${state.lang}&format=json`);
            normalize(r).items.forEach(c => { const o = document.createElement('option'); o.value = c.id; o.textContent = c.name || c.id; sel.appendChild(o); });
        } catch (_) { }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // GENERIC TABLE MODULE
    // ═════════════════════════════════════════════════════════════════════════
    function createModule(name, apiUrl, cfg) {
        const ms = state[name];
        return {
            async load(page = 1) {
                ms.page = page; ms.loaded = true;
                const loading = $(cfg.loadingId);
                const container = $(cfg.containerId);
                const empty = $(cfg.emptyId);
                if (loading) loading.style.display = '';
                if (container) container.style.display = 'none';
                if (empty) empty.style.display = 'none';
                try {
                    const r = await apiFetch(`${apiUrl}?${buildQS(ms)}`);
                    const { items, total } = normalize(r);
                    ms.items = items; ms.total = total;
                    if (loading) loading.style.display = 'none';
                    const tbody = $(cfg.tbodyId);
                    if (!items.length) { if (empty) empty.style.display = ''; if (tbody) tbody.innerHTML = ''; return; }
                    if (container) container.style.display = '';
                    if (tbody) tbody.innerHTML = items.map(cfg.rowFn).join('');
                    renderPagination(cfg.paginationId, cfg.paginationInfoId, total, page, n => this.load(n));
                } catch (err) {
                    console.error(`[Jobs][${name}] Load error:`, err);
                    if (loading) loading.style.display = 'none';
                    notify(err.message, 'error');
                }
            },
            applyFilters() { ms.filters = cfg.getFilters ? cfg.getFilters() : {}; this.load(1); },
            resetFilters() { if (cfg.resetFilters) cfg.resetFilters(); ms.filters = {}; this.load(1); }
        };
    }

    // ═════════════════════════════════════════════════════════════════════════
    // JOBS TAB
    // ═════════════════════════════════════════════════════════════════════════
    const jobsMod = Object.assign(createModule('jobs', URL.jobs, {
        loadingId: 'jobsTableLoading', containerId: 'jobsTableContainer', emptyId: 'jobsEmptyState',
        tbodyId: 'jobsTableBody', paginationId: 'jobsPagination', paginationInfoId: 'jobsPaginationInfo',
        rowFn: j => `<tr>
            <td>${esc(j.id)}</td>
            <td><strong>${esc(j.job_title)}</strong>${+j.is_featured ? ' <span class="badge badge-warning">★</span>' : ''}${+j.is_urgent ? ' <span class="badge badge-danger">!</span>' : ''}</td>
            <td>${esc((j.job_type || '').replace(/_/g, ' '))}</td>
            <td>${esc(j.experience_level || '')}</td>
            <td>${esc(j.category_name || j.category || '')}</td>
            <td>${j.salary_min ? `${Number(j.salary_min).toLocaleString()} ${esc(j.salary_currency || '')}` : '-'}</td>
            <td>${esc(j.applications_count ?? 0)}</td>
            <td>${j.application_deadline ? esc(String(j.application_deadline).substring(0, 10)) : '-'}</td>
            <td>${badge(j.status)}</td>
            <td class="actions">
                ${state.perms.canEdit ? `<button class="btn btn-sm btn-primary" onclick="Jobs.editJob(${j.id})"><i class="fas fa-edit"></i></button>` : ''}
                ${state.perms.canDelete ? `<button class="btn btn-sm btn-danger" onclick="Jobs.delJob(${j.id})"><i class="fas fa-trash"></i></button>` : ''}
            </td></tr>`,
        getFilters: () => ({
            search: $('jobsSearch')?.value || '',
            status: $('jobsStatusFilter')?.value || '',
            job_type: $('jobsTypeFilter')?.value || '',
            experience_level: $('jobsExpFilter')?.value || '',
            category: $('jobsCatFilter')?.value || ''
        }),
        resetFilters: () => ['jobsSearch', 'jobsStatusFilter', 'jobsTypeFilter', 'jobsExpFilter', 'jobsCatFilter']
            .forEach(id => { const el = $(id); if (el) el.value = ''; })
    }), {
        showForm(job = null) {
            const fc = $('jobFormContainer'); if (!fc) return;
            fc.style.display = '';
            // reset inner tabs
            document.querySelectorAll('#jobFormTabs .ftab-btn').forEach((b, i) => b.classList.toggle('active', i === 0));
            document.querySelectorAll('.fpanel').forEach((p, i) => p.style.display = i === 0 ? '' : 'none');
            const form = $('jobForm'); if (form) form.reset();
            if (!job) { $('jobFormTitle').textContent = t('form.add_title', 'Add Job'); $('jobId').value = ''; this.renderSkills([]); this.renderTranslations([]); return; }
            $('jobFormTitle').textContent = t('form.edit_title', 'Edit Job');
            const set = (id, v) => { const el = $(id); if (el) el.value = v ?? '' };
            const chk = (id, v) => { const el = $(id); if (el) el.checked = !!+v };
            set('jobId', job.id); set('jobTitle', job.job_title); set('jobSlug', job.slug);
            set('jobType', job.job_type || 'full_time'); set('jobEmploymentType', job.employment_type || 'permanent');
            set('jobExperienceLevel', job.experience_level || 'entry'); set('jobCategory', job.category || '');
            set('jobDepartment', job.department || ''); set('jobPositions', job.positions_available || 1);
            set('jobStatus', job.status || 'draft'); set('jobStartDate', job.start_date || '');
            set('jobEntityId', job.entity_id || ''); set('jobEntityIdShow', job.entity_id || '');
            chk('jobIsFeatured', job.is_featured); chk('jobIsUrgent', job.is_urgent);
            set('jobDescription', job.description || ''); set('jobRequirements', job.requirements || '');
            set('jobResponsibilities', job.responsibilities || ''); set('jobBenefits', job.benefits || '');
            set('jobSalaryMin', job.salary_min || ''); set('jobSalaryMax', job.salary_max || '');
            set('jobSalaryCurrency', job.salary_currency || 'SAR'); set('jobSalaryPeriod', job.salary_period || 'monthly');
            chk('jobSalaryNegotiable', job.salary_negotiable);
            set('jobWorkLocation', job.work_location || ''); chk('jobIsRemote', job.is_remote);
            set('jobApplicationFormType', job.application_form_type || 'simple');
            set('jobExternalUrl', job.external_application_url || '');
            set('jobApplicationDeadline', job.application_deadline ? (String(job.application_deadline).replace(' ', 'T').slice(0, 16)) : '');
            if (job.country_id) {
                set('jobCountryId', job.country_id);
                loadCities(job.country_id).then(() => set('jobCityId', job.city_id || ''));
            }
            this.renderSkills(job.skills || []); this.renderTranslations(job.translations || []);
        },

        hideForm() { const fc = $('jobFormContainer'); if (fc) fc.style.display = 'none'; },

        async editJob(id) {
            try {
                const r = await apiFetch(`${URL.jobs}?id=${id}&format=json&tenant_id=${state.tenant}&include=skills,translations`);
                const job = Array.isArray(r.data) ? r.data[0] : (r.data || r);
                this.showForm(job);
            } catch (e) { notify(e.message, 'error'); }
        },

        async delJob(id) {
            if (!confirm(t('table.actions.confirm_delete', 'Delete?'))) return;
            try {
                await apiFetch(`${URL.jobs}/${id}`, { method: 'DELETE' });
                notify(t('messages.success.deleted', 'Deleted'), 'success');
                this.load(state.jobs.page);
            } catch (e) { notify(e.message, 'error'); }
        },

        async save(e) {
            e.preventDefault();
            const id = $('jobId').value;
            $('jobEntityId').value = $('jobEntityIdShow').value || '';
            let skills = [], trans = [];
            try { skills = JSON.parse($('jobSkillsData')?.value || '[]'); } catch (_) { }
            try { trans = JSON.parse($('jobTranslationsData')?.value || '[]'); } catch (_) { }
            const body = {
                entity_id: $('jobEntityId').value || null,
                job_title: $('jobTitle').value,
                slug: $('jobSlug').value,
                job_type: $('jobType').value,
                employment_type: $('jobEmploymentType').value,
                experience_level: $('jobExperienceLevel').value,
                category: $('jobCategory').value || null,
                department: $('jobDepartment').value || null,
                positions_available: +$('jobPositions').value || 1,
                status: $('jobStatus').value,
                start_date: $('jobStartDate').value ? $('jobStartDate').value.substring(0, 10) : null,
                is_featured: $('jobIsFeatured').checked ? 1 : 0,
                is_urgent: $('jobIsUrgent').checked ? 1 : 0,
                description: $('jobDescription').value,
                requirements: $('jobRequirements').value || null,
                responsibilities: $('jobResponsibilities').value || null,
                benefits: $('jobBenefits').value || null,
                salary_min: $('jobSalaryMin').value || null,
                salary_max: $('jobSalaryMax').value || null,
                salary_currency: $('jobSalaryCurrency').value,
                salary_period: $('jobSalaryPeriod').value,
                salary_negotiable: $('jobSalaryNegotiable').checked ? 1 : 0,
                country_id: $('jobCountryId').value || null,
                city_id: $('jobCityId').value || null,
                work_location: $('jobWorkLocation').value || null,
                is_remote: $('jobIsRemote').checked ? 1 : 0,
                application_form_type: $('jobApplicationFormType').value,
                external_application_url: $('jobExternalUrl').value || null,
                application_deadline: $('jobApplicationDeadline').value
                    ? $('jobApplicationDeadline').value.replace('T', ' ').replace(/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2})$/, '$1:00')
                    : null,
                tenant_id: state.tenant, created_by: state.userId,
                skills, translations: trans
            };
            if (id) body.id = parseInt(id);
            if (!body.job_title) { notify(t('messages.error.required_fields', 'Fill required fields'), 'error'); return; }
            const btn = $('jobSubmitBtn'); if (btn) btn.disabled = true;
            try {
                const apiUrl = id ? `${URL.jobs}/${id}` : URL.jobs;
                const r = await apiFetch(apiUrl, { method: id ? 'PUT' : 'POST', json: body });
                if (r?.success !== false) {
                    notify(id ? t('messages.success.updated', 'Updated') : t('messages.success.created', 'Created'), 'success');
                    this.hideForm(); this.load(state.jobs.page);
                } else throw new Error(r?.error || r?.message || 'Save failed');
            } catch (e) { notify(e.message, 'error'); }
            finally { if (btn) btn.disabled = false; }
        },

        // Skills manager
        renderSkills(skills) {
            const list = $('skillsList'); if (!list) return;
            list.innerHTML = '';
            skills.forEach(s => this.addSkillRow(s)); this.syncSkills();
        },
        addSkillRow(s = {}) {
            const list = $('skillsList'); if (!list) return;
            const row = document.createElement('div'); row.className = 'skill-row';
            row.innerHTML = `
                <input type="text" class="form-control sk-name" placeholder="Skill name" value="${esc(s.skill_name || '')}">
                <select class="form-control sk-level">
                    ${['basic', 'intermediate', 'advanced', 'expert'].map(l => `<option value="${l}"${l === (s.proficiency_level || 'intermediate') ? ' selected' : ''}>${l}</option>`).join('')}
                </select>
                <label class="checkbox-label"><input type="checkbox" class="sk-req"${+s.is_required ? ' checked' : ''}> Req</label>
                <button type="button" class="btn btn-sm btn-danger sk-del"><i class="fas fa-times"></i></button>`;
            row.querySelector('.sk-del').onclick = () => { row.remove(); this.syncSkills(); };
            row.querySelectorAll('.sk-name,.sk-level,.sk-req').forEach(el => el.addEventListener('change', () => this.syncSkills()));
            list.appendChild(row);
        },
        syncSkills() {
            const rows = document.querySelectorAll('#skillsList .skill-row');
            const data = Array.from(rows).map(r => ({
                skill_name: r.querySelector('.sk-name').value.trim(),
                proficiency_level: r.querySelector('.sk-level').value,
                is_required: r.querySelector('.sk-req').checked ? 1 : 0
            })).filter(s => s.skill_name);
            const el = $('jobSkillsData'); if (el) el.value = JSON.stringify(data);
        },

        // Translations manager
        renderTranslations(trans) {
            const list = $('translationsList'); if (!list) return;
            list.innerHTML = '';
            trans.forEach(tr => this.addTranslationPanel(tr)); this.syncTranslations();
        },
        addTranslationPanel(tr = {}) {
            const list = $('translationsList');
            const lang = tr.language_code || $('translationLanguage')?.value;
            if (!lang || !list) return;
            if (list.querySelector(`[data-lang="${lang}"]`)) { notify('Language already added', 'warning'); return; }
            const panel = document.createElement('div'); panel.className = 'translation-panel'; panel.dataset.lang = lang;
            panel.innerHTML = `<div class="translation-header"><strong>${lang.toUpperCase()}</strong>
                <button type="button" class="btn btn-sm btn-danger tr-del"><i class="fas fa-times"></i></button></div>
                <div class="form-group"><label>Title</label><input type="text" class="form-control tr-title" value="${esc(tr.job_title || '')}"></div>
                <div class="form-group"><label>Description</label><textarea class="form-control tr-desc" rows="3">${esc(tr.description || '')}</textarea></div>
                <div class="form-group"><label>Requirements</label><textarea class="form-control tr-req" rows="2">${esc(tr.requirements || '')}</textarea></div>
                <div class="form-group"><label>Responsibilities</label><textarea class="form-control tr-resp" rows="2">${esc(tr.responsibilities || '')}</textarea></div>
                <div class="form-group"><label>Benefits</label><textarea class="form-control tr-ben" rows="2">${esc(tr.benefits || '')}</textarea></div>`;
            panel.querySelector('.tr-del').onclick = () => { panel.remove(); this.syncTranslations(); };
            panel.querySelectorAll('input,textarea').forEach(el => el.addEventListener('input', () => this.syncTranslations()));
            list.appendChild(panel); this.syncTranslations();
        },
        syncTranslations() {
            const panels = document.querySelectorAll('#translationsList .translation-panel');
            const data = Array.from(panels).map(p => ({
                language_code: p.dataset.lang,
                job_title: p.querySelector('.tr-title').value,
                description: p.querySelector('.tr-desc').value,
                requirements: p.querySelector('.tr-req').value,
                responsibilities: p.querySelector('.tr-resp').value,
                benefits: p.querySelector('.tr-ben').value
            }));
            const el = $('jobTranslationsData'); if (el) el.value = JSON.stringify(data);
        },

        bindEvents() {
            $('jobsAddBtn')?.addEventListener('click', () => this.showForm());
            $('jobCloseForm')?.addEventListener('click', () => this.hideForm());
            $('jobCancelBtn')?.addEventListener('click', () => this.hideForm());
            $('jobForm')?.addEventListener('submit', e => this.save(e));
            $('jobsApplyFilter')?.addEventListener('click', () => this.applyFilters());
            $('jobsResetFilter')?.addEventListener('click', () => this.resetFilters());
            $('jobsExportBtn')?.addEventListener('click', () => exportCSV(state.jobs.items, 'jobs.csv', [
                { field: 'id', label: 'ID' }, { field: 'job_title', label: 'Title' },
                { field: 'job_type', label: 'Type' }, { field: 'experience_level', label: 'Exp' },
                { field: 'status', label: 'Status' }, { field: 'applications_count', label: 'Apps' },
                { field: 'salary_min', label: 'Sal Min' }, { field: 'salary_currency', label: 'Cur' }
            ]));
            $('jobCountryId')?.addEventListener('change', e => loadCities(e.target.value));
            $('jobApplicationFormType')?.addEventListener('change', e => {
                const g = $('externalUrlGroup'); if (g) g.style.display = e.target.value === 'external' ? '' : 'none';
            });
            $('addSkillRow')?.addEventListener('click', () => this.addSkillRow());
            $('addTranslationBtn')?.addEventListener('click', () => this.addTranslationPanel());
            // form inner tabs
            document.querySelectorAll('#jobFormTabs .ftab-btn').forEach(btn => btn.addEventListener('click', () => {
                document.querySelectorAll('#jobFormTabs .ftab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                document.querySelectorAll('.fpanel').forEach(p => p.style.display = 'none');
                const panel = document.getElementById('ftab-' + btn.dataset.ftab);
                if (panel) panel.style.display = '';
            }));
        }
    });

    // ═════════════════════════════════════════════════════════════════════════
    // APPLICATIONS TAB
    // ═════════════════════════════════════════════════════════════════════════
    const appsMod = Object.assign(createModule('apps', URL.apps, {
        loadingId: 'appsTableLoading', containerId: 'appsTableContainer', emptyId: 'appsEmptyState',
        tbodyId: 'appsTableBody', paginationId: 'appsPagination', paginationInfoId: 'appsPaginationInfo',
        rowFn: a => `<tr>
            <td>${esc(a.id)}</td>
            <td>${esc(a.job_title || a.job_id || '')}</td>
            <td><strong>${esc(a.full_name || '')}</strong></td>
            <td>${esc(a.email || '')}</td>
            <td>${esc(a.phone || '')}</td>
            <td>${a.years_of_experience != null ? esc(a.years_of_experience) + ' yrs' : '-'}</td>
            <td>${a.expected_salary ? Number(a.expected_salary).toLocaleString() : '-'}</td>
            <td>${badge(a.status)}</td>
            <td>${a.rating ? '★'.repeat(Math.min(5, +a.rating)) : '-'}</td>
            <td>${esc((a.created_at || '').substring(0, 10))}</td>
            <td class="actions">
                <button class="btn btn-sm btn-primary" onclick="Jobs.editApp(${a.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-danger"  onclick="Jobs.delApp(${a.id})"><i class="fas fa-trash"></i></button>
            </td></tr>`,
        getFilters: () => ({ search: $('appsSearch')?.value || '', status: $('appsStatusFilter')?.value || '', job_id: $('appsJobFilter')?.value || '' }),
        resetFilters: () => ['appsSearch', 'appsStatusFilter', 'appsJobFilter'].forEach(id => { const el = $(id); if (el) el.value = ''; })
    }), {
        showForm(a = {}) {
            const fc = $('appFormContainer'); if (!fc) return; fc.style.display = '';
            const set = (id, v) => { const el = $(id); if (el) el.value = v ?? '' };
            set('appId', a.id || ''); set('appFullName', a.full_name || ''); set('appEmail', a.email || '');
            set('appPhone', a.phone || ''); set('appCurrentPosition', a.current_position || '');
            set('appYearsExp', a.years_of_experience ?? ''); set('appExpectedSalary', a.expected_salary || '');
            set('appNoticePeriod', a.notice_period || ''); set('appLinkedin', a.linkedin_url || '');
            set('appPortfolio', a.portfolio_url || ''); set('appStatus', a.status || 'submitted');
            set('appRating', a.rating || ''); set('appCvUrl', a.cv_file_url || '');
            set('appCoverLetter', a.cover_letter || ''); set('appNotes', a.notes || '');
        },
        async editApp(id) {
            try { const r = await apiFetch(`${URL.apps}?id=${id}&format=json`); this.showForm(Array.isArray(r.data) ? r.data[0] : (r.data || r)); }
            catch (e) { notify(e.message, 'error'); }
        },
        async delApp(id) {
            if (!confirm(t('table.actions.confirm_delete', 'Delete?'))) return;
            try { await apiFetch(`${URL.apps}/${id}`, { method: 'DELETE' }); notify(t('messages.success.deleted', 'Deleted'), 'success'); this.load(state.apps.page); }
            catch (e) { notify(e.message, 'error'); }
        },
        async save(e) {
            e.preventDefault();
            const id = $('appId').value;
            const body = {
                full_name: $('appFullName').value, email: $('appEmail').value,
                phone: $('appPhone').value, current_position: $('appCurrentPosition').value,
                years_of_experience: +$('appYearsExp').value || null,
                expected_salary: $('appExpectedSalary').value || null,
                notice_period: +$('appNoticePeriod').value || null,
                linkedin_url: $('appLinkedin').value || null, portfolio_url: $('appPortfolio').value || null,
                status: $('appStatus').value, rating: $('appRating').value || null,
                cv_file_url: $('appCvUrl').value || null,
                cover_letter: $('appCoverLetter').value, notes: $('appNotes').value,
                tenant_id: state.tenant
            };
            if (id) body.id = parseInt(id);
            try {
                const r = await apiFetch(id ? `${URL.apps}/${id}` : URL.apps, { method: id ? 'PUT' : 'POST', json: body });
                if (r?.success !== false) { notify(t('messages.success.updated', 'Saved'), 'success'); $('appFormContainer').style.display = 'none'; this.load(state.apps.page); }
                else throw new Error(r?.error || 'Save failed');
            } catch (e) { notify(e.message, 'error'); }
        },
        bindEvents() {
            $('appCloseForm')?.addEventListener('click', () => { $('appFormContainer').style.display = 'none'; });
            $('appCancelForm')?.addEventListener('click', () => { $('appFormContainer').style.display = 'none'; });
            $('appForm')?.addEventListener('submit', e => this.save(e));
            $('appsApplyFilter')?.addEventListener('click', () => this.applyFilters());
            $('appsResetFilter')?.addEventListener('click', () => this.resetFilters());
            $('appsExportBtn')?.addEventListener('click', () => exportCSV(state.apps.items, 'applications.csv', [
                { field: 'id', label: 'ID' }, { field: 'job_title', label: 'Job' }, { field: 'full_name', label: 'Name' },
                { field: 'email', label: 'Email' }, { field: 'phone', label: 'Phone' }, { field: 'status', label: 'Status' },
                { field: 'rating', label: 'Rating' }, { field: 'created_at', label: 'Date' }
            ]));
        }
    });

    // ═════════════════════════════════════════════════════════════════════════
    // INTERVIEWS TAB
    // ═════════════════════════════════════════════════════════════════════════
    const interviewsMod = Object.assign(createModule('interviews', URL.interviews, {
        loadingId: 'interviewsTableLoading', containerId: 'interviewsTableContainer', emptyId: 'interviewsEmptyState',
        tbodyId: 'interviewsTableBody', paginationId: 'interviewsPagination', paginationInfoId: 'interviewsPaginationInfo',
        rowFn: iv => `<tr>
            <td>${esc(iv.id)}</td>
            <td>${esc(iv.applicant_name || '#' + iv.application_id)}</td>
            <td>${esc((iv.interview_type || '').replace(/_/g, ' '))}</td>
            <td>${esc((iv.interview_date || '').substring(0, 16))}</td>
            <td>${esc(iv.interview_duration || 60)} min</td>
            <td>${esc(iv.interviewer_name || '-')}</td>
            <td>${badge(iv.status)}</td>
            <td>${iv.rating ? '★'.repeat(Math.min(5, +iv.rating)) : '-'}</td>
            <td class="actions">
                <button class="btn btn-sm btn-primary" onclick="Jobs.editInterview(${iv.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-danger"  onclick="Jobs.delInterview(${iv.id})"><i class="fas fa-trash"></i></button>
            </td></tr>`,
        getFilters: () => ({ search: $('interviewsSearch')?.value || '', status: $('interviewsStatusFilter')?.value || '', interview_type: $('interviewsTypeFilter')?.value || '' }),
        resetFilters: () => ['interviewsSearch', 'interviewsStatusFilter', 'interviewsTypeFilter'].forEach(id => { const el = $(id); if (el) el.value = ''; })
    }), {
        showForm(iv = {}) {
            const fc = $('interviewFormContainer'); if (!fc) return; fc.style.display = '';
            const set = (id, v) => { const el = $(id); if (el) el.value = v ?? '' };
            set('interviewId', iv.id || ''); set('interviewAppId', iv.application_id || '');
            set('interviewType', iv.interview_type || 'phone');
            set('interviewDate', iv.interview_date ? (String(iv.interview_date).replace(' ', 'T').slice(0, 16)) : '');
            set('interviewDuration', iv.interview_duration || 60);
            set('interviewLocation', iv.location || ''); set('interviewMeetingLink', iv.meeting_link || '');
            set('interviewerName', iv.interviewer_name || ''); set('interviewerEmail', iv.interviewer_email || '');
            set('interviewStatus', iv.status || 'scheduled'); set('interviewRating', iv.rating || '');
            set('interviewFeedback', iv.feedback || ''); set('interviewNotes', iv.notes || '');
        },
        async editInterview(id) {
            try { const r = await apiFetch(`${URL.interviews}?id=${id}&format=json`); this.showForm(Array.isArray(r.data) ? r.data[0] : (r.data || r)); }
            catch (e) { notify(e.message, 'error'); }
        },
        async delInterview(id) {
            if (!confirm(t('table.actions.confirm_delete', 'Delete?'))) return;
            try { await apiFetch(`${URL.interviews}/${id}`, { method: 'DELETE' }); notify(t('messages.success.deleted', 'Deleted'), 'success'); this.load(state.interviews.page); }
            catch (e) { notify(e.message, 'error'); }
        },
        async save(e) {
            e.preventDefault();
            const id = $('interviewId').value;
            const body = {
                application_id: $('interviewAppId').value,
                interview_type: $('interviewType').value,
                interview_date: $('interviewDate').value,
                interview_duration: +$('interviewDuration').value || 60,
                location: $('interviewLocation').value || null,
                meeting_link: $('interviewMeetingLink').value || null,
                interviewer_name: $('interviewerName').value || null,
                interviewer_email: $('interviewerEmail').value || null,
                status: $('interviewStatus').value,
                rating: $('interviewRating').value || null,
                feedback: $('interviewFeedback').value || null,
                notes: $('interviewNotes').value || null,
                created_by: state.userId, tenant_id: state.tenant
            };
            if (id) body.id = parseInt(id);
            if (!body.interview_date) { notify('Date required', 'error'); return; }
            try {
                const r = await apiFetch(id ? `${URL.interviews}/${id}` : URL.interviews, { method: id ? 'PUT' : 'POST', json: body });
                if (r?.success !== false) { notify(t('messages.success.updated', 'Saved'), 'success'); $('interviewFormContainer').style.display = 'none'; this.load(state.interviews.page); }
                else throw new Error(r?.error || 'Save failed');
            } catch (e) { notify(e.message, 'error'); }
        },
        bindEvents() {
            $('interviewsAddBtn')?.addEventListener('click', () => this.showForm());
            $('interviewCloseForm')?.addEventListener('click', () => { $('interviewFormContainer').style.display = 'none'; });
            $('interviewCancelForm')?.addEventListener('click', () => { $('interviewFormContainer').style.display = 'none'; });
            $('interviewForm')?.addEventListener('submit', e => this.save(e));
            $('interviewsApplyFilter')?.addEventListener('click', () => this.applyFilters());
            $('interviewsResetFilter')?.addEventListener('click', () => this.resetFilters());
            $('interviewsExportBtn')?.addEventListener('click', () => exportCSV(state.interviews.items, 'interviews.csv', [
                { field: 'id', label: 'ID' }, { field: 'application_id', label: 'App' },
                { field: 'interview_type', label: 'Type' }, { field: 'interview_date', label: 'Date' },
                { field: 'interviewer_name', label: 'Interviewer' }, { field: 'status', label: 'Status' }, { field: 'rating', label: 'Rating' }
            ]));
        }
    });

    // ═════════════════════════════════════════════════════════════════════════
    // ALERTS TAB
    // ═════════════════════════════════════════════════════════════════════════
    const alertsMod = Object.assign(createModule('alerts', URL.alerts, {
        loadingId: 'alertsTableLoading', containerId: 'alertsTableContainer', emptyId: 'alertsEmptyState',
        tbodyId: 'alertsTableBody', paginationId: 'alertsPagination', paginationInfoId: 'alertsPaginationInfo',
        rowFn: al => `<tr>
            <td>${esc(al.id)}</td>
            <td>${esc(al.alert_name)}</td>
            <td>${esc(al.keywords || '-')}</td>
            <td>${esc(al.job_type || '-')}</td>
            <td>${esc(al.frequency)}</td>
            <td>${+al.is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Off</span>'}</td>
            <td>${esc((al.last_sent_at || '').substring(0, 10) || '-')}</td>
            <td class="actions">
                <button class="btn btn-sm btn-primary" onclick="Jobs.editAlert(${al.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-danger"  onclick="Jobs.delAlert(${al.id})"><i class="fas fa-trash"></i></button>
            </td></tr>`,
        getFilters: () => ({ search: $('alertsSearch')?.value || '', is_active: $('alertsActiveFilter')?.value ?? '', frequency: $('alertsFreqFilter')?.value || '' }),
        resetFilters: () => ['alertsSearch', 'alertsActiveFilter', 'alertsFreqFilter'].forEach(id => { const el = $(id); if (el) el.value = ''; })
    }), {
        showForm(al = {}) {
            const fc = $('alertFormContainer'); if (!fc) return; fc.style.display = '';
            const set = (id, v) => { const el = $(id); if (el) el.value = v ?? '' };
            set('alertId', al.id || ''); set('alertName', al.alert_name || '');
            set('alertKeywords', al.keywords || ''); set('alertJobType', al.job_type || '');
            set('alertExpLevel', al.experience_level || ''); set('alertSalaryMin', al.salary_min || '');
            set('alertCountryId', al.country_id || ''); set('alertCityId', al.city_id || '');
            set('alertFrequency', al.frequency || 'daily');
            const chk = $('alertIsActive'); if (chk) chk.checked = al.is_active === undefined ? true : !!+al.is_active;
        },
        async editAlert(id) {
            try { const r = await apiFetch(`${URL.alerts}?id=${id}&format=json`); this.showForm(Array.isArray(r.data) ? r.data[0] : (r.data || r)); }
            catch (e) { notify(e.message, 'error'); }
        },
        async delAlert(id) {
            if (!confirm(t('table.actions.confirm_delete', 'Delete?'))) return;
            try { await apiFetch(`${URL.alerts}/${id}`, { method: 'DELETE' }); notify(t('messages.success.deleted', 'Deleted'), 'success'); this.load(state.alerts.page); }
            catch (e) { notify(e.message, 'error'); }
        },
        async save(e) {
            e.preventDefault();
            const id = $('alertId').value;
            const body = {
                alert_name: $('alertName').value, keywords: $('alertKeywords').value || null,
                job_type: $('alertJobType').value || null, experience_level: $('alertExpLevel').value || null,
                salary_min: $('alertSalaryMin').value || null, country_id: $('alertCountryId').value || null,
                city_id: $('alertCityId').value || null, frequency: $('alertFrequency').value,
                is_active: $('alertIsActive').checked ? 1 : 0, user_id: state.userId, tenant_id: state.tenant
            };
            if (id) body.id = parseInt(id);
            if (!body.alert_name) { notify('Name required', 'error'); return; }
            try {
                const r = await apiFetch(id ? `${URL.alerts}/${id}` : URL.alerts, { method: id ? 'PUT' : 'POST', json: body });
                if (r?.success !== false) { notify(t('messages.success.updated', 'Saved'), 'success'); $('alertFormContainer').style.display = 'none'; this.load(state.alerts.page); }
                else throw new Error(r?.error || 'Save failed');
            } catch (e) { notify(e.message, 'error'); }
        },
        bindEvents() {
            $('alertsAddBtn')?.addEventListener('click', () => this.showForm());
            $('alertCloseForm')?.addEventListener('click', () => { $('alertFormContainer').style.display = 'none'; });
            $('alertCancelForm')?.addEventListener('click', () => { $('alertFormContainer').style.display = 'none'; });
            $('alertForm')?.addEventListener('submit', e => this.save(e));
            $('alertsApplyFilter')?.addEventListener('click', () => this.applyFilters());
            $('alertsResetFilter')?.addEventListener('click', () => this.resetFilters());
            $('alertsExportBtn')?.addEventListener('click', () => exportCSV(state.alerts.items, 'alerts.csv', [
                { field: 'id', label: 'ID' }, { field: 'alert_name', label: 'Name' },
                { field: 'keywords', label: 'Keywords' }, { field: 'frequency', label: 'Freq' }, { field: 'is_active', label: 'Active' }
            ]));
        }
    });

    // ═════════════════════════════════════════════════════════════════════════
    // QUESTIONS TAB
    // ═════════════════════════════════════════════════════════════════════════
    const questionsMod = Object.assign(createModule('questions', URL.questions, {
        loadingId: 'questionsTableLoading', containerId: 'questionsTableContainer', emptyId: 'questionsEmptyState',
        tbodyId: 'questionsTableBody', paginationId: 'questionsPagination', paginationInfoId: 'questionsPaginationInfo',
        rowFn: q => `<tr>
            <td>${esc(q.id)}</td>
            <td>${esc(q.job_title || q.job_id)}</td>
            <td class="text-truncate" style="max-width:200px">${esc(q.question_text)}</td>
            <td>${esc(q.question_type)}</td>
            <td>${+q.is_required ? '<span class="badge badge-danger">Yes</span>' : 'No'}</td>
            <td>${esc(q.sort_order || 0)}</td>
            <td class="actions">
                <button class="btn btn-sm btn-primary" onclick="Jobs.editQuestion(${q.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-danger"  onclick="Jobs.delQuestion(${q.id})"><i class="fas fa-trash"></i></button>
            </td></tr>`,
        getFilters: () => ({ search: $('questionsSearch')?.value || '', job_id: $('questionsJobFilter')?.value || '', question_type: $('questionsTypeFilter')?.value || '' }),
        resetFilters: () => ['questionsSearch', 'questionsJobFilter', 'questionsTypeFilter'].forEach(id => { const el = $(id); if (el) el.value = ''; })
    }), {
        showForm(q = {}) {
            const fc = $('questionFormContainer'); if (!fc) return; fc.style.display = '';
            const set = (id, v) => { const el = $(id); if (el) el.value = v ?? '' };
            set('questionId', q.id || ''); set('questionJobId', q.job_id || '');
            set('questionType', q.question_type || 'text'); set('questionText', q.question_text || '');
            set('questionOptions', q.options || ''); set('questionSortOrder', q.sort_order || 0);
            const chk = $('questionIsRequired'); if (chk) chk.checked = !!+q.is_required;
            this.toggleOptions(q.question_type || 'text');
        },
        toggleOptions(type) { const g = $('questionOptionsGroup'); if (g) g.style.display = ['select', 'multiselect', 'radio', 'checkbox'].includes(type) ? '' : 'none'; },
        async editQuestion(id) {
            try { const r = await apiFetch(`${URL.questions}?id=${id}&format=json`); this.showForm(Array.isArray(r.data) ? r.data[0] : (r.data || r)); }
            catch (e) { notify(e.message, 'error'); }
        },
        async delQuestion(id) {
            if (!confirm(t('table.actions.confirm_delete', 'Delete?'))) return;
            try { await apiFetch(`${URL.questions}/${id}`, { method: 'DELETE' }); notify(t('messages.success.deleted', 'Deleted'), 'success'); this.load(state.questions.page); }
            catch (e) { notify(e.message, 'error'); }
        },
        async save(e) {
            e.preventDefault();
            const id = $('questionId').value;
            let opts = $('questionOptions').value.trim();
            if (opts && !opts.startsWith('[')) opts = JSON.stringify(opts.split(',').map(s => s.trim()));
            const body = {
                job_id: $('questionJobId').value,
                question_type: $('questionType').value,
                question_text: $('questionText').value,
                options: opts || null,
                sort_order: +$('questionSortOrder').value || 0,
                is_required: $('questionIsRequired').checked ? 1 : 0
            };
            if (id) body.id = parseInt(id);
            if (!body.job_id || !body.question_text) { notify('Job and question text required', 'error'); return; }
            try {
                const r = await apiFetch(id ? `${URL.questions}/${id}` : URL.questions, { method: id ? 'PUT' : 'POST', json: body });
                if (r?.success !== false) { notify(t('messages.success.updated', 'Saved'), 'success'); $('questionFormContainer').style.display = 'none'; this.load(state.questions.page); }
                else throw new Error(r?.error || 'Save failed');
            } catch (e) { notify(e.message, 'error'); }
        },
        bindEvents() {
            $('questionsAddBtn')?.addEventListener('click', () => this.showForm());
            $('questionCloseForm')?.addEventListener('click', () => { $('questionFormContainer').style.display = 'none'; });
            $('questionCancelForm')?.addEventListener('click', () => { $('questionFormContainer').style.display = 'none'; });
            $('questionForm')?.addEventListener('submit', e => this.save(e));
            $('questionsApplyFilter')?.addEventListener('click', () => this.applyFilters());
            $('questionsResetFilter')?.addEventListener('click', () => this.resetFilters());
            $('questionType')?.addEventListener('change', e => this.toggleOptions(e.target.value));
        }
    });

    // ─── WORKSPACE TAB ROUTER ─────────────────────────────────────────────────
    function initTabs() {
        document.querySelectorAll('#workspaceTabs .tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#workspaceTabs .tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const tab = btn.dataset.tab;
                document.querySelectorAll('.ws-panel').forEach(p => p.style.display = 'none');
                const panel = document.getElementById(tab + 'Tab'); if (panel) panel.style.display = '';
                if (tab === 'jobs' && !state.jobs.loaded) jobsMod.load(1);
                if (tab === 'applications' && !state.apps.loaded) appsMod.load(1);
                if (tab === 'interviews' && !state.interviews.loaded) interviewsMod.load(1);
                if (tab === 'alerts' && !state.alerts.loaded) alertsMod.load(1);
                if (tab === 'questions' && !state.questions.loaded) questionsMod.load(1);
            });
        });
    }

    // ─── MAIN INIT ────────────────────────────────────────────────────────────
    async function init() {
        console.log('[Jobs] Initializing...');

        // Permissions
        try {
            const ps = document.getElementById('pagePermissions');
            if (ps) state.perms = JSON.parse(ps.textContent);
            else state.perms = window.PAGE_PERMISSIONS || { canCreate: true, canEdit: true, canDelete: true };
        } catch (_) { state.perms = window.PAGE_PERMISSIONS || { canCreate: true, canEdit: true, canDelete: true }; }

        await loadTranslations();

        initTabs();
        jobsMod.bindEvents();
        appsMod.bindEvents();
        interviewsMod.bindEvents();
        alertsMod.bindEvents();
        questionsMod.bindEvents();

        await loadAllDropdowns();
        await jobsMod.load(1);   // load first tab immediately

        console.log('[Jobs] Ready');
    }

    // ─── PUBLIC API ───────────────────────────────────────────────────────────
    window.Jobs = {
        init,
        // exposed so inline onclick= can reach them
        editJob: id => jobsMod.editJob(id),
        delJob: id => jobsMod.delJob(id),
        editApp: id => appsMod.editApp(id),
        delApp: id => appsMod.delApp(id),
        editInterview: id => interviewsMod.editInterview(id),
        delInterview: id => interviewsMod.delInterview(id),
        editAlert: id => alertsMod.editAlert(id),
        delAlert: id => alertsMod.delAlert(id),
        editQuestion: id => questionsMod.editQuestion(id),
        delQuestion: id => questionsMod.delQuestion(id)
    };

    // Standalone (non-embedded) auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            if (window.AdminFramework && !window.Jobs._initByEmbedded) init();
        });
    } else {
        if (window.AdminFramework && !window.Jobs._initByEmbedded) init();
    }
    window.Jobs._initByEmbedded = false;

})();