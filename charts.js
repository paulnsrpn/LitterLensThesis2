new Chart(document.getElementById("pieChart"), {
        type: "pie",
        data: {
          labels: ["Plastic Bottle", "Plastic Bag", "Styrofoam", "Other"],
          datasets: [{
            data: [40, 20, 25, 15],
            backgroundColor: ["#221d4bff", "#38717eff", "#254d6cff", "#15431eff"]
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
      new Chart(document.getElementById("barChart"), {
        type: "bar",
        data: {
          labels: ["Plastic Bottle", "Plastic Bag", "Styrofoam", "Other"],
          datasets: [{
            label: "Items",
            data: [120, 90, 60, 30],
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