(function(){
  'use strict';
  // waits up to timeoutMs for cookieName to appear, then resolves true/false
  function waitForCookie(cookieName, timeoutMs = 6000) {
    return new Promise((resolve) => {
      const start = Date.now();
      function check() {
        if (document.cookie && document.cookie.indexOf(cookieName + '=') !== -1) return resolve(true);
        if (Date.now() - start > timeoutMs) return resolve(false);
        setTimeout(check, 200);
      }
      check();
    });
  }

  // Hook into your register form submit flow.
  const form = document.getElementById('registerForm');
  if (!form) return;

  form.addEventListener('submit', async function(e){
    // If you already have client-side code that builds FormData and does fetch,
    // make sure to integrate the wait logic before the fetch happens.
    // This small wrapper prevents sending the request until the anti-bot cookie exists.
    // If cookie not found, show a friendly message and reload to allow gate script to run.
    const cookiePresent = await waitForCookie('__test', 6000);
    if (!cookiePresent) {
      e.preventDefault();
      alert('Please enable JavaScript and allow the page a few seconds to validate (anti-bot). The page will reload to try again.');
      // optional: reload to run gate script which will set cookie
      setTimeout(() => window.location.reload(), 500);
      return false;
    }
    // If cookie present, allow normal submit to continue.
    // If you use fetch/ajax for submission, ensure fetch(..., { credentials: "include" }) so cookie is sent.
  }, { once: false });
})();