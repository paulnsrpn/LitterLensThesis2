document.addEventListener("DOMContentLoaded", () => {
  // ============================
  // 🟩 TAB SWITCHING LOGIC
  // ============================
  const detectionsTabBtn = document.getElementById("detections-tab");
  const resultsTabBtn = document.getElementById("results-tab");
  const detectionsSection = document.getElementById("detections");
  const resultsSection = document.getElementById("results");

  detectionsTabBtn?.addEventListener("click", () => {
    detectionsTabBtn.classList.add("active");
    resultsTabBtn.classList.remove("active");
    detectionsSection.classList.add("active");
    resultsSection.classList.remove("active");
  });

  resultsTabBtn?.addEventListener("click", () => {
    resultsTabBtn.classList.add("active");
    detectionsTabBtn.classList.remove("active");
    resultsSection.classList.add("active");
    detectionsSection.classList.remove("active");
  });

  // ============================
  // 🗓️ FILTER TABLE BY DATE
  // ============================
  const dateInput = document.getElementById("date");
  const tableBody = document.getElementById("realtimeTableBody");

  if (dateInput && tableBody) {
    dateInput.addEventListener("change", () => {
      const selectedDate = dateInput.value;
      const rows = tableBody.querySelectorAll("tr[data-date]");
      let visibleCount = 0;

      rows.forEach(row => {
        const rowDate = row.getAttribute("data-date");
        row.style.display = rowDate === selectedDate ? "" : "none";
        if (rowDate === selectedDate) visibleCount++;
      });

      const existingMessage = tableBody.querySelector(".no-results-row");
      if (existingMessage) existingMessage.remove();

      if (visibleCount === 0) {
        const noDataRow = document.createElement("tr");
        noDataRow.classList.add("no-results-row");
        noDataRow.innerHTML = `
          <td colspan="5" style="text-align:center; color:#555;">
            No records found for ${selectedDate}.
          </td>`;
        tableBody.appendChild(noDataRow);
      }
    });

    if (dateInput.value) dateInput.dispatchEvent(new Event("change"));
  }

  // ============================================
  // 📊 REAL-TIME LITTER TYPE TREND CHART
  // ============================================
  window.addEventListener("load", () => {
    const loader = document.getElementById("litterTrendLoader");
    const canvas = document.getElementById("litterTrendChart");
    const filterSelect = document.getElementById("litterTrendFilter");
    let litterChart = null;

    function showLoader(show = true, msg = "Loading data...") {
      loader.style.display = show ? "flex" : "none";
      loader.textContent = msg;
      canvas.style.display = show ? "none" : "block";
    }

    function drawLitterChart(labels, datasets) {
      if (!labels?.length || !datasets?.length) {
        showLoader(true, "No data available.");
        return;
      }
      showLoader(false);

      const ctx = canvas.getContext("2d");
      const colors = [
        "#2E7D32", "#388E3C", "#43A047", "#4CAF50", "#66BB6A",
        "#81C784", "#A5D6A7", "#C8E6C9", "#1B5E20"
      ];

      datasets.forEach((ds, i) => {
        ds.borderColor = colors[i % colors.length];
        ds.backgroundColor = colors[i % colors.length] + "33";
        ds.borderWidth = 3;
        ds.pointRadius = 4;
        ds.tension = 0.3;
        ds.fill = false;
      });

      if (litterChart) {
        litterChart.data.labels = labels;
        litterChart.data.datasets = datasets;
        litterChart.update();
        return;
      }

      litterChart = new Chart(ctx, {
        type: "line",
        data: { labels, datasets },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: "bottom",
              labels: {
                usePointStyle: true,
                color: "#2E7D32",
                font: { family: "Poppins", size: 12 }
              }
            },
            tooltip: {
              backgroundColor: "#2E7D32",
              titleColor: "#fff",
              bodyColor: "#fff",
              callbacks: {
                label: ctx => `${ctx.dataset.label}: ${ctx.formattedValue}`
              }
            }
          },
          scales: {
            x: {
              title: { display: true, text: "Date", color: "#2E7D32" },
              ticks: { color: "#333" },
              grid: { color: "rgba(0,0,0,0.05)" }
            },
            y: {
              beginAtZero: true,
              title: { display: true, text: "Detections", color: "#2E7D32" },
              ticks: { color: "#333" },
              grid: { color: "rgba(0,0,0,0.05)" }
            }
          }
        }
      });
    }

    async function fetchLitterData(filter = "day") {
      showLoader(true, `Loading ${filter} data...`);
      try {
        const res = await fetch(
          `http://localhost/LitterLensThesis2/root/system_backend/php/system_admin_data.php?ajax=realtime_trend&filter=${filter}`
        );
        const data = await res.json();
        if (data.labels?.length && data.datasets?.length) drawLitterChart(data.labels, data.datasets);
        else showLoader(true, "No chart data returned.");
      } catch {
        showLoader(true, "Chart data failed to load.");
      }
    }

    fetchLitterData("day");
    filterSelect?.addEventListener("change", e => fetchLitterData(e.target.value));
    setInterval(() => {
      const selected = filterSelect?.value || "day";
      fetchLitterData(selected);
    }, 30000);
  });

  // ============================
  // 🗺️ MAPLIBRE HEATMAP
  // ============================
  const mapContainer = document.getElementById("litterHotspotMap");
  if (mapContainer && Array.isArray(realtimePoints) && realtimePoints.length > 0) {
    const avgLat = realtimePoints.reduce((sum, p) => sum + p.lat, 0) / realtimePoints.length;
    const avgLng = realtimePoints.reduce((sum, p) => sum + p.lng, 0) / realtimePoints.length;

    const map = new maplibregl.Map({
      container: "litterHotspotMap",
      style: {
        version: 8,
        sources: {
          carto: {
            type: "raster",
            tiles: [
              "https://cartodb-basemaps-a.global.ssl.fastly.net/light_all/{z}/{x}/{y}.png",
              "https://cartodb-basemaps-b.global.ssl.fastly.net/light_all/{z}/{x}/{y}.png",
              "https://cartodb-basemaps-c.global.ssl.fastly.net/light_all/{z}/{x}/{y}.png",
            ],
            tileSize: 256,
            attribution: "© OpenStreetMap, © Carto",
          },
        },
        layers: [{ id: "carto", type: "raster", source: "carto" }],
      },
      center: [avgLng, avgLat],
      zoom: 12,
    });

    map.addControl(new maplibregl.NavigationControl(), "top-right");

    map.on("load", () => {
      mapContainer.classList.add("loaded");
      const geojson = {
        type: "FeatureCollection",
        features: realtimePoints.map(p => ({
          type: "Feature",
          geometry: { type: "Point", coordinates: [p.lng, p.lat] },
          properties: { accuracy: p.accuracy || 1 },
        })),
      };
      map.addSource("litter-heat", { type: "geojson", data: geojson });
      map.addLayer({
        id: "litter-heatmap",
        type: "heatmap",
        source: "litter-heat",
        maxzoom: 15,
        paint: {
          "heatmap-radius": 30,
          "heatmap-opacity": 1,
          "heatmap-intensity": 0.2,
          "heatmap-weight": 0.5,
          "heatmap-color": [
            "interpolate",
            ["linear"],
            ["heatmap-density"],
            0, "rgba(0,255,0,0)",
            0.3, "rgba(0,255,0,0.3)",
            0.5, "yellow",
            0.7, "orange",
            1.0, "red",
          ],
        },
      });

      const bounds = new maplibregl.LngLatBounds();
      realtimePoints.forEach(p => bounds.extend([p.lng, p.lat]));
      map.fitBounds(bounds, { padding: 40, duration: 1000, maxZoom: 14 });
    });
  }

  // =============================
  // 📤 IMAGE UPLOAD HANDLER
  // =============================
  const uploadBtn = document.getElementById("admin-upload-btn");
  const fileInput = document.getElementById("admin-upload-input");

  if (!uploadBtn || !fileInput) return;

  fileInput.setAttribute("multiple", "true");
  uploadBtn.addEventListener("click", () => fileInput.click());

  fileInput.addEventListener("change", async e => {
    const files = Array.from(e.target.files || []);
    if (!files.length) {
      alert("Please select one or more images to analyze.");
      return;
    }

    const adminId = window.currentAdminId || localStorage.getItem("admin_id") || "0";
    const adminName = window.currentAdminName || localStorage.getItem("admin_name") || "Admin";

    const overlay = document.createElement("div");
    Object.assign(overlay.style, {
      position: "fixed",
      inset: "0",
      background: "rgba(0,0,0,0.6)",
      color: "#fff",
      fontSize: "16px",
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      flexDirection: "column",
      zIndex: "9998",
    });
    overlay.innerHTML = `
      <div style="text-align:center;">
        <div class="spinner" style="width:36px;height:36px;border:4px solid rgba(255,255,255,0.2);border-top-color:#38bdf8;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 10px auto"></div>
        <p id="analyze-status">Analyzing ${files.length} image(s)...</p>
      </div>`;
    document.body.appendChild(overlay);

    try {
      const formData = new FormData();
      files.forEach((file, i) => formData.append(`file_${i + 1}`, file));
      formData.append("admin_id", adminId);
      formData.append("admin_name", adminName);
      formData.append("uploaded_by", adminName);

      const res = await fetch("http://127.0.0.1:5000/admin_analyze", { method: "POST", body: formData });
      const data = await res.json();

      if (data.redirect) {
        localStorage.setItem("detectionResult", JSON.stringify(data.data));
        localStorage.setItem("detectionSource", "admin");
        window.location.href = data.redirect;
      } else if (data.folder) {
        const redirectUrl = `http://localhost/LitterLensThesis2/root/system_frontend/php/index_result.php?folder=${encodeURIComponent(data.folder)}`;
        localStorage.setItem("detectionResult", JSON.stringify(data));
        localStorage.setItem("detectionSource", "admin");
        window.location.href = redirectUrl;
      } else {
        alert("Detection failed.");
      }
    } catch (err) {
      alert("Upload failed: " + err.message);
    } finally {
      document.body.removeChild(overlay);
    }
  });

  const style = document.createElement("style");
  style.textContent = `@keyframes spin { to { transform: rotate(360deg); } }`;
  document.head.appendChild(style);
});

