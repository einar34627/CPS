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
def get_cascades():
    base = cv2.data.haarcascades
    pf = os.path.join(base, "haarcascade_profileface.xml")
    ef = os.path.join(base, "haarcascade_eye.xml")
    if not os.path.exists(pf) or not os.path.exists(ef):
        print("Required cascades missing.")
        sys.exit(1)
    return {
        'frontal': cv2.CascadeClassifier(os.path.join(base, "haarcascade_frontalface_default.xml")),
        'profile': cv2.CascadeClassifier(pf),
        'eye': cv2.CascadeClassifier(ef)
    }
def normalize_gray(g):
    m = float(np.mean(g))
    if m < 90 or m > 160:
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
        g = clahe.apply(g)
    return g
def blur_metric(g):
    return float(cv2.Laplacian(g, cv2.CV_64F).var())
def detect_faces_fallback(g, cascades):
    r = cascades['frontal'].detectMultiScale(g, 1.15, 5)
    if len(r) == 0:
        r = cascades['profile'].detectMultiScale(g, 1.15, 5)
    if len(r) == 0:
        eyes = cascades['eye'].detectMultiScale(g, 1.15, 5)
        if len(eyes) >= 2:
            eyes = sorted(eyes, key=lambda e: e[0])
            exl, eyl, ewl, ehl = eyes[0]
            exr, eyr, ewr, ehr = eyes[-1]
            clx = int(exl + ewl * 0.5)
            cly = int(eyl + ehl * 0.5)
            crx = int(exr + ewr * 0.5)
            cry = int(eyr + ehr * 0.5)
            dx = crx - clx
            dy = cry - cly
            dist = int(np.sqrt(dx*dx + dy*dy))
            cx = (clx + crx) // 2
            cy = (cly + cry) // 2
            w = int(dist * 2.2)
            h = int(dist * 2.7)
            x = max(0, cx - w // 2)
            y = max(0, cy - int(h * 0.35))
            x2 = min(g.shape[1], x + w)
            y2 = min(g.shape[0], y + h)
            r = np.array([[x, y, x2 - x, y2 - y]])
        else:
            r = []
    return r
def align_face(g, rect, eye_cascade):
    x, y, w, h = rect
    roi = g[y:y+h, x:x+w]
    eyes = eye_cascade.detectMultiScale(roi, 1.15, 5)
    left = None
    right = None
    for (ex, ey, ew, eh) in eyes:
        cx = ex + ew * 0.5
        cy = ey + eh * 0.5
        if left is None or cx < left[0]:
            left = (cx, cy)
        if right is None or cx > right[0]:
            right = (cx, cy)
    if left and right:
        dx = right[0] - left[0]
        dy = right[1] - left[1]
        angle = np.degrees(np.arctan2(dy, dx))
        M = cv2.getRotationMatrix2D((w * 0.5, h * 0.5), angle, 1.0)
        roi = cv2.warpAffine(roi, M, (w, h), flags=cv2.INTER_LINEAR)
    return roi
def dataset_diagnostics(root):
    stats = {'persons': [], 'counts': {}, 'total': 0}
    if not os.path.isdir(root):
        return stats
    names = [d for d in os.listdir(root) if os.path.isdir(os.path.join(root, d))]
    names.sort()
    stats['persons'] = names
    for n in names:
        d = os.path.join(root, n)
        files = [f for f in os.listdir(d) if f.lower().endswith((".jpg", ".jpeg", ".png", ".webp"))]
        stats['counts'][n] = len(files)
        stats['total'] += len(files)
    return stats
def save_training_image(person, img, base_dir):
    if not person:
        person = "unknown"
    d = os.path.join(base_dir, person)
    if not os.path.isdir(d):
        os.makedirs(d, exist_ok=True)
    name = "train_" + time.strftime("%Y%m%d_%H%M%S") + "_" + bin2hex(4) + ".jpg"
    path = os.path.join(d, name)
    cv2.imwrite(path, img)
    return path
def bin2hex(n):
    return os.urandom(n).hex()
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
    cascades = get_cascades()
    for n in names:
        d = os.path.join(root, n)
        files = [f for f in os.listdir(d) if f.lower().endswith((".jpg", ".jpeg", ".png", ".webp"))]
        for f in files:
            p = os.path.join(d, f)
            img = cv2.imread(p)
            if img is None:
                continue
            g = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
            g = normalize_gray(g)
            rects = detect_faces_fallback(g, cascades)
            if len(rects) == 0:
                continue
            r = max(rects, key=lambda x: x[2]*x[3])
            x, y, w, h = r
            roi = align_face(g, (x, y, w, h), cascades['eye'])
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
def run_webcam(recognizer, id_to_name, dataset_dir, person):
    cascades = get_cascades()
    cap = cv2.VideoCapture(0, cv2.CAP_DSHOW)
    if not cap or not cap.isOpened():
        print("Camera not available.")
        sys.exit(1)
    cap.set(cv2.CAP_PROP_FPS, 30)
    cam_ok = True
    fps_est = 0.0
    t0 = time.time()
    frames = 0
    ds = dataset_diagnostics(dataset_dir)
    target_person = person or (ds['persons'][0] if ds['persons'] else '')
    train_count = ds['counts'].get(target_person, 0)
    train_target = 15
    name_to_id = {v: k for k, v in id_to_name.items()}
    while True:
        ok, frame = cap.read()
        if not ok:
            cam_ok = False
            break
        frames += 1
        g = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        g = normalize_gray(g)
        bmean = float(np.mean(g))
        blur = blur_metric(g)
        rects = detect_faces_fallback(g, cascades)
        faces_detected = len(rects)
        recognized = False
        conf_disp = 0
        for (x, y, w, h) in rects:
            roi = align_face(g, (x, y, w, h), cascades['eye'])
            roi = cv2.resize(roi, (200, 200))
            label, dist = recognizer.predict(roi)
            name = id_to_name.get(label, "Unknown")
            conf_disp = max(0, min(100, int(100 - dist)))
            th = 60
            if blur < 80:
                th += 15
            if bmean < 70 or bmean > 180:
                th += 10
            recognized = dist < th
            color = (0, 200, 0) if recognized else (37, 99, 235)
            cv2.rectangle(frame, (x, y), (x+w, y+h), color, 2)
            cv2.putText(frame, f"{name} {conf_disp}%", (x, y-10), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2, cv2.LINE_AA)
        dt = time.time() - t0
        if dt > 0:
            fps_est = frames / dt
        hgt, wdt = frame.shape[:2]
        line1 = f"[{'√' if cam_ok else ' '}] Camera: {wdt}x{hgt} @ {int(fps_est)} FPS"
        line2 = f"[{'√' if faces_detected>0 else ' '}] Faces detected: {faces_detected}"
        line3 = f"[{'√' if recognized else ' '}] Face recognized: {'Yes' if recognized else 'No'} (confidence: {conf_disp})"
        line4 = f"Training data: {train_count} images for '{target_person or '—'}'"
        y0 = 24
        for i, t in enumerate([line1, line2, line3, line4]):
            cv2.putText(frame, t, (12, y0 + i*22), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 0), 2, cv2.LINE_AA)
        cv2.imshow("LBPH Diagnostic", frame)
        k = cv2.waitKey(1) & 0xFF
        if k == ord('q'):
            break
        if k == ord('t'):
            if faces_detected > 0:
                x, y, w, h = rects[0]
                roi = align_face(g, (x, y, w, h), cascades['eye'])
                roi = cv2.resize(roi, (200, 200))
                person_name = target_person or 'unknown'
                path = save_training_image(person_name, roi, dataset_dir)
                train_count += 1
                label_to_update = name_to_id.get(person_name, 0)
                try:
                    recognizer.update([roi], np.array([label_to_update], dtype=np.int32))
                except Exception:
                    pass
                cv2.putText(frame, f"Training image captured: {train_count}/{train_target}", (12, y0 + 5*22), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 255, 0), 2, cv2.LINE_AA)
        time.sleep(0.001)
    cap.release()
    cv2.destroyAllWindows()
def main():
    base = os.path.dirname(os.path.abspath(__file__))
    parser = argparse.ArgumentParser()
    parser.add_argument('--train-only', action='store_true')
    parser.add_argument('--dataset', default=os.path.join(base, "dataset"))
    parser.add_argument('--model', default=os.path.join(base, "face_recognizer.yml"))
    parser.add_argument('--person', default='')
    args = parser.parse_args()
    r, id_to_name = train_and_save(args.dataset, args.model)
    if args.train_only:
        print("TRAINED " + str(len(id_to_name)))
        return
    run_webcam(r, id_to_name, args.dataset, args.person)
if __name__ == "__main__":
    main()
