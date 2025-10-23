from flask import Flask, request, jsonify, send_from_directory
from flask_cors import CORS
from ultralytics import YOLO
from werkzeug.utils import secure_filename
from datetime import datetime
import os
import shutil
import pandas as pd

app = Flask(__name__)
CORS(app)

# === PATH CONFIGURATION ===
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
RUNS_DIR = os.path.join(SCRIPT_DIR, "runs")
MODEL_PATH = os.path.join(SCRIPT_DIR, "my_model.pt")
CSV_PATH = os.path.join(SCRIPT_DIR, "model_metrics.csv")  # 📊 your training CSV file

os.makedirs(RUNS_DIR, exist_ok=True)

# ✅ Load YOLO model
print(f"📦 Loading YOLO model from: {MODEL_PATH}")
model = YOLO(MODEL_PATH)

# === Helper: Get accuracy from training CSV ===
def get_model_accuracy():
    try:
        df = pd.read_csv(CSV_PATH)
        last_row = df.iloc[-1]
        acc = float(last_row["metrics/mAP50(B)"]) * 100  # convert to percentage
        return round(acc, 2)
    except Exception as e:
        print("⚠️ Could not read accuracy from CSV:", e)
        return None

# === Serve result images ===
@app.route('/runs/<path:filename>')
def serve_runs(filename):
    return send_from_directory(RUNS_DIR, filename)

@app.route('/')
def home():
    return "✅ Flask YOLO detection API is running and serving from /runs/"

# === Analyze Endpoint ===
@app.route('/analyze', methods=['POST'])
def analyze_image():
    try:
        threshold = float(request.form.get('threshold', 0.25))
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
            orig_path = os.path.join(run_folder, orig_filename)
            result_path = os.path.join(run_folder, result_filename)

            file.save(orig_path)
            results = model(orig_path, conf=threshold, save=True, project=SCRIPT_DIR, name="yolo_output")
            yolo_output_file = os.path.join(results[0].save_dir, orig_filename)

            if os.path.exists(yolo_output_file):
                shutil.move(yolo_output_file, result_path)

            object_summary = {}
            for det in results[0].boxes:
                cls_id = int(det.cls.item())
                label = model.names[cls_id]
                object_summary[label] = object_summary.get(label, 0) + 1
                total_object_summary[label] = total_object_summary.get(label, 0) + 1

            detection_results.append({
                'original_image': f"runs/save_{timestamp}/{orig_filename}",
                'result_image': f"runs/save_{timestamp}/{result_filename}",
                'summary': object_summary,
                'total_items': sum(object_summary.values())
            })

            shutil.rmtree(str(results[0].save_dir), ignore_errors=True)

        accuracy = get_model_accuracy()

        return jsonify({
            'message': '✅ Detection successful',
            'results': detection_results,
            'total_summary': total_object_summary,
            'folder': f"save_{timestamp}",
            'accuracy': accuracy
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
        run_folder = os.path.join(RUNS_DIR, folder)

        if not os.path.exists(run_folder):
            return jsonify({'error': 'Folder not found'}), 404

        detection_results = []
        total_object_summary = {}

        orig_images = [f for f in os.listdir(run_folder) if "_orig" in f]
        for orig_img in orig_images:
            orig_path = os.path.join(run_folder, orig_img)
            result_img = orig_img.replace("_orig", "_result")
            result_path = os.path.join(run_folder, result_img)

            if os.path.exists(result_path):
                os.remove(result_path)

            results = model(orig_path, conf=threshold, save=True, project=SCRIPT_DIR, name="yolo_output")
            yolo_output_file = os.path.join(results[0].save_dir, orig_img)

            if os.path.exists(yolo_output_file):
                shutil.move(yolo_output_file, result_path)

            object_summary = {}
            for det in results[0].boxes:
                cls_id = int(det.cls.item())
                label = model.names[cls_id]
                object_summary[label] = object_summary.get(label, 0) + 1
                total_object_summary[label] = total_object_summary.get(label, 0) + 1

            detection_results.append({
                'original_image': f"runs/{folder}/{orig_img}",
                'result_image': f"runs/{folder}/{result_img}",
                'summary': object_summary,
                'total_items': sum(object_summary.values())
            })

            shutil.rmtree(str(results[0].save_dir), ignore_errors=True)

        accuracy = get_model_accuracy()

        return jsonify({
            'message': '✅ Redetection complete',
            'results': detection_results,
            'total_summary': total_object_summary,
            'accuracy': accuracy
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


