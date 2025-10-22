// ====================
// HELPER FUNCTIONS
// ====================

function scrollToAbsoluteTop() {
    // Scroll the page to the absolute top (0, 0) instantly.
    window.scrollTo(0, 0);
}

function clearHashOnLoad() {
    // Prevents the browser from scrolling to an element ID specified by a URL hash.
    if (window.location.hash) {
        // Use replaceState to clear the hash fragment without causing a scroll or reload.
        history.replaceState(
            null,
            document.title,
            window.location.pathname + window.location.search
        );
    }
}

function markHomeNavActive() {
    document.querySelectorAll(".nav a").forEach(link => {
        // Assuming your 'Home' link goes to 'main.php' or '#' which resolves to the current page.
        // We'll focus on the first link or the one with the correct text/structure if it's the 'Home' link.
        if (link.textContent.trim() === 'Home') {
            link.classList.add("active");
        } else {
            link.classList.remove("active");
        }
    });
}


// ===================
// LOADER & NAV HANDLING
// ====================

window.addEventListener("load", () => {
    // Run this first to remove any hash that would cause scrolling
    clearHashOnLoad();
    scrollToAbsoluteTop();
    
    // Explicitly scroll to the absolute top of the page (0, 0)
    scrollToAbsoluteTop(); 
    markHomeNavActive();

    const loader = document.getElementById("loader");
    const mainContent = document.getElementById("main-content") || document.body;
 
    // Only show loader if it's the first load (no sessionStorage flag)
    if (!sessionStorage.getItem("visited")) {
        sessionStorage.setItem("visited", "true");

        // The loader must exist in the HTML for this to work. (It's not in the provided HTML, but kept here)
        if (loader) {
            setTimeout(() => {
                loader.classList.add("hide");

                setTimeout(() => {
                    loader.style.display = "none";
                    mainContent.style.display = "block";
                }, 1000); // fade-out time
            }, 3000); // loader visible time
        }
        
    } else {
        // If already visited or loader doesn't exist, skip loader setup
        if (loader) {
            loader.style.display = "none";
        }
        mainContent.style.display = "block";
    }

    // NOTE: The previous scrollToHome() and ensureHomeHash() calls are removed/replaced.
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