#!/bin/bash
set -e

echo "=== Installation des dépendances Tesla Dashcam ==="

# Dépendances système
echo "[1/3] Installation de ffmpeg (utilisé par compile.py)..."
apt-get update -qq
apt-get install -y ffmpeg

# Dépendances Python (protobuf pour le compilateur proto si besoin)
echo "[2/3] Installation de protobuf-compiler..."
apt-get install -y protobuf-compiler

# Dépendances pip
echo "[3/3] Installation des packages Python..."
pip3 install --break-system-packages --upgrade \
    protobuf \
    opencv-python \
    numpy \
    Pillow

echo ""
echo "=== Installation terminée ==="
echo "Packages installés :"
echo "  - ffmpeg          (compilation vidéo - compile.py)"
echo "  - protobuf        (décodage SEI metadata - sei_extractor.py, dashcam_pb2.py)"
echo "  - opencv-python   (traitement vidéo - export.py)"
echo "  - numpy           (manipulation d'images - export.py)"
echo "  - Pillow          (téléchargement tuiles carte - export.py)"
echo "  - protoc          (compilation .proto si nécessaire)"

