// htdocs/assets/js/login.js
console.log('login.js loaded');

document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('loginForm');
  const msg = document.getElementById('login_message');

  function show(text, isError = false) {
    if (msg) { msg.textContent = text; msg.style.color = isError ? 'red' : 'green'; }
  }

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    show('جاري تسجيل الدخول...');
    const fd = new FormData(form);
    const payload = { identifier: fd.get('identifier'), password: fd.get('password') };

    try {
      const res = await fetch('/api/users/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        credentials: 'include'
      });
      const data = await res.json();
      if (data.success) {
        show('تم تسجيل الدخول');
        if (data.token) localStorage.setItem('auth_token', data.token);
        // optional: redirect after login
        // location.href = '/frontend/dashboard.html';
      } else {
        show(data.message || 'فشل تسجيل الدخول', true);
      }
    } catch (err) {
      console.error(err);
      show('خطأ في الاتصال', true);
    }
  });
});