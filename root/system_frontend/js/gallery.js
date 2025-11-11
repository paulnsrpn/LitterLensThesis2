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

  // ============================================================
// 🌊 Load Creek List from Supabase (Dynamic Select Options)
// ============================================================
async function loadCreekList() {
  const creekSelect = document.getElementById("creek-select");
  if (!creekSelect) return;

  try {
    const res = await fetch(`${SUPABASE_REST_URL}/creeks?select=creek_name`, {
      headers: {
        apikey: SUPABASE_KEY,
        Authorization: `Bearer ${SUPABASE_KEY}`,
      },
    });

    if (!res.ok) throw new Error("Failed to fetch creeks");
    const creeks = await res.json();

    // 🧹 Reset dropdown (keep only placeholder)
    creekSelect.innerHTML = `
      <option value="" disabled selected>Select creek/river...</option>
    `;

    // 🏞️ Add creeks from Supabase
    creeks.forEach((c) => {
      const opt = document.createElement("option");
      opt.value = c.creek_name;
      opt.textContent = c.creek_name;
      creekSelect.appendChild(opt);
    });

    // ➕ Add “Other” at the end
    const otherOpt = document.createElement("option");
    otherOpt.value = "Other";
    otherOpt.textContent = "Other (Type below)";
    creekSelect.appendChild(otherOpt);

    console.log(`✅ Loaded ${creeks.length} creeks from Supabase`);
  } catch (err) {
    console.error("❌ Error loading creek list:", err);
    creekSelect.innerHTML = `
      <option value="" disabled selected>Select creek/river...</option>
      <option value="Buli">Buli Creek</option>
      <option value="Other">Other (Type below)</option>
    `;
  }
}
  // ----------------------------
  // Config
  // ----------------------------
  const FLASK_BASE = "http://127.0.0.1:5000";
  const ADMIN_REDIRECT =
    "http://localhost/LitterLensThesis2/root/system_frontend/php/admin.php";
  const USER_REDIRECT = "index.php";

  const SUPABASE_URL = "https://ksbgdgqpdoxabdefjsin.supabase.co";
  const SUPABASE_KEY =
    "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtzYmdkZ3FwZG94YWJkZWZqc2luIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2MTAzMjUxOSwiZXhwIjoyMDc2NjA4NTE5fQ.WAai4nbsqgbe-7PgOw8bktVjk0V9Cm8sdEct_vlQCcY";
  const SUPABASE_REST_URL = `${SUPABASE_URL}/rest/v1`;
  const SUPABASE_STORAGE_URL = `${SUPABASE_URL}/storage/v1/object`;

const latVal = localStorage.getItem("user_latitude");
const lngVal = localStorage.getItem("user_longitude");

