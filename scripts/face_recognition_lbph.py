import os
import sys
import time
import cv2
import numpy as np
import argparse
def ensure_lbph():
    try:
        return cv2.face.LBPHFaceRecognizer_create()
    except Exception:
        print("LBPHFaceRecognizer not available. Install opencv-contrib-python.")
        sys.exit(1)
def get_cascade():
    p = os.path.join(cv2.data.haarcascades, "haarcascade_frontalface_default.xml")
    if not os.path.exists(p):
        print("Haar cascade not found.")
        sys.exit(1)
    return cv2.CascadeClassifier(p)
def load_dataset(root):
    if not os.path.isdir(root):
        print("Dataset folder missing:", root)
        sys.exit(1)
    names = [d for d in os.listdir(root) if os.path.isdir(os.path.join(root, d))]
    if not names:
        print("Dataset has no person folders.")
        sys.exit(1)
    names.sort()
    name_to_id = {n: i for i, n in enumerate(names)}
    faces = []
    labels = []
    cas = get_cascade()
    for n in names:
        d = os.path.join(root, n)
        files = [f for f in os.listdir(d) if f.lower().endswith((".jpg", ".jpeg", ".png"))]
        for f in files:
            p = os.path.join(d, f)
            img = cv2.imread(p)
            if img is None:
                continue
            g = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
            rects = cas.detectMultiScale(g, 1.15, 5)
            if len(rects) == 0:
                continue
            r = max(rects, key=lambda x: x[2]*x[3])
            x, y, w, h = r
            roi = g[y:y+h, x:x+w]
            roi = cv2.resize(roi, (200, 200))
            faces.append(roi)
            labels.append(name_to_id[n])
    if not faces:
        print("No faces detected in dataset.")
        sys.exit(1)
    return faces, np.array(labels, dtype=np.int32), {v: k for k, v in name_to_id.items()}
def train_and_save(dataset_dir, model_path):
    r = ensure_lbph()
    faces, labels, id_to_name = load_dataset(dataset_dir)
    r.train(faces, labels)
    r.write(model_path)
    return r, id_to_name
def run_webcam(recognizer, id_to_name):
    cas = get_cascade()
    cap = cv2.VideoCapture(0, cv2.CAP_DSHOW)
    if not cap or not cap.isOpened():
        print("Camera not available.")
        sys.exit(1)
    cap.set(cv2.CAP_PROP_FPS, 30)
    last = time.time()
    while True:
        ok, frame = cap.read()
        if not ok:
            print("Failed to read frame.")
            break
        g = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        rects = cas.detectMultiScale(g, 1.15, 5)
        for (x, y, w, h) in rects:
            roi = g[y:y+h, x:x+w]
            roi = cv2.resize(roi, (200, 200))
            label, conf = recognizer.predict(roi)
            name = id_to_name.get(label, "Unknown")
            pct = max(0, min(100, int(100 - conf)))
            cv2.rectangle(frame, (x, y), (x+w, y+h), (37, 99, 235), 2)
            t = f"{name} {pct}%"
            cv2.putText(frame, t, (x, y-10), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2, cv2.LINE_AA)
        now = time.time()
        dt = now - last
        if dt < 1/30:
            time.sleep(max(0, 1/30 - dt))
        last = time.time()
        cv2.imshow("LBPH Face Recognition", frame)
        if cv2.waitKey(1) & 0xFF == ord('q'):
            break
    cap.release()
    cv2.destroyAllWindows()
def main():
    base = os.path.dirname(os.path.abspath(__file__))
    parser = argparse.ArgumentParser()
    parser.add_argument('--train-only', action='store_true')
    parser.add_argument('--dataset', default=os.path.join(base, "dataset"))
    parser.add_argument('--model', default=os.path.join(base, "face_recognizer.yml"))
    args = parser.parse_args()
    r, id_to_name = train_and_save(args.dataset, args.model)
    if args.train_only:
        print("TRAINED " + str(len(id_to_name)))
        return
    run_webcam(r, id_to_name)
if __name__ == "__main__":
    main()
