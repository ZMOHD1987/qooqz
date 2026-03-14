<?php
/**
 * api/routes/print_certificate.php
 * Professional print view for certificates (returns HTML with PDF/QR support)
 */
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/helpers/CertificatePdfHelper.php';
// Session check
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: text/html; charset=utf-8');

$sessionUser = $_SESSION['user'] ?? [];
if (!isset($sessionUser['id'])) {
    http_response_code(401);
    die("Unauthorized access.");
}

$id   = isset($_GET['id'])   && is_numeric($_GET['id'])   ? (int)$_GET['id']   : null;
$lang = isset($_GET['lang']) ? $_GET['lang']              : 'ar';

if (!$id) {
    http_response_code(400);
    die("Invalid request ID.");
}

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    http_response_code(500);
    die("Database error.");
}

try {
    // Fetch Request Data (including issued record fields)
    $sql = "
        SELECT cr.*,
               c_imp.name AS importer_country,
               e.store_name AS exporter_name,
               ce.certificate_version,
               ce.scope,
               ci.certificate_number,
               ci.issued_at,
               ci.verification_code,
               ci.qr_code_path,
               ci.pdf_path,
               mo.name     AS official_name,
               mo.position AS official_position
        FROM certificates_requests cr
        LEFT JOIN countries c_imp ON c_imp.id = cr.importer_country_id
        LEFT JOIN entities e ON e.id = cr.entity_id
        LEFT JOIN certificate_editions ce ON ce.id = cr.certificate_edition_id
        LEFT JOIN certificates_issued ci ON ci.id = cr.issued_id
        LEFT JOIN certificates_versions cv ON cv.id = ci.version_id
        LEFT JOIN municipality_officials mo ON mo.id = cv.municipality_official_id
        WHERE cr.id = :id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        http_response_code(404);
        die("Request not found.");
    }

    // Fetch Items with details & translations
    require_once API_VERSION_PATH . '/models/certificates/repositories/Pdocertificatesrequestitemsrepository.php';
    $itemRepo = new PdoCertificatesRequestItemsRepository($pdo);
    $items = $itemRepo->getItemsWithDetails($id, $lang);

    // Determine Template version
    $version = $data['certificate_version'] ?? null;
    if (!$version && isset($data['scope'])) {
        $version = $lang . '_' . $data['scope'];
    }
    if (!$version) $version = 'ar_gcc'; // Hard fallback

    // Load certificate template configuration from DB
    require_once API_VERSION_PATH . '/models/certificates/repositories/PdoCertificatesTemplatesRepository.php';
    $templateRepo = new PdoCertificatesTemplatesRepository($pdo);
    $template = $templateRepo->findByCode($version);

    // Default template values if not found in DB
    if (!$template) {
        $template = [
            'font_family'      => 'Arial',
            'font_size'        => '12.00',
            'background_image' => null,
            'table_start_x'    => '10.00',
            'table_start_y'    => '50.00',
            'table_row_height' => '12.00',
            'table_max_rows'   => 12,
            'qr_x'             => '180.00',
            'qr_y'             => '250.00',
            'qr_width'         => '50.00',
            'qr_height'        => '50.00',
            'signature_x'      => '100.00',
            'signature_y'      => '250.00',
            'signature_width'  => '50.00',
            'signature_height' => '50.00',
            'stamp_x'          => '150.00',
            'stamp_y'          => '250.00',
            'stamp_width'      => '50.00',
            'stamp_height'     => '50.00',
            'logo_x'           => '10.00',
            'logo_y'           => '10.00',
            'logo_width'       => '50.00',
            'logo_height'      => '50.00',
        ];
    }

    // Normalize background image path
    // DB may store 'admin/templates/...' but actual files live under 'admin/assets/templates/...'
    if (!empty($template['background_image'])) {
        $adminDir    = dirname($baseDir) . '/admin';
        $bgRelative  = $template['background_image'];
        $bgFullPath  = $_SERVER['DOCUMENT_ROOT'] . '/' . $bgRelative;
        if (!file_exists($bgFullPath)) {
            // Try the assets sub-path
            $bgAlt = str_replace('admin/templates/', 'admin/assets/templates/', $bgRelative);
            $bgAltFull = $_SERVER['DOCUMENT_ROOT'] . '/' . $bgAlt;
            if (file_exists($bgAltFull)) {
                $template['background_image'] = $bgAlt;
            }
        }
    }

    // QR code path: use stored path, or build a dynamic URL via the generate_qr endpoint
    $verificationCode = $data['verification_code'] ?? '';
    $qrPath = '';
    if (!empty($data['qr_code_path'])) {
        $qrPath = $data['qr_code_path'];
    } elseif ($verificationCode !== '') {
        $qrPath = '/api/generate_qr?code=' . rawurlencode($verificationCode);
    }

    // Stamp and signature — loaded from private storage as data URIs
    $signaturePath = CertificatePdfHelper::assetDataUri('signature');
    $stampPath     = CertificatePdfHelper::assetDataUri('stamp');

    // Labels for this language
    $labels = CertificatePdfHelper::labels($lang);

    // Official info
    $officialName     = $data['official_name']     ?? '';
    $officialPosition = $data['official_position'] ?? '';

    $adminDir     = dirname($baseDir) . '/admin';
    $templatePath = $adminDir . "/assets/templates/certificates/{$version}.php";

    if (!file_exists($templatePath)) {
        // Attempt fallback to ar_gcc
        $templatePath = $adminDir . "/assets/templates/certificates/ar_gcc.php";
        if (!file_exists($templatePath)) {
            die("Template not found: {$version}");
        }
    }

    // Render Template
    // Variables available inside template:
    //   $data, $items, $template, $qrPath, $signaturePath, $stampPath
    //   $labels, $officialName, $officialPosition, $lang
    include $templatePath;

} catch (Throwable $e) {
    http_response_code(500);
    echo "<h1>Error Generating Certificate</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    if (ENVIRONMENT === 'development') {
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
}
