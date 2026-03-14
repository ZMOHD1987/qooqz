<?php
/**
 * api/shared/helpers/CertificatePdfHelper.php
 *
 * Shared helper for certificate PDF generation using dompdf.
 * Replaces Chromium headless — works on any standard PHP host.
 *
 * Public API:
 *   CertificatePdfHelper::generate(array $params): string
 *     Returns the saved PDF file path on success, '' on failure.
 *
 *   CertificatePdfHelper::htmlToPdf(string $html, string $outputPath, string $lang): string
 *     Converts HTML to PDF using dompdf. Returns output path on success, '' on failure.
 *
 *   CertificatePdfHelper::labels(string $lang): array
 *     Returns bilingual label array for the given language.
 *
 *   CertificatePdfHelper::assetDataUri(string $key): string
 *     Returns data URI for a private certificate asset (stamp/signature).
 *     Returns '' if the file does not exist or is a placeholder (< 200 bytes).
 */
declare(strict_types=1);

class CertificatePdfHelper
{
    /** Absolute path to the api/ directory */
    private static string $apiDir = '';

    /** Cached labels */
    private static array $labelsCache = [];

    /** Cached assets config */
    private static ?array $assetsConfig = null;

    /** Cached Arabic font base64 CSS (keyed by font file mtime) */
    private static string $arabicFontCssCache = '';
    private static int    $arabicFontCssMtime = 0;

