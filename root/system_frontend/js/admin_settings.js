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
   🧩 MODEL MANAGEMENT — ADMIN 1 ONLY
========================================================= */
document.addEventListener("DOMContentLoaded", () => {
  console.log("⚙️ Model Management Loaded for Admin:", ADMIN_ID);

  // 🔗 DOM Elements
  const modelSection = document.querySelector(".settings-card2");
  const activeModelInput = modelSection.querySelector("#activeModel");
  const modelFileInput = modelSection.querySelector("#modelFile");
  const uploadModelBtn = modelSection.querySelector("#uploadModelBtn");
  const editModelBtn = modelSection.querySelector("#editModelBtn");
  const saveModelBtn = modelSection.querySelector("#saveModelBtn");
  const cancelModelBtn = modelSection.querySelector("#cancelModelBtn");

  if (!activeModelInput || !modelFileInput || !editModelBtn) {
    console.warn("⚠️ Missing Model Management elements.");
    return;
  }

  // =========================================================
  // Helper Functions
  // =========================================================
  function disableModelControls() {
    [activeModelInput, modelFileInput, uploadModelBtn, saveModelBtn, cancelModelBtn].forEach((el) => {
      el.disabled = true;
      el.style.opacity = "0.6";
      el.style.cursor = "not-allowed";
    });
  }

  function enableModelControls() {
    [activeModelInput, modelFileInput, uploadModelBtn, saveModelBtn, cancelModelBtn].forEach((el) => {
      el.disabled = false;
      el.style.opacity = "1";
      el.style.cursor = "pointer";
    });
  }

  // =========================================================
  // 🔒 Restrict Access — Only Admin 1
  // =========================================================
  if (parseInt(ADMIN_ID) !== 1) {
    console.log("🚫 Model management disabled — not Admin 1");

    disableModelControls();
    editModelBtn.disabled = true;
    editModelBtn.style.opacity = "0.6";
    editModelBtn.style.cursor = "not-allowed";
    editModelBtn.title = "Only Admin 1 can edit models.";

    editModelBtn.addEventListener("mouseenter", () => {
      const tip = document.createElement("div");
      tip.textContent = "Restricted: Only Admin 1 can manage models.";
      tip.className = "no-access-tooltip";
      tip.style.position = "absolute";
      tip.style.top = "-35px";
      tip.style.right = "0";
      tip.style.background = "#333";
      tip.style.color = "#fff";
      tip.style.padding = "6px 10px";
      tip.style.borderRadius = "6px";
      tip.style.fontSize = "13px";
      tip.style.whiteSpace = "nowrap";
      editModelBtn.appendChild(tip);
      setTimeout(() => tip.remove(), 2000);
    });

    return;
  }

  // =========================================================
  // 👑 Admin 1 Functional Logic
  // =========================================================
  disableModelControls();

  editModelBtn.addEventListener("click", () => {
    enableModelControls();
    editModelBtn.disabled = true;
    editModelBtn.style.opacity = "0.5";
    editModelBtn.style.cursor = "not-allowed";
  });

  cancelModelBtn.addEventListener("click", () => {
    disableModelControls();
    editModelBtn.disabled = false;
    editModelBtn.style.opacity = "1";
    editModelBtn.style.cursor = "pointer";
  });

  saveModelBtn.addEventListener("click", () => {
    alert("✅ Model details saved successfully!");
    disableModelControls();
    editModelBtn.disabled = false;
    editModelBtn.style.opacity = "1";
    editModelBtn.style.cursor = "pointer";
  });

  uploadModelBtn.addEventListener("click", () => {
    const file = modelFileInput.files[0];
    if (!file) {
      alert("⚠️ Please select a model (.pt) file to upload.");
      return;
    }
    alert(`📤 Uploading ${file.name} to Supabase...`);
  });
});


/* =========================================================
   🧩 LOAD REAL MODEL DATA FROM SUPABASE (with Loading State)
========================================================= */
document.addEventListener("DOMContentLoaded", () => {
  const modelTableBody = document.querySelector("#modelTable tbody");

  // 🌀 Initial loading indicator
  modelTableBody.innerHTML = `
    <tr>
      <td colspan="5" style="text-align:center; padding:20px;">
        <div class="loading-spinner"></div>
        <p style="margin-top:10px; color:#444;">Loading models...</p>
      </td>
    </tr>
  `;

  // Fetch model records
  fetch("/LitterLensThesis2/root/system_backend/php/fetch_models.php")
    .then((res) => {
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return res.json();
    })
    .then((data) => {
      if (!data.success) {
        console.error("❌ Failed to load models:", data.error);
        modelTableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">⚠️ Error loading models.</td></tr>`;
        return;
      }

      const models = data.data;
      if (!models.length) {
        modelTableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No model records found.</td></tr>`;
        return;
      }

      // ✅ Build table rows dynamically
      modelTableBody.innerHTML = models
        .map(
          (m) => `
          <tr>
            <td>${m.model_name || "—"}</td>
            <td>${m.version || "—"}</td>
            <td>${m.accuracy !== null ? m.accuracy + "%" : "N/A"}</td>
            <td>${new Date(m.uploaded_on).toLocaleDateString("en-US", {
              year: "numeric",
              month: "long",
              day: "numeric",
            })}</td>
            <td class="status ${m.status === "Active" ? "active" : "inactive"}">
              ${m.status}
            </td>
          </tr>`
        )
        .join("");
    })
    .catch((err) => {
      console.error("⚠️ Error fetching models:", err);
      modelTableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">❌ Failed to fetch model data.</td></tr>`;
    });

uploadModelBtn.addEventListener("click", async () => {
  const file = modelFileInput.files[0];
  if (!file) {
    alert("⚠️ Please select a .pt file first.");
    return;
  }

  // 🌀 Start loading state
  uploadModelBtn.disabled = true;
  uploadModelBtn.innerHTML = `
    <div class="spinner" style="
      display:inline-block;
      width:16px; 
      height:16px; 
      border:2px solid #fff; 
      border-top:2px solid transparent;
      border-radius:50%;
      margin-right:8px;
      animation: spin 0.8s linear infinite;">
    </div> Uploading...
  `;

  try {
    const formData = new FormData();
    formData.append("modelFile", file);
    formData.append("model_name", file.name.replace(".pt", ""));
    formData.append("accuracy", "0"); // default if none provided

    const response = await fetch("/LitterLensThesis2/root/system_backend/php/upload_model.php", {
      method: "POST",
      body: formData,
    });

    const data = await response.json();
    console.log("📦 Upload Response:", data);

    if (!data.success) throw new Error(data.error || "Upload failed.");

    // ✅ Success UI feedback
    uploadModelBtn.innerHTML = "✅ Uploaded Successfully";
    uploadModelBtn.style.backgroundColor = "#2d6a4f";

    // 🔄 Reload table after 1.5s
    setTimeout(() => location.reload(), 1500);

  } catch (err) {
    console.error("❌ Upload Error:", err);
    alert("❌ Upload failed. Check console for details.");

    // ❌ Error feedback
    uploadModelBtn.innerHTML = "❌ Upload Failed";
    uploadModelBtn.style.backgroundColor = "#b81c1c";

    setTimeout(() => {
      uploadModelBtn.innerHTML = "Upload Model";
      uploadModelBtn.style.backgroundColor = "#215a36";
      uploadModelBtn.disabled = false;
    }, 2000);

  }
});
});

/* =========================================================
   END OF FILE
========================================================= */    




