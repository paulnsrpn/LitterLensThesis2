import os
import sys
import shutil
from datetime import datetime
from ultralytics import YOLO

# === PATH CONFIGURATION ===
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
RUNS_DIR = os.path.join(SCRIPT_DIR, "runs")  # base folder for outputs
MODEL_PATH = os.path.join(SCRIPT_DIR, "my_model.pt")
CONF_THRESHOLD = 0.25

# ✅ Load model
print("📦 Loading YOLO model...")
model = YOLO(MODEL_PATH)

# ✅ Check if image path is provided
if len(sys.argv) < 2:
    print("❌ Usage: python app_test_result.py <image_path>")
    sys.exit(1)

image_path = sys.argv[1]
if not os.path.exists(image_path):
    print(f"❌ Image not found: {image_path}")
    sys.exit(1)

# 🕒 Create a timestamp for folder and filenames
timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")

# 📁 Create run folder
run_folder = os.path.join(RUNS_DIR, f"save_{timestamp}")
os.makedirs(run_folder, exist_ok=True)

# 📄 Define final file paths
orig_filename = f"{timestamp}_orig.jpg"
result_filename = f"{timestamp}_result.jpg"
orig_save_path = os.path.join(run_folder, orig_filename)
result_save_path = os.path.join(run_folder, result_filename)

# 🧾 Copy original image to run folder (for record)
shutil.copy(image_path, orig_save_path)
print(f"📸 Original saved as: {orig_save_path}")

# 🧠 Run YOLO detection and save annotated image to a temp folder
results = model(image_path, conf=CONF_THRESHOLD, save=True, project=SCRIPT_DIR, name="yolo_output")

# YOLO saves to yolo_output/<original_filename>
filename = os.path.basename(image_path)
yolo_output_path = os.path.join(SCRIPT_DIR, "yolo_output", filename)

# 🖼️ Move annotated result to run folder
if os.path.exists(yolo_output_path):
    shutil.move(yolo_output_path, result_save_path)
    print(f"✅ Result saved as: {result_save_path}")
else:
    print(f"❌ YOLO annotated file not found at {yolo_output_path}")
    sys.exit(1)

# 📊 Detection summary
object_summary = {}
for det in results[0].boxes:
    cls_id = int(det.cls.item())
    label = model.names[cls_id]
    object_summary[label] = object_summary.get(label, 0) + 1

total_items = sum(object_summary.values())
print(f"🧾 Detection Summary: {object_summary}")
print(f"🧮 Total Items Detected: {total_items}")

# 🧹 Clean up YOLO temp folder
try:
    shutil.rmtree(os.path.join(SCRIPT_DIR, "yolo_output"))
except Exception as e:
    print(f"⚠️ Could not delete temp folder: {e}")

print(f"✅ All files saved in: {run_folder}")
