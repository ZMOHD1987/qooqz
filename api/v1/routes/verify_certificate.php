<?php
/**
 * api/routes/verify_certificate.php
 *
 * GET /api/verify_certificate?code=VERIFICATION_CODE
 *
 * PUBLIC endpoint — no session auth required (accessed by QR code scan).
 *
 * Behaviour:
 *  - Looks up the certificate by verification_code.
 *  - If the certificate has a saved PDF → sends it as a direct file download.
 *  - If the PDF is not yet saved → renders a minimal HTML verification page.
 */
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$code = isset($_GET['code']) ? trim($_GET['code']) : '';

if ($code === '') {
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    die('<h1>Invalid verification code.</h1>');
}

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    die('<h1>Server error.</h1>');
}

try {
    // ── Fetch the issued certificate by verification code ─────────────────
    $stmt = $pdo->prepare("
        SELECT ci.*,
               cv.request_id,
               cr.importer_name,
               e.store_name AS exporter_name,
               cr.issue_date
        FROM certificates_issued ci
        INNER JOIN certificates_versions cv ON ci.version_id = cv.id
        INNER JOIN certificates_requests cr ON cv.request_id = cr.id
        LEFT JOIN entities e ON e.id = cr.entity_id
        WHERE ci.verification_code = :code
        LIMIT 1
    ");
    $stmt->execute([':code' => $code]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cert) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        _vc_render_not_found($code);
        exit;
    }

    if (!empty($cert['is_cancelled'])) {
        header('Content-Type: text/html; charset=utf-8');
        _vc_render_cancelled($cert);
        exit;
    }

    // ── Check printable_until ─────────────────────────────────────────────
    $expired = false;
    if (!empty($cert['printable_until'])) {
        $until = strtotime($cert['printable_until']);
        if ($until !== false && $until < time()) {
            $expired = true;
        }
    }

    // ── Serve the saved PDF if it exists ──────────────────────────────────
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname($baseDir), '/');

    if (!empty($cert['pdf_path'])) {
        $pdfFullPath = $docRoot . $cert['pdf_path'];
        if (file_exists($pdfFullPath) && !$expired) {
            $filename = 'certificate_' . preg_replace('/[^A-Za-z0-9\-_]/', '_', $cert['certificate_number']) . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($pdfFullPath));
            header('Cache-Control: private, no-cache');
            readfile($pdfFullPath);
            exit;
        }
    }

    // ── No PDF saved yet — show a verification info page ──────────────────
    header('Content-Type: text/html; charset=utf-8');
    _vc_render_info_page($cert, $expired);

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
}

/* ── HTML Renderers ───────────────────────────────────────────────────────── */

function _vc_render_not_found(string $code): void
{
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Certificate Not Found</title>
<style>body{font-family:Arial,sans-serif;max-width:600px;margin:60px auto;text-align:center;}
.icon{font-size:64px;}.msg{color:#e74c3c;}</style></head><body>
<div class="icon">❌</div>
<h1 class="msg">Certificate Not Found</h1>
<p>Verification code: <code>' . htmlspecialchars($code) . '</code></p>
<p>This certificate could not be verified in our system.</p>
</body></html>';
}

function _vc_render_cancelled(array $cert): void
{
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Certificate Cancelled</title>
<style>body{font-family:Arial,sans-serif;max-width:600px;margin:60px auto;text-align:center;}
.icon{font-size:64px;}.msg{color:#e67e22;}</style></head><body>
<div class="icon">⚠️</div>
<h1 class="msg">Certificate Cancelled</h1>
<p>Certificate No: <strong>' . htmlspecialchars($cert['certificate_number'] ?? '') . '</strong></p>
<p>This certificate has been cancelled.</p>
</body></html>';
}

function _vc_render_info_page(array $cert, bool $expired): void
{
    $statusLabel = $expired ? '⚠️ Expired' : '✅ Valid';
    $statusColor = $expired ? '#e67e22' : '#27ae60';
    $lang        = $cert['language_code'] ?? 'ar';
    $requestId   = $cert['request_id'] ?? null;
    $printLink   = $requestId ? '/api/print_certificate?id=' . (int)$requestId . '&lang=' . rawurlencode($lang) : '#';

    echo '<!DOCTYPE html>
<html lang="' . htmlspecialchars($lang) . '" dir="' . ($lang === 'ar' ? 'rtl' : 'ltr') . '">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Certificate Verification</title>
<style>
body{font-family:Arial,sans-serif;max-width:640px;margin:40px auto;padding:0 16px;background:#f5f5f5;}
.card{background:#fff;border-radius:8px;padding:32px;box-shadow:0 2px 8px rgba(0,0,0,.1);}
.icon{font-size:56px;text-align:center;margin-bottom:16px;}
h1{text-align:center;color:#333;font-size:22px;}
.status{text-align:center;font-size:18px;font-weight:bold;color:' . $statusColor . ';margin:12px 0 24px;}
table{width:100%;border-collapse:collapse;}
td{padding:8px 12px;border-bottom:1px solid #eee;}
td:first-child{color:#888;width:40%;}
.btn{display:block;text-align:center;background:#2c7be5;color:#fff;padding:12px;
     border-radius:6px;text-decoration:none;font-size:15px;margin-top:24px;}
.btn:hover{background:#1a5cbf;}
</style>
</head>
<body>
<div class="card">
  <div class="icon">🏅</div>
  <h1>Certificate Verification</h1>
  <div class="status">' . $statusLabel . '</div>
  <table>
    <tr><td>Certificate No.</td><td><strong>' . htmlspecialchars($cert['certificate_number'] ?? '') . '</strong></td></tr>
    <tr><td>Issued At</td><td>' . htmlspecialchars($cert['issued_at'] ?? '') . '</td></tr>
    <!-- Printable Until -->
    <tr><td>Printable Until</td><td>' . htmlspecialchars($cert['printable_until'] ?? '') . '</td></tr>
    <tr><td>Exporter</td><td>' . htmlspecialchars($cert['exporter_name'] ?? '') . '</td></tr>
    <tr><td>Importer</td><td>' . htmlspecialchars($cert['importer_name'] ?? '') . '</td></tr>
  </table>';

    if (!$expired && $requestId) {
        echo '<a class="btn" href="' . htmlspecialchars($printLink) . '" target="_blank">📄 View / Print Certificate</a>';
    }

    echo '</div></body></html>';
}
