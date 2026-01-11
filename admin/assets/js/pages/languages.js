/*!
 * admin/assets/js/pages/languages.js
 * Full CRUD UI for languages list
 */
(function () {
  'use strict';

  /* ================= Globals ================= */
  var ADMIN_UI = window.ADMIN_UI || {};
  var I18N = window.I18N_FLAT || {};
  var USER = window.USER_INFO || {};
  var THEME = window.THEME || {};
  var LANG = window.LANG || 'en';
  var DIRECTION = window.DIRECTION || 'ltr';
  var CSRF = window.CSRF_TOKEN || '';
  var API = window.API_LANGUAGES || '/api/routes/languages.php';

  /* ================= Permissions ================= */
  var CAN_MANAGE = !!(
    (USER && Number(USER.role_id) === 1) ||
    (USER && Array.isArray(USER.roles) && USER.roles.indexOf('super_admin') !== -1) ||
    (USER && Array.isArray(USER.permissions) && USER.permissions.indexOf('manage_settings') !== -1)
  );

  /* ================= DOM ================= */
  var root = document.getElementById('adminLanguages');
  if (!root) return;

  var refreshBtn = document.getElementById('langRefresh');
  var newBtn = document.getElementById('langNew');
  var statusEl = document.getElementById('langStatus');
  var tableBody = root.querySelector('#languagesTable tbody');

  var formWrap = document.getElementById('languageFormWrap');
  var form = document.getElementById('languageForm');
  var codeOldEl = document.getElementById('lang_code_old');
  var codeEl = document.getElementById('lang_code');
  var nameEl = document.getElementById('lang_name');
  var dirEl = document.getElementById('lang_direction');
  var cancelBtn = document.getElementById('langCancel');
  var titleEl = document.getElementById('languageFormTitle');

  /* ================= State ================= */
  var cache = [];
  var currentPage = 1;
  var perPage = 10;

  /* ================= Helpers ================= */
  function t(key, fallback) {
    if (I18N && I18N[key]) return I18N[key];
    return fallback || key.split('.').pop();
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;');
  }

  function setStatus(msg, isError) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.style.color = isError ? '#b91c1c' : '#065f46';
  }

  function fetchText(url, opts) {
    opts = opts || {};
    opts.credentials = 'same-origin';
    return fetch(url, opts).then(function (r) {
      return r.text().then(function (t) {
        return { ok: r.ok, status: r.status, text: t };
      });
    });
  }

  function fetchJson(url, opts) {
    return fetchText(url, opts).then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      try { return JSON.parse(res.text || 'null'); }
      catch (e) { throw new Error('Invalid JSON'); }
    });
  }

  /* ================= Load ================= */
  function loadLanguages() {
    setStatus(t('loading','Loading...'));
    fetchJson(API + '?format=json')
      .then(function (json) {
        if (Array.isArray(json.data)) cache = json.data;
        else if (Array.isArray(json)) cache = json;
        else cache = [];
        render();
        setStatus('');
      })
      .catch(function (err) {
        console.error(err);
        cache = [];
        render();
        setStatus(t('error_loading','Error loading languages'), true);
      });
  }

  /* ================= Render ================= */
  function render() {
    if (!tableBody) return;
    tableBody.innerHTML = '';

    if (!cache.length) {
      tableBody.innerHTML =
        '<tr><td colspan="4" style="text-align:center;color:#666;">' +
        esc(t('no_data','No languages found')) +
        '</td></tr>';
      return;
    }

    var start = (currentPage - 1) * perPage;
    var pageItems = cache.slice(start, start + perPage);

    pageItems.forEach(function (l) {
      var actions = '';
      if (CAN_MANAGE) {
        actions =
          '<button class="btn edit" data-code="' + esc(l.code) + '">' + esc(t('edit','Edit')) + '</button> ' +
          '<button class="btn danger del" data-code="' + esc(l.code) + '">' + esc(t('delete','Delete')) + '</button>';
      }

      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + esc(l.code) + '</td>' +
        '<td>' + esc(l.name) + '</td>' +
        '<td>' + esc((l.direction || '').toUpperCase()) + '</td>' +
        '<td style="text-align:right">' + actions + '</td>';

      tableBody.appendChild(tr);
    });

    bindRowActions();
  }

  function bindRowActions() {
    tableBody.querySelectorAll('.edit').forEach(function (b) {
      b.addEventListener('click', function () {
        openEdit(this.getAttribute('data-code'));
      });
    });

    tableBody.querySelectorAll('.del').forEach(function (b) {
      b.addEventListener('click', function () {
        var code = this.getAttribute('data-code');
        if (!confirm(t('confirm_delete','Are you sure?'))) return;
        deleteLanguage(code);
      });
    });
  }

  /* ================= Form ================= */
  function openEdit(code) {
    var lang = cache.find(function (x) { return x.code === code; });
    if (!lang) return;

    formWrap.style.display = 'block';
    titleEl.textContent = t('edit_language','Edit Language');

    codeOldEl.value = lang.code;
    codeEl.value = lang.code;
    nameEl.value = lang.name;
    dirEl.value = lang.direction || 'ltr';
  }

  function openNew() {
    form.reset();
    codeOldEl.value = '';
    titleEl.textContent = t('add_language','Add Language');
    formWrap.style.display = 'block';
    codeEl.focus();
  }

  function saveLanguage(e) {
    e.preventDefault();
    if (!CAN_MANAGE) return;

    var fd = new FormData(form);
    fd.append('action', 'save');
    if (CSRF) fd.append('csrf_token', CSRF);

    setStatus(t('processing','Processing...'));
    fetchText(API, { method: 'POST', body: fd })
      .then(function (res) {
        var j = JSON.parse(res.text || '{}');
        if (j.success) {
          setStatus(j.message || t('saved','Saved'));
          formWrap.style.display = 'none';
          loadLanguages();
        } else {
          setStatus(j.message || t('error_save','Save failed'), true);
        }
      })
      .catch(function (err) {
        console.error(err);
        setStatus(t('error_save','Save failed'), true);
      });
  }

  function deleteLanguage(code) {
    if (!CAN_MANAGE) return;

    var fd = new FormData();
    fd.append('action', 'delete');
    fd.append('code', code);
    if (CSRF) fd.append('csrf_token', CSRF);

    setStatus(t('processing','Processing...'));
    fetchText(API, { method: 'POST', body: fd })
      .then(function (res) {
        var j = JSON.parse(res.text || '{}');
        if (j.success) {
          setStatus(j.message || t('deleted','Deleted'));
          loadLanguages();
        } else {
          setStatus(j.message || t('error_delete','Delete failed'), true);
        }
      })
      .catch(function (err) {
        console.error(err);
        setStatus(t('error_delete','Delete failed'), true);
      });
  }

  /* ================= Events ================= */
  if (refreshBtn) refreshBtn.addEventListener('click', loadLanguages);
  if (newBtn) newBtn.addEventListener('click', openNew);
  if (cancelBtn) cancelBtn.addEventListener('click', function () {
    formWrap.style.display = 'none';
  });
  if (form) form.addEventListener('submit', saveLanguage);

  document.addEventListener('DOMContentLoaded', function () {
    loadLanguages();
  });

  /* ================= Debug ================= */
  window._languagesAdmin = {
    reload: loadLanguages,
    cache: function () { return cache; }
  };

})();
