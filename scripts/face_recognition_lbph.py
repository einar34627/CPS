# file: setup_face_recognition.py
#!/usr/bin/env python3
"""
Face Recognition using LBPH (Local Binary Patterns Histograms)
For Community Policing and Surveillance System
"""

import cv2
import numpy as np
import os
import json
import sys
import argparse
from datetime import datetime
import pickle

class FaceRecognizerLBPH:
    def __init__(self, base_dir='.'):
        """
        Initialize the LBPH face recognizer
        
        Args:
            base_dir (str): Base directory for storing models and faces
        """
        self.base_dir = base_dir
        self.faces_dir = os.path.join(base_dir, 'faces')
        self.training_dir = os.path.join(self.faces_dir, 'training')
        self.models_dir = os.path.join(self.faces_dir, 'models')
        self.temp_dir = os.path.join(self.faces_dir, 'temp')
        
        # Create directories if they don't exist
        os.makedirs(self.training_dir, exist_ok=True)
        os.makedirs(self.models_dir, exist_ok=True)
        os.makedirs(self.temp_dir, exist_ok=True)
        
        # Initialize face recognizer
        self.face_recognizer = cv2.face.LBPHFaceRecognizer_create()
        self.face_cascade = cv2.CascadeClassifier(
            cv2.data.haarcascades + 'haarcascade_frontalface_default.xml'
        )
        
        # Load existing model if available
        self.model_file = os.path.join(self.models_dir, 'face_model.yml')
        self.labels_file = os.path.join(self.models_dir, 'labels.pkl')
        
        self.labels = {}
        self.next_label_id = 0
        
        self.load_model()
    
    def load_model(self):
        """Load trained model and labels"""
        try:
            if os.path.exists(self.model_file):
                self.face_recognizer.read(self.model_file)
                print(f"Model loaded from {self.model_file}")
            
            if os.path.exists(self.labels_file):
                with open(self.labels_file, 'rb') as f:
                    self.labels = pickle.load(f)
                if self.labels:
                    self.next_label_id = max(self.labels.keys()) + 1
                else:
                    self.next_label_id = 0
                print(f"Labels loaded: {len(self.labels)} faces")
        except Exception as e:
            print(f"Error loading model: {e}")
            self.labels = {}
            self.next_label_id = 0
    
    def save_model(self):
        """Save trained model and labels"""
        try:
            self.face_recognizer.write(self.model_file)
            with open(self.labels_file, 'wb') as f:
                pickle.dump(self.labels, f)
            print(f"Model saved to {self.model_file}")
            print(f"Labels saved: {self.labels}")
            return True
        except Exception as e:
            print(f"Error saving model: {e}")
            return False
    
    def detect_faces(self, image):
        """
        Detect faces in an image
        
        Args:
            image: numpy array image
            
        Returns:
            list: List of face bounding boxes (x, y, w, h)
        """
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
        faces = self.face_cascade.detectMultiScale(
            gray,
            scaleFactor=1.1,
            minNeighbors=5,
            minSize=(30, 30)
        )
        return faces
    
    def preprocess_face(self, face_image):
        """
        Preprocess face image for recognition
        
        Args:
            face_image: numpy array of face region
            
        Returns:
            numpy array: Preprocessed grayscale face image
        """
        # Convert to grayscale
        gray = cv2.cvtColor(face_image, cv2.COLOR_BGR2GRAY)
        
        # Resize to standard size
        gray = cv2.resize(gray, (100, 100))
        
        # Apply histogram equalization for better contrast
        gray = cv2.equalizeHist(gray)
        
        return gray
    
    def train_from_directory(self):
        """
        Train the face recognizer from the training directory
        
        Returns:
            dict: Training results
        """
        print("Starting training from directory...")
        
        # Scan training directory for images
        training_data = {}
        
        # Get all image files
        image_extensions = ['.jpg', '.jpeg', '.png', '.bmp']
        for filename in os.listdir(self.training_dir):
            if any(filename.lower().endswith(ext) for ext in image_extensions):
                # Parse name from filename (format: face_name_timestamp_id.ext)
                parts = filename.split('_')
                if len(parts) >= 2:
                    name = parts[1]  # Get the name part
                    
                    if name not in training_data:
                        training_data[name] = []
                    
                    image_path = os.path.join(self.training_dir, filename)
                    training_data[name].append(image_path)
        
        if not training_data:
            return {
                'success': False,
                'error': 'No training images found',
                'faces_trained': 0
            }
        
        # Prepare training data
        faces = []
        labels = []
        label_ids = {}
        current_label_id = 0
        
        for name, image_paths in training_data.items():
            if name not in label_ids:
                label_ids[name] = current_label_id
                current_label_id += 1
            
            for image_path in image_paths:
                try:
                    # Load image
                    image = cv2.imread(image_path)
                    if image is None:
                        print(f"Failed to load image: {image_path}")
                        continue
                    
                    # Detect face
                    face_rects = self.detect_faces(image)
                    
                    if len(face_rects) == 0:
                        print(f"No face detected in {image_path}")
                        continue
                    
                    # Use the first face found
                    x, y, w, h = face_rects[0]
                    face_roi = image[y:y+h, x:x+w]
                    
                    # Preprocess face
                    processed_face = self.preprocess_face(face_roi)
                    
                    # Add to training data
                    faces.append(processed_face)
                    labels.append(label_ids[name])
                    
                    print(f"Added training sample: {name} from {image_path}")
                    
                except Exception as e:
                    print(f"Error processing {image_path}: {e}")
        
        if not faces:
            return {
                'success': False,
                'error': 'No valid faces found for training',
                'faces_trained': 0
            }
        
        # Train the recognizer
        try:
            self.face_recognizer.train(faces, np.array(labels))
            
            # Update labels dictionary
            self.labels = {v: k for k, v in label_ids.items()}
            self.next_label_id = current_label_id
            
            # Save the model
            self.save_model()
            
            return {
                'success': True,
                'message': f'Model trained with {len(faces)} faces from {len(label_ids)} people',
                'faces_trained': len(faces),
                'people_count': len(label_ids)
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': f'Training failed: {str(e)}',
                'faces_trained': 0
            }
    
    def train_single_face(self, image_path, name, role=None):
        """
        Train a single face
        
        Args:
            image_path (str): Path to face image
            name (str): Name of the person
            role (str, optional): Role/position of the person
            
        Returns:
            dict: Training results
        """
        try:
            image = cv2.imread(image_path)
            if image is None:
                return {
                    'success': False,
                    'error': f'Cannot read image: {image_path}',
                }
            face_rects = self.detect_faces(image)
            if len(face_rects) == 0:
                return {
                    'success': False,
                    'error': 'No face detected in provided image',
                }
            x, y, w, h = face_rects[0]
            face_roi = image[y:y+h, x:x+w]
            processed_face = self.preprocess_face(face_roi)
            existing_id = None
            for lid, n in self.labels.items():
                if n == name:
                    existing_id = lid
                    break
            if existing_id is None:
                label_id = self.next_label_id
                self.labels[label_id] = name
                self.next_label_id += 1
            else:
                label_id = existing_id
            try:
                self.face_recognizer.update([processed_face], np.array([label_id]))
            except Exception:
                self.face_recognizer.train([processed_face], np.array([label_id]))
            self.save_model()
            try:
                ts = datetime.now().strftime('%Y%m%d_%H%M%S')
                out_name = f'face_{name}_{ts}_{label_id}.png'
                out_path = os.path.join(self.training_dir, out_name)
                cv2.imwrite(out_path, face_roi)
            except Exception:
                pass
            return {
                'success': True,
                'message': f'Trained face for {name}',
                'label_id': int(label_id),
                'face_box': {'x': int(x), 'y': int(y), 'w': int(w), 'h': int(h)}
            }
        except Exception as e:
            return {
                'success': False,
                'error': f'Training error: {str(e)}',
            }
