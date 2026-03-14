<?php
/**
 * api/routes/generate_certificate_files.php
 *
 * POST /api/generate_certificate_files
 * Body: { "issued_id": <int>, "request_id": <int>, "lang": "<string>" }
 *
 * 1. Downloads QR PNG from qrserver.com and saves it under /uploads/certificates/qr/
 * 2. Generates PDF via dompdf (CertificatePdfHelper) and saves under /uploads/certificates/pdf/
 * 3. Updates certificates_issued with qr_code_path and pdf_path.
 *
 * Returns JSON with { qr_code_path, pdf_path }.
 */
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/CertificatePdfHelper.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

$sessionUser = $_SESSION['user'] ?? [];
if (!isset($sessionUser['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $raw   = file_get_contents('php://input');
    $input = $raw ? (json_decode($raw, true) ?? []) : [];

    $issuedId  = isset($input['issued_id'])  && is_numeric($input['issued_id'])  ? (int)$input['issued_id']  : null;
    $requestId = isset($input['request_id']) && is_numeric($input['request_id']) ? (int)$input['request_id'] : null;
    $lang      = isset($input['lang'])       ? trim($input['lang'])              : 'ar';

    if (!$issuedId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'issued_id required'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Fetch issued record
    $stmt = $pdo->prepare("SELECT * FROM certificates_issued WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $issuedId]);
    $issued = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$issued) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Issued record not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $verificationCode = $issued['verification_code'] ?? '';
    $reqId = $requestId ?? 0;

    $docRoot  = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname($baseDir), '/');

    // ── Build verification URL ────────────────────────────────────────────
    $scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $verifyUrl = $scheme . '://' . $host . '/api/verify_certificate?code=' . rawurlencode($verificationCode);

    // ── 1. Download and save QR PNG ───────────────────────────────────────
    $qrDir = $docRoot . '/uploads/certificates/qr';
    if (!is_dir($qrDir)) {
        @mkdir($qrDir, 0755, true);
    }

    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?'
           . http_build_query(['data' => $verifyUrl, 'size' => '200x200', 'format' => 'png']);

    $qrCodePath = '';
    $ctx = stream_context_create([
        'http' => ['timeout' => 10, 'ignore_errors' => true, 'user_agent' => 'QooqzCertificates/1.0'],
    ]);
    $qrPng = @file_get_contents($qrUrl, false, $ctx);

    if ($qrPng !== false && strlen($qrPng) > 10) {
        $qrFileName = 'qr_' . $issuedId . '_' . time() . '.png';
        $qrFullPath = $qrDir . '/' . $qrFileName;
        if (file_put_contents($qrFullPath, $qrPng) !== false) {
            $qrCodePath = '/uploads/certificates/qr/' . $qrFileName;
        }
    }

    // Fall back to dynamic endpoint if download/save failed
    if ($qrCodePath === '') {
        $qrCodePath = '/api/generate_qr?code=' . rawurlencode($verificationCode);
    }

    // ── 2. Generate and save PDF via dompdf ───────────────────────────────
    $pdfDir = $docRoot . '/uploads/certificates/pdf';
    if (!is_dir($pdfDir)) {
        @mkdir($pdfDir, 0755, true);
    }
    $pdfFileName = 'cert_' . (int)$reqId . '_' . $issuedId . '.pdf';
    $pdfFullPath = $pdfDir . '/' . $pdfFileName;
    $pdfWebPath  = '/uploads/certificates/pdf/' . $pdfFileName;

    $pdfPath = '';
    $pdfError = '';
    if (!file_exists($pdfFullPath)) {
        $result = CertificatePdfHelper::generate([
            'pdo'             => $pdo,
            'issued_id'       => $issuedId,
            'request_id'      => $reqId,
            'lang'            => $lang,
            'qr_saved_path'   => $qrCodePath,
            'pdf_output_path' => $pdfFullPath,
            'doc_root'        => $docRoot,
        ]);
        if ($result !== '') {
            $pdfPath = $pdfWebPath;
        } else {
            $pdfError = 'PDF generation failed — see server error_log for details';
        }
    } else {
        $pdfPath = $pdfWebPath;
    }

    // If PDF generation failed, fall back to print view URL
    if ($pdfPath === '') {
        $pdfPath = '/api/print_certificate?id=' . (int)$reqId . '&lang=' . rawurlencode($lang);
    }

    // ── 3. Persist changes to certificates_issued ────────────────────────
    $update = $pdo->prepare(
        "UPDATE certificates_issued SET qr_code_path = :qr, pdf_path = :pdf WHERE id = :id"
    );
    $update->execute([
        ':qr'  => $qrCodePath,
        ':pdf' => $pdfPath,
        ':id'  => $issuedId,
    ]);

    $responseData = [
        'issued_id'    => $issuedId,
        'qr_code_path' => $qrCodePath,
        'pdf_path'     => $pdfPath,
    ];
    if ($pdfError !== '') {
        $responseData['pdf_error'] = $pdfError;
    }

    echo json_encode([
        'success' => true,
        'message' => 'OK',
        'data'    => $responseData,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
