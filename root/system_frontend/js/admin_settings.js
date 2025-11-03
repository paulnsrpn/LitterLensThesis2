/* =========================================================
   SETTINGS SECTION — PROFILE MANAGEMENT (Camera + Crop + AJAX)
========================================================= */

document.addEventListener("DOMContentLoaded", () => {
  console.log("⚙️ Settings initialized");

  // =========================================================
  // ELEMENT REFERENCES
  // =========================================================
  const uploadInput = document.getElementById("upload");
  const cropModal = document.getElementById("cropModal");
  const cropImage = document.getElementById("cropImage");
  const cancelCropBtn = document.getElementById("cancelCrop");
  const confirmCropBtn = document.getElementById("confirmCrop");
  const profilePic = document.querySelector(".profile-pic");

  const saveBtn = document.querySelector(".settings-save-btn");
  const editBtn = document.querySelector(".edit-profile-btn");
  const cancelBtn = document.querySelector(".cancel-profile-btn");
  const allInputs = document.querySelectorAll("#settings input");

  let cropper = null;
  let pendingCroppedImage = null; // temporarily stores cropped photo before saving

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
  }

  // 🔹 Hide modal completely on page load
  if (cropModal) {
    cropModal.style.display = "none";
    cropModal.setAttribute("aria-hidden", "true");
  }

  disableAll(); // initialize state

  // =========================================================
  // ENABLE EDITING MODE
  // =========================================================
  editBtn.addEventListener("click", () => {
    allInputs.forEach((input) => {
      input.disabled = false;
      input.style.opacity = "1";
      input.style.cursor = "text";
    });

    saveBtn.disabled = false;
    cancelBtn.disabled = false;
    saveBtn.style.opacity = "1";
    cancelBtn.style.opacity = "1";
    saveBtn.style.cursor = "pointer";
    cancelBtn.style.cursor = "pointer";

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
  // IMAGE CROPPING MODAL — OPEN
  // =========================================================
  uploadInput?.addEventListener("change", (e) => {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (ev) => {
      cropImage.src = ev.target.result;

      cropModal.style.display = "flex";
      cropModal.setAttribute("aria-hidden", "false");
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
  // CANCEL CROPPING
  // =========================================================
  cancelCropBtn?.addEventListener("click", () => {
    if (cropper) cropper.destroy();
    cropModal.style.display = "none";
    cropModal.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "auto";
    uploadInput.value = "";
  });

  // =========================================================
  // CONFIRM CROPPING — SAVE TEMPORARILY UNTIL "SAVE CHANGES"
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
    cropModal.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "auto";
  });

  // =========================================================
// SAVE CHANGES (TEXT + PHOTO) — with Fade Refresh
// =========================================================
const adminId = ADMIN_ID; // ✅ Provided via PHP in HTML

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

  // ⚠️ Validate passwords
  if (payload.password && payload.password !== payload.confirm) {
    alert("⚠️ Passwords do not match!");
    return;
  }

  // 🔒 Disable button and show saving state
  saveBtn.disabled = true;
  saveBtn.textContent = "Saving...";
  saveBtn.style.cursor = "not-allowed";
  saveBtn.style.opacity = "0.6";

  fetch("/LitterLensThesis2/root/system_backend/php/system_admin_data.php?ajax=update_profile_all", {
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

        // ✅ Update UI immediately
        disableAll();
        pendingCroppedImage = null;

        const newName = document.getElementById("name").value.trim();
        const profileName = document.querySelector(".profile-name p");
        if (profileName) profileName.textContent = newName;

        const contactDisplay = document.querySelector(".profile-contact p");
        if (contactDisplay) contactDisplay.textContent = payload.contact_number;

        if (data.new_profile_pic_url) {
          profilePic.src = data.new_profile_pic_url + "?t=" + new Date().getTime();
        }

        // Restore Save button
        saveBtn.textContent = "Save Changes";
        saveBtn.style.cursor = "not-allowed";
        saveBtn.style.opacity = "0.6";
        saveBtn.disabled = true;

        // ✨ Smooth fade-out before refresh
        document.body.style.transition = "opacity 0.5s ease";
        document.body.style.opacity = "0";

        setTimeout(() => {
          window.location.reload();
        }, 500);

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


}); // DOMContentLoaded
