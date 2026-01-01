/*!
 * admin/assets/js/color-slider.js
 * Color slider component for displaying and selecting colors from color_settings table.
 * 
 * Features:
 * - Displays all active colors from window.ADMIN_UI.theme.colors
 * - Groups colors by category (primary, secondary, accent, background, etc.)
 * - Provides visual color swatches with hover effects
 * - Supports color selection and callback
 * - Integrates with theme system via CSS variables
 * - i18n support for labels
 * 
 * Usage:
 *   ColorSlider.init(containerElement, options);
 *   ColorSlider.render(containerSelector, options);
 */
(function () {
  'use strict';

  if (window.ColorSlider) return; // already initialized

  var ColorSlider = {
    version: '1.0.0',
    initialized: false,
    currentSelection: null,
    callbacks: {}
  };

  // Utilities
  function esc(str) {
    var div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
  }

  function safeSlug(s) {
    return String(s || '').toLowerCase().replace(/[^a-z0-9_-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
  }

  function getTranslation(key, fallback) {
    try {
      if (window.ADMIN_UI && window.ADMIN_UI.strings && window.ADMIN_UI.strings[key]) {
        return window.ADMIN_UI.strings[key];
      }
      if (window._admin && window._admin.t && typeof window._admin.t === 'function') {
        var result = window._admin.t(key);
        if (result !== key) return result;
      }
    } catch (e) {
      console.warn('Translation lookup failed for key:', key, e);
    }
    return fallback || key;
  }

  function normalizeColorValue(v) {
    if (!v) return null;
    var s = String(v).trim();
    if (!s) return null;
    if (/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/.test(s)) return s.toUpperCase();
    if (/^(rgb|rgba|hsl|hsla)\(/i.test(s)) return s;
    if (s.toLowerCase() === 'transparent') return 'transparent';
    if (/^[A-Fa-f0-9]{6}$/.test(s)) return '#' + s.toUpperCase();
    if (/^[A-Fa-f0-9]{3}$/.test(s)) return '#' + s.toUpperCase();
    if (/^var\(--/.test(s)) return s;
    return s;
  }

  // Get colors from ADMIN_UI
  function getColors() {
    try {
      if (!window.ADMIN_UI || !window.ADMIN_UI.theme) return [];
      var colors = window.ADMIN_UI.theme.colors || [];
      // Filter active colors
      return colors.filter(function (c) {
        return c && c.is_active !== 0 && c.color_value;
      });
    } catch (e) {
      console.warn('ColorSlider: Failed to get colors', e);
      return [];
    }
  }

  // Group colors by category
  function groupColorsByCategory(colors) {
    var groups = {};
    colors.forEach(function (color) {
      var category = color.category || 'other';
      if (!groups[category]) {
        groups[category] = [];
      }
      groups[category].push(color);
    });
    // Sort each group by sort_order
    Object.keys(groups).forEach(function (cat) {
      groups[cat].sort(function (a, b) {
        return (a.sort_order || 0) - (b.sort_order || 0);
      });
    });
    return groups;
  }

  // Render a single color swatch
  function renderColorSwatch(color, options) {
    options = options || {};
    var colorValue = normalizeColorValue(color.color_value);
    var settingName = esc(color.setting_name || color.setting_key || 'Color');
    var settingKey = esc(color.setting_key || '');
    var category = esc(color.category || 'other');
    var id = color.id || '';

    var swatch = document.createElement('div');
    swatch.className = 'color-swatch';
    swatch.setAttribute('data-color-id', id);
    swatch.setAttribute('data-color-key', settingKey);
    swatch.setAttribute('data-color-value', colorValue || '');
    swatch.setAttribute('data-category', category);
    swatch.setAttribute('title', settingName + ' (' + (colorValue || 'N/A') + ')');

    var swatchInner = document.createElement('div');
    swatchInner.className = 'color-swatch-inner';
    swatchInner.style.backgroundColor = colorValue || 'transparent';
    
    // Add checkerboard pattern for transparent colors
    if (!colorValue || colorValue === 'transparent') {
      swatchInner.style.backgroundImage = 'linear-gradient(45deg, #ccc 25%, transparent 25%), linear-gradient(-45deg, #ccc 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #ccc 75%), linear-gradient(-45deg, transparent 75%, #ccc 75%)';
      swatchInner.style.backgroundSize = '20px 20px';
      swatchInner.style.backgroundPosition = '0 0, 0 10px, 10px -10px, -10px 0px';
    }

    var swatchLabel = document.createElement('div');
    swatchLabel.className = 'color-swatch-label';
    swatchLabel.textContent = settingName;

    var swatchValue = document.createElement('div');
    swatchValue.className = 'color-swatch-value';
    swatchValue.textContent = colorValue || 'N/A';

    swatch.appendChild(swatchInner);
    swatch.appendChild(swatchLabel);
    swatch.appendChild(swatchValue);

    // Add click handler
    if (options.onSelect && typeof options.onSelect === 'function') {
      swatch.style.cursor = 'pointer';
      swatch.addEventListener('click', function () {
        // Remove previous selection
        var container = swatch.closest('.color-slider-container');
        if (container) {
          var selected = container.querySelectorAll('.color-swatch.selected');
          Array.prototype.forEach.call(selected, function (el) {
            el.classList.remove('selected');
          });
        }
        // Mark as selected
        swatch.classList.add('selected');
        ColorSlider.currentSelection = color;
        options.onSelect(color);
      });
    }

    return swatch;
  }

  // Render category group
  function renderCategoryGroup(category, colors, options) {
    var group = document.createElement('div');
    group.className = 'color-category-group';
    group.setAttribute('data-category', category);

    var header = document.createElement('div');
    header.className = 'color-category-header';
    var categoryLabel = getTranslation('color_category_' + category, category.charAt(0).toUpperCase() + category.slice(1));
    header.textContent = categoryLabel;
    
    var swatchesContainer = document.createElement('div');
    swatchesContainer.className = 'color-swatches-container';

    colors.forEach(function (color) {
      var swatch = renderColorSwatch(color, options);
      swatchesContainer.appendChild(swatch);
    });

    group.appendChild(header);
    group.appendChild(swatchesContainer);

    return group;
  }

  // Main render function
  ColorSlider.render = function (containerSelector, options) {
    options = options || {};
    var container;
    
    if (typeof containerSelector === 'string') {
      container = document.querySelector(containerSelector);
    } else if (containerSelector && containerSelector.nodeType === 1) {
      container = containerSelector;
    }

    if (!container) {
      console.warn('ColorSlider: Container not found:', containerSelector);
      return null;
    }

    // Clear container
    container.innerHTML = '';
    container.className = (container.className || '') + ' color-slider-container';

    // Get and group colors
    var colors = getColors();
    if (!colors || colors.length === 0) {
      var emptyMsg = document.createElement('div');
      emptyMsg.className = 'color-slider-empty';
      emptyMsg.textContent = getTranslation('no_colors_available', 'No colors available');
      container.appendChild(emptyMsg);
      return container;
    }

    var grouped = groupColorsByCategory(colors);
    var categoryOrder = ['primary', 'secondary', 'accent', 'background', 'text', 'border', 'success', 'error', 'warning', 'info', 'other'];

    // Create header
    var header = document.createElement('div');
    header.className = 'color-slider-header';
    var title = document.createElement('h3');
    title.textContent = getTranslation('color_slider_title', 'Theme Colors');
    header.appendChild(title);
    container.appendChild(header);

    // Create scrollable content area
    var content = document.createElement('div');
    content.className = 'color-slider-content';

    // Render categories in order
    categoryOrder.forEach(function (cat) {
      if (grouped[cat] && grouped[cat].length > 0) {
        var group = renderCategoryGroup(cat, grouped[cat], options);
        content.appendChild(group);
      }
    });

    // Render any remaining categories not in the order list
    Object.keys(grouped).forEach(function (cat) {
      if (categoryOrder.indexOf(cat) === -1 && grouped[cat].length > 0) {
        var group = renderCategoryGroup(cat, grouped[cat], options);
        content.appendChild(group);
      }
    });

    container.appendChild(content);

    return container;
  };

  // Initialize with default rendering
  ColorSlider.init = function (containerSelector, options) {
    if (ColorSlider.initialized) {
      console.warn('ColorSlider: Already initialized');
      return;
    }
    ColorSlider.initialized = true;
    return ColorSlider.render(containerSelector, options);
  };

  // Get current selection
  ColorSlider.getSelection = function () {
    return ColorSlider.currentSelection;
  };

  // Clear selection
  ColorSlider.clearSelection = function () {
    ColorSlider.currentSelection = null;
    var selected = document.querySelectorAll('.color-swatch.selected');
    Array.prototype.forEach.call(selected, function (el) {
      el.classList.remove('selected');
    });
  };

  // Register callback
  ColorSlider.on = function (event, callback) {
    if (!ColorSlider.callbacks[event]) {
      ColorSlider.callbacks[event] = [];
    }
    ColorSlider.callbacks[event].push(callback);
  };

  // Trigger event
  ColorSlider.trigger = function (event, data) {
    if (!ColorSlider.callbacks[event]) return;
    ColorSlider.callbacks[event].forEach(function (cb) {
      try {
        cb(data);
      } catch (e) {
        console.error('ColorSlider event callback error:', e);
      }
    });
  };

  // Expose to window
  window.ColorSlider = ColorSlider;

  // Auto-initialize if data-color-slider attribute is present
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      var autoInit = document.querySelectorAll('[data-color-slider]');
      Array.prototype.forEach.call(autoInit, function (el) {
        var onSelect = el.getAttribute('data-on-select');
        var options = {};
        if (onSelect && window[onSelect]) {
          options.onSelect = window[onSelect];
        }
        ColorSlider.render(el, options);
      });
    });
  }

})();
