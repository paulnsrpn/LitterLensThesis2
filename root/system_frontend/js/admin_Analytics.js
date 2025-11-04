document.addEventListener("DOMContentLoaded", async () => {
  // ======================================================
  // 🥧 PIE CHART: DETECTIONS BY CATEGORY
  // ======================================================
  if (Array.isArray(litterLabels) && litterLabels.length > 0 && typeof Chart !== "undefined") {
    const pieCanvas = document.getElementById("pieChart");

    if (pieCanvas) {
      if (pieCanvas.chartInstance) pieCanvas.chartInstance.destroy();

      const colors = [
        "#2E7D32", "#388E3C", "#43A047", "#4CAF50", "#66BB6A",
        "#81C784", "#A5D6A7", "#C8E6C9", "#E8F5E9", "#1B5E20"
      ];

      pieCanvas.chartInstance = new Chart(pieCanvas.getContext("2d"), {
        type: "pie",
        data: {
          labels: litterLabels,
          datasets: [{
            label: "Litter Detected",
            data: litterValues,
            backgroundColor: colors,
            borderWidth: 1,
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
                font: { size: 14, family: "Poppins" }
              }
            },
            tooltip: {
              callbacks: {
                label: ctx => `${ctx.label}: ${ctx.formattedValue} detections`
              }
            }
          }
        }
      });
    }
  } else {
    console.warn("⚠️ Missing pie chart data or Chart.js not loaded.");
  }

  // ======================================================
  // 📍 GEO MAP: IMAGE COORDINATE PINPOINT
  // ======================================================
  if (typeof maplibregl !== "undefined" && document.getElementById("a-map")) {
    const map = new maplibregl.Map({
      container: "a-map",
      style: "https://basemaps.cartocdn.com/gl/positron-gl-style/style.json",
      center: [120.9842, 14.5995], // Manila
      zoom: 5
    });

    map.addControl(new maplibregl.NavigationControl(), "top-right");

    if (Array.isArray(imageCoordinates) && imageCoordinates.length > 0) {
      const bounds = new maplibregl.LngLatBounds();

      imageCoordinates.forEach(([lat, lng]) => {
        if (!lat || !lng) return;

        const marker = new maplibregl.Marker({ color: "#2E7D32" })
          .setLngLat([lng, lat])
          .addTo(map);

        const popup = new maplibregl.Popup({ closeButton: false, offset: 20 }).setHTML(`
          <div style="font-family:Poppins, sans-serif; font-size:13px; color:#333;">
            <strong>📍 Coordinates</strong><br>
            Latitude: ${lat.toFixed(5)}<br>
            Longitude: ${lng.toFixed(5)}
          </div>
        `);

        const el = marker.getElement();
        el.style.cursor = "pointer";
        el.addEventListener("mouseenter", () => popup.addTo(map).setLngLat([lng, lat]));
        el.addEventListener("mouseleave", () => popup.remove());

        bounds.extend([lng, lat]);
      });

      map.fitBounds(bounds, { padding: 50 });
    } else {
      console.warn("⚠️ No coordinates available for map display.");
    }
  } else {
    console.warn("⚠️ MapLibre not loaded or map container missing.");
  }

  // ======================================================
  // 📈 LINE CHART: LITTER TRENDS (Day / Month / Year)
  // ======================================================
  const loader = document.getElementById("lineChartLoader");
  const canvas = document.getElementById("lineChart");
  const filterSelect = document.getElementById("trendFilterAnalytics");
  let lineChart = null;

  const showChartLoader = (show = true, msg = "Loading data...") => {
    if (!loader || !canvas) return;
    loader.style.display = show ? "flex" : "none";
    loader.textContent = msg;
    canvas.style.display = show ? "none" : "block";
  };

  const updateLineChart = (labels, datasets) => {
    if (!labels?.length || !datasets?.length) return showChartLoader(true, "No data available.");

    showChartLoader(false);
    const ctx = canvas.getContext("2d");
    const greenPalette = [
      "#2E7D32", "#388E3C", "#43A047", "#4CAF50", "#66BB6A",
      "#81C784", "#A5D6A7", "#C8E6C9", "#E8F5E9", "#1B5E20"
    ];

    datasets.forEach((ds, i) => Object.assign(ds, {
      borderColor: greenPalette[i % greenPalette.length],
      backgroundColor: greenPalette[i % greenPalette.length] + "55",
      borderWidth: 3,
      pointRadius: 4,
      pointHoverRadius: 6,
      tension: 0.4,
      fill: false
    }));

    if (lineChart) {
      lineChart.data = { labels, datasets };
      lineChart.update();
    } else {
      lineChart = new Chart(ctx, {
        type: "line",
        data: { labels, datasets },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: { duration: 800, easing: "easeInOutQuart" },
          plugins: {
            legend: {
              position: "bottom",
              align: "start",
              labels: {
                boxWidth: 12,
                usePointStyle: true,
                pointStyle: "circle",
                color: "#2E7D32",
                font: { size: 12, family: "Poppins, sans-serif" }
              }
            },
            tooltip: {
              backgroundColor: "#2E7D32",
              titleColor: "#fff",
              bodyColor: "#fff",
              displayColors: false,
              callbacks: {
                label: ctx => ` ${ctx.dataset.label}: ${ctx.formattedValue} detections`
              }
            }
          },
          interaction: { mode: "nearest", axis: "x", intersect: false },
          scales: {
            x: {
              title: { display: true, text: "Period", color: "#2E7D32", font: { size: 13, weight: "600" } },
              ticks: { color: "#333", font: { size: 12, family: "Poppins" } },
              grid: { color: "rgba(200,200,200,0.2)" }
            },
            y: {
              beginAtZero: true,
              title: { display: true, text: "Detections", color: "#2E7D32", font: { size: 13, weight: "600" } },
              ticks: { color: "#333", font: { size: 12, family: "Poppins" } },
              grid: { color: "rgba(200,200,200,0.2)" }
            }
          }
        }
      });
    }
  };

  async function fetchChartData(filter = "day") {
    showChartLoader(true, `Loading ${filter} data...`);
    try {
      const res = await fetch(`http://localhost/LitterLensThesis2/root/system_backend/php/system_admin_data.php?ajax=trend&trend_filter=${filter}`);
      const data = await res.json();
      console.log(`📊 Loaded ${filter} trend:`, data);

      if (data?.labels && data?.datasets) updateLineChart(data.labels, data.datasets);
      else showChartLoader(true, "No data returned.");
    } catch (err) {
      console.error("❌ Fetch failed:", err);
      showChartLoader(true, "Error loading data.");
    }
  }

  await fetchChartData("day");
  filterSelect?.addEventListener("change", e => fetchChartData(e.target.value));
});


