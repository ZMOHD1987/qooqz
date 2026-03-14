// frontend/assets/js/login.js
// Sends login/register requests to the API at /api/auth
// Important: include credentials so cookies (session) are preserved: credentials: 'same-origin'

function showForm(form) {
    document.getElementById('loginForm').classList.add('lq-hidden');
    document.getElementById('registerForm').classList.add('lq-hidden');
    document.getElementById('tab-login').classList.remove('active');
    document.getElementById('tab-register').classList.remove('active');

    if (form === 'login') {
        document.getElementById('loginForm').classList.remove('lq-hidden');
        document.getElementById('tab-login').classList.add('active');
        document.getElementById('tab-login').setAttribute('aria-selected', 'true');
        document.getElementById('tab-register').setAttribute('aria-selected', 'false');
    } else {
        document.getElementById('registerForm').classList.remove('lq-hidden');
        document.getElementById('tab-register').classList.add('active');
        document.getElementById('tab-register').setAttribute('aria-selected', 'true');
        document.getElementById('tab-login').setAttribute('aria-selected', 'false');
    }
    clearResult();
}

function clearFieldErrors(formId) {
    document.querySelectorAll('#' + formId + ' .lq-field-error').forEach(e => e.remove());
}

function showFieldErrors(formId, errors) {
    clearFieldErrors(formId);
    for (const [field, msg] of Object.entries(errors || {})) {
        const input = document.querySelector('#' + formId + ' [name="' + field + '"]');
        if (input) {
            const div = document.createElement('div');
            div.className = 'lq-field-error';
            div.textContent = msg;
            input.closest('.lq-field')?.appendChild(div) || input.parentNode.insertBefore(div, input.nextSibling);
        }
    }
}

function setResult(message, ok = true) {
    const r = document.getElementById('result');
    r.innerHTML = '<div class="' + (ok ? 'lq-ok' : 'lq-err') + '">' + message + '</div>';
}

function clearResult() {
    const r = document.getElementById('result');
    if (r) r.innerHTML = '';
}

async function postToApi(formId) {
    clearFieldErrors(formId);
    clearResult();

    const form = document.getElementById(formId);
    const btn = form.querySelector('button[type="submit"]');
    const formData = new FormData(form);

    // Basic client-side validation
    if (formId === 'loginForm') {
        if (!formData.get('username') || !formData.get('password')) {
            showFieldErrors(formId, {
                username: formData.get('username') ? '' : 'Required',
                password: formData.get('password') ? '' : 'Required',
            });
            return;
        }
    }

    if (btn) { btn.disabled = true; btn.style.opacity = '0.7'; }

    try {
        const resp = await fetch('/api/auth', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });

        const data = await resp.json().catch(() => null);

        if (btn) { btn.disabled = false; btn.style.opacity = ''; }

        if (!resp.ok) {
            if (data && data.errors) {
                showFieldErrors(formId, data.errors);
                setResult(data.message || 'Validation failed', false);
            } else {
                setResult((data && data.message) ? data.message : 'Server returned an error', false);
            }
            return;
        }

        const success = data && (data.success === true || data.status === 'success' ||
            data.message === 'Authenticated' || data.message === 'Registration successful' ||
            (data.data && data.data.ok === true));

        if (success) {
            setResult(data.message || 'Success', true);

            // Store user info in localStorage for header display
            try {
                const u = (data.data && data.data.user) ? data.data.user : (data.user || null);
                if (u && u.id) {
                    localStorage.setItem('pubUser', JSON.stringify({
                        id:       u.id,
                        name:     u.name || u.username || u.email || 'User',
                        username: u.username || '',
                    }));
                }
            } catch (e) {}

            // Redirect URL validation — only allow relative paths on same origin
            const redirect = new URLSearchParams(window.location.search).get('redirect');
            const safeRedirect = (redirect && redirect.startsWith('/') && !redirect.startsWith('//'))
                ? redirect : '/frontend/public/index.php';
            setTimeout(() => {
                window.location.href = safeRedirect;
            }, 600);
        } else {
            if (data && data.errors) {
                showFieldErrors(formId, data.errors);
                setResult(data.message || 'Validation failed', false);
            } else {
                setResult((data && data.message) ? data.message : 'Invalid credentials', false);
            }
        }
    } catch (err) {
        if (btn) { btn.disabled = false; btn.style.opacity = ''; }
        console.error(err);
        setResult('Network or server error', false);
    }
}

// Attach events
document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');

    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            postToApi('loginForm');
        });
    }

    if (registerForm) {
        registerForm.addEventListener('submit', function (e) {
            e.preventDefault();
            postToApi('registerForm');
        });
    }

    // Auto-switch to register tab if URL has ?tab=register
    const params = new URLSearchParams(window.location.search);
    if (params.get('tab') === 'register') {
        showForm('register');
    }

    // If user is already logged in (PHP session user injected by header.php or window.pubSessionUser),
    // sync to localStorage and redirect away from login page.
    try {
        const existing = localStorage.getItem('pubUser');
        if (!existing && typeof window.pubSessionUser !== 'undefined' && window.pubSessionUser && window.pubSessionUser.id) {
            localStorage.setItem('pubUser', JSON.stringify(window.pubSessionUser));
            const redirect2 = new URLSearchParams(window.location.search).get('redirect');
            const safe2 = (redirect2 && redirect2.startsWith('/') && !redirect2.startsWith('//'))
                ? redirect2 : '/frontend/public/index.php';
            window.location.href = safe2;
        }
    } catch (e) {}
});