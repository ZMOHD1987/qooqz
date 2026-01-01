// htdocs/admin/assets/js/category.js
// Simple uploader helper for category images. Requires fetch API.
// Usage: initCategoryImageUpload({ uploadUrl, csrfToken, iconInput, imageInput, iconFileInput, imageFileInput, iconPreview, imagePreview });

function initCategoryImageUpload(opts) {
  if (!opts) return;
  const { uploadUrl, csrfToken, iconInput, imageInput, iconFileInput, imageFileInput, iconPreview, imagePreview } = opts;

  async function uploadFile(file) {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('csrf_token', csrfToken);
    const resp = await fetch(uploadUrl, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    });
    return await resp.json();
  }

  if (iconFileInput) {
    iconFileInput.addEventListener('change', async (e) => {
      const f = e.target.files[0];
      if (!f) return;
      const res = await uploadFile(f);
      if (res && res.success) {
        iconInput.value = res.url;
        if (iconPreview) iconPreview.innerHTML = "<img src='" + res.url + "' style='height:80px' />";
      } else {
        alert(res.error || 'Upload failed');
      }
    });
  }

  if (imageFileInput) {
    imageFileInput.addEventListener('change', async (e) => {
      const f = e.target.files[0];
      if (!f) return;
      const res = await uploadFile(f);
      if (res && res.success) {
        imageInput.value = res.url;
        if (imagePreview) imagePreview.innerHTML = "<img src='" + res.url + "' style='height:120px' />";
      } else {
        alert(res.error || 'Upload failed');
      }
    });
  }
}