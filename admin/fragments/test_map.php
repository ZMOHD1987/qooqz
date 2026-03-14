<?php
/**
 * admin/fragments/test_map.php
 * ملف اختبار بسيط لعزل مشكلة ظهور الخريطة و Leaflet.Draw
 */
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار الخريطة - Leaflet Draw</title>
    
    <!-- 1. تحميل ملفات CSS (الستايل) - هذه هي نقطة الإصلاح الرئيسية -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />
    
    <style>
        body { margin: 0; padding: 20px; font-family: sans-serif; background: #f4f4f4; }
        .container { max-width: 1200px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { font-size: 1.5rem; color: #333; margin-bottom: 15px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        
        /* ضروري جداً: يجب تحديد ارتفاع للحاوية */
        #map { 
            height: 500px; 
            width: 100%; 
            border: 2px solid #ccc;
            border-radius: 4px;
            z-index: 1; /* لضمان ظهور الخريطة فوق العناصر الأخرى */
        }
        
        #output {
            margin-top: 20px;
            background: #f9f9f9;
            padding: 15px;
            border: 1px dashed #ccc;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>اختبار الخريطة والرسم (Delivery Zone Test)</h1>
    
    <!-- حاوية الخريطة -->
    <div id="map"></div>
    
    <!-- منطقة لعرض النتائج (JSON) -->
    <h3>الإحداثيات (JSON):</h3>
    <div id="output">ارسم شكلاً على الخريطة لظهور البيانات هنا...</div>
</div>

<!-- 2. تحميل ملفات JS (المكتبات) -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // أ. تهيئة الخريطة (الإحداثيات الافتراضية: الرياض)
        var map = L.map('map').setView([24.7136, 46.6753], 10);

        // ب. إضافة طبقة الصور (Tiles)
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // ج. إضافة طبقة للرسومات (FeatureGroup)
        var drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        // د. إضافة أدوات التحكم (Draw Control)
        var drawControl = new L.Control.Draw({
            position: 'topright',
            draw: {
                polygon: {
                    allowIntersection: false, // عدم السماح بتقاطع الخطوط
                    showArea: true
                },
                rectangle: true,
                circle: true,
                marker: false,
                polyline: false,
                circlemarker: false
            },
            edit: {
                featureGroup: drawnItems,
                remove: true
            }
        });
        map.addControl(drawControl);

        // هـ. التعامل مع حدث الرسم (عندما ينتهي المستخدم من الرسم)
        map.on(L.Draw.Event.CREATED, function (event) {
            var layer = event.layer;
            drawnItems.addLayer(layer);
            
            // استخراج البيانات وعرضها
            updateOutput(layer);
        });

        // و. التعامل مع حدث التعديل
        map.on(L.Draw.Event.EDITED, function (event) {
            var layers = event.layers;
            layers.eachLayer(function (layer) {
                updateOutput(layer);
            });
        });

        // ز. التعامل مع حدث الحذف
        map.on(L.Draw.Event.DELETED, function () {
            document.getElementById('output').innerText = 'تم حذف الرسومات...';
        });

        // دالة مساعدة لتنسيق المخرجات
        function updateOutput(layer) {
            var output = document.getElementById('output');
            var json = {};
            
            // التحقق من نوع الشكل واستخراج الإحداثيات المناسبة
            if (layer instanceof L.Circle) {
                var center = layer.getLatLng();
                json.type = "Circle";
                json.center = [center.lat, center.lng];
                json.radius = layer.getRadius(); // بالأمتار
            } else if (layer instanceof L.Polygon || layer instanceof L.Rectangle) {
                json.type = layer instanceof L.Rectangle ? "Rectangle" : "Polygon";
                // تحويل الإحداثيات إلى صيغة GeoJSON القياسية
                json.coordinates = layer.toGeoJSON().geometry.coordinates;
            }
            
            output.innerText = JSON.stringify(json, null, 2);
        }
        
        // إصلاح محتمل: إجبار الخريطة على إعادة حساب حجمها إذا تم تحميلها في تبويب مخفي
        // يمكنك استدعاء هذه الدالة عند فتح التبويب
        window.invalidateMapSize = function() {
            if (map) map.invalidateSize();
        };
        
        // محاكاة تأخير بسيط للتأكد من تحميل الستايل (للاختبار فقط)
        setTimeout(function() {
            map.invalidateSize();
        }, 500);
    });
</script>

</body>
</html>