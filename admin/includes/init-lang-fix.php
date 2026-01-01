<?php
// بعد استكمال تحميل/إعادة بناء $_SESSION['user'] في init.php:
$preferred_lang = $_SESSION['preferred_language'] ?? ($_SESSION['user']['preferred_language'] ?? ($ui_strings['lang'] ?? 'en'));
$preferred_lang = is_string($preferred_lang) ? $preferred_lang : 'en';

// اتجاه الصفحة
$html_direction = $_SESSION['html_direction'] ?? ($ui_strings['direction'] ?? (($preferred_lang === 'ar' || $preferred_lang === 'fa' || $preferred_lang === 'he') ? 'rtl' : 'ltr'));

// ضع بالقيم في $ui_strings كي تنتقل للعميل أيضاً
$ui_strings['lang'] = $preferred_lang;
$ui_strings['direction'] = $html_direction;

// تأكد من وجود csrf_token
if (empty($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
    catch (Throwable $e) { $_SESSION['csrf_token'] = substr(md5(uniqid('', true)), 0, 32); }
}
$csrf_token = $_SESSION['csrf_token'];