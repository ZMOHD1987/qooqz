# Tenant Users Page — CSS Documentation

**File**: `admin/assets/css/pages/tenant_users.css`  
**Fragment**: `admin/fragments/tenant_users.php`  
**Page container ID**: `#tenantUsersPageContainer`  
**Version**: 2.0 · **Last updated**: 2026-03

---

## 1. Design Philosophy

Every colour, font, and spacing token in this stylesheet is driven by the
database theme system. Values are resolved at runtime by
`admin/includes/theme_injector.php`, which reads from the `design_settings`,
`color_settings`, and `font_settings` tables and emits a
`<style id="theme-vars">:root { … }</style>` block before the page CSS is linked.

**No hard-coded hex colour values appear in this file.** All colour
declarations reference CSS variables. Fallbacks rely on the default palette
pre-set in `theme_injector.php`.

---

## 2. CSS Variable Reference

The following CSS variables are consumed by this stylesheet. All are provided
by the DB theme loader:

| CSS Variable | Source DB column | Purpose |
|---|---|---|
| `--primary-color` | `color_settings.primary_color` | Action buttons, icon-btn hover, badge-info |
| `--primary-hover` | `color_settings.primary_hover` | Hover state of primary buttons |
| `--danger-color` | `color_settings.danger_color` | Destructive icon-btn hover |
| `--success-color` | `color_settings.success_color` | User-info-box user icon, alert-success |
| `--warning-color` | `color_settings.warning_color` | User-info-box entity icon, alert-warning |
| `--background-tertiary` | `color_settings.background_tertiary` | Form card header, info-box background |
| `--text-primary` | `color_settings.text_primary` | Body text, info-box strong, filter labels |
| `--text-secondary` | `color_settings.text_secondary` | Muted text, icon-btn default colour |
| `--text-on-primary` | _(fallback: `#fff`)_ | Text on coloured backgrounds |
| `--border-color` | `color_settings.border_color` | Borders, form card, icon-btn |
| `--body-font-family` | `font_settings` (body category) | Alert toast font |
| `--border-radius` | `design_settings.border_radius` | Rounded corners — info-box, icon-btn, alerts |

---

## 3. Scoping

All rules are scoped under the `#tenantUsersPageContainer` selector, which is
the `id` of the outermost `<div>` rendered by `tenant_users.php`:

```html
<div class="page-container" id="tenantUsersPageContainer" dir="…">
  …
</div>
```

This prevents any style bleeding into the admin sidebar, header, or other page
fragments that may be loaded on the same DOM (e.g. when embedded as a sub-tab
inside the tenants management page).

---

## 4. Transparent Colour Variants

Wherever a semi-transparent version of a theme colour is needed, this file uses
the CSS `color-mix()` function rather than hard-coded `rgba()` values:

```css
/* Correct — DB-aware */
background: color-mix(in srgb, var(--success-color) 20%, transparent);

/* Wrong — hard-coded, ignores DB theme */
background: rgba(16, 185, 129, 0.2);
```

### Transparent layers used

| Element | Mix formula |
|---|---|
| Alert success background | `color-mix(in srgb, var(--success-color) 20%, transparent)` |
| Alert success border | `color-mix(in srgb, var(--success-color) 40%, transparent)` |
| Alert error background | `color-mix(in srgb, var(--danger-color) 20%, transparent)` |
| Alert error border | `color-mix(in srgb, var(--danger-color) 40%, transparent)` |
| Alert info background | `color-mix(in srgb, var(--primary-color) 20%, transparent)` |
| Alert info border | `color-mix(in srgb, var(--primary-color) 40%, transparent)` |
| Badge info background | `color-mix(in srgb, var(--primary-color) 20%, transparent)` |
| Badge info border | `color-mix(in srgb, var(--primary-color) 30%, transparent)` |
| Form card box-shadow | `color-mix(in srgb, var(--text-primary) 10%, transparent)` |
| Alert toast box-shadow | `color-mix(in srgb, var(--text-primary) 18%, transparent)` |