if (latVal && lngVal) {
  const latElem = document.getElementById("lat-value");
  const lngElem = document.getElementById("lng-value");
  const locElem = document.getElementById("location-name");
  const linkElem = document.getElementById("location-link");

  if (latElem && lngElem && locElem && linkElem) {
    latElem.textContent = parseFloat(latVal).toFixed(4);
    lngElem.textContent = parseFloat(lngVal).toFixed(4);

    // 🗺️ Make the link open Google Maps
    linkElem.href = `https://www.google.com/maps?q=${latVal},${lngVal}`;

    // Fetch readable location (barangay, city, etc.)
    fetch(`${FLASK_BASE}/reverse_geocode?lat=${latVal}&lon=${lngVal}`)
      .then((r) => r.json())
      .then((d) => {
        if (d.error) {
          locElem.textContent = "Unknown area";
          return;
        }

        const parts = [];
        if (d.barangay && d.barangay !== "Unknown") parts.push(d.barangay);
        if (d.city && d.city !== "Unknown") parts.push(d.city);
        if (d.province && d.province !== "Unknown") parts.push(d.province);
        if (d.country && d.country !== "Unknown") parts.push(d.country);

        let text = parts.join(", ") || d.display_name || "Unknown area";

        // 🏞️ Add note for rivers or creeks
        if (d.display_name?.toLowerCase().includes("river") || d.display_name?.toLowerCase().includes("creek")) {
          const landmark = d.display_name.split(",")[0];
          text += ` (near ${landmark})`;
        }

        locElem.textContent = text;
        linkElem.title = text;
      })
      .catch(() => {
        locElem.textContent = "Location unavailable";
      });
  }
}
  

  // ----------------------------
  // Utility
  // ----------------------------
  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => Array.from(document.querySelectorAll(sel));
  // ----------------------------
  // 🧾 Minimal Logging (No Debug Panel)
  // ----------------------------
  function debugLog(message, type = "info") {
    const prefix =
      type === "error" ? "❌" :
      type === "warn" ? "⚠️" :
      type === "success" ? "✅" : "ℹ️";
    if (type === "error") console.error(`${prefix} ${message}`);
    else if (type === "warn") console.warn(`${prefix} ${message}`);
    else console.log(`${prefix} ${message}`);
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
  // Session / Role detection
  // ----------------------------
  const navEntry = performance.getEntriesByType("navigation")[0];
  const isHardReload =
    (performance.navigation &&
      performance.navigation.type === performance.navigation.TYPE_RELOAD) ||
    (navEntry && navEntry.type === "reload");

  if (isHardReload) {
    console.warn("🔄 Page refreshed manually — forcing guest session.");
    localStorage.removeItem("admin_id");
    localStorage.removeItem("admin_name");
  }

  const adminId =
    window.currentAdminId || localStorage.getItem("admin_id") || null;
  const adminName =
    window.currentAdminName || localStorage.getItem("admin_name") || null;
  const isLoggedIn =
    adminId && adminName && adminId !== "null" && adminId !== "undefined";

  if (!isLoggedIn) {
    localStorage.removeItem("admin_id");
    localStorage.removeItem("admin_name");
    debugLog("Guest mode (no active session)", "warn");
  } else {
    debugLog(`Admin: ${adminName} (ID: ${adminId})`);
  }

  debugLog(`Session: ${isLoggedIn ? "Admin logged in" : "Guest mode"}`);

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
  // Spinner Overlay
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
    zIndex: 9998,
  });
  spinnerOverlay.innerHTML = `<div style="text-align:center">
    <div class="spinner" style="width:36px;height:36px;border:4px solid rgba(255,255,255,0.08);border-top-color:#4ade80;border-radius:50%;margin:0 auto 8px auto;animation:spin 1s linear infinite"></div>
    <div class="spinner-text" style="font-size:13px">Updating...</div>
  </div>`;
  if (imgContainer)
    imgContainer.style.position = imgContainer.style.position || "relative";
  imgContainer?.appendChild(spinnerOverlay);
  const spinnerText = spinnerOverlay.querySelector(".spinner-text");
  const showSpinner = (text = "Updating...") => {
    if (spinnerText) spinnerText.textContent = text;
    spinnerOverlay.style.display = "flex";
  };
  const hideSpinner = () => (spinnerOverlay.style.display = "none");

  // CSS Animations
  const styleEl = document.createElement("style");
  styleEl.innerHTML = `@keyframes spin{to{transform:rotate(360deg)}} 
    .accuracy-good{color:#16a34a} 
    .accuracy-medium{color:#f59e0b} 
    .accuracy-low{color:#ef4444}`;
  document.head.appendChild(styleEl);

  // ----------------------------
  // UI helpers
  // ----------------------------
  function updateImageDisplay(src) {
    if (!imgElement) return;
    imgElement.src = src + `?t=${Date.now()}`;
    if (fileNameElement) {
      const filename = src.split("/").pop();
      fileNameElement.textContent =
        filename.length > 20 ? filename.substring(0, 20) + "..." : filename;
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

  // =============================
  // 🖼️ Show Image (with persistence fix)
  // =============================
  let lastSummary = {};
  let lastTotalItems = 0;

  // =============================
  // Updated showImage (always uses per-image data)
  // =============================
  function showImage(index) {
    if (!detectionResult?.results?.length) return;
    if (index < 0) index = 0;
    if (index >= detectionResult.results.length)
      index = detectionResult.results.length - 1;
    currentIndex = index;

    const imgData = detectionResult.results[currentIndex];
    const src = showingResult
      ? `${FLASK_BASE}/${imgData.result_image}`
      : `${FLASK_BASE}/${imgData.original_image}`;
    updateImageDisplay(src);

    // Use this image's summary ALWAYS (no global fallback)
    const summary = imgData.summary || {};
    const totalItems =
      imgData.total_items ?? Object.values(summary).reduce((a, b) => a + b, 0);

    updateTable(summary, totalItems);

    // Always display this image's per-image accuracy (backend returned)
    const accValue = Number(imgData.accuracy ?? 0);
    updateAccuracy(accValue);
  }


  // Initialize
  if (detectionResult?.results?.length > 0) {
    if (labelModeDropdown)
      labelModeDropdown.value = detectionResult.label_mode || currentLabelMode;
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
      body: JSON.stringify(payload),
    });
    return res.json();
  }
