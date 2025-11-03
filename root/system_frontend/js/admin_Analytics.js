document.addEventListener("DOMContentLoaded", async () => {
  // ===============================
  // 🥧 PIE CHART: ANALYTICS OVERVIEW
  // ===============================
  if (typeof litterLabels !== "undefined" && Array.isArray(litterLabels) && litterLabels.length > 0) {
    const analyticsCanvas = document.getElementById("pieChart");

    if (analyticsCanvas && typeof Chart !== "undefined") {
      if (analyticsCanvas.chartInstance) analyticsCanvas.chartInstance.destroy();

      analyticsCanvas.chartInstance = new Chart(analyticsCanvas.getContext("2d"), {
        type: "pie",
        data: {
          labels: litterLabels,
          datasets: [{
            label: "Litter Detected",
            data: litterValues,
            borderWidth: 1,
            backgroundColor: [
              "#2E7D32", "#388E3C", "#43A047", "#4CAF50", "#66BB6A",
              "#81C784", "#A5D6A7", "#C8E6C9", "#E8F5E9", "#1B5E20"
            ],
            hoverOffset: 10
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
                font: { size: 14 }
              }
            },
            tooltip: {
              callbacks: {
                label: context =>
                  `${context.label}: ${context.formattedValue} detections`
              }
            }
          }
        }
      });
    } else {
      console.warn("⚠️ Chart.js or canvas element not found for Analytics Pie Chart.");
    }
  }

// ===============================
// 📍 GEOLOCATION MAP (Pinpoint Locations with Lat/Lng Popup + Pointer Cursor)
// ===============================
if (typeof maplibregl !== "undefined") {
  const mapContainer = document.getElementById("a-map");

  if (mapContainer) {
    const map = new maplibregl.Map({
      container: "a-map",
      style: "https://basemaps.cartocdn.com/gl/positron-gl-style/style.json",
      center: [120.9842, 14.5995], // Default center (Manila)
      zoom: 5
    });

    // Add zoom controls
    map.addControl(new maplibregl.NavigationControl(), "top-right");

    if (Array.isArray(imageCoordinates) && imageCoordinates.length > 0) {
      const bounds = new maplibregl.LngLatBounds();

      imageCoordinates.forEach(([lat, lng]) => {
        if (!lat || !lng) return;

        // 🟩 Create marker
        const marker = new maplibregl.Marker({ color: "#2E7D32" })
          .setLngLat([lng, lat])
          .addTo(map);

        // 🧭 Create popup with coordinates
        const popup = new maplibregl.Popup({
          closeButton: false,
          offset: 20
        }).setHTML(`
          <div style="font-family:Poppins, sans-serif; font-size:13px; color:#333;">
            <strong>📍 Coordinates</strong><br>
            Latitude: ${lat.toFixed(5)}<br>
            Longitude: ${lng.toFixed(5)}
          </div>
        `);

        const el = marker.getElement();

        // 🖱️ Add pointer cursor on hover
        el.style.cursor = "pointer";

        // Show popup on hover, hide on leave
        el.addEventListener("mouseenter", () => popup.addTo(map).setLngLat([lng, lat]));
        el.addEventListener("mouseleave", () => popup.remove());

        // Extend bounds for fitting
        bounds.extend([lng, lat]);
      });

      // Fit map to all markers
      map.fitBounds(bounds, { padding: 50 });
    } else {
      console.warn("⚠️ No coordinates available for image map.");
    }
  }
} else {
  console.warn("⚠️ MapLibre library not loaded.");
}

});