---

## 5. Animation Names

To avoid collision with global keyframe names, all `@keyframes` in this file
are prefixed with `tu-`:

| Keyframe | Purpose |
|---|---|
| `tu-slide-in` | Alert toast slide-in from the trailing edge |

---

## 6. Alert Toasts

The alert toast component (`#tenantUsersPageContainer .alert`) is positioned
`fixed` at `top: 20px; inset-inline-end: 20px` so it works for both LTR and
RTL layouts without JavaScript.

Variants:

| Class | Colour source | Use case |
|---|---|---|
| `.alert-success` | `--success-color` | Successful save / delete |
| `.alert-error` | `--danger-color` | API error or validation failure |
| `.alert-info` | `--primary-color` | Informational message |

The `.btn-close` inside the toast inherits the alert's colour and does not
use a hard-coded colour.

---

## 7. User / Entity / Tenant Info Boxes

Info boxes (`.user-info-box`) are hidden by default (`display: none`) and
shown by JavaScript when a user lookup succeeds. Three icon colour variants
are provided:

| CSS class | Colour source | Context |
|---|---|---|
| `.entity-icon` | `--warning-color` | Entity/store info |
| `.tenant-icon` | `--primary-color` | Tenant info |
| `.user-icon` | `--success-color` | User info |

---

## 8. Key CSS Classes

| Class | Selector scope | Purpose |
|---|---|---|
| `.form-card` | `#tenantUsersPageContainer .form-card` | Add/Edit form card |
| `.card-title` | `#tenantUsersPageContainer .form-card .card-title` | Form card heading |
| `.input-with-button` | `#tenantUsersPageContainer .input-with-button` | Search input + Verify button row |
| `.user-info-box` | `#tenantUsersPageContainer .user-info-box` | Context-sensitive info panel |
| `.info-content` | `#tenantUsersPageContainer .user-info-box .info-content` | Icon + text flex row |
| `.alert` | `#tenantUsersPageContainer .alert` | Fixed-position toast notification |
| `.alert-success` | `#tenantUsersPageContainer .alert-success` | Success variant |
| `.alert-error` | `#tenantUsersPageContainer .alert-error` | Error variant |
| `.alert-info` | `#tenantUsersPageContainer .alert-info` | Info variant |
| `.filters-grid` | `#tenantUsersPageContainer .filters-grid` | Filter controls responsive grid |
| `.filter-group` | `#tenantUsersPageContainer .filter-group` | Label + input stack |
| `.filter-actions` | `#tenantUsersPageContainer .filter-actions` | Filter Submit / Reset buttons |
| `.table-actions` | `#tenantUsersPageContainer .table-actions` | Per-row action button group |
| `.icon-btn` | `#tenantUsersPageContainer .icon-btn` | Square icon-only action button |
| `.icon-btn.danger` | `#tenantUsersPageContainer .icon-btn.danger` | Destructive icon button |
| `.badge-info` | `#tenantUsersPageContainer .badge-info` | Primary-colour info badge |

---

## 9. Responsive Breakpoints

| Breakpoint | Width | Key changes |
|---|---|---|
| Mobile | ≤ 768 px | Filters collapse to single column; table actions left-aligned |

---

## 10. Related Files

| File | Purpose |
|---|---|
| `admin/fragments/tenant_users.php` | PHP template that renders `#tenantUsersPageContainer` |
| `admin/assets/js/pages/tenant_users.js` | JS module for this page |
| `admin/assets/css/pages/tenant.css` | Parent tenant page styles (hosts this fragment as a sub-tab) |
| `admin/includes/theme_injector.php` | Emits `:root { --primary-color: …; }` from DB |
| `api/v1/routes/tenant_users.php` | REST API handler |
| `docs/TENANTS_ARCHITECTURE.md` | Tenant ↔ user data model |
