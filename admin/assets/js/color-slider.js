/*!
 * admin/assets/js/color-slider.js
 * Production-ready Color Slider
 */
(function() {
    'use strict';

    if (window.ColorSlider) return;

    var ColorSlider = {
        initialized: false,
        currentSelection: null,
        callbacks: {}
    };

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
        return null;
    }

    function getColors() {
        return (window.ADMIN_UI?.theme?.colors || []).filter(c => c && c.color_value);
    }

    function groupColorsByCategory(colors) {
        var groups = {};
        colors.forEach(c => {
            var category = c.category || 'other';
            if (!groups[category]) groups[category] = [];
            groups[category].push(c);
        });
        Object.keys(groups).forEach(cat => {
            groups[cat].sort((a,b)=> (a.sort_order||0) - (b.sort_order||0));
        });
        return groups;
    }

    function renderColorSwatch(color, options) {
        var colorValue = normalizeColorValue(color.color_value);
        var name = color.setting_name || 'Color';
        var key = color.setting_key || '';

        var swatch = document.createElement('div');
        swatch.className = 'color-swatch';
        swatch.setAttribute('data-color-key', key);
        swatch.setAttribute('data-color-value', colorValue);
        swatch.setAttribute('title', name + ' (' + colorValue + ')');

        var inner = document.createElement('div');
        inner.className = 'color-swatch-inner';
        inner.style.backgroundColor = colorValue || 'transparent';

        if (!colorValue || colorValue==='transparent'){
            inner.style.backgroundImage='linear-gradient(45deg,#ccc 25%,transparent 25%),linear-gradient(-45deg,#ccc 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#ccc 75%),linear-gradient(-45deg,transparent 75%,#ccc 75%)';
            inner.style.backgroundSize='20px 20px';
            inner.style.backgroundPosition='0 0,0 10px,10px -10px,-10px 0';
        }

        var label = document.createElement('div');
        label.className='color-swatch-label';
        label.textContent=name;

        var valueDiv = document.createElement('div');
        valueDiv.className='color-swatch-value';
        valueDiv.textContent=colorValue;

        swatch.appendChild(inner);
        swatch.appendChild(label);
        swatch.appendChild(valueDiv);

        swatch.style.cursor='pointer';
        swatch.addEventListener('click',function(){
            var container=swatch.closest('.color-slider-container');
            if(container){
                container.querySelectorAll('.color-swatch.selected').forEach(el=>el.classList.remove('selected'));
            }
            swatch.classList.add('selected');
            ColorSlider.currentSelection=color;

            // Update live cards
            document.querySelectorAll('.card-slider').forEach(card=>{
                if(colorValue) card.style.backgroundColor=colorValue;
            });

            if(options?.onSelect) options.onSelect(color);
            ColorSlider.trigger('select', color);
        });

        return swatch;
    }

    function renderCategoryGroup(category, colors, options) {
        var group = document.createElement('div');
        group.className='color-category-group';
        group.setAttribute('data-category', category);

        var header = document.createElement('div');
        header.className='color-category-header';
        header.textContent=category.charAt(0).toUpperCase()+category.slice(1);

        var container=document.createElement('div');
        container.className='color-swatches-container';

        colors.forEach(color=>{
            container.appendChild(renderColorSwatch(color, options));
        });

        group.appendChild(header);
        group.appendChild(container);
        return group;
    }

    ColorSlider.render=function(containerSelector, options){
        options=options||{};
        var container = typeof containerSelector==='string' ? document.querySelector(containerSelector) : containerSelector;
        if(!container) return null;

        container.innerHTML='';
        container.classList.add('color-slider-container');

        var colors=getColors();
        if(!colors.length){
            var msg=document.createElement('div');
            msg.className='color-slider-empty';
            msg.textContent='No colors available';
            container.appendChild(msg);
            return container;
        }

        var grouped=groupColorsByCategory(colors);
        var order=['primary','secondary','accent','background','text','border','success','error','warning','info','other'];

        order.forEach(cat=>{
            if(grouped[cat]?.length) container.appendChild(renderCategoryGroup(cat, grouped[cat], options));
        });

        Object.keys(grouped).forEach(cat=>{
            if(!order.includes(cat) && grouped[cat].length)
                container.appendChild(renderCategoryGroup(cat, grouped[cat], options));
        });

        return container;
    };

    ColorSlider.init=function(containerSelector, options){
        if(ColorSlider.initialized) return ColorSlider.render(containerSelector, options);
        ColorSlider.initialized=true;
        return ColorSlider.render(containerSelector, options);
    };

    ColorSlider.getSelection=()=>ColorSlider.currentSelection;
    ColorSlider.clearSelection=()=>{
        ColorSlider.currentSelection=null;
        document.querySelectorAll('.color-swatch.selected').forEach(el=>el.classList.remove('selected'));
    };

    ColorSlider.on=function(event, callback){
        if(!ColorSlider.callbacks[event]) ColorSlider.callbacks[event]=[];
        ColorSlider.callbacks[event].push(callback);
    };
    ColorSlider.trigger=function(event,data){
        ColorSlider.callbacks[event]?.forEach(cb=>{try{cb(data);}catch(e){console.error(e);}});
    };

    window.ColorSlider=ColorSlider;

    if(document.readyState==='loading'){
        document.addEventListener('DOMContentLoaded',function(){
            document.querySelectorAll('[data-color-slider]').forEach(el=>{
                var onSelect=el.getAttribute('data-on-select');
                var options={};
                if(onSelect && window[onSelect]) options.onSelect=window[onSelect];
                ColorSlider.render(el, options);
            });
        });
    }

})();
