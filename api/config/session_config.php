<?php
// ضع هذا قبل أي استدعاء لـ session_start()
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'domain' => 'mzmz.rf.gd', // غيّره إلى اسم النطاق الصحيح
  'secure' => true,         // يتطلب HTTPS
  'httponly' => true,
  'samesite' => 'Lax'       // 'Strict' أو 'Lax' حسب حاجتك
]);
session_start();