// ============================
// 🗑️ DELETE DETECTIONS
// ============================
document.addEventListener("DOMContentLoaded", () => {
  const deleteBtn = document.querySelector(".delete-btn");
  const checkboxes = document.querySelectorAll("tbody input[type='checkbox']");

  if (!deleteBtn || !checkboxes.length) return;

  checkboxes.forEach(cb => cb.disabled = false);

  function updateDeleteButton() {
    deleteBtn.disabled = !Array.from(checkboxes).some(cb => cb.checked);
  }

  checkboxes.forEach(cb => cb.addEventListener("change", updateDeleteButton));
  updateDeleteButton();

  deleteBtn.addEventListener("click", async () => {
    const checked = Array.from(checkboxes).filter(cb => cb.checked);
    const ids = checked.map(cb => cb.closest("tr").dataset.detectionId).filter(Boolean);
    if (!ids.length || !confirm(`Delete ${ids.length} detection(s)? This cannot be undone.`)) return;

    deleteBtn.disabled = true;
    const originalHTML = deleteBtn.innerHTML;
    deleteBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Deleting...`;

    try {
      const res = await fetch("?ajax=delete_detections", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ids }),
      });
      const data = await res.json();

      if (data.success) {
        alert(`Successfully deleted ${data.deleted.length} record(s).`);
        checked.forEach(cb => cb.closest("tr").remove());
      } else {
        alert("Some records could not be deleted.");
      }
    } catch {
      alert("Server error while deleting.");
    } finally {
      deleteBtn.innerHTML = originalHTML;
      deleteBtn.disabled = false;
      updateDeleteButton();
    }
  });
});
