// ==============================
// DRAG AND DROP UPLOAD HANDLING
// ==============================
const photoContainer = document.querySelector('.photo-container');
const uploadPage = document.getElementById('upload-page');
const fileInput = document.getElementById('file-upload');
const previewImage = document.getElementById('preview-image');
const previewBox = document.getElementById('preview-box');
const fileName = document.getElementById('file-name');
const analyzeBtn = document.querySelector('.upload-photo-btn');

// Handle file preview logic
function handleFile(file) {
  if (!file || !file.type.startsWith('image/')) return;

  const reader = new FileReader();
  reader.onload = () => {
    const base64Data = reader.result;
    previewImage.src = base64Data;
    fileName.textContent = file.name;
    previewBox.style.display = 'flex';
    photoContainer.classList.add('image-loaded');
    uploadPage.dataset.imageBase64 = base64Data;
  };

  reader.readAsDataURL(file);

  // Keep the file in the file input
  const dataTransfer = new DataTransfer();
  dataTransfer.items.add(file);
  fileInput.files = dataTransfer.files;
}

// Drag highlight effects
['dragenter', 'dragover'].forEach(eventType => {
  uploadPage.addEventListener(eventType, e => {
    e.preventDefault();
    photoContainer.classList.add('dragging-highlight');
  });
});
['dragleave', 'drop'].forEach(eventType => {
  uploadPage.addEventListener(eventType, e => {
    e.preventDefault();
    photoContainer.classList.remove('dragging-highlight');
  });
});

// Handle drag-drop image
uploadPage.addEventListener('drop', e => {
  const files = e.dataTransfer.files;
  if (files.length > 0) {
    handleFile(files[0]);
  }
});

// Handle manual file select
fileInput.addEventListener('change', () => {
  if (fileInput.files.length > 0) {
    handleFile(fileInput.files[0]);
  }
});

// ==============================
// ANALYZE IMAGE BUTTON LOGIC
// ==============================
analyzeBtn.addEventListener("click", async (e) => {
  e.preventDefault();
  const file = fileInput.files[0];

  if (!file) {
    alert("Please select an image first.");
    return;
  }

  // Button feedback
  analyzeBtn.disabled = true;
  const originalText = analyzeBtn.textContent;
  analyzeBtn.textContent = "Analyzing...";

  const formData = new FormData();
  formData.append("image", file);

  try {
      // ✅ Send to PHP backend (which calls Supabase Edge Function)
    const response = await fetch('../system_backend/php/analyze_image.php', {
    method: 'POST',
    body: formData
  });


    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }

    const resultText = await response.text();

    // ✅ Display Supabase response (you can later redirect)
    document.open();
    document.write(resultText);
    document.close();

  } catch (err) {
    console.error("Error analyzing image:", err);
    alert("Failed to analyze the image.");
  } finally {
    analyzeBtn.disabled = false;
    analyzeBtn.textContent = originalText;
  }
});


// ==============================
// OPTIONAL: AUTO-RUN ANALYSIS WHEN FILE SELECTED
// ==============================
fileInput.addEventListener("change", () => {
  if (fileInput.files.length > 0) {
    handleFile(fileInput.files[0]);
    setTimeout(() => analyzeBtn.click(), 1000); // auto-run after 1s
  }
});
