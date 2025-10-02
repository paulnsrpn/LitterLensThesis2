document.addEventListener("DOMContentLoaded", () => {
    const menuItems = document.querySelectorAll(".sidebar nav ul li");
    const sections = document.querySelectorAll(".content-section");

    menuItems.forEach(item => {
        item.addEventListener("click", () => {
            // remove active from all
            menuItems.forEach(i => i.classList.remove("active"));
            sections.forEach(sec => sec.classList.remove("active"));

            // set active
            item.classList.add("active");
            document.getElementById(item.dataset.section).classList.add("active");
        });
    });
});
