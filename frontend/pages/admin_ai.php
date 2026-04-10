<?php
/**
 * frontend/pages/admin_ai.php
 * لوحة إدارة AI Engine — نفس طريقة الاتصال الأصلية + دعم اللغات
 */
session_start();

// ===== اللغة =====
$_allowed = ['ar', 'en'];
$lang = in_array($_GET['lang'] ?? '', $_allowed, true)
    ? $_GET['lang']
    : ($_SESSION['lang'] ?? 'ar');
$_SESSION['lang'] = $lang;
$dir = in_array($lang, ['ar', 'fa', 'ur', 'he'], true) ? 'rtl' : 'ltr';

$_lf = dirname(__DIR__, 2) . '/languages/frontend/main/' . $lang . '.json';
if (!file_exists($_lf)) {
    $_lf = dirname(__DIR__, 2) . '/languages/frontend/main/ar.json';
}
$L = file_exists($_lf) ? (json_decode(file_get_contents($_lf), true) ?? []) : [];

function L(array $t, string $k, string $fb = ''): string {
    return htmlspecialchars($t[$k] ?? $fb, ENT_QUOTES, 'UTF-8');
}

// ===== إعدادات API (نفس طريقة index.php الأصلية) =====
$API_BASE = "http://127.0.0.1:8888";

// دالة مساعدة: طلب curl مشترك
function api_get(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    return $raw ? json_decode($raw, true) : null;
}

// ====== معالجة الأفعال ======
$flash = '';

// إضافة قاعدة معرفة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_kb') {
    $ch = curl_init($API_BASE . '/api/v1/knowledge-bases');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'name'        => $_POST['kb_name'] ?? '',
            'description' => $_POST['kb_desc'] ?? '',
            'is_public'   => isset($_POST['kb_public']),
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    ]);
    $r = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $flash = ($r && ($r['status'] ?? '') === 'ok') ? "✅ " . L($L,'ai_create_kb','تم الإنشاء') : "❌ " . ($r['detail'] ?? 'خطأ');
}

// إضافة مستند
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_doc') {
    $kb_id = $_POST['doc_kb_id'] ?? '';
    $ch = curl_init($API_BASE . "/api/v1/knowledge-bases/{$kb_id}/documents");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'title'    => $_POST['doc_title'] ?? '',
            'content'  => $_POST['doc_content'] ?? '',
            'language' => $_POST['doc_lang'] ?? 'ar',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    ]);
    $r = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $flash = ($r && ($r['status'] ?? '') === 'ok')
        ? "✅ " . L($L,'ai_add_doc','تم الإضافة') . " — " . ($r['chunks_created'] ?? 0) . " chunks"
        : "❌ " . ($r['detail'] ?? 'خطأ');
}

// إضافة قطعة مباشرة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_chunk') {
    $ch = curl_init($API_BASE . "/api/v1/chunks/add");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'id'          => 'chunk-' . substr(uniqid(), -8) . '-uuid',
            'document_id' => $_POST['chunk_doc_id'] ?: 'doc-001-uuid',
            'content'     => $_POST['chunk_content'] ?? '',
            'language'    => $_POST['chunk_lang'] ?? 'ar',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    ]);
    $r = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $flash = ($r && ($r['status'] ?? '') === 'ok') ? "✅ " . L($L,'ai_add_chunk','تم الإضافة') : "❌ " . ($r['detail'] ?? 'خطأ');
}

