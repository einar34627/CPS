# file: setup_face_recognition.py
#!/usr/bin/env python3
"""
Setup script for Face Recognition System
Run this first to install dependencies and set up the system
"""

import os
import sys
import subprocess
import platform

def check_python_version():
    """Check Python version"""
    print("Checking Python version...")
    if sys.version_info < (3, 6):
        print(f"ERROR: Python 3.6+ required. You have {sys.version}")
        return False
    print(f"✓ Python {sys.version_info.major}.{sys.version_info.minor}.{sys.version_info.micro}")
    return True

def install_dependencies():
    """Install required Python packages"""
    print("\nInstalling dependencies...")
    
    packages = [
        "opencv-python",
        "opencv-contrib-python",
        "numpy",
        "pillow"
    ]
    
    try:
        for package in packages:
            print(f"Installing {package}...")
            subprocess.check_call([sys.executable, "-m", "pip", "install", package])
        print("✓ All dependencies installed successfully!")
        return True
    except Exception as e:
        print(f"ERROR: Failed to install dependencies: {e}")
        return False

def create_directories():
    """Create necessary directories"""
    print("\nCreating directories...")
    
    dirs = [
        "dataset",
        "models",
        "training_images",
        "exports"
    ]
    
    for dir_name in dirs:
        os.makedirs(dir_name, exist_ok=True)
        print(f"✓ Created: {dir_name}/")
    
    return True

def download_cascade_files():
    """Download Haar cascade files if missing"""
    print("\nChecking for cascade files...")
    
    import cv2
    cascade_path = cv2.data.haarcascades
    
    if not os.path.exists(cascade_path):
        print("WARNING: Cascade files not found!")
        print("Download from: https://github.com/opencv/opencv/tree/master/data/haarcascades")
        print("Place in: C:/opencv/data/haarcascades/ (Windows) or similar")
        return False
    
    print(f"✓ Cascade files found at: {cascade_path}")
    return True

def create_sample_dataset():
    """Create a sample dataset structure"""
    print("\nCreating sample dataset structure...")
    
    sample_structure = """
dataset/
├── John_Doe/
│   ├── john_001.jpg
│   ├── john_002.jpg
│   └── john_003.jpg
├── Jane_Smith/
│   ├── jane_001.jpg
│   └── jane_002.jpg
└── README.txt
    """
    
    readme_path = os.path.join("dataset", "README.txt")
    with open(readme_path, "w") as f:
        f.write("FACE RECOGNITION DATASET\n")
        f.write("=" * 40 + "\n")
        f.write("Place face images in folders named after each person.\n")
        f.write("Each folder should contain 10-20 images of that person.\n")
        f.write("Images should show the face clearly in different conditions.\n")
        f.write("\nRecommended image format: .jpg, 200x200 pixels, grayscale\n")
    
    print("✓ Sample structure created")
    print(sample_structure)
    return True

def create_batch_files():
    """Create batch files for easy execution"""
    print("\nCreating batch files...")
    
    # Windows batch file
    if platform.system() == "Windows":
        batch_content = """@echo off
echo ========================================
echo FACE RECOGNITION SYSTEM
echo ========================================
echo.
echo Options:
echo 1. Interactive Menu
echo 2. Train Model
echo 3. Run Recognition
echo 4. Register New Person
echo.
set /p choice="Enter choice (1-4): "

if "%choice%"=="1" (
    python face_recognition_lbph.py --interactive
) else if "%choice%"=="2" (
    python face_recognition_lbph.py --train
) else if "%choice%"=="3" (
    python face_recognition_lbph.py --recognize
) else if "%choice%"=="4" (
    set /p name="Enter person's name: "
    python face_recognition_lbph.py --register "%name%"
) else (
    echo Invalid choice!
)

pause
"""
        
        with open("face_recognition.bat", "w") as f:
            f.write(batch_content)
        print("✓ Created: face_recognition.bat")
    
    # Linux/Mac bash script
    bash_content = """#!/bin/bash
echo "========================================"
echo "FACE RECOGNITION SYSTEM"
echo "========================================"
echo ""
echo "Options:"
echo "1. Interactive Menu"
echo "2. Train Model"
echo "3. Run Recognition"
echo "4. Register New Person"
echo ""
read -p "Enter choice (1-4): " choice

case $choice in
    1)
        python3 face_recognition_lbph.py --interactive
        ;;
    2)
        python3 face_recognition_lbph.py --train
        ;;
    3)
        python3 face_recognition_lbph.py --recognize
        ;;
    4)
        read -p "Enter person's name: " name
        python3 face_recognition_lbph.py --register "$name"
        ;;
    *)
        echo "Invalid choice!"
        ;;
esac
"""
    
    with open("face_recognition.sh", "w") as f:
        f.write(bash_content)
    
    # Make executable on Unix-like systems
    if platform.system() != "Windows":
        os.chmod("face_recognition.sh", 0o755)
    
    print("✓ Created: face_recognition.sh")
    return True

def print_usage_instructions():
    """Print usage instructions"""
    print("\n" + "="*60)
    print("SETUP COMPLETE!")
    print("="*60)
    print("\nUSAGE INSTRUCTIONS:")
    print("-" * 40)
    print("1. INTERACTIVE MENU (Recommended):")
    print("   python face_recognition_lbph.py --interactive")
    print("\n2. REGISTER A NEW PERSON:")
    print("   python face_recognition_lbph.py --register \"Your Name\"")
    print("\n3. TRAIN MODEL:")
    print("   python face_recognition_lbph.py --train")
    print("\n4. RUN RECOGNITION:")
    print("   python face_recognition_lbph.py --recognize")
    print("\n5. QUICK SETUP (All-in-one):")
    print("   python face_recognition_lbph.py")
    print("\n" + "="*60)
    print("NEXT STEPS:")
    print("-" * 40)
    print("1. Run the interactive menu to get started")
    print("2. Register yourself using option 1")
    print("3. Capture 15-20 images of your face")
    print("4. Train the model")
    print("5. Start recognition!")
    print("="*60)

def main():
    """Main setup function"""
    print("="*60)
    print("FACE RECOGNITION SYSTEM SETUP")
    print("="*60)
    
    steps = [
        ("Python Version Check", check_python_version),
        ("Install Dependencies", install_dependencies),
        ("Create Directories", create_directories),
        ("Check Cascade Files", download_cascade_files),
        ("Create Sample Dataset", create_sample_dataset),
        ("Create Batch Files", create_batch_files),
    ]
    
    success = True
    for step_name, step_func in steps:
        print(f"\n[{step_name}]")
        if not step_func():
            success = False
            print(f"⚠ {step_name} failed!")
            break
    
    if success:
        print_usage_instructions()
        print("\nSetup completed successfully!")
    else:
        print("\n⚠ Setup completed with some errors.")
        print("Please fix the issues above and try again.")
    
    return success

if __name__ == "__main__":
    try:
        if main():
            sys.exit(0)
        else:
            sys.exit(1)
    except KeyboardInterrupt:
        print("\n\nSetup cancelled by user.")
        sys.exit(1)