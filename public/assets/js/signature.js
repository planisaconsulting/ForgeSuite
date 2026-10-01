(function () {
    var canvas = document.getElementById('sign-pad');
    var input = document.getElementById('signature_png');
    var form = canvas && canvas.closest('form');
    var note = document.getElementById('pod-sync');
    if (!canvas || !input || !form) return;
    var ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = '#111';
    ctx.lineWidth = 2;
    var drawing = false;
    function point(event) {
        var rect = canvas.getBoundingClientRect();
        var src = event.touches ? event.touches[0] : event;
        return {x: (src.clientX - rect.left) * (canvas.width / rect.width), y: (src.clientY - rect.top) * (canvas.height / rect.height)};
    }
    function start(event) { drawing = true; var p = point(event); ctx.beginPath(); ctx.moveTo(p.x, p.y); event.preventDefault(); }
    function move(event) { if (!drawing) return; var p = point(event); ctx.lineTo(p.x, p.y); ctx.stroke(); event.preventDefault(); }
    function end() { drawing = false; }
    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    window.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start, {passive: false});
    canvas.addEventListener('touchmove', move, {passive: false});
    canvas.addEventListener('touchend', end);
    var key = 'sf-pod-' + location.pathname;
    form.addEventListener('input', function () {
        if (note) note.textContent = 'NOT YET SYNCED';
        try { localStorage.setItem(key, 'draft'); } catch (e) {}
    });
    form.addEventListener('submit', function () {
        input.value = canvas.toDataURL('image/png');
        var client = document.getElementById('client_signed_at');
        if (client) client.value = new Date().toISOString();
        try { localStorage.removeItem(key); } catch (e) {}
    });
})();
