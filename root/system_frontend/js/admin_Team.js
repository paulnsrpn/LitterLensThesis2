document.addEventListener("DOMContentLoaded", () => {
  const teamContainer = document.querySelector("#users .user-grid");
  const searchInput = document.querySelector(".user-search");
  const noResults = document.getElementById("no-results");

  const editModal = document.getElementById("editMemberModal");
  const deleteModal = document.getElementById("deleteConfirmModal");
  const editName = document.getElementById("editName");
  const editEmail = document.getElementById("editEmail");
  const editRole = document.getElementById("editRole");
  const deleteName = document.getElementById("deleteName");

  let currentEditId = null;
  let currentDeleteId = null;
  let debounceTimer;

  // ==============================
  // 🧠 Initialize All Functions
  // ==============================
  function initTeamFunctions() {
    const cards = Array.from(document.querySelectorAll(".user-card"));

    // 🔍 SEARCH FUNCTION
    searchInput?.addEventListener("input", () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        const query = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;

        cards.forEach(card => {
          const name = card.querySelector("h3")?.textContent.toLowerCase() || "";
          const email = card.querySelector("p")?.textContent.toLowerCase() || "";
          const role = card.querySelector(".role")?.textContent.toLowerCase() || "";
          const matches = name.includes(query) || email.includes(query) || role.includes(query);

          if (!query || matches) {
            card.style.display = "block";
            card.classList.remove("hide");
            visibleCount++;
          } else {
            card.classList.add("hide");
            setTimeout(() => (card.style.display = "none"), 300);
          }
        });

        // Show/hide "No results"
        if (visibleCount === 0) {
          noResults.style.display = "block";
          noResults.style.opacity = "1";
        } else {
          noResults.style.opacity = "0";
          setTimeout(() => (noResults.style.display = "none"), 300);
        }
      }, 150);
    });

    // ⚙️ MENU TOGGLE
    document.querySelectorAll(".menu-btn").forEach(menu => {
      if (menu.disabled) return;
      menu.addEventListener("click", e => {
        e.stopPropagation();
        const options = menu.closest(".user-menu").querySelector(".menu-options");
        if (!options) return;

        // Close other open menus
        document.querySelectorAll(".menu-options.active").forEach(m => {
          if (m !== options) m.classList.remove("active");
        });

        options.classList.toggle("active");
      });
    });

    // Close menus when clicking elsewhere
    document.addEventListener("click", () => {
      document.querySelectorAll(".menu-options.active").forEach(m => m.classList.remove("active"));
    });

    // ✏️ EDIT MEMBER
    document.querySelectorAll(".btn-edit").forEach(btn => {
      btn.addEventListener("click", () => {
        currentEditId = btn.dataset.id;
        editName.value = btn.dataset.name;
        editEmail.value = btn.dataset.email;
        editRole.value = btn.dataset.role;
        editModal.style.display = "flex";
      });
    });

    // 🗑️ DELETE MEMBER
    document.querySelectorAll(".btn-delete").forEach(btn => {
      btn.addEventListener("click", () => {
        currentDeleteId = btn.dataset.id;
        deleteName.textContent = btn.dataset.name;
        deleteModal.style.display = "flex";
      });
    });

    // 📧 EMAIL MEMBER
    document.querySelectorAll(".email-btn").forEach(btn => {
      btn.addEventListener("click", () => {
        const email = btn.dataset.email;
        const name = btn.dataset.name;
        if (!email) return alert("⚠️ No email found.");
        const subject = encodeURIComponent("LitterLens Admin");
        const body = encodeURIComponent(`Hello ${name},\n\nI’d like to discuss...`);
        window.open(`https://mail.google.com/mail/?view=cm&fs=1&to=${email}&su=${subject}&body=${body}`, "_blank");
      });
    });

    // 📞 CALL MEMBER
    document.querySelectorAll(".call-btn").forEach(btn => {
      btn.addEventListener("click", () => {
        const phone = btn.dataset.phone?.trim();
        const isMobile = /Android|iPhone|iPad|iPod|Windows Phone/i.test(navigator.userAgent);
        if (!phone) return showTooltip(btn, "⚠️ No phone number available.");
        if (!isMobile) return showTooltip(btn, `📞 Use manually: ${phone}`);
        window.location.href = `tel:${phone}`;
      });
    });
  }

  // ==============================
  // 💬 TOOLTIP HELPERS
  // ==============================
  function showTooltip(button, message) {
    const existing = button.parentElement.querySelector(".call-tooltip");
    if (existing) existing.remove();

    const tooltip = document.createElement("div");
    tooltip.className = "call-tooltip";
    tooltip.textContent = message;
    button.parentElement.appendChild(tooltip);

    requestAnimationFrame(() => tooltip.classList.add("visible"));
    const hideTimer = setTimeout(() => removeTooltip(tooltip), 3000);

    const hideOnLeave = () => {
      removeTooltip(tooltip);
      clearTimeout(hideTimer);
      button.removeEventListener("mouseleave", hideOnLeave);
    };

    button.addEventListener("mouseleave", hideOnLeave);
  }

  function removeTooltip(tooltip) {
    tooltip.classList.remove("visible");
    setTimeout(() => tooltip.remove(), 250);
  }

  // ==============================
  // 🧩 MODALS
  // ==============================
  document.getElementById("cancelEditBtn").onclick = () => (editModal.style.display = "none");
  document.getElementById("cancelDeleteBtn").onclick = () => (deleteModal.style.display = "none");

  // ==============================
  // 💾 SAVE EDIT
  // ==============================
  document.getElementById("saveEditBtn").addEventListener("click", async () => {
    const btn = document.getElementById("saveEditBtn");
    const originalText = btn.innerHTML;

    btn.innerHTML = `<span class="loading-spinner"></span> Saving...`;
    btn.disabled = true;

    const payload = {
      admin_id: currentEditId,
      name: editName.value.trim(),
      email: editEmail.value.trim(),
      role: editRole.value.trim(),
    };

    try {
      const res = await fetch("/LitterLensThesis2/root/system_backend/php/system_admin_data.php?ajax=edit_member", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify(payload),
      });

      const data = await res.json();

      if (data.success) {
        btn.innerHTML = "✅ Updated!";
        editModal.classList.add("modal-fade-out");
        setTimeout(() => {
          editModal.style.display = "none";
          editModal.classList.remove("modal-fade-out");
          btn.innerHTML = originalText;
          btn.disabled = false;
        }, 400);
        await reloadTeamData();
      } else {
        alert("Failed to update: " + (data.error || "Unknown"));
        btn.innerHTML = originalText;
        btn.disabled = false;
      }
    } catch (err) {
      console.error(err);
      alert("Server error.");
      btn.innerHTML = originalText;
      btn.disabled = false;
    }
  });

  // ==============================
  // 🗑️ DELETE MEMBER
  // ==============================
  document.getElementById("confirmDeleteBtn").addEventListener("click", async () => {
    const btn = document.getElementById("confirmDeleteBtn");
    const originalText = btn.innerHTML;

    btn.innerHTML = `<span class="loading-spinner red"></span> Deleting...`;
    btn.disabled = true;

    try {
      const res = await fetch("/LitterLensThesis2/root/system_backend/php/system_admin_data.php?ajax=delete_member", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({ admin_id: currentDeleteId }),
      });

      const data = await res.json();

      if (data.success) {
        btn.innerHTML = "🗑️ Deleted!";
        deleteModal.classList.add("modal-fade-out");
        setTimeout(() => {
          deleteModal.style.display = "none";
          deleteModal.classList.remove("modal-fade-out");
          btn.innerHTML = originalText;
          btn.disabled = false;
        }, 400);
        await reloadTeamData();
      } else {
        alert("Failed: " + (data.error || "Unknown"));
        btn.innerHTML = originalText;
        btn.disabled = false;
      }
    } catch (err) {
      console.error(err);
      alert("Server error.");
      btn.innerHTML = originalText;
      btn.disabled = false;
    }
  });

  // ==============================
  // 🔁 RELOAD TEAM LIST
  // ==============================
  async function reloadTeamData() {
    teamContainer.style.opacity = "0.5";
    teamContainer.innerHTML = `<p style="text-align:center;margin-top:20px;font-weight:600;">Updating...</p>`;

    try {
      const res = await fetch("/LitterLensThesis2/root/system_backend/php/system_admin_data.php?ajax=fetch_admins", {
        credentials: "include",
      });

      const html = await res.text();
      teamContainer.innerHTML = html;
      teamContainer.style.opacity = "1";
      initTeamFunctions(); // Rebind all interactions
    } catch (err) {
      console.error(err);
      teamContainer.innerHTML = `<p style="text-align:center;color:red;">⚠️ Failed to reload team.</p>`;
    }
  }

  // 🏁 INITIALIZE
  initTeamFunctions();
});
