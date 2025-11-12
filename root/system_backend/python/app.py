# app.py
# =====================================================
# 🚀 LITTERLENS FLASK BACKEND — YOLOv8 Real-Time Detection (Ensemble Mode)
# Features:
# - Fetch active model records from Supabase (status='Active')
# - Download missing model files into python/models/
# - Load all available models (1..N) into memory (ensemble)
# - /reload_model endpoint to refresh models (called by PHP after upload/activate/delete)
# - /analyze, /admin_analyze, /redetect, /rerender, /live (MJPEG), /live_stats, /set_threshold, /reset_stats, /stop_camera, /check_camera, /cleanup, /reverse_geocode
# - Thread-safe inference using locks
# =====================================================

from flask import Flask, request, jsonify, Response, send_from_directory, make_response
from flask_cors import CORS
from ultralytics import YOLO
import os
import requests
import threading
import time
import json
import shutil
import cv2
import numpy as np
from collections import deque
from datetime import datetime


# =====================================================
# 🌍 GLOBAL SERVER CONFIGURATION
# =====================================================

# 🧠 Change only this when moving servers or domains!
SERVER_BASE_URL = "http://72.61.117.189"  # ← change to your domain later (e.g., https://litterlens.site)

# Flask configuration
APP_HOST = "0.0.0.0"
APP_PORT = int(os.environ.get("PORT", 5000))

# ============ CONFIG ============
APP_DIR = os.path.dirname(os.path.abspath(__file__))
MODELS_DIR = os.path.join(APP_DIR, "models")
RUNS_DIR = os.path.join(APP_DIR, "runs")
os.makedirs(MODELS_DIR, exist_ok=True)
os.makedirs(RUNS_DIR, exist_ok=True)

# Supabase REST + Storage settings (update if needed)
SUPABASE_REST_MODELS = "https://ksbgdgqpdoxabdefjsin.supabase.co/rest/v1/models"
SUPABASE_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtzYmdkZ3FwZG94YWJkZWZqc2luIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2MTAzMjUxOSwiZXhwIjoyMDc2NjA4NTE5fQ.WAai4nbsqgbe-7PgOw8bktVjk0V9Cm8sdEct_vlQCcY"
SUPABASE_STORAGE_BASE = "https://ksbgdgqpdoxabdefjsin.supabase.co/storage/v1/object"
SUPABASE_BUCKET = "model"
PUBLIC_URL_PREFIX = f"{SUPABASE_STORAGE_BASE}/public/{SUPABASE_BUCKET}/"

# Detection defaults
DETECTION_THRESHOLD = 0.30  # global, adjustable via /set_threshold
TARGET_FPS = 30.0

# ============ FLASK APP ============
app = Flask(__name__)
CORS(app, resources={
    r"/*": {"origins": [SERVER_BASE_URL, f"{SERVER_BASE_URL.replace('http', 'https')}"]},
    r"/api/*": {"origins": [SERVER_BASE_URL, f"{SERVER_BASE_URL.replace('http', 'https')}"]}
})
    
# ============ GLOBAL STATE ============
# models_list: list of loaded ultralytics.YOLO objects
models_list = []
models_meta = []  # list of dicts {filename, model_name}
models_lock = threading.Lock()  # protects models_list & models_meta

# Current single 'model' backward compatibility (first in list)
def first_model():
    return models_list[0] if len(models_list) else None

# For live streaming and stats
current_camera = None
stream_running = False
stream_lock = threading.Lock()
detection_stats_lock = threading.Lock()
recent_objects = deque(maxlen=200)
class_totals = {}
running_total_detections = 0
running_accuracy_sum = 0.0
frame_count = 0
last_summary = {"total": 0, "accuracy": 0.0, "speed": 0.0, "classes": {}}

# Colors for rendering (BGR)
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

# ============ UTIL: Supabase model listing & download ============
def supabase_get_active_models():
    """
    Calls Supabase REST endpoint for `models` table to get active rows.
    Expected fields: model_name, model_filename, status
    Returns list of dicts from Supabase.
    """
    headers = {
        "apikey": SUPABASE_KEY,
        "Authorization": f"Bearer {SUPABASE_KEY}",
        "Content-Type": "application/json",
        "Accept": "application/json",
    }
    try:
        res = requests.get(f"{SUPABASE_REST_MODELS}?select=model_name,model_filename,status", headers=headers, timeout=15)
        res.raise_for_status()
        data = res.json()
        # Filter active ones
        active = [r for r in data if (r.get("status") or "").lower() == "active" and r.get("model_filename")]
        return active
    except Exception as e:
        print(f"[Supabase] failed to fetch model rows: {e}")
        return []


