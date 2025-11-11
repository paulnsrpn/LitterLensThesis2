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

 
document.addEventListener("DOMContentLoaded", () => {
  const exportBtn = document.getElementById("exportAnalyticsBtn");
  const dropdown = document.getElementById("trendFilterAnalytics");

  if (!exportBtn) return;

  exportBtn.addEventListener("click", () => {
    exportBtn.disabled = true;
    const originalText = exportBtn.innerHTML;

    // Add spinner animation
    exportBtn.innerHTML = `
      <div class="spinner"></div> Exporting...
    `;

    const filter = dropdown.value || "day";
    const exportUrl = `../../system_backend/php/system_admin_data.php?export=analytics&filter=${filter}`;

    // Trigger download
    const link = document.createElement("a");
    link.href = exportUrl;
    link.download = "";
    document.body.appendChild(link);
    link.click();
    link.remove();

    // Simulate short delay for better UX
    setTimeout(() => {
      exportBtn.innerHTML = originalText;
      exportBtn.disabled = false;
    }, 2500);
  });
});
// ======================================================
// 📈 TREND + RISK PER CREEK (Per Litter Type)
// ======================================================
(async function () {
  const creekSelect = document.getElementById("creekSelect");
  const creekFilter = document.getElementById("creekTrendFilter");
  const loader = document.getElementById("creekChartLoader");
  const lineCanvas = document.getElementById("creekLineChart");
  const riskCanvas = document.getElementById("creekRiskChart");
  const summaryBox = document.getElementById("creekSummary");
  const riskLegend = document.getElementById("riskLegend");
  const exportBtn = document.getElementById("exportXlsxBtn");
  let lineChart = null;
  let riskChart = null;

  const showLoader = (show = true, msg = "Loading data...") => {
    loader.style.display = show ? "flex" : "none";
    loader.textContent = msg;
    lineCanvas.style.display = show ? "none" : "block";
    riskCanvas.style.display = show ? "none" : "block";
    if (show) summaryBox.style.display = "none";
  };

  // ======================================================
  // 🏞️ Load Creek List
  // ======================================================
  async function loadCreeks() {
    try {
      const res = await fetch("http://localhost/LitterLensThesis2/root/system_backend/php/system_admin_data.php?ajax=get_creeks");
      const data = await res.json();
      creekSelect.innerHTML = `<option value="" disabled selected>Select Creek</option>`;
      data.forEach(c => creekSelect.innerHTML += `<option value="${c.creek_id}">${c.creek_name}</option>`);
    } catch (err) {
      console.error("❌ Failed to load creeks:", err);
      creekSelect.innerHTML = `<option disabled>Error loading creeks</option>`;
    }
  }

  // ======================================================
  // 📊 Load and Render Charts
  // ======================================================
  async function loadCreekData(creekId, filter = "month") {
    if (!creekId) return;
    showLoader(true, `Loading ${filter} data...`);

    try {
      const res = await fetch(`http://localhost/LitterLensThesis2/root/system_backend/php/system_admin_data.php?ajax=trend_creek&creek_id=${creekId}&filter=${filter}`);
      const data = await res.json();

      if (!data?.labels?.length) {
        showLoader(true, "No data available");
        return;
      }

      showLoader(false);
      const ctx = lineCanvas.getContext("2d");
      if (lineChart) lineChart.destroy();

      // 🎨 Line Chart
      const colors = ["#2E7D32", "#43A047", "#66BB6A", "#81C784", "#A5D6A7", "#C8E6C9"];
      data.datasets.forEach((d, i) => {
        d.borderColor = colors[i % colors.length];
        d.backgroundColor = colors[i % colors.length] + "55";
        d.tension = 0.4;
        d.fill = false;
      });

      lineChart = new Chart(ctx, {
        type: "line",
        data: data,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: "bottom", labels: { boxWidth: 12 } },
            title: { display: true, text: "Litter Detection Trends per Litter Type" }
          },
          scales: {
            x: { title: { display: true, text: "Date" } },
            y: { title: { display: true, text: "Detections" }, beginAtZero: true }
          }
        }
      });

      // ======================================================
      // 📊 Risk Bar Chart
      // ======================================================
      if (!Array.isArray(data.risk) || !data.risk.length) {
        console.warn("⚠️ No risk data for selected creek");
        riskCanvas.style.display = "none";
        riskLegend.style.display = "none";
        return;
      }

      riskCanvas.style.display = "block";
      riskLegend.style.display = "block";

      const rctx = riskCanvas.getContext("2d");
      if (riskChart) riskChart.destroy();

      const labels = data.risk.map(r => r.litter_type || "Unknown");
      const values = data.risk.map(r => r.total || 0);
      const colorsRisk = data.risk.map(r =>
        r.risk_level === "High" ? "#C62828" :
        r.risk_level === "Moderate" ? "#FFB300" : "#2E7D32"
      );

      riskChart = new Chart(rctx, {
        type: "bar",
        data: {
          labels,
          datasets: [{
            label: "Total Detections (Risk Level)",
            data: values,
            backgroundColor: colorsRisk
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            title: { display: true, text: "Litter Risk Analysis per Litter Type" },
            tooltip: {
              callbacks: {
                label: ctx => `${ctx.label}: ${ctx.formattedValue} detections`
              }
            }
          },
          scales: {
            x: { title: { display: true, text: "Litter Type" } },
            y: { title: { display: true, text: "Total Detections" }, beginAtZero: true }
          }
        }
      });

      // ======================================================
      // 🧠 Smart Summary Insight
      // ======================================================
      summaryBox.style.display = "block";

      const totalAll = values.reduce((a, b) => a + b, 0);
      const highest = data.risk.reduce((a, b) => (a.total > b.total ? a : b));
      const firstDate = data.labels[0];
      const lastDate = data.labels[data.labels.length - 1];

      const trendMessage = (() => {
        if (highest.risk_level === "High")
          return `⚠️ <b>${highest.litter_type}</b> has the highest accumulation this period. Next month should prioritize cleanup in <b>${highest.litter_type}</b> areas.`;
        if (highest.risk_level === "Moderate")
          return `🟡 <b>${highest.litter_type}</b> shows moderate litter activity. Schedule extra monitoring or awareness drives.`;
        return `🟢 Great progress! <b>${highest.litter_type}</b> remains under control. Continue cleanup consistency.`;
      })();

      const overall = (() => {
        if (totalAll > 200)
          return `<b>Overall litter detection is high</b> (${totalAll} items). Plan additional cleanups next month (${new Date().toLocaleString('default',{ month:'long'})}).`;
        if (totalAll > 100)
          return `<b>Litter accumulation is moderate</b> (${totalAll} items). Maintain cleanup schedule.`;
        return `<b>Low litter detection</b> (${totalAll} items). Maintain awareness campaigns and periodic cleaning.`;
      })();

      let borderColor = "#2E7D32";
      if (totalAll > 200) borderColor = "#C62828";
      else if (totalAll > 100) borderColor = "#FFB300";

      summaryBox.style.borderLeft = `5px solid ${borderColor}`;
      summaryBox.innerHTML = `
        <strong>📅 Summary Insight for ${creekSelect.options[creekSelect.selectedIndex].text}</strong><br>
        <small>Period: ${firstDate} → ${lastDate}</small><br><br>
        ${trendMessage}<br><br>
        <em>${overall}</em>
      `;

    } catch (err) {
      console.error("❌ Trend Creek fetch error:", err);
      showLoader(true, "Error loading data.");
    }
  }

  // ======================================================
  // 💾 XLSX Export Function with Loading
  // ======================================================
  exportBtn.addEventListener("click", async () => {
    const creekId = creekSelect.value;
    const filter = creekFilter.value;
    const creekName = creekSelect.options[creekSelect.selectedIndex]?.text || "Unknown Creek";

    if (!creekId) {
      alert("Please select a creek first!");
      return;
    }

    const originalText = exportBtn.innerHTML;
    exportBtn.disabled = true;
    exportBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Exporting...`;

    try {
      const res = await fetch(`http://localhost/LitterLensThesis2/root/system_backend/php/system_admin_data.php?ajax=trend_creek&creek_id=${creekId}&filter=${filter}`);
      const data = await res.json();

      if (!data?.labels?.length) {
        alert("No data available to export.");
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
        return;
      }

      const sheet1 = [];
      sheet1.push(["Trend Analytics per Creek"]);
      sheet1.push(["Creek:", creekName]);
      sheet1.push(["Filter:", filter]);
      sheet1.push([]);
      sheet1.push(["Date", ...data.datasets.map(d => d.label)]);

      data.labels.forEach((lbl, idx) => {
        const row = [lbl];
        data.datasets.forEach(d => row.push(d.data[idx] || 0));
        sheet1.push(row);
      });

      sheet1.push([]);
      sheet1.push(["Risk Level Summary"]);
      sheet1.push(["Litter Type", "Total Detections", "Risk Level"]);

      data.risk.forEach(r => {
        sheet1.push([r.litter_type, r.total, r.risk_level]);
      });

      const wb = XLSX.utils.book_new();
      const ws = XLSX.utils.aoa_to_sheet(sheet1);
      XLSX.utils.book_append_sheet(wb, ws, "Creek Trend");
      XLSX.writeFile(wb, `TrendAnalytics_${creekName.replace(/\s+/g, "_")}.xlsx`);

    } catch (err) {
      console.error("❌ XLSX Export Error:", err);
      alert("Failed to export XLSX file. See console for details.");
    } finally {
      exportBtn.innerHTML = `<i class="fas fa-file-excel"></i> Export Analytics (CSV)`;
      exportBtn.disabled = false;
    }
  });

  // ======================================================
  // ⚙️ Initialize
  // ======================================================
  await loadCreeks();
  creekSelect.addEventListener("change", () => loadCreekData(creekSelect.value, creekFilter.value));
  creekFilter.addEventListener("change", () => {
    if (creekSelect.value) loadCreekData(creekSelect.value, creekFilter.value);
  });

})(); // 👈 keep this at the end