// ============================================================
// 🧠 FIXED: Accurate Redetect (Threshold-Based, Fully Synced)
// ============================================================
async function redetect(newThreshold, labelMode = currentLabelMode, opacity = currentOpacity) {
  if (!detectionResult?.folder) return;
  showSpinner("Re-running detection...");

  try {
    const data = await postJson(`${FLASK_BASE}/redetect`, {
      folder: detectionResult.folder,
      threshold: newThreshold,
      label_mode: labelMode,
      opacity,
    });
    hideSpinner();

    if (!data?.results?.length) {
      debugLog("Redetect: No results returned", "warn");
      return;
    }

    // ✅ Replace the entire result set with the new one from backend
    // Each image’s summary, total_items, and accuracy come from backend threshold-based detection
    detectionResult = {
      ...data,
      folder: detectionResult.folder, // keep folder info
    };

    // ✅ Save to localStorage so it persists correctly
    localStorage.setItem("detectionResult", JSON.stringify(detectionResult));

    // ✅ Refresh the currently shown image (shows per-image accuracy & summary)
    showImage(currentIndex);

    debugLog(
      `Redetect complete — threshold ${newThreshold} applied successfully`,
      "success"
    );
  } catch (err) {
    hideSpinner();
    debugLog("Redetect failed: " + (err.message || err), "error");
  }
}