def download_model_if_missing(filename):
    """
    Downloads filename from Supabase storage public path into MODELS_DIR if missing.
    Returns local path or None on failure.
    """
    local_path = os.path.join(MODELS_DIR, filename)
    if os.path.exists(local_path):
        return local_path

    public_url = f"{PUBLIC_URL_PREFIX}{filename}"
    headers = {
        "apikey": SUPABASE_KEY,
        "Authorization": f"Bearer {SUPABASE_KEY}",
    }
    try:
        print(f"[Download] Attempting to download {filename} from Supabase storage...")
        r = requests.get(public_url, headers=headers, stream=True, timeout=60)
        if r.status_code not in (200, 201):
            print(f"[Download] Failed HTTP {r.status_code} for {filename}")
            return None
        with open(local_path, "wb") as f:
            for chunk in r.iter_content(chunk_size=8192):
                if chunk:
                    f.write(chunk)
        print(f"[Download] Saved {filename} -> {local_path}")
        return local_path
    except Exception as e:
        print(f"[Download] error downloading {filename}: {e}")
        if os.path.exists(local_path):
            try:
                os.remove(local_path)
            except:
                pass
        return None

# ============ MODEL LOADING / RELOAD ============
def load_models_from_supabase():
    """
    Fetch active model rows, download missing model files, and load present .pt files into models_list.
    This function replaces the global models_list with the new list (thread-safe).
    Returns dict summary.
    """
    global models_list, models_meta
    with models_lock:
        print("[Models] Fetching active models list from Supabase...")
        rows = supabase_get_active_models()
        if not rows:
            print("[Models] No active rows from Supabase — will fallback to local models dir files.")
            # fallback: load any .pt file in MODELS_DIR
            local_files = sorted([f for f in os.listdir(MODELS_DIR) if f.lower().endswith('.pt')])
            if not local_files:
                print("[Models] No local .pt files found in models dir.")
                models_list = []
                models_meta = []
                return {"loaded": 0, "files": []}
            rows = [{"model_name": os.path.splitext(f)[0], "model_filename": f, "status": "active"} for f in local_files]

        loaded_models = []
        loaded_meta = []
        for r in rows:
            filename = r.get("model_filename")
            model_name = r.get("model_name") or os.path.splitext(filename)[0]
            if not filename:
                continue
            local_path = os.path.join(MODELS_DIR, filename)
            if not os.path.exists(local_path):
                local_path = download_model_if_missing(filename)
                if not local_path:
                    print(f"[Models] Skipping {filename}: download failed or not found.")
                    continue
            # load model (YOLO)
            try:
                print(f"[Models] Loading model file: {local_path} ...")
                m = YOLO(local_path)
                loaded_models.append(m)
                loaded_meta.append({"model_name": model_name, "filename": filename, "path": local_path})
                print(f"[Models] Loaded: {filename}")
            except Exception as e:
                print(f"[Models] Error loading {local_path}: {e}")
                # continue loading others

        # If none loaded but there are fallback local files, try loading them
        if not loaded_models:
            print("[Models] No models loaded from Supabase rows — scanning local dir for .pt files.")
            local_files = sorted([f for f in os.listdir(MODELS_DIR) if f.lower().endswith('.pt')])
            for f in local_files:
                p = os.path.join(MODELS_DIR, f)
                try:
                    print(f"[Models] Loading local fallback {p}")
                    m = YOLO(p)
                    loaded_models.append(m)
                    loaded_meta.append({"model_name": os.path.splitext(f)[0], "filename": f, "path": p})
                except Exception as e:
                    print(f"[Models] Failed to load fallback model {p}: {e}")

        models_list = loaded_models
        models_meta = loaded_meta
        print(f"[Models] Total models loaded: {len(models_list)}")
        return {"loaded": len(models_list), "meta": models_meta}

# initial load
print("[Startup] Loading models...")
initial_info = load_models_from_supabase()
print("[Startup] Models load info:", initial_info)

# ============ HELPER: NMS & Ensemble Merging ============
def simple_nms(boxes, scores, iou_threshold=0.5):
    """
    Basic NMS for numpy arrays (boxes Nx4, scores N).
    Returns indices kept.
    """
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
        if order.size == 1:
            break
        xx1 = np.maximum(x1[i], x1[order[1:]])
        yy1 = np.maximum(y1[i], y1[order[1:]])
        xx2 = np.minimum(x2[i], x2[order[1:]])
        yy2 = np.minimum(y2[i], y2[order[1:]])
        w = np.maximum(0.0, xx2 - xx1)
        h = np.maximum(0.0, yy2 - yy1)
        inter = w * h
        union = areas[i] + areas[order[1:]] - inter
        valid = union > 0
        iou = np.zeros_like(inter)
        iou[valid] = inter[valid] / union[valid]
        inds = np.where(iou <= iou_threshold)[0]
        order = order[inds + 1]
    return np.array(keep, dtype=int)

