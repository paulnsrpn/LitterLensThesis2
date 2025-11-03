# =====================================================
# 🚀 LITTERLENS FLASK BACKEND — YOLOv8 Real-Time Detection (FINAL)
# =====================================================

from flask import Flask, request, jsonify, send_from_directory, make_response, Response
from flask_cors import CORS
from ultralytics import YOLO
from datetime import datetime
import os
import shutil
import cv2
import json
import io
import time
import threading
from collections import deque
import math
import numpy as np

# =====================================================
# ⚙️ FLASK CONFIGURATION
# =====================================================
app = Flask(__name__)
CORS(app)

# =====================================================
# 🧠 MODEL LOADING — MANUAL ENSEMBLE (3 MODELS) + RUNS_DIR PRESERVED
# =====================================================
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
RUNS_DIR = os.path.join(SCRIPT_DIR, "runs")
os.makedirs(RUNS_DIR, exist_ok=True)

MODEL_PATHS = [
    os.path.join(SCRIPT_DIR, "best.pt"),
    os.path.join(SCRIPT_DIR, "last.pt"),
    os.path.join(SCRIPT_DIR, "my_model.pt")
]

print("\n🔍 Checking model paths...")
for p in MODEL_PATHS:
    print(f"  - {p} {'✅ FOUND' if os.path.exists(p) else '❌ MISSING'}")

valid_models = [p for p in MODEL_PATHS if os.path.exists(p)]

if not valid_models:
    raise FileNotFoundError(f"❌ No YOLO models found in: {MODEL_PATHS}")

# Load all YOLO models individually
models = [YOLO(p) for p in valid_models]
print(f"✅ Loaded {len(models)} YOLO model(s) successfully.\n")

# Keep a reference "model" to the first model so code using model.names still works
model = models[0]

# =====================================================
# 🔮 ENSEMBLE INFERENCE FUNCTION (with optional basic NMS merge)
# =====================================================
def ensemble_predict(image, conf=0.10, iou_thresh=0.5):
    """
    Run detection on all models and combine results.
    Returns an object with .boxes.xyxy (N x 4), .boxes.conf (N,), .boxes.cls (N,)
    """
    all_boxes, all_confs, all_classes = [], [], []

    last_results = None
    for m in models:
        try:
            results = m(image, conf=conf, verbose=False)[0]
            last_results = results
            # Convert to numpy safely
            try:
                boxes_np = results.boxes.xyxy.cpu().numpy()
                confs_np = results.boxes.conf.cpu().numpy()
                cls_np = results.boxes.cls.cpu().numpy()
            except Exception:
                # Fallback: cast directly (some versions)
                boxes_np = np.array(results.boxes.xyxy)
                confs_np = np.array(results.boxes.conf)
                cls_np = np.array(results.boxes.cls)
            if boxes_np.size:
                all_boxes.append(boxes_np)
                all_confs.append(confs_np)
                all_classes.append(cls_np)
        except Exception as e:
            print(f"⚠️ Model inference error: {e}")

    # Merge all results
    if all_boxes:
        all_boxes = np.concatenate(all_boxes, axis=0)
        all_confs = np.concatenate(all_confs, axis=0)
        all_classes = np.concatenate(all_classes, axis=0)
    else:
        all_boxes = np.empty((0, 4))
        all_confs = np.empty((0,))
        all_classes = np.empty((0,))

    # Perform simple class-agnostic NMS to remove duplicates (optional, recommended)
    # We'll use a basic NMS implementation
    def simple_nms(boxes, scores, iou_threshold=0.5):
        if boxes.shape[0] == 0:
            return np.array([], dtype=int)
        x1 = boxes[:, 0]
        y1 = boxes[:, 1]
        x2 = boxes[:, 2]
        y2 = boxes[:, 3]
        areas = (x2 - x1) * (y2 - y1)
        order = scores.argsort()[::-1]
        keep = []
        while order.size > 0:
            i = order[0]
            keep.append(i)
            xx1 = np.maximum(x1[i], x1[order[1:]])
            yy1 = np.maximum(y1[i], y1[order[1:]])
            xx2 = np.minimum(x2[i], x2[order[1:]])
            yy2 = np.minimum(y2[i], y2[order[1:]])
            w = np.maximum(0.0, xx2 - xx1)
            h = np.maximum(0.0, yy2 - yy1)
            inter = w * h
            union = areas[i] + areas[order[1:]] - inter
            iou = np.zeros_like(inter)
            valid = union > 0
            iou[valid] = inter[valid] / union[valid]
            inds = np.where(iou <= iou_threshold)[0]
            order = order[inds + 1]
        return np.array(keep, dtype=int)

    # You may want class-aware NMS; here we'll do class-aware by running NMS per class
    kept_indices = []
    if all_boxes.shape[0] > 0:
        unique_classes = np.unique(all_classes)
        for cls in unique_classes:
            cls_mask = (all_classes == cls)
            cls_boxes = all_boxes[cls_mask]
            cls_scores = all_confs[cls_mask]
            # map back to global indices
            global_indices = np.where(cls_mask)[0]
            keep_local = simple_nms(cls_boxes, cls_scores, iou_threshold=iou_thresh)
            kept_indices.extend(global_indices[keep_local].tolist())

        # sort kept indices by score desc
        if kept_indices:
            kept_indices = np.array(kept_indices)
            order = all_confs[kept_indices].argsort()[::-1]
            kept_indices = kept_indices[order]
        else:
            kept_indices = np.array([], dtype=int)

        final_boxes = all_boxes[kept_indices]
        final_confs = all_confs[kept_indices]
        final_classes = all_classes[kept_indices]
    else:
        final_boxes = np.empty((0, 4))
        final_confs = np.empty((0,))
        final_classes = np.empty((0,))

    # Create a minimal result object resembling Ultralytics' structure enough for downstream funcs
    class BoxesObj:
        def __init__(self, xyxy, conf, cls):
            self.xyxy = xyxy
            self.conf = conf
            self.cls = cls

    class CombinedResult:
        def __init__(self, boxes_obj):
            self.boxes = boxes_obj

    boxes_obj = BoxesObj(final_boxes, final_confs, final_classes)
    return CombinedResult(boxes_obj)


