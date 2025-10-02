const sections = document.querySelectorAll(".step");
  const navLinks = document.querySelectorAll(".navParts a");

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        // Remove active class from all
        navLinks.forEach(link => link.classList.remove("active"));
        // Add to the one matching the section
        const activeLink = document.querySelector(`.navParts a[href="#${entry.target.id}"]`);
        if (activeLink) {
          activeLink.classList.add("active");
        }
      }
    });
  }, { threshold: 0.5 }); // 50% of section must be visible

  sections.forEach(section => observer.observe(section));
