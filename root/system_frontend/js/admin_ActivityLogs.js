// ============================================================
// 📘 ADMIN_ACTIVITYLOGS.JS — Local Logs Filter + Pagination
// ============================================================

document.addEventListener("DOMContentLoaded", () => {
  const tbody = document.getElementById("logsBody");
  const allRows = Array.from(tbody.querySelectorAll("tr"));
  const searchInput = document.getElementById("logSearchInput");
  const dropdownBtn = document.getElementById("actionDropdown");
  const dropdownMenu = document.getElementById("dropdownMenu");
  const prevBtn = document.getElementById("prevPage");
  const nextBtn = document.getElementById("nextPage");
  const pageInfo = document.getElementById("pageInfo");
  const dateRangeInput = document.querySelector('input[name="daterange"]');

  // ===============================
  // ⚙️ Variables
  // ===============================
  let filteredRows = [...allRows];
  let selectedAction = "all";
  let currentPage = 1;
  const itemsPerPage = 5;
  let dateRange = null;

  // ===============================
  // 🗓️ DATE RANGE PICKER
  // ===============================
  if (typeof jQuery !== "undefined" && typeof moment !== "undefined") {
    $(dateRangeInput).daterangepicker(
      {
        opens: "left",
        autoUpdateInput: true,
        locale: { format: "MM/DD/YYYY" },
      },
      function (start, end) {
        dateRange = { start: start.toDate(), end: end.toDate() };
        applyFilters();
      }
    );
  }

  // ===============================
  // 🔽 DROPDOWN FILTER
  // ===============================
  dropdownBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    dropdownMenu.style.display =
      dropdownMenu.style.display === "block" ? "none" : "block";
  });

  dropdownMenu.querySelectorAll("li").forEach((item) => {
    item.addEventListener("click", () => {
      selectedAction = item.dataset.value || "all";
      dropdownBtn.innerHTML = `${item.textContent} <i class="fa-solid fa-chevron-down"></i>`;
      dropdownMenu.style.display = "none";
      applyFilters();
    });
  });

  document.addEventListener("click", (e) => {
    if (!dropdownBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
      dropdownMenu.style.display = "none";
    }
  });

  // ===============================
  // 🔍 SEARCH BAR FILTER
  // ===============================
  if (searchInput) {
    searchInput.addEventListener("input", () => {
      applyFilters();
    });
  }

  // ===============================
  // 🔢 PAGINATION
  // ===============================
  function renderTable() {
    const totalPages = Math.ceil(filteredRows.length / itemsPerPage) || 1;
    if (currentPage > totalPages) currentPage = totalPages;

    // Hide all rows
    allRows.forEach((r) => (r.style.display = "none"));

    // Show only rows for this page
    const start = (currentPage - 1) * itemsPerPage;
    const visible = filteredRows.slice(start, start + itemsPerPage);
    visible.forEach((r) => (r.style.display = ""));

    // Update pagination info
    pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
    prevBtn.style.opacity = currentPage > 1 ? "1" : "0.5";
    nextBtn.style.opacity = currentPage < totalPages ? "1" : "0.5";
    prevBtn.style.pointerEvents = currentPage > 1 ? "auto" : "none";
    nextBtn.style.pointerEvents = currentPage < totalPages ? "auto" : "none";
  }

  prevBtn.addEventListener("click", () => {
    if (currentPage > 1) {
      currentPage--;
      renderTable();
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  });

  nextBtn.addEventListener("click", () => {
    const totalPages = Math.ceil(filteredRows.length / itemsPerPage);
    if (currentPage < totalPages) {
      currentPage++;
      renderTable();
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  });

  // ===============================
  // 🧩 APPLY FILTERS
  // ===============================
  function applyFilters() {
    const term = searchInput.value.toLowerCase().trim();

    filteredRows = allRows.filter((row) => {
      const cells = Array.from(row.querySelectorAll("td")).map((td) =>
        td.textContent.toLowerCase()
      );

      const actionCell = (cells[2] || "").toLowerCase();
      const dateText = row.children[0]?.textContent.trim();
      const dateObj = new Date(dateText);

      const textMatch = cells.some((text) => text.includes(term));
      const actionMatch =
        selectedAction === "all" || actionCell === selectedAction;

      let dateMatch = true;
      if (dateRange && !isNaN(dateObj)) {
        dateMatch =
          dateObj >= dateRange.start && dateObj <= dateRange.end;
      }

      return textMatch && actionMatch && dateMatch;
    });

    currentPage = 1;
    renderTable();
  }

  // ===============================
  // 🚀 INITIAL RENDER
  // ===============================
  renderTable();
});
