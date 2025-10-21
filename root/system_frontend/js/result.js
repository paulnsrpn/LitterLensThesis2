// ====================
// RESET ANALYSIS
// ====================
function resetAnalysis() {
  localStorage.removeItem("analyze_result");
  localStorage.removeItem("uploaded_images");
  window.location.href = "homePage.html#upload-page";
}

document.addEventListener("DOMContentLoaded", () => {
  const results = JSON.parse(localStorage.getItem("analyze_result"));
  const uploadedImages = JSON.parse(localStorage.getItem("uploaded_images"));

  if (!results || !uploadedImages) {
    alert("No result found. Please analyze an image first.");
    window.location.href = "homePage.html";
    return;
  }

  // If backend returns array (multiple results)
  const resultArray = Array.isArray(results) ? results : [results];
  let currentIndex = 0;

  // DOM elements
  const resultImg = document.querySelector(".detected-image");
  const receipt = document.querySelector(".results-receipt");
  const itemCount = document.getElementById("item-count");
  const accuracyValue = document.getElementById("accuracy-value");
  const prevBtn = document.querySelector(".prev-btn");
  const nextBtn = document.querySelector(".next-btn");

  // ====================
  // DISPLAY CURRENT IMAGE + DATA
  // ====================
  function displayResult(index) {
    const result = resultArray[index];
    const imageBase64 = uploadedImages[index];

    // 1. Image
    if (resultImg) {
      if (result.result_image) {
        // server-processed version
        resultImg.src = `http://localhost:5000/uploads/${result.result_image}`;
      } else {
        // fallback to raw uploaded image
        resultImg.src = imageBase64;
      }
    }

    // 2. Summary table
    receipt.querySelectorAll(".results-item").forEach(el => el.remove());

    let totalCount = 0;
    for (const [label, count] of Object.entries(result.summary || {})) {
      const item = document.createElement("div");
      item.classList.add("results-item");

      const name = document.createElement("p");
      name.className = "item-name";
      name.textContent = label;

      const countEl = document.createElement("p");
      countEl.className = "item-count";
      countEl.textContent = count;

      item.appendChild(name);
      item.appendChild(countEl);
      receipt.insertBefore(item, document.querySelector(".other-infos"));
      totalCount += count;
    }

    // 3. Counts and accuracy
    if (itemCount) itemCount.textContent = totalCount;
    if (accuracyValue && result.accuracy !== undefined)
      accuracyValue.textContent = `${result.accuracy}%`;
  }

  // Initial load
  displayResult(currentIndex);

  // ====================
  // NAVIGATION (Prev / Next)
  // ====================
  function updateNavButtons() {
    prevBtn.disabled = currentIndex === 0;
    nextBtn.disabled = currentIndex === resultArray.length - 1;
  }

  prevBtn.addEventListener("click", () => {
    if (currentIndex > 0) {
      currentIndex--;
      displayResult(currentIndex);
      updateNavButtons();
    }
  });

  nextBtn.addEventListener("click", () => {
    if (currentIndex < resultArray.length - 1) {
      currentIndex++;
      displayResult(currentIndex);
      updateNavButtons();
    }
  });

  updateNavButtons();

  // ====================
  // PDF GENERATION
  // ====================
  document.querySelector('.download-btn').addEventListener('click', async () => {
    const result = resultArray[currentIndex];
    if (!result) {
      alert("No data to export.");
      return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();

    // === Border & Header ===
    doc.setDrawColor(160, 160, 160);
    doc.rect(10, 10, pageWidth - 20, pageHeight - 20);
    doc.setFillColor(34, 49, 63);
    doc.rect(10, 10, pageWidth - 20, 15, 'F');
    doc.setFontSize(16);
    doc.setTextColor(255, 255, 255);
    doc.setFont("helvetica", "bold");
    doc.text("LitterLens Detection Report", pageWidth / 2, 20, { align: "center" });

    doc.setFont("helvetica", "normal");
    doc.setFontSize(12);
    doc.setTextColor(0, 0, 0);

    let y = 30;
    doc.text(`Date: ${new Date().toLocaleString()}`, 20, y);
    y += 8;
    doc.text(`Location: Pasig River (Kalawaan Bridge)`, 20, y);
    y += 8;
    doc.text(`Average Confidence: ${result.accuracy || "N/A"}%`, 20, y);
    y += 15;

    // === Classification Summary ===
    doc.setFont("helvetica", "bold");
    doc.text("Classification Summary", 20, y);
    y += 8;

    const startX = 20;
    const colWidths = [100, 30];
    doc.setDrawColor(0);
    doc.setFillColor(220, 220, 220);
    doc.rect(startX, y, colWidths[0], 10, "F");
    doc.rect(startX + colWidths[0], y, colWidths[1], 10, "F");
    doc.setTextColor(0);
    doc.text("Litter Type", startX + 2, y + 7);
    doc.text("Count", startX + colWidths[0] + 2, y + 7);
    y += 10;

    doc.setFont("helvetica", "normal");
    for (const [label, count] of Object.entries(result.summary || {})) {
      doc.rect(startX, y, colWidths[0], 10);
      doc.rect(startX + colWidths[0], y, colWidths[1], 10);
      doc.text(label, startX + 2, y + 7);
      doc.text(String(count), startX + colWidths[0] + 2, y + 7);
      y += 10;
    }

    // === Image Section ===
    try {
      const toDataURL = url =>
        fetch(url)
          .then(r => r.blob())
          .then(blob => new Promise((res, rej) => {
            const reader = new FileReader();
            reader.onload = () => res(reader.result);
            reader.onerror = e => rej(e);
            reader.readAsDataURL(blob);
          })); 

      const imageData = result.result_image
        ? await toDataURL(`http://localhost:5000/uploads/${result.result_image}`)
        : uploadedImages[currentIndex];

      const imageY = y + 10; 
      const imageHeight = 75;
      const imageWidth = 170;

      doc.setFont("helvetica", "bold");
      doc.setFontSize(12);
      doc.text("Analyzed Image:", 20, imageY - 5);
      doc.addImage(imageData, "JPEG", 20, imageY, imageWidth, imageHeight);
    } catch (err) {
      console.error("Image load error:", err);
      alert("Unable to include the image in PDF.");
    }

    // === Footer ===
    const footerY = pageHeight - 25;
    doc.setFontSize(10);
    doc.setTextColor(100);
    doc.text("Pasig River Coordinating and Management Office - PRCMO", 20, footerY);
    doc.text("records.ncr@denr.gov.ph", 20, footerY + 5);
    doc.text("© LitterLens 2025. All rights reserved.", 20, footerY + 10);

    doc.save(`litterlens_report_${currentIndex + 1}.pdf`);
  });
});
