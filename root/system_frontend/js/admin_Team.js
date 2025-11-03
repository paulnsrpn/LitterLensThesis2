document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.querySelector(".user-search");
  const cards = Array.from(document.querySelectorAll(".user-card"));
  const noResults = document.getElementById("no-results");
  const hideTimers = new WeakMap();
  let debounceTimer;

  // 🪄 Smooth FLIP Animation
  function flipAnimate() {
    const activeCards = cards.filter(c => c.style.display !== "none");
    const firstRects = new Map(activeCards.map(c => [c, c.getBoundingClientRect()]));

    // Wait for layout to settle, prevents blur
    setTimeout(() => {
      requestAnimationFrame(() => {
        activeCards.forEach(card => {
          const lastRect = card.getBoundingClientRect();
          const first = firstRects.get(card);
          if (!first) return;

          const dx = first.left - lastRect.left;
          const dy = first.top - lastRect.top;

          const anim = card.animate(
            [
              { transform: `translate(${dx}px, ${dy}px)` },
              { transform: "translate(0, 0)" }
            ],
            {
              duration: 600,
              easing: "cubic-bezier(0.25, 1, 0.5, 1)"
            }
          );

          anim.onfinish = () => {
            card.style.transform = "translate(0, 0)";
          };
        });
      });
    }, 50); // ⏳ Small delay avoids ghosting/blur
  }

  // 🧠 Search Input Logic
  searchInput.addEventListener("input", () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      const query = searchInput.value.trim().toLowerCase();
      let visibleCount = 0;

      // 🩶 If search is empty → show all cards cleanly
      if (query === "") {
        cards.forEach((card, i) => {
          const existingTimer = hideTimers.get(card);
          if (existingTimer) clearTimeout(existingTimer);

          card.style.display = "block";
          card.classList.remove("hide");
          card.classList.add("show");
          card.style.animationDelay = `${i * 0.05}s`;
        });

        noResults.style.opacity = "0";
        setTimeout(() => (noResults.style.display = "none"), 300);
        flipAnimate();
        return;
      }

      // 🎯 Otherwise → Filter normally
      cards.forEach((card) => {
        const name = card.querySelector("h3")?.textContent.toLowerCase() || "";
        const email = card.querySelector("p")?.textContent.toLowerCase() || "";
        const role = card.querySelector(".role")?.textContent.toLowerCase() || "";
        const matches = name.includes(query) || email.includes(query) || role.includes(query);

        const existingTimer = hideTimers.get(card);
        if (existingTimer) clearTimeout(existingTimer);

        if (matches) {
          visibleCount++;
          card.style.display = "block";
          card.classList.remove("hide");
          card.classList.add("show");
        } else {
          card.classList.remove("show");
          card.classList.add("hide");

          const timer = setTimeout(() => {
            card.style.display = "none";
            hideTimers.delete(card);
          }, 500);
          hideTimers.set(card, timer);
        }
      });

      // 🌀 Smoothly animate to new layout
      flipAnimate();

      // 🧾 Handle No Results
      if (visibleCount === 0) {
        noResults.style.display = "block";
        setTimeout(() => (noResults.style.opacity = "1"), 50);
      } else {
        noResults.style.opacity = "0";
        setTimeout(() => (noResults.style.display = "none"), 400);
      }
    }, 150);
  });
});
