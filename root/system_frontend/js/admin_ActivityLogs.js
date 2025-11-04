// ============================================================
// 📘 ADMIN_ACTIVITYLOGS.JS — Local Logs Filter + Pagination + Export (FINAL CLEAN VERSION)
// ============================================================

document.addEventListener("DOMContentLoaded", () => {
  // ===============================
  // 🔗 ELEMENT REFERENCES
  // ===============================
  const tbody = document.getElementById("logsBody");
  const allRows = Array.from(tbody.querySelectorAll("tr"));
  const searchInput = document.getElementById("logSearchInput");
  const dropdownBtn = document.getElementById("actionDropdown");
  const dropdownMenu = document.getElementById("dropdownMenu");
  const prevBtn = document.getElementById("prevPage");
  const nextBtn = document.getElementById("nextPage");
  const pageInfo = document.getElementById("pageInfo");
  const dateRangeInput = document.querySelector('input[name="daterange"]');
  const exportBtn = document.getElementById("exportExcelBtn");

  // ===============================
  // ⚙️ VARIABLES
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
      (start, end) => {
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
      selectedAction = (item.dataset.value || "all").toLowerCase().trim();
      dropdownBtn.innerHTML = `${item.textContent} <i class="fa-solid fa-chevron-down"></i>`;
      dropdownMenu.style.display = "none";
      applyFilters();
    });
  });

  // Close dropdown when clicking outside
  document.addEventListener("click", (e) => {
    if (!dropdownBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
      dropdownMenu.style.display = "none";
    }
  });

  // ===============================
  // 🔍 SEARCH BAR FILTER
  // ===============================
  if (searchInput) {
    searchInput.addEventListener("input", () => applyFilters());
  }

  // ===============================
  // 🔢 PAGINATION
  // ===============================
  function renderTable() {
    const totalPages = Math.ceil(filteredRows.length / itemsPerPage) || 1;
    if (currentPage > totalPages) currentPage = totalPages;

    // Hide all rows
    allRows.forEach((r) => (r.style.display = "none"));

    // Show only rows for current page
    const start = (currentPage - 1) * itemsPerPage;
    const visibleRows = filteredRows.slice(start, start + itemsPerPage);
    visibleRows.forEach((r) => (r.style.display = ""));

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
  // 🧩 APPLY FILTERS — FINAL
  // ===============================
  function applyFilters() {
    const term = searchInput.value.toLowerCase().trim();

    filteredRows = allRows.filter((row) => {
      const cells = Array.from(row.querySelectorAll("td")).map((td) =>
        td.textContent.toLowerCase().trim()
      );

      const actionCell = cells[2] || ""; // “Added”, “Updated”, etc.
      const dateText = row.children[0]?.textContent.trim();
      const dateObj = new Date(dateText);

      // 🔍 Search text match
      const textMatch = cells.some((text) => text.includes(term));

      // 🔽 Action match
      let actionMatch = true;
      if (selectedAction !== "all") {
        const selected = selectedAction.toLowerCase().trim();
        const normalizedAction = actionCell
          .toLowerCase()
          .replace(/ed$/, "")
          .replace(/\s+/g, "");

        actionMatch =
          actionCell.includes(selected) ||
          normalizedAction.includes(selected.replace(/ed$/, ""));
      }

      // 🗓️ Date range match
      let dateMatch = true;
      if (dateRange && !isNaN(dateObj)) {
        dateMatch = dateObj >= dateRange.start && dateObj <= dateRange.end;
      }

      return textMatch && actionMatch && dateMatch;
    });

    currentPage = 1;
    renderTable();

    // ⚠️ Handle empty state
    const existingNoData = tbody.querySelector(".no-logs-row");
    if (filteredRows.length === 0) {
      if (!existingNoData) {
        const noDataRow = document.createElement("tr");
        noDataRow.className = "no-logs-row";
        noDataRow.innerHTML = `
          <td colspan="6" style="text-align:center;color:#999;">
            No matching logs found
          </td>`;
        tbody.appendChild(noDataRow);
      }
    } else if (existingNoData) {
      existingNoData.remove();
    }
  }

  // ===============================
  // 📤 EXPORT FILTERED LOGS TO CSV
  // ===============================
  if (exportBtn) {
    exportBtn.addEventListener("click", async () => {
      if (!filteredRows || filteredRows.length === 0) {
        alert("⚠️ No logs to export. Try adjusting your filters first.");
        return;
      }

      const originalHTML = exportBtn.innerHTML;
      exportBtn.disabled = true;
      exportBtn.innerHTML = `<span class="btn-spinner"></span> Exporting...`;

      try {
        // Collect filtered logs
        const logs = filteredRows.map((row) => {
          const cells = Array.from(row.querySelectorAll("td")).map((td) =>
            td.textContent.trim()
          );
          return {
            date: cells[0] || "",
            admin: cells[1] || "",
            action: cells[2] || "",
            affected_record: cells[3] || "",
            description: cells[4] || "",
            status: cells[5] || "",
          };
        });

        // Send logs to backend
        const response = await fetch(
          "/LitterLensThesis2/root/system_backend/php/system_admin_data.php?ajax=export_logs_csv",
          {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ logs }),
          }
        );

        if (!response.ok) throw new Error(`Server error: ${response.status}`);

        // Download the CSV
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `Activity_Logs_${new Date()
          .toISOString()
          .slice(0, 10)}.csv`;
        a.click();
        a.remove();

        // ✅ Success feedback
        exportBtn.innerHTML = "✅ Exported!";
        setTimeout(() => {
          exportBtn.innerHTML = originalHTML;
          exportBtn.disabled = false;
        }, 1000);

        window.URL.revokeObjectURL(url);
      } catch (error) {
        console.error(error);
        alert("❌ Export failed. Please try again.");
        exportBtn.innerHTML = originalHTML;
        exportBtn.disabled = false;
      }
    });
  }

  // ===============================
  // 🚀 INITIAL RENDER
  // ===============================
  renderTable();
});
 