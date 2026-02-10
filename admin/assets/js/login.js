// admin/assets/js/login.js
// Sends login/register requests to the API at /api/auth
// Important: include credentials so cookies (session) are preserved: credentials: 'same-origin'

function showForm(form) {
    document.getElementById('loginForm').classList.add('hidden');
    document.getElementById('registerForm').classList.add('hidden');
    document.getElementById('tab-login').classList.remove('active');
    document.getElementById('tab-register').classList.remove('active');

    if (form === 'login') {
        document.getElementById('loginForm').classList.remove('hidden');
        document.getElementById('tab-login').classList.add('active');
    } else {
        document.getElementById('registerForm').classList.remove('hidden');
        document.getElementById('tab-register').classList.add('active');
    }
    clearResult();
}

function clearErrors(formId) {
    document.querySelectorAll('#' + formId + ' .error').forEach(e => e.remove());
}

function showErrors(formId, errors) {
    clearErrors(formId);
    for (const [field, msg] of Object.entries(errors || {})) {
        const input = document.querySelector('#' + formId + ' [name="' + field + '"]');
        if (input) {
            const div = document.createElement('div');
            div.className = 'error';
            div.textContent = msg;
            input.parentNode.insertBefore(div, input.nextSibling);
        }
    }
}

function setResult(message, ok = true) {
    const r = document.getElementById('result');
    r.innerHTML = '<div class="' + (ok ? 'result-ok' : 'result-err') + '">' + message + '</div>';
}

function clearResult() {
    const r = document.getElementById('result');
    r.innerHTML = '';
}

async function postToApi(formId) {
    clearErrors(formId);
    clearResult();

    const form = document.getElementById(formId);
    const formData = new FormData(form);

    // Basic client-side validation
    if (!formData.get('password') || (formId === 'loginForm' && !formData.get('username'))) {
        const errs = {};
        if (!formData.get('username')) errs.username = 'Username / email / phone is required';
        if (!formData.get('password')) errs.password = 'Password is required';
        showErrors(formId, errs);
        return;
    }

    try {
        const resp = await fetch('/api/auth', {
            method: 'POST',
            credentials: 'same-origin', // ensure session cookie is sent/received
            body: formData
        });

        // Try to parse JSON response
        const data = await resp.json().catch(() => null);

        // If the server returned a non-2xx status, show message from body if present
        if (!resp.ok) {
            if (data && data.errors) {
                showErrors(formId, data.errors);
                setResult(data.message || 'Validation failed', false);
            } else {
                setResult((data && data.message) ? data.message : 'Server returned an error', false);
            }
            return;
        }

        // Server returned 2xx: check different possible structures
        // Accept formats:
        // - { success: true, ... }
        // - ResponseFormatter::success -> maybe { status: 'success', data: ... } or similar
        const success = data && (data.success === true || data.status === 'success' || data.message === 'Login successful');

        if (success) {
            setResult(data.message || 'Success', true);
            if (formId === 'loginForm') {
                // Redirect to admin dashboard after short delay
                setTimeout(() => { window.location.href = '/admin/dashboard.php'; }, 600);
            }
        } else {
            // Show errors if any
            if (data && data.errors) {
                showErrors(formId, data.errors);
                setResult(data.message || 'Validation failed', false);
            } else {
                setResult((data && data.message) ? data.message : 'Invalid credentials', false);
            }
        }
    } catch (err) {
        console.error(err);
        setResult('Network or server error', false);
    }
}

// Attach events
document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');

    loginForm.addEventListener('submit', function (e) {
        e.preventDefault();
        postToApi('loginForm');
    });

    registerForm.addEventListener('submit', function (e) {
        e.preventDefault();
        postToApi('registerForm');
    });
});