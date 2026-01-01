# Color Slider Component

## Overview

The Color Slider component is a visual UI element that displays all active colors from the `color_settings` database table. It allows administrators to preview and select colors from the theme configuration.

## Features

- **Database Integration**: Automatically loads colors from `window.ADMIN_UI.theme.colors`
- **Category Grouping**: Colors are organized by category (primary, secondary, accent, background, text, border, etc.)
- **Visual Swatches**: Each color is displayed as a swatch with its name and hex value
- **Interactive Selection**: Click on any color to select it, with visual feedback
- **Responsive Design**: Adapts to different screen sizes (desktop, tablet, mobile)
- **Theme Integration**: Uses CSS variables from the theme system
- **Multiple Display Styles**: Supports default grid, compact, and range slider layouts
- **i18n Support**: All labels support internationalization

## Files

### JavaScript
- `/admin/assets/js/color-slider.js` - Main component logic

### CSS
- `/admin/assets/css/color-slider.css` - Component styling
- `/admin/assets/css/admin.css` - Base styles integration
- `/admin/assets/css/admin-theme.css` - Theme-specific overrides
- `/admin/assets/css/modal.css` - Modal integration styles

### PHP
- `/admin/includes/header.php` - Includes color slider assets
- `/admin/dashboard.php` - Displays color slider on dashboard
- `/api/bootstrap_admin_ui.php` - Provides color data from database

## Usage

### Basic Usage

```html
<!-- In HTML -->
<div id="colorSliderContainer" data-color-slider></div>
```

```javascript
// Initialize programmatically
ColorSlider.render('#colorSliderContainer', {
    onSelect: function(color) {
        console.log('Selected color:', color);
        // color object contains: id, setting_key, setting_name, color_value, category, etc.
    }
});
```

### Auto-initialization

Add the `data-color-slider` attribute to any element to auto-initialize:

```html
<div data-color-slider data-on-select="myCallbackFunction"></div>
```

### Display Styles

#### Default Grid Style
```html
<div id="colorSlider"></div>
<script>
    ColorSlider.render('#colorSlider');
</script>
```

#### Compact Style
```html
<div id="colorSlider" class="compact"></div>
```

#### Range/Slider Style
```html
<div id="colorSlider" class="range-style"></div>
```

### Modal Integration

```javascript
// Show color picker in a modal
AdminModal.showColorPicker({
    title: 'Select a Theme Color',
    onSelect: function(color) {
        console.log('Color selected from modal:', color);
    },
    onCancel: function() {
        console.log('Color selection cancelled');
    }
});
```

## API Reference

### ColorSlider.render(containerSelector, options)

Renders the color slider in the specified container.

**Parameters:**
- `containerSelector` (string|Element): CSS selector or DOM element
- `options` (object): Configuration options
  - `onSelect` (function): Callback when a color is selected

**Returns:** Container element

**Example:**
```javascript
ColorSlider.render('#myContainer', {
    onSelect: function(color) {
        alert('Selected: ' + color.color_value);
    }
});
```

### ColorSlider.init(containerSelector, options)

Initialize the color slider (same as render, but marks as initialized).

### ColorSlider.getSelection()

Get the currently selected color.

**Returns:** Color object or null

### ColorSlider.clearSelection()

Clear the current selection.

### ColorSlider.on(event, callback)

Register an event callback.

**Events:**
- Custom events can be triggered using `ColorSlider.trigger()`

## Color Object Structure

Colors retrieved from the database have this structure:

```javascript
{
    id: 1,                          // Database ID
    setting_key: 'primary_main',     // Unique key
    setting_name: 'Primary Color',   // Display name
    color_value: '#3B82F6',          // Hex color value
    category: 'primary',             // Category (primary, secondary, etc.)
    is_active: 1,                    // Active status
    sort_order: 1                    // Display order
}
```

## Database Schema

The component reads from the `color_settings` table:

```sql
color_settings:
  - id (bigint, PRI)
  - theme_id (bigint, MUL)
  - setting_key (varchar)
  - setting_name (varchar)
  - color_value (varchar - hex format)
  - category (enum: 'primary', 'secondary', 'accent', 'background', etc.)
  - is_active (tinyint)
  - sort_order (int)
  - created_at, updated_at (datetime)
```

## Internationalization

Add translations to `window.ADMIN_UI.strings`:

```javascript
window.ADMIN_UI.strings = {
    color_slider_title: 'Theme Colors',
    no_colors_available: 'No colors available',
    color_category_primary: 'Primary',
    color_category_secondary: 'Secondary',
    color_category_accent: 'Accent',
    color_category_background: 'Background',
    color_category_text: 'Text',
    color_category_border: 'Border',
    color_category_other: 'Other'
};
```

## Browser Support

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Testing

A test page is available at `/admin/test_color_slider.html` with mock data to verify the component functionality without a database connection.

## Customization

### CSS Variables

The component respects these CSS variables:

- `--theme-background` - Container background
- `--theme-border` - Border colors
- `--theme-primary` - Selection/hover color
- `--theme-text-primary` - Label text color
- `--theme-text-secondary` - Value text color
- `--theme-background-secondary` - Swatch background
- `--theme-card-radius` - Border radius

### Custom Styling

Override styles by targeting these classes:

```css
.color-slider-container { /* Main container */ }
.color-category-group { /* Category section */ }
.color-category-header { /* Category title */ }
.color-swatches-container { /* Swatches grid */ }
.color-swatch { /* Individual swatch */ }
.color-swatch-inner { /* Color display area */ }
.color-swatch-label { /* Color name */ }
.color-swatch-value { /* Color value */ }
.color-swatch.selected { /* Selected state */ }
```

## Troubleshooting

### Colors not displaying

1. Check that `window.ADMIN_UI.theme.colors` is populated
2. Verify database connection in `bootstrap_admin_ui.php`
3. Check browser console for errors

### Styles not applied

1. Ensure `color-slider.css` is loaded in header
2. Check that CSS variables are set by `admin_core.js`
3. Verify no CSS conflicts with other stylesheets

### Selection not working

1. Verify `onSelect` callback is provided
2. Check for JavaScript errors in console
3. Ensure color objects have required properties

## Performance

- Colors are loaded once from `window.ADMIN_UI` (server-side injection)
- No additional database queries after page load
- Minimal DOM manipulation
- Efficient CSS Grid layout
- Optimized for 50+ colors without performance degradation