def ensemble_predict(image, conf=0.30, iou_thresh=0.5):
    """
    Run inference on all loaded models and merge predictions using class-aware NMS.
    Accepts an OpenCV image path or numpy array or filepath (YOLO supports both).
    Returns CombinedResult-like object with .boxes.xyxy, .boxes.conf, .boxes.cls arrays.
    """
    # gather boxes, confs, classes
    all_boxes = []
    all_confs = []
    all_classes = []

    with models_lock:
        if not models_list:
            # No models loaded
            return None
        models_copy = list(models_list)

    for m in models_copy:
        try:
            results = m(image, conf=conf, verbose=False)[0]
            # safe conversions
            try:
                boxes_np = results.boxes.xyxy.cpu().numpy()
                confs_np = results.boxes.conf.cpu().numpy()
                cls_np = results.boxes.cls.cpu().numpy()
            except Exception:
                boxes_np = np.array(results.boxes.xyxy)
                confs_np = np.array(results.boxes.conf)
                cls_np = np.array(results.boxes.cls)
            if boxes_np.size:
                all_boxes.append(boxes_np)
                all_confs.append(confs_np)
                all_classes.append(cls_np)
        except Exception as e:
            print(f"[Ensemble] model inference error: {e}")
            continue

    if all_boxes:
        all_boxes = np.concatenate(all_boxes, axis=0)
        all_confs = np.concatenate(all_confs, axis=0)
        all_classes = np.concatenate(all_classes, axis=0)
    else:
        all_boxes = np.empty((0, 4))
        all_confs = np.empty((0,))
        all_classes = np.empty((0,))

    # class-aware NMS
    final_indices = []
    if all_boxes.shape[0] > 0:
        unique_classes = np.unique(all_classes)
        for cls in unique_classes:
            cls_mask = (all_classes == cls)
            cls_boxes = all_boxes[cls_mask]
            cls_scores = all_confs[cls_mask]
            global_idx = np.where(cls_mask)[0]
            keep_local = simple_nms(cls_boxes, cls_scores, iou_threshold=iou_thresh)
            final_indices.extend(global_idx[keep_local].tolist())
        if final_indices:
            final_indices = np.array(final_indices)
            # sort by score desc
            order = all_confs[final_indices].argsort()[::-1]
            final_indices = final_indices[order]
        else:
            final_indices = np.array([], dtype=int)

        final_boxes = all_boxes[final_indices]
        final_confs = all_confs[final_indices]
        final_classes = all_classes[final_indices]
    else:
        final_boxes = np.empty((0, 4))
        final_confs = np.empty((0,))
        final_classes = np.empty((0,))

    # Create minimal object matching ultralytics' result interface used in _serialize_dets
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

# ============ SERIALIZE helpers ============
def _serialize_dets(results):
    """
    Works for Ultralytics result or our CombinedResult: returns list of dicts.
    """
    dets = []
    try:
        if results is None:
            return dets
        boxes = results.boxes
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
            row = arr[i]
            x1, y1, x2, y2 = map(float, row.tolist() if hasattr(row, "tolist") else row)
            conf = float(confs[i])
            cls_id = int(classes[i])
            dets.append({"x1": x1, "y1": y1, "x2": x2, "y2": y2, "conf": conf, "cls": cls_id})
    except Exception as e:
        print(f"[Serialize] error: {e}")
    return dets

def calculate_image_accuracy(dets):
    if not dets:
        return 0.0
    return round(sum(d['conf'] for d in dets) / len(dets) * 100, 2)

def _summary_from_dets(dets):
    summary = {}
    # need mapping model->names; we use first_model().names if present
    base_model = first_model()
    if not base_model:
        return {}, 0
    names = getattr(base_model, "names", None) or {}
    for d in dets:
        label = names.get(d["cls"], str(d["cls"]))
        summary[label] = summary.get(label, 0) + 1
    return summary, sum(summary.values())

# ============ STATS / DEDUP LOGIC ============
def iou(box1, box2):
    x1, y1, x2, y2 = box1
    x1b, y1b, x2b, y2b = box2
    inter_x1 = max(x1, x1b)
    inter_y1 = max(y1, y1b)
    inter_x2 = min(x2, x2b)
    inter_y2 = min(y2, y2b)
    inter_area = max(0, inter_x2 - inter_x1) * max(0, inter_y2 - inter_y1)
    area1 = max(0, (x2 - x1)) * max(0, (y2 - y1))
    area2 = max(0, (x2b - x1b)) * max(0, (y2b - y1b))
    union = area1 + area2 - inter_area
    if union <= 0:
        return 0.0
    return inter_area / union

