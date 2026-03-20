-- =============================================================================
-- Theme System Database Migrations
-- Apply these ALTER statements to align the DB schema with AdminUiThemeLoader.
-- Run each statement individually; review UNIQUE key additions against your data
-- first (remove duplicates with the SELECT queries in the comments below).
-- =============================================================================

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Expand color_value to support rgba(), var(), linear-gradient(), etc.
--    The previous varchar(7) only fitted #RRGGBB hex codes.
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE color_settings
    MODIFY COLUMN color_value VARCHAR(200) NOT NULL;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Expand button_styles color columns for the same reason.
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE button_styles
    MODIFY COLUMN background_color       VARCHAR(200) NOT NULL,
    MODIFY COLUMN text_color             VARCHAR(200) NOT NULL,
    MODIFY COLUMN border_color           VARCHAR(200),
    MODIFY COLUMN hover_background_color VARCHAR(200),
    MODIFY COLUMN hover_text_color       VARCHAR(200),
    MODIFY COLUMN hover_border_color     VARCHAR(200);

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Expand card_styles color columns and add text_color.
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE card_styles
    MODIFY COLUMN background_color VARCHAR(200),
    MODIFY COLUMN border_color      VARCHAR(200);

-- 3b. Add text_color to card_styles (used by AdminUiThemeLoader, ui.php, and
--     public_context.php to emit --card-{slug}-text CSS variables).
--     Use ADD COLUMN IF NOT EXISTS to avoid errors on databases that already
--     have this column.
ALTER TABLE card_styles
    ADD COLUMN IF NOT EXISTS text_color VARCHAR(200) DEFAULT NULL AFTER border_color;

-- ─────────────────────────────────────────────────────────────────────────────
-- 4. Replace ENUM with VARCHAR for flexibility (no schema change needed for
--    adding new categories without an ALTER TABLE).
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE color_settings
    MODIFY COLUMN category VARCHAR(50) DEFAULT 'other';

ALTER TABLE font_settings
    MODIFY COLUMN category VARCHAR(50) DEFAULT 'other';

ALTER TABLE design_settings
    MODIFY COLUMN setting_type VARCHAR(50) DEFAULT 'text',
    MODIFY COLUMN category     VARCHAR(50) DEFAULT 'other';

ALTER TABLE card_styles
    MODIFY COLUMN hover_effect VARCHAR(50) DEFAULT 'none',
    MODIFY COLUMN text_align   VARCHAR(10) DEFAULT 'left';

-- ─────────────────────────────────────────────────────────────────────────────
-- 5. Add unique constraints to prevent duplicate keys per tenant+theme.
--
-- Before running, check for existing duplicates:
--   SELECT tenant_id, theme_id, setting_key, COUNT(*) c
--     FROM color_settings GROUP BY tenant_id, theme_id, setting_key HAVING c > 1;
--
--   SELECT tenant_id, theme_id, setting_key, COUNT(*) c
--     FROM font_settings GROUP BY tenant_id, theme_id, setting_key HAVING c > 1;
--
--   SELECT tenant_id, theme_id, slug, COUNT(*) c
--     FROM button_styles GROUP BY tenant_id, theme_id, slug HAVING c > 1;
--
--   SELECT tenant_id, theme_id, slug, COUNT(*) c
--     FROM card_styles GROUP BY tenant_id, theme_id, slug HAVING c > 1;
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE color_settings
    ADD UNIQUE KEY uq_color_tenant_theme_key (tenant_id, theme_id, setting_key);

ALTER TABLE font_settings
    ADD UNIQUE KEY uq_font_tenant_theme_key  (tenant_id, theme_id, setting_key);

ALTER TABLE button_styles
    ADD UNIQUE KEY uq_btn_tenant_theme_slug  (tenant_id, theme_id, slug);

ALTER TABLE card_styles
    ADD UNIQUE KEY uq_card_tenant_theme_slug (tenant_id, theme_id, slug);
