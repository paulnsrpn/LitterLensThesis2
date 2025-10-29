/**
 * litterlens_realtime_full.js
 * Complete frontend script — multi-camera probe, select, stream switching, stats, location labels.
 *
 * Requires backend endpoints on BASE_URL:
 *  GET  /check_camera?camera=<index>   -> { detected: true/false }
 *  GET  /live?camera=<index>           -> MJPEG stream
 *  GET  /stop_camera
 *  GET  /live_stats                    -> { total, accuracy, speed, classes }
 *  POST /reset_stats
 *  POST /set_threshold  (JSON: { threshold: 0.25 })
 *  GET  /reverse_geocode?lat=<>&lon=<> -> proxy to Nominatim (so front-end avoids CORS)
 */

document.addEventListener('DOMContentLoaded', () => {
  // ================== CONFIG ==================
        
        const BASE_URL = 'http://127.0.0.1:5000'; // change if needed
        const MAX_PROBE_CAMERAS = 6; // probe indices 0..MAX_PROBE_CAMERAS - adjust if you need more
        const STATS_POLL_INTERVAL = 1000; // ms

        // ================== DOM REFERENCES ==================
        const cameraSelect = document.getElementById('camera-select');
        const startBtn = document.getElementById('startBtn');
        const stopBtn = document.getElementById('stopBtn');
        const refreshBtn = document.getElementById('refresh-btn');
        const liveFeed = document.getElementById('liveFeed');
        const thresholdSelect = document.getElementById('threshold-select');
        const noCameraOverlay = document.querySelector('.noDisplay');
        const statusEl = document.querySelector('.status');
        const timeEl = document.querySelector('.time');

        const latitudeEl = document.getElementById('latitude');
        const longitudeEl = document.getElementById('longitude');

        // stats UI
        const totalDetectionsEl = document.getElementById('totalDetections');
        const topLitterEl = document.getElementById('topLitter');
        const detectionSpeedEl = document.getElementById('detectionSpeed');
        const cameraStatusEl = document.getElementById('cameraStatus');
        const detectionAccuracyEl = document.getElementById('detectionAccuracy');
        const litterTableBody = document.getElementById('litterTableBody');

        // ================== STATE ==================
        let streaming = false;
        let timerInterval = null;
        let statsInterval = null;
        let startTime = null;
        let availableCameraIndices = []; // detected indices
        let lastLocationLabel = 'Unknown Area';

        // ================== HELPERS ==================
        function log(...args) { console.debug('[Realtime]', ...args); }


    function setStatus(state, text) {
        const icons = {
        loading: '<i class="fa-solid fa-circle-notch fa-spin"></i>',
        active: '<i class="fa-solid fa-circle-check"></i>',
        error: '<i class="fa-solid fa-circle-exclamation"></i>',
        idle: '<i class="fa-solid fa-circle-notch"></i>',
        refreshing: '<i class="fa-solid fa-rotate-right fa-spin"></i>'
        };
        if (!statusEl) return console.warn('Missing .status element');
        statusEl.className = `status ${state}`;
        statusEl.innerHTML = `${icons[state] || ''} ${text}`;
    }

    function showOverlay(show = true) {
        if (!noCameraOverlay) return;
        noCameraOverlay.style.display = show ? 'flex' : 'none';
    }

    function formatTime(ms) {
        const totalSeconds = Math.floor(ms / 1000);
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        return `${hours}hr ${minutes}mins ${seconds}s`;
    }

    function startTimer() {
        startTime = Date.now();
        if (timerInterval) clearInterval(timerInterval);
        timerInterval = setInterval(() => {
        const elapsed = Date.now() - startTime;
        if (timeEl) timeEl.textContent = formatTime(elapsed);
        }, 1000);
    }

    function stopTimer() {
        if (timerInterval) clearInterval(timerInterval);
        timerInterval = null;
        if (timeEl) timeEl.textContent = '0hr 0mins 0s';
    }

    function updateRefreshButton() {
        if (!refreshBtn) return;
        refreshBtn.disabled = !streaming;
    }

    function resetStatsUI() {
        if (totalDetectionsEl) totalDetectionsEl.textContent = '0';
        if (detectionSpeedEl) detectionSpeedEl.textContent = '0s/frame';
        if (detectionAccuracyEl) detectionAccuracyEl.textContent = '0%';
        if (topLitterEl) topLitterEl.innerHTML = '--';
        if (litterTableBody) litterTableBody.innerHTML = '';
        if (cameraStatusEl) {
        cameraStatusEl.textContent = 'Idle';
        cameraStatusEl.classList.remove('active');
        }
    }

    // ================== GEOLOCATION & REVERSE ==================
    function getCurrentPositionPromise() {
        return new Promise((resolve) => {
        if (!navigator.geolocation) return resolve(null);
        navigator.geolocation.getCurrentPosition(
            pos => resolve(pos.coords),
            err => {
            console.warn('Geolocation error', err);
            resolve(null);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
        });
    }

    // Use backend proxy /reverse_geocode to avoid CORS with Nominatim
    async function getReadableLocationFromProxy(lat, lon) {
        if (!lat || !lon) return 'Unknown Area';
        try {
        const res = await fetch(`${BASE_URL}/reverse_geocode?lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lon)}`, { cache: 'no-store' });
        if (!res.ok) {
            log('reverse_geocode proxy returned', res.status);
            return 'Unknown Area';
        }
        const data = await res.json();
        const addr = data.address || (data.raw && data.raw.address) || {};
        const barangay = addr.suburb || addr.village || addr.neighbourhood || addr.hamlet || '';
        const city = addr.city || addr.town || addr.municipality || addr.county || '';
        const state = addr.state || addr.region || '';
        const parts = [barangay, city, state].filter(Boolean);
        return parts.join(', ') || data.display_name || 'Unknown Area';
        } catch (e) {
        console.warn('reverse geocode proxy failed', e);
        return 'Unknown Area';
        }
    }

    // ================== BACKEND CAMERA CHECK ==================
    async function backendCheckCamera(index) {
        try {
        const res = await fetch(`${BASE_URL}/check_camera?camera=${index}`, { cache: 'no-store' });
        if (!res.ok) {
            log('check_camera non-ok', index, res.status);
            return false;
        }
        const j = await res.json();
        return !!j.detected;
        } catch (e) {
        // likely connection refused or network error
        log('check_camera failed', index, e);
        return false;
        }
    }

  // ================== PROBE CAMERAS & POPULATE SELECT ==================
    async function probeCamerasAndLabel() {
        if (!cameraSelect) return;
        setStatus('loading', 'Probing cameras & location...');
        showOverlay(true);
        cameraSelect.innerHTML = '';

        // Get location first (for labeling)
        let coords = await getCurrentPositionPromise();
        let labelText = 'Unknown Area';
        if (coords) {
        if (latitudeEl) latitudeEl.textContent = coords.latitude.toFixed(6);
        if (longitudeEl) longitudeEl.textContent = coords.longitude.toFixed(6);
        // Use backend proxy to reverse geocode if available
        try {
            labelText = await getReadableLocationFromProxy(coords.latitude, coords.longitude);
        } catch (e) {
            log('reverse geocode proxy error', e);
            labelText = 'Unknown Area';
        }
        } else {
        if (latitudeEl) latitudeEl.textContent = 'Unavailable';
        if (longitudeEl) longitudeEl.textContent = 'Unavailable';
        labelText = 'Location unavailable';
            }
        lastLocationLabel = labelText;

        // Probe camera indices (use Promise.allSettled to speed up)
        const checks = [];
        for (let i = 0; i <= MAX_PROBE_CAMERAS; i++) {
        checks.push(backendCheckCamera(i).then(ok => ({ idx: i, ok })));
            }

        const results = await Promise.all(checks);
        availableCameraIndices = results.filter(r => r.ok).map(r => r.idx);

        if (!availableCameraIndices.length) {
        // show one disabled option and disable start
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'No cameras found';
        cameraSelect.appendChild(opt);
        cameraSelect.disabled = true;
        if (startBtn) startBtn.disabled = true;
        setStatus('error', 'No cameras available');
        showOverlay(true);
        updateRefreshButton();
        return;
            }

    // populate select with one option for each found camera
        cameraSelect.disabled = false;
        cameraSelect.innerHTML = '';
        availableCameraIndices.forEach(idx => {
        const opt = document.createElement('option');
        opt.value = idx;
        opt.textContent = `📷 Camera ${idx} • ${lastLocationLabel}`;
        cameraSelect.appendChild(opt);
        });

        // enable start
        if (startBtn) startBtn.disabled = false;
        setStatus('idle', 'Select camera');
        showOverlay(false);
        updateRefreshButton();
    }

    // ================== STATS POLLING ==================
    function updateStatsUI(data) {
        if (!data) return;
        if (totalDetectionsEl) totalDetectionsEl.textContent = data.total ?? 0;
        if (detectionSpeedEl) detectionSpeedEl.textContent = `${data.speed ?? 0}s/frame`;
        if (detectionAccuracyEl) detectionAccuracyEl.textContent = `${data.accuracy ?? 0}%`;

        const classes = data.classes || {};
        const entries = Object.entries(classes);
        if (entries.length) {
        entries.sort((a,b) => b[1] - a[1]);
        const [topClass, topCount] = entries[0];
        const topPercent = data.total ? Math.round((topCount / data.total) * 100) : 0;
        if (topLitterEl) topLitterEl.innerHTML = `${topPercent}% <br><em>${topClass}</em>`;
        } else {
        if (topLitterEl) topLitterEl.innerHTML = '--';
        }

        if (litterTableBody) {
        litterTableBody.innerHTML = '';
        entries.forEach(([cls, count]) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${cls}</td><td>${count}</td>`;
            litterTableBody.appendChild(tr);
        });
        }

        if (cameraStatusEl) {
        cameraStatusEl.textContent = streaming ? 'Active' : 'Idle';
        cameraStatusEl.classList.toggle('active', streaming);
        }
    }

    function startStatsPolling() {
        if (statsInterval) return;
        statsInterval = setInterval(async () => {
        try {
            const res = await fetch(`${BASE_URL}/live_stats`, { cache: 'no-store' });
            if (!res.ok) throw new Error('stats fetch failed');
            const data = await res.json();
            updateStatsUI(data);
        } catch (e) {
            log('live_stats fetch failed', e);
            setStatus('error', 'Stats error');
        }
        }, STATS_POLL_INTERVAL);
    }

    function stopStatsPolling() {
        if (statsInterval) clearInterval(statsInterval);
        statsInterval = null;
    }

    // ================== STREAM CONTROL ==================
    async function startStreamForCamera(camIndex) {
        if (typeof camIndex === 'undefined' || camIndex === null || camIndex === '') {
        alert('No camera selected');
        return;
        }

        // Pre-stop backend stream & reset stats to ensure clean start
        try {
        await fetch(`${BASE_URL}/stop_camera`).catch(()=>{});
        await fetch(`${BASE_URL}/reset_stats`, { method: 'POST' }).catch(()=>{});
        } catch (e) {
        log('pre-stop/reset failed', e);
        }

        // Set threshold on backend
        const thr = parseFloat(thresholdSelect?.value ?? 0.25) || 0.25;
        try {
        await fetch(`${BASE_URL}/set_threshold`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ threshold: thr })
        }).catch(()=>{});
        } catch (e) {
        log('set_threshold failed', e);
        }

        // Switch client to backend MJPEG URL (with camera param)
        streaming = true;
        updateRefreshButton();
        setStatus('loading', 'Starting stream...');
        showOverlay(false);

        // compose stream URL
        const url = `${BASE_URL}/live?camera=${encodeURIComponent(camIndex)}&cb=${Date.now()}`;
        liveFeed.src = url;
        liveFeed.style.display = 'block';

        // start timer & stats
        startTimer();
        startStatsPolling();

        setTimeout(() => {
        if (streaming) setStatus('active', 'Active');
        }, 700);
    }

    async function startStream() {
        // if change camera while streaming: switch camera smoothly
        const camVal = cameraSelect?.value;
        if (!camVal) return alert('Please select a camera first');
        // Start stream for the selected value
        await startStreamForCamera(camVal);
    }

    async function stopStream() {
        if (!streaming) {
        showOverlay(true);
        return;
        }

        setStatus('loading', 'Stopping stream...');
        streaming = false;
        updateRefreshButton();

        try {
        await fetch(`${BASE_URL}/stop_camera`).catch(()=>{});
        await fetch(`${BASE_URL}/reset_stats`, { method: 'POST' }).catch(()=>{});
        } catch (e) {
        log('stop endpoints error', e);
        }

        stopTimer();
        stopStatsPolling();
        resetStatsUI();

        liveFeed.src = '';
        liveFeed.style.display = 'none';
        showOverlay(true);

        setTimeout(() => setStatus('idle', 'Idle'), 300);
    }

    async function switchCameraWhileStreaming(newIdx) {
        if (!streaming) {
        // simply start on selected camera
        await startStreamForCamera(newIdx);
        return;
        }
        // stop backend stream then start new one for new camera index
        setStatus('loading', 'Switching camera...');
        try {
        await fetch(`${BASE_URL}/stop_camera`).catch(()=>{});
        await fetch(`${BASE_URL}/reset_stats`, { method: 'POST' }).catch(()=>{});
        } catch (e) {
        log('switch stop/reset failed', e);
        }

        // small pause to allow backend to free device
        await new Promise(r => setTimeout(r, 300));
        await startStreamForCamera(newIdx);
    }

    function refreshStream() {
        if (!streaming) return;
        setStatus('refreshing', 'Refreshing stream...');
        const camVal = cameraSelect.value || '';
        liveFeed.src = `${BASE_URL}/live?camera=${encodeURIComponent(camVal)}&cb=${Date.now()}`;
    }

    // ================== EVENTS ==================
    startBtn?.addEventListener('click', async () => {
        // re-probe quickly (labels cached server-side if you implemented caching)
        await probeCamerasAndLabel();
        await startStream();
    });

    stopBtn?.addEventListener('click', stopStream);

    refreshBtn?.addEventListener('click', refreshStream);

    // when user changes camera selection
    cameraSelect?.addEventListener('change', async (e) => {
        const val = e.target.value;
        if (!val) return;
        // If streaming, switch to new camera cleanly
        if (streaming) {
        await switchCameraWhileStreaming(val);
        } else {
        // Not streaming: update the preview src (optional) or just update label
        // leave feed hidden until user presses Start
        }
    });

    // handle stream load/error
    liveFeed.onload = () => {
        if (streaming) setStatus('active', 'Active');
    };

    liveFeed.onerror = () => {
        console.warn('liveFeed onerror fired');
        setStatus('error', 'Camera Error');
        // show overlay and stop streaming (backend might have crashed/failed to open device)
        stopStream();
    };

    // apply threshold live if streaming
    thresholdSelect?.addEventListener('change', () => {
        if (!streaming) return;
        const thr = parseFloat(thresholdSelect.value || 0.25);
        fetch(`${BASE_URL}/set_threshold`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ threshold: thr })
        }).then(res => res.json()).then(j => {
        log('Threshold updated', j);
        }).catch(e => {
        log('Failed to update threshold', e);
        });
    });

    // ================== INIT ==================
    (async function init() {
        resetStatsUI();
        setStatus('loading', 'Initializing...');
        showOverlay(true);

        // attempt to probe cameras and label once
        await probeCamerasAndLabel();

        // reset backend stats
        try {
        await fetch(`${BASE_URL}/reset_stats`, { method: 'POST' }).catch(()=>{});
        } catch (e) {
        log('reset_stats failed on init', e);
        }

        setStatus('idle', 'Idle');
    })();

    });
