تمام — هذا **دليل احترافي كامل ومُنظم** لتحويل أي صفحة إدارية حالية إلى النظام الديناميكي المعتمد على قاعدة البيانات (Theme System + AJAX + i18n + RTL).

سأعطيك نسخة نهائية جاهزة تعتمد عليها كمصدر رسمي داخل مشروعك 👇

---

# 📘 دليل تعديل الصفحات إلى النظام الديناميكي الكامل (Admin UI System)

## 🧠 الهدف من هذا الدليل

تحويل أي صفحة قديمة (PHP / CSS / JS) إلى صفحة:

* تعتمد بالكامل على **قاعدة البيانات** في الألوان والتصميم
* تستخدم **أزرار ديناميكية (button_styles)**
* تعمل عبر **AJAX بدون تغيير الرابط**
* تدعم **RTL و i18n**
* تستخدم **AdminModal للنوافذ**
* متجاوبة 100% (Responsive)
* بدون أي ألوان hard-coded ❌

---

# 🏗️ أولاً: بنية النظام

## 1. مصادر التصميم (Database-driven)

### 🎨 الألوان → `color_settings`

مسؤولة عن:

* ألوان عامة (primary, danger, background…)
* يتم تحويلها إلى CSS Variables

مثال:

```css
--primary-color
--background-secondary
--text-primary
```

---

### 🔘 شكل الأزرار → `button_styles`

مسؤولة عن:

* شكل الزر (padding, border-radius, font)
* hover
* border

❗ لا تحتوي منطقياً على الألوان الأساسية → هذه من color_settings

---

### 🧱 التصميم العام → `design_settings`

مثل:

* border-radius
* spacing
* layout sizes

---

### 🔤 الخطوط → `font_settings`

مثل:

* font-family
* font-size
* font-weight

---

# ⚙️ ثانياً: كيف يتم تطبيق الثيم

## المسؤول الرئيسي:

📄 `/admin/includes/header.php`

يقوم بـ:

### 1. حقن CSS Variables

```css
:root {
  --primary-color: #03874e;
  --text-primary: #ffffff;
}
```

---

### 2. توليد CSS للأزرار من DB

```css
.btn-primary {
  background: #03874e;
  padding: 10px 20px;
}
```

---

### 3. تحميل الخطوط تلقائياً

---

### 4. تمرير البيانات إلى JavaScript

```js
window.ADMIN_UI = {...}
```

---

# 🧩 ثالثاً: تعديل ملفات PHP

## ✅ الهيكل القياسي

```php
<?php
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
$isFragment = $isAjax || isset($_GET['embedded']);

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

// صلاحيات
if (!is_admin_logged_in()) exit;
if (!can('your_permission')) exit;

$lang = admin_lang();
$dir = in_array($lang, ['ar','fa','he','ur']) ? 'rtl' : 'ltr';
$csrf = admin_csrf();
?>

<link rel="stylesheet" href="/admin/assets/css/pages/your_page.css">

<div class="page-container" dir="<?= $dir ?>">
    
    <div class="page-header">
        <h1 class="page-title" data-i18n="title">Title</h1>

        <div class="page-header-actions">
            <button class="btn btn-primary">Add</button>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <!-- محتوى -->
        </div>
    </div>

</div>

<script>
window.YOUR_PAGE_CONFIG = {
    csrfToken: '<?= $csrf ?>'
};
</script>

<script src="/admin/assets/js/pages/your_page.js"></script>

<?php if (!$isFragment) require_once '../includes/footer.php'; ?>
```

---

## 🔴 أخطاء ممنوعة

❌ استخدام:

```html
style="background:red"
```

❌ كتابة ألوان في PHP أو HTML

---

# 🎨 رابعاً: تعديل CSS

## ✅ القواعد الأساسية

### استخدم فقط:

```css
var(--primary-color)
```

---

## 📦 مثال كامل

```css
.page-container {
    padding: clamp(10px, 2vw, 24px);
    color: var(--text-primary);
}

.page-header {
    background: var(--background-secondary);
    border: 1px solid var(--border-color);
}

.page-title {
    font-size: clamp(1.2rem, 2vw, 1.6rem);
}

```

---

## 📱 Responsive

```css
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
    }
}
```

---

## 🔁 RTL

```css
[dir="rtl"] .page-header-actions {
    flex-direction: row-reverse;
}
```

---

## ❌ ممنوع

* ألوان ثابتة
* إعادة تعريف `.btn` أو `.card`

---

# ⚡ خامساً: JavaScript

## ✅ الهيكل القياسي

```javascript
(function() {
    'use strict';

    let CFG, CSRF;

    function init() {
        CFG = window.YOUR_PAGE_CONFIG || {};
        CSRF = CFG.csrfToken;

        loadData();
    }

    function loadData() {
        fetch('/api/data', {
            headers: { 'X-CSRF-TOKEN': CSRF }
        })
        .then(r => r.json())
        .then(console.log);
    }

    document.addEventListener('DOMContentLoaded', init);

    window.page = { run: init };

})();
```

---

## 📦 النوافذ المنبثقة

```javascript
AdminModal.openModalByUrl('/admin/fragments/add.php');
```

---

## 🔔 الإشعارات

```javascript
AdminFramework.notify("Saved", "success");
```

---

## ❌ ممنوع

* alert()
* ألوان داخل JS

---

# 🔘 سادساً: الأزرار

## ✅ استخدم فقط:

```html
btn-primary
btn-danger
btn-success
btn-warning
btn-info
btn-outline
btn-link
```

---

## ⚠️ مهم جداً

| الشيء | المصدر         |
| ----- | -------------- |
| اللون | color_settings |
| الشكل | button_styles  |

---

# 📊 سابعاً: الجداول

```html
<div class="table-responsive">
    <table class="data-table">
    </table>
</div>
```

---

# 🧱 ثامناً: البطاقات

```html
<div class="card">
    <div class="card-header"></div>
    <div class="card-body"></div>
</div>
```

---

# 🌍 تاسعاً: الترجمة (i18n)

## HTML

```html
<span data-i18n="title"></span>
```

## JS

```javascript
t('title', 'Title')
```

---

# 🔄 عاشراً: AJAX Navigation

## الرابط:

```html
<a class="js-ajax-link" data-load-url="/admin/fragments/page.php">
```

---

## التحميل:

```javascript
loadAdminFragment(url);
```

---

# 📱 الحادي عشر: Responsive + UX

✔ استخدم:

* clamp()
* flex
* overflow-x

✔ أضف:

```css
@media print {
    .btn { display: none }
}
```

---

# ✅ Checklist النهائي

قبل اعتماد الصفحة:

✔ لا يوجد أي لون ثابت
✔ كل الأزرار `.btn-*`
✔ CSS يستخدم var()
✔ دعم RTL
✔ دعم Responsive
✔ يعمل عبر AJAX
✔ لا reload كامل
✔ AdminModal مستخدم
✔ API عبر fetch
✔ CSRF مضاف
✔ data-i18n موجود
✔ الخطوط من DB
✔ tested mobile + desktop

---

# 🧪 مثال عملي سريع

## تغيير لون زر Delete:

### من DB:

```sql
UPDATE button_styles
SET background_color = '#ff0000'
WHERE slug = 'danger';
```

✔ النتيجة: يتغير فوراً بدون تعديل كود

---

# 🏁 الخلاصة

النظام الجديد يعتمد على:

| العنصر  | المصدر          |
| ------- | --------------- |
| الألوان | color_settings  |
| الأزرار | button_styles   |
| الخطوط  | font_settings   |
| التصميم | design_settings |
| السلوك  | JS + API        |


