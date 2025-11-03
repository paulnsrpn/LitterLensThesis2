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




  const pieCanvas = document.getElementById("litterDistributionChart");

  if (!pieCanvas || typeof Chart === "undefined") {
    console.warn("❌ Chart.js or canvas element not found for realtime pie chart.");
    return;
  }

  if (!realtimeLabels?.length || !realtimeValues?.length) {
    console.warn("⚠️ No realtime data available for the pie chart.");
    return;
  }

  const ctx = pieCanvas.getContext("2d");
  const totalRealtime = realtimeValues.reduce((a, b) => a + b, 0);

  if (pieCanvas.chartInstance) {
    pieCanvas.chartInstance.destroy();
  }

  pieCanvas.chartInstance = new Chart(ctx, {
    type: "pie",
    data: {
      labels: realtimeLabels,
      datasets: [{
        label: "Real-Time Litter Detections",
        data: realtimeValues,
        borderWidth: 1,
        backgroundColor: [
          "#2E7D32", "#388E3C", "#43A047", "#4CAF50", "#66BB6A",
          "#81C784", "#A5D6A7", "#C8E6C9", "#E8F5E9", "#1B5E20"
        ],
        borderColor: "#fff",
        hoverOffset: 12
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: "right",
          labels: {
            color: "#333",
            font: { size: 14, family: "Afacad, sans-serif" },
            boxWidth: 14,
            padding: 8
          }
        },
        tooltip: {
          enabled: true,
          backgroundColor: "rgba(0, 0, 0, 0.85)",
          padding: 10,
          usePointStyle: true,
          titleFont: { weight: "bold", size: 14 },
          bodyFont: { size: 13 },
          callbacks: {
            label: function (context) {
              const label = context.label || "Unknown";
              const value = context.parsed ?? context.raw ?? 0;
              const percent = ((value / totalRealtime) * 100).toFixed(1);
              return `${label}: ${value} detections (${percent}%)`;
            }
          }
        }
      }
    }
  });



  // ============================
  // 🗓️ FILTER TABLE BY DATE (Fixed)
  // ============================
  const dateInput = document.getElementById("date");
  const tableBody = document.getElementById("realtimeTableBody");

  if (dateInput && tableBody) {
    dateInput.addEventListener("change", () => {
      const selectedDate = dateInput.value;
      const rows = tableBody.querySelectorAll("tr[data-date]");
      let visibleCount = 0;

      rows.forEach((row) => {
        const rowDate = row.getAttribute("data-date");
        if (rowDate === selectedDate) {
          row.style.display = "";
          visibleCount++;
        } else {
          row.style.display = "none";
        }
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

    if (dateInput.value) {
      const event = new Event("change");
      dateInput.dispatchEvent(event);
    }
  }


  // ============================
  // 🟨 MAPLIBRE HEATMAP (STATIC)
  // ============================
  const mapContainer = document.getElementById("litterHotspotMap");

  if (mapContainer && Array.isArray(realtimePoints) && realtimePoints.length > 0) {
    console.log(`🔥 Rendering ${realtimePoints.length} realtime heat points...`);

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
            attribution: "© OpenStreetMap, © Carto"
          }
        },
        layers: [{ id: "carto", type: "raster", source: "carto" }]
      },
      center: [avgLng, avgLat],
      zoom: 12
    });

    map.addControl(new maplibregl.NavigationControl(), "top-right");

    map.on("load", () => {
      mapContainer.classList.add("loaded");

      const geojson = {
        type: "FeatureCollection",
        features: realtimePoints.map((p) => ({
          type: "Feature",
          geometry: { type: "Point", coordinates: [p.lng, p.lat] },
          properties: { accuracy: p.accuracy || 1 }
        }))
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
      realtimePoints.forEach((p) => bounds.extend([p.lng, p.lat]));
      map.fitBounds(bounds, { padding: 40, duration: 1000, maxZoom: 14 });
    });
  } else {
    console.warn("⚠️ Heatmap not initialized: Missing container or realtime data.");
  }

});
 