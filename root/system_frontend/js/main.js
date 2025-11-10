// ================================================
// 🧭 MAIN SITE SCRIPT — Navigation, Loader & Location
// ================================================

// ====================
// 🧩 HELPER FUNCTIONS
// ====================

/** Scroll smoothly to the absolute top of the page */
function scrollToAbsoluteTop() {
  window.scrollTo(0, 0);
}

/** Remove any hash fragment (#upload, #about, etc.) from URL on load */
function clearHashOnLoad() {
  if (window.location.hash) {
    history.replaceState(
      null,
      document.title,
      window.location.pathname + window.location.search
    );
  }
}

/** Highlight the 'Home' link as active in the navigation bar */
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

  // 🧭 First-time visit check
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

  // 🛰️ Automatically fetch user location
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

  // Highlight active guide step while scrolling
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




  // Smooth scroll to specific step when clicked
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

    // ❌ Close when pressing the X
    closeBtn.addEventListener("click", () => {
      hamburger.classList.remove("active");
      mobileMenu.classList.remove("active");
      overlay.classList.remove("active");
    });

    // 🌑 Close when clicking the overlay
    overlay.addEventListener("click", () => {
      hamburger.classList.remove("active");
      mobileMenu.classList.remove("active");
      overlay.classList.remove("active");
    });

    // 📱 Auto-close when clicking a link
    mobileMenu.querySelectorAll("a").forEach(link => {
      link.addEventListener("click", () => {
        hamburger.classList.remove("active");
        mobileMenu.classList.remove("active");
        overlay.classList.remove("active");
      });
    });
  }
});

// ================================================
// 🧠 AUTO-CLOSE MOBILE MENU ON WINDOW RESIZE
// ================================================
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

// ==============================
// 🎞️ AOS (Animate On Scroll) INIT
// ==============================
AOS.init({
  duration: 800,
  offset: 120,
  easing: "ease-in-out",
  once: false,
  mirror: true
});

