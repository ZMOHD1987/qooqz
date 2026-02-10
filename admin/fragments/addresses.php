<?php
declare(strict_types=1);

/**
 * admin/fragments/addresses.php
 * Embedded addresses management fragment for the admin panel.
 * Loaded as iframe from entities.js with URL like:
 *   /admin/fragments/addresses.php?embedded=1&tenant_id=1&lang=ar&owner_type=entity&owner_id=1
 */

// Bootstrap admin UI
$bootstrap = __DIR__ . '/../../api/bootstrap_admin_ui.php';
if (is_readable($bootstrap)) {
    try { require_once $bootstrap; } catch (Throwable $e) {}
}

$ADMIN_UI_PAYLOAD = $ADMIN_UI_PAYLOAD ?? ($GLOBALS['ADMIN_UI'] ?? []);
$lang      = $_GET['lang'] ?? $ADMIN_UI_PAYLOAD['lang'] ?? 'ar';
$rtlLangs  = ['ar', 'fa', 'he', 'ur', 'ps', 'sd', 'ku'];
$direction = in_array(substr($lang, 0, 2), $rtlLangs, true) ? 'rtl' : 'ltr';
$strings   = $ADMIN_UI_PAYLOAD['strings'] ?? [];
$theme     = $ADMIN_UI_PAYLOAD['theme'] ?? [];

$csrf      = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES);
$tenantId  = (int)($_GET['tenant_id'] ?? $_SESSION['tenant_id'] ?? 1);
$ownerType = $_GET['owner_type'] ?? 'entity';
$ownerId   = (int)($_GET['owner_id'] ?? 0);
$embedded  = !empty($_GET['embedded']);

// Theme colors
$primaryColor  = $theme['colors_map']['primary'] ?? '#2563eb';
$background    = $theme['colors_map']['background'] ?? '#0f0f0f';
$cardBg        = $theme['colors_map']['card-background'] ?? $theme['colors_map']['background-card'] ?? '#1a1a1a';
$textPrimary   = $theme['colors_map']['text-primary'] ?? '#ffffff';
$textSecondary = $theme['colors_map']['text-secondary'] ?? '#cccccc';
$borderColor   = $theme['colors_map']['border'] ?? '#333333';
$dangerBg      = $theme['colors_map']['danger'] ?? '#450a0a';
$dangerText    = $theme['colors_map']['danger-text'] ?? '#f87171';
$successColor  = '#16a34a';
$fontFamily    = $theme['fonts'][0]['font_family'] ?? "'Segoe UI', sans-serif";

// Translation helper
function addr_t(string $key, string $fallback = ''): string {
    global $strings;
    return $strings[$key] ?? $fallback;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($direction) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= addr_t('addresses', 'Addresses') ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: <?= $fontFamily ?>;
    background: <?= $embedded ? 'transparent' : $background ?>;
    color: <?= $textPrimary ?>;
    direction: <?= $direction ?>;
    padding: <?= $embedded ? '0' : '20px' ?>;
    min-height: <?= $embedded ? 'auto' : '100vh' ?>;
}
.addr-card {
    background: <?= $cardBg ?>;
    border: 1px solid <?= $borderColor ?>;
    border-radius: 8px;
    padding: 20px;
}
.addr-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid <?= $borderColor ?>;
}
.addr-header h2 { color: <?= $primaryColor ?>; font-size: 1.1rem; }
.addr-list { display: flex; flex-direction: column; gap: 10px; }
.addr-item {
    background: <?= $background ?>;
    border: 1px solid <?= $borderColor ?>;
    border-radius: 6px;
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    transition: border-color 0.2s;
}
.addr-item:hover { border-color: <?= $primaryColor ?>; }
.addr-item.primary { border-color: <?= $successColor ?>; }
.addr-item .addr-info { flex: 1; }
.addr-item .addr-info .line1 { font-weight: 600; margin-bottom: 4px; }
.addr-item .addr-info .meta { font-size: 0.8rem; color: <?= $textSecondary ?>; }
.addr-item .addr-actions { display: flex; gap: 6px; flex-shrink: 0; }
.badge-primary {
    display: inline-block;
    background: <?= $successColor ?>;
    color: #fff;
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 10px;
    margin-inline-start: 8px;
}
.btn {
    padding: 6px 14px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.8rem;
    transition: opacity 0.2s;
}
.btn:hover { opacity: 0.85; }
.btn-primary { background: <?= $primaryColor ?>; color: #fff; }
.btn-gray { background: <?= $borderColor ?>; color: <?= $textSecondary ?>; }
.btn-danger { background: <?= $dangerBg ?>; color: <?= $dangerText ?>; }
.btn-sm { padding: 4px 10px; font-size: 0.75rem; }

/* Form */
.addr-form-wrap {
    display: none;
    margin-top: 16px;
    background: <?= $background ?>;
    border: 1px solid <?= $borderColor ?>;
    border-radius: 6px;
    padding: 16px;
}
.addr-form-wrap.open { display: block; }
.addr-form-wrap h3 {
    color: <?= $primaryColor ?>;
    margin-bottom: 14px;
    padding-bottom: 8px;
    border-bottom: 1px solid <?= $borderColor ?>;
    font-size: 0.95rem;
}
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-group.full { grid-column: 1 / -1; }
.form-group label { font-size: 0.8rem; color: <?= $textSecondary ?>; }
.form-group input, .form-group select {
    padding: 8px 10px;
    background: <?= $cardBg ?>;
    border: 1px solid <?= $borderColor ?>;
    color: <?= $textPrimary ?>;
    border-radius: 4px;
    font-size: 0.85rem;
}
.form-group input:focus, .form-group select:focus {
    outline: none;
    border-color: <?= $primaryColor ?>;
}
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 14px;
}
.checkbox-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding-top: 20px;
}
.checkbox-row input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; }
.checkbox-row label { font-size: 0.85rem; color: <?= $textSecondary ?>; cursor: pointer; }

