// admin/assets/js/pages/product_studio.js
// Lightweight client-side image studio:
// - Allows selecting multiple images, previews thumbnails
// - Basic cropping by selecting a centered square (no external lib) and uploading via /api/upload_image.php
// - Shows uploaded images gallery

(function(){
  'use strict';
  var input = document.getElementById('studioFiles');
  var thumbs = document.getElementById('studioThumbnails');
  var canvas = document.getElementById('studioCanvas');
  var uploadBtn = document.getElementById('studioUploadBtn');
  var clearBtn = document.getElementById('studioClearBtn');
  var uploaded = document.getElementById('studioUploaded');
  var filesQueue = [];
  var currentIndex = 0;

  function el(tag, attrs){ var e=document.createElement(tag); if(attrs) Object.keys(attrs).forEach(k=>e.setAttribute(k,attrs[k])); return e; }

  input && input.addEventListener('change', function(){
    thumbs.innerHTML = ''; filesQueue = [];
    var f = this.files;
    if (!f || f.length === 0) return;
    for (var i=0;i<f.length;i++) {
      (function(file, idx){
        var reader = new FileReader();
        reader.onload = function(e){
          var img = new Image(); img.src = e.target.result;
          img.onload = function(){
            var t = el('div'); t.style.width='96px'; t.style.height='96px'; t.style.border='1px solid #e6eef0'; t.style.borderRadius='8px'; t.style.overflow='hidden'; t.style.display='flex'; t.style.alignItems='center'; t.style.justifyContent='center';
            var im = el('img'); im.src = e.target.result; im.style.maxWidth='100%'; im.style.maxHeight='100%';
            t.appendChild(im);
            t.addEventListener('click', function(){ showOnCanvas(file); currentIndex = idx; });
            thumbs.appendChild(t);
            filesQueue.push(file);
            // auto show first
            if (filesQueue.length === 1) showOnCanvas(file);
          };
        };
        reader.readAsDataURL(file);
      })(f[i], i);
    }
  });

  function showOnCanvas(file){
    var ctx = canvas.getContext('2d');
    var reader = new FileReader();
    reader.onload = function(e){
      var img = new Image(); img.src = e.target.result;
      img.onload = function(){
        // set canvas size to 600x600 or image constrained
        var max = 800;
        var w = img.width, h = img.height;
        var scale = Math.min(1, max / Math.max(w,h));
        var cw = Math.round(w*scale), ch = Math.round(h*scale);
        canvas.width = cw; canvas.height = ch;
        ctx.clearRect(0,0,cw,ch);
        ctx.drawImage(img,0,0,cw,ch);
        // draw crop rectangle (center square)
        var side = Math.min(cw,ch);
        var x = Math.round((cw-side)/2), y = Math.round((ch-side)/2);
        ctx.strokeStyle = 'rgba(6,182,212,0.8)'; ctx.lineWidth = 3; ctx.strokeRect(x,y,side,side);
      };
    };
    reader.readAsDataURL(file);
  }

  uploadBtn && uploadBtn.addEventListener('click', function(){
    if (!filesQueue.length) return alert('No files selected');
    uploadBtn.disabled = true;
    setStatus('Uploading...');
    // prepare crop for current index
    var idxs = filesQueue.map((f,i)=>i);
    // upload sequentially
    (function uploadNext(i){
      if (i >= filesQueue.length) { uploadBtn.disabled=false; setStatus('All uploaded'); loadUploaded(); return; }
      // crop from canvas centered square and convert to blob
      var ctx = canvas.getContext('2d');
      var side = Math.min(canvas.width, canvas.height);
      var sx = Math.round((canvas.width - side)/2), sy = Math.round((canvas.height - side)/2);
      var tmp = document.createElement('canvas'); tmp.width = side; tmp.height = side;
      tmp.getContext('2d').drawImage(canvas, sx, sy, side, side, 0,0, side, side);
      tmp.toBlob(function(blob){
        var fd = new FormData(); fd.append('file', blob, 'img_'+Date.now()+'.jpg'); fd.append('csrf_token', window.CSRF_TOKEN || '');
        fetch('/api/upload_image.php', { method:'POST', body: fd, credentials:'include' }).then(r=>r.json()).then(function(resp){
          if (resp && resp.success) {
            // show in uploaded
            var im = document.createElement('img'); im.src = resp.url; im.style.maxHeight='80px'; im.style.border='1px solid #e6eef0'; im.style.borderRadius='8px'; uploaded.appendChild(im);
          } else {
            console.error('Upload failed', resp);
          }
          // move to next file and put it on canvas
          if (i+1 < filesQueue.length) {
            var nextFile = filesQueue[i+1];
            showOnCanvas(nextFile);
          }
          uploadNext(i+1);
        }).catch(err=>{ console.error(err); uploadNext(i+1); });
      }, 'image/jpeg', 0.86);
    })(0);
  });

  clearBtn && clearBtn.addEventListener('click', function(){ thumbs.innerHTML=''; uploaded.innerHTML=''; filesQueue=[]; if (canvas) { canvas.width=0; canvas.height=0; } });

  function setStatus(msg){ var s = document.getElementById('bannersStatus'); if (s) s.textContent = msg; }

  function loadUploaded(){
    // fetch recent uploaded images if you have endpoint; here we just keep local
  }

})();