#!/usr/bin/env python3
import cv2
import json
import numpy as np
import os
import urllib.request
import math
from io import BytesIO
from PIL import Image

UPLOAD_DIR = '/var/www/html/uploads/'
GPS_JSON = '/var/www/html/cgi-bin/videosgps.json'
OUTPUT_FILE = UPLOAD_DIR + 'export.mp4'

print("📋 Chargement des données GPS...")
with open(GPS_JSON, 'r') as f:
    data = json.load(f)
    gps_points = data['gps']

# Filtrer les vrais points GPS pour le calcul de la carte
real_gps_for_map = [p for p in gps_points if p.get('source_file', '') != '(dummy padding)']

# Calculer les limites GPS (seulement sur les vrais points)
all_lats = [p['lat'] for p in real_gps_for_map]
all_lons = [p['lon'] for p in real_gps_for_map]
min_lat, max_lat = min(all_lats), max(all_lats)
min_lon, max_lon = min(all_lons), max(all_lons)

# Centre de la carte
center_lat = (min_lat + max_lat) / 2
center_lon = (min_lon + max_lon) / 2

# Calculer le zoom optimal
def get_zoom_level(lat_range, lon_range, map_width, map_height):
    for zoom in range(18, 1, -1):
        lat_pixels = lat_range * (256 * (2 ** zoom)) / 360
        lon_pixels = lon_range * (256 * (2 ** zoom)) / 360
        if lat_pixels < map_height * 0.8 and lon_pixels < map_width * 0.8:
            return zoom
    return 10

zoom = get_zoom_level(max_lat - min_lat, max_lon - min_lon, 1920, 420)

def lat_lon_to_tile(lat, lon, zoom):
    lat_rad = math.radians(lat)
    n = 2.0 ** zoom
    xtile = int((lon + 180.0) / 360.0 * n)
    ytile = int((1.0 - math.asinh(math.tan(lat_rad)) / math.pi) / 2.0 * n)
    return (xtile, ytile)

def download_tile(x, y, zoom):
    url = f"https://tile.openstreetmap.org/{zoom}/{x}/{y}.png"
    try:
        req = urllib.request.Request(url, headers={'User-Agent': 'TeslaDashcamViewer/1.0'})
        with urllib.request.urlopen(req, timeout=5) as response:
            img_data = response.read()
            img = Image.open(BytesIO(img_data))
            return cv2.cvtColor(np.array(img), cv2.COLOR_RGB2BGR)
    except:
        # Tuile grise si échec
        return np.full((256, 256, 3), (240, 240, 240), dtype=np.uint8)

print("🗺️  Téléchargement de la carte OpenStreetMap...")

# Calculer les tuiles nécessaires
center_tile_x, center_tile_y = lat_lon_to_tile(center_lat, center_lon, zoom)

# Télécharger une grille 8x2 de tuiles (1920px de large / 256 = 7.5 tuiles)
tiles_x = 8
tiles_y = 2

map_tiles = []
for ty in range(center_tile_y - 1, center_tile_y + 1):
    row = []
    for tx in range(center_tile_x - 4, center_tile_x + 4):
        print(f"  Tuile {tx},{ty}...", end='\r')
        tile = download_tile(tx, ty, zoom)
        row.append(tile)
    map_tiles.append(row)

print("  ✅ Tuiles téléchargées                    ")

# Assembler les tuiles
map_rows = [np.hstack(row) for row in map_tiles]
map_full = np.vstack(map_rows)

# Redimensionner à la taille exacte
map_background = cv2.resize(map_full, (1920, 420))

# Fonction de conversion GPS vers pixels sur la carte assemblée
def gps_to_pixel_on_map(lat, lon):
    lat_rad = math.radians(lat)
    n = 2.0 ** zoom
    
    world_x = (lon + 180.0) / 360.0 * n
    world_y = (1.0 - math.asinh(math.tan(lat_rad)) / math.pi) / 2.0 * n
    
    tile_offset_x = center_tile_x - 4
    tile_offset_y = center_tile_y - 1
    
    pixel_x = (world_x - tile_offset_x) * 256
    pixel_y = (world_y - tile_offset_y) * 256
    
    # Redimensionner aux coordonnées finales
    final_x = int(pixel_x * 1920 / (tiles_x * 256))
    final_y = int(pixel_y * 420 / (tiles_y * 256))
    
    return (final_x, final_y)

# Dessiner le trajet sur la carte (seulement les vrais points GPS, pas le padding)
real_gps_points = [p for p in gps_points if p.get('source_file', '') != '(dummy padding)']