def update_summary(dets, speed):
    """
    Update running stats while preventing immediate duplicates using spatial + label matching.
    """
    global recent_objects, class_totals, running_total_detections, running_accuracy_sum, frame_count, last_summary
    with detection_stats_lock:
        frame_count += 1
        acc = calculate_image_accuracy(dets)
        now = time.time()
        # expire old objects older than 5s
        exp = 5.0
        while recent_objects and now - recent_objects[0]['time'] > exp:
            recent_objects.popleft()

        new_detects = 0
        base_model = first_model()
        names = getattr(base_model, "names", {}) if base_model else {}

        for d in dets:
            label = names.get(d["cls"], str(d["cls"]))
            box = (d["x1"], d["y1"], d["x2"], d["y2"])
            conf = d["conf"]
            matched = False
            best_i = None
            best_iou = 0.0
            # match existing
            for i, obj in enumerate(list(recent_objects)):
                if obj['label'] == label:
                    score = iou(obj['box'], box)
                    if score > 0.35 and score > best_iou:
                        best_iou = score
                        best_i = i
            if best_i is not None:
                # update object
                recent_objects[best_i]['box'] = box
                recent_objects[best_i]['time'] = now
                recent_objects[best_i]['conf'] = conf
            else:
                # new
                recent_objects.append({'id': int(now*1000), 'label': label, 'box': box, 'time': now, 'conf': conf})
                class_totals[label] = class_totals.get(label, 0) + 1
                new_detects += 1

        running_total_detections += new_detects
        running_accuracy_sum += acc
        avg_acc = (running_accuracy_sum / frame_count) if frame_count > 0 else 0.0
        last_summary = {
            "total": running_total_detections,
            "accuracy": round(avg_acc, 2),
            "speed": round(speed, 3),
            "classes": dict(sorted(class_totals.items(), key=lambda x: x[1], reverse=True))
        }

# ============ CAMERA STREAM ============
def open_camera(index=0, width=640, height=360):
    try:
        cap = cv2.VideoCapture(int(index), cv2.CAP_DSHOW)  # Windows; falls back on others
    except Exception:
        cap = cv2.VideoCapture(int(index))
    time.sleep(0.2)
    if not cap or not cap.isOpened():
        try:
            cap.release()
        except:
            pass
        return None
    cap.set(cv2.CAP_PROP_FRAME_WIDTH, width)
    cap.set(cv2.CAP_PROP_FRAME_HEIGHT, height)
    cap.set(cv2.CAP_PROP_FPS, TARGET_FPS)
    return cap

def generate_frames(camera_index=0):
    global current_camera, stream_running
    cap = open_camera(camera_index, width=640, height=360)
    if cap is None:
        print(f"[Stream] Unable to open camera {camera_index}")
        yield b''
        return

    current_camera = cap
    stream_running = True
    prev = time.time()
    fps_counter = 0
    fps_last = time.time()
    fps = 0.0

    while stream_running:
        ret, frame = cap.read()
        if not ret or frame is None:
            print("[Stream] camera read failed")
            break
        # resize for speed
        frame = cv2.resize(frame, (640, 360))
        start = time.time()
        # use ensemble_predict if multiple models loaded else single model
        try:
            with models_lock:
                multi = len(models_list) > 1
            if multi:
                results = ensemble_predict(frame, conf=DETECTION_THRESHOLD)
            else:
                m = first_model()
                if not m:
                    results = None
                else:
                    res = m(frame, conf=DETECTION_THRESHOLD, verbose=False)[0]
                    # wrap to same interface expected by _serialize_dets
                    results = res
            dets = _serialize_dets(results)
        except Exception as e:
            print("[Stream] detection error:", e)
            dets = []

        inference_time = time.time() - start
        update_summary(dets, inference_time)

        # draw boxes
        overlay = frame.copy()
        base_model = first_model()
        names = getattr(base_model, "names", {}) if base_model else {}
        for d in dets:
            x1, y1, x2, y2 = map(int, [d["x1"], d["y1"], d["x2"], d["y2"]])
            label = names.get(d["cls"], str(d["cls"]))
            conf = d["conf"]
            color = CLASS_COLORS.get(label, (0, 255, 255))
            cv2.rectangle(overlay, (x1, y1), (x2, y2), color, 2, cv2.LINE_AA)
            text = f"{label} {conf*100:.1f}%"
            (tw, th), _ = cv2.getTextSize(text, cv2.FONT_HERSHEY_DUPLEX, 0.5, 1)
            y_text = max(0, y1 - th - 6)
            sub = overlay.copy()
            cv2.rectangle(sub, (x1, y_text), (x1 + tw + 6, y1), color, -1)
            cv2.addWeighted(sub, 0.7, overlay, 0.3, 0, overlay)
            cv2.putText(overlay, text, (x1 + 3, y1 - 5), cv2.FONT_HERSHEY_DUPLEX, 0.5, (255,255,255), 1, cv2.LINE_AA)
        frame_out = cv2.addWeighted(overlay, 1.0, frame, 0, 0)

        # fps calc
        fps_counter += 1
        now = time.time()
        if now - fps_last >= 1.0:
            fps = fps_counter / (now - fps_last)
            fps_counter = 0
            fps_last = now
        cv2.putText(frame_out, f"FPS: {fps:.1f}", (10, 24), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0,255,0), 2, cv2.LINE_AA)

        _, buf = cv2.imencode('.jpg', frame_out, [int(cv2.IMWRITE_JPEG_QUALITY), 70])
        frame_bytes = buf.tobytes()
        yield (b'--frame\r\n'
               b'Content-Type: image/jpeg\r\n\r\n' + frame_bytes + b'\r\n')
        # pacing to target FPS approx
        elapsed = time.time() - start
        target = 1.0 / TARGET_FPS
        if elapsed < target:
            time.sleep(target - elapsed)

    # cleanup
    try:
        cap.release()
    except:
        pass
    stream_running = False
    current_camera = None
    print("[Stream] camera closed")

