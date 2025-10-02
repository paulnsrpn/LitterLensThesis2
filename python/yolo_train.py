from ultralytics import YOLO
import pandas as pd
import os

# ===============================
# 1. Load Model
# ===============================
model = YOLO("yolov8s.pt")

# ===============================
# 2. Train Model
# ===============================
results = model.train(
    data=r"C:\Users\Eman\Desktop\THESIS 2\THESIS 2\my_dataset\data.yaml", 
    epochs=10,
    imgsz=640,
    batch=16,
    name="yolov8_Thesis1_optimized",
    device='cpu',
    
    # Generate plots like confusion matrix & PR curve
    plots=True,
    
    # Hyperparameter tuning
    optimizer='AdamW',
    lr0=0.001,
    lrf=0.0001,
    patience=50,
    
    # Data augmentation
    close_mosaic=20,
    hsv_s=0.8,
    scale=0.8,
)

# ===============================
# 3. Extract Final Metrics
# ===============================
# Path to results.csv
csv_path = os.path.join("runs", "detect", "yolov8_Thesis1_optimized", "results.csv")

if os.path.exists(csv_path):
    df = pd.read_csv(csv_path)

    # Get the last epoch row
    final_metrics = df.iloc[-1]

    # Extract YOLOv8 metrics
    mAP50_95 = final_metrics["metrics/mAP50-95(B)"]
    mAP50 = final_metrics["metrics/mAP50(B)"]
    precision = final_metrics["metrics/precision(B)"]
    recall = final_metrics["metrics/recall(B)"]

    # ===============================
    # 4. Print Results
    # ===============================
    print("\n--- Training Complete ---")
    print(f"Final mAP@.5:.95: {mAP50_95:.4f}")
    print(f"Final mAP@.50: {mAP50:.4f}")
    print(f"Final Precision: {precision:.4f}")
    print(f"Final Recall: {recall:.4f}")
    print(f"Check '{os.path.dirname(csv_path)}' for plots and saved weights.")

else:
    print("⚠️ results.csv not found. Training may have failed or not finished.")
