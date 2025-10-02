// ====================
// LOADER & NAV HANDLING
// ====================

  window.addEventListener("load", () => {
    const loader = document.getElementById("loader");
    const mainContent = document.getElementById("main-content") || document.body;

    // Only show loader if it's the first load (no sessionStorage flag)
    if (!sessionStorage.getItem("visited")) {
      sessionStorage.setItem("visited", "true");

      setTimeout(() => {
        loader.classList.add("hide");

        setTimeout(() => {
          loader.style.display = "none";
          mainContent.style.display = "block";

          scrollToHome();
          markHomeNavActive();
          ensureHomeHash();
        }, 1000); // fade-out time
      }, 3000); // loader visible time
    } else {
      // If already visited, skip loader
      loader.style.display = "none";
      mainContent.style.display = "block";

      scrollToHome();
      markHomeNavActive();
      ensureHomeHash();
    }

    function scrollToHome() {
      const homePage = document.getElementById("home-page");
      if (homePage) {
        homePage.scrollIntoView({ behavior: "smooth" });
      }
    }

    function markHomeNavActive() {
      document.querySelectorAll("nav a").forEach(link => {
        link.classList.remove("active");
        if (link.getAttribute("href") === "#home-page") {
          link.classList.add("active");
        }
      });
    }

    function ensureHomeHash() {
      if (!location.hash || location.hash === "#") {
        history.replaceState(null, null, "#home-page");
      }
    }
  });



// ==============================
// GUIDE STEP SCROLL HIGHLIGHT
// ==============================
document.addEventListener("DOMContentLoaded", () => {
  const card = document.querySelector(".stepsCard");
  const sections = card.querySelectorAll(".step");
  const navLinks = document.querySelectorAll(".navParts a");

  // Scroll sync with step indicators
  card.addEventListener("scroll", () => {
    const scrollTop = card.scrollTop;

    sections.forEach(section => {
      const top = section.offsetTop;
      const height = section.offsetHeight;

      if (scrollTop >= top - height / 3 && scrollTop < top + height - height / 3) {
        const id = section.getAttribute("id");

        navLinks.forEach(link => {
          link.classList.remove("active");
          if (link.getAttribute("href") === `#${id}`) {
            link.classList.add("active");
          }
        });
      }
    });
  });

  // Smooth scroll to section inside stepsCard
  navLinks.forEach(link => {
    link.addEventListener("click", e => {
      e.preventDefault();
      const targetId = link.getAttribute("href").substring(1);
      const targetSection = document.getElementById(targetId);

      if (targetSection) {
        const scrollTo = targetSection.offsetTop - (card.clientHeight / 2) + (targetSection.offsetHeight / 2);
        card.scrollTo({ top: scrollTo, behavior: "smooth" });
      }
    });
  });
});


// ==============================
// AOS (Animate On Scroll) INIT
// ==============================
AOS.init({
  duration: 800,
  offset: 120,
  easing: 'ease-in-out',
  once: false,
  mirror: true
});


// ==============================
// DRAG AND DROP UPLOAD HANDLING
// ==============================
const photoContainer = document.querySelector('.photo-container');
const uploadPage = document.getElementById('upload-page');
const fileInput = document.getElementById('file-upload');
const previewImage = document.getElementById('preview-image');
const previewBox = document.getElementById('preview-box');
const fileName = document.getElementById('file-name');

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

  // Ensure the file is also usable by input element
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
const analyzeBtn = document.querySelector(".upload-photo-btn");

analyzeBtn.addEventListener("click", async () => {
  const file = fileInput.files[0];

  if (!file) {
    alert("Please select an image first.");
    return;
  }

  // Update button state
  analyzeBtn.disabled = true;
  const originalText = analyzeBtn.textContent;
  analyzeBtn.textContent = "Analyzing...";

  const formData = new FormData();
  formData.append("image", file);

  try {
    const response = await fetch("http://localhost:5000/analyze", {
      method: "POST",
      body: formData
    });

    const result = await response.json();

    if (response.ok) {
      localStorage.setItem("analyze_result", JSON.stringify(result));
      window.location.href = "result.html";
    } else {
      alert("Error: " + (result.error || "Unexpected error"));
    }
  } catch (err) {
    console.error("Error analyzing image:", err);
    alert("Failed to analyze the image.");
  } finally {
    analyzeBtn.disabled = false;
    analyzeBtn.textContent = originalText;
  }
});



// CONTACTS 
document.getElementById("contactForm").addEventListener("submit", async function (e) {
  e.preventDefault();

  const formData = new FormData(this);

  const data = Object.fromEntries(formData.entries());

  try {
    const response = await fetch("http://localhost:5000/send-email", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify(data)
    });

    const res = await response.json();

    if (response.ok) {
      alert("Your message was sent successfully!");
    } else {
      alert("Error: " + res.error);
    }
  } catch (error) {
    alert("Something went wrong. Try again later.");
    console.error(error);
  }
});