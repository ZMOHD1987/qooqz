<?php
// htdocs/admin/includes/footer.php
// Admin footer include — closes main content and layout, shows footer, loads scripts.
// Expects variables from init.php/header.php: $ui_strings, $theme, $user, $preferred_lang, $_SESSION['csrf_token']
// Safe: will provide sensible defaults if variables are missing.

if (!isset($ui_strings)) {
    // try to include init.php if not already included
    $maybe = __DIR__ . '/init.php';
    if (is_readable($maybe)) require_once $maybe;
}

$ui_strings = $ui_strings ?? [
    'lang' => 'en',
    'direction' => 'ltr',
    'strings' => [
        'footer_copyright' => '© {year} QOOQZ',
        'brand' => 'QOOQZ',
        'support' => 'Support'
    ],
    'nav' => []
];

$theme = $theme ?? [];
$dir = $ui_strings['direction'] ?? ($theme['direction'] ?? 'ltr');
$year = date('Y');
$footerText = $ui_strings['strings']['footer_copyright'] ?? '© {year} QOOQZ';
$footerText = str_replace('{year}', $year, $footerText);
$supportText = $ui_strings['strings']['support'] ?? 'Support';
$brand = $ui_strings['strings']['brand'] ?? 'QOOQZ';

?>
        <!-- page content ends -->
      </div>
    </main>
  </div>

  <footer class="admin-footer" role="contentinfo" aria-label="Footer">
    <div class="container" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
      <div style="color:var(--color-muted);font-size:.95rem"><?php echo htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8'); ?></div>
      <div style="color:var(--color-muted);font-size:.95rem">
        <a href="/admin/support.php" style="color:var(--color-primary);text-decoration:none"><?php echo htmlspecialchars($supportText, ENT_QUOTES, 'UTF-8'); ?></a>
      </div>
    </div>
  </footer>

  <!-- Core admin JS -->
  <script src="/admin/assets/js/admin.js"></script>

  <!-- Ensure HTML direction is applied (useful if server rendered differently) -->
  <script>
    (function(){
      try {
        var dir = <?php echo json_encode($dir); ?> || document.documentElement.getAttribute('dir') || 'ltr';
        if (document.documentElement.getAttribute('dir') !== dir) {
          document.documentElement.setAttribute('dir', dir);
        }
        // small helper: update body class for dir
        document.body.classList.remove('rtl','ltr');
        document.body.classList.add(dir === 'rtl' ? 'rtl' : 'ltr');
      } catch(e){
        console && console.warn && console.warn('Direction script error', e);
      }
    })();
  </script>

  <!-- Optional: small accessibility helpers (focus visible outline) -->
  <style>
    /* Visible focus for keyboard users */
    :focus { outline: 3px solid rgba(26,188,156,0.18); outline-offset: 2px; }
    /* Ensure footer spacing on small screens */
    @media (max-width:600px){ .admin-footer .container { padding:10px } }
  </style>

</body>
</html>