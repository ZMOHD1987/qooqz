// htdocs/admin/assets/js/category_form.js
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('categoryForm');
  const translationsContainer = document.getElementById('translations_container');
  const langSelect = document.getElementById('lang_select');
  const addLangBtn = document.getElementById('addLangBtn');
  const msgs = document.getElementById('messages');
  const saveBtn = document.getElementById('saveBtn');

  // Helper: create translation block
  function createTranslationBlock(code, isDefault=false, data={}) {
    const div = document.createElement('div');
    div.className = 'lang-section';
    div.dataset.lang = code;

    const title = document.createElement('h3');
    title.textContent = (isDefault ? code.toUpperCase() + ' (الأساسية)' : code.toUpperCase());
    div.appendChild(title);

    const nameLabel = document.createElement('label');
    nameLabel.textContent = 'الاسم (' + code + ')';
    const nameInput = document.createElement('input');
    nameInput.type = 'text';
    nameInput.name = `translations[${code}][name]`;
    nameInput.value = data.name || '';
    nameLabel.appendChild(nameInput);
    div.appendChild(nameLabel);

    const descLabel = document.createElement('label');
    descLabel.textContent = 'الوصف (' + code + ')';
    const descTextarea = document.createElement('textarea');
    descTextarea.name = `translations[${code}][description]`;
    descTextarea.rows = 4;
    descTextarea.textContent = data.description || '';
    descLabel.appendChild(descTextarea);
    div.appendChild(descLabel);

    // optional slug override per translation
    const slugLabel = document.createElement('label');
    slugLabel.textContent = 'slug (' + code + ')';
    const slugInput = document.createElement('input');
    slugInput.type = 'text';
    slugInput.name = `translations[${code}][slug]`;
    slugInput.value = data.slug || '';
    slugLabel.appendChild(slugInput);
    div.appendChild(slugLabel);

    if (!isDefault) {
      const rm = document.createElement('button');
      rm.type = 'button';
      rm.className = 'secondary';
      rm.style.marginTop = '6px';
      rm.textContent = 'إزالة هذه اللغة';
      rm.addEventListener('click', () => div.remove());
      div.appendChild(rm);
    }

    return div;
  }

  // Add default English block
  translationsContainer.appendChild(createTranslationBlock('en', true));

  addLangBtn.addEventListener('click', function () {
    const code = langSelect.value;
    if (!code) return;
    // prevent doubling
    if (translationsContainer.querySelector(`[data-lang="${code}"]`)) {
      alert('تمت إضافة هذه اللغة بالفعل.');
      return;
    }
    translationsContainer.appendChild(createTranslationBlock(code, false));
  });

  // load parent options via API (simple list)
  fetch(window.CATEGORY_API_BASE + '/list.php')
    .then(r => r.json())
    .then(data => {
      if (data.success && Array.isArray(data.categories)) {
        const select = document.getElementById('parent_id');
        data.categories.forEach(c => {
          const opt = document.createElement('option');
          opt.value = c.id;
          opt.textContent = (c.indent ? c.indent + ' ' : '') + (c.name || c.slug);
          select.appendChild(opt);
        });
      }
    });

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    msgs.innerHTML = '';

    const fd = new FormData(form);

    // Collect translations as JSON string (server expects translations JSON OR form fields depending)
    // We'll send form fields as is (names already translations[code][name], etc.)
    saveBtn.disabled = true;
    fetch(window.CATEGORY_API_BASE + '/save.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    }).then(r => r.json())
      .then(json => {
        saveBtn.disabled = false;
        if (json.success) {
          msgs.innerHTML = `<div class="ok">${json.message || 'تم الحفظ'}</div>`;
          // optional redirect to categories list
          setTimeout(()=>{ window.location.href = 'categories.php'; }, 900);
        } else {
          msgs.innerHTML = `<div class="err">${json.message || 'فشل الحفظ'}</div>`;
        }
      }).catch(err => {
        saveBtn.disabled = false;
        msgs.innerHTML = `<div class="err">خطأ في الشبكة أو الخادم</div>`;
        console.error(err);
      });
  });

  // reset button
  document.getElementById('resetBtn')?.addEventListener('click', function(){ form.reset(); });
});