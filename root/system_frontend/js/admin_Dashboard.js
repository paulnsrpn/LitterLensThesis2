
document.addEventListener("DOMContentLoaded", () => {
  if (typeof trendLabels !== "undefined" && typeof trendData !== "undefined") {
    const trendCanvas = document.getElementById("trendChartDash");
    if (trendCanvas) {
      const ctx = trendCanvas.getContext("2d");

      // 🎯 Compute total detections per time unit (day, month, or year)
      const totalPerPeriod = trendLabels.map((_, idx) => {
        return trendData.reduce((sum, dataset) => sum + (dataset.data[idx] || 0), 0);
      });

      // 🟢 Render Bar Chart
      new Chart(ctx, {
        type: "bar",
        data: {
          labels: trendLabels,
          datasets: [{
            label: "Total Litter Detections",
            data: totalPerPeriod,
            backgroundColor: "#52b788",
            borderColor: "#2d6a4f",
            borderWidth: 1,
            borderRadius: 8,
            hoverBackgroundColor: "#40916c",
            hoverBorderColor: "#1b4332",
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: ctx => `Total: ${ctx.parsed.y}`
              }
            }
          },
          scales: {
            x: {
              title: { display: true, text: "Date / Period" },
              ticks: { autoSkip: false, maxRotation: 45, minRotation: 0 }
            },
            y: {
              beginAtZero: true,
              title: { display: true, text: "Total Detections" }
            }
          }
        }
      });
    }
  }



            // ============================
            // 🌍 Real Map + Clean Heatmap
            // ============================
            if (typeof detections === "undefined" || detections.length === 0) {
                console.warn("⚠️ No detection coordinates found.");
                return;
            }

            console.log("✅ Loaded detections:", detections.length);

            // Convert detections → GeoJSON
            const geoData = {
                type: "FeatureCollection",
                features: detections.map(([lat, lng]) => ({
                type: "Feature",
                geometry: { type: "Point", coordinates: [lng, lat] },
                })),
            };

            // Center automatically
            const avgLat = detections.reduce((s, p) => s + p[0], 0) / detections.length;
            const avgLng = detections.reduce((s, p) => s + p[1], 0) / detections.length;

            // 🗺️ Initialize MapLibre with Carto Light tiles
            const map = new maplibregl.Map({
                container: "hotspotMap",
                style: {
                version: 8,
                name: "Carto Light",
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
                layers: [
                    {
                    id: "carto",
                    type: "raster",
                    source: "carto",
                    minzoom: 0,
                    maxzoom: 20,
                    },
                ],
                },
                center: [avgLng, avgLat],
                zoom: 14,
                attributionControl: false,
            });

           map.on("load", () => {
    // Add detection points
    map.addSource("detections", { type: "geojson", data: geoData });

    // 🔥 Heatmap layer
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

    // ✅ Auto-fit map to include all detections
    const bounds = new maplibregl.LngLatBounds();
    detections.forEach(([lat, lng]) => bounds.extend([lng, lat]));

    map.fitBounds(bounds, {
        padding: 60,   // space from edges (px)
        duration: 1000, // smooth zoom animation
        maxZoom: 16     // prevent zooming too far in
    });

    document.getElementById("hotspotMap").classList.add("loaded");
    console.log("✅ Real map with Carto Light + auto-fit heatmap ready");
});
});