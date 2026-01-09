<?php
// htdocs/frontend/admin/theme_editor.php
// Admin UI for editing theme colors, fonts, buttons, cards, homepage sections and banners.
// Uses /api/themes endpoints to load/save data.

date_default_timezone_set('UTC');
header('Content-Type: text/html; charset=utf-8');

// simple helper to call internal API (server-side)
function api_get($path) {
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/api' . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $res = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($res, true);
    return ['ok' => $status >= 200 && $status < 300, 'json' => $json];
}

$themesResp = api_get('/themes');
$themes = $themesResp['ok'] ? ($themesResp['json']['themes'] ?? []) : [];
$activeThemeId = null;
foreach ($themes as $t) {
    if (!empty($t['is_default'])) { $activeThemeId = $t['id']; break; }
}
?>
<!doctype html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <title>محرر القالب — إدارة التصميم</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body{font-family:Arial,Helvetica,sans-serif;direction:rtl;margin:18px}
        .wrap{max-width:1100px;margin:auto;display:flex;gap:18px}
        .panel{width:350px;background:#fff;border:1px solid #eee;padding:12px;border-radius:6px;overflow:auto;height:80vh}
        .main{flex:1;background:#fff;border:1px solid #eee;padding:12px;border-radius:6px}
        label{display:block;margin-top:8px}
        input[type="text"], select, textarea{width:100%;padding:8px;box-sizing:border-box}
        .row{display:flex;gap:8px}
        .btn{display:inline-block;padding:8px 12px;background:#2d8cf0;color:#fff;border-radius:6px;text-decoration:none;cursor:pointer;margin-top:8px}
        .muted{color:#666}
        .swatch{display:inline-block;width:28px;height:20px;border:1px solid #ccc;margin-left:8px;vertical-align:middle}
        .tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px}
        .tab{padding:8px 10px;border:1px solid #ddd;border-radius:6px;cursor:pointer;background:#fafafa}
        .tab.active{background:#2d8cf0;color:#fff}
        .form-section{margin-bottom:12px;border-bottom:1px dashed #eee;padding-bottom:10px}
    </style>
</head>
<body>
<h1>محرر القالب</h1>
<p class="muted">اختر القالب ثم عدّل الألوان والخطوط وأسلوب الأزرار والبطاقات والبانرات. الضغط على "حفظ" يكتب التغييرات في قاعدة البيانات.</p>

<div class="row" style="gap:18px">
    <div class="panel">
        <h3>القوالب المتاحة</h3>
        <select id="themeSelect">
            <option value="">-- اختر قالب --</option>
            <?php foreach ($themes as $t): ?>
                <option value="<?php echo htmlspecialchars($t['id']); ?>" <?php echo ($t['id'] == $activeThemeId ? 'selected' : ''); ?>><?php echo htmlspecialchars($t['name']); ?> <?php echo (!empty($t['is_default']) ? '(نشط)' : ''); ?></option>
            <?php endforeach; ?>
        </select>

        <div style="margin-top:10px">
            <button id="activateBtn" class="btn">تفعيل القالب</button>
            <button id="loadBtn" class="btn" style="background:#27ae60">تحميل الإعدادات</button>
        </div>

        <h4 style="margin-top:16px">المعاينة (Header/ Footer)</h4>
        <div id="previewWrap" style="border:1px solid #f1f1f1;padding:8px;border-radius:6px;margin-top:8px">
            <div id="previewHeader" style="padding:12px;border-bottom:1px solid #eee">عنوان الموقع — معاينة رأس</div>
            <div style="padding:12px">محتوى تجريبي لواجهة المتجر — معاينة الصفحة</div>
            <div id="previewFooter" style="padding:12px;border-top:1px solid #eee;margin-top:8px">تذييل — معاينة تذييل</div>
        </div>
    </div>

    <div class="main">
        <div class="tabs">
            <div class="tab active" data-tab="colors">الألوان</div>
            <div class="tab" data-tab="fonts">الخطوط</div>
            <div class="tab" data-tab="buttons">الأزرار</div>
            <div class="tab" data-tab="cards">البطاقات</div>
            <div class="tab" data-tab="sections">محتوى الصفحة الرئيسية</div>
            <div class="tab" data-tab="banners">البانرات</div>
        </div>

        <div id="colors" class="tab-content">
            <h3>ألوان القالب</h3>
            <div id="colorsList"></div>
            <button id="addColor" class="btn">أضف لوناً</button>
        </div>

        <div id="fonts" class="tab-content" style="display:none">
            <h3>خطوط القالب</h3>
            <div id="fontsList"></div>
            <button id="addFont" class="btn">أضف مجموعة خط</button>
        </div>

        <div id="buttons" class="tab-content" style="display:none">
            <h3>أنماط الأزرار</h3>
            <div id="buttonsList"></div>
            <button id="addButton" class="btn">أضف نمط زر</button>
        </div>

        <div id="cards" class="tab-content" style="display:none">
            <h3>أنماط البطاقات</h3>
            <div id="cardsList"></div>
            <button id="addCard" class="btn">أضف نمط بطاقة</button>
        </div>

        <div id="sections" class="tab-content" style="display:none">
            <h3>أقسام الصفحة الرئيسية</h3>
            <div id="sectionsList"></div>
            <button id="addSection" class="btn">أضف قسم</button>
        </div>

        <div id="banners" class="tab-content" style="display:none">
            <h3>البانرات</h3>
            <div id="bannersList"></div>
            <button id="addBanner" class="btn">أضف بانر</button>
        </div>

        <div style="margin-top:12px">
            <button id="saveBtn" class="btn" style="background:#1abc9c">حفظ التغييرات</button>
            <span id="saveMsg" class="muted" style="margin-left:12px"></span>
        </div>
    </div>
</div>

<script>
let state = {
    themeId: '<?php echo $activeThemeId ? (int)$activeThemeId : ''; ?>',
    colors: [],
    fonts: [],
    buttons: [],
    cards: [],
    sections: [],
    banners: []
};

function el(id){ return document.getElementById(id); }
function api(path, method='GET', body=null) {
    const opts = { method, headers: { 'Accept': 'application/json' } };
    if (body !== null) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    return fetch('/api' + path, opts).then(r => r.json().catch(()=>null));
}

function renderColors() {
    const wrap = el('colorsList');
    if (!state.colors.length) wrap.innerHTML = '<div class="muted">لا توجد إعدادات ألوان. أضف واحدة.</div>';
    else {
        wrap.innerHTML = '';
        state.colors.forEach((c, idx) => {
            const div = document.createElement('div');
            div.className = 'form-section';
            div.innerHTML = `<label>مفتاح الإعداد <input type="text" data-idx="${idx}" data-field="setting_key" value="${escapeHtml(c.setting_key||'')}"></label>
                <label>الاسم <input type="text" data-idx="${idx}" data-field="setting_name" value="${escapeHtml(c.setting_name||'')}"></label>
                <label>القيمة <input type="text" data-idx="${idx}" data-field="color_value" value="${escapeHtml(c.color_value||'#000000')}"> <span class="swatch" style="background:${escapeHtml(c.color_value||'#000000')}"></span></label>
                <label>الفئة <input type="text" data-idx="${idx}" data-field="category" value="${escapeHtml(c.category||'')}" placeholder="primary/secondary/..."></label>
                <div style="margin-top:6px"><button data-idx="${idx}" class="btn removeColor" style="background:#e74c3c">حذف</button></div>`;
            wrap.appendChild(div);
        });
    }
}

function renderFonts() {
    const wrap = el('fontsList');
    if (!state.fonts.length) wrap.innerHTML = '<div class="muted">لا توجد مجموعات خطوط. أضف مجموعة.</div>';
    else {
        wrap.innerHTML = '';
        state.fonts.forEach((f, idx) => {
            const div = document.createElement('div');
            div.className = 'form-section';
            div.innerHTML = `<label>مفتاح <input type="text" data-idx="${idx}" data-field="setting_key" value="${escapeHtml(f.setting_key||'')}"></label>
                <label>الاسم <input type="text" data-idx="${idx}" data-field="setting_name" value="${escapeHtml(f.setting_name||'')}"></label>
                <label>العائلة <input type="text" data-idx="${idx}" data-field="font_family" value="${escapeHtml(f.font_family||'')}"></label>
                <label>الحجم <input type="text" data-idx="${idx}" data-field="font_size" value="${escapeHtml(f.font_size||'')}"></label>
                <label>الوزن <input type="text" data-idx="${idx}" data-field="font_weight" value="${escapeHtml(f.font_weight||'')}"></label>
                <div style="margin-top:6px"><button data-idx="${idx}" class="btn removeFont" style="background:#e74c3c">حذف</button></div>`;
            wrap.appendChild(div);
        });
    }
}

function renderButtons() {
    const wrap = el('buttonsList');
    if (!state.buttons.length) wrap.innerHTML = '<div class="muted">لا توجد أنماط أزرار.</div>';
    else {
        wrap.innerHTML = '';
        state.buttons.forEach((b, idx) => {
            const div = document.createElement('div');
            div.className = 'form-section';
            div.innerHTML = `<label>الاسم <input type="text" data-idx="${idx}" data-field="name" value="${escapeHtml(b.name||'')}"></label>
                <label>الخلفية <input type="text" data-idx="${idx}" data-field="background_color" value="${escapeHtml(b.background_color||'#2d8cf0')}"> <span class="swatch" style="background:${escapeHtml(b.background_color||'#2d8cf0')}"></span></label>
                <label>اللون <input type="text" data-idx="${idx}" data-field="text_color" value="${escapeHtml(b.text_color||'#fff')}"></label>
                <label>الحواف (px) <input type="text" data-idx="${idx}" data-field="border_radius" value="${escapeHtml(b.border_radius||4)}"></label>
                <div style="margin-top:6px"><button data-idx="${idx}" class="btn removeButton" style="background:#e74c3c">حذف</button></div>`;
            wrap.appendChild(div);
        });
    }
}

function renderCards() {
    const wrap = el('cardsList');
    if (!state.cards.length) wrap.innerHTML = '<div class="muted">لا توجد أنماط بطاقات.</div>';
    else {
        wrap.innerHTML = '';
        state.cards.forEach((c, idx) => {
            const div = document.createElement('div');
            div.className = 'form-section';
            div.innerHTML = `<label>الاسم <input type="text" data-idx="${idx}" data-field="name" value="${escapeHtml(c.name||'')}"></label>
                <label>خلفية <input type="text" data-idx="${idx}" data-field="background_color" value="${escapeHtml(c.background_color||'#fff')}"></label>
                <label>حدود (px) <input type="text" data-idx="${idx}" data-field="border_radius" value="${escapeHtml(c.border_radius||8)}"></label>
                <div style="margin-top:6px"><button data-idx="${idx}" class="btn removeCard" style="background:#e74c3c">حذف</button></div>`;
            wrap.appendChild(div);
        });
    }
}

function renderSections() {
    const wrap = el('sectionsList');
    if (!state.sections.length) wrap.innerHTML = '<div class="muted">لا توجد أقسام للصفحة الرئيسية.</div>';
    else {
        wrap.innerHTML = '';
        state.sections.forEach((s, idx) => {
            const div = document.createElement('div');
            div.className = 'form-section';
            div.innerHTML = `<label>النوع <input type="text" data-idx="${idx}" data-field="section_type" value="${escapeHtml(s.section_type||'featured_products')}" placeholder="slider/featured_products"></label>
                <label>العنوان <input type="text" data-idx="${idx}" data-field="title" value="${escapeHtml(s.title||'')}"></label>
                <label>المصدر <input type="text" data-idx="${idx}" data-field="data_source" value="${escapeHtml(s.data_source||'')}"></label>
                <div style="margin-top:6px"><button data-idx="${idx}" class="btn removeSection" style="background:#e74c3c">حذف</button></div>`;
            wrap.appendChild(div);
        });
    }
}

function renderBanners() {
    const wrap = el('bannersList');
    if (!state.banners.length) wrap.innerHTML = '<div class="muted">لا توجد بانرات.</div>';
    else {
        wrap.innerHTML = '';
        state.banners.forEach((b, idx) => {
            const div = document.createElement('div');
            div.className = 'form-section';
            div.innerHTML = `<label>العنوان <input type="text" data-idx="${idx}" data-field="title" value="${escapeHtml(b.title||'')}"></label>
                <label>صورة (URL) <input type="text" data-idx="${idx}" data-field="image_url" value="${escapeHtml(b.image_url||'')}"></label>
                <label>رابط <input type="text" data-idx="${idx}" data-field="link_url" value="${escapeHtml(b.link_url||'')}"></label>
                <div style="margin-top:6px"><button data-idx="${idx}" class="btn removeBanner" style="background:#e74c3c">حذف</button></div>`;
            wrap.appendChild(div);
        });
    }
}

function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];});
}

function attachDelegation() {
    document.body.addEventListener('input', function(ev){
        const input = ev.target;
        const idx = input.getAttribute('data-idx');
        const field = input.getAttribute('data-field');
        if (idx === null || field === null) return;
        const i = parseInt(idx,10);
        // try to find which array holds this index (colors/fonts/buttons/cards/sections/banners)
        ['colors','fonts','buttons','cards','sections','banners'].forEach(key=>{
            if (state[key] && state[key][i] !== undefined && input) {
                state[key][i][field] = input.value;
                if (key === 'colors') updatePreview();
            }
        });
    });

    document.body.addEventListener('click', function(ev){
        const t = ev.target;
        if (t.classList.contains('removeColor')) {
            const idx = parseInt(t.getAttribute('data-idx'),10);
            state.colors.splice(idx,1); renderColors(); updatePreview();
        }
        if (t.classList.contains('removeFont')) {
            const idx = parseInt(t.getAttribute('data-idx'),10);
            state.fonts.splice(idx,1); renderFonts();
        }
        if (t.classList.contains('removeButton')) {
            const idx = parseInt(t.getAttribute('data-idx'),10);
            state.buttons.splice(idx,1); renderButtons();
        }
        if (t.classList.contains('removeCard')) {
            const idx = parseInt(t.getAttribute('data-idx'),10);
            state.cards.splice(idx,1); renderCards();
        }
        if (t.classList.contains('removeSection')) {
            const idx = parseInt(t.getAttribute('data-idx'),10);
            state.sections.splice(idx,1); renderSections();
        }
        if (t.classList.contains('removeBanner')) {
            const idx = parseInt(t.getAttribute('data-idx'),10);
            state.banners.splice(idx,1); renderBanners();
        }
    });
}

function updatePreview() {
    // update header/footer preview using primary / background colors and first font
    const primary = (state.colors.find(c=>c.category==='primary')||state.colors[0]||{}).color_value || '#2d8cf0';
    const background = (state.colors.find(c=>c.category==='background')||{}).color_value || '#ffffff';
    const headingFont = (state.fonts.find(f=>f.category==='heading')||state.fonts[0]||{}).font_family || 'inherit';
    el('previewHeader').style.background = primary;
    el('previewHeader').style.color = '#fff';
    el('previewHeader').style.fontFamily = headingFont;
    el('previewFooter').style.background = background;
}

document.addEventListener('DOMContentLoaded', function(){
    // tabs
    document.querySelectorAll('.tab').forEach(tab=>{
        tab.addEventListener('click', function(){
            document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
            this.classList.add('active');
            document.querySelectorAll('.tab-content').forEach(c=>c.style.display='none');
            el(this.getAttribute('data-tab')).style.display='block';
        });
    });

    // load selected theme on load or when loadBtn pressed
    el('loadBtn').addEventListener('click', function(){
        const sel = el('themeSelect').value;
        if (!sel) { alert('اختر قالبا أولاً'); return; }
        state.themeId = sel;
        api('/themes/'+sel+'/design-settings').then(json=>{
            if (!json) { alert('فشل جلب البيانات'); return; }
            // server returns keys: colors, fonts, buttons, cards, settings, homepage_sections, banners
            state.colors = json.colors || json.data && json.data.colors || [];
            state.fonts = json.fonts || json.data && json.data.fonts || [];
            state.buttons = json.buttons || json.data && json.data.buttons || [];
            state.cards = json.cards || json.data && json.data.cards || [];
            state.sections = json.homepage_sections || json.data && json.data.homepage_sections || [];
            state.banners = json.banners || json.data && json.data.banners || [];
            renderColors(); renderFonts(); renderButtons(); renderCards(); renderSections(); renderBanners();
            updatePreview();
        });
    });

    // activate theme
    el('activateBtn').addEventListener('click', function(){
        const sel = el('themeSelect').value;
        if (!sel) { alert('اختر قالبا'); return; }
        if (!confirm('تأكيد تفعيل القالب؟')) return;
        api('/themes/'+sel+'/activate','POST',{}).then(json=>{
            alert((json && json.message) || 'تم');
            location.reload();
        });
    });

    // add item buttons
    el('addColor').addEventListener('click', function(){
        state.colors.push({ setting_key: 'new_color_'+(state.colors.length+1), setting_name: 'New color', color_value:'#000000', category:'other', is_active:1 });
        renderColors(); updatePreview();
    });
    el('addFont').addEventListener('click', function(){
        state.fonts.push({ setting_key:'new_font_'+(state.fonts.length+1), setting_name:'New font', font_family:'Arial, sans-serif', font_size:'16px', font_weight:'normal', category:'body', is_active:1 });
        renderFonts();
    });
    el('addButton').addEventListener('click', function(){ state.buttons.push({ name:'Primary', slug:'primary', button_type:'primary', background_color:'#2d8cf0', text_color:'#ffffff', border_radius:4 }); renderButtons(); });
    el('addCard').addEventListener('click', function(){ state.cards.push({ name:'Default', slug:'default', card_type:'product', background_color:'#ffffff', border_radius:8 }); renderCards(); });
    el('addSection').addEventListener('click', function(){ state.sections.push({ section_type:'featured_products', title:'قسم جديد', items_per_row:4 }); renderSections(); });
    el('addBanner').addEventListener('click', function(){ state.banners.push({ title:'بانر جديد', image_url:'', link_url:'' }); renderBanners(); });

    attachDelegation();

    // save handler
    el('saveBtn').addEventListener('click', function(){
        if (!state.themeId) { alert('حدد قالباً ثم حمّل الإعدادات'); return; }
        el('saveMsg').textContent = 'جاري الحفظ...';
        const payload = {
            colors: state.colors,
            fonts: state.fonts,
            buttons: state.buttons,
            cards: state.cards,
            settings: [], // you can map design_settings here if needed
            homepage_sections: state.sections,
            banners: state.banners
        };
        api('/themes/'+state.themeId+'/design-settings','POST',payload).then(json=>{
            el('saveMsg').textContent = (json && json.message) || 'تم الحفظ';
            setTimeout(()=> el('saveMsg').textContent = '', 1500);
        }).catch(err=>{
            el('saveMsg').textContent = 'فشل الحفظ';
        });
    });

    // if themeSelect had active theme, auto-load
    if (state.themeId) { el('loadBtn').click(); }
});
</script>
</body>
</html>