# =====================================================
# 🎨 CLASS COLOR MAP (BGR for OpenCV)
# =====================================================
CLASS_COLORS = {
    "Biological Debris": (255, 0, 0),
    "Electronic Waste": (0, 165, 255),
    "Fabric and Textiles": (255, 255, 0),
    "Foamed Plastic": (255, 0, 255),
    "Glass and Ceramic": (255, 0, 0),
    "Metal": (0, 255, 0),
    "Organic Debris": (255, 0, 0),
    "Paper and Cardboard": (0, 255, 255),
    "Plastic": (128, 0, 128),
    "Rubber": (0, 0, 255),
    "Sanitary Waste": (204, 204, 255)
}

# =====================================================
# 📈 LIVE DETECTION VARIABLES
# =====================================================
detection_threshold = 0.10
recent_objects = deque(maxlen=200)
object_lifetime = 2.0
object_id_counter = 0
last_summary = {"total": 0, "accuracy": 0.0, "speed": 0.0, "classes": {}}
running_total_detections = 0
running_accuracy_sum = 0.0
frame_count = 0
class_totals = {}
camera = None
running = False
lock = threading.Lock()

# =====================================================
# 🎥 CAMERA AVAILABILITY CHECK
# =====================================================
def open_camera_index(idx, width=1280, height=720, open_wait=0.5):
    """Helper: open a cv2.VideoCapture safely and set size."""
    cap = cv2.VideoCapture(int(idx))
    time.sleep(open_wait)
    if cap is None or not cap.isOpened():
        try:
            cap.release()
        except:
            pass
        return None
    cap.set(cv2.CAP_PROP_FRAME_WIDTH, width)
    cap.set(cv2.CAP_PROP_FRAME_HEIGHT, height)
    return cap

