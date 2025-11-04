// gallery.js
// ================================
// LitterLens — gallery / results page logic
// ================================
// Features:
// - Debug console
// - Role/session handling (admin vs guest)
// - Go back redirect (admin -> admin.php, guest -> index.php)
// - Redetect / Rerender via Flask endpoints
// - Upload to Supabase (uses admin id when available, avoids duplicates)
// - PDF export (jsPDF)
// - Zoom & pan
// - Image gallery navigation
// - Spinner overlay
// - Defensive checks
// ================================

document.addEventListener("DOMContentLoaded", () => {
  // ----------------------------
  // Config
  // ----------------------------
  const FLASK_BASE = "http://127.0.0.1:5000";
  const ADMIN_REDIRECT = "http://localhost/LitterLensThesis2/root/system_frontend/php/admin.php";
  const USER_REDIRECT = "index.php";

  const SUPABASE_URL = "https://ksbgdgqpdoxabdefjsin.supabase.co";
  const SUPABASE_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtzYmdkZ3FwZG94YWJkZWZqc2luIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2MTAzMjUxOSwiZXhwIjoyMDc2NjA4NTE5fQ.WAai4nbsqgbe-7PgOw8bktVjk0V9Cm8sdEct_vlQCcY";
  const SUPABASE_REST_URL = `${SUPABASE_URL}/rest/v1`;
  const SUPABASE_STORAGE_URL = `${SUPABASE_URL}/storage/v1/object`;

  // ----------------------------
  // Utility
  // ----------------------------
  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => Array.from(document.querySelectorAll(sel));

  // ----------------------------
  // 🪲 Debug Console
  // ----------------------------
  const debugPanel = document.createElement("div");
  Object.assign(debugPanel.style, {
    position: "fixed",
    bottom: "12px",
    right: "12px",
    width: "420px",
    height: "240px",
    background: "#0b0b0b",
    color: "#e6eef4",
    fontFamily: "monospace",
    fontSize: "12px",
    padding: "8px",
    borderRadius: "8px",
    overflowY: "auto",
    zIndex: "99999",
    boxShadow: "0 8px 30px rgba(0,0,0,0.6)",
    border: "1px solid rgba(255,255,255,0.04)"
  });
  debugPanel.innerHTML = `<div style="display:flex;justify-content:space-between;align-items:center;">
    <strong style="color:#4ade80">🧠 LitterLens Debug Console</strong>
    <button id="dbg-clear-btn" style="background:#222;color:#fff;border:none;padding:4px 8px;border-radius:4px;cursor:pointer">Clear</button>
  </div><hr style="border-color:#222;margin:6px 0 8px 0">`;
  document.body.appendChild(debugPanel);

  const dbgClearBtn = document.getElementById("dbg-clear-btn");
  dbgClearBtn?.addEventListener("click", () => {
    Array.from(debugPanel.children).slice(1).forEach(n => n.remove());
  });

  function debugLog(message, type = "info") {
    const time = new Date().toLocaleTimeString();
    const line = document.createElement("div");
    line.style.marginBottom = "6px";
    line.innerText = `[${time}] ${message}`;
    if (type === "success") line.style.color = "#4ade80";
    if (type === "error") line.style.color = "#f87171";
    if (type === "warn") line.style.color = "#facc15";
    debugPanel.appendChild(line);
    debugPanel.scrollTop = debugPanel.scrollHeight;
    if (type === "error") console.error(message);
    else if (type === "warn") console.warn(message);
    else console.log(message);
  }
  debugLog("✅ gallery.js loaded", "success");

  // ----------------------------
  // DOM Elements
  // ----------------------------
  const imgElement = $(".main-img");
  const prevBtn = $(".prev-btn");
  const nextBtn = $(".next-btn");
  const fileNameElement = $("#file-name-display");
  const thresholdDropdown = $(".threshold-dropdown");
  const opacityDropdown = $("#opacityDropdown");
  const switchInput = $(".switch input");
  const goBackBtn = $("#go-back-btn");
  const imgContainer = $(".img-container");
  const labelModeDropdown = $(".label-mode-dropdown");
  const uploadBtn = $("#upload-btn");
  const pdfButton = $("#download-pdf");
  const zoomInBtn = $("#zoom-in");
  const zoomOutBtn = $("#zoom-out");
  const zoomResetBtn = $("#zoom-reset");
  const zoomLevelDisplay = $("#zoom-level");

  if (!imgElement) {
    debugLog("🚨 .main-img element not found — script will exit.", "error");
    return;
  }

// ----------------------------
// Session / Role detection (smart guest fallback)
// ----------------------------

// Check if this page load is a hard refresh (not navigation or redirect)
const navEntry = performance.getEntriesByType("navigation")[0];
const isHardReload =
  (performance.navigation && performance.navigation.type === performance.navigation.TYPE_RELOAD) ||
  (navEntry && navEntry.type === "reload");

// ✅ Only clear admin session if user *manually refreshes the page*
if (isHardReload) {
  console.warn("🔄 Page refreshed manually — forcing guest session.");
  localStorage.removeItem("admin_id");
  localStorage.removeItem("admin_name");
}

const adminId = window.currentAdminId || localStorage.getItem("admin_id") || null;
const adminName = window.currentAdminName || localStorage.getItem("admin_name") || null;
const isLoggedIn = adminId && adminName && adminId !== "null" && adminId !== "undefined";

if (!isLoggedIn) {
  localStorage.removeItem("admin_id");
  localStorage.removeItem("admin_name");
  debugLog("Guest mode (no active session)", "warn");
} else {
  debugLog(`Admin: ${adminName} (ID: ${adminId})`);
}

debugLog(`Session: ${isLoggedIn ? "Admin logged in" : "Guest (normal navigation)"}`);
if (isLoggedIn) debugLog(`Admin: ${adminName} (ID: ${adminId})`);


  // ----------------------------
  // State
  // ----------------------------
  let currentLabelMode = localStorage.getItem("labelMode") || "confidence";
  let currentOpacity = localStorage.getItem("boxOpacity") || "1.00";
  localStorage.setItem("boxOpacity", currentOpacity);

  let detectionResult = JSON.parse(localStorage.getItem("detectionResult") || "null");
  let currentIndex = 0;
  let showingResult = true;

  // ----------------------------
  // Spinner
  // ----------------------------
  const spinnerOverlay = document.createElement("div");
  spinnerOverlay.classList.add("spinner-overlay");
  Object.assign(spinnerOverlay.style, {
    position: "absolute",
    inset: "0",
    display: "none",
    alignItems: "center",
    justifyContent: "center",
    background: "rgba(0,0,0,0.45)",
    color: "#fff",
    zIndex: 9998
  });
  spinnerOverlay.innerHTML = `<div style="text-align:center">
    <div class="spinner" style="width:36px;height:36px;border:4px solid rgba(255,255,255,0.08);border-top-color:#4ade80;border-radius:50%;margin:0 auto 8px auto;animation:spin 1s linear infinite"></div>
    <div class="spinner-text" style="font-size:13px">Updating...</div>
  </div>`;
  if (imgContainer) imgContainer.style.position = imgContainer.style.position || "relative";
  imgContainer?.appendChild(spinnerOverlay);
  const spinnerText = spinnerOverlay.querySelector(".spinner-text");
  const showSpinner = (text = "Updating...") => {
    if (spinnerText) spinnerText.textContent = text;
    spinnerOverlay.style.display = "flex";
  };
  const hideSpinner = () => (spinnerOverlay.style.display = "none");

  const styleEl = document.createElement("style");
  styleEl.innerHTML = `@keyframes spin{to{transform:rotate(360deg)}} .accuracy-good{color:#16a34a} .accuracy-medium{color:#f59e0b} .accuracy-low{color:#ef4444}`;
  document.head.appendChild(styleEl);

  // ----------------------------
  // UI helpers
  // ----------------------------
  function updateImageDisplay(src) {
    if (!imgElement) return;
    imgElement.src = src + `?t=${Date.now()}`;
    if (fileNameElement) {
      const filename = src.split("/").pop();
      fileNameElement.textContent = filename.length > 20 ? filename.substring(0, 20) + "..." : filename;
    }
  }

  function updateTable(summary = {}, total = 0) {
    const table = document.querySelector(".receipt table");
    const title = document.querySelector(".receipt-card h2");
    if (!table || !title) return;
    title.textContent = `${total || 0} Items detected`;
    table.querySelectorAll("tr:not(:first-child)").forEach((tr) => tr.remove());
    if (!summary || Object.keys(summary).length === 0) {
      const row = document.createElement("tr");
      row.innerHTML = `<td>No items</td><td>0</td>`;
      table.appendChild(row);
    } else {
      Object.entries(summary).forEach(([label, count]) => {
        const row = document.createElement("tr");
        row.innerHTML = `<td>${label}</td><td>${count}</td>`;
        table.appendChild(row);
      });
    }
  }

  function updateAccuracy(accuracy) {
    const el = document.querySelector(".accuracy");
    if (!el) return;
    const value = Number(accuracy || 0);
    el.textContent = `Detection Accuracy: ${value}%`;
    el.classList.remove("accuracy-good", "accuracy-medium", "accuracy-low");
    if (value >= 80) el.classList.add("accuracy-good");
    else if (value >= 50) el.classList.add("accuracy-medium");
    else el.classList.add("accuracy-low");
  }

  function showImage(index) {
    if (!detectionResult?.results?.length) return;
    if (index < 0) index = 0;
    if (index >= detectionResult.results.length) index = detectionResult.results.length - 1;
    currentIndex = index;
    const imgData = detectionResult.results[currentIndex];
    const src = showingResult
      ? `${FLASK_BASE}/${imgData.result_image}`
      : `${FLASK_BASE}/${imgData.original_image}`;
    updateImageDisplay(src);
    updateTable(imgData.summary || {}, imgData.total_items || 0);
    updateAccuracy(imgData.accuracy ?? detectionResult.accuracy);
  }

  // Initialize
  if (detectionResult?.results?.length > 0) {
    if (labelModeDropdown) labelModeDropdown.value = detectionResult.label_mode || currentLabelMode;
    if (opacityDropdown) opacityDropdown.value = currentOpacity;
    showImage(currentIndex);
  }

  // ----------------------------
  // Networking
  // ----------------------------
  async function postJson(url, payload) {
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });
    return res.json();
  }

  // ----------------------------
  // Redetect / Rerender
  // ----------------------------
  async function redetect(newThreshold, labelMode = currentLabelMode, opacity = currentOpacity) {
    if (!detectionResult?.folder) return;
    showSpinner("Re-running detection...");
    try {
      const data = await postJson(`${FLASK_BASE}/redetect`, {
        folder: detectionResult.folder,
        threshold: newThreshold,
        label_mode: labelMode,
        opacity
      });
      hideSpinner();
      detectionResult = { ...detectionResult, ...data };
      localStorage.setItem("detectionResult", JSON.stringify(detectionResult));
      showImage(currentIndex);
      debugLog("Redetect complete", "success");
    } catch (err) {
      hideSpinner();
      debugLog("Redetect failed: " + err.message, "error");
    }
  }

  async function rerender(labelMode = currentLabelMode, opacity = currentOpacity) {
    if (!detectionResult?.folder) return;
    showSpinner("Redrawing boxes...");
    try {
      const data = await postJson(`${FLASK_BASE}/rerender`, {
        folder: detectionResult.folder,
        label_mode: labelMode,
        opacity
      });
      hideSpinner();
      detectionResult = { ...detectionResult, ...data };
      localStorage.setItem("detectionResult", JSON.stringify(detectionResult));
      showImage(currentIndex);
    } catch (err) {
      hideSpinner();
      debugLog("Rerender failed: " + err.message, "error");
    }
  }

  // ----------------------------
  // Dropdowns
  // ----------------------------
  thresholdDropdown?.addEventListener("change", async () => {
    const newThreshold = parseFloat(thresholdDropdown.value);
    try {
      const res = await postJson(`${FLASK_BASE}/set_threshold`, { threshold: newThreshold });
      debugLog(`Backend threshold set to ${res.threshold}`, "success");
      await redetect(newThreshold);
    } catch (err) {
      debugLog("Failed to set threshold: " + err.message, "error");
    }
  });

  opacityDropdown?.addEventListener("change", () => {
    currentOpacity = opacityDropdown.value;
    localStorage.setItem("boxOpacity", currentOpacity);
    rerender(currentLabelMode, currentOpacity);
  });

  labelModeDropdown?.addEventListener("change", () => {
    currentLabelMode = labelModeDropdown.value;
    localStorage.setItem("labelMode", currentLabelMode);
    rerender(currentLabelMode, currentOpacity);
  });

  // ----------------------------
  // Prev / Next
  // ----------------------------
  prevBtn?.addEventListener("click", () => {
    if (!detectionResult?.results?.length) return;
    currentIndex = (currentIndex - 1 + detectionResult.results.length) % detectionResult.results.length;
    showImage(currentIndex);
  });

  nextBtn?.addEventListener("click", () => {
    if (!detectionResult?.results?.length) return;
    currentIndex = (currentIndex + 1) % detectionResult.results.length;
    showImage(currentIndex);
  });

  // ----------------------------
  // Before/After
  // ----------------------------
  if (switchInput) {
    switchInput.checked = true;
    switchInput.addEventListener("change", (e) => {
      showingResult = e.target.checked;
      showImage(currentIndex);
    });
  }

  // ----------------------------
  // Go Back
  // ----------------------------
  goBackBtn?.addEventListener("click", async () => {
    const detectionSource = localStorage.getItem("detectionSource");
    const shouldAdminRedirect = detectionSource === "admin" || isLoggedIn;
    const target = shouldAdminRedirect ? ADMIN_REDIRECT : USER_REDIRECT;
    if (detectionResult?.folder) {
      try {
        await fetch(`${FLASK_BASE}/cleanup`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ folder: detectionResult.folder })
        });
      } catch {}
    }
    localStorage.removeItem("detectionResult");
    localStorage.removeItem("detectionSource");
    window.location.href = target;
  });

  // ----------------------------
  // Supabase Upload (with duplicate protection)
  // ----------------------------
  async function findImageRecordByName(fileName) {
    const q = `${SUPABASE_REST_URL}/images?imagefile_name=eq.${encodeURIComponent(fileName)}`;
    const res = await fetch(q, {
      headers: { apikey: SUPABASE_KEY, Authorization: `Bearer ${SUPABASE_KEY}` }
    });
    const rows = await res.json();
    return rows.length > 0 ? rows[0] : null;
  }

  async function uploadAllImagesToBucket(results) {
    const uploadedImages = [];
    for (const imgData of results) {
      try {
        const path = imgData.result_image || imgData.original_image || "";
        const fileName = path.split("/").pop();
        let existingRecord = await findImageRecordByName(fileName);
        if (existingRecord) {
          uploadedImages.push({
            fileName,
            publicUrl: `${SUPABASE_URL}/storage/v1/object/public/images/${fileName}`,
            existingImageRecord: existingRecord
          });
          continue;
        }
        const imageUrl = `${FLASK_BASE}/${path}`;
        const response = await fetch(imageUrl);
        const blob = await response.blob();
        const uploadUrl = `${SUPABASE_STORAGE_URL}/images/${fileName}`;
        const uploadRes = await fetch(uploadUrl, {
          method: "POST",
          headers: {
            apikey: SUPABASE_KEY,
            Authorization: `Bearer ${SUPABASE_KEY}`,
            "Content-Type": "image/jpeg",
            "x-upsert": "true"
          },
          body: blob
        });
        if (!uploadRes.ok) throw new Error(`Upload failed for ${fileName}`);
        const publicUrl = `${SUPABASE_URL}/storage/v1/object/public/images/${fileName}`;
        uploadedImages.push({ fileName, publicUrl });
      } catch (err) {
        debugLog("Upload failed: " + err.message, "error");
      }
    }
    return uploadedImages;
  }

  async function getLitterTypeId(name) {
    const getRes = await fetch(`${SUPABASE_REST_URL}/litter_types?littertype_name=eq.${encodeURIComponent(name)}`, {
      headers: { apikey: SUPABASE_KEY, Authorization: `Bearer ${SUPABASE_KEY}` }
    });
    const existing = await getRes.json();
    if (existing.length > 0) return existing[0].littertype_id;
    const postRes = await fetch(`${SUPABASE_REST_URL}/litter_types`, {
      method: "POST",
      headers: {
        apikey: SUPABASE_KEY,
        Authorization: `Bearer ${SUPABASE_KEY}`,
        "Content-Type": "application/json",
        Prefer: "return=representation"
      },
      body: JSON.stringify({ littertype_name: name })
    });
    const newType = await postRes.json();
    return newType[0].littertype_id;
  }

  async function storeDetectionToSupabase(detectionResult) {
    try {
      const folderKey = detectionResult?.folder || "defaultFolder";
      if (localStorage.getItem(`uploaded_${folderKey}`)) {
        debugLog("Already uploaded previously.", "warn");
        alert("Already uploaded previously.");
        return;
      }

      const lat = localStorage.getItem("user_latitude");
      const lng = localStorage.getItem("user_longitude");
      const uploads = await uploadAllImagesToBucket(detectionResult.results);
      const now = new Date();
      const dateStr = now.toISOString().split("T")[0];
      const timeStr = now.toTimeString().split(" ")[0];

      for (let i = 0; i < uploads.length; i++) {
        const imgUpload = uploads[i];
        const imgData = detectionResult.results[i];
        let imageId = imgUpload.existingImageRecord?.image_id || null;
        if (!imageId) {
          const imgPayload = {
            imagefile_name: imgUpload.fileName,
            uploaded_by: isLoggedIn ? parseInt(adminId) : null,
            latitude: parseFloat(lat) || null,
            longitude: parseFloat(lng) || null
          };
          const imgRes = await fetch(`${SUPABASE_REST_URL}/images`, {
            method: "POST",
            headers: {
              apikey: SUPABASE_KEY,
              Authorization: `Bearer ${SUPABASE_KEY}`,
              "Content-Type": "application/json",
              Prefer: "return=representation"
            },
            body: JSON.stringify(imgPayload)
          });
          const imgJson = await imgRes.json();
          imageId = imgJson[0]?.image_id;
        }

        for (const [typeName, qty] of Object.entries(imgData.summary || {})) {
          const typeId = await getLitterTypeId(typeName);
          const detCheck = await fetch(
            `${SUPABASE_REST_URL}/detections?image_id=eq.${imageId}&littertype_id=eq.${typeId}&date=eq.${dateStr}`,
            { headers: { apikey: SUPABASE_KEY, Authorization: `Bearer ${SUPABASE_KEY}` } }
          );
          const exist = await detCheck.json();
          if (exist.length > 0) continue;

          const detPayload = {
            image_id: imageId,
            littertype_id: typeId,
            date: dateStr,
            quantity: qty,
            confidence_lvl: parseFloat(imgData.accuracy || detectionResult.accuracy || 0),
            detection_time: timeStr
          };
          await fetch(`${SUPABASE_REST_URL}/detections`, {
            method: "POST",
            headers: {
              apikey: SUPABASE_KEY,
              Authorization: `Bearer ${SUPABASE_KEY}`,
              "Content-Type": "application/json",
              Prefer: "return=representation"
            },
            body: JSON.stringify(detPayload)
          });
        }
      }

      localStorage.setItem(`uploaded_${folderKey}`, "1");
      debugLog("All detections saved to Supabase.", "success");
      alert("Upload complete.");
    } catch (err) {
      debugLog("Upload failed: " + err.message, "error");
      alert("Upload failed: " + err.message);
    }
  }

  uploadBtn?.addEventListener("click", async () => {
    const dr = JSON.parse(localStorage.getItem("detectionResult") || "null");
    if (!dr) {
      alert("No detection results available.");
      return;
    }
    uploadBtn.disabled = true;
    uploadBtn.textContent = "Uploading...";
    await storeDetectionToSupabase(dr);
    uploadBtn.disabled = false;
    uploadBtn.textContent = "Upload to Database";
  });

  // ----------------------------
  // Zoom & Pan
  // ----------------------------
  let currentZoom = 1;
  let isDragging = false;
  let startX = 0,
    startY = 0,
    currentX = 0,
    currentY = 0;
  function updateZoom() {
    imgElement.style.transform = `translate(${currentX}px, ${currentY}px) scale(${currentZoom})`;
    if (zoomLevelDisplay) zoomLevelDisplay.textContent = `${Math.round(currentZoom * 100)}%`;
  }
  zoomInBtn?.addEventListener("click", () => {
    currentZoom = Math.min(currentZoom + 0.1, 3);
    updateZoom();
  });
  zoomOutBtn?.addEventListener("click", () => {
    currentZoom = Math.max(currentZoom - 0.1, 1);
    if (currentZoom === 1) {
      currentX = 0;
      currentY = 0;
    }
    updateZoom();
  });
  zoomResetBtn?.addEventListener("click", () => {
    currentZoom = 1;
    currentX = 0;
    currentY = 0;
    updateZoom();
  });

  imgElement.addEventListener("mousedown", (e) => {
    if (currentZoom <= 1) return;
    isDragging = true;
    startX = e.clientX - currentX;
    startY = e.clientY - currentY;
  });
  window.addEventListener("mouseup", () => {
    isDragging = false;
  });
  window.addEventListener("mousemove", (e) => {
    if (!isDragging) return;
    e.preventDefault();
    const newX = e.clientX - startX;
    const newY = e.clientY - startY;
    currentX = newX;
    currentY = newY;
    updateZoom();
  });

  // ----------------------------
  // PDF Export
  // ----------------------------
  async function convertImageToBase64(url) {
    const response = await fetch(url);
    const blob = await response.blob();
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onloadend = () => resolve(reader.result);
      reader.onerror = reject;
      reader.readAsDataURL(blob);
    });
  }

  pdfButton?.addEventListener("click", async () => {
    const dr = JSON.parse(localStorage.getItem("detectionResult") || "null");
    if (!dr?.results?.length) return;
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({ orientation: "p", unit: "mm", format: "a4" });
    let y = 20;
    pdf.setFontSize(20);
    pdf.text("Litter Detection Report", 105, y, { align: "center" });
    y += 10;
    pdf.setFontSize(12);
    pdf.text(`Generated: ${new Date().toLocaleString()}`, 20, y);
    y += 8;
    for (let i = 0; i < dr.results.length; i++) {
      const imgData = dr.results[i];
      const imgUrl = `${FLASK_BASE}/${imgData.result_image}`;
      pdf.addPage();
      const base64 = await convertImageToBase64(imgUrl);
      const props = pdf.getImageProperties(base64);
      const pdfWidth = 180;
      const pdfHeight = (props.height * pdfWidth) / props.width;
      pdf.addImage(base64, "JPEG", 15, 20, pdfWidth, pdfHeight);
    }
    pdf.save(`Litter_Report_${Date.now()}.pdf`);
  });

  // ----------------------------
  // Default threshold reset
  // ----------------------------
  (async () => {
    try {
      await fetch(`${FLASK_BASE}/set_threshold`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ threshold: 0.1 })
      });
    } catch {}
  })();
});
