<?php
declare(strict_types=1);
/**
 * admin/products.php
 * Standalone products page that uses the products fragment
 * with header and footer for proper admin layout
 */

// Include header (loads bootstrap, sets up admin context, and renders HTML skeleton)
require_once __DIR__ . '/includes/header.php';

// Get current user from header context
$currentUser = $ADMIN_UI_PAYLOAD['user'] ?? null;
$lang = $ADMIN_UI_PAYLOAD['lang'] ?? 'en';
?>

<meta data-page="products" 
      data-assets-js="/admin/assets/js/pages/products.js"
      data-i18n-files="/languages/admin/<?php echo rawurlencode($lang); ?>.json,/languages/Product/<?php echo rawurlencode($lang); ?>.json">

<div class="admin-content">
  <?php
  // Include the products fragment (handles all product functionality)
  $fragmentPath = __DIR__ . '/fragments/products.php';
  if (is_readable($fragmentPath)) {
      include $fragmentPath;
  } else {
      echo '<div class="container" style="padding:20px;color:#c0392b;">Products fragment not found at: ' . htmlspecialchars($fragmentPath) . '</div>';
  }
  ?>
</div>

<?php
// Include footer (closes layout, provides client-side helpers)
require_once __DIR__ . '/includes/footer.php';
?>