if len(real_gps_points) > 1:
    for i in range(len(real_gps_points) - 1):
        p1 = gps_to_pixel_on_map(real_gps_points[i]['lat'], real_gps_points[i]['lon'])
        p2 = gps_to_pixel_on_map(real_gps_points[i+1]['lat'], real_gps_points[i+1]['lon'])
        cv2.line(map_background, p1, p2, (0, 0, 0), 8)
        cv2.line(map_background, p1, p2, (39, 33, 232), 6)

# Ouvrir les vidéos
print("📹 Ouverture des vidéos...")
caps = {
    'front': cv2.VideoCapture(UPLOAD_DIR + 'merged_front.mp4'),
    'back': cv2.VideoCapture(UPLOAD_DIR + 'merged_back.mp4'),
    'left': cv2.VideoCapture(UPLOAD_DIR + 'merged_left.mp4'),
    'right': cv2.VideoCapture(UPLOAD_DIR + 'merged_right.mp4')
}

fps = caps['front'].get(cv2.CAP_PROP_FPS)
total_frames = int(caps['front'].get(cv2.CAP_PROP_FRAME_COUNT))

fourcc = cv2.VideoWriter_fourcc(*'mp4v')
out = cv2.VideoWriter(OUTPUT_FILE, fourcc, fps, (1920, 1580))

print(f"⚙️  Génération: {total_frames} frames...")

frame_count = 0
black = np.zeros((540, 960, 3), dtype=np.uint8)
canvas = np.zeros((1580, 1920, 3), dtype=np.uint8)

while frame_count < total_frames:
    ret_f, f_frame = caps['front'].read()
    ret_b, b_frame = caps['back'].read()
    ret_l, l_frame = caps['left'].read()
    ret_r, r_frame = caps['right'].read()
    
    if not ret_f:
        break
    
    f_frame = cv2.resize(f_frame, (960, 540), interpolation=cv2.INTER_NEAREST)
    b_frame = cv2.resize(b_frame, (960, 540), interpolation=cv2.INTER_NEAREST) if ret_b else black
    l_frame = cv2.resize(l_frame, (960, 540), interpolation=cv2.INTER_NEAREST) if ret_l else black
    r_frame = cv2.resize(r_frame, (960, 540), interpolation=cv2.INTER_NEAREST) if ret_r else black
    
    canvas[80:620, :960] = f_frame
    canvas[80:620, 960:] = b_frame
    canvas[620:1160, :960] = l_frame
    canvas[620:1160, 960:] = r_frame
    
    # Calculer le temps en secondes
    time_seconds = frame_count / fps
    
    # Synchronisation basée sur le FPS du JSON (36 fps avec padding)
    idx = int(time_seconds * 36)
    idx = max(0, min(idx, len(gps_points) - 1))
    
    gps = gps_points[idx]
    
    # Vérifier si on est dans le padding (optionnel)
    is_dummy = gps.get('source_file', '') == '(dummy padding)'
    
    canvas[:80, :] = 0
    
    cv2.putText(canvas, f"{int(gps['speed'])} km/h", (860, 55), 
                cv2.FONT_HERSHEY_SIMPLEX, 1.2, (39, 33, 232), 3)
    
    if gps.get('blinker_left'):
        cv2.putText(canvas, '<--', (350, 55), cv2.FONT_HERSHEY_SIMPLEX, 1.5, (0, 255, 0), 4)
    else:
        cv2.putText(canvas, '<--', (350, 55), cv2.FONT_HERSHEY_SIMPLEX, 1.5, (80, 80, 80), 2)
    
    if gps.get('blinker_right'):
        cv2.putText(canvas, '-->', (1470, 55), cv2.FONT_HERSHEY_SIMPLEX, 1.5, (0, 255, 0), 4)
    else:
        cv2.putText(canvas, '-->', (1470, 55), cv2.FONT_HERSHEY_SIMPLEX, 1.5, (80, 80, 80), 2)
    
    if gps.get('brake'):
        cv2.putText(canvas, 'STOP', (1650, 55), cv2.FONT_HERSHEY_SIMPLEX, 1.2, (0, 0, 255), 4)
    else:
        cv2.putText(canvas, 'STOP', (1650, 55), cv2.FONT_HERSHEY_SIMPLEX, 1.2, (80, 80, 80), 2)
    
    canvas[1160:, :] = map_background
    pos = gps_to_pixel_on_map(gps['lat'], gps['lon'])
    cv2.circle(canvas[1160:, :], pos, 12, (0, 0, 255), -1)
    cv2.circle(canvas[1160:, :], pos, 12, (255, 255, 255), 2)
    
    out.write(canvas)
    
    frame_count += 1
    if frame_count % 500 == 0:
        print(f"⏳ {int(frame_count/total_frames*100)}%")

for cap in caps.values():
    cap.release()
out.release()

print(f"✅ Export terminé: {OUTPUT_FILE}")