// ============================================================
// 🎨 FIXED: Rerender (Keeps Per-Image Accuracy & Summary)
// ============================================================
async function rerender(labelMode = currentLabelMode, opacity = currentOpacity) {
  if (!detectionResult?.folder) return;
  showSpinner("Redrawing boxes...");

  try {
    const data = await postJson(`${FLASK_BASE}/rerender`, {
      folder: detectionResult.folder,
      label_mode: labelMode,
      opacity,
    });
    hideSpinner();

    if (!data?.results?.length) {
      debugLog("Rerender: No results returned", "warn");
      return;
    }

    // ✅ Merge new visuals (rerender keeps old accuracy — we don’t re-detect)
    const updatedResults = data.results.map((newImg, i) => {
      const oldImg = detectionResult.results?.[i] || {};
      return {
        ...oldImg,
        ...newImg,
        summary: newImg.summary ?? oldImg.summary ?? {},
        total_items:
          newImg.total_items ??
          oldImg.total_items ??
          (newImg.summary
            ? Object.values(newImg.summary).reduce((a, b) => a + b, 0)
            : 0),
        accuracy:
          newImg.accuracy !== undefined
            ? newImg.accuracy
            : oldImg.accuracy ?? 0,
      };
    });

    detectionResult = {
      ...detectionResult,
      results: updatedResults,
      total_summary: data.total_summary || detectionResult.total_summary,
    };

    // ✅ Store and refresh UI
    localStorage.setItem("detectionResult", JSON.stringify(detectionResult));
    showImage(currentIndex);

    debugLog("Rerender complete — per-image accuracy preserved", "success");
  } catch (err) {
    hideSpinner();
    debugLog("Rerender failed: " + (err.message || err), "error");
  }
}

  // =============================
  // 🔄 Before/After Switch (fix total reset)
  // =============================
  if (switchInput) {
    switchInput.checked = true;
    switchInput.addEventListener("change", (e) => {
      showingResult = e.target.checked;
      showImage(currentIndex);

      // ✅ Keep previous total visible when switching display
      updateTable(lastSummary, lastTotalItems);
    });
  }


  // ----------------------------
  // Dropdowns
  // ----------------------------
  thresholdDropdown?.addEventListener("change", async () => {
    const newThreshold = parseFloat(thresholdDropdown.value);
    showSpinner("Re-running detection...");
    try {
      const res = await postJson(`${FLASK_BASE}/set_threshold`, {
        threshold: newThreshold,
      });
      debugLog(`Backend threshold set to ${res.threshold}`, "success");
      await redetect(newThreshold);
    } catch (err) {
      hideSpinner();
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
// Prev / Next (Threshold-Safe Navigation)
// ----------------------------
async function ensureThresholdSynced() {
  try {
    const currentThreshold = parseFloat(
      thresholdDropdown?.value || localStorage.getItem("currentThreshold") || "0.30"
    );
    // Save in localStorage to persist
    localStorage.setItem("currentThreshold", currentThreshold.toFixed(2));

    // Optional: check current backend threshold (optional optimization)
    // For simplicity, just reapply — Flask will ignore if unchanged
    await fetch(`${FLASK_BASE}/set_threshold`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ threshold: currentThreshold }),
    });
  } catch (err) {
    console.warn("⚠️ Failed to sync threshold with backend:", err);
  }
}

prevBtn?.addEventListener("click", async () => {
  if (!detectionResult?.results?.length) return;
  await ensureThresholdSynced();
  currentIndex =
    (currentIndex - 1 + detectionResult.results.length) %
    detectionResult.results.length;
  showImage(currentIndex);
});

nextBtn?.addEventListener("click", async () => {
  if (!detectionResult?.results?.length) return;
  await ensureThresholdSynced();
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

    // ✅ Reset threshold to default before leaving
    try {
      await fetch(`${FLASK_BASE}/set_threshold`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ threshold: 0.30 }),
      });
      debugLog("Threshold reset to default (0.30)", "info");
    } catch {}

    if (detectionResult?.folder) {
      try {
        await fetch(`${FLASK_BASE}/cleanup`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ folder: detectionResult.folder }),
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
      headers: { apikey: SUPABASE_KEY, Authorization: `Bearer ${SUPABASE_KEY}` },
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
            existingImageRecord: existingRecord,
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
            "x-upsert": "true",
          },
          body: blob,
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
    const getRes = await fetch(
      `${SUPABASE_REST_URL}/litter_types?littertype_name=eq.${encodeURIComponent(name)}`,
      { headers: { apikey: SUPABASE_KEY, Authorization: `Bearer ${SUPABASE_KEY}` } }
    );
    const existing = await getRes.json();
    if (existing.length > 0) return existing[0].littertype_id;
    const postRes = await fetch(`${SUPABASE_REST_URL}/litter_types`, {
      method: "POST",
      headers: { 
        apikey: SUPABASE_KEY,
        Authorization: `Bearer ${SUPABASE_KEY}`,
        "Content-Type": "application/json",
        Prefer: "return=representation",
      },
      body: JSON.stringify({ littertype_name: name }),
    });
    const newType = await postRes.json();
    return newType[0].littertype_id;
  }