# ============ ROUTES ============

@app.route('/')
def home():
    return "✅ LitterLens Flask YOLO Ensemble API running."

@app.route('/check_camera')
def check_camera():
    cam_param = request.args.get('camera', default='0')
    try:
        cam_index = int(cam_param)
    except:
        cam_index = 0
    cap = open_camera(cam_index)
    if not cap:
        return jsonify({"detected": False}), 200
    ret, frame = cap.read()
    try:
        cap.release()
    except:
        pass
    # 🕒 Add small delay to allow driver release (Windows fix)
    time.sleep(0.5)
    return jsonify({"detected": bool(ret)}), 200

@app.route('/live')
def live():
    cam = request.args.get('camera', default='0')
    try:
        cam_index = int(cam)
    except:
        cam_index = 0
    # if stream running, stop so we can reopen properly
    global stream_running
    if stream_running:
        stream_running = False
        time.sleep(0.3)
    return Response(generate_frames(cam_index), mimetype='multipart/x-mixed-replace; boundary=frame')

@app.route('/stop_camera')
def stop_camera():
    global stream_running
    stream_running = False
    return "Stopped", 200

@app.route('/live_stats')
def live_stats():
    return jsonify(last_summary)

@app.route('/reset_stats', methods=['POST'])
def reset_stats():
    global last_summary, running_total_detections, running_accuracy_sum, frame_count, class_totals, recent_objects
    with detection_stats_lock:
        last_summary = {"total": 0, "accuracy": 0.0, "speed": 0.0, "classes": {}}
        running_total_detections = 0
        running_accuracy_sum = 0.0
        frame_count = 0
        class_totals = {}
        recent_objects.clear()
    return jsonify({"message": "Stats reset"}), 200

@app.route('/set_threshold', methods=['POST'])
def set_threshold():
    global DETECTION_THRESHOLD
    data = request.get_json() or {}
    try:
        val = float(data.get("threshold", DETECTION_THRESHOLD))
        DETECTION_THRESHOLD = max(0.0, min(1.0, val))
        return jsonify({"message": "Threshold set", "threshold": DETECTION_THRESHOLD}), 200
    except Exception as e:
        return jsonify({"error": str(e)}), 400

