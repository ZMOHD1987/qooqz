<?php
declare(strict_types=1);
/**
 * frontend/verify_phone.php
 *
 * Phone verification landing page.
 * Opened automatically when the user clicks the SMS activation link.
 *
 * Two modes:
 *   ?t=RAW_TOKEN          — Fresh activation attempt (from SMS link)
 *   ?status=success       — Already activated; show success message
 *   ?status=error&msg=…   — Activation failed; show error message
 *   ?waiting              — Show waiting/resend UI
 */

if (session_status() === PHP_SESSION_NONE) {
    $__sharedSess = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/shared/config/session.php';
    if (file_exists($__sharedSess)) {
        require_once $__sharedSess;
    } else {
        session_name('APP_SESSID');
        session_start();
    }
    unset($__sharedSess);
}

ini_set('display_errors', '0');

$status   = trim($_GET['status']  ?? '');
$rawMsg   = trim($_GET['msg']     ?? '');
$rawToken = trim($_GET['t']       ?? '');
$waiting  = !empty($_GET['waiting']);
$rawPhone = trim($_GET['phone']   ?? '');
// Sanitise phone for display only (never trusted for auth)
$displayPhone = preg_replace('/[^\d+]/', '', $rawPhone);
$displayPhone = htmlspecialchars(substr($displayPhone, 0, 20), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// Activation link is never stored in session (security hardening);
// it is fetched fresh via the resend API when the user requests it.
$sessionVerifyLink = '';

// Ensure a CSRF token is available for JS-driven POST requests (activate, resend, WhatsApp)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$pageCsrfToken = $_SESSION['csrf_token'];

// If we already have a status, just render the result page
$autoVerify = ($rawToken !== '' && $status === '');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفعيل الحساب</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,.10);
            padding: 48px 40px;
            max-width: 420px;
            width: 100%;
            text-align: center;
        }
        .icon { font-size: 64px; margin-bottom: 20px; line-height: 1; }
        h1 { font-size: 22px; margin-bottom: 10px; color: #1a1a2e; }
        p  { font-size: 15px; color: #555; line-height: 1.7; }
        .spinner {
            width: 52px; height: 52px;
            border: 5px solid #e2e8f0;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin .85s linear infinite;
            margin: 0 auto 24px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .btn {
            display: inline-block;
            margin-top: 24px;
            padding: 12px 28px;
            background: #3b82f6;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
        }
        .btn:hover { background: #2563eb; }
        .err { color: #dc2626; }
        .btn-resend {
            background: none;
            border: 2px solid #3b82f6;
            color: #3b82f6;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        .btn-resend:hover { background: #eff6ff; }
        .btn-resend:disabled { opacity: .5; cursor: not-allowed; }
        #resendMsg { font-size: 13px; margin-top: 10px; }
        .btn-whatsapp {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 24px;
            background: #25d366;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-whatsapp:hover { background: #1ebe5d; }
        .btn-whatsapp:disabled { opacity: .5; cursor: not-allowed; }
        .link-box {
            display: none;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 12px 14px;
            margin-top: 16px;
            text-align: right;
        }
        .link-box label {
            font-size: 12px;
            color: #64748b;
            display: block;
            margin-bottom: 6px;
        }
        .link-box .link-text {
            font-size: 12px;
            color: #1e40af;
            word-break: break-all;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px;
            display: block;
            margin-bottom: 8px;
            direction: ltr;
            text-align: left;
        }
        .link-box .btn-copy {
            background: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 6px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .link-box .btn-copy:hover { background: #2563eb; }
        .btn-show-link {
            background: none;
            border: 2px solid #64748b;
            color: #64748b;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            padding: 8px 20px;
            border-radius: 8px;
            margin-top: 8px;
        }
        .btn-show-link:hover { background: #f1f5f9; }
    </style>
</head>
<body>
<div class="card" id="card">

<?php if ($status === 'success'): ?>
    <div class="icon">✅</div>
    <h1>تم تفعيل حسابك بنجاح!</h1>
    <p>مرحباً بك. يمكنك الآن تسجيل الدخول والاستمتاع بخدماتنا.</p>
    <a href="/frontend/public/index.php" class="btn">الذهاب للرئيسية</a>

<?php elseif ($status === 'error'): ?>
    <div class="icon">❌</div>
    <h1 class="err">فشل التفعيل</h1>
    <p class="err"><?= htmlspecialchars($rawMsg ?: 'حدث خطأ غير متوقع.') ?></p>
    <a href="/frontend/" class="btn" style="background:#6b7280">العودة للرئيسية</a>

<?php elseif ($autoVerify): ?>
    <div class="spinner" id="spinner"></div>
    <h1 id="titleMsg">جاري التحقق…</h1>
    <p id="bodyMsg">يرجى الانتظار بينما نتحقق من هويتك.</p>

<?php elseif ($waiting): ?>
    <div class="icon">📱</div>
    <h1>تحقق من رسائل SMS</h1>
    <?php if ($displayPhone !== ''): ?>
    <p style="font-size:1rem;font-weight:700;color:#1a1a2e;margin:8px 0 4px;"><?= $displayPhone ?></p>
    <?php endif; ?>
    <p>تم إنشاء حسابك! أرسلنا رابط التفعيل إلى رقمك. افتح الرابط على نفس الجهاز لتفعيل حسابك.</p>
    <div style="background:#f0faf3;border:1px solid #c3e6cb;border-radius:10px;padding:14px;margin:18px 0;font-size:.87rem;color:#155724;text-align:right;">
        <strong>📌 خطوات التفعيل:</strong>
        <ol style="margin:8px 0 0;padding-right:18px;">
            <li>افتح الرسالة النصية الواردة على رقمك</li>
            <li>اضغط رابط التفعيل <strong>على نفس الجهاز</strong></li>
            <li>سيتم تفعيل حسابك تلقائياً</li>
        </ol>
    </div>
    <button id="btnResend" class="btn btn-resend" style="display:inline-block;margin-top:8px;padding:10px 24px;border-radius:8px;">
        🔁 إعادة إرسال رسالة التفعيل
    </button>
    <br>
    <button id="btnWhatsapp" class="btn-whatsapp" style="margin-top:10px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="vertical-align:middle;margin-left:6px;"><path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z"/></svg>
        إرسال الرابط عبر واتساب
    </button>
    <br>
    <button id="btnShowLink" class="btn-show-link" style="margin-top:8px;">
        🔗 عرض الرابط يدوياً
    </button>
    <div id="linkBox" class="link-box">
        <label>📋 رابط التفعيل (انسخه وأرسله عبر واتساب على نفس الجهاز):</label>
        <a id="linkText" class="link-text" href="<?= htmlspecialchars($sessionVerifyLink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" style="word-break:break-all;color:#1d4ed8;"><?= htmlspecialchars($sessionVerifyLink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
        <button type="button" class="btn-copy" id="btnCopy">نسخ الرابط</button>
        <?php if ($sessionVerifyLink !== ''): ?>
        <br>
        <a id="btnWaDirect" href="#" target="_blank" class="btn-whatsapp" style="margin-top:8px;display:inline-block;text-decoration:none;">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" style="vertical-align:middle;margin-left:4px;"><path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z"/></svg>
            فتح واتساب مع الرابط
        </a>
        <?php endif; ?>
    </div>
    <div id="resendMsg"></div>
    <a href="/frontend/" class="btn" style="background:#6b7280">العودة للرئيسية</a>
    <a href="/frontend/register.php" class="btn" style="background:#6b7280;margin-top:12px;display:inline-block;">العودة للتسجيل</a>

<?php else: ?>
    <div class="icon">🔗</div>
    <h1>رابط التفعيل</h1>
    <p>لم يتم التعرف على رابط التفعيل. يرجى فتح الرابط المرسل عبر SMS مرة أخرى.</p>
    <a href="/frontend/" class="btn" style="background:#6b7280">العودة للرئيسية</a>

<?php endif; ?>

</div>

<?php if ($autoVerify): ?>
<script>
(function () {
    'use strict';
    const token    = <?= json_encode($rawToken, JSON_UNESCAPED_UNICODE) ?>;
    const csrfToken = <?= json_encode($pageCsrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    async function activate() {
        let data;
        try {
            const res = await fetch('/api/verify_phone', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ token: token })
            });
            data = await res.json();
        } catch (e) {
            showResult(false, 'تعذّر الاتصال بالخادم. يرجى المحاولة مجدداً.');
            return;
        }

        if (data && data.ok) {
            showResult(true);
            setTimeout(() => { window.location.href = '/frontend/public/index.php'; }, 1800);
        } else {
            showResult(false, data.error || 'فشل التفعيل');
        }
    }

    function showResult(success, errMsg) {
        document.getElementById('spinner').style.display = 'none';
        const title = document.getElementById('titleMsg');
        const body  = document.getElementById('bodyMsg');
        if (success) {
            document.getElementById('card').insertAdjacentHTML(
                'afterbegin', '<div class="icon">✅</div>'
            );
            title.textContent = 'تم تفعيل حسابك بنجاح!';
            body.textContent  = 'مرحباً بك. جاري تحويلك…';
        } else {
            document.getElementById('card').insertAdjacentHTML(
                'afterbegin', '<div class="icon">❌</div>'
            );
            title.textContent = 'فشل التفعيل';
            title.className   = 'err';
            // Use textContent for the message to avoid XSS
            const errSpan = document.createElement('span');
            errSpan.className = 'err';
            errSpan.textContent = errMsg;
            const backLink = document.createElement('a');
            backLink.href = '/frontend/';
            backLink.className = 'btn';
            backLink.style.background = '#6b7280';
            backLink.style.marginTop  = '16px';
            backLink.textContent = 'العودة للرئيسية';
            body.textContent = '';
            body.appendChild(errSpan);
            body.appendChild(document.createElement('br'));
            body.appendChild(backLink);
        }
    }

    activate();
})();
</script>
<?php endif; ?>

<?php if ($waiting): ?>
<script>
(function () {
    'use strict';
    const btn        = document.getElementById('btnResend');
    const btnWa      = document.getElementById('btnWhatsapp');
    const btnShowLink= document.getElementById('btnShowLink');
    const linkBox    = document.getElementById('linkBox');
    const linkText   = document.getElementById('linkText');
    const btnCopy    = document.getElementById('btnCopy');
    const btnWaDirect= document.getElementById('btnWaDirect');
    const msg        = document.getElementById('resendMsg');

    // CSRF token for all POST requests
    const csrfToken = <?= json_encode($pageCsrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    // Link stored in session from registration (may be empty if session expired)
    let currentLink = <?= json_encode($sessionVerifyLink, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    // User's registered phone number (digits only, from registration URL param)
    let userPhone = <?= json_encode(preg_replace('/[^\d]/', '', $displayPhone), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    // Build direct WhatsApp URL — sends to user's own number if available
    function buildWaUrl(link, phone) {
        const waText = encodeURIComponent('رابط تفعيل حسابك: ' + link);
        const ph = (phone || '').replace(/[^\d]/g, '');
        return ph ? 'https://wa.me/' + ph + '?text=' + waText : 'https://wa.me/?text=' + waText;
    }

    // Update the displayed link and the direct WA button
    function updateLinkDisplay(link, phone) {
        if (!link) return;
        currentLink = link;
        if (phone) userPhone = phone.replace(/[^\d]/g, '');
        if (linkText) { linkText.textContent = link; linkText.href = link; }
        if (btnWaDirect) btnWaDirect.href = buildWaUrl(currentLink, userPhone);
    }

    // Initialise direct WA button if link already available
    if (currentLink && btnWaDirect) {
        btnWaDirect.href = buildWaUrl(currentLink, userPhone);
    }

    if (!btn) return;

    let cooldown = 0;
    let timer = null;

    function setCooldown(secs) {
        cooldown = secs;
        btn.disabled = true;
        if (btnWa) btnWa.disabled = true;
        clearInterval(timer);
        timer = setInterval(function () {
            cooldown--;
            if (cooldown <= 0) {
                clearInterval(timer);
                btn.disabled = false;
                if (btnWa) btnWa.disabled = false;
                btn.textContent = '🔁 إعادة إرسال رسالة التفعيل';
            } else {
                btn.textContent = '⏳ إعادة الإرسال بعد ' + cooldown + 'ث';
            }
        }, 1000);
    }

    async function resendAndGetLink() {
        msg.textContent = '';
        msg.style.color = '';
        btn.disabled = true;
        if (btnWa) btnWa.disabled = true;
        btn.textContent = '⏳ جارٍ الإرسال…';

        let data;
        try {
            const res = await fetch('/api/auth', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ action: 'resend_verification' })
            });
            data = await res.json();
        } catch (e) {
            msg.textContent = 'تعذّر الاتصال بالخادم. يرجى المحاولة مجدداً.';
            msg.style.color = '#dc2626';
            btn.disabled = false;
            if (btnWa) btnWa.disabled = false;
            btn.textContent = '🔁 إعادة إرسال رسالة التفعيل';
            return null;
        }

        if (data && data.ok) {
            setCooldown(60);
            const newLink = data.activation_link || null;
            const newPhone = data.phone || '';
            if (newLink) updateLinkDisplay(newLink, newPhone);
            return { link: newLink, phone: newPhone };
        } else {
            msg.textContent = '❌ ' + (data.error || 'فشل إرسال الرسالة. يرجى المحاولة مجدداً.');
            msg.style.color = '#dc2626';
            btn.disabled = false;
            if (btnWa) btnWa.disabled = false;
            btn.textContent = '🔁 إعادة إرسال رسالة التفعيل';
            return null;
        }
    }

    btn.addEventListener('click', async function () {
        const result = await resendAndGetLink();
        if (result) {
            msg.textContent = '✅ تم إرسال رسالة التفعيل بنجاح!';
            msg.style.color = '#155724';
        }
    });

    // Show/hide the manual link box
    if (btnShowLink) {
        btnShowLink.addEventListener('click', async function () {
            if (linkBox.style.display === 'block') {
                linkBox.style.display = 'none';
                btnShowLink.textContent = '🔗 عرض الرابط يدوياً';
                return;
            }
            // If no link yet, fetch one via resend
            if (!currentLink) {
                btnShowLink.textContent = '⏳ جارٍ تحضير الرابط…';
                btnShowLink.disabled = true;
                const result = await resendAndGetLink();
                btnShowLink.disabled = false;
                if (!result || !result.link) {
                    btnShowLink.textContent = '🔗 عرض الرابط يدوياً';
                    return;
                }
            }
            linkBox.style.display = 'block';
            btnShowLink.textContent = '🙈 إخفاء الرابط';
        });
    }

    // Copy link to clipboard
    if (btnCopy) {
        btnCopy.addEventListener('click', function () {
            if (!currentLink) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(currentLink).then(function () {
                    btnCopy.textContent = '✅ تم النسخ!';
                    setTimeout(function () { btnCopy.textContent = 'نسخ الرابط'; }, 2000);
                });
            } else {
                // Fallback for older browsers
                const tmp = document.createElement('textarea');
                tmp.value = currentLink;
                tmp.style.position = 'fixed';
                tmp.style.opacity = '0';
                document.body.appendChild(tmp);
                tmp.select();
                try {
                    const ok = document.execCommand('copy');
                    btnCopy.textContent = ok ? '✅ تم النسخ!' : '⚠️ انسخ الرابط يدوياً';
                } catch (ex) {
                    btnCopy.textContent = '⚠️ انسخ الرابط يدوياً';
                }
                document.body.removeChild(tmp);
                setTimeout(function () { btnCopy.textContent = 'نسخ الرابط'; }, 2500);
            }
        });
    }

    // WhatsApp button — use existing link if available, only resend if needed
    if (btnWa) {
        btnWa.addEventListener('click', async function () {
            // If we already have a link, open WhatsApp immediately (no resend needed)
            if (currentLink) {
                msg.textContent = '✅ جاري فتح واتساب…';
                msg.style.color = '#155724';
                window.open(buildWaUrl(currentLink, userPhone), '_blank');
                return;
            }

            const result = await resendAndGetLink();
            if (!result || !result.link) return;

            // Phone comes from the server response (trusted)
            // Strip non-digit chars for wa.me (no leading +)
            const serverPhone = (result.phone || '').replace(/[^\d]/g, '');
            // Validate: must be 7–15 digits
            if (serverPhone && !/^\d{7,15}$/.test(serverPhone)) {
                msg.textContent = '❌ رقم الهاتف غير صالح.';
                msg.style.color = '#dc2626';
                return;
            }

            msg.textContent = '✅ تم إنشاء الرابط. جاري فتح واتساب…';
            msg.style.color = '#155724';
            window.open(buildWaUrl(result.link, serverPhone || userPhone), '_blank');
        });
    }
})();
</script>
<?php endif; ?>

</body>
</html>