// رفع ملف وإضافته تلقائياً لقاعدة المعرفة (Excel/PDF/DOCX/CSV/صورة)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_to_kb') {
    $kb_id = $_POST['kb_id_for_file'] ?? '';
    if (!empty($_FILES['kb_file']['tmp_name']) && !empty($kb_id)) {
        // الخطوة 1: رفع الملف لاستخراج النص
        $ch = curl_init($API_BASE . '/api/v1/files/upload');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'file' => new CURLFile(
                    $_FILES['kb_file']['tmp_name'],
                    $_FILES['kb_file']['type'],
                    $_FILES['kb_file']['name']
                ),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $file_resp = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if ($file_resp && ($file_resp['status'] ?? '') === 'ok') {
            $extracted = $file_resp['extracted_text'] ?? '';
            $fname     = $file_resp['filename'] ?? $_FILES['kb_file']['name'];
            if (!empty($extracted)) {
                // الخطوة 2: إضافة المحتوى المستخرج كمستند مقطع في قاعدة المعرفة
                $ch2 = curl_init($API_BASE . "/api/v1/knowledge-bases/{$kb_id}/documents");
                curl_setopt_array($ch2, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => json_encode([
                        'title'    => $fname,
                        'content'  => $extracted,
                        'language' => $_POST['kb_file_lang'] ?? 'ar',
                    ]),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 60,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
                ]);
                $doc_resp = json_decode(curl_exec($ch2), true);
                curl_close($ch2);
                $flash = ($doc_resp && ($doc_resp['status'] ?? '') === 'ok')
                    ? "✅ تم رفع '{$fname}' وإضافته — " . ($doc_resp['chunks_created'] ?? 0) . " قطعة"
                    : "⚠️ رُفع الملف لكن فشل التقطيع: " . ($doc_resp['detail'] ?? 'خطأ');
            } else {
                $flash = "✅ رُفع الملف '{$fname}' (لم يُستخرج نص منه)";
            }
        } else {
            $flash = "❌ فشل رفع الملف: " . ($file_resp['detail'] ?? 'خطأ في الاتصال');
        }
    } else {
        $flash = "❌ الرجاء اختيار ملف وقاعدة معرفة";
    }
}


// رفع ملف (للتخزين فقط)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_file') {
    if (!empty($_FILES['file_upload']['tmp_name'])) {
        $ch = curl_init($API_BASE . '/api/v1/files/upload');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'file' => new CURLFile(
                    $_FILES['file_upload']['tmp_name'],
                    $_FILES['file_upload']['type'],
                    $_FILES['file_upload']['name']
                ),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $r = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $flash = ($r && ($r['status'] ?? '') === 'ok')
            ? "✅ " . L($L,'ai_upload','رفع') . ": " . htmlspecialchars($r['filename'] ?? '')
            : "❌ " . L($L,'ai_upload_error','فشل الرفع');
    } else {
        $flash = "❌ " . L($L,'ai_choose_file','اختر ملف');
    }
}

// إرسال تقييم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_feedback') {
    $ch = curl_init($API_BASE . '/api/v1/feedback');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'message_id' => $_POST['fb_message_id'] ?? '',
            'rating'     => (int)($_POST['fb_rating'] ?? 5),
            'comment'    => $_POST['fb_comment'] ?? '',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    ]);
    $r = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $flash = ($r && ($r['status'] ?? '') === 'ok') ? "✅ " . L($L,'ai_send_feedback','شكراً!') : "❌ خطأ";
}

// ====== جلب البيانات (كلها عبر curl) ======
$kbs_resp    = api_get($API_BASE . '/api/v1/knowledge-bases');
$kbs         = ($kbs_resp && isset($kbs_resp['knowledge_bases'])) ? $kbs_resp['knowledge_bases'] : [];

$files_resp  = api_get($API_BASE . '/api/v1/files');
$files_list  = ($files_resp && isset($files_resp['files'])) ? $files_resp['files'] : [];

$fb_resp     = api_get($API_BASE . '/api/v1/feedback');
$feedbacks   = ($fb_resp && isset($fb_resp['feedbacks'])) ? $fb_resp['feedbacks'] : [];
$avg_rating  = $fb_resp['average_rating'] ?? 0;

$thr_resp    = api_get($API_BASE . '/api/v1/threads?limit=10');
$threads     = ($thr_resp && isset($thr_resp['threads'])) ? $thr_resp['threads'] : [];