    /** Minimum valid font file size in bytes (50 KB) */
    private const MIN_FONT_SIZE = 51200;

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Generate a PDF from a certificate template using dompdf.
     *
     * Required keys in $params:
     *   pdo            PDO  — database connection
     *   issued_id      int  — certificates_issued.id
     *   request_id     int  — certificates_requests.id
     *   lang           string — 'ar' | 'en'
     *   qr_saved_path  string — web-relative path of the QR PNG (or '')
     *   pdf_output_path string — absolute filesystem path for the output PDF
     *   doc_root       string — absolute filesystem document root
     *
     * Returns the output file path on success, '' on failure.
     */
    public static function generate(array $params): string
    {
        /** @var PDO $pdo */
        $pdo            = $params['pdo'];
        $issuedId       = (int)($params['issued_id']      ?? 0);
        $requestId      = (int)($params['request_id']     ?? 0);
        $lang           = $params['lang']                 ?? 'ar';
        $qrSavedPath    = $params['qr_saved_path']        ?? '';
        $pdfOutputPath  = $params['pdf_output_path']      ?? '';
        $docRoot        = rtrim($params['doc_root']       ?? '', '/');

        if (!$pdo instanceof PDO || !$issuedId || !$requestId || !$pdfOutputPath) {
            return '';
        }

        try {
            // ── 1. Fetch request + joined data ────────────────────────────
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
            $stmt->execute([':id' => $requestId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) return '';

            // ── 2. Fetch items ────────────────────────────────────────────
            $itemsRepoFile = defined('API_VERSION_PATH')
                ? API_VERSION_PATH . '/models/certificates/repositories/Pdocertificatesrequestitemsrepository.php'
                : '';
            if ($itemsRepoFile && file_exists($itemsRepoFile)) {
                require_once $itemsRepoFile;
            }
            $items = [];
            if (class_exists('PdoCertificatesRequestItemsRepository', false)) {
                $itemRepo = new PdoCertificatesRequestItemsRepository($pdo);
                $items = $itemRepo->getItemsWithDetails($requestId, $lang);
            }

            // ── 3. Template version & config ──────────────────────────────
            $version = $data['certificate_version'] ?? null;
            if (!$version && isset($data['scope'])) {
                $version = $lang . '_' . $data['scope'];
            }
            if (!$version) $version = 'ar_gcc';

            $tplRepoFile = defined('API_VERSION_PATH')
                ? API_VERSION_PATH . '/models/certificates/repositories/PdoCertificatesTemplatesRepository.php'
                : '';
            $template = null;
            if ($tplRepoFile && file_exists($tplRepoFile)) {
                require_once $tplRepoFile;
                if (class_exists('PdoCertificatesTemplatesRepository', false)) {
                    $templateRepo = new PdoCertificatesTemplatesRepository($pdo);
                    $template = $templateRepo->findByCode($version);
                }
            }
            $template = $template ?? self::defaultTemplate();

            // ── 4. Background image as data URI ───────────────────────────
            if (!empty($template['background_image'])) {
                $bgRelative = $template['background_image'];
                // Normalise: try 'admin/assets/templates/...' fallback
                $bgAlt = str_replace('admin/templates/', 'admin/assets/templates/', $bgRelative);
                if (!file_exists($docRoot . '/' . $bgRelative) && file_exists($docRoot . '/' . $bgAlt)) {
                    $template['background_image'] = $bgAlt;
                }
                $bgFull = $docRoot . '/' . $template['background_image'];
                if (file_exists($bgFull)) {
                    $template['background_image_data_uri'] = self::dataUri($bgFull);
                }
            }

            // ── 5. QR code as data URI ────────────────────────────────────
            $qrPath = '';
            if ($qrSavedPath !== '') {
                $qrAbsolute = $docRoot . $qrSavedPath;
                if (file_exists($qrAbsolute)) {
                    $qrPath = self::dataUri($qrAbsolute);
                }
            }
            if ($qrPath === '') {
                $verificationCode = $data['verification_code'] ?? '';
                if ($verificationCode !== '') {
                    $scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $verifyUrl = $scheme . '://' . $host . '/api/verify_certificate?code=' . rawurlencode($verificationCode);
                    $qrApiUrl  = 'https://api.qrserver.com/v1/create-qr-code/?'
                               . http_build_query(['data' => $verifyUrl, 'size' => '200x200', 'format' => 'png']);
                    $ctx = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
                    $qrPng = @file_get_contents($qrApiUrl, false, $ctx);
                    if ($qrPng !== false && strlen($qrPng) > 10) {
                        $qrPath = 'data:image/png;base64,' . base64_encode($qrPng);
                    }
                }
            }

            // ── 6. Stamp & Signature from private storage ─────────────────
            $signaturePath = self::assetDataUri('signature');
            $stampPath     = self::assetDataUri('stamp');

            // ── 7. Labels ─────────────────────────────────────────────────
            $labels = self::labels($lang);

            // ── 8. Official info ──────────────────────────────────────────
            // May come from the join above or overridden per $lang via municipality_officials
            $officialName     = $data['official_name']     ?? '';
            $officialPosition = $data['official_position'] ?? '';

            // ── 9. Render HTML template ───────────────────────────────────
            $adminDir     = self::findAdminDir($docRoot);
            $templateFile = $adminDir . "/assets/templates/certificates/{$version}.php";
            if (!file_exists($templateFile)) {
                $templateFile = $adminDir . "/assets/templates/certificates/ar_gcc.php";
            }
            if (!file_exists($templateFile)) return '';

            if (!defined('CERT_PDF_MODE')) define('CERT_PDF_MODE', true);
            ob_start();
            include $templateFile;
            $html = ob_get_clean();
            if (!$html) return '';

            // ── 10. Convert to PDF with dompdf ────────────────────────────
            return self::htmlToPdf($html, $pdfOutputPath, $lang);

        } catch (Throwable $ex) {
            error_log('[CertificatePdfHelper] ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
            return '';
        }
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Convert an HTML string to PDF using dompdf and save it to $outputPath.
     * Returns $outputPath on success, '' on failure.
     */
    public static function htmlToPdf(string $html, string $outputPath, string $lang = 'ar'): string
    {
        // Walk up from the api/ directory looking for vendor/autoload.php.
        // This handles three common cPanel layouts:
        //   ~/public_html/api/vendor/autoload.php   (git-deployed vendor)
        //   ~/public_html/vendor/autoload.php        (composer run from public_html)
        //   ~/vendor/autoload.php                    (composer run from home dir — most common on this host)
        $autoload = null;
        $dir = self::apiDir();
        for ($i = 0; $i < 4; $i++) {
            $candidate = $dir . '/vendor/autoload.php';
            if (file_exists($candidate)) {
                $autoload = $candidate;
                break;
            }
            $parent = dirname($dir);
            if ($parent === $dir) break; // reached filesystem root
            $dir = $parent;
        }
        if ($autoload === null) {
            error_log('[CertificatePdfHelper] dompdf vendor/autoload.php not found. Searched up to 4 levels above: ' . self::apiDir());
            return '';
        }
        require_once $autoload;

        if (!class_exists('Dompdf\Dompdf')) {
            error_log('[CertificatePdfHelper] Dompdf\Dompdf class not found after loading ' . $autoload);
            return '';
        }

        // Determine dompdf vendor dir.  $vendorDir is the composer vendor root
        // (e.g. ~/vendor on this cPanel host).
        $vendorDir = dirname($autoload);

        // fontDir  — where dompdf finds TTF/OTF/AFM source files (vendor lib/fonts)
        // fontCache — where dompdf writes/reads generated .ufm metric files.
        //             Must be writable; use api/storage/fonts/ so metrics persist
        //             across requests instead of being discarded every time /tmp is cleared.
        $builtinFontDir = $vendorDir . '/dompdf/dompdf/lib/fonts';
        $fontCacheDir   = self::apiDir() . '/storage/fonts';
        if (!is_dir($fontCacheDir) && !@mkdir($fontCacheDir, 0755, true)) {
            error_log('[CertificatePdfHelper] Cannot create font cache dir: ' . $fontCacheDir);
        }

        /** @var \Dompdf\Options $options */
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        if (is_dir($builtinFontDir)) {
            // Source font files live here (DejaVuSans.ttf etc.)
            $options->set('fontDir', $builtinFontDir);
        }
        // Persistent, writable cache for generated .ufm files
        $options->set('fontCache', $fontCacheDir);
        $options->set('logOutputFile', '');

        $pdf = new \Dompdf\Dompdf($options);

        // Register Amiri font for proper Arabic letter shaping.
        // Uses dompdf's file-based registerFont() API rather than injecting a
        // huge base64 @font-face string into the HTML (which is unreliable if the
        // CDN download fails or dompdf's CSS parser chokes on a 667 KB data URI).
        if (in_array($lang, ['ar', 'ar-SA', 'ar-AE'], true)) {
            // getArabicFontPath() downloads Amiri-Regular.ttf from CDN on first use
            // and caches it in api/storage/fonts/. Returns '' if unavailable.
            $arabicFontPath = self::getArabicFontPath();
            if ($arabicFontPath !== '') {
                try {
                    $pdf->getFontMetrics()->registerFont(
                        ['family' => 'Amiri', 'weight' => 'normal', 'style' => 'normal'],
                        $arabicFontPath
                    );
                } catch (\Throwable $fontEx) {
                    error_log('[CertificatePdfHelper] Amiri registerFont: ' . $fontEx->getMessage());
                    // Falls back gracefully to DejaVu Sans which also supports Arabic Unicode
                }
            }
        }

        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $pdfContent = $pdf->output();
        if (!$pdfContent || strlen($pdfContent) < 100) {
            return '';
        }

        $outDir = dirname($outputPath);
        if (!is_dir($outDir)) {
            @mkdir($outDir, 0755, true);
        }

        if (file_put_contents($outputPath, $pdfContent) === false) {
            return '';
        }
        return $outputPath;
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return a CSS <style> block that embeds the Amiri Arabic font as a base64
     * data URI @font-face rule.  The font file is downloaded from CDN on first
     * use and cached in api/storage/fonts/ for subsequent calls.
     *
     * Returns '' if the font cannot be obtained (falls back to DejaVu Sans).
     */
    public static function arabicFontCss(): string
    {
        $fontPath = self::getArabicFontPath();
        if ($fontPath === '') {
            return '';
        }
        $mtime = (int)@filemtime($fontPath);
        // Return the in-process cache if the font file hasn't changed
        if (self::$arabicFontCssCache !== '' && self::$arabicFontCssMtime === $mtime) {
            return self::$arabicFontCssCache;
        }
        $b64 = base64_encode(file_get_contents($fontPath));
        $css = '<style>'
            . '@font-face {'
            . 'font-family:"Amiri";'
            . 'font-style:normal;font-weight:400;'
            . 'src:url("data:font/truetype;base64,' . $b64 . '") format("truetype");'
            . '}'
            . '</style>';
        self::$arabicFontCssCache = $css;
        self::$arabicFontCssMtime = $mtime;
        return $css;
    }

    /**
     * Return the local path to the cached Amiri-Regular.ttf, downloading it
     * from CDN if it is not already present.  Returns '' on failure.
     */
    public static function getArabicFontPath(): string
    {
        $fontsDir = self::apiDir() . '/storage/fonts';
        $fontPath = $fontsDir . '/Amiri-Regular.ttf';

        // Use cache if the file looks valid
        if (file_exists($fontPath) && filesize($fontPath) > self::MIN_FONT_SIZE) {
            return $fontPath;
        }

        @mkdir($fontsDir, 0755, true);

        // Try multiple CDN mirrors in order
        $cdnUrls = [
            'https://cdn.jsdelivr.net/npm/amiri-font@0.113.0/fonts/amiri-regular.ttf',
            'https://fonts.gstatic.com/s/amiri/v27/J7aRnpd8CGxBHqUpvrIw74NL.ttf',
            'https://github.com/aliftype/amiri/raw/main/Amiri-Regular.ttf',
        ];

        $ctx = stream_context_create(['http' => [
            'timeout'       => 20,
            'ignore_errors' => true,
            'user_agent'    => 'Mozilla/5.0 (compatible; CertBot/1.0)',
        ], 'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ]]);

        foreach ($cdnUrls as $url) {
            $data = @file_get_contents($url, false, $ctx);
            if ($data !== false && strlen($data) > self::MIN_FONT_SIZE) {
                if (@file_put_contents($fontPath, $data) !== false) {
                    return $fontPath;
                }
            }
        }

        error_log('[CertificatePdfHelper] Could not download Amiri font. Arabic PDF may render incorrectly.');
        return '';
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Convert a local file to a base64 data URI.
     */
    public static function dataUri(string $filePath): string
    {
        $content = @file_get_contents($filePath);
        if ($content === false || $content === '') return '';
        $ext  = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return the bilingual label array for the given language.
     * Falls back to 'ar' if the language is not found.
     */
    public static function labels(string $lang = 'ar'): array
    {
        if (isset(self::$labelsCache[$lang])) {
            return self::$labelsCache[$lang];
        }

        $file = self::apiDir() . '/shared/config/certificate_labels.json';
        if (!file_exists($file)) {
            return [];
        }

        $all = json_decode(file_get_contents($file), true) ?? [];
        $labels = $all[$lang] ?? $all['ar'] ?? [];
        self::$labelsCache[$lang] = $labels;
        return $labels;
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return a base64 data URI for a private certificate asset (e.g. 'stamp', 'signature').
     * The actual file path is read from certificate_assets.json.
     * Returns '' if the file does not exist or is a placeholder (< 200 bytes).
     */
    public static function assetDataUri(string $key): string
    {
        $config = self::assetsConfig();
        if (!isset($config[$key]['path'])) {
            return '';
        }

        $relativePath = $config[$key]['path'];
        $fullPath = self::apiDir() . '/' . $relativePath;

        if (!file_exists($fullPath)) {
            return '';
        }

        // Skip placeholder files (< 200 bytes)
        if (filesize($fullPath) < 200) {
            return '';
        }

        return self::dataUri($fullPath);
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return the absolute path to the api/ directory.
     */
    public static function apiDir(): string
    {
        if (self::$apiDir !== '') return self::$apiDir;
        // This file lives at api/shared/helpers/ → three levels up → api/
        self::$apiDir = dirname(__DIR__, 2);
        return self::$apiDir;
    }

    // ──────────────────────────────────────────────────────────────────────────

    private static function assetsConfig(): array
    {
        if (self::$assetsConfig !== null) {
            return self::$assetsConfig;
        }
        $file = self::apiDir() . '/shared/config/certificate_assets.json';
        self::$assetsConfig = file_exists($file)
            ? (json_decode(file_get_contents($file), true) ?? [])
            : [];
        return self::$assetsConfig;
    }

    private static function defaultTemplate(): array
    {
        return [
            'font_family'      => 'DejaVu Sans',
            'font_size'        => '11.00',
            'background_image' => null,
            'table_start_x'    => '10.00',
            'table_start_y'    => '100.00',
            'table_row_height' => '8.00',
            'table_max_rows'   => 12,
            'qr_x'             => '160.00',
            'qr_y'             => '240.00',
            'qr_width'         => '40.00',
            'qr_height'        => '40.00',
            'signature_x'      => '100.00',
            'signature_y'      => '245.00',
            'signature_width'  => '50.00',
            'signature_height' => '30.00',
            'stamp_x'          => '20.00',
            'stamp_y'          => '240.00',
            'stamp_width'      => '45.00',
            'stamp_height'     => '45.00',
            'logo_x'           => '10.00',
            'logo_y'           => '10.00',
            'logo_width'       => '30.00',
            'logo_height'      => '30.00',
        ];
    }

    private static function findAdminDir(string $docRoot): string
    {
        // Try: docRoot/../admin (when api is inside public/htdocs)
        $candidates = [
            dirname($docRoot) . '/admin',
            dirname(self::apiDir()) . '/admin',
            $docRoot . '/admin',
        ];
        foreach ($candidates as $dir) {
            if (is_dir($dir . '/assets/templates/certificates')) {
                return $dir;
            }
        }
        return dirname(self::apiDir()) . '/admin';
    }
}