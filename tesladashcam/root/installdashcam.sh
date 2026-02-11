#!/bin/bash
set -e

echo "=== Installation des dépendances Tesla Dashcam ==="

# Dépendances système
echo "[1/4] Installation de ffmpeg (utilisé par compile.py)..."
apt-get update -qq
apt-get install -y ffmpeg

# Dépendances Python (protobuf pour le compilateur proto si besoin)
echo "[2/4] Installation de protobuf-compiler..."
apt-get install -y protobuf-compiler

# Dépendances pip
echo "[3/4] Installation des packages Python..."
pip3 install --break-system-packages --upgrade \
    protobuf \
    opencv-python \
    numpy \
    Pillow

# Création du répertoire uploads et permissions
echo "[4/4] Création du répertoire uploads et permissions..."
mkdir -p /var/www/html/uploads
chown -R www-data:www-data /var/www/html/uploads
chmod -R 755 /var/www/html/uploads

chown www-data:www-data /var/www/html/cgi-bin/*.py
chmod 755 /var/www/html/cgi-bin/*.py

# Création du répertoire uploads et permissions
echo "[4/4] Création du répertoire uploads et permissions..."
mkdir -p /var/www/html/uploads
chown www-data:www-data /var/www/html/uploads
chmod 755 /var/www/html/uploads

# Permissions des scripts CGI
chown www-data:www-data /var/www/html/cgi-bin/*.py 2>/dev/null || true
chmod 755 /var/www/html/cgi-bin/*.py 2>/dev/null || true

echo ""
echo "=== Installation terminée ==="
echo "Packages installés :"
echo "  - ffmpeg          (compilation vidéo - compile.py)"
echo "  - protobuf        (décodage SEI metadata - sei_extractor.py, dashcam_pb2.py)"
echo "  - opencv-python   (traitement vidéo - export.py)"
echo "  - numpy           (manipulation d'images - export.py)"
echo "  - Pillow          (téléchargement tuiles carte - export.py)"
echo "  - protoc          (compilation .proto si nécessaire)"
echo ""
echo "Répertoires configurés :"
echo "  - /var/www/html/uploads  (www-data:www-data, 755)"
echo "  - /var/www/html/cgi-bin/*.py (www-data:www-data, 755)"

