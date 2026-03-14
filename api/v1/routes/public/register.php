<?php
declare(strict_types=1);
/**
 * Public API sub-route: register
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'register') {
    $regSub     = strtolower($segments[1] ?? '');
    $regUser    = $_SESSION['user'] ?? null;
    $regUserId  = isset($regUser['id']) ? (int)$regUser['id'] : (int)($_SESSION['user_id'] ?? 0);

    if (!$regUserId) {
        ResponseFormatter::error('Login required', 401); exit;
    }

    if ($regSub === 'entity' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $storeName   = trim($_POST['store_name']   ?? '');
        $slug        = trim($_POST['slug']         ?? '');
        $phone       = trim($_POST['phone']        ?? '');
        $email       = trim($_POST['email']        ?? '');
        $vendorType  = trim($_POST['vendor_type']  ?? 'product_seller');
        $storeType   = trim($_POST['store_type']   ?? 'individual');
        $websiteUrl  = trim($_POST['website_url']  ?? '');

        if (!$storeName || !$phone || !$email) {
            ResponseFormatter::error('store_name, phone and email are required', 422); exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ResponseFormatter::error('Invalid email address', 422); exit;
        }
        // Normalise slug: lowercase, replace spaces with hyphens, strip non-alnum
        if (!$slug) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $storeName));
        }
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
        $slug = trim($slug, '-');

        // Validate vendor_type and store_type enums
        $allowedVendorTypes = ['product_seller', 'service_provider', 'both'];
        $allowedStoreTypes  = ['individual', 'company', 'brand'];
        if (!in_array($vendorType, $allowedVendorTypes, true)) $vendorType = 'product_seller';
        if (!in_array($storeType, $allowedStoreTypes, true)) $storeType = 'individual';

        if (!$pdo) { ResponseFormatter::error('Database unavailable', 503); exit; }
        try {
            // Check slug uniqueness
            $existing = $pdoOne('SELECT id FROM entities WHERE slug = ? LIMIT 1', [$slug]);
            if ($existing) {
                $slug = $slug . '-' . substr(md5(uniqid()), 0, 6);
            }
            $regTenantId = (int)($_POST['tenant_id'] ?? 0)
                ?: ($tenantId ?? (int)($_SESSION['pub_tenant_id'] ?? $_SESSION['tenant_id'] ?? 1));
            $st = $pdo->prepare(
                'INSERT INTO entities
                    (parent_id, tenant_id, user_id, store_name, slug, vendor_type, store_type,
                     phone, email, website_url, status, is_verified, joined_at, created_at, updated_at)
                 VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", 0, NOW(), NOW(), NOW())'
            );
            $st->execute([
                $regTenantId, $regUserId, $storeName, $slug,
                $vendorType, $storeType, $phone, $email,
                $websiteUrl ?: null,
            ]);
            $newEntityId = (int)$pdo->lastInsertId();
            ResponseFormatter::success(['ok' => true, 'id' => $newEntityId, 'slug' => $slug, 'status' => 'pending'],
                'Application submitted — pending review', 201);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Registration failed: ' . $ex->getMessage(), 500);
        }
        exit;
    }

    if ($regSub === 'tenant' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $tName   = trim($_POST['name']   ?? '');
        $tDomain = trim($_POST['domain'] ?? '') ?: null;

        if (!$tName) { ResponseFormatter::error('name is required', 422); exit; }
        if (!$pdo) { ResponseFormatter::error('Database unavailable', 503); exit; }
        try {
            if ($tDomain) {
                $existing = $pdoOne('SELECT id FROM tenants WHERE domain = ? LIMIT 1', [$tDomain]);
                if ($existing) { ResponseFormatter::error('Domain already in use', 409); exit; }
            }
            $st = $pdo->prepare(
                'INSERT INTO tenants (name, domain, owner_user_id, status, created_at)
                 VALUES (?, ?, ?, "suspended", NOW())'
            );
            $st->execute([$tName, $tDomain, $regUserId]);
            $newTenantId = (int)$pdo->lastInsertId();
            // Link the user as owner in tenant_users
            try {
                $pdo->prepare(
                    'INSERT INTO tenant_users (tenant_id, user_id, role_id, is_active, joined_at)
                     VALUES (?, ?, 1, 1, NOW())'
                )->execute([$newTenantId, $regUserId]);
            } catch (Throwable $_) { /* tenant_users is optional */ }
            ResponseFormatter::success(['ok' => true, 'id' => $newTenantId], 'Tenant created', 201);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Tenant creation failed: ' . $ex->getMessage(), 500);
        }
        exit;
    }

    ResponseFormatter::notFound('Unknown register endpoint');
    exit;
}

// ── Wishlist ─────────────────────────────────────────────────────────────────
