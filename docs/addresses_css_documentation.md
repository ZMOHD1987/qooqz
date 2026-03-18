# Addresses Page — CSS Documentation

**File**: `admin/assets/css/pages/addresses.css`  
**Fragment**: `admin/fragments/addresses.php`  
**Page container ID**: `#addressesPage`  
**Version**: 2.0 · **Last updated**: 2026-03

---

## 1. Design Philosophy

Every colour, font, and spacing token in this stylesheet is driven by the
database theme system. Values are resolved at runtime by
`admin/includes/theme_injector.php`, which reads from the `design_settings`,
`color_settings`, and `font_settings` tables and emits a `<style id="theme-vars">:root { … }</style>`
block before the page CSS is linked.

**No hard-coded hex colour values appear in this file.** All colour
declarations reference CSS variables. Fallbacks (e.g. `var(--border-color)`)
rely on the default palette pre-set in `theme_injector.php`; they exist only
for environments where the theme loader is unavailable.

---

## 2. CSS Variable Reference

The following CSS variables are consumed by this stylesheet. All are provided
by the DB theme loader:

| CSS Variable | Source DB column | Purpose |
|---|---|---|
| `--primary-color` | `color_settings.primary_color` | Action buttons, focused borders, active pagination |
| `--primary-hover` | `color_settings.primary_hover` | Hover state of primary buttons |
| `--secondary-color` | `color_settings.secondary_color` | Secondary buttons |
| `--danger-color` | `color_settings.danger_color` | Delete button, validation errors |
| `--success-color` | `color_settings.success_color` | Active/primary badge |
| `--warning-color` | `color_settings.warning_color` | Warning badges |
| `--background-secondary` | `color_settings.background_secondary` | Page header panel background |
| `--card-bg` | `color_settings.card_bg` | Card surfaces |
| `--input-bg` | `color_settings.input_bg` | Form input background |
| `--thead-bg` | `color_settings.thead_bg` | Table header row background |
| `--text-primary` | `color_settings.text_primary` | Body text |
| `--text-secondary` | `color_settings.text_secondary` | Labels, muted text |
| `--text-on-primary` | _(fallback: `#fff`)_ | Text colour on primary-colour backgrounds |
| `--border-color` | `color_settings.border_color` | Borders and dividers |
| `--body-font-family` | `font_settings` (body category) | All text in the page |
| `--border-radius` | `design_settings.border_radius` | Rounded corners — cards, inputs, buttons |

---

## 3. Scoping

All rules are scoped under the `#addressesPage` selector, which is the
`id` of the outermost `<div>` rendered by `addresses.php`:

```html
<div class="page-container" id="addressesPage" dir="…">
  …
</div>
```

This prevents any style bleeding into the admin sidebar, header, or
other page fragments loaded on the same DOM.

---

## 4. Transparent Colour Variants

Wherever a semi-transparent version of a theme colour is needed, this file
uses the CSS `color-mix()` function rather than hard-coded `rgba()` values:

```css
/* Correct — DB-aware */
background: color-mix(in srgb, var(--primary-color) 10%, transparent);

/* Wrong — hard-coded, ignores DB theme */
background: rgba(59, 130, 246, 0.1);
```

### Transparent layers used

| Element | Mix formula |
|---|---|
| Translation panel header bg | `color-mix(in srgb, var(--primary-color) 10%, transparent)` |
| Translation section bg | `color-mix(in srgb, var(--primary-color) 4%, transparent)` |
| Translation section border | `color-mix(in srgb, var(--primary-color) 14%, transparent)` |
| Super-admin notice bg | `color-mix(in srgb, var(--primary-color) 12%, transparent)` |
| Super-admin notice border | `color-mix(in srgb, var(--primary-color) 40%, transparent)` |
| Active badge bg | `color-mix(in srgb, var(--success-color) 14%, transparent)` |
| Inactive badge bg | `color-mix(in srgb, var(--danger-color) 14%, transparent)` |
| Table row hover bg | `color-mix(in srgb, var(--text-primary) 3%, transparent)` |

---

## 5. Animation Names

To avoid collision with global keyframe names, all `@keyframes` in this file
are prefixed with `addr-`:

| Keyframe | Purpose |
|---|---|
| `addr-spin` | Loading spinner rotation |
| `addr-fade-in` | Results count fade-in on load |

---

## 6. RTL Support

The stylesheet provides full right-to-left layout support using the
`[dir="rtl"]` attribute selector combined with the `#addressesPage` scope:

```css
[dir="rtl"] #addressesPage .page-header { flex-direction: row-reverse; }
```

The `dir` attribute is set on `#addressesPage` itself by the PHP fragment
from the user's language preference:

```php
<div class="page-container" id="addressesPage" dir="<?= htmlspecialchars($dir) ?>">
```

---

## 7. Responsive Breakpoints

| Breakpoint | Width | Key changes |
|---|---|---|
| Tablet | ≤ 1024 px | Reduced padding, narrower grid columns |
| Mobile | ≤ 768 px | Single-column forms, stacked header/buttons |
| Small phone | ≤ 480 px | Minimal padding, reduced font sizes |
| Print | — | Hides form, buttons, and table action controls |

---

## 8. Key CSS Classes

| Class | Selector scope | Purpose |
|---|---|---|
| `.page-header` | `#addressesPage .page-header` | Top header with title + Add button |
| `.page-title` | `#addressesPage .page-title` | `<h1>` title |
| `.page-subtitle` | `#addressesPage .page-subtitle` | Subtitle paragraph |
| `.card` | `#addressesPage .card` | White/dark card container |
| `.card-header` | `#addressesPage .card-header` | Card top bar with title + close |
| `.card-body` | `#addressesPage .card-body` | Card content padding |
| `.form-row` | `#addressesPage .form-row` | Auto-fit responsive grid row |
| `.form-group` | `#addressesPage .form-group` | Label + input vertical stack |
| `.form-actions` | `#addressesPage .form-actions` | Save / Delete button row |
| `.filters-grid` | `#addressesPage .filters-grid` | Filter controls grid |
| `.data-table` | `#addressesPage .data-table` | Full-width border-collapse table |
| `.table-actions` | `#addressesPage .table-actions` | Per-row Edit/Delete flex row |
| `.badge-active` | `#addressesPage .badge-active` | Green "Primary" badge |
| `.badge-inactive` | `#addressesPage .badge-inactive` | Red "Not primary" badge |
| `.loading-state` | `#addressesPage .loading-state` | Spinner + message |
| `.empty-state` | `#addressesPage .empty-state` | No-results message |
| `.pagination-wrapper` | `#addressesPage .pagination-wrapper` | Pagination bar container |
| `.page-btn` | `#addressesPage .page-btn` | Individual pagination button |
| `.super-admin-notice` | `#addressesPage .super-admin-notice` | Themed info box for super-admin mode |
| `.translation-panel` | `#addressesPage .translation-panel` | Collapsible translation card |
| `.translations-section` | `#addressesPage .translations-section` | Tinted translations wrapper |

---

## 9. Related Files

| File | Purpose |
|---|---|
| `admin/fragments/addresses.php` | PHP template that renders `#addressesPage` |
| `admin/assets/js/pages/addresses.js` | JS module (`window.Addresses`) |
| `admin/includes/theme_injector.php` | Emits `:root { --primary-color: …; }` from DB |
| `api/v1/routes/addresses.php` | REST API handler |
| `api/v1/models/addresses/` | Repository / Service / Validator / Controller |
| `docs/TENANTS_ARCHITECTURE.md` | Tenant ↔ entity ↔ address data model |
