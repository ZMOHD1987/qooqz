const wrap = document.getElementById('chatWrap');
wrap.scrollTop = wrap.scrollHeight;

document.getElementById('qInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (this.value.trim()) document.getElementById('chatForm').submit();
    }
});

const ta = document.getElementById('qInput');
ta.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 140) + 'px';
});
ta.focus();

function ask(q) {
    document.getElementById('qInput').value = q;
    document.getElementById('chatForm').submit();
}

function showFile(type) {
    var input = type === 'image' ? document.getElementById('imageInput') : document.getElementById('docInput');
    var tag   = type === 'image' ? document.getElementById('imgTag')    : document.getElementById('docTag');
    var name  = type === 'image' ? document.getElementById('imgName')   : document.getElementById('docName');
    if (input.files.length) {
        name.textContent = input.files[0].name;
        tag.classList.add('show');
        if (type === 'image') _runOcr(input.files[0]);
    }
}
function clearFile(type) {
    const input  = type === 'image' ? document.getElementById('imageInput')  : document.getElementById('docInput');
    const camInp = type === 'image' ? document.getElementById('cameraInput') : null;
    const tag    = type === 'image' ? document.getElementById('imgTag')       : document.getElementById('docTag');
    if (input)  input.value  = '';
    if (camInp) camInp.value = '';
    tag.classList.remove('show');
    // Also hide OCR preview when clearing image
    if (type === 'image') {
        var prev = document.getElementById('ocrPreview');
        if (prev) prev.style.display = 'none';
        var ocrIn = document.getElementById('ocrTextInput');
        if (ocrIn) ocrIn.value = '';
    }
}

document.getElementById('chatForm').addEventListener('submit', function() {
    const btn = document.getElementById('sendBtn');
    btn.disabled = true;
    btn.parentElement.classList.add('sending');
});

// ===== Attachment dropdown toggle =====
(function() {
    var btn = document.getElementById('attachToggle');
    var dd  = document.getElementById('attachDropdown');
    if (!btn || !dd) return;
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        dd.classList.toggle('show');
        btn.classList.toggle('open');
    });
    document.addEventListener('click', function() {
        dd.classList.remove('show');
        btn.classList.remove('open');
    });
})();

function closeAttachMenu() {
    var dd  = document.getElementById('attachDropdown');
    var btn = document.getElementById('attachToggle');
    if (dd)  dd.classList.remove('show');
    if (btn) btn.classList.remove('open');
}

// ===== Camera button — dedicated input with static capture="environment" =====
(function() {
    var camBtn = document.getElementById('cameraBtn');
    if (!camBtn) return;
    camBtn.addEventListener('click', function() {
        closeAttachMenu();
        var inp = document.getElementById('cameraInput');
        if (inp) inp.click();
    });
})();

function showCameraFile() {
    var input = document.getElementById('cameraInput');
    var tag   = document.getElementById('imgTag');
    var name  = document.getElementById('imgName');
    if (!input || !input.files.length) return;
    name.textContent = input.files[0].name;
    tag.classList.add('show');
    _runOcr(input.files[0]);
}

// ===== Client-side OCR via Tesseract.js (loaded lazily) =====
function _loadTesseract(cb) {
    if (typeof Tesseract !== 'undefined') { cb(); return; }
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
    s.onload = cb;
    s.onerror = function() { console.warn('Tesseract.js failed to load'); };
    document.head.appendChild(s);
}

