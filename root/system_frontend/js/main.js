// ================================================
// 🧭 MAIN SITE SCRIPT
// ================================================

function scrollToAbsoluteTop() {
  window.scrollTo(0, 0);
}

function clearHashOnLoad() {
  if (window.location.hash) {
    history.replaceState(
      null,
      document.title,
      window.location.pathname + window.location.search
    );
  }
}

function markHomeNavActive() {
  document.querySelectorAll(".nav a").forEach(link => {
    if (link.textContent.trim() === "Home") {
      link.classList.add("active");
    } else {
      link.classList.remove("active");
    }
  });
}

// =====================
// 🌍 GEOLOCATION FUNCTION
// =====================
function getUserLocation() {
  if ("geolocation" in navigator) {
    navigator.geolocation.getCurrentPosition(
      position => {
        const latitude = position.coords.latitude;
        const longitude = position.coords.longitude;
        console.log(`📍 Location: Lat ${latitude}, Lng ${longitude}`);

        // ✅ Optionally send coordinates to PHP backend
        fetch("http://localhost/LitterLensThesis2/root/system_backend/php/save_location.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `latitude=${latitude}&longitude=${longitude}`
        })
          .then(res => res.text())
          .then(data => console.log("✅ Location saved:", data))
          .catch(err => console.error("❌ Failed to save location:", err));

        // ✅ Optionally populate hidden fields if present
        const latField = document.getElementById("latField");
        const lonField = document.getElementById("lonField");
        if (latField && lonField) {
          latField.value = latitude;
          lonField.value = longitude;
        }
      },
      error => {
        console.error(`❌ Geolocation error: ${error.message}`);
      }
    );
  } else {
    console.warn("⚠️ Geolocation is not supported by this browser.");
  }
}

// ===================
// ⏳ LOADER & NAVIGATION HANDLING
// ===================
window.addEventListener("load", () => {
  clearHashOnLoad();
  scrollToAbsoluteTop();
  markHomeNavActive();

  const loader = document.getElementById("loader");
  const mainContent = document.getElementById("main-content") || document.body;

  if (!sessionStorage.getItem("visited")) {
    sessionStorage.setItem("visited", "true");

    if (loader) {
      setTimeout(() => {
        loader.classList.add("hide");

        setTimeout(() => {
          loader.style.display = "none";
          mainContent.style.display = "block";
        }, 1000);
      }, 3000);
    }
  } else {
    if (loader) loader.style.display = "none";
    mainContent.style.display = "block";
  }

  getUserLocation();
});

