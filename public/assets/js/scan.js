(function () {
    var box = document.getElementById('scan-camera');
    var note = document.getElementById('scan-camera-note');
    var field = document.getElementById('code');
    if (!box || !field) return;
    if (!('BarcodeDetector' in window) || !navigator.mediaDevices) {
        if (note) note.textContent = 'Camera scanning is not available in this browser. Type the code or use a USB scanner.';
        return;
    }
    var detector = new BarcodeDetector({formats: ['qr_code', 'code_128']});
    navigator.mediaDevices.getUserMedia({video: {facingMode: 'environment'}}).then(function (stream) {
        var video = document.createElement('video');
        video.setAttribute('playsinline', '');
        video.autoplay = true;
        video.srcObject = stream;
        video.style.width = '100%';
        box.appendChild(video);
        var timer = setInterval(function () {
            detector.detect(video).then(function (codes) {
                if (!codes.length) return;
                clearInterval(timer);
                stream.getTracks().forEach(function (track) { track.stop(); });
                var value = codes[0].rawValue || '';
                var parts = value.split('/scan/');
                field.value = parts.length > 1 ? parts[1] : value;
                field.form.submit();
            }).catch(function () {});
        }, 400);
    }).catch(function () {
        if (note) note.textContent = 'Camera permission was not granted. Type the code or use a USB scanner.';
    });
})();
