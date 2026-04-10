<?php
/**
 * QOOQZ Enterprise Observability Layer (Mini)
 * 
 * الميزات المتقدمة:
 * - Deduplication لمنع الغرق في نفس الخطأ آلاف المرات
 * - Performance API حديث (بدل deprecated) واكتشاف CLS و FPS المنخفض
 * - DOM Scanning آمن وخفيف عبر requestIdleCallback
 * - تصنيف الأخطاء والتكامل مع بيئة الخادم (ENV)
 * - جاهز للدمج مع Sentry & Clarity 
 */

// حماية استراتيجية: تشغيل الأداة في بيئة التطوير فقط (أو بـ debug=1)
// في الإنتاج يجب أن تكون APP_ENV = prod
$appEnv = $_ENV['APP_ENV'] ?? 'prod';
if ($appEnv === 'prod' && (!isset($_GET['debug']) || $_GET['debug'] != '1')) {
    return;
}

$sentry_dsn = '';
$clarity_id = '';
?>
<style>
#qz-bug-detector {
    position: fixed; bottom: 20px; left: 20px; width: 380px; max-height: 400px;
    background: #111; color: #0f0; font-family: monospace; font-size: 12px;
    z-index: 999999; border-radius: 8px; border: 2px solid #ef4444;
    overflow-y: auto; direction: ltr; text-align: left;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5); display: none;
}
#qz-bug-detector-header {
    background: #ef4444; color: #fff; padding: 8px; font-weight: bold;
    display: flex; justify-content: space-between;
}
.qz-bug-item { padding: 6px; border-bottom: 1px solid #333; word-break: break-all; }
.qz-bug-error { color: #f87171; }
.qz-bug-warn { color: #facc15; }
.qz-bug-perf { color: #60a5fa; }
.qz-bug-network { color: #c084fc; }
</style>

<div id="qz-bug-detector">
    <div id="qz-bug-detector-header">
        <span id="qz-bug-title">🕵️ Frontend Observability</span>
        <button onclick="document.getElementById('qz-bug-detector').style.display='none'" style="background:none;border:none;color:#fff;cursor:pointer;">X</button>
    </div>
    <div id="qz-bug-content"></div>
</div>

<?php if (!empty($sentry_dsn)): ?>
<script src="https://browser.sentry-cdn.com/7.80.0/bundle.tracing.min.js" crossorigin="anonymous"></script>
<script>Sentry.init({dsn: "<?= htmlspecialchars($sentry_dsn, ENT_QUOTES) ?>", tracesSampleRate: 1.0});</script>
<?php endif; ?>

<?php if (!empty($clarity_id)): ?>
<script type="text/javascript">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "<?= htmlspecialchars($clarity_id, ENT_QUOTES) ?>");
</script>
<?php endif; ?>

<script>
(function(){
    const bugs = [];
    const seen = new Set(); // 🚀 3) Deduplication (منع التكرار المزعج)
    const container = document.getElementById('qz-bug-detector');
    const content = document.getElementById('qz-bug-content');
    const titleEl = document.getElementById('qz-bug-title');

    function logBug(msg, type = 'error') {
        const hash = `${type}:${msg}`;
        if (seen.has(hash)) return;
        seen.add(hash);

        bugs.push({msg, type});
        container.style.display = 'block';

        const div = document.createElement('div');
        let colorClass = 'qz-bug-error';
        if (type === 'warn') colorClass = 'qz-bug-warn';
        else if (type === 'performance' || type === 'ui') colorClass = 'qz-bug-perf';
        else if (type === 'network' || type === 'api') colorClass = 'qz-bug-network';
        
        div.className = `qz-bug-item ${colorClass}`;
        div.textContent = `[${type.toUpperCase()}] ${msg}`;
        content.appendChild(div);

        titleEl.textContent = `🕵️ Bugs (${bugs.length})`;

        if (window.Sentry) {
            window.Sentry.captureMessage(msg, (type === 'error' || type === 'api') ? 'error' : 'warning');
        }

        // Endpoint for backend gathering
        // fetch('/api/public/frontend-log', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({msg, type, url: location.href}) }).catch(()=>{});
    }

    // 1. JS Errors
    window.addEventListener('error', e => logBug(`${e.message} at ${e.filename}:${e.lineno}`, 'error'));
    window.addEventListener('unhandledrejection', e => logBug(`Unhandled Promise: ${e.reason}`, 'error'));

    // 2. Fetch Interceptor
    const originalFetch = window.fetch;
    window.fetch = async (...args) => {
        try {
            const res = await originalFetch(...args);
            if (!res.ok) logBug(`Fetch failed ${res.url} (${res.status})`, 'api');
            return res;
        } catch (err) {
            logBug(`Network Error: ${err.message}`, 'network');
            throw err;
        }
    };

    // 3. Console Interceptor
    const originalError = console.error;
    console.error = function(...args) {
        logBug(`${args.join(' ')}`, 'error');
        originalError.apply(console, args);
    };

    // 4. Performance & UX Checks
    window.addEventListener('load', function() {
        const ric = window.requestIdleCallback || ((cb) => setTimeout(cb, 1));
        
        ric(() => {
            // 🚀 2) Modern Performance API
            const nav = performance.getEntriesByType("navigation")[0];
            if (nav) {
                const loadTime = nav.loadEventEnd - nav.startTime;
                if (loadTime > 4000) logBug(`Slow Load: ${loadTime.toFixed(0)}ms`, 'performance');
            }

            // Broken images and Empty links
            document.querySelectorAll('img').forEach(img => {
                if (!img.complete || img.naturalWidth === 0) logBug(`Broken Image: ${img.src}`, 'ui');
            });
            document.querySelectorAll('a').forEach(a => {
                const href = a.getAttribute('href');
                if (href === '' || href === 'undefined' || href === 'javascript:void(0)') {
                    logBug(`Broken Link text: "${a.innerText.substring(0, 20)}"`, 'ui');
                }
            });

            // 🚀 1) Safer Horizontal Scroll Check
            const winWidth = window.innerWidth;
            if (document.documentElement.scrollWidth > winWidth) {
                logBug(`Page is wider than screen! Horizontal scroll detected.`, 'ui');
                Array.from(document.body.children).forEach(el => {
                    if (el.scrollWidth > winWidth) logBug(`Overflow container: <${el.tagName}>`, 'ui');
                });
            }

            if (bugs.length === 0) logBug('All tests passed.', 'pass');
        });
    });

    // 🚀 4) FPS Monitoring
    let lastFPS = performance.now();
    let frames = 0;
    function fpsCheck() {
        frames++;
        const now = performance.now();
        if (now > lastFPS + 1000) {
            // If main thread blocks heavily and drops frames...
            if (frames < 25) logBug(`Low FPS detected: ${frames}fps`, 'performance');
            frames = 0;
            lastFPS = now;
        }
        requestAnimationFrame(fpsCheck);
    }
    fpsCheck();

    // 🚀 5) CLS (Cumulative Layout Shift)
    try {
        let clsScore = 0;
        new PerformanceObserver((entryList) => {
            for (const entry of entryList.getEntries()) {
                if (!entry.hadRecentInput) clsScore += entry.value;
            }
            if (clsScore > 0.15) {
                logBug(`High Cumulative Layout Shift (CLS): ${clsScore.toFixed(3)}`, 'performance');
            }
        }).observe({type: 'layout-shift', buffered: true});
    } catch(e) {}
})();
</script>