// ==============================
// 📘 GUIDE STEP SCROLL HIGHLIGHT
// ==============================
document.addEventListener("DOMContentLoaded", () => {
  const card = document.querySelector(".stepsCard");
  if (!card) return;

  const sections = card.querySelectorAll(".step");
  const navLinks = document.querySelectorAll(".navParts a");

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

  navLinks.forEach(link => {
    link.addEventListener("click", e => {
      e.preventDefault();
      const targetId = link.getAttribute("href").substring(1);
      const targetSection = document.getElementById(targetId);

      if (targetSection) {
        const scrollTo =
          targetSection.offsetTop - card.clientHeight / 2 + targetSection.offsetHeight / 2;
        card.scrollTo({ top: scrollTo, behavior: "smooth" });
      }
    });
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const hamburger = document.getElementById("hamburger");
  const mobileMenu = document.getElementById("mobile-menu");
  const overlay = document.getElementById("mobile-overlay");
  const closeBtn = document.getElementById("menu-close");

  if (hamburger && mobileMenu && overlay && closeBtn) {
    // 🍔 Open menu
    hamburger.addEventListener("click", () => {
      hamburger.classList.toggle("active");
      mobileMenu.classList.toggle("active");
      overlay.classList.toggle("active");
    });

    closeBtn.addEventListener("click", () => {
      hamburger.classList.remove("active");
      mobileMenu.classList.remove("active");
      overlay.classList.remove("active");
    });

    overlay.addEventListener("click", () => {
      hamburger.classList.remove("active");
      mobileMenu.classList.remove("active");
      overlay.classList.remove("active");
    });

    mobileMenu.querySelectorAll("a").forEach(link => {
      link.addEventListener("click", () => {
        hamburger.classList.remove("active");
        mobileMenu.classList.remove("active");
        overlay.classList.remove("active");
      });
    });
  }
});

window.addEventListener("resize", () => {
  const hamburger = document.getElementById("hamburger");
  const mobileMenu = document.getElementById("mobile-menu");
  const overlay = document.getElementById("mobile-overlay");

  // If window width is greater than 992px (desktop view)
  if (window.innerWidth > 992) {
    if (hamburger && mobileMenu && overlay) {
      hamburger.classList.remove("active");
      mobileMenu.classList.remove("active");
      overlay.classList.remove("active");
    }
  }
});

document.addEventListener("DOMContentLoaded", () => {
  const policyToast = document.getElementById("policy-toast");
  const policyTitle = document.getElementById("policy-title");
  const policyMsg = document.getElementById("policy-message");
  const policyOk = document.getElementById("policy-ok-btn");

  const termsLink = document.querySelector('a.link[href="#"]:nth-of-type(1)');
  const privacyLink = document.querySelector('a.link[href="#"]:nth-of-type(2)');

  const messages = {
    terms: {
      title: "Terms of Service",
      text: `
        <p>Welcome to <strong>LitterLens</strong>. By accessing or using our platform, you agree to comply with these Terms of Service.</p>

        <p><strong>1. Use of Service</strong><br>
        LitterLens is designed to promote environmental awareness and support litter detection and analysis using image-based systems.
        Users agree to use the system responsibly and only for lawful, educational, or research purposes.</p>

        <p><strong>2. Accuracy of Information</strong><br>
        While we strive for precision in detection results and system analytics, the data generated by LitterLens may contain minor inaccuracies
        due to technical limitations, environmental factors, or dataset variations.</p>

        <p><strong>3. Intellectual Property</strong><br>
        All software, logos, documentation, and other system materials remain the intellectual property of the LitterLens development team.
        Unauthorized duplication, modification, or redistribution is strictly prohibited.</p>

        <p><strong>4. Data and Privacy Compliance</strong><br>
        The collection, storage, and processing of data within this system adhere to the principles of the 
        <strong>Republic Act No. 10173 – Data Privacy Act of 2012</strong>. 
        Personal and image data collected are handled solely for environmental and analytical purposes.</p>

        <p><strong>5. Liability Disclaimer</strong><br>
        LitterLens is provided "as is" without warranties of any kind. The developers shall not be held liable for 
        any damages arising from system misuse or technical issues.</p>

        <p><strong>6. Amendments</strong><br>
        The LitterLens Team reserves the right to modify or update these terms at any time. Continued use of the platform
        after any modification constitutes acceptance of the revised terms.</p>

        <p style="margin-top:10px;"><em>By continuing to use this system, you acknowledge that you have read, understood, and agreed to these Terms of Service.</em></p>
      `,
    },

    privacy: {
      title: "Privacy Policy",
      text: `
        <p>At <strong>LitterLens</strong>, we are committed to safeguarding your personal data and ensuring transparency
        in how information is collected, processed, and used.</p>

        <p><strong>1. Data We Collect</strong><br>
        The system may collect limited user information such as name, email, location coordinates, and uploaded images 
        to support detection analysis and improve model performance.</p>

        <p><strong>2. Purpose of Data Collection</strong><br>
        Collected data are used exclusively for research, analytics, and environmental monitoring. 
        We do not sell, rent, or disclose personal data to third parties without consent.</p>

        <p><strong>3. Legal Compliance</strong><br>
        LitterLens fully complies with <strong>Republic Act No. 10173 – The Data Privacy Act of 2012</strong>,
        which ensures that all personal and sensitive information is protected and processed lawfully.</p>

        <p><strong>4. Data Retention</strong><br>
        Information is retained only for as long as necessary to fulfill the purposes stated and is deleted or anonymized
        after processing or upon user request.</p>

        <p><strong>5. User Rights</strong><br>
        Users have the right to access, correct, and request deletion of their data in accordance with RA 10173.
        To exercise these rights, users may contact our data protection officer at <strong>cenro@pasigcity.gov.ph</strong>.</p>

        <p><strong>6. Security Measures</strong><br>
        We employ encryption, secure storage, and controlled access protocols to protect user data against unauthorized access or misuse.</p>

        <p style="margin-top:10px;"><em>
        This Privacy Policy ensures that your information remains confidential and protected under the principles of transparency, accountability, and security.
        </em></p>
      `,
    },
  };

  function openPolicy(type) {
    const data = messages[type];
    policyTitle.textContent = data.title;
    policyMsg.innerHTML = data.text;
    policyToast.classList.add("active");
  }

  policyOk.addEventListener("click", () => {
    policyToast.classList.remove("active");
  });

  termsLink.addEventListener("click", (e) => {
    e.preventDefault();
    openPolicy("terms");
  });

  privacyLink.addEventListener("click", (e) => {
    e.preventDefault();
    openPolicy("privacy");
  });
});

AOS.init({
  duration: 800,
  offset: 120,
  easing: "ease-in-out",
  once: false,
  mirror: true
});