@app.route('/analyze', methods=['POST'])
def analyze():
    """
    Endpoint for general image analysis (public).
    Accepts multipart files. Returns result JSON + saved images in runs/.
    """
    try:
        threshold = float(request.form.get('threshold', DETECTION_THRESHOLD))
        label_mode = request.form.get('label_mode', 'confidence')
        opacity_val = request.form.get('opacity', '1.0')
        box_opacity = float(opacity_val) if opacity_val else 1.0

        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        run_folder = os.path.join(RUNS_DIR, f"admin_{timestamp}")
        os.makedirs(run_folder, exist_ok=True)

        results_out = []
        total_summary = {}

        # iterate files
        for idx, key in enumerate(request.files):
            f = request.files[key]
            if not f or f.filename == '':
                continue
            orig_name = f"{timestamp}_orig_{idx+1}.jpg"
            result_name = f"{timestamp}_result_{idx+1}.jpg"
            dets_name = f"{timestamp}_dets_{idx+1}.json"
            orig_path = os.path.join(run_folder, orig_name)
            result_path = os.path.join(run_folder, result_name)
            dets_path = os.path.join(run_folder, dets_name)
            f.save(orig_path)

            # Run detection using ensemble if multiple models loaded else single
            with models_lock:
                multi = len(models_list) > 1
            if multi:
                res = ensemble_predict(orig_path, conf=threshold)
            else:
                m = first_model()
                if not m:
                    res = None
                else:
                    res = m(orig_path, conf=threshold, verbose=False)[0]
            dets = _serialize_dets(res)
            with open(dets_path, "w") as fh:
                json.dump(dets, fh)
            # render visual
            try:
                render_from_dets(orig_path, dets, result_path, label_mode, float(box_opacity))
            except Exception as e:
                print("[Render] error:", e)
                shutil.copy(orig_path, result_path)
            summary, total_items = _summary_from_dets(dets)
            for k, v in summary.items():
                total_summary[k] = total_summary.get(k, 0) + v
            results_out.append({
                "original_image": f"runs/admin_{timestamp}/{orig_name}",
                "result_image": f"runs/admin_{timestamp}/{result_name}",
                "dets_json": f"runs/admin_{timestamp}/{dets_name}",
                "summary": summary,
                "total_items": total_items,
                "accuracy": calculate_image_accuracy(dets)
            })

        mean_acc = round(sum(r['accuracy'] for r in results_out) / len(results_out), 2) if results_out else 0.0
        return jsonify({"message": "success", "results": results_out, "total_summary": total_summary, "folder": f"admin_{timestamp}", "accuracy": mean_acc}), 200
    
    except Exception as e:
        import traceback; traceback.print_exc()
        return jsonify({"error": str(e)}), 500
 
@app.route('/admin_analyze', methods=['POST'])
def admin_analyze():
    """
    Similar to /analyze but returns redirect link for admin results page.
    """
    try:
        admin_id = request.form.get('admin_id', '1')
        admin_name = request.form.get('admin_name', 'Admin')
        threshold = float(request.form.get('threshold', DETECTION_THRESHOLD))
        label_mode = request.form.get('label_mode', 'confidence')
        box_opacity = float(request.form.get('opacity', '1.0'))

        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        run_folder = os.path.join(RUNS_DIR, f"admin_{timestamp}")
        os.makedirs(run_folder, exist_ok=True)

        results_out = []
        total_summary = {}

        for idx, key in enumerate(request.files):
            f = request.files[key]
            if not f or f.filename == '':
                continue
            orig_name = f"{timestamp}_orig_{idx+1}.jpg"
            result_name = f"{timestamp}_result_{idx+1}.jpg"
            dets_name = f"{timestamp}_dets_{idx+1}.json"
            orig_path = os.path.join(run_folder, orig_name)
            result_path = os.path.join(run_folder, result_name)
            dets_path = os.path.join(run_folder, dets_name)
            f.save(orig_path)

            with models_lock:
                multi = len(models_list) > 1
            if multi:
                res = ensemble_predict(orig_path, conf=threshold)
            else:
                m = first_model()
                if not m:
                    res = None
                else:
                    res = m(orig_path, conf=threshold, verbose=False)[0]
            dets = _serialize_dets(res)
            with open(dets_path, "w") as fh:
                json.dump(dets, fh)
            try:
                render_from_dets(orig_path, dets, result_path, label_mode, float(box_opacity))
            except Exception as e:
                print("[Render] admin error:", e)
                shutil.copy(orig_path, result_path)
            summary, total_items = _summary_from_dets(dets)
            for k, v in summary.items():
                total_summary[k] = total_summary.get(k, 0) + v
            results_out.append({
                "original_image": f"runs/admin_{timestamp}/{orig_name}",
                "result_image": f"runs/admin_{timestamp}/{result_name}",
                "dets_json": f"runs/admin_{timestamp}/{dets_name}",
                "summary": summary,
                "total_items": total_items,
                "accuracy": calculate_image_accuracy(dets)
            })

        mean_acc = round(sum(r['accuracy'] for r in results_out) / len(results_out), 2) if results_out else 0.0

        # Save a JSON summary for admin UI use
        result_payload = {"message": "success", "results": results_out, "total_summary": total_summary, "folder": f"admin_{timestamp}", "accuracy": mean_acc, "admin_id": admin_id, "admin_name": admin_name}
        with open(os.path.join(run_folder, "result_data.json"), "w") as fh:
            json.dump(result_payload, fh, indent=2)

        redirect_url = f"{SERVER_BASE_URL}/php/index_result.php?folder=admin_{timestamp}"
        return jsonify({"status": "success", "redirect": redirect_url, "data": result_payload}), 200

    except Exception as e:
        import traceback; traceback.print_exc()
        return jsonify({"error": str(e)}), 500

