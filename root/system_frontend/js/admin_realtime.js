/**
 * ================================================================
 * 🧠 LitterLens Realtime Detection — Full Frontend Script
 * ================================================================
 * Controls:
 * - Multi-camera detection & switching
 * - Real-time YOLOv8 streaming stats from Flask backend
 * - Geo-tagging via browser geolocation
 * - Upload of summarized detection session to Supabase (through PHP)
 * - Fully interactive UI: live stats, timers, progress indicators
 *
 * Dependencies:
 *  - Font Awesome icons
 *  - Flask backend endpoints:
 *      GET  /check_camera?camera=<index>
 *      GET  /live?camera=<index>
 *      GET  /stop_camera
 *      GET  /live_stats
 *      POST /reset_stats
 *      POST /set_threshold  (JSON)
 *      GET  /reverse_geocode?lat=<>&lon=<>
 *
 * ================================================================
 */

document.addEventListener("DOMContentLoaded", () => {
  // ================================================================
  // ⚙️ CONFIGURATION
  // ================================================================
  const BASE_URL = "http://127.0.0.1:5000"; // Flask API base URL
  const MAX_PROBE_CAMERAS = 6;              // Check camera indices 0..6
  const STATS_POLL_INTERVAL = 1000;         // ms between each /live_stats poll

  // ================================================================
  // 🔗 DOM ELEMENT REFERENCES
  // ================================================================
  const cameraSelect = document.getElementById("camera-select");
  const startBtn = document.getElementById("startBtn");
  const stopBtn = document.getElementById("stopBtn");
  const refreshBtn = document.getElementById("refresh-btn");
  const thresholdSelect = document.getElementById("threshold-select");
  const liveFeed = document.getElementById("liveFeed");
const statusEl = document.getElementById("realtimeStatus");
console.log("🎯 Detection status element:", statusEl);

  const noCameraOverlay = document.querySelector(".noDisplay");
  const timeEl = document.querySelector(".time");
  const latitudeEl = document.getElementById("latitude");
  const longitudeEl = document.getElementById("longitude");
  const totalDetectionsEl = document.getElementById("totalDetections");
  const topLitterEl = document.getElementById("topLitter");
  const detectionSpeedEl = document.getElementById("detectionSpeed");
  const cameraStatusEl = document.getElementById("cameraStatus");
  const detectionAccuracyEl = document.getElementById("detectionAccuracy");
  const litterTableBody = document.getElementById("litterTableBody");
  const uploadBtn = document.getElementById("uploadDb-btn");

  // ================================================================
  // 🧩 STATE VARIABLES
  // ================================================================
  let streaming = false;             // Whether the camera stream is active
  let timerInterval = null;          // Timer interval reference
  let statsInterval = null;          // Polling interval reference
  let startTime = null;              // Timestamp when session started
  let availableCameraIndices = [];   // Detected camera indices


  // ================================================================
  // 🧠 HELPER FUNCTIONS
  // ================================================================

  /** Debug logging shortcut */
  function log(...args) {
    console.debug("[Realtime]", ...args);
  }

 /** Updates status banner (top of panel) with icon + message */
/** Updates realtime detection status (independent from logs) */
function setStatus(state, text) {
  const icons = {
    loading: '<i class="fa-solid fa-circle-notch fa-spin"></i>',
    uploading: '<i class="fa-solid fa-cloud-arrow-up"></i>',
    active: '<i class="fa-solid fa-circle-check"></i>',
    error: '<i class="fa-solid fa-circle-exclamation"></i>',
    idle: '<i class="fa-solid fa-circle"></i>',
    refreshing: '<i class="fa-solid fa-rotate-right fa-spin"></i>',
  };

  if (!statusEl) return;
  statusEl.className = `realtime-status ${state}`;
  statusEl.innerHTML = `${icons[state] || icons.idle} ${text}`;
}

 
function showOverlay(show = true) {
  if (!noCameraOverlay) return;
  noCameraOverlay.style.display = show ? "flex" : "none";
}


  /** Converts ms → readable hh:mm:ss string */
  function formatTime(ms) {
    const totalSeconds = Math.floor(ms / 1000);
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    return `${h}hr ${m}mins ${s}s`;
  }

  /** Starts session timer */
  function startTimer() {
    startTime = Date.now();
    if (timerInterval) clearInterval(timerInterval);
    timerInterval = setInterval(() => {
      const elapsed = Date.now() - startTime;
      if (timeEl) timeEl.textContent = formatTime(elapsed);
    }, 1000);
  }

  /** Stops session timer */
  function stopTimer() {
    if (timerInterval) clearInterval(timerInterval);
    timerInterval = null;
    if (timeEl) timeEl.textContent = "0hr 0mins 0s";
  }

  /** Resets all detection stats in UI */
  function resetStatsUI() {
    totalDetectionsEl.textContent = "0";
    detectionSpeedEl.textContent = "0s/frame";
    detectionAccuracyEl.textContent = "0%";
    topLitterEl.innerHTML = "--";
    litterTableBody.innerHTML = "";
    cameraStatusEl.textContent = "Idle";
    cameraStatusEl.classList.remove("active");
  }

  // ================================================================
  // 📍 GEOLOCATION + REVERSE GEOCODING
  // ================================================================

  /** Gets GPS coords (Promise-based) */
  async function getCurrentPositionPromise() {
    return new Promise((resolve) => {
      if (!navigator.geolocation) return resolve(null);
      navigator.geolocation.getCurrentPosition(
        (pos) => resolve(pos.coords),
        (err) => {
          console.warn("Geolocation error", err);
          resolve(null);
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
      );
    });
  }

async function getReadableLocationFromProxy(lat, lon) {
  if (!lat || !lon) return "Unknown Area";
  try {
    const res = await fetch(`${BASE_URL}/reverse_geocode?lat=${lat}&lon=${lon}`, {
      cache: "no-store",
    });
    if (!res.ok) return "Unknown Area";
    const data = await res.json();

    // 🏘️ Barangay + City + Province display
    const barangay = data.barangay || "";
    const city = data.city || "";
    const province = data.province || "";
    const country = data.country || "";
    const parts = [barangay, city, province, country].filter(Boolean);

    return parts.join(", ") || data.display_name || "Unknown Area";
  } catch (e) {
    console.warn("Reverse geocode failed:", e);
    return "Unknown Area";
  }
}

 
/**
 * ======================================================
 * 🧩 Backend Camera Checker — Helper
 * ======================================================
 * Calls the Flask /check_camera endpoint to verify
 * if a given camera index is available.
 * Returns: true (if working) or false (if not)
 */
async function backendCheckCamera(index) {
  try {
    const res = await fetch(`${BASE_URL}/check_camera?camera=${index}`, { cache: "no-store" });
    if (!res.ok) return false;

    const data = await res.json();
    return !!data.detected; // true if { "detected": true }
  } catch (e) {
    console.warn(`⚠️ Camera probe error for index ${index}:`, e);
    return false;
  }
}
  // ================================================================
  // 🎥 CAMERA CHECK + PROBING
  // ================================================================

/** Probes cameras and populates dropdown with detected devices */
async function probeCamerasAndLabel() {
  setStatus("loading", "Probing cameras & location...");
  showOverlay(true);
  cameraSelect.innerHTML = "";

  // === Get location once ===
  let coords = await getCurrentPositionPromise();
  let labelText = "Unknown Area";

  if (coords) {
    latitudeEl.textContent = coords.latitude.toFixed(6);
    longitudeEl.textContent = coords.longitude.toFixed(6);
    labelText = await getReadableLocationFromProxy(coords.latitude, coords.longitude);
  }

  // 🧭 Save for reuse
  lastLocationLabel = labelText;

  // === Probe available cameras ===
  const checks = [];
  for (let i = 0; i <= MAX_PROBE_CAMERAS; i++) {
    checks.push(backendCheckCamera(i).then((ok) => ({ idx: i, ok })));
  }

  const results = await Promise.all(checks);
  availableCameraIndices = results.filter((r) => r.ok).map((r) => r.idx);

  if (!availableCameraIndices.length) {
    cameraSelect.innerHTML = "<option>No cameras found</option>";
    cameraSelect.disabled = true;
    startBtn.disabled = true;
    setStatus("error", "No cameras available");
    showOverlay(true);
    return;
  }

  // === Populate dropdown ===
  cameraSelect.disabled = false;
  cameraSelect.innerHTML = "";

  availableCameraIndices.forEach((idx) => {
    const opt = document.createElement("option");

    // 👇 Smartly format: Barangay first if available
    let displayLabel = labelText;
    if (displayLabel.includes("Barangay")) {
      displayLabel = displayLabel.replace(/^Barangay\s*/i, "Brgy. ");
    }

    opt.value = idx;
    opt.textContent = `📷 Camera ${idx} • ${displayLabel}`;
    cameraSelect.appendChild(opt);
  });

  startBtn.disabled = false;
  setStatus("idle", "Select camera");
  showOverlay(false);
}

  // ================================================================
  // 🧮 LIVE STATS POLLING + UI UPDATE
  // ================================================================

  function updateStatsUI(data) {
    if (!data) return;
    totalDetectionsEl.textContent = data.total ?? 0;
    detectionSpeedEl.textContent = `${data.speed ?? 0}s/frame`;
    detectionAccuracyEl.textContent = `${data.accuracy ?? 0}%`;

    const entries = Object.entries(data.classes || {});
    if (entries.length) {
      entries.sort((a, b) => b[1] - a[1]);
      const [topClass, topCount] = entries[0];
      const topPercent = data.total
        ? Math.round((topCount / data.total) * 100)
        : 0;
      topLitterEl.innerHTML = `${topPercent}% <br><em>${topClass}</em>`;
    } else {
      topLitterEl.innerHTML = "--";
    }

    litterTableBody.innerHTML = "";
    entries.forEach(([cls, count]) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td>${cls}</td><td>${count}</td>`;
      litterTableBody.appendChild(tr);
    });

    cameraStatusEl.textContent = streaming ? "Active" : "Idle";
    cameraStatusEl.classList.toggle("active", streaming);
  }

  function startStatsPolling() {
    if (statsInterval) return;
    statsInterval = setInterval(async () => {
      try {
        const res = await fetch(`${BASE_URL}/live_stats`, { cache: "no-store" });
        if (!res.ok) throw new Error();
        const data = await res.json();
        updateStatsUI(data);
      } catch (e) {
        setStatus("error", "Stats error");
      }
    }, STATS_POLL_INTERVAL);
  }

  function stopStatsPolling() {
    if (statsInterval) clearInterval(statsInterval);
    statsInterval = null;
  }

  // ================================================================
  // 🎬 STREAM CONTROL
  // ================================================================

  async function startStreamForCamera(camIndex) {
    await fetch(`${BASE_URL}/stop_camera`).catch(() => {});
    await fetch(`${BASE_URL}/reset_stats`, { method: "POST" }).catch(() => {});

    // 🕒 Give camera hardware 1s cooldown before starting stream
    await new Promise((res) => setTimeout(res, 1000));

    const thr = parseFloat(thresholdSelect?.value ?? 0.25) || 0.25;
    await fetch(`${BASE_URL}/set_threshold`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ threshold: thr }),
    }).catch(() => {});

    streaming = true;
    setStatus("loading", "Starting stream...");
    showOverlay(false);

    const url = `${BASE_URL}/live?camera=${encodeURIComponent(camIndex)}&cb=${Date.now()}`;
    liveFeed.src = url;
    liveFeed.style.display = "block";
    startTimer();
    startStatsPolling();
    setTimeout(() => setStatus("active", "Active"), 800);
  }

  async function startStream() {
    const camVal = cameraSelect?.value;
    if (!camVal) return alert("Select a camera first.");
    await startStreamForCamera(camVal);
  }

  async function stopStream() {
    if (!streaming) return;
    setStatus("loading", "Stopping stream...");
    streaming = false;

    await fetch(`${BASE_URL}/stop_camera`).catch(() => {});
    // ⚠️ No reset here — keep stats for upload
    stopTimer();
    stopStatsPolling();

    liveFeed.src = "";
    liveFeed.style.display = "none";
    showOverlay(true);
    setTimeout(() => setStatus("idle", "Stopped"), 400);
  }

  function refreshStream() {
    if (!streaming) return;
    setStatus("refreshing", "Refreshing stream...");
    const camVal = cameraSelect.value || "";
    liveFeed.src = `${BASE_URL}/live?camera=${encodeURIComponent(camVal)}&cb=${Date.now()}`;
  }

  // ================================================================
  // 📤 UPLOAD SESSION TO DATABASE (SUPABASE)
  // ================================================================
uploadBtn?.addEventListener("click", async () => {
  try {
    // 🟡 Indicate preparation
    setStatus("loading", "Preparing upload...");
    showOverlay(true);

    // Fetch live stats
    const statsRes = await fetch(`${BASE_URL}/live_stats`, { cache: "no-store" });
    if (!statsRes.ok) throw new Error("Stats not available");
    const stats = await statsRes.json();

    const totalDetections = stats.total || 0;
    const detectionAccuracy = stats.accuracy || 0;
    const detectionSpeed = stats.speed ? `${stats.speed}s/frame` : "0s/frame";
    const topDetected =
      Object.keys(stats.classes || {}).length
        ? Object.entries(stats.classes).sort((a, b) => b[1] - a[1])[0][0]
        : "--";
    const litterSummary = stats.classes || {};
    const latitude = latitudeEl.textContent || null;
    const longitude = longitudeEl.textContent || null;
    const cameraName =
      cameraSelect?.options[cameraSelect.selectedIndex]?.text || "Local Camera";

    const sessionData = {
      totalDetections,
      topDetectedLitter: topDetected,
      detectionSpeed,
      detectionAccuracy,
      litterSummary,
      latitude,
      longitude,
      cameraName,
    };

    const summaryMsg = `
📊 Total Detections: ${totalDetections}
🏷 Top Litter: ${topDetected}
⚡ Speed: ${detectionSpeed}
🎯 Accuracy: ${detectionAccuracy}%
📍 Location: ${latitude}, ${longitude}
📹 Camera: ${cameraName}
    `;

    if (!confirm("Upload this detection session?\n\n" + summaryMsg)) {
      setStatus("idle", "Upload cancelled");
      showOverlay(false);
      return;
    }

    // 🟢 Uploading
    setStatus("loading", "Uploading data to database...");
    const uploadRes = await fetch("/LitterLensThesis2/root/system_backend/php/upload_realtime.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(sessionData),
    });

    const result = await uploadRes.json();

    if (result.success) {
      showModal("✅ Realtime session uploaded successfully!", "#5cb85c");
      await fetch(`${BASE_URL}/reset_stats`, { method: "POST" }).catch(() => {});
      resetStatsUI();

      // ✅ Reset UI back to idle cleanly
      setTimeout(() => {
        setStatus("idle", "Idle");
        showOverlay(false);
      }, 1000);
    } else {
      showModal("❌ Upload failed. " + (result.error || ""), "#d9534f");
      setTimeout(() => {
        setStatus("error", "Upload Failed");
        showOverlay(false);
        // Automatically return to idle after 3 seconds
        setTimeout(() => setStatus("idle", "Idle"), 3000);
      }, 500);
    }
  } catch (err) {
    console.error("❌ Upload Error:", err);
    showModal("❌ Failed to upload session.", "#d9534f");
    setStatus("error", "Upload Error");
    showOverlay(false);
    setTimeout(() => setStatus("idle", "Idle"), 3000);
  }
});



  // ================================================================
  // 💬 MODAL CREATOR (Feedback UI)
  // ================================================================

  function showModal(message, bgColor = "#333") {
    let modal = document.getElementById("feedback-modal");
    let msg = document.getElementById("feedback-message");

    if (!modal) {
      modal = document.createElement("div");
      modal.id = "feedback-modal";
      Object.assign(modal.style, {
        position: "fixed",
        top: 0,
        left: 0,
        width: "100%",
        height: "100%",
        background: "rgba(0,0,0,0.5)",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        zIndex: "9999",
      });

      const content = document.createElement("div");
      Object.assign(content.style, {
        background: "#fff",
        padding: "20px 30px",
        borderRadius: "10px",
        textAlign: "center",
        minWidth: "300px",
      });

      msg = document.createElement("p");
      msg.id = "feedback-message";
      Object.assign(msg.style, { fontSize: "16px", marginBottom: "15px" });

      const closeBtn = document.createElement("button");
      closeBtn.textContent = "Close";
      Object.assign(closeBtn.style, {
        background: bgColor,
        color: "#fff",
        border: "none",
        padding: "8px 20px",
        borderRadius: "5px",
        cursor: "pointer",
      });
      closeBtn.addEventListener("click", () => (modal.style.display = "none"));

      content.appendChild(msg);
      content.appendChild(closeBtn);
      modal.appendChild(content);
      document.body.appendChild(modal);
    }

    msg.textContent = message;
    modal.querySelector("button").style.background = bgColor;
    modal.style.display = "flex";
  }

  // ================================================================
  // 🚀 INIT SEQUENCE
  // ================================================================
  (async function init() {
    resetStatsUI();
    setStatus("loading", "Initializing...");
    showOverlay(true);
    await probeCamerasAndLabel();
    await fetch(`${BASE_URL}/reset_stats`, { method: "POST" }).catch(() => {});
    setStatus("idle", "Idle");
  })();

  // ================================================================
  // 🧭 EVENT HANDLERS
  // ================================================================
  startBtn?.addEventListener("click", async () => {
    await probeCamerasAndLabel();
    await startStream();
  });

  stopBtn?.addEventListener("click", stopStream);
  refreshBtn?.addEventListener("click", refreshStream);

  thresholdSelect?.addEventListener("change", () => {
    if (!streaming) return;
    const thr = parseFloat(thresholdSelect.value || 0.25);
    fetch(`${BASE_URL}/set_threshold`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ threshold: thr }),
    }).catch(() => {});
  });
});
