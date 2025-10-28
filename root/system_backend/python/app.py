from flask import Flask, request, jsonify, send_from_directory
from flask_cors import CORS
from ultralytics import YOLO
from datetime import datetime
from flask import make_response
import os
import shutil
import cv2
import json
import io
from flask import Response
import time
import threading
from collections import deque
import math


app = Flask(__name__)
CORS(app)

# === PATH CONFIGURATION ===
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
RUNS_DIR = os.path.join(SCRIPT_DIR, "runs")
MODEL_PATH = os.path.join(SCRIPT_DIR, "my_model.pt")

os.makedirs(RUNS_DIR, exist_ok=True)

# ✅ Load YOLO model
print(f"📦 Loading YOLO model from: {MODEL_PATH}")
model = YOLO(MODEL_PATH)

# === CLASS COLOR MAP (BGR for OpenCV) ===
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


# === Live Stats Tracking ===
detection_threshold = 0.25  # default
recent_objects = deque(maxlen=200)  # limited memory
object_lifetime = 2.0  # seconds — consider object "gone" after this
object_id_counter = 0
last_summary = {"total": 0, "accuracy": 0.0, "speed": 0.0, "classes": {}}
running_total_detections = 0
running_accuracy_sum = 0.0
frame_count = 0
class_totals = {}

camera = None
running = False
lock = threading.Lock()

def generate_frames():
    global camera, running
    camera = cv2.VideoCapture(0)
    camera.set(cv2.CAP_PROP_FRAME_WIDTH, 1280)
    camera.set(cv2.CAP_PROP_FRAME_HEIGHT, 720)
    running = True

    while running:
        ret, frame = camera.read()
        if not ret:
            break

        # 🕒 Start time for speed measurement
        start_time = time.time()

        # 🧠 YOLO detection
        results = model(frame, conf=detection_threshold)[0]
        dets = _serialize_dets(results)

        # 🖼️ Draw boxes
        for d in dets:
            x1, y1, x2, y2 = map(int, [d["x1"], d["y1"], d["x2"], d["y2"]])
            label = model.names[d["cls"]]
            conf = int(d["conf"] * 100)
            color = CLASS_COLORS.get(label, (255, 255, 255))
            cv2.rectangle(frame, (x1, y1), (x2, y2), color, 2)
            cv2.putText(frame, f"{label} {conf}%", (x1, max(0, y1 - 5)),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.6, color, 2)

        # 🕒 Calculate speed (seconds/frame)
        speed = time.time() - start_time

        # ✅ Update stats for frontend
        update_summary(dets, speed)

        # 📸 Encode frame for MJPEG stream
        _, buffer = cv2.imencode('.jpg', frame, [int(cv2.IMWRITE_JPEG_QUALITY), 80])
        yield (b'--frame\r\n'
               b'Content-Type: image/jpeg\r\n\r\n' + buffer.tobytes() + b'\r\n')

        time.sleep(0.03)

    if camera is not None:
        camera.release()
        camera = None


@app.route('/live')
def live_detection():
    global running, running_total_detections, running_accuracy_sum, frame_count, class_totals, last_summary
    # reset counters when starting new stream
    running_total_detections = 0
    running_accuracy_sum = 0.0
    frame_count = 0
    class_totals = {}
    last_summary = {"total": 0, "accuracy": 0.0, "speed": 0.0, "classes": {}}

    if running:
        running = False
        time.sleep(0.5)
    return Response(generate_frames(), mimetype='multipart/x-mixed-replace; boundary=frame')


@app.route('/stop_camera')
def stop_camera():
    global running
    running = False
    return "Camera stopped", 200

# === Detection Summary (For Live Stats Update) ===
last_summary = {"total": 0, "accuracy": 0, "speed": 0.0, "classes": {}}
last_frame_time = time.time()

def update_summary(dets, speed):
    global last_summary, running_total_detections, running_accuracy_sum, frame_count, class_totals, recent_objects, object_id_counter

    current_time = time.time()
    frame_count += 1
    summary, total_items = _summary_from_dets(dets)
    acc = calculate_image_accuracy(dets)

    # Clean old detections
    while recent_objects and current_time - recent_objects[0]['time'] > object_lifetime:
        recent_objects.popleft()

    new_detections = 0

    for d in dets:
        label = model.names[d["cls"]]
        box = (d["x1"], d["y1"], d["x2"], d["y2"])
        matched = False

        # check against recently seen objects
        for obj in recent_objects:
            if obj['label'] == label and iou(obj['box'], box) > 0.5:
                matched = True
                obj['time'] = current_time  # refresh timestamp
                break

        if not matched:
            # new object detected
            object_id_counter += 1
            recent_objects.append({'id': object_id_counter, 'label': label, 'box': box, 'time': current_time})
            class_totals[label] = class_totals.get(label, 0) + 1
            new_detections += 1

    running_total_detections += new_detections
    running_accuracy_sum += acc

    avg_acc = (running_accuracy_sum / frame_count) if frame_count > 0 else 0.0
    last_summary = {
        "total": running_total_detections,
        "accuracy": round(avg_acc, 2),
        "speed": round(speed, 2),
        "classes": class_totals
    }


