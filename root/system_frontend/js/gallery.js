document.addEventListener("DOMContentLoaded", () => {
  // === ELEMENTS ===
  const imgElement = document.querySelector(".main-img");
  const prevBtn = document.querySelector(".prev-btn");
  const nextBtn = document.querySelector(".next-btn");
  const fileNameElement = document.getElementById("file-name-display");
  const thresholdDropdown = document.querySelector(".threshold-dropdown");
  const opacityDropdown = document.getElementById("opacityDropdown");
  const switchInput = document.querySelector(".switch input");
  const goBackBtn = document.getElementById("go-back-btn");
  const imgContainer = document.querySelector(".img-container");
  const labelModeDropdown = document.querySelector(".label-mode-dropdown");
  const uploadBtn = document.getElementById("upload-btn");
  const pdfButton = document.getElementById("download-pdf");

  // === SUPABASE CONFIG ===
  const SUPABASE_URL = "https://ksbgdgqpdoxabdefjsin.supabase.co";
  const SUPABASE_KEY =
    "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtzYmdkZ3FwZG94YWJkZWZqc2luIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2MTAzMjUxOSwiZXhwIjoyMDc2NjA4NTE5fQ.WAai4nbsqgbe-7PgOw8bktVjk0V9Cm8sdEct_vlQCcY";
  const SUPABASE_REST_URL = `${SUPABASE_URL}/rest/v1`;
  const SUPABASE_STORAGE_URL = `${SUPABASE_URL}/storage/v1/object`;

  // === DEBUGGER PANEL ===
  const debuggerPanel = document.createElement("div");
  Object.assign(debuggerPanel.style, {
    position: "fixed",
    bottom: "10px",
    right: "10px",
    width: "350px",
    height: "200px",
    background: "#1e1e1e",
    color: "#eee",
    fontFamily: "monospace",
    fontSize: "12px",
    padding: "8px",
    borderRadius: "8px",
    overflowY: "auto",
    zIndex: "9999",
    boxShadow: "0 0 8px rgba(0,0,0,0.4)"
  });
  debuggerPanel.innerHTML = "<strong>🐞 Debugger Panel</strong><hr>";
  document.body.appendChild(debuggerPanel);

  function debugLog(message, type = "info") {
    const el = document.createElement("div");
    const time = new Date().toLocaleTimeString();
    el.innerHTML = `[${time}] ${message}`;
    if (type === "success") el.style.color = "#4ade80";
    if (type === "error") el.style.color = "#f87171";
    debuggerPanel.appendChild(el);
    debuggerPanel.scrollTop = debuggerPanel.scrollHeight;
    console.log(`[DEBUG:${type.toUpperCase()}]`, message);
  }

  debugLog("✅ Script loaded and debugger initialized", "success");

  // === STATE ===
  let currentLabelMode = localStorage.getItem("labelMode") || "confidence";
  localStorage.removeItem("boxOpacity");
  let currentOpacity = "1.00";
  localStorage.setItem("boxOpacity", currentOpacity);

  let detectionResult = JSON.parse(localStorage.getItem("detectionResult"));
  let currentIndex = 0;
  let showingResult = true;

  // === SPINNER ===
  const spinnerOverlay = document.createElement("div");
  spinnerOverlay.classList.add("spinner-overlay");
  spinnerOverlay.innerHTML = `<div class="spinner"></div><p class="spinner-text">Updating...</p>`;
  imgContainer.appendChild(spinnerOverlay);
  const spinnerText = spinnerOverlay.querySelector(".spinner-text");
  const showSpinner = (text = "Updating...") => {
    spinnerText.textContent = text;
    spinnerOverlay.classList.add("active");
  };
  const hideSpinner = () => spinnerOverlay.classList.remove("active");

  // === UI ===
  function updateImageDisplay(src) {
    imgElement.src = src + `?t=${Date.now()}`;
    const filename = src.split("/").pop();
    fileNameElement.textContent =
      filename.length > 15 ? filename.substring(0, 15) + "..." : filename;
  }

  function updateTable(summary, total) {
    const table = document.querySelector(".receipt table");
    const title = document.querySelector(".receipt-card h2");
    if (!table || !title) return;
    title.textContent = `${total || 0} Items detected`;
    table.querySelectorAll("tr:not(:first-child)").forEach(tr => tr.remove());
    Object.entries(summary || {}).forEach(([label, count]) => {
      const row = document.createElement("tr");
      row.innerHTML = `<td>${label}</td><td>${count}</td>`;
      table.appendChild(row);
    });
  }

  function updateAccuracy(accuracy) {
    const accuracyElement = document.querySelector(".accuracy");
    if (!accuracyElement) return;
    const value = Number(accuracy) || 0;
    accuracyElement.textContent = `Detection Accuracy: ${value}%`;
    accuracyElement.classList.remove("accuracy-good", "accuracy-medium", "accuracy-low");
    if (value >= 80) accuracyElement.classList.add("accuracy-good");
    else if (value >= 50) accuracyElement.classList.add("accuracy-medium");
    else accuracyElement.classList.add("accuracy-low");
  }

  function showImage(index) {
    if (!detectionResult?.results || detectionResult.results.length === 0) return;
    const imgData = detectionResult.results[index];
    const src = showingResult
      ? `http://127.0.0.1:5000/${imgData.result_image}`
      : `http://127.0.0.1:5000/${imgData.original_image}`;
    updateImageDisplay(src);
    updateTable(imgData.summary, imgData.total_items);
    updateAccuracy(imgData.accuracy ?? detectionResult.accuracy);
    debugLog(`🖼️ Displaying image ${index + 1}/${detectionResult.results.length}`, "info");
  }

  // === DETECTION CONTROLS ===
  function redetect(newThreshold, labelMode = currentLabelMode, opacity = currentOpacity) {
    if (!detectionResult || !detectionResult.folder) return;
    showSpinner("Re-running detection...");
    fetch("http://127.0.0.1:5000/redetect", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ folder: detectionResult.folder, threshold: newThreshold, label_mode: labelMode, opacity })
    })
      .then(res => res.json())
      .then(data => {
        hideSpinner();
        if (data.error) return debugLog(`❌ Redetect error: ${data.error}`, "error");
        detectionResult = { ...detectionResult, ...data };
        localStorage.setItem("detectionResult", JSON.stringify(detectionResult));
        showImage(currentIndex);
      })
      .catch(err => {
        hideSpinner();
        debugLog(`❌ Redetect fetch failed: ${err}`, "error");
      });
  }

  function rerender(labelMode = currentLabelMode, opacity = currentOpacity) {
    if (!detectionResult || !detectionResult.folder) return;
    showSpinner("Redrawing boxes...");
    fetch("http://127.0.0.1:5000/rerender", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ folder: detectionResult.folder, label_mode: labelMode, opacity })
    })
      .then(res => res.json())
      .then(data => {
        hideSpinner();
        if (data.error) return debugLog(`❌ Rerender error: ${data.error}`, "error");
        detectionResult = { ...detectionResult, ...data };
        localStorage.setItem("detectionResult", JSON.stringify(detectionResult));
        showImage(currentIndex);
      })
      .catch(err => {
        hideSpinner();
        debugLog(`❌ Rerender fetch failed: ${err}`, "error");
      });
  }

  // === EVENTS ===
  thresholdDropdown.addEventListener("change", () => redetect(parseFloat(thresholdDropdown.value)));
  opacityDropdown.addEventListener("change", () => {
    currentOpacity = opacityDropdown.value;
    rerender(currentLabelMode, currentOpacity);
  });
  labelModeDropdown.addEventListener("change", () => {
    currentLabelMode = labelModeDropdown.value;
    localStorage.setItem("labelMode", currentLabelMode);
    rerender(currentLabelMode, currentOpacity);
  });
  prevBtn.addEventListener("click", () => {
    currentIndex = (currentIndex - 1 + detectionResult.results.length) % detectionResult.results.length;
    showImage(currentIndex);
  });
  nextBtn.addEventListener("click", () => {
    currentIndex = (currentIndex + 1) % detectionResult.results.length;
    showImage(currentIndex);
  });
  switchInput.addEventListener("change", e => {
    showingResult = e.target.checked;
    showImage(currentIndex);
  });

  // === GO BACK ===
  goBackBtn.addEventListener("click", () => {
    localStorage.removeItem("boxOpacity");
    if (detectionResult?.folder) {
      fetch("http://127.0.0.1:5000/cleanup", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ folder: detectionResult.folder })
      })
        .then(() => {
          localStorage.removeItem("detectionResult");
          window.location.href = "index.php";
        })
        .catch(() => {
          localStorage.removeItem("detectionResult");
          window.location.href = "index.php";
        });
    } else {
      localStorage.removeItem("detectionResult");
      window.location.href = "index.php";
    }
  });

  // === SUPABASE MULTIPLE UPLOAD ===
  async function uploadAllImagesToBucket(results) {
    const uploadedImages = [];
    for (const imgData of results) {
      try {
        const imageUrl = `http://127.0.0.1:5000/${imgData.result_image}`;
        const response = await fetch(imageUrl);
        const blob = await response.blob();
        const fileName = `detection_result_${Date.now()}_${Math.floor(Math.random() * 10000)}.jpg`;
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
        debugLog(`🟢 Uploaded: ${fileName}`, "success");
      } catch (err) {
        debugLog(`❌ Upload failed: ${err.message}`, "error");
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
    debugLog(`🆕 Created litter type: ${name}`, "success");
    return newType[0].littertype_id;
  }

  async function storeDetectionToSupabase(detectionResult) {
    try {
      debugLog("🚀 Starting Supabase storage for multiple images...", "info");
      const lat = localStorage.getItem("user_latitude");
      const lng = localStorage.getItem("user_longitude");

      const uploads = await uploadAllImagesToBucket(detectionResult.results);
      if (uploads.length === 0) throw new Error("No images uploaded.");

      const now = new Date();
      const dateStr = now.toISOString().split("T")[0];
      const timeStr = now.toTimeString().split(" ")[0];

      for (let i = 0; i < uploads.length; i++) {
        const imgUpload = uploads[i];
        const imgData = detectionResult.results[i];
        const imgPayload = {
          imagefile_name: imgUpload.fileName,
          uploaded_by: "System",
          latitude: parseFloat(lat),
          longitude: parseFloat(lng)
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
        const imageId = imgJson[0]?.image_id;
        if (!imageId) throw new Error("Failed to save image record.");
        for (const [typeName, qty] of Object.entries(imgData.summary || {})) {
          const typeId = await getLitterTypeId(typeName);
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
          debugLog(`✅ Detection inserted: ${typeName} (${qty}) for image ${i + 1}`, "success");
        }
      }
      debugLog("🎯 All images and detections saved to Supabase successfully.", "success");
    } catch (err) {
      debugLog(`❌ Supabase insertion failed: ${err.message}`, "error");
    }
  }

  uploadBtn?.addEventListener("click", () => {
    if (!detectionResult) return debugLog("❌ No detection data to upload.", "error");
    debugLog("🚀 Uploading detection data to Supabase...", "info");
    storeDetectionToSupabase(detectionResult);
  });

  // === ZOOM ===
  const zoomInBtn = document.getElementById("zoom-in");
  const zoomOutBtn = document.getElementById("zoom-out");
  const zoomResetBtn = document.getElementById("zoom-reset");
  const zoomLevelDisplay = document.getElementById("zoom-level");

  let currentZoom = 1;
  let isDragging = false;
  let startX, startY, currentX = 0, currentY = 0;

  function updateZoom() {
    imgElement.style.transform = `translate(${currentX}px, ${currentY}px) scale(${currentZoom})`;
    zoomLevelDisplay.textContent = `${Math.round(currentZoom * 100)}%`;
  }

  zoomInBtn.addEventListener("click", () => {
    currentZoom = Math.min(currentZoom + 0.1, 3);
    updateZoom();
  });
  zoomOutBtn.addEventListener("click", () => {
    currentZoom = Math.max(currentZoom - 0.1, 1);
    if (currentZoom === 1) { currentX = 0; currentY = 0; }
    updateZoom();
  });
  zoomResetBtn.addEventListener("click", () => {
    currentZoom = 1; currentX = 0; currentY = 0; updateZoom();
  });
  imgElement.addEventListener("mousedown", e => {
    if (currentZoom <= 1) return;
    isDragging = true;
    startX = e.clientX - currentX;
    startY = e.clientY - currentY;
    imgElement.style.cursor = "grabbing";
  });
  window.addEventListener("mouseup", () => { isDragging = false; imgElement.style.cursor = "grab"; });
  window.addEventListener("mousemove", e => {
    if (!isDragging) return;
    e.preventDefault();
    const newX = e.clientX - startX;
    const newY = e.clientY - startY;
    const containerRect = imgContainer.getBoundingClientRect();
    const imageRect = imgElement.getBoundingClientRect();
    const maxX = (imageRect.width - containerRect.width) / 2;
    const maxY = (imageRect.height - containerRect.height) / 2;
    currentX = Math.max(-maxX, Math.min(maxX, newX));
    currentY = Math.max(-maxY, Math.min(maxY, newY));
    updateZoom();
  });

  // === LOCATION DISPLAY ===
  const latitude = localStorage.getItem("user_latitude");
  const longitude = localStorage.getItem("user_longitude");
  if (latitude && longitude) {
    const latElement = document.getElementById("lat-value");
    const lngElement = document.getElementById("lng-value");
    const locationLink = document.getElementById("location-link");
    const formattedLat = parseFloat(latitude).toFixed(6);
    const formattedLng = parseFloat(longitude).toFixed(6);
    if (latElement) latElement.textContent = formattedLat;
    if (lngElement) lngElement.textContent = formattedLng;
    if (locationLink) locationLink.href = `https://www.google.com/maps?q=${formattedLat},${formattedLng}`;
  }

  // === PDF (HTML2PDF) ===
  pdfButton?.addEventListener("click", async (e) => {
    e.preventDefault();
    if (!detectionResult) return;

    const container = document.createElement("div");
    container.style.fontFamily = "Arial, sans-serif";
    container.innerHTML = `
      <header style="background:#22313f;color:white;padding:20px;text-align:center;">
        <h1 style="margin:0;">LitterLens Detection Report</h1>
        <p>📅 ${new Date().toLocaleString()} | 📍 ${parseFloat(latitude).toFixed(6)}, ${parseFloat(longitude).toFixed(6)} | 🎯 ${detectionResult.accuracy || 0}%</p>
      </header>
      <section style="padding:20px;">
        <h2>Litter Classification Summary</h2>
        <table style="width:100%;border-collapse:collapse;margin-top:10px;">
          <thead style="background:#eee;">
            <tr>
              <th style="padding:8px;text-align:left;">Litter Type</th>
              <th style="padding:8px;text-align:left;">Total Count</th>
            </tr>
          </thead>
          <tbody>
            ${Object.entries(detectionResult.total_summary || {})
              .map(([label, count]) => `
                <tr>
                  <td style="padding:8px;border-bottom:1px solid #ccc;">${label}</td>
                  <td style="padding:8px;border-bottom:1px solid #ccc;">${count}</td>
                </tr>`).join("")}
          </tbody>
        </table>
      </section>
    `;

    // Image sections
    detectionResult.results.forEach((data, i) => {
      const imgUrl = `http://127.0.0.1:5000/${data.result_image}`;
      const section = document.createElement("section");
      section.style.padding = "20px";
      section.style.pageBreakInside = "avoid";
      section.innerHTML = `
        <h3>🖼️ Image ${i + 1} | Detected: ${data.total_items} | Accuracy: ${data.accuracy}%</h3>
        <img src="${imgUrl}" style="width:100%;border:1px solid #ccc;margin:10px 0;" />
        <table style="width:100%;border-collapse:collapse;">
          <thead style="background:#eee;">
            <tr>
              <th style="padding:8px;text-align:left;">Litter Type</th>
              <th style="padding:8px;text-align:left;">Count</th>
            </tr>
          </thead>
          <tbody>
            ${Object.entries(data.summary || {})
              .map(([type, count]) => `
                <tr>
                  <td style="padding:8px;border-bottom:1px solid #ccc;">${type}</td>
                  <td style="padding:8px;border-bottom:1px solid #ccc;">${count}</td>
                </tr>`).join("")}
          </tbody>
        </table>
      `;
      container.appendChild(section);
    });

    const footer = document.createElement("footer");
    footer.style.background = "#f7f7f7";
    footer.style.textAlign = "center";
    footer.style.padding = "10px";
    footer.style.fontSize = "12px";
    footer.style.color = "#666";
    footer.innerText = "Generated by LitterLens | Image-based Litter Detection System";
    container.appendChild(footer);

    const opt = {
      margin: 0,
      filename: `LitterLens_Report_${new Date().toISOString().split("T")[0]}.pdf`,
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2 },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().from(container).set(opt).save();
  });

  // === INIT ===
  imgElement.style.transition = "transform 0.2s ease";
  switchInput.checked = true;
  showingResult = true;
  showImage(currentIndex);
  updateZoom();
});
