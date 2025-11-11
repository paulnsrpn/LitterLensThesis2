document.addEventListener("DOMContentLoaded", () => {
  // ==============================
  // 📊 BAR CHART (Total Litter Detections)
  // ==============================
  const trendCanvas = document.getElementById("trendChartDash");
  const trendLoader = document.getElementById("trendChartLoader");
  const trendFilterSelect = document.getElementById("trendFilter");
  let trendChart = null;

  // Loader visibility
  function showTrendChartLoader(show = true, message = "Loading trend data...") {
    if (!trendLoader || !trendCanvas) return;
    trendLoader.style.display = show ? "flex" : "none";
    trendLoader.textContent = message;
    trendCanvas.style.display = show ? "none" : "block";
  }

  // Render / Update Chart
  function renderTrendBarChart(labels, datasets) {
    showTrendChartLoader(false);
    const ctx = trendCanvas.getContext("2d");

    if (trendChart) {
      trendChart.data.labels = labels;
      trendChart.data.datasets = datasets;
      trendChart.update();
    } else {
      trendChart = new Chart(ctx, {
        type: "bar",
        data: { labels, datasets },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: { duration: 500, easing: "easeOutQuart" },
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: "rgba(0,0,0,0.8)",
              padding: 10,
              callbacks: {
                label: (context) => `Total: ${context.parsed.y}`,
              },
            },
          },
          scales: {
            x: {
              title: { display: true, text: "Date / Period" },
              ticks: { autoSkip: false, maxRotation: 45 },
              grid: { display: false },
            },
            y: {
              beginAtZero: true,
              title: { display: true, text: "Total Detections" },
              grid: { color: "rgba(0,0,0,0.05)" },
            },
          },
        },
      });
    }
  }

  // Fetch Chart Data
  async function fetchTrendData(filter = "month") {
    showTrendChartLoader(true, `Loading ${filter} data...`);
    try {
      const response = await fetch(
        `http://localhost/LitterLensThesis2/root/system_backend/php/system_admin_data.php?ajax=trend_total&trend_filter=${filter}`
      );
      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const data = await response.json();

      if (data?.labels?.length && data?.datasets?.length) {
        const colors = [
          "#2E7D32", "#388E3C", "#43A047", "#4CAF50", "#66BB6A",
          "#81C784", "#A5D6A7", "#C8E6C9", "#E8F5E9", "#1B5E20",
        ];

        data.datasets[0].backgroundColor = colors;
        data.datasets[0].borderColor = "#1B4332";
        data.datasets[0].borderRadius = 8;
        data.datasets[0].hoverBackgroundColor = "#40916c";
        data.datasets[0].hoverBorderColor = "#1b4332";

        renderTrendBarChart(data.labels, data.datasets);
      } else {
        showTrendChartLoader(true, "No data available for this period.");
      }
    } catch {
      showTrendChartLoader(true, "Error loading chart data.");
    }
  }

  // Initial Load + Filter Change
  fetchTrendData("month");
  if (trendFilterSelect) {
    trendFilterSelect.addEventListener("change", (e) => {
      const selected = e.target.value.toLowerCase();
      fetchTrendData(selected);
    });
  }

  // ============================
  // 🌍 HEATMAP (MapLibre)
  // ============================
  if (typeof detections === "undefined" || detections.length === 0) return;

  const geoData = {
    type: "FeatureCollection",
    features: detections.map(([lat, lng]) => ({
      type: "Feature",
      geometry: { type: "Point", coordinates: [lng, lat] },
    })),
  };

  const avgLat = detections.reduce((s, p) => s + p[0], 0) / detections.length;
  const avgLng = detections.reduce((s, p) => s + p[1], 0) / detections.length;

  const map = new maplibregl.Map({
    container: "hotspotMap",
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
    zoom: 14,
    attributionControl: false,
  });

  map.on("load", () => {
    map.addSource("detections", { type: "geojson", data: geoData });
    map.addLayer({
      id: "detections-heat",
      type: "heatmap",
      source: "detections",
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
    detections.forEach(([lat, lng]) => bounds.extend([lng, lat]));
    map.fitBounds(bounds, { padding: 60, duration: 1000, maxZoom: 16 });
    document.getElementById("hotspotMap").classList.add("loaded");
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const timeFilter = document.getElementById("timeFilter");
  const activityLoader = document.getElementById("activityLoader");
  const activityTable = document.getElementById("activityTable").querySelector("tbody");

  // Toast elements
  const toast = document.getElementById("activityToast");
  const toastImage = document.getElementById("toastImage");
  const toastTitle = document.getElementById("toastTitle");
  const toastDate = document.getElementById("toastDate");
  const toastInfo = document.getElementById("toastInfo");
  const toastClose = document.getElementById("toastClose");

  // Loader helper
  function showLoader(show = true, msg = "Loading recent activity...") {
    const loaderText = document.querySelector("#activityLoader .loader-text");
    if (loaderText) loaderText.textContent = msg;
    activityLoader.style.display = show ? "flex" : "none";
    activityTable.style.opacity = show ? "0.3" : "1";
  }

  // 🧾 Toast logic (with fade animation)
  function showToast(imageUrl, date, time) {
    toastImage.src = imageUrl;
    toastTitle.textContent = "Detection Summary";
    toastDate.textContent = `🕒 ${date} ${time}`;
    toastInfo.textContent =
      `Captured image showing litter detection. Review cleanup records for this detection.`;

    toast.classList.add("show"); // smooth fade in
    toast.style.display = "flex";

    // Auto-hide after 6 seconds
    setTimeout(() => {
      toast.classList.remove("show");
      setTimeout(() => (toast.style.display = "none"), 300); // fade out delay
    }, 6000);
  }

  toastClose.addEventListener("click", () => {
    toast.classList.remove("show");
    setTimeout(() => (toast.style.display = "none"), 300);
  });

  // Fetch activity data dynamically
  async function fetchRecentActivity(filter = "today") {
    showLoader(true, `Loading ${filter === "today" ? "today's" : "last 7 days'"} data...`);
    try {
      const response = await fetch(
        `http://localhost/LitterLensThesis2/root/system_backend/php/system_admin_data.php?ajax=recent_activity&time_filter=${filter}`
      );
      if (!response.ok) throw new Error("Network error");

      const data = await response.json();
      activityTable.innerHTML = ""; // clear previous rows

      if (Array.isArray(data) && data.length > 0) {
        data.forEach((row) => {
          const dateObj = new Date(`${row.date}T${row.time}`);
          const formattedDate = dateObj.toLocaleDateString("en-US", {
            month: "long",
            day: "numeric",
            year: "numeric",
          });
          const formattedTime = dateObj.toLocaleTimeString("en-US", {
            hour: "numeric",
            minute: "2-digit",
          });

          const tr = document.createElement("tr");
          tr.innerHTML = `
            <td>${formattedDate}<br><small>${formattedTime}</small></td>
            <td><img src="${row.image_url}" alt="Thumbnail" width="60" style="border-radius: 8px;"></td>
            <td>New Detection</td>
            <td><button class="view-btn" data-image="${row.image_url}" data-date="${row.date}" data-time="${row.time}">View</button></td>
          `;

          // ✅ Attach the toast trigger
          tr.querySelector(".view-btn").addEventListener("click", (e) => {
            const btn = e.currentTarget;
            showToast(btn.dataset.image, btn.dataset.date, btn.dataset.time);
          });

          activityTable.appendChild(tr);
        });
      } else {
        activityTable.innerHTML = `<tr><td colspan="4" style="text-align:center;">No recent activity</td></tr>`;
      }

      showLoader(false);
    } catch (err) {
      console.error("Error loading activity data:", err);
      showLoader(true, "Error loading activity data.");
    }
  }

  // Initial load + dropdown change
  fetchRecentActivity("today");
  timeFilter.addEventListener("change", (e) => {
    fetchRecentActivity(e.target.value.toLowerCase());
  });
});
