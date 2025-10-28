document.addEventListener('DOMContentLoaded', () => {

// ================== ELEMENT REFERENCES ==================
const totalDetectionsEl = document.getElementById('totalDetections');
const topLitterEl = document.getElementById('topLitter');
const detectionSpeedEl = document.getElementById('detectionSpeed');
const cameraStatusEl = document.getElementById('cameraStatus');
const detectionAccuracyEl = document.getElementById('detectionAccuracy');
const litterTableBody = document.getElementById('litterTableBody');
const thresholdSelect = document.getElementById('threshold-select');

const startBtn = document.getElementById('startBtn');
const stopBtn = document.getElementById('stopBtn');
const refreshBtn = document.getElementById('refresh-btn'); // 🆕 Refresh
const liveFeed = document.getElementById('liveFeed');
const timeEl = document.querySelector('.time');
const statusEl = document.querySelector('.status');

// ================== STATE ==================
let statsInterval = null;
let timerInterval = null;
let startTime = null;
let streaming = false;

// ================== TIMER ==================
function formatTime(ms) {
    const totalSeconds = Math.floor(ms / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    return `${hours}hr ${minutes}mins ${seconds}s`;
}

function startTimer() {
    startTime = Date.now();
    timerInterval = setInterval(() => {
        const elapsed = Date.now() - startTime;
        timeEl.textContent = formatTime(elapsed);
    }, 1000);
}

function stopTimer() {
    clearInterval(timerInterval);
    timerInterval = null;
    timeEl.textContent = '0hr 0mins 0s';
}

// ================== STATUS ==================
function setStatus(state, text) {
    statusEl.className = `status ${state}`;
    let icon = '';
    if (state === 'loading') icon = '<i class="fa-solid fa-circle-notch fa-spin"></i>';
    if (state === 'active') icon = '<i class="fa-solid fa-circle-check"></i>';
    if (state === 'error') icon = '<i class="fa-solid fa-circle-exclamation"></i>';
    if (state === 'idle') icon = '<i class="fa-solid fa-circle-notch"></i>';
    if (state === 'refreshing') icon = '<i class="fa-solid fa-rotate-right fa-spin"></i>';
    statusEl.innerHTML = `${icon} ${text}`;
}

// ================== STATS UI ==================
function updateStatsUI(data) {
    totalDetectionsEl.textContent = data.total || 0;
    detectionSpeedEl.textContent = `${data.speed || 0}s/frame`;
    detectionAccuracyEl.textContent = `${data.accuracy || 0}%`;

    const entries = Object.entries(data.classes || {});
    if (entries.length > 0) {
        const sorted = entries.sort((a, b) => b[1] - a[1]);
        const [topClass, topCount] = sorted[0];
        const topPercent = ((topCount / data.total) * 100).toFixed(0);
        topLitterEl.innerHTML = `${topPercent}% <br><em>${topClass}</em>`;
    } else {
        topLitterEl.innerHTML = `--`;
    }

    litterTableBody.innerHTML = '';
    entries.forEach(([cls, count]) => {
        const row = document.createElement('tr');
        row.innerHTML = `<td>${cls}</td><td>${count}</td>`;
        litterTableBody.appendChild(row);
    });

    cameraStatusEl.textContent = streaming ? 'Active' : 'Idle';
    cameraStatusEl.classList.toggle('active', streaming);
}

function resetStatsUI() {
    totalDetectionsEl.textContent = '0';
    detectionSpeedEl.textContent = '0s/frame';
    detectionAccuracyEl.textContent = '0%';
    topLitterEl.innerHTML = '--';
    litterTableBody.innerHTML = '';
    cameraStatusEl.textContent = 'Idle';
    cameraStatusEl.classList.remove('active');
}

// ================== STATS FETCH ==================
function startStatsUpdates() {
    if (!statsInterval) {
        statsInterval = setInterval(() => {
            fetch('http://127.0.0.1:5000/live_stats')
                .then(res => res.json())
                .then(data => updateStatsUI(data))
                .catch(err => {
                    console.error('Stats error:', err);
                    setStatus('error', 'Camera Error');
                });
        }, 1000);
    }
}

function stopStatsUpdates() {
    clearInterval(statsInterval);
    statsInterval = null;
}

// ================== REFRESH BUTTON ==================
function updateRefreshButton() {
    refreshBtn.disabled = !streaming;
}

refreshBtn.addEventListener('click', () => {
    if (streaming) {
        setStatus('refreshing', 'Refreshing...');
        liveFeed.src = `http://127.0.0.1:5000/live?cb=${Date.now()}`;
    }
});

// ================== CAMERA CONTROL ==================
startBtn.addEventListener('click', () => {
    if (!streaming) {
        streaming = true;
        updateRefreshButton();
        setStatus('loading', 'Starting...');

        // 🆕 Send threshold to backend
        const selectedThreshold = parseFloat(thresholdSelect.value);
        fetch('http://127.0.0.1:5000/set_threshold', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ threshold: selectedThreshold })
        })
        .then(res => res.json())
        .then(data => {
            console.log('✅ Threshold updated:', data.threshold);

            // Start live stream
            liveFeed.src = `http://127.0.0.1:5000/live?cb=${Date.now()}`;
            liveFeed.style.display = 'block';
            startTimer();
            startStatsUpdates();

            // fallback if onload doesn't fire
            setTimeout(() => {
                if (streaming && statusEl.textContent.includes('Idle')) {
                    setStatus('active', 'Active');
                }
            }, 2000);
        })
        .catch(err => {
            console.error('❌ Failed to update threshold:', err);
            setStatus('error', 'Camera Error');
        });
    }
});

stopBtn.addEventListener('click', () => {
    if (streaming) {
        streaming = false;
        updateRefreshButton();
        fetch('http://127.0.0.1:5000/stop_camera').catch(() => {});
        fetch('http://127.0.0.1:5000/reset_stats', { method: 'POST' }).catch(() => {});
        liveFeed.src = '';
        liveFeed.style.display = 'none';
        stopTimer();
        stopStatsUpdates();
        resetStatsUI();
        setStatus('idle', 'Idle');
    }
});

// ================== STREAM EVENTS ==================
liveFeed.onload = () => {
    if (streaming) {
        setStatus('active', 'Active');
    }
};

liveFeed.onerror = () => {
    setStatus('error', 'Camera Error');
    stopTimer();
    stopStatsUpdates();
    streaming = false;
    resetStatsUI();
    updateRefreshButton();
};

// ================== PAGE LOAD ==================
fetch('http://127.0.0.1:5000/live_stats')
    .then(res => res.json())
    .then(data => updateStatsUI(data))
    .catch(err => {
        console.error('Stats error:', err);
        setStatus('error', 'Camera Error');
    });

// 🧼 Reset stats on page load to prevent old values
window.addEventListener('load', () => {
    fetch('http://127.0.0.1:5000/reset_stats', { method: 'POST' }).catch(() => {});
    updateRefreshButton();
});

// ================== THRESHOLD SELECT ==================
thresholdSelect.addEventListener('change', () => {
    if (streaming) {
        const selectedThreshold = parseFloat(thresholdSelect.value);
        fetch('http://127.0.0.1:5000/set_threshold', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ threshold: selectedThreshold })
        })
        .then(res => res.json())
        .then(data => console.log('✅ Threshold live updated:', data.threshold))
        .catch(err => console.error('❌ Failed to update threshold:', err));
    }
});

});