@app.route('/redetect', methods=['POST'])
def redetect():
    try:
        data = request.get_json() or {}
        folder = data.get('folder')
        threshold = float(data.get('threshold', DETECTION_THRESHOLD))
        label_mode = data.get('label_mode', 'confidence')
        opacity = float(data.get('opacity', '1.0'))
        run_folder = os.path.join(RUNS_DIR, folder)
        if not os.path.exists(run_folder):
            return jsonify({"error": "folder not found"}), 404

        origs = sorted([f for f in os.listdir(run_folder) if "_orig_" in f])
        results_out = []
        total_summary = {}
        for orig in origs:
            orig_path = os.path.join(run_folder, orig)
            dets_path = os.path.join(run_folder, orig.replace("_orig_", "_dets_").rsplit('.',1)[0] + ".json")
            result_path = os.path.join(run_folder, orig.replace("_orig", "_result"))
            # delete previous result if exists
            if os.path.exists(result_path):
                try:
                    os.remove(result_path)
                except:
                    pass

            with models_lock:
                multi = len(models_list) > 1
            if multi:
                res = ensemble_predict(orig_path, conf=threshold)
            else:
                m = first_model()
                res = m(orig_path, conf=threshold, verbose=False)[0] if m else None
            dets = _serialize_dets(res)
            with open(dets_path, "w") as fh:
                json.dump(dets, fh)
            try:
                render_from_dets(orig_path, dets, result_path, label_mode, opacity)
            except Exception as e:
                print("[Redetect] render error", e)
                shutil.copy(orig_path, result_path)
            summary, total_items = _summary_from_dets(dets)
            for k,v in summary.items():
                total_summary[k] = total_summary.get(k,0) + v
            results_out.append({"original_image": f"runs/{folder}/{orig}", "result_image": f"runs/{folder}/{os.path.basename(result_path)}", "summary": summary, "total_items": total_items, "accuracy": calculate_image_accuracy(dets)})
        mean_acc = round(sum(r['accuracy'] for r in results_out) / len(results_out), 2) if results_out else 0.0
        return jsonify({"message":"redetect complete", "results": results_out, "total_summary": total_summary, "accuracy": mean_acc}), 200
    except Exception as e:
        import traceback; traceback.print_exc()
        return jsonify({"error": str(e)}), 500

@app.route('/rerender', methods=['POST'])
def rerender():
    try:
        data = request.get_json() or {}
        folder = data.get('folder')
        label_mode = data.get('label_mode', 'confidence')
        opacity = float(data.get('opacity', '1.0'))
        run_folder = os.path.join(RUNS_DIR, folder)
        if not os.path.exists(run_folder):
            return jsonify({"error":"folder not found"}), 404

        origs = sorted([f for f in os.listdir(run_folder) if "_orig_" in f])
        results_out = []
        total_summary = {}
        for orig in origs:
            orig_path = os.path.join(run_folder, orig)
            dets_path = os.path.join(run_folder, orig.replace("_orig_", "_dets_").rsplit('.',1)[0] + ".json")
            result_path = os.path.join(run_folder, orig.replace("_orig", "_result"))
            if not os.path.exists(dets_path):
                continue
            with open(dets_path, "r") as fh:
                dets = json.load(fh)
            try:
                render_from_dets(orig_path, dets, result_path, label_mode, opacity)
            except Exception as e:
                print("[Rerender] error:", e)
                shutil.copy(orig_path, result_path)
            summary, total_items = _summary_from_dets(dets)
            for k,v in summary.items():
                total_summary[k] = total_summary.get(k,0) + v
            results_out.append({"original_image": f"runs/{folder}/{orig}", "result_image": f"runs/{folder}/{os.path.basename(result_path)}", "summary": summary, "accuracy": calculate_image_accuracy(dets)})
        return jsonify({"message":"rerender complete","results": results_out,"total_summary": total_summary}), 200
    except Exception as e:
        import traceback; traceback.print_exc()
        return jsonify({"error": str(e)}), 500

@app.route('/cleanup', methods=['POST'])
def cleanup():
    try:
        data = request.get_json() or {}
        folder = data.get('folder')
        target = os.path.join(RUNS_DIR, folder)
        if os.path.exists(target):
            shutil.rmtree(target)
        return jsonify({"message":"cleaned"}), 200
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/reload_model', methods=['POST'])
def reload_model_route():
    """
    Called by PHP after model upload/activate/delete.
    Downloads and reloads all active models from Supabase.
    """
    try:
        info = load_models_from_supabase()
        return jsonify({"success": True, "info": info}), 200
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

