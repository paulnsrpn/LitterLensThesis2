/* =========================================================
   SETTINGS SECTION — PROFILE MANAGEMENT (Camera + Crop + AJAX)
========================================================= */

document.addEventListener("DOMContentLoaded", () => {
  console.log("⚙️ Settings initialized");
  console.log("🧩 Admin Info → ID:", ADMIN_ID, "Role:", ADMIN_ROLE);

  // =========================================================
  // ELEMENT REFERENCES (Scoped to Profile Only)
  // =========================================================
  const uploadInput = document.getElementById("upload");
  const cropModal = document.getElementById("cropModal");
  const cropImage = document.getElementById("cropImage");
  const cancelCropBtn = document.getElementById("cancelCrop");
  const confirmCropBtn = document.getElementById("confirmCrop");
  const profilePic = document.querySelector(".profile-pic");

  // ✅ Scope these only inside the Profile Card
  const profileSection = document.querySelector(".settings-card1");
  const saveBtn = profileSection.querySelector(".settings-save-btn");
  const editBtn = profileSection.querySelector(".edit-profile-btn");
  const cancelBtn = profileSection.querySelector(".cancel-profile-btn");
  const allInputs = profileSection.querySelectorAll("input");

  let cropper = null;
  let pendingCroppedImage = null;

  // =========================================================
  // FORCE CROP MODAL TO STAY CLOSED ON LOAD
  // =========================================================
  if (cropModal) {
    cropModal.style.display = "none";
    cropModal.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "auto";
  }

  // =========================================================
  // INITIAL STATE — DISABLE EVERYTHING ON LOAD
  // =========================================================
  function disableAll() {
    allInputs.forEach((input) => {
      input.disabled = true;
      if (input.id !== "upload") input.style.opacity = "0.6";
      input.style.cursor = "not-allowed";
    });

    saveBtn.disabled = true;
    cancelBtn.disabled = true;
    editBtn.disabled = false;

    saveBtn.style.opacity = cancelBtn.style.opacity = "0.6";
    editBtn.style.opacity = "1";
    saveBtn.style.cursor = cancelBtn.style.cursor = "not-allowed";
    editBtn.style.cursor = "pointer";

    // 🧱 Force Role Lock Always
    const roleInput = document.getElementById("role");
    if (roleInput) {
      const isSuperAdmin = (ADMIN_ID === 1 || ADMIN_ROLE === "admin");
      if (!isSuperAdmin) {
        roleInput.disabled = true;
        roleInput.style.opacity = "0.6";
        roleInput.style.cursor = "not-allowed";
      }
    }
  }

  disableAll();

  // =========================================================
  // ENABLE EDITING MODE (lock Role for non-admins)
  // =========================================================
  editBtn.addEventListener("click", () => {
    const isSuperAdmin = (ADMIN_ID === 1 || ADMIN_ROLE === "admin");

    allInputs.forEach((input) => {
      // Keep Role locked for non-admins
      if (input.id === "role" && !isSuperAdmin) {
        input.disabled = true;
        input.style.opacity = "0.6";
        input.style.cursor = "not-allowed";
        return;
      }

      // Enable other fields
      input.disabled = false;
      input.style.opacity = "1";
      input.style.cursor = "text";
    });

    saveBtn.disabled = false;
    cancelBtn.disabled = false;
    saveBtn.style.opacity = cancelBtn.style.opacity = "1";
    saveBtn.style.cursor = cancelBtn.style.cursor = "pointer";

    editBtn.disabled = true;
    editBtn.style.opacity = "0.5";
    editBtn.style.cursor = "not-allowed";
  });

  // =========================================================
  // CANCEL EDITING
  // =========================================================
  cancelBtn.addEventListener("click", () => {
    if (confirm("Discard unsaved changes?")) {
      disableAll();
      pendingCroppedImage = null;
    }
  });

  // =========================================================
  // IMAGE CROPPING MODAL — SAFE OPEN
  // =========================================================
  uploadInput?.addEventListener("change", (e) => {
    const file = e.target.files[0];
    if (!file || file.size === 0) {
      console.log("⚠️ No file selected or input reset");
      return;
    }

    e.target.blur();

    const reader = new FileReader();
    reader.onload = (ev) => {
      cropImage.src = ev.target.result;

      cropModal.style.display = "flex";
      cropModal.style.opacity = "0";
      setTimeout(() => {
        cropModal.style.transition = "opacity 0.25s ease-in";
        cropModal.style.opacity = "1";
      }, 10);

      document.body.style.overflow = "hidden";

      if (cropper) cropper.destroy();
      cropper = new Cropper(cropImage, {
        aspectRatio: 1,
        viewMode: 1,
        dragMode: "move",
        background: false,
        autoCropArea: 1,
      });
    };
    reader.readAsDataURL(file);
  });

  // =========================================================
  // CLOSE MODAL WHEN CLICKING OUTSIDE
  // =========================================================
  cropModal.addEventListener("click", (e) => {
    if (e.target === cropModal) {
      cropModal.style.display = "none";
      document.body.style.overflow = "auto";
      uploadInput.value = "";
    }
  });

  // =========================================================
  // CANCEL CROPPING
  // =========================================================
  cancelCropBtn?.addEventListener("click", () => {
    if (cropper) cropper.destroy();
    cropModal.style.display = "none";
    document.body.style.overflow = "auto";
    uploadInput.value = "";
  });

  // =========================================================
  // CONFIRM CROPPING
  // =========================================================
  confirmCropBtn?.addEventListener("click", () => {
    if (!cropper) return;

    const canvas = cropper.getCroppedCanvas({ width: 300, height: 300 });
    if (!canvas) {
      alert("⚠️ Failed to crop image.");
      return;
    }

    pendingCroppedImage = canvas.toDataURL("image/png");
    profilePic.src = pendingCroppedImage;

    cropper.destroy();
    cropModal.style.display = "none";
    document.body.style.overflow = "auto";
    uploadInput.value = "";
  });

  // =========================================================
  // SAVE CHANGES (TEXT + PHOTO)
  // =========================================================
  const adminId = ADMIN_ID;

  saveBtn.addEventListener("click", () => {
    const payload = {
      admin_id: adminId,
      name: document.getElementById("name").value.trim(),
      email: document.getElementById("email").value.trim(),
      contact_number: document.getElementById("contact_number").value.trim(),
      role: document.getElementById("role").value.trim(),
      password: document.getElementById("password").value.trim(),
      confirm: document.getElementById("conpassword").value.trim(),
      profile_pic: pendingCroppedImage || null,
    };

    if (payload.password && payload.password !== payload.confirm) {
      alert("⚠️ Passwords do not match!");
      return;
    }

    saveBtn.disabled = true;
    saveBtn.textContent = "Saving...";
    saveBtn.style.cursor = "not-allowed";
    saveBtn.style.opacity = "0.6";

    fetch("/LitterLensThesis2/root/system_backend/php/system_admin_data.php?ajax=update_profile_secure", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then((r) => r.text())
      .then((text) => {
        console.log("📦 Server Response:", text);
        let data;
        try {
          data = JSON.parse(text);
        } catch (err) {
          throw new Error("Invalid JSON response: " + text);
        }

        if (data.success) {
          alert("✅ Profile updated successfully!");
          disableAll();
          pendingCroppedImage = null;

          if (data.new_profile_pic_url) {
            profilePic.src = data.new_profile_pic_url + "?t=" + new Date().getTime();
          }

          saveBtn.textContent = "Save Changes";
          saveBtn.disabled = true;
          saveBtn.style.opacity = "0.6";
          saveBtn.style.cursor = "not-allowed";

          document.body.style.transition = "opacity 0.5s ease";
          document.body.style.opacity = "0";
          setTimeout(() => window.location.reload(), 500);
        } else {
          alert("❌ Failed: " + (data.error || "Unknown error"));
          saveBtn.textContent = "Save Changes";
          saveBtn.disabled = false;
          saveBtn.style.opacity = "1";
          saveBtn.style.cursor = "pointer";
        }
      })
      .catch((err) => {
        console.error("❌ Save error:", err);
        alert("⚠️ Server error occurred while saving.");
        saveBtn.textContent = "Save Changes";
        saveBtn.disabled = false;
        saveBtn.style.opacity = "1";
        saveBtn.style.cursor = "pointer";
      });
  });
});



/* =========================================================
   🧩 MODEL MANAGEMENT — ADMIN 1 ONLY (Final On-Load Lock)
========================================================= */
document.addEventListener("DOMContentLoaded", () => {
  console.log("⚙️ Model Management Loaded");

  // =========================================================
  // 🔒 ROLE-BASED ACCESS CONTROL
  // =========================================================
  const ADMIN_ID = window.currentAdminId || 0;
  const ADMIN_ROLE = (window.currentAdminRole || "").toLowerCase();
  const isSuperAdmin = ADMIN_ID === 1 || ADMIN_ROLE === "admin";

  // =========================================================
  // 🔧 ELEMENTS
  // =========================================================
  const modelTableBody = document.querySelector("#modelTable tbody");
  const uploadModelBtn = document.querySelector("#uploadModelBtn");
  const modelFileInput = document.querySelector("#modelFile");
  const activeModelInput = document.querySelector("#activeModel");
  const editModelBtn = document.querySelector("#editModelBtn");
  const cancelModelBtn = document.querySelector("#cancelModelBtn");
  const saveModelBtn = document.querySelector("#saveModelBtn");

  let inEditMode = false;

  // =========================================================
  // 🧩 ON-LOAD INSTANT LOCK (before fetching anything)
  // =========================================================
  lockAllControls();

  // =========================================================
  // 📥 FETCH MODELS
  // =========================================================
  modelTableBody.innerHTML = `
    <tr>
      <td colspan="5" style="text-align:center; padding:20px;">
        <div class="loading-spinner"></div>
        <p>Loading models...</p>
      </td>
    </tr>
  `;

  fetch("/LitterLensThesis2/root/system_backend/php/fetch_models.php")
    .then((res) => res.json())
    .then((data) => {
      if (!data.success) throw new Error(data.error);
      const models = data.data;

      if (!models.length) {
        modelTableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No models found.</td></tr>`;
        disableAll();
        return;
      }

      models.sort((a, b) => (a.status === "Active" ? -1 : 1));

      // Show active model name
      const activeModel = models.find((m) => m.status === "Active");
      if (activeModel)
        activeModelInput.value = `${activeModel.model_name} (${activeModel.version})`;

      // Build table rows
      modelTableBody.innerHTML = models
        .map(
          (m) => `
        <tr>
          <td>${m.model_name || "—"}</td>
          <td>${m.version || "—"}</td>
          <td>${new Date(m.uploaded_on).toLocaleString()}</td>
          <td class="status ${m.status === "Active" ? "active" : "inactive"}">${m.status}</td>
          <td>
            <button class="activate-btn" 
              data-id="${m.model_id}" 
              ${!isSuperAdmin || m.status === "Active" ? "disabled" : ""}>
              Activate
            </button>
            <button class="delete-btn" 
              data-id="${m.model_id}" 
              ${!isSuperAdmin || m.model_id == 1 ? "disabled" : ""}>
              🗑 Delete
            </button>
          </td>
        </tr>`
        )
        .join("");
      bindModelActions();
      disableAll();
    })
    .catch((err) => {
      console.error("❌ Fetch error:", err);
      modelTableBody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:red;">Error loading models.</td></tr>`;
      disableAll();
    });

  // =========================================================
  // 🧩 LOCK / UNLOCK FUNCTIONS
  // =========================================================
  function lockAllControls() {
    const elements = [uploadModelBtn, modelFileInput, cancelModelBtn, saveModelBtn, activeModelInput];
    elements.forEach((el) => {
      if (el) {
        el.disabled = true;
        el.style.opacity = "0.6";
        el.style.pointerEvents = "none";
      }
    });

    document.querySelectorAll(".activate-btn, .delete-btn").forEach((btn) => {
      btn.disabled = true;
      btn.style.opacity = "0.6";
      btn.style.pointerEvents = "none";
    });

    // Keep Edit active
    editModelBtn.disabled = false;
    editModelBtn.style.opacity = "1";
    editModelBtn.style.pointerEvents = "auto";
  }

  function disableAll() {
    inEditMode = false;
    lockAllControls();
  }

  function enableAll() {
    if (!isSuperAdmin) {
      alert("⚠️ Only Admin 1 can edit models.");
      return;
    }

    inEditMode = true;

    [uploadModelBtn, modelFileInput, cancelModelBtn, saveModelBtn, activeModelInput].forEach((el) => {
      if (el) {
        el.disabled = false;
        el.style.opacity = "1";
        el.style.pointerEvents = "auto";
      }
    });

    editModelBtn.disabled = true;
    editModelBtn.style.opacity = "0.6";
    editModelBtn.style.pointerEvents = "none";

    document.querySelectorAll(".activate-btn, .delete-btn").forEach((btn) => {
      const id = btn.dataset.id;
      btn.disabled = false;
      btn.style.opacity = "1";
      btn.style.pointerEvents = "auto";

      // Protect model 1 from deletion
      if (btn.classList.contains("delete-btn") && id == "1") {
        btn.disabled = true;
        btn.style.opacity = "0.6";
        btn.style.pointerEvents = "none";
      }
    });
  }

  editModelBtn.addEventListener("click", enableAll);

  cancelModelBtn.addEventListener("click", () => {
    if (!inEditMode) return;
    const confirmCancel = confirm("Discard unsaved changes and exit edit mode?");
    if (confirmCancel) {
      disableAll();
      console.log("🔒 Edit mode exited.");
    } else {
      console.log("↩️ Cancel aborted — staying in edit mode");
    }
  });

  // =========================================================
  // 🧩 ACTION BUTTONS
  // =========================================================
  function bindModelActions() {
    document.querySelectorAll(".activate-btn").forEach((btn) =>
      btn.addEventListener("click", async () => {
        if (!inEditMode || !isSuperAdmin) return alert("⚠️ Enable Edit Mode first.");
        const id = btn.dataset.id;
        if (!confirm("Activate this model?")) return;

        const formData = new FormData();
        formData.append("action", "activate");
        formData.append("id", id);

        const res = await fetch("/LitterLensThesis2/root/system_backend/php/manage_model.php", {
          method: "POST",
          body: formData,
          credentials: "include",
        });
        const result = await res.json();
        alert(result.success ? "✅ Model activated!" : "❌ " + result.error);
        if (result.success) location.reload();
      })
    );

    document.querySelectorAll(".delete-btn").forEach((btn) =>
      btn.addEventListener("click", async () => {
        if (!inEditMode || !isSuperAdmin) return alert("⚠️ Enable Edit Mode first.");
        const id = btn.dataset.id;
        if (id == 1) return alert("⚠️ Default model cannot be deleted.");
        if (!confirm("🗑 Delete this model from Supabase?")) return;

        const formData = new FormData();
        formData.append("action", "delete");
        formData.append("id", id);

        const res = await fetch("/LitterLensThesis2/root/system_backend/php/manage_model.php", {
          method: "POST",
          body: formData,
          credentials: "include",
        });
        const result = await res.json();
        if (result.success) {
          alert("✅ Model deleted.");
          btn.closest("tr").remove();
        } else alert("❌ " + result.error);
      })
    );
  }

  // =========================================================
  // 📤 UPLOAD MODEL
  // =========================================================
  uploadModelBtn.addEventListener("click", async () => {
    if (!inEditMode || !isSuperAdmin) return alert("⚠️ Only Admin 1 can upload models.");
    const file = modelFileInput.files[0];
    if (!file) return alert("Select a .pt file first.");

    uploadModelBtn.disabled = true;
    uploadModelBtn.innerHTML = "⏳ Uploading...";

    const formData = new FormData();
    formData.append("action", "upload");
    formData.append("modelFile", file);
    formData.append("accuracy", "0");

    try {
      const res = await fetch("/LitterLensThesis2/root/system_backend/php/manage_model.php", {
        method: "POST",
        body: formData,
        credentials: "include",
      });
      const data = await res.json();

      if (data.success) {
        alert("✅ Model uploaded successfully!");
        location.reload();
      } else {
        alert("❌ " + data.error);
      }
    } catch (err) {
      console.error("Upload failed:", err);
      alert("⚠️ Upload failed.");
    } finally { 
      uploadModelBtn.disabled = false;
      uploadModelBtn.innerHTML = "Upload & Activate";
    }
  });

  // =========================================================
  // 🧱 NON-ADMIN USERS
  // =========================================================
  if (!isSuperAdmin) {
    disableAll();
    document.querySelector(".settings-card2").classList.add("locked-section");
    console.warn("🔒 Non-admin detected: Model Management is read-only.");
  }
});
