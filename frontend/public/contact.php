<?php
declare(strict_types=1);
/**
 * frontend/public/contact.php
 * QOOQZ — Contact Us Page
 * Requires login — user_id is stored with every message.
 */
require_once dirname(__DIR__) . '/includes/public_context.php';

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('contact.page_title', 'Contact Us') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_TYPE']  = 'contact';

/* Pre-fill name / email from session when logged in */
$_userName  = $_isLoggedIn ? ($_user['name'] ?? $_user['username'] ?? '') : '';
$_userEmail = $_isLoggedIn ? ($_user['email'] ?? '') : '';

include dirname(__DIR__) . '/partials/header.php';
?>

<style>
.contact-hero {
    background: linear-gradient(135deg, var(--pub-primary, #2563eb) 0%, #1e40af 100%);
    color: #fff;
    padding: 3.5rem 1.5rem;
    text-align: center;
}
.contact-hero h1 { font-size: 2rem; margin-bottom: .5rem; }
.contact-hero p  { font-size: 1rem; opacity: .9; }

.contact-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2.5rem;
    max-width: 960px;
    margin: 3rem auto;
    padding: 0 1.5rem 3rem;
}
@media (max-width: 700px) {
    .contact-layout { grid-template-columns: 1fr; }
}

.contact-form-card {
    background: var(--pub-surface, #fff);
    border: 1px solid var(--pub-border, #e5e7eb);
    border-radius: 12px;
    padding: 2rem;
}
.contact-form-card h2 {
    font-size: 1.25rem;
    margin-bottom: 1.5rem;
    color: var(--pub-primary, #2563eb);
}
.contact-form .form-group { margin-bottom: 1.25rem; }
.contact-form label {
    display: block;
    font-size: .875rem;
    font-weight: 500;
    margin-bottom: .4rem;
    color: var(--pub-text, #374151);
}
.contact-form input,
.contact-form textarea,
.contact-form select {
    width: 100%;
    padding: .65rem .9rem;
    border: 1px solid var(--pub-border, #d1d5db);
    border-radius: 8px;
    font-size: .95rem;
    color: var(--pub-text, #374151);
    background: var(--pub-bg, #fff);
    box-sizing: border-box;
    transition: border-color .2s;
}
.contact-form input:focus,
.contact-form textarea:focus { outline: none; border-color: var(--pub-primary, #2563eb); }
.contact-form input[readonly] {
    background: var(--pub-surface, #f3f4f6);
    color: var(--pub-text-muted, #6b7280);
    cursor: not-allowed;
}
.contact-form textarea { resize: vertical; min-height: 130px; }
.contact-form-submit {
    width: 100%;
    padding: .75rem;
    background: var(--pub-primary, #2563eb);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity .2s;
}
.contact-form-submit:hover { opacity: .9; }

.contact-info-card {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
.contact-info-item {
    background: var(--pub-surface, #f9fafb);
    border: 1px solid var(--pub-border, #e5e7eb);
    border-radius: 12px;
    padding: 1.25rem;
}
.contact-info-item .label {
    font-size: .8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--pub-text-muted, #6b7280);
    margin-bottom: .35rem;
}
.contact-info-item .value {
    font-size: .95rem;
    color: var(--pub-text, #374151);
    font-weight: 500;
}

#contactMsg {
    padding: .75rem 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    display: none;
}
#contactMsg.success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
#contactMsg.error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

.contact-login-notice {
    max-width: 600px;
    margin: 3rem auto;
    padding: 2.5rem 2rem;
    text-align: center;
    background: var(--pub-surface, #f9fafb);
    border: 1px solid var(--pub-border, #e5e7eb);
    border-radius: 12px;
}
.contact-login-notice .icon { font-size: 2.5rem; margin-bottom: 1rem; }
.contact-login-notice h2 { font-size: 1.3rem; margin-bottom: .6rem; color: var(--pub-text, #111827); }
.contact-login-notice p  { color: var(--pub-text-muted, #6b7280); margin-bottom: 1.5rem; }
.contact-login-notice a {
    display: inline-block;
    background: var(--pub-primary, #2563eb);
    color: #fff;
    padding: .7rem 2rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
}
.contact-login-notice a:hover { opacity: .9; }
</style>

<section class="contact-hero">
    <h1><?= e(t('contact.hero_title', 'Get in Touch')) ?></h1>
    <p><?= e(t('contact.hero_subtitle', 'We are here to help.')) ?></p>
</section>

<?php if (!$_isLoggedIn): ?>
<!-- Login required notice -->
<div class="contact-login-notice">
    <div class="icon">🔒</div>
    <h2><?= e(t('contact.login_required_title', 'Login Required')) ?></h2>
    <p><?= e(t('contact.login_required_text', 'You need to be logged in to send us a message. Please log in or create an account to continue.')) ?></p>
    <a href="/frontend/login.php?redirect=<?= urlencode('/frontend/public/contact.php') ?>"><?= e(t('contact.login_cta', 'Log In')) ?></a>
</div>
<?php else: ?>

<div class="contact-layout">

    <!-- Contact Form -->
    <div class="contact-form-card">
        <h2><?= e(t('contact.form_title', 'Send Us a Message')) ?></h2>
        <div id="contactMsg" role="alert"></div>
        <form id="contactForm" class="contact-form" novalidate>
            <div class="form-group">
                <label for="cName"><?= e(t('contact.name', 'Full Name')) ?></label>
                <input type="text" id="cName" name="name" value="<?= e($_userName) ?>" placeholder="<?= e(t('contact.name_placeholder', 'Your full name')) ?>" <?= $_userName ? 'readonly aria-label="' . e(t('contact.name', 'Full Name')) . ' — ' . e(t('contact.autofilled', 'auto-filled from your account')) . '"' : '' ?> required>
            </div>
            <div class="form-group">
                <label for="cEmail"><?= e(t('contact.email', 'Email Address')) ?></label>
                <input type="email" id="cEmail" name="email" value="<?= e($_userEmail) ?>" placeholder="<?= e(t('contact.email_placeholder', 'your@email.com')) ?>" <?= $_userEmail ? 'readonly aria-label="' . e(t('contact.email', 'Email Address')) . ' — ' . e(t('contact.autofilled', 'auto-filled from your account')) . '"' : '' ?> required>
            </div>
            <div class="form-group">
                <label for="cSubject"><?= e(t('contact.subject', 'Subject')) ?></label>
                <input type="text" id="cSubject" name="subject" placeholder="<?= e(t('contact.subject_placeholder', 'How can we help?')) ?>" required>
            </div>
            <div class="form-group">
                <label for="cMessage"><?= e(t('contact.message', 'Message')) ?></label>
                <textarea id="cMessage" name="message" placeholder="<?= e(t('contact.message_placeholder', 'Describe your question or issue...')) ?>" required></textarea>
            </div>
            <button type="submit" class="contact-form-submit" id="contactSubmit">
                <?= e(t('contact.send', 'Send Message')) ?>
            </button>
        </form>
    </div>

    <!-- Info -->
    <div class="contact-info-card">
        <div class="contact-info-item">
            <div class="label"><?= e(t('contact.email_label', 'Email')) ?></div>
            <div class="value">📧 <?= e(t('contact.email_value', 'support@qooqz.com')) ?></div>
        </div>
        <div class="contact-info-item">
            <div class="label"><?= e(t('contact.hours_label', 'Support Hours')) ?></div>
            <div class="value">🕘 <?= e(t('contact.hours_value', 'Sun – Thu, 9AM – 6PM GST')) ?></div>
        </div>
        <div class="contact-info-item">
            <div class="label"><?= e(t('contact.response_label', 'Response Time')) ?></div>
            <div class="value">⚡ <?= e(t('contact.response_value', 'Within 24 business hours')) ?></div>
        </div>
        <div class="contact-info-item">
            <div class="label"><?= e(t('contact.social_title', 'Follow Us')) ?></div>
            <div class="value">🌐 QOOQZ</div>
        </div>
    </div>

</div>

<script>
(function () {
    const form   = document.getElementById('contactForm');
    const msgEl  = document.getElementById('contactMsg');
    const submit = document.getElementById('contactSubmit');

    const labelSending = <?= json_encode(t('contact.sending', 'Sending...')) ?>;
    const labelSend    = <?= json_encode(t('contact.send', 'Send Message')) ?>;
    const msgSuccess   = <?= json_encode(t('contact.success', 'Message sent!')) ?>;
    const msgError     = <?= json_encode(t('contact.error', 'Failed to send. Please try again.')) ?>;

    function showMsg(text, type) {
        msgEl.textContent = text;
        msgEl.className   = type;
        msgEl.style.display = 'block';
        msgEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const name    = form.name.value.trim();
        const email   = form.email.value.trim();
        const subject = form.subject.value.trim();
        const message = form.message.value.trim();
        if (!name || !email || !subject || !message) return;

        submit.disabled = true;
        submit.textContent = labelSending;

        fetch('/api/public/contact', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ name, email, subject, message })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showMsg(msgSuccess, 'success');
                form.reset();
            } else {
                showMsg(d.message || msgError, 'error');
            }
        })
        .catch(() => showMsg(msgError, 'error'))
        .finally(() => {
            submit.disabled = false;
            submit.textContent = labelSend;
        });
    });
})();
</script>

<?php endif; ?>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>