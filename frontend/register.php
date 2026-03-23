<?php
declare(strict_types=1);

/**
 * frontend/register.php
 * QOOQZ — Registration with WhatsApp Number Verification
 *
 * Single-page flow:
 *   Step 1 — Fill registration form (username, email, password, phone, lang, country, city)
 *   Step 2 — Generate a WhatsApp verification link (session-bound token)
 *   Step 3 — Copy / open WhatsApp link; countdown until expiry
 *
 * Uses the same shared session config as the API so that the session cookie
 * (APP_SESSID) is shared between this page and all /api/* endpoints.
 */

if (session_status() === PHP_SESSION_NONE) {
    $__sharedSess = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/shared/config/session.php';
    if (file_exists($__sharedSess)) {
        require_once $__sharedSess;
    } else {
        $__sp = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/storage/sessions';
        session_name('APP_SESSID');
        if (is_dir($__sp)) ini_set('session.save_path', $__sp);
        session_start();
    }
    unset($__sharedSess, $__sp);
}

ini_set('display_errors', '0');
error_reporting(E_ALL);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// Language / direction detection
$lang   = $_SESSION['pub_lang'] ?? $_SESSION['user']['preferred_language'] ?? 'ar';
$dir    = in_array($lang, ['ar', 'fa', 'ur', 'he'], true) ? 'rtl' : 'ltr';
$isRtl  = $dir === 'rtl';

