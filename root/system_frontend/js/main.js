// ====================
// HELPER FUNCTIONS
// ====================

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
        if (link.textContent.trim() === 'Home') {
            link.classList.add("active");
        } else {
            link.classList.remove("active");
        }
    });
}

// =====================
// GEOLOCATION FUNCTION
// =====================
function getUserLocation() {
    if ("geolocation" in navigator) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const latitude = position.coords.latitude;
                const longitude = position.coords.longitude;
                console.log(`📍 Location: Lat ${latitude}, Lng ${longitude}`);

                // ✅ Optional: send to backend (PHP)
                fetch("save_location.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `latitude=${latitude}&longitude=${longitude}`
                })
                .then(res => res.text())
                .then(data => console.log("✅ Location saved:", data))
                .catch(err => console.error("❌ Failed to save location:", err));

                // ✅ Optional: if you want to store in hidden fields
                const latField = document.getElementById("latField");
                const lonField = document.getElementById("lonField");
                if (latField && lonField) {
                    latField.value = latitude;
                    lonField.value = longitude;
                }
            },
            (error) => {
                console.error(`❌ Geolocation error: ${error.message}`);
            }
        );
    } else {
        console.warn("⚠️ Geolocation is not supported by this browser.");
    }
}

// ===================
// LOADER & NAV HANDLING
// ====================
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

    // 🛰️ Auto get location on first load
    getUserLocation();
});

// ==============================
// GUIDE STEP SCROLL HIGHLIGHT
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
