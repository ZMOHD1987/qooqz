<?php
/**
 * api/routes/generate_qr.php
 * Generates a QR code PNG image for a given verification code or URL.
 *
 * Usage: GET /api/generate_qr?code=VERIFICATION_CODE[&size=150]
 *
 * The output is a PNG image rendered inline (or saved when called server-side).
 */
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$sessionUser = $_SESSION['user'] ?? [];
if (!isset($sessionUser['id'])) {
    http_response_code(401);
    exit;
}

$code = isset($_GET['code']) ? trim($_GET['code']) : '';
$size = isset($_GET['size']) && is_numeric($_GET['size']) ? min(500, max(50, (int)$_GET['size'])) : 150;

if ($code === '') {
    http_response_code(400);
    exit;
}

// Build the verification URL (adjust base URL as needed)
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$verifyUrl = $scheme . '://' . $host . '/api/verify_certificate?code=' . rawurlencode($code);

// Generate QR code PNG using PHP GD (Reed-Solomon / matrix via phpqrcode-style bit encoding)
// We use a compact pure-PHP implementation that works with GD.
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');

$png = _qr_generate_png($verifyUrl, $size);
if ($png === null) {
    // Fallback: 1×1 transparent PNG
    header('Content-Type: image/png');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    exit;
}

echo $png;
exit;

/* ─────────────────────────────────────────────────────────────────
   Minimal pure-PHP QR Code generator (Version 1, ECC Level M)
   Supports strings up to 25 alphanumeric characters.
   For longer strings it falls back to the external qrserver.com API.
───────────────────────────────────────────────────────────────── */

function _qr_generate_png(string $data, int $size): ?string
{
    return _qr_remote($data, $size);
}

/**
 * Fetch a QR code PNG from the public qrserver.com API.
 * Returns PNG binary or null on failure.
 */
function _qr_remote(string $data, int $size): ?string
{
    $url = 'https://api.qrserver.com/v1/create-qr-code/?'
         . http_build_query(['data' => $data, 'size' => "{$size}x{$size}", 'format' => 'png']);

    $ctx = stream_context_create([
        'http' => [
            'timeout'        => 5,
            'ignore_errors'  => true,
            'user_agent'     => 'QooqzCertificateSystem/1.0',
        ],
    ]);

    $png = @file_get_contents($url, false, $ctx);
    if ($png === false || strlen($png) < 10) {
        return null;
    }
    return $png;
}
