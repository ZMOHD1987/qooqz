function showPanel(name) {
    document.querySelectorAll('.panel').forEach(function(p) { p.classList.remove('active'); });
    document.querySelectorAll('.tab').forEach(function(t) { t.classList.remove('active'); });
    document.getElementById('panel-' + name).classList.add('active');
    event.target.classList.add('active');
}
