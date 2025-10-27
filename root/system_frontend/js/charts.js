document.addEventListener("DOMContentLoaded", () => {
  const pieCtx = document.getElementById("pieChart");
  const barCtx = document.getElementById("barChart");

  // Default colors if there are many categories
  const colors = [
    "#221d4bff", "#38717eff", "#254d6cff", "#15431eff",
    "#7b1fa2", "#c2185b", "#0288d1", "#f57c00", "#388e3c", "#c62828"
  ];

  if (litterLabels.length === 0) {
    console.warn("No data for charts.");
    return;
  }

  // Pie Chart
  new Chart(pieCtx, {
    type: "pie",
    data: {
      labels: litterLabels,
      datasets: [{
        data: litterValues,
        backgroundColor: colors.slice(0, litterLabels.length)
      }]
    },
    options: {
      plugins: {
        legend: {
          display: true,
          position: "bottom",
          labels: { font: { size: 11 } }
        }
      }
    }
  });

  // Bar Chart
  new Chart(barCtx, {
    type: "bar",
    data: {
      labels: litterLabels,
      datasets: [{
        label: "Items",
        data: litterValues,
        backgroundColor: "#2e3272ff"
      }]
    },
    options: {
      plugins: {
        legend: {
          display: true,
          position: "bottom",
          labels: { font: { size: 11 } }
        }
      },
      scales: {
        x: { ticks: { font: { size: 11 } } },
        y: { ticks: { font: { size: 11 } } }
      }
    }
  });
});