# =====================================================
# 🎯 /check_camera should also respect camera param
# =====================================================
@app.route('/check_camera')
def check_camera():
    """
    Checks if a camera is connected and accessible.
    Accepts ?camera=<index>
    """
    cam_param = request.args.get('camera', default='0')
    try:
        camera_index = int(cam_param)
        if camera_index < 0:
            camera_index = 0
    except:
        camera_index = 0

    try:
        cap = cv2.VideoCapture(camera_index)
        time.sleep(0.3)  # give it a moment
        if not cap.isOpened():
            cap.release()
            print(f"❌ No camera detected at index {camera_index}.")
            return jsonify({"detected": False}), 200

        ret, frame = cap.read()
        cap.release()
        if not ret or frame is None:
            print(f"⚠️ Camera {camera_index} opened but couldn't read a frame.")
            return jsonify({"detected": False}), 200

        print(f"✅ Camera {camera_index} detected and working.")
        return jsonify({"detected": True}), 200
    except Exception as e:
        print(f"❌ Camera check error for {camera_index}: {e}")
        return jsonify({"detected": False}), 500

# =====================================================
# 🧮 HELPER FUNCTIONS
# =====================================================
def _serialize_dets(results):
    """
    Robust serializer: works with Ultralytics result or our CombinedResult.
    Returns list of dicts: {"x1","y1","x2","y2","conf","cls"}
    """
    dets = []
    try:
        boxes = results.boxes
        # boxes.xyxy might be numpy array or torch tensor
        xy = boxes.xyxy
        try:
            arr = xy.cpu().numpy()
        except Exception:
            arr = np.array(xy)
        try:
            confs = boxes.conf.cpu().numpy()
        except Exception:
            confs = np.array(boxes.conf)
        try:
            classes = boxes.cls.cpu().numpy()
        except Exception:
            classes = np.array(boxes.cls)

        for i in range(len(arr)):
            x1, y1, x2, y2 = map(float, arr[i].tolist() if hasattr(arr[i], "tolist") else arr[i])
            conf = float(confs[i])
            cls_id = int(classes[i])
            dets.append({"x1": x1, "y1": y1, "x2": x2, "y2": y2, "conf": conf, "cls": cls_id})
    except Exception as e:
        # If anything fails, return empty list
        print(f"⚠️ _serialize_dets error: {e}")
    return dets

def _summary_from_dets(dets):
    summary = {}
    for d in dets:
        lbl = model.names[d["cls"]]
        summary[lbl] = summary.get(lbl, 0) + 1
    return summary, sum(summary.values())

def calculate_image_accuracy(dets):
    if not dets:
        return 0.0
    return round(sum(d['conf'] for d in dets) / len(dets) * 100, 2)

def iou(box1, box2):
    """Compute Intersection-over-Union between two boxes."""
    x1, y1, x2, y2 = box1
    x1b, y1b, x2b, y2b = box2

    inter_x1 = max(x1, x1b)
    inter_y1 = max(y1, y1b)
    inter_x2 = min(x2, x2b)
    inter_y2 = min(y2, y2b)
    inter_area = max(0, inter_x2 - inter_x1) * max(0, inter_y2 - inter_y1)

    area1 = (x2 - x1) * (y2 - y1)
    area2 = (x2b - x1b) * (y2b - y1b)
    union = area1 + area2 - inter_area

    if union == 0:
        return 0
    return inter_area / union

