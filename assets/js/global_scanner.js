/**
 * global_scanner.js
 * Provides a shared camera barcode scanner modal for all pages.
 * Call window.openGlobalScanner(inputElement) from any page to open the scanner.
 * The scanned value is written into inputElement and change/keydown events are fired.
 * No auto-injection of buttons — each page controls its own scan UI explicitly.
 */
(function () {
  let html5QrScanner = null;
  let activeInput = null;
  let lastScannedCode = '';
  let lastScannedTime = 0;

  // ── Beep ─────────────────────────────────────────────────────────────────
  function playGlobalBeep() {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.type = 'sine';
      osc.frequency.setValueAtTime(1000, ctx.currentTime);
      gain.gain.setValueAtTime(0.1, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
      osc.start();
      osc.stop(ctx.currentTime + 0.15);
    } catch (e) { /* silent */ }
  }

  // ── Open modal ────────────────────────────────────────────────────────────
  async function openGlobalScanner(input) {
    activeInput = input;
    const modal = document.getElementById('globalCameraModal');
    if (!modal) return;
    modal.style.display = 'flex';

    const selectEl = document.getElementById('globalCameraSelect');
    selectEl.innerHTML = '';

    try {
      const devices = await Html5Qrcode.getCameras();
      if (!devices || devices.length === 0) {
        alert('Kamera tidak ditemukan.');
        closeGlobalScanner();
        return;
      }

      devices.forEach(device => {
        const opt = document.createElement('option');
        opt.value = device.id;
        opt.textContent = device.label || ('Kamera ' + (selectEl.options.length + 1));
        selectEl.appendChild(opt);
      });

      // Prefer back/rear camera on mobile
      const backCam = devices.find(d =>
        /back|rear|environment|belakang/i.test(d.label)
      );
      if (backCam) selectEl.value = backCam.id;

      selectEl.onchange = () => startGlobalScanning(selectEl.value);
      await startGlobalScanning(selectEl.value);
    } catch (err) {
      alert('Gagal akses kamera: ' + err.message);
      closeGlobalScanner();
    }
  }

  // ── Start scanning ────────────────────────────────────────────────────────
  async function startGlobalScanning(cameraId) {
    if (html5QrScanner) {
      try { await html5QrScanner.stop(); } catch (e) { /* ignore */ }
    }
    html5QrScanner = new Html5Qrcode('global-qr-reader');
    try {
      await html5QrScanner.start(
        cameraId,
        {
          fps: 15,
          qrbox: function (w, h) {
            const edge = Math.min(w, h);
            return { width: Math.floor(edge * 0.75), height: Math.floor(edge * 0.34) };
          },
          aspectRatio: 1.333333
        },
        decodedText => onGlobalScanSuccess(decodedText),
        () => { /* ignore frame errors */ }
      );
    } catch (err) {
      console.error('Global scanner start failed:', err);
    }
  }

  // ── Close modal ───────────────────────────────────────────────────────────
  function closeGlobalScanner() {
    const modal = document.getElementById('globalCameraModal');
    if (modal) modal.style.display = 'none';

    if (html5QrScanner) {
      html5QrScanner.stop()
        .catch(() => {})
        .finally(() => { html5QrScanner = null; });
    }
    if (activeInput) activeInput.focus();
  }

  // ── Scan success callback ─────────────────────────────────────────────────
  function onGlobalScanSuccess(decodedText) {
    const now = Date.now();
    // Cooldown: ignore repeated same scan within 1.8 s
    if (decodedText === lastScannedCode && (now - lastScannedTime) < 1800) return;
    lastScannedCode = decodedText;
    lastScannedTime = now;

    playGlobalBeep();

    if (activeInput) {
      activeInput.value = decodedText;
      activeInput.dispatchEvent(new Event('input',  { bubbles: true }));
      activeInput.dispatchEvent(new Event('change', { bubbles: true }));
      activeInput.dispatchEvent(new KeyboardEvent('keydown', {
        key: 'Enter', keyCode: 13, code: 'Enter', which: 13, bubbles: true
      }));

      // If input is a GET filter box (name="q"), auto-submit to search
      if (activeInput.name === 'q' && activeInput.form) {
        activeInput.form.submit();
      }
    }

    const autoClose = document.getElementById('globalScanAutoClose');
    if (autoClose && autoClose.checked) {
      closeGlobalScanner();
    }
  }

  // ── Expose globally ───────────────────────────────────────────────────────
  window.openGlobalScanner  = openGlobalScanner;
  window.closeGlobalScanner = closeGlobalScanner;

  // ── Bootstrap modal event listeners on DOM ready ──────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('globalCameraModal');
    if (!modal) return;

    // Click on backdrop → close
    modal.addEventListener('click', e => {
      if (e.target === modal) closeGlobalScanner();
    });
    // Custom event from ✕ button
    modal.addEventListener('gcam-close', closeGlobalScanner);

    // Escape key → close
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && modal.style.display !== 'none') {
        closeGlobalScanner();
      }
    });
  });
})();