@app.route('/reverse_geocode')
def reverse_geocode():
    lat = request.args.get('lat')
    lon = request.args.get('lon')
    if not lat or not lon:
        return jsonify({"error":"missing coords"}), 400
    try:
        url = f"https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat={lat}&lon={lon}"
        r = requests.get(url, headers={"User-Agent": "LitterLens/1.0"}, timeout=10)
        r.raise_for_status()
        data = r.json()
        address = data.get("address", {})
        barangay = address.get("neighbourhood") or address.get("suburb") or address.get("village") or address.get("hamlet")
        city = address.get("city") or address.get("town") or address.get("municipality") or address.get("county")
        province = address.get("state") or address.get("region")
        country = address.get("country")
        label = ", ".join(filter(None, [barangay, city, province, country]))
        return jsonify({"barangay": barangay or "Unknown", "city": city or "Unknown", "province": province or "Unknown", "country": country or "Philippines", "display_name": label or data.get("display_name","")})
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/models_list')
def models_list_route():
    """
    Return the currently loaded models (meta info).
    """
    with models_lock:
        return jsonify({"count": len(models_meta), "models": models_meta}), 200

@app.route('/models/downloaded/<filename>')
def serve_model_file(filename):
    # allow serving downloaded model files for debugging if needed
    return send_from_directory(MODELS_DIR, filename, as_attachment=True)

# ============ RENDER UTIL ============
def render_from_dets(orig_path, dets, output_path, mode="confidence", box_opacity=1.0):
    """
    Draw bounding boxes (det dicts) on orig image and save to output_path.
    mode options:
      - "confidence": show only confidence values
      - "labels": show label + confidence
      - "boxes": show only boxes (no text)
    """
    if box_opacity <= 0 or box_opacity > 1:
        box_opacity = 1.0  # 🧩 ensure valid opacity

    img = cv2.imread(orig_path)
    if img is None:
        raise RuntimeError(f"cannot read {orig_path}")

    overlay = img.copy()
    base_model = first_model()
    names = getattr(base_model, "names", {}) if base_model else {}

    # 🟩 Draw filled boxes with color + transparency
    # 🟩 Draw filled boxes with color + transparency
    for d in dets:
        x1, y1, x2, y2 = map(int, [d["x1"], d["y1"], d["x2"], d["y2"]])
        label = names.get(d["cls"], str(d["cls"]))
        color = CLASS_COLORS.get(label, (255, 255, 255))
        conf_pct = d["conf"] * 100

        if conf_pct < 90:  
            # 🟢 Below 90% → fill with dynamic opacity
            dynamic_opacity = np.interp(d["conf"], [0.3, 0.9], [0.2, 0.8])
            dynamic_opacity = np.clip(dynamic_opacity, 0.2, 0.8)
            sub = overlay.copy()
            cv2.rectangle(sub, (x1, y1), (x2, y2), color, -1)
            cv2.addWeighted(sub, dynamic_opacity, overlay, 1 - dynamic_opacity, 0, overlay)
        else:
            # 🔵 90% and above → no fill, outline only
            pass

        # Always draw the outer border
        cv2.rectangle(overlay, (x1, y1), (x2, y2), color, 2)


    # Merge overlay with base image using opacity
    img = cv2.addWeighted(overlay, box_opacity, img, 1 - box_opacity, 0)

    # 🏷️ Draw text (only if mode allows)
    for d in dets:
        if mode == "boxes":
            continue  # 🚫 skip all text drawing

        x1, y1, x2, y2 = map(int, [d["x1"], d["y1"], d["x2"], d["y2"]])
        label = names.get(d["cls"], str(d["cls"]))
        conf_pct = int(round(d["conf"] * 100))
        color = CLASS_COLORS.get(label, (255, 255, 255))

        if mode == "confidence":
            text = f"{conf_pct}%"
        elif mode == "labels":
            text = f"{label} {conf_pct}%"
        else:
            text = f"{conf_pct}%"

        (tw, th), baseline = cv2.getTextSize(text, cv2.FONT_HERSHEY_SIMPLEX, 0.6, 2)
        py = max(0, y1 - th - 6)
        cv2.rectangle(img, (x1, py), (x1 + tw + 6, y1), color, -1)
        cv2.putText(img, text, (x1 + 3, y1 - 5), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2)

    cv2.imwrite(output_path, img)

    
# ============================================================
# 🖼️ STATIC FILE SERVE — allow gallery.js to access /runs/*
# ============================================================
@app.route('/runs/<path:filename>')
def serve_runs(filename):
    runs_path = os.path.join(RUNS_DIR)
    return send_from_directory(runs_path, filename)

# ============ MAIN ============
if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))  # Render sets PORT automatically
    app.run(host='0.0.0.0', port=port)
