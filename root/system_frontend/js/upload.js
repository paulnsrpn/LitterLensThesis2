// ================================================
// 📤 UPLOAD & ANALYZE SCRIPT — Dropzone + Flask Integration
// ================================================

Dropzone.autoDiscover = false;

// ================================================
// 🟢 Initialize Dropzone Configuration
// ================================================
const myDropzone = new Dropzone("#my-dropzone", {
  url: "http://127.0.0.1:5000/analyze",
  maxFiles: 10,
  maxFilesize: 5, // MB
  acceptedFiles: "image/*",
  addRemoveLinks: true,
  dictRemoveFile: "×",
  dictDefaultMessage: "",
  clickable: ".select-btn",
  autoProcessQueue: false,
  parallelUploads: 10,
  uploadMultiple: true,
  paramName: "image",
  method: "post",

  init: function () {
    this.on("error", function (file, errorMessage) {
      console.error("❌ Upload error:", errorMessage);
      showErrorModal("❌ Upload failed. Please check your file.");
    });
  }
});

// ================================================
// 🟡 Drag & Drop Visual Feedback
// ================================================
const dropzoneElement = document.getElementById("my-dropzone");

myDropzone.on("dragenter", () => dropzoneElement.classList.add("drag-over"));
myDropzone.on("dragleave", () => dropzoneElement.classList.remove("drag-over"));
myDropzone.on("drop", () => dropzoneElement.classList.remove("drag-over"));

// ================================================
// 🖱 ANALYZE BUTTON HANDLER
// ================================================
const analyzeBtn = document.getElementById("analyze-btn");
const originalBtnText = analyzeBtn.textContent;

analyzeBtn.addEventListener("click", () => {
  if (myDropzone.files.length === 0) {
    showErrorModal("⚠️ Please upload at least one image before analyzing.");
    return;
  }

  console.log(`📸 Sending ${myDropzone.files.length} images to Flask...`);

  analyzeBtn.disabled = true;
  analyzeBtn.innerHTML = `<div class="spinner"></div> Analyzing...`;

  myDropzone.processQueue();
});

// ================================================
// ✅ SUCCESS — When Flask returns results
// ================================================
myDropzone.on("successmultiple", (files, response) => {
  console.log("✅ Flask response:", response);
  localStorage.setItem("detectionResult", JSON.stringify(response));

  analyzeBtn.disabled = false;
  analyzeBtn.textContent = originalBtnText;

  window.location.href = "../php/index_result.php";
});

// ================================================
// ❌ ERROR — When Flask or Upload Fails
// ================================================
myDropzone.on("errormultiple", (files, errorMessage) => {
  console.error("❌ Error during detection:", errorMessage);
  showErrorModal("❌ Detection failed. Check Flask server.");

  // 🛑 Reset button state
  analyzeBtn.disabled = false;
  analyzeBtn.textContent = originalBtnText;
});

// ================================================
// 🔴 Error & Success Modal Utility
// ================================================
function showErrorModal(message) {
  createModal(message, "#d9534f");
}

// ================================================
// 💬 Modal Creator Function
// ================================================
function createModal(message, bgColor) {
  let modal = document.getElementById("feedback-modal");
  let msg = document.getElementById("feedback-message");

  if (!modal) {
    // 🪟 Create modal structure
    modal = document.createElement("div");
    modal.id = "feedback-modal";
    modal.style.position = "fixed";
    modal.style.top = 0;
    modal.style.left = 0;
    modal.style.width = "100%";
    modal.style.height = "100%";
    modal.style.background = "rgba(0,0,0,0.5)";
    modal.style.display = "flex";
    modal.style.alignItems = "center";
    modal.style.justifyContent = "center";
    modal.style.zIndex = "9999";

    const content = document.createElement("div");
    content.style.background = "#fff";
    content.style.padding = "20px 30px";
    content.style.borderRadius = "10px";
    content.style.textAlign = "center";
    content.style.minWidth = "300px";

    msg = document.createElement("p");
    msg.id = "feedback-message";
    msg.style.fontSize = "16px";
    msg.style.marginBottom = "15px";

    const closeBtn = document.createElement("button");
    closeBtn.textContent = "Close";
    closeBtn.style.background = bgColor;
    closeBtn.style.color = "#fff";
    closeBtn.style.border = "none";
    closeBtn.style.padding = "8px 20px";
    closeBtn.style.borderRadius = "5px";
    closeBtn.style.cursor = "pointer";
    closeBtn.addEventListener("click", () => {
      modal.style.display = "none";
    });

    content.appendChild(msg);
    content.appendChild(closeBtn);
    modal.appendChild(content);
    document.body.appendChild(modal);
  }

  msg.textContent = message;
  modal.querySelector("button").style.background = bgColor;
  modal.style.display = "flex";
}