.empty-state {
    text-align: center;
    padding: 30px;
    color: <?= $textSecondary ?>;
    font-size: 0.9rem;
}
.loading-state {
    text-align: center;
    padding: 30px;
    color: <?= $textSecondary ?>;
}
.notification {
    position: fixed;
    top: 10px;
    left: 50%;
    transform: translateX(-50%);
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    z-index: 9999;
    opacity: 0;
    transition: opacity 0.3s;
}
.notification.show { opacity: 1; }
.notification.success { background: <?= $successColor ?>; color: #fff; }
.notification.error { background: <?= $dangerBg ?>; color: <?= $dangerText ?>; }

@media (max-width: 600px) {
    .form-grid { grid-template-columns: 1fr; }
    .addr-item { flex-direction: column; }
}
</style>
</head>
<body>

<div class="addr-card">
    <div class="addr-header">
        <h2><?= addr_t('addresses', 'Addresses') ?></h2>
        <button type="button" class="btn btn-primary" id="btnAddNew">
            + <?= addr_t('add_new', 'Add New') ?>
        </button>
    </div>

    <div id="addrLoading" class="loading-state"><?= addr_t('loading', 'Loading...') ?></div>
    <div id="addrList" class="addr-list" style="display:none;"></div>
    <div id="addrEmpty" class="empty-state" style="display:none;"><?= addr_t('no_addresses', 'No addresses found') ?></div>

    <!-- Add / Edit Form -->
    <div class="addr-form-wrap" id="addrFormWrap">
        <h3 id="formTitle"><?= addr_t('add_address', 'Add Address') ?></h3>
        <form id="addrForm">
            <input type="hidden" id="fId" value="">
            <div class="form-grid">
                <div class="form-group full">
                    <label for="fLine1"><?= addr_t('address_line1', 'Address Line 1') ?> *</label>
                    <input type="text" id="fLine1" required>
                </div>
                <div class="form-group full">
                    <label for="fLine2"><?= addr_t('address_line2', 'Address Line 2') ?></label>
                    <input type="text" id="fLine2">
                </div>
                <div class="form-group">
                    <label for="fCountry"><?= addr_t('country', 'Country') ?></label>
                    <input type="number" id="fCountry" min="0">
                </div>
                <div class="form-group">
                    <label for="fCity"><?= addr_t('city', 'City') ?></label>
                    <input type="number" id="fCity" min="0">
                </div>
                <div class="form-group">
                    <label for="fPostal"><?= addr_t('postal_code', 'Postal Code') ?></label>
                    <input type="text" id="fPostal">
                </div>
                <div class="form-group">
                    <label for="fLat"><?= addr_t('latitude', 'Latitude') ?></label>
                    <input type="text" id="fLat" inputmode="decimal">
                </div>
                <div class="form-group">
                    <label for="fLng"><?= addr_t('longitude', 'Longitude') ?></label>
                    <input type="text" id="fLng" inputmode="decimal">
                </div>
                <div class="checkbox-row">
                    <input type="checkbox" id="fPrimary">
                    <label for="fPrimary"><?= addr_t('is_primary', 'Primary Address') ?></label>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-gray" id="btnCancel"><?= addr_t('cancel', 'Cancel') ?></button>
                <button type="submit" class="btn btn-primary" id="btnSave"><?= addr_t('save', 'Save') ?></button>
            </div>
        </form>
    </div>
</div>

<div class="notification" id="notification"></div>

<script>
(function() {
    'use strict';

    const CFG = {
        apiUrl: '/api/addresses',
        ownerType: <?= json_encode($ownerType) ?>,
        ownerId: <?= $ownerId ?>,
        tenantId: <?= $tenantId ?>,
        csrf: <?= json_encode($csrf) ?>,
        lang: <?= json_encode($lang) ?>,
        embedded: <?= $embedded ? 'true' : 'false' ?>,
        texts: {
            add: <?= json_encode(addr_t('add_address', 'Add Address')) ?>,
            edit: <?= json_encode(addr_t('edit_address', 'Edit Address')) ?>,
            confirmDelete: <?= json_encode(addr_t('confirm_delete', 'Are you sure you want to delete this address?')) ?>,
            saveFailed: <?= json_encode(addr_t('save_failed', 'Failed to save address')) ?>,
            deleteFailed: <?= json_encode(addr_t('delete_failed', 'Failed to delete address')) ?>,
            saved: <?= json_encode(addr_t('address_saved', 'Address saved')) ?>,
            deleted: <?= json_encode(addr_t('address_deleted', 'Address deleted')) ?>
        }
    };

    // DOM
    const $list      = document.getElementById('addrList');
    const $loading   = document.getElementById('addrLoading');
    const $empty     = document.getElementById('addrEmpty');
    const $formWrap  = document.getElementById('addrFormWrap');
    const $form      = document.getElementById('addrForm');
    const $formTitle = document.getElementById('formTitle');
    const $notif     = document.getElementById('notification');

    let addresses = [];

    // ── Helpers ──

    function notify(msg, type) {
        $notif.textContent = msg;
        $notif.className = 'notification ' + type + ' show';
        setTimeout(() => { $notif.classList.remove('show'); }, 3000);
    }

    function postToParent(type, data) {
        if (!CFG.embedded) return;
        try {
            window.parent.postMessage({ type, ...data }, '*');
        } catch (e) {
            console.warn('[Addresses] postMessage failed:', e);
        }
    }

    async function apiCall(url, opts) {
        const res = await fetch(url, opts);
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || 'API error');
        return json;
    }

    // ── Load ──

    async function loadAddresses() {
        $loading.style.display = '';
        $list.style.display = 'none';
        $empty.style.display = 'none';

        try {
            const url = `${CFG.apiUrl}?owner_type=${encodeURIComponent(CFG.ownerType)}&owner_id=${CFG.ownerId}&tenant_id=${CFG.tenantId}&lang=${CFG.lang}`;
            const json = await apiCall(url, { method: 'GET' });

            // API returns { success, data: { data: [...], meta: {...} } }
            const result = json.data || json;
            addresses = Array.isArray(result) ? result : (result.data || []);
        } catch (e) {
            console.error('[Addresses] Load failed:', e);
            addresses = [];
        }

        $loading.style.display = 'none';
        renderList();
        postToParent('address-loaded', { count: addresses.length });
    }

    // ── Render ──

    function renderList() {
        if (addresses.length === 0) {
            $list.style.display = 'none';
            $empty.style.display = '';
            return;
        }

        $empty.style.display = 'none';
        $list.style.display = '';
        $list.innerHTML = addresses.map(a => {
            const isPrimary = a.is_primary == 1 || a.is_primary === true;
            const parts = [a.address_line1];
            if (a.address_line2) parts.push(a.address_line2);
            const meta = [];
            if (a.city_id) meta.push('City: ' + a.city_id);
            if (a.country_id) meta.push('Country: ' + a.country_id);
            if (a.postal_code) meta.push(a.postal_code);
            if (a.latitude && a.longitude) meta.push('📍 ' + a.latitude + ', ' + a.longitude);

            return `<div class="addr-item${isPrimary ? ' primary' : ''}" data-id="${a.id}">
                <div class="addr-info">
                    <div class="line1">${escHtml(parts.join(', '))}${isPrimary ? '<span class="badge-primary">★</span>' : ''}</div>
                    ${meta.length ? '<div class="meta">' + escHtml(meta.join(' · ')) + '</div>' : ''}
                </div>
                <div class="addr-actions">
                    <button type="button" class="btn btn-gray btn-sm btn-edit" data-id="${a.id}">✏️</button>
                    <button type="button" class="btn btn-danger btn-sm btn-delete" data-id="${a.id}">🗑️</button>
                </div>
            </div>`;
        }).join('');
    }

    function escHtml(s) {
        const d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    // ── Form ──

    function openForm(addr) {
        const isEdit = addr && addr.id;
        $formTitle.textContent = isEdit ? CFG.texts.edit : CFG.texts.add;
        document.getElementById('fId').value      = isEdit ? addr.id : '';
        document.getElementById('fLine1').value    = isEdit ? (addr.address_line1 || '') : '';
        document.getElementById('fLine2').value    = isEdit ? (addr.address_line2 || '') : '';
        document.getElementById('fCountry').value  = isEdit ? (addr.country_id || '') : '';
        document.getElementById('fCity').value     = isEdit ? (addr.city_id || '') : '';
        document.getElementById('fPostal').value   = isEdit ? (addr.postal_code || '') : '';
        document.getElementById('fLat').value      = isEdit ? (addr.latitude || '') : '';
        document.getElementById('fLng').value      = isEdit ? (addr.longitude || '') : '';
        document.getElementById('fPrimary').checked = isEdit ? (addr.is_primary == 1) : false;
        $formWrap.classList.add('open');
        document.getElementById('fLine1').focus();
    }

    function closeForm() {
        $formWrap.classList.remove('open');
        $form.reset();
        document.getElementById('fId').value = '';
    }

    function getFormData() {
        const data = {
            owner_type:    CFG.ownerType,
            owner_id:      CFG.ownerId,
            tenant_id:     CFG.tenantId,
            address_line1: document.getElementById('fLine1').value.trim(),
            address_line2: document.getElementById('fLine2').value.trim() || null,
            country_id:    document.getElementById('fCountry').value ? parseInt(document.getElementById('fCountry').value) : null,
            city_id:       document.getElementById('fCity').value ? parseInt(document.getElementById('fCity').value) : null,
            postal_code:   document.getElementById('fPostal').value.trim() || null,
            latitude:      document.getElementById('fLat').value.trim() || null,
            longitude:     document.getElementById('fLng').value.trim() || null,
            is_primary:    document.getElementById('fPrimary').checked ? 1 : 0
        };
        const id = document.getElementById('fId').value;
        if (id) data.id = parseInt(id);
        return data;
    }

    // ── Save ──

    async function saveAddress(e) {
        e.preventDefault();
        const data = getFormData();
        if (!data.address_line1) return;

        const isEdit = !!data.id;
        const method = isEdit ? 'PUT' : 'POST';

        try {
            const json = await apiCall(CFG.apiUrl, {
                method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            notify(CFG.texts.saved, 'success');
            closeForm();
            await loadAddresses();

            const savedData = { ...data };
            if (!isEdit && json.data && json.data.id) {
                savedData.id = json.data.id;
            }
            postToParent('address-saved', { addressData: savedData });

        } catch (err) {
            console.error('[Addresses] Save failed:', err);
            notify(CFG.texts.saveFailed + ': ' + err.message, 'error');
        }
    }

    // ── Delete ──

    async function deleteAddress(id) {
        if (!confirm(CFG.texts.confirmDelete)) return;

        try {
            await apiCall(CFG.apiUrl, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });

            notify(CFG.texts.deleted, 'success');
            await loadAddresses();
            postToParent('address-deleted', { addressId: id });

        } catch (err) {
            console.error('[Addresses] Delete failed:', err);
            notify(CFG.texts.deleteFailed + ': ' + err.message, 'error');
        }
    }

    // ── Events ──

    document.getElementById('btnAddNew').addEventListener('click', () => openForm(null));
    document.getElementById('btnCancel').addEventListener('click', closeForm);
    $form.addEventListener('submit', saveAddress);

    $list.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.btn-edit');
        if (editBtn) {
            const addr = addresses.find(a => a.id == editBtn.dataset.id);
            if (addr) openForm(addr);
            return;
        }
        const delBtn = e.target.closest('.btn-delete');
        if (delBtn) {
            deleteAddress(parseInt(delBtn.dataset.id));
        }
    });

    // ── postMessage listener (from parent) ──

    window.addEventListener('message', (e) => {
        if (!e.data || typeof e.data !== 'object') return;

        switch (e.data.type) {
            case 'get-address-data':
                // Parent requests current form/address data
                const currentData = addresses.length > 0 ? addresses[0] : getFormData();
                window.parent.postMessage({
                    type: 'current-address-data',
                    addressData: currentData
                }, '*');
                break;

            case 'set-parent':
                // Parent identified itself
                if (e.data.entityId && e.data.entityId !== CFG.ownerId) {
                    CFG.ownerId = e.data.entityId;
                    loadAddresses();
                }
                break;
        }
    });

    // ── Init ──

    if (CFG.ownerId > 0) {
        loadAddresses();
    } else {
        $loading.style.display = 'none';
        $empty.style.display = '';
    }

})();
</script>

</body>
</html>