# =====================================================
# 📊 UPDATE SUMMARY (Non-Duplicating Counting)
# =====================================================
def update_summary(dets, speed):
    global last_summary, running_total_detections, running_accuracy_sum, frame_count, class_totals, recent_objects, object_id_counter

    current_time = time.time()
    frame_count += 1
    acc = calculate_image_accuracy(dets)

    # Remove old detections (expired)
    while recent_objects and current_time - recent_objects[0]['time'] > object_lifetime:
        recent_objects.popleft()

    new_detections = 0

    # Process current detections
    for d in dets:
        label = model.names[d["cls"]]
        box = (d["x1"], d["y1"], d["x2"], d["y2"])
        matched = False

        # Check for match with existing object memory
        for obj in list(recent_objects):
            if obj['label'] == label and iou(obj['box'], box) > 0.6:
                # same litter item → just update timestamp, not new
                obj['box'] = box  # update position
                obj['time'] = current_time
                matched = True
                break

        if not matched:
            # New litter item (not overlapping with recent ones)
            object_id_counter += 1
            recent_objects.append({
                'id': object_id_counter,
                'label': label,
                'box': box,
                'time': current_time
            })
            class_totals[label] = class_totals.get(label, 0) + 1
            new_detections += 1

    running_total_detections += new_detections
    running_accuracy_sum += acc

    avg_acc = (running_accuracy_sum / frame_count) if frame_count > 0 else 0.0

    # Update summary for /live_stats
    last_summary = {
        "total": running_total_detections,
        "accuracy": round(avg_acc, 2),
        "speed": round(speed, 2),
        "classes": dict(sorted(class_totals.items(), key=lambda x: x[1], reverse=True))
    }

# =====================================================
# 🎥 CAMERA STREAM GENERATOR (accepts camera_index)
# =====================================================
def generate_frames(camera_index=0):
    """
    Yield MJPEG frames from the specified camera index.
    camera_index: int
    """
    global camera, running
    print(f"🎬 Starting camera stream on device {camera_index}...")
    try:
        camera = cv2.VideoCapture(int(camera_index))
    except Exception as e:
        print("❌ Error opening camera:", e)
        running = False
        yield b''
        return

    camera.set(cv2.CAP_PROP_FRAME_WIDTH, 1280)
    camera.set(cv2.CAP_PROP_FRAME_HEIGHT, 720)

    if not camera.isOpened():
        print(f"❌ Failed to open camera {camera_index}.")
        running = False
        if camera is not None:
            camera.release()
            camera = None
        yield b''
        return

    print(f"✅ Camera {camera_index} opened.")
    running = True

    while running:
        ret, frame = camera.read()
        if not ret or frame is None:
            print("⚠️ Failed to read frame. Exiting stream loop.")
            break

        start_time = time.time()
        try:
            results = ensemble_predict(frame, conf=detection_threshold)
            dets = _serialize_dets(results)
        except Exception as e:
            print("⚠️ Model detection error:", e)
            dets = []

        # Update stats and draw boxes
        speed = time.time() - start_time
        update_summary(dets, speed)

        for d in dets:
            x1, y1, x2, y2 = map(int, [d["x1"], d["y1"], d["x2"], d["y2"]])
            label = model.names[d["cls"]]
            conf = int(d["conf"] * 100)
            color = CLASS_COLORS.get(label, (255, 255, 255))
            cv2.rectangle(frame, (x1, y1), (x2, y2), color, 2)
            cv2.putText(frame, f"{label} {conf}%", (x1, max(0, y1 - 5)),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.6, color, 2)

        _, buffer = cv2.imencode('.jpg', frame, [int(cv2.IMWRITE_JPEG_QUALITY), 80])
        yield (b'--frame\r\n'
               b'Content-Type: image/jpeg\r\n\r\n' + buffer.tobytes() + b'\r\n')

        time.sleep(0.03)

    print("🛑 Camera streaming stopped.")
    if camera is not None:
        camera.release()
        camera = None

@app.route('/live')
def live_detection():
    """
    Start MJPEG stream. Accepts ?camera=<index>
    """
    global running, running_total_detections, running_accuracy_sum, frame_count, class_totals, last_summary

    # reset counters for a fresh stream
    running_total_detections = 0
    running_accuracy_sum = 0.0
    frame_count = 0
    class_totals = {}
    last_summary = {"total": 0, "accuracy": 0.0, "speed": 0.0, "classes": {}}

    # parse camera param
    cam_param = request.args.get('camera', default='0')
    try:
        camera_index = int(cam_param)
        if camera_index < 0:
            camera_index = 0
    except:
        camera_index = 0

    # if a stream is already running, stop it briefly so we can open new device
    if running:
        running = False
        time.sleep(0.5)

    print(f"🌍 /live called for camera {camera_index}")
    return Response(generate_frames(camera_index), mimetype='multipart/x-mixed-replace; boundary=frame')