@app.route('/live_stats')
def live_stats():
    return jsonify(last_summary)


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
        val = float(data.get("threshold", 0.25))
        detection_threshold = max(0.0, min(1.0, val))  # clamp between 0 and 1
        print(f"🛠 Detection threshold set to: {detection_threshold}")
        return {"message": "Threshold updated", "threshold": detection_threshold}, 200
    except Exception as e:
        return {"error": str(e)}, 400










# === Helper Functions ===
def _parse_opacity(val, default=1.0):
    try:
        f = float(val)
        if f > 1.0:
            f = f / 100.0
        f = max(0.0, min(1.0, f))
        return f
    except:
        return default

def _serialize_dets(results):
    dets = []
    for box in results.boxes:
        x1, y1, x2, y2 = map(float, box.xyxy[0].tolist())
        conf = float(box.conf.item())
        cls_id = int(box.cls.item())
        dets.append({"x1": x1, "y1": y1, "x2": x2, "y2": y2, "conf": conf, "cls": cls_id})
    return dets

def _summary_from_dets(dets):
    summary = {}
    for d in dets:
        lbl = model.names[d["cls"]]
        summary[lbl] = summary.get(lbl, 0) + 1
    return summary, sum(summary.values())

def calculate_image_accuracy(dets):
    """Compute per-image accuracy based on mean confidence."""
    if not dets:
        return 0.0
    total_conf = sum(d['conf'] for d in dets)
    mean_conf = total_conf / len(dets)
    return round(mean_conf * 100, 2)

def render_from_dets(orig_path, dets, output_path, mode="confidence", box_opacity=1.0):
    img = cv2.imread(orig_path)
    if img is None:
        raise RuntimeError(f"Could not read image: {orig_path}")

    overlay = img.copy()
    thickness = 2

    for d in dets:
        x1, y1, x2, y2 = map(int, [d["x1"], d["y1"], d["x2"], d["y2"]])
        label_name = model.names[d["cls"]]
        color = CLASS_COLORS.get(label_name, (255, 255, 255))
        conf_pct = int(round(d["conf"] * 100))

        # Confidence-based fill
        if 25 <= conf_pct < 70:
            sub_overlay = overlay.copy()
            cv2.rectangle(sub_overlay, (x1, y1), (x2, y2), color, -1)
            cv2.addWeighted(sub_overlay, 0.3, overlay, 0.7, 0, overlay)
        elif conf_pct < 25:
            cv2.rectangle(overlay, (x1, y1), (x2, y2), color, -1)

        cv2.rectangle(overlay, (x1, y1), (x2, y2), color, thickness)

    img = cv2.addWeighted(overlay, box_opacity, img, 1 - box_opacity, 0)

    # === Labels
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
            cv2.putText(img, text, (x1, max(0, y1 - 5)), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2)

    cv2.imwrite(output_path, img)

@app.route('/runs/<path:filename>')
def serve_runs(filename):
    # Serve files from the "runs" folder
    response = make_response(send_from_directory(RUNS_DIR, filename))
    response.headers["Access-Control-Allow-Origin"] = "*"
    response.headers["Access-Control-Allow-Methods"] = "GET, OPTIONS"
    response.headers["Access-Control-Allow-Headers"] = "Content-Type"
    response.headers["Cache-Control"] = "no-cache, no-store, must-revalidate"
    return response


@app.route('/')
def home():
    return "✅ Flask YOLO detection API with dynamic accuracy is running."

# === Analyze Endpoint ===
@app.route('/analyze', methods=['POST'])
def analyze_image():
    try:
        threshold = float(request.form.get('threshold', 0.25))
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
            results = model(orig_path, conf=threshold)[0]
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

# === Redetect Endpoint ===
@app.route('/redetect', methods=['POST'])
def redetect():
    try:
        data = request.get_json()
        folder = data.get('folder')
        threshold = float(data.get('threshold', 0.25))
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
            dets_img = f"{orig_img.replace('_orig_', '_dets_').rsplit('.',1)[0]}.json"
            result_img = orig_img.replace("_orig", "_result")
            orig_path = os.path.join(run_folder, orig_img)
            result_path = os.path.join(run_folder, result_img)
            dets_path = os.path.join(run_folder, dets_img)

            if os.path.exists(result_path):
                os.remove(result_path)

            results = model(orig_path, conf=threshold)[0]
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

        mean_accuracy = round(sum([r['accuracy'] for r in detection_results]) / len(detection_results), 2) if detection_results else 0.0

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

# === Rerender Endpoint ===
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
            dets_img = f"{orig_img.replace('_orig_', '_dets_').rsplit('.',1)[0]}.json"
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

# === Cleanup ===
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

if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5000, debug=True)
