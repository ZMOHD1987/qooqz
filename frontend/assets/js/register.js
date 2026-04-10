// htdocs/assets/js/register.js
// Updated to match current DB schema:
// - Only sends fields that exist in users table (no users.latitude/longitude/location).
// - Store coordinates are optional and sent under store_latitude/store_longitude.
// - Better error handling for fetch (shows response text when server returns HTML or error).
// - Loads countries and cities, toggles store section for vendor roles.

(function(){
  'use strict';

  const API_REGISTER = '/api/users/register_user.php';
  const API_VERIFY = '/api/users/verify_signed_token.php'; // verification endpoint
  const API_COUNTRIES = '/api/countries.php';
  const API_CITIES = '/api/cities.php?country_id=';

  const form = document.getElementById('registerForm');
  const roleSelect = document.getElementById('role_key');
  const storeSection = document.getElementById('storeSection');
  const storeUseAccount = document.getElementById('store_use_account');
  const countrySelect = document.getElementById('country_id');
  const citySelect = document.getElementById('city_id');
  const storeCountrySelect = document.getElementById('store_country_id');
  const submitBtn = document.getElementById('submitBtn');
  const msgEl = document.getElementById('msg');

  // token panel elements
  const tokenPanel = document.getElementById('tokenPanel');
  const tokenValue = document.getElementById('tokenValue');
  const copyTokenBtn = document.getElementById('copyToken');
  const openWhatsappBtn = document.getElementById('openWhatsApp');
  const verifyTokenInput = document.getElementById('verify_token_input');
  const verifyTokenBtn = document.getElementById('verifyTokenBtn');
  const verifyMsg = document.getElementById('verifyMsg');

  const vendorRoles = ['vendor','home_vendor','third_party_service'];

  function setMsg(text, isError){
    msgEl.textContent = text || '';
    msgEl.style.color = isError ? '#b00020' : '#0b6f00';
  }

  function toggleStore(){
    if (vendorRoles.indexOf(roleSelect.value) !== -1) storeSection.classList.remove('hidden');
    else storeSection.classList.add('hidden');
  }

  roleSelect.addEventListener('change', toggleStore);
  toggleStore();

  async function loadCountries() {
    try {
      const res = await fetch(API_COUNTRIES, { credentials:'include' });
      const json = await res.json();
      if (json.success && Array.isArray(json.countries)) {
        countrySelect.innerHTML = '<option value="">(select)</option>';
        storeCountrySelect.innerHTML = '<option value="">(select)</option>';
        json.countries.forEach(c=>{
          const o = document.createElement('option'); o.value = c.id; o.textContent = c.name;
          countrySelect.appendChild(o);
          storeCountrySelect.appendChild(o.cloneNode(true));
        });
      } else {
        countrySelect.innerHTML = '<option value="">(error)</option>';
        storeCountrySelect.innerHTML = '<option value="">(error)</option>';
      }
    } catch(e) {
      countrySelect.innerHTML = '<option value="">(error)</option>';
      storeCountrySelect.innerHTML = '<option value="">(error)</option>';
      console.error(e);
    }
  }

  async function loadCitiesFor(selectEl, countryId) {
    if (!countryId) { selectEl.innerHTML = '<option value="">Select country first</option>'; return; }
    try {
      const res = await fetch(API_CITIES + encodeURIComponent(countryId), { credentials:'include' });
      const json = await res.json();
      selectEl.innerHTML = '<option value="">(select)</option>';
      if (json.success && Array.isArray(json.cities)) {
        json.cities.forEach(c => {
          const o = document.createElement('option'); o.value = c.id; o.textContent = c.name; selectEl.appendChild(o);
        });
      } else {
        selectEl.innerHTML = '<option value="">(error)</option>';
      }
    } catch(e) {
      selectEl.innerHTML = '<option value="">(error)</option>';
      console.error(e);
    }
  }

  countrySelect.addEventListener('change', function(){ loadCitiesFor(citySelect, this.value); });
  storeCountrySelect.addEventListener('change', function(){ loadCitiesFor(document.getElementById('store_city_id'), this.value); });

  loadCountries();

  storeUseAccount.addEventListener('change', function(){
    if (!this.checked) return;
    // copy account contact values to store fields visually
    document.getElementById('store_email').value = document.getElementById('email').value || '';
    document.getElementById('store_phone').value = document.getElementById('phone').value || '';
    document.getElementById('store_country_id').value = document.getElementById('country_id').value || '';
    const userCountry = document.getElementById('country_id').value;
    if (userCountry) {
      loadCitiesFor(document.getElementById('store_city_id'), userCountry).then(()=> {
        document.getElementById('store_city_id').value = document.getElementById('city_id').value || '';
      });
    }
  });

  // Helper to read text response when status not ok.
  async function readResponseText(response) {
    try {
      return await response.text();
    } catch (e) {
      return String(response.status) + ' ' + response.statusText;
    }
  }

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    setMsg('');
    submitBtn.disabled = true;

    const fd = new FormData();

    // user fields (only those that exist in users table)
    ['username','email','password','role_key','phone','preferred_language','country_id','city_id','timezone','postal_code'].forEach(k=>{
      const el = document.getElementsByName(k)[0];
      if (el && el.value !== undefined && el.value !== '') fd.set(k, el.value);
    });

    // store fields only when requested
    if (document.getElementById('create_store') && document.getElementById('create_store').checked) {
      fd.set('create_store','1');
      if (storeUseAccount.checked) fd.set('store_use_account','1');
      ['store_name','store_email','store_phone','store_country_id','store_city_id','store_street_address','store_postal_code','store_state','store_latitude','store_longitude'].forEach(k=>{
        const el = document.getElementsByName(k)[0];
        if (el && el.value !== undefined && el.value !== '') fd.set(k, el.value);
      });
    }

    let res, text;
    try {
      res = await fetch(API_REGISTER, { method:'POST', body: fd, credentials:'include' });
    } catch (networkErr) {
      console.error('Network/Fetch error', networkErr);
      setMsg('Network error: ' + (networkErr.message || 'Failed to contact server'), true);
      submitBtn.disabled = false;
      return;
    }

    // If server returned non-JSON (HTML error), show it
    if (!res.ok) {
      text = await readResponseText(res);
      console.error('Server error response', res.status, text);
      setMsg('Server error: ' + (res.status + ' — see console'), true);
      submitBtn.disabled = false;
      return;
    }

    // parse JSON safely
    try {
      const json = await res.json();
      if (!json.success) {
        setMsg(json.message || 'Registration failed', true);
        submitBtn.disabled = false;
        return;
      }
      setMsg('Account created. Follow verification instructions below.', false);

      // show token if provided
      if (json.token) {
        tokenValue.textContent = json.token;
        tokenPanel.classList.remove('hidden');

        openWhatsappBtn.onclick = function(){
          const textMsg = encodeURIComponent('Verification token: ' + json.token);
          // open wa.me with prefilled text: user must send from their phone
          window.open('https://wa.me/?text=' + textMsg, '_blank');
        };
        copyTokenBtn.onclick = function(){
          navigator.clipboard.writeText(json.token).then(()=> {
            copyTokenBtn.textContent = 'Copied';
            setTimeout(()=> copyTokenBtn.textContent = 'Copy', 1400);
          });
        };
      } else {
        // No token returned: show notice
        setMsg(json.notice || 'Account created. No token returned.', false);
      }

    } catch (err) {
      // invalid JSON
      const bodyText = await readResponseText(res);
      console.error('Invalid JSON response', err, bodyText);
      setMsg('Unexpected server response. See console.', true);
    } finally {
      submitBtn.disabled = false;
    }
  });

  // verify token
  verifyTokenBtn.addEventListener('click', async function(){
    const tok = verifyTokenInput.value.trim();
    const phone = document.getElementById('phone').value.trim();
    if (!tok) { verifyMsg.textContent = 'Please enter the token you sent.'; verifyMsg.style.color = '#b00020'; return; }
    verifyMsg.textContent = 'Verifying...';
    try {
      const fd = new FormData();
      fd.set('token', tok);
      fd.set('phone', phone);
      const res = await fetch(API_VERIFY, { method:'POST', body:fd, credentials:'include' });
      const json = await res.json();
      if (json.success) {
        verifyMsg.textContent = 'Verification successful. Your account is active.';
        verifyMsg.style.color = '#0b6f00';
        setTimeout(()=> window.location.href = '/', 1200);
      } else {
        verifyMsg.textContent = json.message || 'Verification failed';
        verifyMsg.style.color = '#b00020';
      }
    } catch (e) {
      console.error('verify error', e);
      verifyMsg.textContent = 'Network or server error';
      verifyMsg.style.color = '#b00020';
    }
  });

})();