// ============================================================
// 🌊 Enhanced: Ensure creek exists and store location metadata
// ============================================================
async function getCreekId(name) {
  if (!name) return null;

  // Fetch coordinates
  const lat = localStorage.getItem("user_latitude");
  const lng = localStorage.getItem("user_longitude");

  // Fetch readable address for more context
  let geo = {};
  try {
    const res = await fetch(`${FLASK_BASE}/reverse_geocode?lat=${lat}&lon=${lng}`);
    geo = await res.json();
  } catch (err) {
    console.warn("⚠️ Reverse geocode failed for creek metadata:", err);
  }

  // Extract optional location data
  const barangay = geo.barangay && geo.barangay !== "Unknown" ? geo.barangay : null;
  const city = geo.city && geo.city !== "Unknown" ? geo.city : null;
  const province = geo.province && geo.province !== "Unknown" ? geo.province : null;
  const coordinates = lat && lng ? `${lat},${lng}` : null;

  // 1️⃣ Check if creek already exists
  const getRes = await fetch(
    `${SUPABASE_REST_URL}/creeks?creek_name=eq.${encodeURIComponent(name)}`,
    {
      headers: {
        apikey: SUPABASE_KEY,
        Authorization: `Bearer ${SUPABASE_KEY}`,
      },
    }
  );
  const existing = await getRes.json();
  if (existing.length > 0) return existing[0].creek_id;

  // 2️⃣ Insert new creek with full metadata
  const postRes = await fetch(`${SUPABASE_REST_URL}/creeks`, {
    method: "POST",
    headers: {
      apikey: SUPABASE_KEY,
      Authorization: `Bearer ${SUPABASE_KEY}`,
      "Content-Type": "application/json",
      Prefer: "return=representation",
    },
    body: JSON.stringify({
      creek_name: name,
      city,
      barangay,
      province,
      coordinates,
    }),
  });

  const newCreek = await postRes.json();
  return newCreek[0]?.creek_id || null;
}
// ============================================================
// 🧠 UPDATED: Upload detections to Supabase with Creek Handling
// ============================================================
async function storeDetectionToSupabase(detectionResult) {
  try {
    const folderKey = detectionResult?.folder || "defaultFolder";
    if (localStorage.getItem(`uploaded_${folderKey}`)) {
      debugLog("Already uploaded previously.", "warn");
      alert("Already uploaded previously.");
      return;
    }

    let lat = localStorage.getItem("user_latitude");
    let lng = localStorage.getItem("user_longitude");
    const creekName = localStorage.getItem("selected_creek") || null;

    // 🌊 Force fixed coordinates for Buli Creek
    if (creekName && creekName.toLowerCase().includes("buli")) {
      lat = 14.6000556;
      lng = 121.0994722;
      console.log("📍 Buli Creek selected — using fixed coordinates:", lat, lng);
  }
    // ✅ Ensure creek is registered in Supabase
    let creekId = null;
    if (creekName) creekId = await getCreekId(creekName);

    const uploads = await uploadAllImagesToBucket(detectionResult.results);
    // 🕓 Use Philippine local date/time
    const phNow = new Date().toLocaleString("sv-SE", { timeZone: "Asia/Manila" });
    const [dateStr, timeStr] = phNow.split(" ");

    for (let i = 0; i < uploads.length; i++) {
      const imgUpload = uploads[i];
      const imgData = detectionResult.results[i];
      let imageId = imgUpload.existingImageRecord?.image_id || null;

      // 🖼️ Insert image if not yet in database
      if (!imageId) {
        const imgPayload = {
          imagefile_name: imgUpload.fileName,
          uploaded_by: isLoggedIn ? parseInt(adminId) : null,
          latitude: parseFloat(lat) || null,
          longitude: parseFloat(lng) || null,
          creek_id: creekId || null, // 🌊 Add link to creek
        };

        const imgRes = await fetch(`${SUPABASE_REST_URL}/images`, {
          method: "POST",
          headers: {
            apikey: SUPABASE_KEY,
            Authorization: `Bearer ${SUPABASE_KEY}`,
            "Content-Type": "application/json",
            Prefer: "return=representation",
          },
          body: JSON.stringify(imgPayload),
        });
        const imgJson = await imgRes.json();
        imageId = imgJson[0]?.image_id;
      }

      // 🗑️ Insert detections linked to image
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
          detection_time: timeStr,
        };
        await fetch(`${SUPABASE_REST_URL}/detections`, {
          method: "POST",
          headers: {
            apikey: SUPABASE_KEY,
            Authorization: `Bearer ${SUPABASE_KEY}`,
            "Content-Type": "application/json",
            Prefer: "return=representation",
          },
          body: JSON.stringify(detPayload),
        });
      }
    }

    localStorage.setItem(`uploaded_${folderKey}`, "1");
    debugLog("All detections saved to Supabase (with creek link).", "success");
    alert("Upload complete — creek data included.");
  } catch (err) {
    debugLog("Upload failed: " + err.message, "error");
    alert("Upload failed: " + err.message);
  }
}