@app.route('/stop_camera')
def stop_camera():
    global running
    running = False
    return "Camera stopped", 200

@app.route('/live_stats')
def live_stats():
    return jsonify(last_summary)

@app.route('/reset_stats', methods=['POST'])
def reset_stats():
    global last_summary, running_total_detections, running_accuracy_sum, frame_count, class_totals
    last_summary = {"total": 0, "accuracy": 0.0, "speed": 0.0, "classes": {}}
    running_total_detections = 0
    running_accuracy_sum = 0.0
    frame_count = 0
    class_totals = {}
    print("🔁 Stats reset successful")
    return {"message": "Stats reset"}, 200

@app.route('/set_threshold', methods=['POST'])
def set_threshold():
    global detection_threshold
    data = request.get_json()
    try:
        val = float(data.get("threshold", 0.10))
        detection_threshold = max(0.0, min(1.0, val))
        print(f"🛠 Detection threshold set to: {detection_threshold}")
        return {"message": "Threshold updated", "threshold": detection_threshold}, 200
    except Exception as e:
        return {"error": str(e)}, 400

# =====================================================
# 🧮 HELPER FUNCTIONS (opacity & render)
# =====================================================
def _parse_opacity(val, default=1.0):
    try:
        f = float(val)
        return max(0.0, min(1.0, f / 100 if f > 1.0 else f))
    except:
        return default

def _summary_from_dets(dets):
    summary = {}
    for d in dets:
        lbl = model.names[d["cls"]]
        summary[lbl] = summary.get(lbl, 0) + 1
    return summary, sum(summary.values())

def render_from_dets(orig_path, dets, output_path, mode="confidence", box_opacity=1.0):
    """
    Draws bounding boxes with visual cues and writes to output_path
    """
    img = cv2.imread(orig_path)
    if img is None:
        raise RuntimeError(f"Could not read image: {orig_path}")

    overlay = img.copy()
    thickness = 3

    for d in dets:
        x1, y1, x2, y2 = map(int, [d["x1"], d["y1"], d["x2"], d["y2"]])
        label_name = model.names[d["cls"]]
        color = CLASS_COLORS.get(label_name, (255, 255, 255))
        conf_pct = int(round(d["conf"] * 100))

        sub_overlay = overlay.copy()

        # Confidence-based transparency mapping
        if conf_pct < 10:
            alpha = 1.0
        elif conf_pct < 20:
            alpha = 0.9
        elif conf_pct < 30:
            alpha = 0.8
        elif conf_pct < 40:
            alpha = 0.7
        elif conf_pct < 50:
            alpha = 0.6
        elif conf_pct < 60:
            alpha = 0.5
        elif conf_pct < 70:
            alpha = 0.4
        elif conf_pct < 80:
            alpha = 0.3
        elif conf_pct < 90:
            alpha = 0.2
        else:
            alpha = 0.1

        cv2.rectangle(sub_overlay, (x1, y1), (x2, y2), color, -1)
        cv2.addWeighted(sub_overlay, alpha, overlay, 1 - alpha, 0, overlay)
        cv2.rectangle(overlay, (x1, y1), (x2, y2), color, thickness)

    img = cv2.addWeighted(overlay, box_opacity, img, 1 - box_opacity, 0)

    for d in dets:
        x1, y1, x2, y2 = map(int, [d["x1"], d["y1"], d["x2"], d["y2"]])
        conf_pct = int(round(d["conf"] * 100))
        label_name = model.names[d["cls"]]
        color = CLASS_COLORS.get(label_name, (255, 255, 255))

        text = ""
        if mode == "confidence":
            text = f"{conf_pct}%"
        elif mode == "labels":
            text = f"{label_name} {conf_pct}%"

        if text:
            (tw, th), baseline = cv2.getTextSize(text, cv2.FONT_HERSHEY_SIMPLEX, 0.6, 2)
            cv2.rectangle(img, (x1, max(0, y1 - th - 10)), (x1 + tw, y1), color, -1)
            cv2.putText(img, text, (x1, max(0, y1 - 5)),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2)

    cv2.imwrite(output_path, img)