function _runOcr(file) {
    var preview  = document.getElementById('ocrPreview');
    var statusEl = document.getElementById('ocrStatus');
    var textEl   = document.getElementById('ocrExtracted');
    var ocrInput = document.getElementById('ocrTextInput');
    if (ocrInput) ocrInput.value = '';
    if (!preview || !file) return;
    var isEn = (typeof AI_LANG !== 'undefined' && AI_LANG === 'en');
    preview.style.display = 'block';
    if (statusEl) statusEl.textContent = isEn ? '⏳ Reading image…' : '⏳ جارٍ قراءة الصورة…';
    if (textEl)   textEl.textContent   = '';

    _loadTesseract(function() {
        if (typeof Tesseract === 'undefined') {
            if (statusEl) statusEl.textContent = '⚠️ OCR library failed to load';
            return;
        }
        var lang = isEn ? 'eng' : 'ara+eng';
        var reader = new FileReader();
        reader.onload = function(e) {
            Tesseract.recognize(e.target.result, lang, {
                logger: function(m) {
                    if (m.status === 'recognizing text' && statusEl) {
                        statusEl.textContent = (isEn ? '⏳ ' : '⏳ ') + Math.round((m.progress || 0) * 100) + '%';
                    }
                }
            }).then(function(result) {
                var text = (result.data.text || '').trim();
                if (ocrInput) ocrInput.value = text;
                if (statusEl) statusEl.textContent = text
                    ? (isEn ? '✅ Text extracted (' + text.length + ' chars)' : '✅ تم استخراج النص (' + text.length + ' حرف)')
                    : (isEn ? 'ℹ️ No text found' : 'ℹ️ لم يُعثر على نص في الصورة');
                if (textEl && text) textEl.textContent = text.length > 400 ? text.substring(0, 400) + '…' : text;
            }).catch(function() {
                if (statusEl) statusEl.textContent = isEn ? '⚠️ OCR failed' : '⚠️ فشل استخراج النص';
            });
        };
        reader.readAsDataURL(file);
    });
}

// ===== Voice Recognition (Web Speech API) =====
(function() {
    var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    var micBtn = document.getElementById('micBtn');
    if (!micBtn) return;
    if (!SpeechRecognition) { micBtn.style.display = 'none'; return; }

    var recognition = new SpeechRecognition();
    recognition.continuous = false;
    recognition.interimResults = true;
    var isRecording = false;

    // Use language passed from PHP; default to Arabic
    recognition.lang = (typeof AI_LANG !== 'undefined' && AI_LANG === 'en') ? 'en-US' : 'ar-SA';

    recognition.onstart = function() {
        isRecording = true;
        micBtn.classList.add('recording');
        micBtn.title = micBtn.title; // keep title
    };
    recognition.onresult = function(event) {
        var transcript = '';
        for (var i = 0; i < event.results.length; i++) {
            transcript += event.results[i][0].transcript;
        }
        ta.value = transcript;
        ta.style.height = 'auto';
        ta.style.height = Math.min(ta.scrollHeight, 140) + 'px';
    };
    recognition.onend = function() {
        isRecording = false;
        micBtn.classList.remove('recording');
    };
    recognition.onerror = function(event) {
        isRecording = false;
        micBtn.classList.remove('recording');
        if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
            alert(typeof AI_LANG !== 'undefined' && AI_LANG === 'en'
                ? '🎤 Microphone permission denied. Please allow microphone access in your browser settings.'
                : '🎤 تم رفض إذن الميكروفون. يرجى السماح بالوصول إلى الميكروفون من إعدادات المتصفح.');
        }
    };

    micBtn.addEventListener('click', function() {
        // Update lang at click time (in case user switched languages)
        recognition.lang = (typeof AI_LANG !== 'undefined' && AI_LANG === 'en') ? 'en-US' : 'ar-SA';
        if (isRecording) {
            recognition.stop();
        } else {
            closeAttachMenu();
            recognition.start();
        }
    });
})();

// ===== Markdown + Mermaid rendering =====
(function() {
    if (typeof marked === 'undefined') return;
    marked.setOptions({ breaks: true, gfm: true });

    document.querySelectorAll('.md-bubble').forEach(function(el) {
        var raw = '';
        try { raw = JSON.parse(el.getAttribute('data-md') || '""'); } catch(e) { raw = el.getAttribute('data-md') || ''; }
        if (!raw) return;
        el.innerHTML = marked.parse(raw);
        el.classList.add('md-content');
        // Replace mermaid code blocks with .mermaid divs for chart rendering
        el.querySelectorAll('pre code').forEach(function(code) {
            var cls = code.className || '';
            if (cls.indexOf('language-mermaid') !== -1 || cls.indexOf('mermaid') !== -1) {
                var div = document.createElement('div');
                div.className = 'mermaid';
                div.textContent = code.textContent;
                var pre = code.closest('pre');
                if (pre) pre.replaceWith(div);
            }
        });
    });

    // Init mermaid if any .mermaid divs exist
    if (typeof mermaid !== 'undefined' && document.querySelector('.mermaid')) {
        mermaid.initialize({ startOnLoad: false, theme: 'dark', securityLevel: 'loose' });
        mermaid.run({ querySelector: '.mermaid' });
    }

    // Re-scroll to bottom after rendering
    wrap.scrollTop = wrap.scrollHeight;
})();