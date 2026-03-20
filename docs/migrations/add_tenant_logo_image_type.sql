-- Migration: Add tenant_logo image type
-- Adds a dedicated image type for tenant (company/client) logos.
-- Run once; uses INSERT IGNORE to be safely re-runnable.

INSERT IGNORE INTO `image_types`
    (`id`, `code`, `name`, `description`, `width`, `height`, `crop`, `quality`, `format`, `is_thumbnail`)
VALUES
    (21, 'tenant_logo', 'Tenant Logo', 'شعار التينانت (الشركة/العميل)', 400, 400, 'fit', 85, 'webp', 0);