uploadBtn?.addEventListener("click", async () => {
   await loadCreekList();
  const dr = JSON.parse(localStorage.getItem("detectionResult") || "null");
  if (!dr) {
    alert("No detection results available.");
    return;
  }

  // Show creek selection toast
  const toast = document.getElementById("creek-toast");
  const creekSelect = document.getElementById("creek-select");
  const creekInput = document.getElementById("creek-input");
  const confirmBtn = document.getElementById("toast-confirm");
  const cancelBtn = document.getElementById("toast-cancel");

  toast.classList.add("active");
  creekSelect.value = "";
  creekInput.value = "";
  creekInput.style.display = "none";

  creekSelect.addEventListener("change", () => {
    creekInput.style.display = creekSelect.value === "Other" ? "block" : "none";
  });

  const handleConfirm = async () => {
    let creekName =
      creekSelect.value === "Other"
        ? creekInput.value.trim()
        : creekSelect.value;

    if (!creekName) {
      alert("Please select or type the creek/river name.");
      return;
    }

    localStorage.setItem("selected_creek", creekName);
    toast.classList.remove("active");

    uploadBtn.disabled = true;
    uploadBtn.textContent = "Uploading...";
    await storeDetectionToSupabase(dr);
    uploadBtn.disabled = false;
    uploadBtn.textContent = "Upload to Database";

    // cleanup event listeners
    confirmBtn.removeEventListener("click", handleConfirm);
    cancelBtn.removeEventListener("click", handleCancel);
  };

  const handleCancel = () => {
    toast.classList.remove("active");
  };

  confirmBtn.addEventListener("click", handleConfirm);
  cancelBtn.addEventListener("click", handleCancel);
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
    if (zoomLevelDisplay)
      zoomLevelDisplay.textContent = `${Math.round(currentZoom * 100)}%`;
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
// =============================
// 📄 Formal PDF Export (with location + summary)
// =============================
pdfButton?.addEventListener("click", async () => {
  const dr = JSON.parse(localStorage.getItem("detectionResult") || "null");
  if (!dr?.results?.length) {
    alert("No detection results available for export.");
    return;
  }

  const { jsPDF } = window.jspdf;
  const pdf = new jsPDF({ orientation: "p", unit: "mm", format: "a4" });

  // ===========================
  // 🗺️ Location Information
  // ===========================
  const lat = localStorage.getItem("user_latitude");
  const lng = localStorage.getItem("user_longitude");
  let locationText = "Location not available";

  if (lat && lng) {
    try {
      const res = await fetch(`${FLASK_BASE}/reverse_geocode?lat=${lat}&lon=${lng}`);
      const geo = await res.json();
      if (geo.display_name) locationText = geo.display_name;
      else locationText = `Coordinates: ${parseFloat(lat).toFixed(4)}, ${parseFloat(lng).toFixed(4)}`;
    } catch {
      locationText = `Coordinates: ${parseFloat(lat).toFixed(4)}, ${parseFloat(lng).toFixed(4)}`;
    }
  }

  // ===========================
  // 🏛️ Formal Header
  // ===========================
  try {
    const logo = await convertImageToBase64("../imgs/logo.png");
    pdf.addImage(logo, "PNG", 15, 10, 25, 20);
  } catch {}

  pdf.setFont("helvetica", "bold");
  pdf.setFontSize(18);
  pdf.text("LitterLens Detection Report", 105, 20, { align: "center" });

  pdf.setFont("helvetica", "normal");
  pdf.setFontSize(11);
  pdf.text(`Generated on: ${new Date().toLocaleString()}`, 105, 28, { align: "center" });

  pdf.setFont("helvetica", "italic");
  pdf.setFontSize(10);
  pdf.text(locationText, 105, 35, { align: "center", maxWidth: 170 });

  pdf.line(15, 40, 195, 40); // separator

  // ===========================
  // 📊 Summary Overview (Page 1)
  // ===========================
  pdf.setFont("helvetica", "bold");
  pdf.setFontSize(14);
  pdf.text("Detection Summary Overview", 15, 50);

  pdf.setFont("helvetica", "normal");
  pdf.setFontSize(11);
  let y = 58;

  const totalSummary = dr.total_summary || {};
  const totalItems = Object.values(totalSummary).reduce((a, b) => a + b, 0);
  const avgAccuracy = dr.accuracy ? `${dr.accuracy}%` : "N/A";

  pdf.text(`Total Detections: ${totalItems}`, 15, y);
  y += 7;
  pdf.text(`Average Accuracy: ${avgAccuracy}`, 15, y);
  y += 7;
  pdf.text("Detected Litter Types:", 15, y);
  y += 8;

  pdf.setFont("helvetica", "normal");
  pdf.setFontSize(10);
  pdf.text("Litter Type", 20, y);
  pdf.text("Count", 110, y);
  y += 6;

  Object.entries(totalSummary).forEach(([cls, cnt]) => {
    pdf.text(cls, 20, y);
    pdf.text(String(cnt), 110, y);
    y += 6;
  });

  y += 10;
  pdf.setFontSize(10);
  pdf.text("Note: This report is automatically generated by the LitterLens System.", 15, y);

  // ===========================
  // 📸 Detailed Image Results
  // ===========================
  for (let i = 0; i < dr.results.length; i++) {
    const imgData = dr.results[i];
    pdf.addPage();

    const imageLabel = imgData.result_image.split("/").pop();
    const imgUrl = `${FLASK_BASE}/${imgData.result_image}`;
    const base64 = await convertImageToBase64(imgUrl);
    const props = pdf.getImageProperties(base64);
    const pdfWidth = 170;
    const pdfHeight = (props.height * pdfWidth) / props.width;

    // Section Header
    pdf.setFont("helvetica", "bold");
    pdf.setFontSize(14);
    pdf.text(`Detection Result #${i + 1}`, 15, 20);

    pdf.setFont("helvetica", "normal");
    pdf.setFontSize(11);
    pdf.text(`Image File: ${imageLabel}`, 15, 28);
    pdf.text(`Detection Accuracy: ${imgData.accuracy || dr.accuracy || 0}%`, 15, 35);
    pdf.text(`Captured Location:`, 15, 42);
    pdf.setFontSize(10);
    pdf.text(locationText, 15, 48, { maxWidth: 180 });

    // Draw image
    pdf.addImage(base64, "JPEG", 20, 55, pdfWidth, pdfHeight);

    let detailY = 55 + pdfHeight + 10;
    pdf.setFont("helvetica", "bold");
    pdf.setFontSize(12);
    pdf.text("Image Detection Summary:", 15, detailY);
    detailY += 8;

    pdf.setFont("helvetica", "normal");
    pdf.setFontSize(10);
    pdf.text("Litter Type", 20, detailY);
    pdf.text("Count", 110, detailY);
    detailY += 6;

    Object.entries(imgData.summary || {}).forEach(([cls, cnt]) => {
      pdf.text(cls, 20, detailY);
      pdf.text(String(cnt), 110, detailY);
      detailY += 6;
    });

    pdf.line(15, detailY + 2, 195, detailY + 2);
    pdf.text("End of result section", 105, detailY + 10, { align: "center" });
  }

  // ===========================
  // ✅ Save the report
  // ===========================
  const timestamp = new Date().toISOString().replace(/[:.]/g, "-");
  pdf.save(`LitterLens_Report_${timestamp}.pdf`);
});



});


