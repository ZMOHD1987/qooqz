<?php
declare(strict_types=1);
/**
 * Public API sub-route: job_applications
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'job_applications') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ResponseFormatter::error('Method not allowed', 405);
        exit;
    }
    if (!$pdo instanceof PDO) { ResponseFormatter::error('Database unavailable', 503); exit; }

    $raw  = file_get_contents('php://input');
    $body = ($raw && str_starts_with(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json'))
          ? (json_decode($raw, true) ?? []) : $_POST;

    $jobId        = isset($body['job_id'])  && is_numeric($body['job_id'])  ? (int)$body['job_id']  : 0;
    $fullName     = trim((string)($body['full_name']     ?? $body['name']    ?? ''));
    $email        = trim((string)($body['email']         ?? ''));
    $phone        = trim((string)($body['phone']         ?? ''));
    $coverLetter  = trim((string)($body['cover_letter']  ?? ''));
    $cvFileUrl    = trim((string)($body['cv_file_url']   ?? ''));
    $portfolioUrl = trim((string)($body['portfolio_url'] ?? ''));
    $linkedinUrl  = trim((string)($body['linkedin_url']  ?? ''));

    // Require authenticated user (check both session formats)
    $jobSessUserId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
    if (!$jobSessUserId) {
        ResponseFormatter::error('Login required to apply for a job', 401);
        exit;
    }

    if (!$jobId || !$fullName || !$email) {
        ResponseFormatter::error('job_id, full_name and email are required', 422);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ResponseFormatter::error('Invalid email address', 422);
        exit;
    }

    // Verify job exists and is open
    $jobRow = $pdoOne(
        "SELECT id FROM jobs WHERE id = ? AND status NOT IN ('cancelled','filled','closed') LIMIT 1",
        [$jobId]
    );
    if (!$jobRow) { ResponseFormatter::notFound('Job not found or no longer accepting applications'); exit; }

    try {
        $st = $pdo->prepare(
            "INSERT INTO job_applications
               (job_id, user_id, full_name, email, phone,
                cover_letter, portfolio_url, linkedin_url, cv_file_url,
                status, ip_address)
             VALUES (?, ?, ?, ?, ?,
                     ?, ?, ?, ?,
                     'submitted', ?)"
        );
        $st->execute([
            $jobId, $jobSessUserId,
            $fullName, $email, $phone,
            $coverLetter, $portfolioUrl, $linkedinUrl, $cvFileUrl,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        $appId = (int)$pdo->lastInsertId();
        ResponseFormatter::success(['ok' => true, 'id' => $appId], 'Application submitted', 201);
    } catch (Throwable $ex) {
        ResponseFormatter::error('Application submission failed', 500);
    }
    exit;
}

/* -------------------------------------------------------
 * Route: User Addresses (requires login)
 * GET  /api/public/addresses           — list user's addresses
 * POST /api/public/addresses           — add new address
 * DELETE /api/public/addresses/{id}    — delete address
 * ----------------------------------------------------- */
