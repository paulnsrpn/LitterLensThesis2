document.addEventListener("DOMContentLoaded", () => {
  const imgElement = document.querySelector(".main-img");
  const prevBtn = document.querySelector(".prev-btn");
  const nextBtn = document.querySelector(".next-btn");
  const fileNameElement = document.getElementById("file-name-display");
  const thresholdDropdown = document.querySelector(".threshold-dropdown");
  const switchInput = document.querySelector(".switch input");
  const goBackBtn = document.getElementById("go-back-btn");
  const imgContainer = document.querySelector(".img-container");

  
  let detectionResult = JSON.parse(localStorage.getItem("detectionResult"));
  let currentIndex = 0;
  let showingResult = true;

  

  // 🌀 Spinner overlay inside image container
  const spinnerOverlay = document.createElement("div");
  spinnerOverlay.classList.add("spinner-overlay");
  spinnerOverlay.innerHTML = `
    <div class="spinner"></div>
    <p>Updating threshold...</p>
  `; 
  imgContainer.appendChild(spinnerOverlay);

  function showSpinner() {
    spinnerOverlay.classList.add("active");
  }

  function hideSpinner() {
    spinnerOverlay.classList.remove("active");
  }

  // 🖼️ Update image and filename display
  function updateImageDisplay(src) {
    imgElement.src = src + `?t=${Date.now()}`; // prevent cache issues
    const filename = src.split("/").pop();
    fileNameElement.textContent =
      filename.length > 15 ? filename.substring(0, 15) + "..." : filename;
  }

  // 🧾 Update detection table
  function updateTable(summary, total) {
    const table = document.querySelector(".receipt table");
    const title = document.querySelector(".receipt-card h2");
    title.textContent = `${total} Items detected`;
    table.querySelectorAll("tr:not(:first-child)").forEach(tr => tr.remove());

    Object.entries(summary || {}).forEach(([label, count]) => {
      const row = document.createElement("tr");
      row.innerHTML = `<td>${label}</td><td>${count}</td>`;
      table.appendChild(row);
    });
  }

  // 📊 Update accuracy text
  function updateAccuracy(accuracy) {
    const accuracyElement = document.querySelector(".accuracy");
    accuracyElement.textContent = `Detection Accuracy: ${accuracy ?? 0}%`;
  }

  // 🔥 Show image based on index and before/after state
  function showImage(index) {
    if (!detectionResult?.results || detectionResult.results.length === 0) return;

    const imgData = detectionResult.results[index];
    const src = showingResult
      ? `http://127.0.0.1:5000/${imgData.result_image}`
      : `http://127.0.0.1:5000/${imgData.original_image}`;

    updateImageDisplay(src);
    updateTable(imgData.summary, imgData.total_items);
    updateAccuracy(imgData.accuracy);
  }

  // ⏮ Image navigation
  prevBtn.addEventListener("click", () => {
    currentIndex = (currentIndex - 1 + detectionResult.results.length) % detectionResult.results.length;
    showImage(currentIndex);
  });

  nextBtn.addEventListener("click", () => {
    currentIndex = (currentIndex + 1) % detectionResult.results.length;
    showImage(currentIndex);
  });

  // 🆚 Before/After switch
  switchInput.addEventListener("change", (e) => {
    showingResult = e.target.checked;
    showImage(currentIndex);
  });

  // 🎚️ Optional: threshold redetect if needed
  thresholdDropdown.addEventListener("change", () => {
    const newThreshold = parseFloat(thresholdDropdown.value);
    if (!detectionResult || !detectionResult.folder) return;

    showSpinner();

    fetch("http://127.0.0.1:5000/redetect", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        folder: detectionResult.folder,
        threshold: newThreshold
      }),
    })
      .then(res => res.json())
      .then(data => {
        hideSpinner();
        if (data.error) {
          console.error("❌ Redetect error:", data.error);
          return;
        }
        detectionResult.results = data.results;
        localStorage.setItem("detectionResult", JSON.stringify(detectionResult));
        showImage(currentIndex);
      })
      .catch(err => {
        hideSpinner();
        console.error("❌ Redetect fetch failed:", err);
      });
  });

  // ⏪ Go back & cleanup saved results
  goBackBtn.addEventListener("click", () => {
    if (detectionResult && detectionResult.folder) {
      fetch("http://127.0.0.1:5000/cleanup", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ folder: detectionResult.folder }),
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

  // ✅ Initialize display
  switchInput.checked = true;
  showingResult = true;
  showImage(currentIndex);
});