# =====================================================
# 📦 FILE HANDLING ROUTES
# =====================================================
@app.route('/runs/<path:filename>')
def serve_runs(filename):
    response = make_response(send_from_directory(RUNS_DIR, filename))
    response.headers.update({
        "Access-Control-Allow-Origin": "*",
        "Cache-Control": "no-cache, no-store, must-revalidate"
    })
    return response

@app.route('/')
def home():
    return "✅ Flask YOLO detection API is running."

# =====================================================
# 📸 DETECTION ROUTES — ANALYZE, REDETECT, RERENDER
# =====================================================
@app.route('/analyze', methods=['POST'])
def analyze_image():
    try:
        threshold = float(request.form.get('threshold', 0.10))
        label_mode = request.form.get('label_mode', 'confidence')
        opacity_raw = request.form.get('opacity', '1.00')
        box_opacity = _parse_opacity(opacity_raw, default=1.0)

        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        run_folder = os.path.join(RUNS_DIR, f"save_{timestamp}")
        os.makedirs(run_folder, exist_ok=True)

        detection_results = []
        total_object_summary = {}

        for idx, file_key in enumerate(request.files):
            file = request.files[file_key]
            if file.filename == '':
                continue

            orig_filename = f"{timestamp}_orig_{idx+1}.jpg"
            result_filename = f"{timestamp}_result_{idx+1}.jpg"
            dets_filename = f"{timestamp}_dets_{idx+1}.json"

            orig_path = os.path.join(run_folder, orig_filename)
            result_path = os.path.join(run_folder, result_filename)
            dets_path = os.path.join(run_folder, dets_filename)

            file.save(orig_path)
            results = ensemble_predict(orig_path, conf=threshold)
            dets = _serialize_dets(results)

            with open(dets_path, "w") as f:
                json.dump(dets, f)

            render_from_dets(orig_path, dets, result_path, label_mode, box_opacity)
            summary, total_items = _summary_from_dets(dets)
            image_accuracy = calculate_image_accuracy(dets)

            for k, v in summary.items():
                total_object_summary[k] = total_object_summary.get(k, 0) + v

            detection_results.append({
                'original_image': f"runs/save_{timestamp}/{orig_filename}",
                'result_image': f"runs/save_{timestamp}/{result_filename}",
                'dets_json': f"runs/save_{timestamp}/{dets_filename}",
                'summary': summary,
                'total_items': total_items,
                'accuracy': image_accuracy
            })

        mean_accuracy = round(sum([r['accuracy'] for r in detection_results]) / len(detection_results), 2) if detection_results else 0.0

        return jsonify({
            'message': '✅ Detection successful',
            'results': detection_results,
            'total_summary': total_object_summary,
            'folder': f"save_{timestamp}",
            'accuracy': mean_accuracy,
            'label_mode': label_mode
        })

    except Exception as e:
        import traceback
        traceback.print_exc()
        return jsonify({'error': str(e)}), 500