$health      = api_get($API_BASE . '/api/v1/health');
$chunks_count= $health['total_chunks_found'] ?? 0;
$sample_chunks = $health['sample_chunks'] ?? [];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= L($L,'ai_admin_panel','لوحة إدارة AI Engine') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/ai-admin.css">
</head>
<body>

<div class="topbar">
    <h1>🛠️ <?= L($L,'ai_admin_panel','لوحة إدارة AI Engine') ?></h1>
    <div class="topbar-right">
        <a href="test_api.php?lang=<?= htmlspecialchars($lang) ?>"><?= L($L,'ai_chat_link','💬 الدردشة') ?></a>
        <a href="?" ><?= L($L,'ai_refresh','🔄 تحديث') ?></a>
        <a href="?lang=ar" class="<?= $lang==='ar' ? 'lang-active' : '' ?>">ع</a>
        <a href="?lang=en" class="<?= $lang==='en' ? 'lang-active' : '' ?>">EN</a>
    </div>
</div>

<div class="container">

    <?php if ($flash): ?>
        <div class="flash <?= str_contains($flash, '❌') ? 'err' : 'ok' ?>"><?= $flash ?></div>
    <?php endif; ?>

    <!-- إحصائيات -->
    <div class="stats">
        <div class="stat"><div class="num"><?= count($kbs) ?></div><div class="label"><?= L($L,'ai_stats_kbs','قواعد معرفة') ?></div></div>
        <div class="stat"><div class="num"><?= $chunks_count ?></div><div class="label"><?= L($L,'ai_stats_chunks','قطع نصية') ?></div></div>
        <div class="stat"><div class="num"><?= count($files_list) ?></div><div class="label"><?= L($L,'ai_stats_files','ملفات') ?></div></div>
        <div class="stat"><div class="num"><?= count($threads) ?></div><div class="label"><?= L($L,'ai_stats_threads','محادثات') ?></div></div>
        <div class="stat"><div class="num"><?= count($feedbacks) ?></div><div class="label"><?= L($L,'ai_tab_feedback','تقييمات') ?></div></div>
        <div class="stat"><div class="num"><?= $avg_rating ?></div><div class="label">⭐ <?= L($L,'ai_avg_rating','متوسط التقييم') ?></div></div>
    </div>

    <!-- تبويبات -->
    <div class="tabs">
        <button class="tab active" onclick="showPanel('feed')">📤 تغذية البيانات</button>
        <button class="tab" onclick="showPanel('kb')"><?= L($L,'ai_tab_kb','قواعد المعرفة') ?></button>
        <button class="tab" onclick="showPanel('docs')"><?= L($L,'ai_tab_docs','مستندات') ?></button>
        <button class="tab" onclick="showPanel('files')"><?= L($L,'ai_tab_files','ملفات') ?></button>
        <button class="tab" onclick="showPanel('feedback')"><?= L($L,'ai_tab_feedback','تقييمات') ?></button>
        <button class="tab" onclick="showPanel('threads')"><?= L($L,'ai_tab_threads','محادثات') ?></button>
        <button class="tab" onclick="showPanel('chunks')"><?= L($L,'ai_tab_chunks','القطع') ?></button>
    </div>

    <!-- 0. تغذية البيانات (الرئيسي) -->
    <div class="panel active" id="panel-feed">
        <div class="row-2">
            <div class="card">
                <h3>📤 رفع ملف وإضافته تلقائياً لقاعدة المعرفة</h3>
                <p style="font-size:.82rem;color:var(--text2);margin-bottom:14px">يدعم: Excel (.xlsx/.xls)، PDF، Word (.docx)، CSV، TXT، صور (PNG/JPG)</p>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_to_kb">
                    <div class="form-group">
                        <label><?= L($L,'ai_knowledge_bases','قاعدة المعرفة') ?> *</label>
                        <select name="kb_id_for_file" required>
                            <option value=""><?= L($L,'ai_kb_select','اختر...') ?></option>
                            <?php foreach ($kbs as $kb): ?>
                                <option value="<?= htmlspecialchars($kb['id']) ?>"><?= htmlspecialchars($kb['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= L($L,'ai_language','اللغة') ?></label>
                        <select name="kb_file_lang">
                            <option value="ar"><?= L($L,'ai_lang_ar','عربي') ?></option>
                            <option value="en"><?= L($L,'ai_lang_en','English') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>الملف *</label>
                        <div class="file-input-wrap">
                            <input type="file" name="kb_file" accept=".xlsx,.xls,.pdf,.doc,.docx,.csv,.txt,.jpg,.jpeg,.png,.gif" required style="display:block">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">📤 رفع وإضافة للمعرفة</button>
                </form>
            </div>
            <div class="card">
                <h3>💡 كيف يعمل</h3>
                <div style="font-size:.85rem;color:var(--text2);line-height:2">
                    <p>1️⃣ ارفع ملف Excel أو PDF أو Word أو CSV</p>
                    <p>2️⃣ يستخرج النظام النصوص تلقائياً</p>
                    <p>3️⃣ يُقطّع المحتوى ويضيفه لقاعدة المعرفة</p>
                    <p>4️⃣ يصبح متاحاً للبحث فوراً في المحادثات</p>
                    <hr style="border-color:var(--brd);margin:10px 0">
                    <p>🖼️ <strong>الصور:</strong> يتعرف النظام على محتوى الصورة ويقرأ ما فيها</p>
                    <p>📊 <strong>Excel:</strong> يقرأ كل الأوراق والبيانات</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 1. قواعد المعرفة -->
    <div class="panel" id="panel-kb">
        <div class="row-2">
            <div class="card">
                <h3>➕ <?= L($L,'ai_create_kb','إنشاء قاعدة معرفة جديدة') ?></h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_kb">
                    <div class="form-group">
                        <label><?= L($L,'ai_kb_name','اسم القاعدة *') ?></label>
                        <input type="text" name="kb_name" required>
                    </div>
                    <div class="form-group">
                        <label><?= L($L,'ai_description','الوصف') ?></label>
                        <input type="text" name="kb_desc">
                    </div>
                    <div class="form-group">
                        <div class="checkbox-row">
                            <input type="checkbox" name="kb_public" id="kb_public" checked>
                            <label for="kb_public"><?= L($L,'ai_kb_public','عامة') ?></label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= L($L,'ai_create_btn','➕ إنشاء') ?></button>
                </form>
            </div>
            <div class="card">
                <h3>📋 <?= L($L,'ai_existing_kbs','القواعد الموجودة') ?></h3>
                <?php if (empty($kbs)): ?>
                    <p style="color:var(--text3)"><?= L($L,'ai_no_kbs','لا توجد قواعد بعد') ?></p>
                <?php else: ?>
                    <table>
                        <tr><th>ID</th><th><?= L($L,'ai_name','الاسم') ?></th><th><?= L($L,'ai_description','الوصف') ?></th><th><?= L($L,'ai_public','عامة') ?></th></tr>
                        <?php foreach ($kbs as $kb): ?>
                            <tr>
                                <td class="id-cell"><?= htmlspecialchars($kb['id'] ?? '') ?></td>
                                <td><strong><?= htmlspecialchars($kb['name'] ?? '') ?></strong></td>
                                <td class="content-cell"><?= htmlspecialchars($kb['description'] ?? '-') ?></td>
                                <td><?= ($kb['is_public'] ?? 0) ? '✅' : '❌' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 2. مستندات + قطع -->
    <div class="panel" id="panel-docs">
        <div class="row-2">
            <div class="card">
                <h3>📄 <?= L($L,'ai_add_doc','إضافة مستند') ?></h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_doc">
                    <div class="form-group">
                        <label><?= L($L,'ai_knowledge_bases','قاعدة المعرفة') ?> *</label>
                        <select name="doc_kb_id" required>
                            <option value=""><?= L($L,'ai_kb_select','اختر...') ?></option>
                            <?php foreach ($kbs as $kb): ?>
                                <option value="<?= htmlspecialchars($kb['id']) ?>"><?= htmlspecialchars($kb['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= L($L,'ai_doc_title','عنوان المستند') ?></label>
                        <input type="text" name="doc_title">
                    </div>
                    <div class="form-group">
                        <label><?= L($L,'ai_language','اللغة') ?></label>
                        <select name="doc_lang">
                            <option value="ar"><?= L($L,'ai_lang_ar','عربي') ?></option>
                            <option value="en"><?= L($L,'ai_lang_en','English') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= L($L,'ai_content','المحتوى') ?> *</label>
                        <textarea name="doc_content" rows="8" required placeholder="سؤال: ما هو Python؟&#10;جواب: Python هي لغة برمجة عالية المستوى..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= L($L,'ai_add_and_chunk','📄 إضافة وتقطيع') ?></button>
                </form>
            </div>
            <div class="card">
                <h3>✏️ <?= L($L,'ai_add_chunk','إضافة قطعة نصية مباشرة') ?></h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_chunk">
                    <div class="form-group">
                        <label><?= L($L,'ai_doc_id','معرف المستند') ?></label>
                        <input type="text" name="chunk_doc_id" value="doc-001-uuid">
                    </div>
                    <div class="form-group">
                        <label><?= L($L,'ai_language','اللغة') ?></label>
                        <select name="chunk_lang">
                            <option value="ar"><?= L($L,'ai_lang_ar','عربي') ?></option>
                            <option value="en"><?= L($L,'ai_lang_en','English') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= L($L,'ai_content','المحتوى') ?> *</label>
                        <textarea name="chunk_content" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-green"><?= L($L,'ai_add_chunk_btn','✏️ إضافة قطعة') ?></button>
                </form>
            </div>
        </div>
    </div>

    <!-- 3. ملفات -->
    <div class="panel" id="panel-files">
        <div class="row-2">
            <div class="card">
                <h3>📁 <?= L($L,'ai_upload_file','رفع ملف') ?></h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_file">
                    <div class="form-group">
                        <label><?= L($L,'ai_choose_file','اختر ملف (TXT, PDF, صورة)') ?></label>
                        <div class="file-input-wrap">
                            <input type="file" name="file_upload" accept=".txt,.pdf,.doc,.docx,.csv,.jpg,.jpeg,.png,.gif" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= L($L,'ai_upload','📤 رفع') ?></button>
                </form>
            </div>
            <div class="card">
                <h3>📋 <?= L($L,'ai_uploaded_files','الملفات المرفوعة') ?></h3>
                <?php if (empty($files_list)): ?>
                    <p style="color:var(--text3)"><?= L($L,'ai_no_files','لا توجد ملفات') ?></p>
                <?php else: ?>
                    <table>
                        <tr><th><?= L($L,'ai_name','الاسم') ?></th><th><?= L($L,'ai_type','النوع') ?></th><th><?= L($L,'ai_size','الحجم') ?></th><th><?= L($L,'ai_date','التاريخ') ?></th></tr>
                        <?php foreach ($files_list as $f): ?>
                            <tr>
                                <td><?= htmlspecialchars($f['filename'] ?? '') ?></td>
                                <td><?= htmlspecialchars($f['mime_type'] ?? '') ?></td>
                                <td><?= number_format(($f['file_size'] ?? 0) / 1024, 1) ?> KB</td>
                                <td style="font-size:.72rem"><?= htmlspecialchars($f['created_at'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 4. تقييمات -->
    <div class="panel" id="panel-feedback">
        <div class="row-2">
            <div class="card">
                <h3>⭐ <?= L($L,'ai_feedback_title','إرسال تقييم') ?></h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_feedback">
                    <div class="form-group">
                        <label><?= L($L,'ai_message_id','معرف الرسالة *') ?></label>
                        <input type="text" name="fb_message_id" required>
                    </div>
                    <div class="form-group">
                        <label><?= L($L,'ai_rating','التقييم * (1-5)') ?></label>
                        <select name="fb_rating" required>
                            <option value="5">⭐⭐⭐⭐⭐</option>
                            <option value="4">⭐⭐⭐⭐</option>
                            <option value="3">⭐⭐⭐</option>
                            <option value="2">⭐⭐</option>
                            <option value="1">⭐</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= L($L,'ai_comment','تعليق') ?></label>
                        <input type="text" name="fb_comment">
                    </div>
                    <button type="submit" class="btn btn-primary"><?= L($L,'ai_send_feedback','⭐ إرسال') ?></button>
                </form>
            </div>
            <div class="card">
                <h3>📊 <?= L($L,'ai_feedback_list','التقييمات') ?> (<?= L($L,'ai_avg_rating','متوسط') ?>: <?= $avg_rating ?> ⭐)</h3>
                <?php if (empty($feedbacks)): ?>
                    <p style="color:var(--text3)"><?= L($L,'ai_no_feedback','لا توجد تقييمات') ?></p>
                <?php else: ?>
                    <table>
                        <tr><th><?= L($L,'ai_message_id','الرسالة') ?></th><th><?= L($L,'ai_rating','التقييم') ?></th><th><?= L($L,'ai_comment','التعليق') ?></th><th><?= L($L,'ai_date','التاريخ') ?></th></tr>
                        <?php foreach ($feedbacks as $fb): ?>
                            <tr>
                                <td class="id-cell"><?= htmlspecialchars($fb['message_id'] ?? '') ?></td>
                                <td><span class="stars"><?= str_repeat('⭐', (int)($fb['rating'] ?? 0)) ?></span></td>
                                <td><?= htmlspecialchars($fb['comment'] ?? '-') ?></td>
                                <td style="font-size:.72rem"><?= htmlspecialchars($fb['created_at'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 5. محادثات -->
    <div class="panel" id="panel-threads">
        <div class="card">
            <h3>💬 <?= L($L,'ai_last_threads','آخر المحادثات') ?></h3>
            <?php if (empty($threads)): ?>
                <p style="color:var(--text3)"><?= L($L,'ai_no_threads','لا توجد محادثات') ?></p>
            <?php else: ?>
                <table>
                    <tr><th>ID</th><th><?= L($L,'ai_title','العنوان') ?></th><th><?= L($L,'ai_date','التاريخ') ?></th></tr>
                    <?php foreach ($threads as $t): ?>
                        <tr>
                            <td class="id-cell"><?= htmlspecialchars($t['id'] ?? '') ?></td>
                            <td><strong><?= htmlspecialchars($t['title'] ?? '-') ?></strong></td>
                            <td style="font-size:.72rem"><?= htmlspecialchars($t['created_at'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- 6. القطع النصية -->
    <div class="panel" id="panel-chunks">
        <div class="card">
            <h3>🔍 <?= L($L,'ai_tab_chunks','القطع النصية') ?> (<?= $chunks_count ?>)</h3>
            <?php if (empty($sample_chunks)): ?>
                <p style="color:var(--text3)"><?= L($L,'ai_no_chunks','لا توجد قطع') ?></p>
            <?php else: ?>
                <table>
                    <tr><th>ID</th><th><?= L($L,'ai_content','المحتوى') ?></th><th><?= L($L,'ai_language','اللغة') ?></th><th><?= L($L,'ai_words','كلمات') ?></th></tr>
                    <?php foreach ($sample_chunks as $ch): ?>
                        <tr>
                            <td class="id-cell"><?= htmlspecialchars($ch['id'] ?? '') ?></td>
                            <td class="content-cell"><?= htmlspecialchars(mb_substr($ch['content'] ?? '', 0, 100)) ?></td>
                            <td><?= htmlspecialchars($ch['language'] ?? 'ar') ?></td>
                            <td><?= htmlspecialchars((string)($ch['token_count'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="../assets/js/ai-admin.js"></script>

</body>
</html>