document.addEventListener("DOMContentLoaded", () => {
  const loader = document.getElementById("lineChartLoader");
  const canvas = document.getElementById("lineChart");
  const filterSelect = document.getElementById("trendFilterAnalytics");
  let lineChart = null;

  // 🟢 Loader show/hide
  function showChartLoader(show = true, message = "Loading data...") {
    if (!loader || !canvas) return;
    loader.style.display = show ? "flex" : "none";
    loader.textContent = message;
    canvas.style.display = show ? "none" : "block";
  }

  function updateLineChart(labels, datasets) {
  if (!labels?.length || !datasets?.length) {
    showChartLoader(true, "No data available.");
    return;
  }

  showChartLoader(false);

  const ctx = canvas.getContext("2d");

  // 🌿 Apply unified green tone colors for all datasets
  const greenPalette = [
    "#2E7D32", "#388E3C", "#43A047", "#4CAF50", "#66BB6A",
    "#81C784", "#A5D6A7", "#C8E6C9", "#E8F5E9", "#1B5E20"
  ];

  datasets.forEach((ds, index) => {
    ds.borderColor = greenPalette[index % greenPalette.length];
    ds.backgroundColor = greenPalette[index % greenPalette.length] + "55"; // slight transparency
    ds.borderWidth = 3;
    ds.pointRadius = 4;
    ds.pointHoverRadius = 6;
    ds.tension = 0.4; // curve
    ds.fill = false;
  });

  if (lineChart) {
    // ✅ Update existing chart smoothly
    lineChart.data.labels = labels;
    lineChart.data.datasets = datasets;
    lineChart.update();
  } else {
    // ✅ Create new styled chart
    lineChart = new Chart(ctx, {
      type: "line",
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: { top: 20, right: 25, bottom: 10, left: 10 }
        },
        animation: {
          duration: 800,
          easing: "easeInOutQuart"
        },
        plugins: {
        legend: {
            position: "bottom",
            align: "start", // Align neatly left-to-right
            labels: {
            boxWidth: 12,
            usePointStyle: true,
            pointStyle: "circle",
            color: "#2E7D32",
            font: {
                size: 12,
                weight: "500",
                family: "Poppins, sans-serif"
            },
            padding: 12
            },
            title: {
            display: false
            }
        },
          tooltip: {
            backgroundColor: "#2E7D32",
            titleColor: "#fff",
            bodyColor: "#fff",
            titleFont: { weight: "bold" },
            bodyFont: { size: 13 },
            displayColors: false,
            callbacks: {
              label: context => ` ${context.dataset.label}: ${context.formattedValue} detections`
            }
          }
        },
        interaction: { mode: "nearest", axis: "x", intersect: false },
        scales: {
          x: {
            grid: {
              color: "rgba(200, 200, 200, 0.2)"
            },
            ticks: {
              color: "#333",
              font: { size: 12, family: "Poppins, sans-serif" },
              maxRotation: 0,
              autoSkipPadding: 15
            },
            title: {
              display: true,
              text: "Period",
              color: "#2E7D32",
              font: { size: 13, weight: "600" }
            }
          },
          y: {
            beginAtZero: true,
            grid: {
              color: "rgba(200, 200, 200, 0.2)"
            },
            ticks: {
              color: "#333",
              font: { size: 12, family: "Poppins, sans-serif" },
              padding: 8
            },
            title: {
              display: true,
              text: "Detections",
              color: "#2E7D32",
              font: { size: 13, weight: "600" }
            }
          }
        }
      }
    });
  }
}

  // 🚀 Fetch data from backend
  async function fetchChartData(filter = "day") {
    showChartLoader(true, `Loading ${filter} data...`);
    const url = `http://localhost/LitterLensThesis2/root/system_backend/php/system_admin_data.php?ajax=trend&trend_filter=${filter}`;

    try {
      const response = await fetch(url);
      const data = await response.json();

      console.log("📦 Loaded data for", filter, data);

      if (data?.labels && data?.datasets) {
        updateLineChart(data.labels, data.datasets);
      } else {
        showChartLoader(true, "No data returned from server.");
      }
    } catch (err) {
      showChartLoader(true, "Error loading data.");
      console.error("❌ Fetch failed:", err);
    }
  }

  // 🧠 Initialize chart
  async function initChart() {
    await fetchChartData("day"); // Default on load
  }

  initChart();

  // 🔁 Dropdown change handler
  filterSelect?.addEventListener("change", async (e) => {
    const selected = e.target.value;
    console.log("🔁 Changing chart to:", selected);
    await fetchChartData(selected);
  });
});