@app.route('/redetect', methods=['POST'])
def redetect():
    try:
        data = request.get_json()
        folder = data.get('folder')
        threshold = float(data.get('threshold', 0.10))
        label_mode = data.get('label_mode', 'confidence')
        opacity_raw = data.get('opacity', '1.00')
        box_opacity = _parse_opacity(opacity_raw, default=1.0)

        run_folder = os.path.join(RUNS_DIR, folder)
        if not os.path.exists(run_folder):
            return jsonify({'error': 'Folder not found'}), 404

        detection_results = []
        total_object_summary = {}

        orig_images = sorted([f for f in os.listdir(run_folder) if "_orig" in f])
        for orig_img in orig_images:
            dets_img = f"{orig_img.replace('_orig_', '_dets_').rsplit('.', 1)[0]}.json"
            result_img = orig_img.replace("_orig", "_result")
            orig_path = os.path.join(run_folder, orig_img)
            result_path = os.path.join(run_folder, result_img)
            dets_path = os.path.join(run_folder, dets_img)

            if os.path.exists(result_path):
                os.remove(result_path)

            # Use ensemble for redetection
            results = ensemble_predict(orig_path, conf=threshold)
            dets = _serialize_dets(results)
            with open(dets_path, "w") as f:
                json.dump(dets, f)

            render_from_dets(orig_path, dets, result_path, label_mode, box_opacity)
            summary, total_items = _summary_from_dets(dets)
            image_accuracy = calculate_image_accuracy(dets)

            for k, v in summary.items():
                total_object_summary[k] = total_object_summary.get(k, 0) + v

            detection_results.append({
                'original_image': f"runs/{folder}/{orig_img}",
                'result_image': f"runs/{folder}/{result_img}",
                'dets_json': f"runs/{folder}/{dets_img}",
                'summary': summary,
                'total_items': total_items,
                'accuracy': image_accuracy
            })

        mean_accuracy = round(sum([r['accuracy'] for r in detection_results]) / len(detection_results), 2)

        return jsonify({
            'message': '✅ Redetection complete',
            'results': detection_results,
            'total_summary': total_object_summary,
            'accuracy': mean_accuracy,
            'label_mode': label_mode
        })

    except Exception as e:
        import traceback
        traceback.print_exc()
        return jsonify({'error': str(e)}), 500


@app.route('/rerender', methods=['POST'])
def rerender():
    try:
        data = request.get_json()
        folder = data.get('folder')
        label_mode = data.get('label_mode', 'confidence')
        opacity_raw = data.get('opacity', '1.00')
        box_opacity = _parse_opacity(opacity_raw, default=1.0)

        run_folder = os.path.join(RUNS_DIR, folder)
        if not os.path.exists(run_folder):
            return jsonify({'error': 'Folder not found'}), 404

        detection_results = []
        total_object_summary = {}

        orig_images = sorted([f for f in os.listdir(run_folder) if "_orig" in f])
        for orig_img in orig_images:
            dets_img = f"{orig_img.replace('_orig_', '_dets_').rsplit('.', 1)[0]}.json"
            result_img = orig_img.replace("_orig", "_result")
            orig_path = os.path.join(run_folder, orig_img)
            result_path = os.path.join(run_folder, result_img)
            dets_path = os.path.join(run_folder, dets_img)

            with open(dets_path, "r") as f:
                dets = json.load(f)

            render_from_dets(orig_path, dets, result_path, label_mode, box_opacity)
            summary, total_items = _summary_from_dets(dets)
            image_accuracy = calculate_image_accuracy(dets)

            for k, v in summary.items():
                total_object_summary[k] = total_object_summary.get(k, 0) + v

            detection_results.append({
                'original_image': f"runs/{folder}/{orig_img}",
                'result_image': f"runs/{folder}/{result_img}",
                'dets_json': f"runs/{folder}/{dets_img}",
                'summary': summary,
                'total_items': total_items,
                'accuracy': image_accuracy
            })

        return jsonify({
            'message': '✅ Rerender complete',
            'results': detection_results,
            'total_summary': total_object_summary,
            'label_mode': label_mode
        })

    except Exception as e:
        import traceback
        traceback.print_exc()
        return jsonify({'error': str(e)}), 500


# =====================================================
# 🧹 CLEANUP ROUTE
# =====================================================
@app.route('/cleanup', methods=['POST'])
def cleanup():
    try:
        data = request.get_json()
        folder = data.get('folder')
        target_path = os.path.join(RUNS_DIR, folder)
        if os.path.exists(target_path):
            shutil.rmtree(target_path)
            print(f"🧹 Cleaned folder: {target_path}")
        return jsonify({'message': 'Folder cleaned'})
    except Exception as e:
        return jsonify({'error': str(e)}), 500


# =====================================================
# 🏁 RUN APP
# =====================================================
if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5000, debug=True)