// If already logged in, redirect to home
if (!empty($_SESSION['user']['id'])) {
    header('Location: /frontend/public/index.php');
    exit;
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= $dir ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>QOOQZ — <?= $isRtl ? 'إنشاء حساب عبر واتساب' : 'Register via WhatsApp' ?></title>
    <?php if ($isRtl): ?>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php else: ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php endif; ?>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --wa:   #25D366;
            --wa-dk:#128C7E;
            --pri:  #0b6f00;
            --gray: #6c757d;
            --err:  #721c24;
            --err-bg:#f8d7da;
            --ok:   #155724;
            --ok-bg:#d4edda;
            --warn-bg:#fff3cd;
            --warn: #856404;
            --radius:10px;
            --shadow:0 4px 24px rgba(0,0,0,.12);
        }

        body {
            font-family: <?= $isRtl ? "'Cairo'" : "'Inter'" ?>, system-ui, Arial, sans-serif;
            background: linear-gradient(135deg, var(--wa) 0%, var(--wa-dk) 100%);
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 32px 16px 48px;
        }

        .rw-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 520px;
            overflow: hidden;
        }

        /* ── Header ── */
        .rw-header {
            background: var(--wa);
            padding: 24px 28px 20px;
            text-align: center;
            color: #fff;
        }
        .rw-header .rw-logo { font-size: 2.2rem; margin-bottom: 4px; }
        .rw-header h1 { font-size: 1.35rem; font-weight: 700; }
        .rw-header p  { font-size: .85rem; opacity: .9; margin-top: 4px; }

        /* ── Steps indicator ── */
        .rw-steps {
            display: flex;
            justify-content: center;
            gap: 0;
            background: #f0faf3;
            padding: 14px 0 10px;
            border-bottom: 1px solid #e0f0e6;
        }
        .rw-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
            font-size: .75rem;
            color: #aaa;
        }
        .rw-step::after {
            content: '';
            position: absolute;
            top: 13px;
            <?= $isRtl ? 'left' : 'right' ?>: 0;
            width: 50%;
            height: 2px;
            background: #ddd;
        }
        .rw-step:last-child::after { display: none; }
        .rw-step-num {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: #ddd;
            color: #888;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700;
            font-size: .8rem;
            margin-bottom: 4px;
            transition: background .3s, color .3s;
        }
        .rw-step.active .rw-step-num  { background: var(--wa); color: #fff; }
        .rw-step.done  .rw-step-num   { background: var(--pri); color: #fff; }
        .rw-step.active, .rw-step.done { color: #333; }

        /* ── Body ── */
        .rw-body { padding: 28px; }

        /* ── Form fields ── */
        .rw-field { margin-bottom: 16px; }
        .rw-field label {
            display: block;
            font-size: .82rem;
            font-weight: 600;
            color: #444;
            margin-bottom: 5px;
        }
        .rw-field input,
        .rw-field select {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid #ccc;
            border-radius: var(--radius);
            font-size: .95rem;
            font-family: inherit;
            transition: border-color .2s;
            background: #fafafa;
        }
        .rw-field input:focus,
        .rw-field select:focus {
            outline: none;
            border-color: var(--wa);
            background: #fff;
        }
        .rw-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        /* ── Buttons ── */
        .rw-btn {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%;
            padding: 12px 16px;
            border: none;
            border-radius: var(--radius);
            font-size: .95rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: filter .2s, transform .1s;
        }
        .rw-btn:active { transform: scale(.98); }
        .rw-btn:hover  { filter: brightness(1.07); }
        .rw-btn-wa    { background: var(--wa);   color: #fff; }
        .rw-btn-pri   { background: var(--pri);  color: #fff; }
        .rw-btn-gray  { background: var(--gray); color: #fff; }
        .rw-btn-out   { background: transparent; color: var(--gray); border: 1.5px solid var(--gray); width: auto; padding: 8px 14px; }
        .rw-btn-sm    { font-size: .82rem; padding: 8px 14px; width: auto; }
        .rw-btn-row   { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }

        /* ── Alerts ── */
        .rw-alert {
            padding: 12px 14px;
            border-radius: var(--radius);
            font-size: .88rem;
            margin-top: 14px;
            display: none;
        }
        .rw-alert.ok   { background: var(--ok-bg);   color: var(--ok);   display: block; }
        .rw-alert.err  { background: var(--err-bg);  color: var(--err);  display: block; }
        .rw-alert.info { background: #e3f2fd;         color: #1565c0;     display: block; }
        .rw-alert.warn { background: var(--warn-bg);  color: var(--warn); display: block; }

        /* ── Link box ── */
        .rw-link-box {
            background: #f8f9fa;
            border: 2px dashed #25D366;
            border-radius: var(--radius);
            padding: 14px;
            margin-top: 16px;
            word-break: break-all;
            font-size: .85rem;
            color: #333;
        }
        .rw-link-box a { color: #0d6efd; text-decoration: none; }
        .rw-link-box a:hover { text-decoration: underline; }

        /* ── Message preview ── */
        .rw-msg-preview {
            width: 100%;
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: monospace;
            font-size: .82rem;
            resize: none;
            background: #fff;
        }

        /* ── Countdown ── */
        .rw-timer {
            text-align: center;
            padding: 14px;
            background: var(--warn-bg);
            border-radius: var(--radius);
            margin-top: 16px;
        }
        .rw-timer .rw-clock {
            font-size: 2rem;
            font-weight: 700;
            color: #c0392b;
            font-variant-numeric: tabular-nums;
        }
        .rw-timer p { font-size: .8rem; color: var(--warn); margin-top: 4px; }

        /* ── Action cards (copy / open WA) ── */
        .rw-actions {
            display: grid;
            grid-template-columns: repeat(3,1fr);
            gap: 8px;
            margin-top: 14px;
        }
        .rw-action-btn {
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 4px;
            padding: 12px 8px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-family: inherit;
            font-size: .78rem;
            font-weight: 600;
            transition: filter .2s, transform .1s;
        }
        .rw-action-btn:active  { transform: scale(.97); }
        .rw-action-btn:hover   { filter: brightness(1.08); }
        .rw-action-btn .icon   { font-size: 1.4rem; }
        .rw-action-wa  { background: var(--wa);  color: #fff; }
        .rw-action-cp  { background: #28a745;    color: #fff; }
        .rw-action-msg { background: #0088cc;    color: #fff; }

        /* ── Instructions ── */
        .rw-instructions {
            background: #e3f2fd;
            border-radius: var(--radius);
            padding: 14px;
            margin-top: 16px;
            font-size: .82rem;
        }
        .rw-instructions h5 { margin-bottom: 8px; color: #1565c0; }
        .rw-instructions ol { padding-<?= $isRtl ? 'right' : 'left' ?>: 18px; }
        .rw-instructions li { margin-bottom: 6px; }

        /* ── Meta info ── */
        .rw-meta {
            font-size: .8rem;
            color: #666;
            background: #f5f5f5;
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 14px;
        }
        .rw-meta span { font-weight: 600; color: #333; }

        /* ── Footer link ── */
        .rw-footer-link {
            text-align: center;
            font-size: .83rem;
            color: #666;
            margin-top: 20px;
        }
        .rw-footer-link a { color: var(--wa-dk); font-weight: 600; text-decoration: none; }
        .rw-footer-link a:hover { text-decoration: underline; }

        /* ── Spinner ── */
        .rw-spinner {
            display: inline-block;
            width: 18px; height: 18px;
            border: 3px solid rgba(255,255,255,.5);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            vertical-align: middle;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 480px) {
            .rw-row { grid-template-columns: 1fr; }
            .rw-actions { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<div class="rw-card">

    <!-- Header -->
    <div class="rw-header">
        <div class="rw-logo">
            <svg viewBox="0 0 24 24" width="40" height="40" fill="#fff" style="display:inline-block;vertical-align:middle;margin-<?= $isRtl ? 'left' : 'right' ?>:8px">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.76.982.998-3.675-.236-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.9 6.994c-.004 5.45-4.438 9.88-9.888 9.88m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.333.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.333 11.893-11.893 0-3.18-1.24-6.162-3.495-8.411"/>
            </svg>
            QOOQZ
        </div>
        <h1><?= $isRtl ? 'إنشاء حساب جديد' : 'Create New Account' ?></h1>
        <p><?= $isRtl ? 'سجّل بياناتك وستصلك رسالة SMS لتفعيل حسابك' : 'Register and receive an SMS to activate your account' ?></p>
    </div>

    <!-- Step indicators -->
    <div class="rw-steps" id="rwSteps">
        <div class="rw-step active" id="sInd1">
            <div class="rw-step-num">1</div>
            <span><?= $isRtl ? 'البيانات' : 'Details' ?></span>
        </div>
        <div class="rw-step" id="sInd2">
            <div class="rw-step-num">2</div>
            <span><?= $isRtl ? 'التفعيل' : 'Activation' ?></span>
        </div>
    </div>

    <div class="rw-body">

        <!-- ══════════════════════════════════════
             STEP 1 — Registration form
        ══════════════════════════════════════ -->
        <div id="step1">

            <div class="rw-row">
                <div class="rw-field">
                    <label for="rw_username"><?= $isRtl ? 'اسم المستخدم *' : 'Username *' ?></label>
                    <input id="rw_username" type="text" autocomplete="username" required
                           placeholder="<?= $isRtl ? 'أدخل اسم المستخدم' : 'Enter username' ?>">
                </div>
                <div class="rw-field">
                    <label for="rw_email"><?= $isRtl ? 'البريد الإلكتروني *' : 'Email *' ?></label>
                    <input id="rw_email" type="email" autocomplete="email" required
                           placeholder="example@mail.com">
                </div>
            </div>

            <div class="rw-field">
                <label for="rw_phone"><?= $isRtl ? 'رقم الجوال *' : 'Phone Number *' ?></label>
                <input id="rw_phone" type="tel" autocomplete="tel" required
                       placeholder="+971 50 000 0000"
                       style="font-size:1.05rem;font-weight:600;letter-spacing:.5px;">
                <small style="color:#888;font-size:.75rem;display:block;margin-top:4px;">
                    <?= $isRtl ? 'سيُرسَل رابط التفعيل إلى هذا الرقم عبر SMS' : 'An activation link will be sent to this number via SMS' ?>
                </small>
            </div>

            <div class="rw-field">
                <label for="rw_password"><?= $isRtl ? 'كلمة المرور *' : 'Password *' ?></label>
                <input id="rw_password" type="password" autocomplete="new-password" required
                       minlength="6" placeholder="••••••••">
            </div>

            <div class="rw-field">
                <label for="rw_lang"><?= $isRtl ? 'اللغة المفضلة' : 'Preferred Language' ?></label>
                <select id="rw_lang">
                    <option value="ar" <?= $lang === 'ar' ? 'selected' : '' ?>>العربية</option>
                    <option value="en" <?= $lang === 'en' ? 'selected' : '' ?>>English</option>
                </select>
            </div>

            <div id="regAlert" class="rw-alert"></div>

            <button id="btnRegister" class="rw-btn rw-btn-wa" style="margin-top:6px;">
                <span id="btnRegisterIcon">📋</span>
                <?= $isRtl ? 'إنشاء الحساب والمتابعة' : 'Create Account & Continue' ?>
            </button>

            <div class="rw-footer-link">
                <?= $isRtl ? 'لديك حساب؟' : 'Already have an account?' ?>
                <a href="/frontend/login.php"><?= $isRtl ? 'تسجيل الدخول' : 'Sign In' ?></a>
            </div>
        </div>

        <!-- ══════════════════════════════════════
             STEP 2 — Waiting for SMS activation
        ══════════════════════════════════════ -->
        <div id="step2" style="display:none;text-align:center;">
            <div style="font-size:4rem;margin-bottom:16px;">📱</div>
            <h2 style="font-size:1.2rem;margin-bottom:8px;">
                <?= $isRtl ? 'تحقق من رسائل SMS' : 'Check Your SMS' ?>
            </h2>
            <p style="color:#555;font-size:.88rem;margin-bottom:12px;">
                <?= $isRtl
                    ? 'تم إنشاء حسابك! أرسلنا رابط التفعيل إلى رقم:'
                    : 'Your account has been created! We sent an activation link to:' ?>
            </p>
            <div style="font-size:1.1rem;font-weight:700;color:var(--pri);margin-bottom:20px;">
                <span id="metaPhone">—</span>
            </div>
            <div style="background:#f0faf3;border:1px solid #c3e6cb;border-radius:10px;padding:16px;margin-bottom:20px;font-size:.87rem;color:#155724;text-align:<?= $isRtl ? 'right' : 'left' ?>;">
                <?php if ($isRtl): ?>
                <strong>📌 خطوات التفعيل:</strong>
                <ol style="margin:8px 0 0;padding-right:18px;">
                    <li>افتح الرسالة النصية الواردة على رقمك</li>
                    <li>اضغط رابط التفعيل <strong>على نفس الجهاز</strong></li>
                    <li>سيتم تفعيل حسابك تلقائياً</li>
                </ol>
                <?php else: ?>
                <strong>📌 Activation Steps:</strong>
                <ol style="margin:8px 0 0;padding-left:18px;">
                    <li>Open the SMS sent to your phone</li>
                    <li>Tap the activation link <strong>on this same device</strong></li>
                    <li>Your account will be activated automatically</li>
                </ol>
                <?php endif; ?>
            </div>
            <div id="smsAlert" class="rw-alert"></div>
            <div class="rw-btn-row" style="margin-top:16px;justify-content:center;">
                <button id="btnBackToStep1" class="rw-btn rw-btn-out rw-btn-sm">
                    ← <?= $isRtl ? 'تعديل البيانات' : 'Edit Details' ?>
                </button>
                <button id="btnStartOver" class="rw-btn rw-btn-out rw-btn-sm">
                    ↺ <?= $isRtl ? 'بدء من جديد' : 'Start Over' ?>
                </button>
            </div>
        </div>

    </div><!-- /.rw-body -->
</div><!-- /.rw-card -->

<script>
(function () {
    'use strict';

    // ── DOM refs ──────────────────────────────────────────────────────
    const step1    = document.getElementById('step1');
    const step2    = document.getElementById('step2');
    const sInd1    = document.getElementById('sInd1');
    const sInd2    = document.getElementById('sInd2');
    const regAlert = document.getElementById('regAlert');
    const metaPhone= document.getElementById('metaPhone');
    const csrfToken= <?= json_encode($csrf) ?>;

    // ── State ─────────────────────────────────────────────────────────
    let state = { user_id: null, username: null, phone: null };

    // Restore partial session from sessionStorage (same tab only)
    try {
        const saved = JSON.parse(sessionStorage.getItem('rw_state') || 'null');
        if (saved && saved.user_id) { Object.assign(state, saved); }
    } catch (_) {}

    // ── Helpers ───────────────────────────────────────────────────────
    function showAlert(el, type, msg) {
        el.className = 'rw-alert ' + type;
        el.textContent = msg;
    }
    function hideAlert(el) { el.className = 'rw-alert'; el.textContent = ''; }

    function setStep(n) {
        step1.style.display = n === 1 ? '' : 'none';
        step2.style.display = n === 2 ? '' : 'none';
        sInd1.className = 'rw-step' + (n > 1 ? ' done' : ' active');
        sInd2.className = 'rw-step' + (n === 2 ? ' active' : '');
    }

    async function postJSON(url, body) {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const txt = await r.text();
        try { return { ok: true, status: r.status, data: JSON.parse(txt) }; }
        catch (_) { return { ok: false, status: r.status, text: txt }; }
    }

    // ── Language select ───────────────────────────────────────────────
    const langSelect = document.getElementById('rw_lang');

    // ── Step 1: Register ──────────────────────────────────────────────
    document.getElementById('btnRegister').addEventListener('click', async () => {
        hideAlert(regAlert);

        const username = document.getElementById('rw_username').value.trim();
        const email    = document.getElementById('rw_email').value.trim();
        const phone    = document.getElementById('rw_phone').value.trim();
        const password = document.getElementById('rw_password').value;

        if (!username || !email || !password) {
            showAlert(regAlert, 'err', '<?= $isRtl ? 'يرجى تعبئة جميع الحقول المطلوبة (*).' : 'Please fill all required fields (*).' ?>');
            return;
        }
        if (!phone) {
            showAlert(regAlert, 'err', '<?= $isRtl ? 'رقم الجوال مطلوب.' : 'Phone number is required.' ?>');
            return;
        }

        const btn = document.getElementById('btnRegister');
        btn.disabled = true;
        btn.innerHTML = '<span class="rw-spinner"></span> <?= $isRtl ? 'جارٍ التسجيل...' : 'Registering...' ?>';

        const body = {
            action:             'register',
            username,
            email,
            password,
            phone:              phone || null,
            preferred_language: langSelect ? langSelect.value : <?= json_encode($lang) ?>,
            csrf_token:         csrfToken,
        };

        const res = await postJSON('/api/auth', body);

        btn.disabled = false;
        btn.innerHTML = '📋 <?= $isRtl ? 'إنشاء الحساب والمتابعة' : 'Create Account & Continue' ?>';

        if (!res.ok) {
            showAlert(regAlert, 'err', '<?= $isRtl ? 'خطأ من السيرفر: ' : 'Server error: ' ?>' + (res.text || res.status));
            return;
        }

        const j = res.data;
        if (!j.ok) {
            showAlert(regAlert, 'err', (j.error || j.message || JSON.stringify(j)));
            return;
        }

        state.user_id  = j.user?.id      ?? j.user_id ?? null;
        state.username = j.user?.username ?? username;
        state.phone    = j.user?.phone    ?? phone;

        try { sessionStorage.setItem('rw_state', JSON.stringify(state)); } catch (_) {}

        // Redirect to phone verification page
        const verifyUrl = '/frontend/verify_phone.php?waiting=1&phone=' + encodeURIComponent(state.phone || '');
        window.location.href = verifyUrl;
    });

    // ── Navigation ────────────────────────────────────────────────────
    document.getElementById('btnBackToStep1').addEventListener('click', () => setStep(1));
    document.getElementById('btnStartOver').addEventListener('click', () => {
        state = {};
        try { sessionStorage.removeItem('rw_state'); } catch (_) {}
        location.reload();
    });

    // ── Init ──────────────────────────────────────────────────────────
    (function init() {
        if (state.user_id) {
            metaPhone.textContent = state.phone ?? '—';
            setStep(2);
        }
    })();

})();
</script>

</body>
</html>