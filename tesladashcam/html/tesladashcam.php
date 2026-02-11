<?php
define('UPLOAD_DIR', '/var/www/html/uploads/');
define('CGI_BIN', '/var/www/html/cgi-bin/');
define('GPS_JSON', CGI_BIN . 'videosgps.json');

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);

$existingFiles = glob(UPLOAD_DIR . "*.mp4");
$fileCount = count($existingFiles);

if (isset($_POST['delete_all'])) {
    exec("rm -rf " . escapeshellarg(UPLOAD_DIR) . "*");
    if (file_exists(GPS_JSON)) unlink(GPS_JSON);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'upload_file' && isset($_FILES['file'])) {
    header('Content-Type: application/json');
    $targetPath = UPLOAD_DIR . basename($_FILES['file']['name']);
    echo json_encode(['success' => move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)]);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'export_video') {
    header('Content-Type: application/json');
    
    $outputFile = UPLOAD_DIR . 'export.mp4';
    
    // Supprimer l'ancien export s'il existe
    if (file_exists($outputFile)) {
        unlink($outputFile);
    }
    
    // Lancer le script Python
    $cmd = "python3 /mnt/user-data/outputs/export_video.py 2>&1";
    exec($cmd, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($outputFile)) {
        echo json_encode([
            'success' => true,
            'download_url' => 'uploads/export.mp4'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Erreur export: ' . implode("\n", array_slice($output, -10))
        ]);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'process_tesla_data') {
    header('Content-Type: application/json');
    $cams = ['front' => 'front', 'back' => 'back', 'left' => 'left_repeater', 'right' => 'right_repeater'];
    $allGPS = [];
    $processedPaths = [];

    // EXTRACTION GPS: camera FRONT uniquement
    $frontFiles = glob(UPLOAD_DIR . "*front.mp4");
    usort($frontFiles, function($a, $b) {
        return strcmp(basename($a), basename($b));
    });
    
    // Récupérer SEULEMENT les vidéos FRONT et les trier par ordre chronologique
    $frontFiles = glob(UPLOAD_DIR . "*-front.mp4");
    $frontFiles = array_filter($frontFiles, function($f) {
        return !preg_match('/(merged|export)/', basename($f));
    });
    usort($frontFiles, function($a, $b) {
        return strcmp(basename($a), basename($b));
    });
    
    error_log("Extraction GPS de " . count($frontFiles) . " vidéos FRONT dans l'ordre chronologique");
    
    foreach ($frontFiles as $videoFile) {
        $basename = basename($videoFile);
        error_log("Extraction GPS: $basename");
        
        $cmd = "cd " . escapeshellarg(CGI_BIN) . " && python3 sei_extractor.py " . escapeshellarg($videoFile) . " 2>&1";
        $output = shell_exec($cmd);
        
        $pointsAdded = 0;
        
        if ($output) {
            $lines = explode("\n", trim($output));
            $isFirstLine = true;
            
            foreach ($lines as $line) {
                if ($isFirstLine) {
                    $isFirstLine = false;
                    continue;
                }
                
                $fields = str_getcsv($line);
                if (count($fields) >= 13) {
                    $lat = floatval($fields[10]); 
                    $lon = floatval($fields[11]);
                    
                    $allGPS[] = [
                        'lat' => $lat, 
                        'lon' => $lon, 
                        'speed' => floatval($fields[3]) * 3.6,
                        'elev' => 0,
                        'heading' => floatval($fields[12]),
                        'gear' => $fields[1] ?? '',
                        'frame' => intval($fields[2] ?? 0),
                        'timestamp' => floatval($fields[3] ?? 0),
                        'blinker_left' => ($fields[6] ?? '') === 'True',
                        'blinker_right' => ($fields[7] ?? '') === 'True',
                        'brake' => ($fields[8] ?? '') === 'True',
                        'autopilot' => ($fields[9] ?? '') === 'True',
                        'source_file' => $basename,
                        'gear_state' => $fields[1] ?? '',
                        'autopilot_state' => ($fields[9] ?? '') === 'True'
                    ];
                    $pointsAdded++;
                }
            }
        }
        
        error_log("  → $pointsAdded points GPS extraits");
    }
    
    error_log("Total points GPS: " . count($allGPS));
    
    // Calculer le décalage temporel entre le début des vidéos et le premier GPS
    if (!empty($allGPS)) {
        // Trouver le timestamp de la première vidéo FRONT
        $firstVideoFile = $frontFiles[0];
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})_(\d{2})-(\d{2})-(\d{2})/', basename($firstVideoFile), $m)) {
            $firstVideoTime = mktime(intval($m[4]), intval($m[5]), intval($m[6]), intval($m[2]), intval($m[3]), intval($m[1]));
        }
        
        // Trouver le timestamp de la première vidéo avec GPS
        $firstGPSFile = $allGPS[0]['source_file'];
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})_(\d{2})-(\d{2})-(\d{2})/', $firstGPSFile, $m)) {
            $firstGPSTime = mktime(intval($m[4]), intval($m[5]), intval($m[6]), intval($m[2]), intval($m[3]), intval($m[1]));
        }
        
        if (isset($firstVideoTime) && isset($firstGPSTime)) {
            $timeGapSeconds = $firstGPSTime - $firstVideoTime;
            error_log("Décalage temporel: $timeGapSeconds secondes (" . ($timeGapSeconds/60) . " minutes)");
            
            if ($timeGapSeconds > 0) {
                // Créer des points GPS fictifs pour combler le gap
                // Utiliser la position du premier point GPS réel
                $firstRealGPS = $allGPS[0];
                
                // Estimer le nombre de frames nécessaires (36 fps moyen)
                $dummyPoints = intval($timeGapSeconds * 36);
                
                error_log("Création de $dummyPoints points GPS fictifs pour combler les " . ($timeGapSeconds/60) . " premières minutes");
                
                $dummyGPS = [];
                for ($i = 0; $i < $dummyPoints; $i++) {
                    $dummyGPS[] = [
                        'lat' => $firstRealGPS['lat'],
                        'lon' => $firstRealGPS['lon'],
                        'speed' => 0,
                        'elev' => 0,
                        'heading' => $firstRealGPS['heading'],
                        'gear' => '',
                        'frame' => $i,
                        'timestamp' => 0,
                        'blinker_left' => false,
                        'blinker_right' => false,
                        'brake' => true,
                        'autopilot' => false,
                        'source_file' => '(dummy padding)',
                        'gear_state' => 'GEAR_PARK',
                        'autopilot_state' => false
                    ];
                }
                
                // Ajouter les points fictifs AU DÉBUT
                $allGPS = array_merge($dummyGPS, $allGPS);
                error_log("Total points GPS après padding: " . count($allGPS));
            }
        }
    }

    foreach ($cams as $key => $suffix) {
        $files = glob(UPLOAD_DIR . "*$suffix.mp4");
        usort($files, function($a, $b) {
            return strcmp(basename($a), basename($b));
        });

        if (empty($files)) continue;

        $listFile = UPLOAD_DIR . "concat_$key.txt";
        $content = "";
        foreach ($files as $f) {
            $content .= "file '" . basename($f) . "'\n";
        }
        
        file_put_contents($listFile, $content);
        $outputFile = UPLOAD_DIR . "merged_$key.mp4";
        exec("ffmpeg -y -f concat -safe 0 -i $listFile -c copy $outputFile 2>&1");
        unlink($listFile);
        $processedPaths[$key] = "uploads/merged_$key.mp4";
    }
    
    // Extraire le timestamp de base depuis le premier fichier vidéo FRONT
    $firstFile = $frontFiles[0] ?? '';
    $baseTimestamp = 0;
    if (preg_match('/(\d{4})-(\d{2})-(\d{2})_(\d{2})-(\d{2})-(\d{2})/', basename($firstFile), $m)) {
        $baseTimestamp = mktime(intval($m[4]), intval($m[5]), intval($m[6]), intval($m[2]), intval($m[3]), intval($m[1]));
    }
    
    file_put_contents(GPS_JSON, json_encode([
        'gps' => $allGPS, 
        'paths' => $processedPaths,
        'base_timestamp' => $baseTimestamp,
        'video_start_time' => $baseTimestamp > 0 ? date('Y-m-d H:i:s', $baseTimestamp) : null,
        'metadata' => [
            'total_points' => count($allGPS),
            'created' => date('Y-m-d H:i:s'),
            'files_processed' => count($frontFiles)
        ]
    ], JSON_PRETTY_PRINT));
    echo json_encode(['success' => true, 'gps_count' => count($allGPS)]);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_data') {
    header('Content-Type: application/json');
    echo file_exists(GPS_JSON) ? file_get_contents(GPS_JSON) : json_encode(['error' => 'no data']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>TeslaCam Sync Pro</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #0f0f0f; 
            color: #fff; 
            margin: 0; 
            padding: 1vw; 
        }
        .panel { 
            background: #1a1a1a; 
            padding: 2vw; 
            border-radius: 10px; 
            border: 1px solid #333; 
            margin-bottom: 2vh; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.5); 
            max-width: 1600px;
            margin-left: auto;
            margin-right: auto;
        }
        .video-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 0.5vw; 
            background: #000; 
            padding: 0.5vw; 
            border-radius: 8px; 
        }
        .cam-box { 
            position: relative; 
            border: 1px solid #333; 
            overflow: hidden; 
            aspect-ratio: 16/9;
        }
        .cam-label { 
            position: absolute; 
            top: 0.5vw; 
            left: 0.5vw; 
            background: rgba(232, 33, 39, 0.9); 
            padding: 0.2vw 1vw; 
            font-size: clamp(8px, 0.8vw, 12px); 
            font-weight: bold; 
            border-radius: 3px; 
            z-index: 5; 
        }
        video { 
            width: 100%; 
            height: 100%;
            object-fit: cover;
            display: block; 
        }
        #map { 
            height: 50vh; 
            min-height: 300px;
            border-radius: 8px; 
            border: 1px solid #333; 
        }
        button { 
            background: #e82127; 
            color: white; 
            border: none; 
            padding: clamp(8px, 1vw, 12px) clamp(16px, 2vw, 24px); 
            border-radius: 5px; 
            cursor: pointer; 
            font-weight: bold; 
            transition: 0.3s; 
            font-size: clamp(12px, 1vw, 16px);
        }
        button:hover { background: #c61a1f; }
        .seek-container { 
            margin: 1vh 0; 
            display: flex; 
            align-items: center; 
            gap: 1vw; 
            background: #1a1a1a; 
            padding: 1vh 1vw; 
            border-radius: 8px; 
            border: 1px solid #333; 
        }
        #seekbar { 
            flex-grow: 1; 
            accent-color: #e82127; 
            height: 8px; 
            cursor: pointer; 
        }
        #timer { 
            font-family: 'Courier New', monospace; 
            font-size: clamp(12px, 1.2vw, 16px); 
            color: #e82127; 
            min-width: 100px; 
            white-space: nowrap;
        }
        .layout { 
            display: grid; 
            grid-template-columns: 1fr; 
            gap: 2vh; 
            max-width: 1600px;
            margin: 0 auto;
        }
        @media (min-width: 1200px) {
            .layout {
                grid-template-columns: 1fr 35vw;
                max-width: none;
            }
            #map {
                height: 70vh;
            }
        }
        #progress-bar { 
            height: 6px; 
            background: #e82127; 
            width: 0%; 
            transition: 0.2s; 
            margin-top: 1vh; 
            border-radius: 3px; 
        }
        .existing-box { 
            background: #252525; 
            padding: 1.5vh 1.5vw; 
            border-radius: 5px; 
            margin-bottom: 1.5vh; 
            border-left: 4px solid #e82127; 
        }
    </style>
</head>
<body>

    <div class="panel" id="setup-panel">
        <h2 style="margin-top:0">TeslaCam Multi-Viewer</h2>
        
        <?php if ($fileCount > 0): ?>
            <div class="existing-box">
                <strong><?php echo $fileCount; ?></strong> fichiers MP4 disponibles dans <code>uploads/</code>.
                <br><br>
                <button onclick="processAndLoad()">VOIR LES VIDÉOS</button>
            </div>
        <?php endif; ?>
        
        <p>Charger un répertoire (TeslaCam/RecentClips) :</p>
        <input type="file" id="dirInput" webkitdirectory multiple>
        <button onclick="uploadDir()">UPLOADER ET TRAITER</button>
        <button onclick="deleteAll()" style="background:#444; margin-left:10px;">RESET</button>
        
        <div id="progress-bar"></div>
        <div id="status" style="font-size:13px; margin-top:8px; color:#aaa"></div>
    </div>

    <div id="interface" style="display:none" class="layout">
        <div>
            <div class="video-grid">
                <div class="cam-box"><span class="cam-label">AVANT</span><video id="v-front"></video></div>
                <div class="cam-box"><span class="cam-label">ARRIÈRE</span><video id="v-back"></video></div>
                <div class="cam-box"><span class="cam-label">GAUCHE</span><video id="v-left"></video></div>
                <div class="cam-box"><span class="cam-label">DROITE</span><video id="v-right"></video></div>
            </div>
        </div>
        <div>
            <div id="map"></div>
        </div>

    <!-- Panneau de contrôle flottant -->
    <div id="floating-controls" style="
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: rgba(26, 26, 26, 0.95);
        border: 2px solid #e82127;
        border-radius: 12px;
        padding: 15px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.8);
        cursor: move;
        user-select: none;
        z-index: 9999;
        backdrop-filter: blur(10px);
        min-width: 320px;
        max-width: 800px;
        resize: both;
        overflow: auto;
    ">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #333;">
            <span style="font-size: 12px; color: #aaa; font-weight: bold;">🎮 CONTRÔLES</span>
            <div style="display: flex; gap: 8px; align-items: center;">
                <span style="font-size: 10px; color: #666; cursor: help;" title="Glissez pour déplacer">✋ Déplacer</span>
                <span style="font-size: 10px; color: #666; cursor: help;" title="Coin bas-droit pour redimensionner">↔️ Redim.</span>
            </div>
        </div>
        
        <!-- Indicateurs -->
        <div style="display: flex; justify-content: center; align-items: center; gap: 15px; margin-bottom: 12px; flex-wrap: wrap;">
            <div id="blinker-left" class="indicator" style="font-size: 35px; opacity: 0.2; transition: all 0.2s; filter: grayscale(100%);">⬅️</div>
            <div id="speed-display" class="indicator" style="font-family: 'Courier New', monospace; font-size: 28px; font-weight: bold; color: #e82127; min-width: 100px; text-align: center;">0 km/h</div>
            <div id="blinker-right" class="indicator" style="font-size: 35px; opacity: 0.2; transition: all 0.2s; filter: grayscale(100%);">➡️</div>
            <div id="brake-indicator" class="indicator" style="font-size: 35px; opacity: 0.2; transition: all 0.2s;">🛑</div>
        </div>
        
        <!-- Deuxième ligne : Autopilot et Gear -->
        <div style="display: flex; justify-content: center; align-items: center; gap: 20px; margin-bottom: 12px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div id="autopilot-indicator" class="indicator" style="font-size: 30px; opacity: 0.2; transition: all 0.3s; filter: grayscale(100%);">🤖</div>
                <span style="font-size: 11px; color: #888;">AUTO</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div id="gear-display" class="indicator" style="font-family: 'Courier New', monospace; font-size: 24px; font-weight: bold; color: #0f0; min-width: 40px; text-align: center; border: 2px solid #0f0; padding: 4px 8px; border-radius: 4px;">P</div>
                <span style="font-size: 11px; color: #888;">GEAR</span>
            </div>
        </div>
        
        <!-- Barre de progression -->
        <div class="seek-container" style="margin: 10px 0; gap: 10px; padding: 8px; background: #0f0f0f;">
            <span id="timer" style="font-size: 13px; min-width: 90px;">00:00 / 00:00</span>
            <input type="range" id="seekbar" value="0" step="0.01">
        </div>
        
        <!-- Bouton lecture -->
        <div style="text-align: center; margin-top: 10px;">
            <button onclick="togglePlay()" id="pBtn" style="width: 100%; padding: 10px;">▶ LECTURE</button>
        </div>
        
        <!-- Poignée de resize visible (coin bas-droit) -->
        <div id="resize-handle" style="
            position: absolute;
            bottom: 0;
            right: 0;
            width: 20px;
            height: 20px;
            cursor: nwse-resize;
            background: linear-gradient(135deg, transparent 50%, #e82127 50%);
            border-bottom-right-radius: 10px;
        " title="Glisser pour redimensionner"></div>
    </div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    let gpsPoints = [];
    let map, marker, polyline;
    let firstMovementIndex = 0; // Index du premier mouvement GPS
    const vids = { front: document.getElementById('v-front'), back: document.getElementById('v-back'), left: document.getElementById('v-left'), right: document.getElementById('v-right') };

    async function uploadDir() {
        const files = Array.from(document.getElementById('dirInput').files).filter(f => f.name.endsWith('.mp4'));
        if (!files.length) return alert("Pas de fichiers MP4.");
        
        const status = document.getElementById('status');
        const bar = document.getElementById('progress-bar');

        for (let i = 0; i < files.length; i++) {
            const fd = new FormData();
            fd.append('action', 'upload_file'); 
            fd.append('file', files[i]);
            await fetch('', { method: 'POST', body: fd });
            bar.style.width = ((i + 1) / files.length * 100) + '%';
            status.innerText = `Envoi : ${i + 1}/${files.length}`;
        }
        processAndLoad();
    }

    async function processAndLoad() {
        document.getElementById('status').innerText = "Fusion des fichiers et extraction GPS chronologique...";
        document.getElementById('progress-bar').style.width = '100%';

        await fetch('', { 
            method: 'POST', 
            headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
            body: 'action=process_tesla_data' 
        });
        
        const res = await fetch('?action=get_data');
        const data = await res.json();
        gpsPoints = data.gps;

        if (!gpsPoints || gpsPoints.length === 0) {
            alert("Erreur: Aucune donnée GPS extraite.");
            return;
        }
        
        // Détecter le premier vrai mouvement (vitesse > 2 km/h)
        firstMovementIndex = 0;
        for (let i = 0; i < gpsPoints.length; i++) {
            if (gpsPoints[i].speed > 2) {
                firstMovementIndex = i;
                console.log(`✅ Premier mouvement GPS au point ${i}/${gpsPoints.length} (${(i/gpsPoints.length*100).toFixed(1)}% dans les données GPS)`);
                console.log(`   Vitesse: ${gpsPoints[i].speed.toFixed(1)} km/h`);
                console.log(`   Position: ${gpsPoints[i].lat.toFixed(6)}, ${gpsPoints[i].lon.toFixed(6)}`);
                if (gpsPoints[i].source_file) {
                    console.log(`   Fichier source: ${gpsPoints[i].source_file}`);
                }
                break;
            }
        }
        
        console.log(`📊 Total points GPS chargés: ${gpsPoints.length}`);
        console.log(`📍 Premier point: ${gpsPoints[0].lat.toFixed(6)}, ${gpsPoints[0].lon.toFixed(6)} (vitesse: ${gpsPoints[0].speed.toFixed(1)} km/h)`);
        if (gpsPoints[0].source_file) {
            console.log(`   Source: ${gpsPoints[0].source_file}`);
        }

        document.getElementById('setup-panel').style.display = 'none';
        document.getElementById('interface').style.display = 'grid';
        
        for (let k in data.paths) { 
            vids[k].src = data.paths[k] + "?v=" + Date.now(); 
            vids[k].load(); 
        }

        vids.front.onloadedmetadata = () => { 
            document.getElementById('seekbar').max = vids.front.duration; 
            initMap(); 
        };

        vids.front.ontimeupdate = () => {
            const t = vids.front.currentTime;
            if (!document.getElementById('seekbar').matches(':active')) {
                document.getElementById('seekbar').value = t;
            }
            document.getElementById('timer').innerText = `${formatTime(t)} / ${formatTime(vids.front.duration)}`;
            
            ['back','left','right'].forEach(k => { 
                if(vids[k].src && Math.abs(vids[k].currentTime - t) > 0.4) vids[k].currentTime = t; 
            });
            updateMap(t);
        };

        document.getElementById('seekbar').oninput = (e) => {
            const t = parseFloat(e.target.value);
            Object.values(vids).forEach(v => { if(v.src) v.currentTime = t; });
        };
    }

    function initMap() {
        if(map) map.remove();
        
        map = L.map('map').setView([gpsPoints[0].lat, gpsPoints[0].lon], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        
        const coords = gpsPoints.map(p => [p.lat, p.lon]);
        polyline = L.polyline(coords, {color: '#e82127', weight: 6, opacity: 0.85}).addTo(map);
        marker = L.marker([gpsPoints[0].lat, gpsPoints[0].lon]).addTo(map);
        
        map.fitBounds(polyline.getBounds());
        
        // Gérer le redimensionnement de la fenêtre
        window.addEventListener('resize', () => {
            if (map) {
                setTimeout(() => map.invalidateSize(), 100);
            }
        });
        
        // CLIC CARTE
        map.on('click', function(e) {
            let closestIdx = 0;
            let minDist = Infinity;
            
            for (let i = 0; i < gpsPoints.length; i++) {
                const dist = Math.sqrt(
                    Math.pow(gpsPoints[i].lat - e.latlng.lat, 2) + 
                    Math.pow(gpsPoints[i].lon - e.latlng.lng, 2)
                );
                if (dist < minDist) {
                    minDist = dist;
                    closestIdx = i;
                }
            }
            
            const ratio = closestIdx / (gpsPoints.length - 1);
            const targetTime = ratio * vids.front.duration;
            
            Object.values(vids).forEach(v => { 
                if(v.src) v.currentTime = targetTime; 
            });
            
            marker.setLatLng([gpsPoints[closestIdx].lat, gpsPoints[closestIdx].lon]);
        });
    }

    let lastGPSPosition = null;
    let debugInterval = null;

    function updateMap(time) {
        if(!vids.front.duration || gpsPoints.length === 0) return;
        
        // Calculer l'index GPS basé sur le FPS moyen (36 fps)
        let idx = Math.floor(time * 36);
        idx = Math.min(idx, gpsPoints.length - 1);
        idx = Math.max(0, idx);
        
        if (gpsPoints[idx]) {
            const currentPos = {lat: gpsPoints[idx].lat, lon: gpsPoints[idx].lon};
            const isDummyPadding = gpsPoints[idx].source_file === '(dummy padding)';
            
            // Debug toutes les 5 secondes
            if (!debugInterval || time - debugInterval > 5) {
                console.log(`⏱️ Time: ${time.toFixed(1)}s | Index: ${idx}/${gpsPoints.length} | Speed: ${gpsPoints[idx].speed.toFixed(1)} km/h | Dummy: ${isDummyPadding}`);
                debugInterval = time;
            }
            
            // Initialiser
            if (lastGPSPosition === null) {
                lastGPSPosition = currentPos;
                marker.setLatLng([currentPos.lat, currentPos.lon]);
                console.log(`🎯 Marker initialisé à: ${currentPos.lat.toFixed(6)}, ${currentPos.lon.toFixed(6)}`);
            }
            
            // Bouger SYSTÉMATIQUEMENT si on n'est plus dans le padding
            if (!isDummyPadding) {
                marker.setLatLng([currentPos.lat, currentPos.lon]);
                if (Math.abs(currentPos.lat - lastGPSPosition.lat) > 0.00001) {
                    console.log(`🚗 Marker bougé à: ${currentPos.lat.toFixed(6)}, ${currentPos.lon.toFixed(6)} (vitesse: ${gpsPoints[idx].speed.toFixed(1)} km/h)`);
                }
                lastGPSPosition = currentPos;
            }
            
            // Mise à jour de la vitesse
            const speedDisplay = document.getElementById('speed-display');
            speedDisplay.textContent = Math.round(gpsPoints[idx].speed) + ' km/h';
            
            // Mise à jour des clignotants (vert vif quand actif)
            const blinkerLeft = document.getElementById('blinker-left');
            const blinkerRight = document.getElementById('blinker-right');
            
            if (gpsPoints[idx].blinker_left) {
                blinkerLeft.style.opacity = '1';
                blinkerLeft.style.filter = 'grayscale(0%) brightness(1.5) hue-rotate(90deg)';
            } else {
                blinkerLeft.style.opacity = '0.2';
                blinkerLeft.style.filter = 'grayscale(100%)';
            }
            
            if (gpsPoints[idx].blinker_right) {
                blinkerRight.style.opacity = '1';
                blinkerRight.style.filter = 'grayscale(0%) brightness(1.5) hue-rotate(90deg)';
            } else {
                blinkerRight.style.opacity = '0.2';
                blinkerRight.style.filter = 'grayscale(100%)';
            }
            
            // Mise à jour du frein
            const brakeIndicator = document.getElementById('brake-indicator');
            if (gpsPoints[idx].brake) {
                brakeIndicator.style.opacity = '1';
                brakeIndicator.style.filter = 'brightness(1.5)';
            } else {
                brakeIndicator.style.opacity = '0.2';
                brakeIndicator.style.filter = 'grayscale(100%)';
            }
            
            // Mise à jour de l'Autopilot
            const autopilotIndicator = document.getElementById('autopilot-indicator');
            if (gpsPoints[idx].autopilot_state) {
                autopilotIndicator.style.opacity = '1';
                autopilotIndicator.style.filter = 'brightness(1.2) hue-rotate(200deg)'; // Bleu
            } else {
                autopilotIndicator.style.opacity = '0.2';
                autopilotIndicator.style.filter = 'grayscale(100%)';
            }
            
            // Mise à jour du Gear
            const gearDisplay = document.getElementById('gear-display');
            const gearState = gpsPoints[idx].gear_state || 'GEAR_PARK';
            let gearLetter = 'P';
            
            if (gearState.includes('DRIVE')) {
                gearLetter = 'D';
            } else if (gearState.includes('REVERSE')) {
                gearLetter = 'R';
            } else if (gearState.includes('NEUTRAL')) {
                gearLetter = 'N';
            } else {
                gearLetter = 'P';
            }
            
            gearDisplay.textContent = gearLetter;
        }
    }

    function formatTime(s) {
        if(isNaN(s)) return "00:00";
        const mins = Math.floor(s / 60);
        const secs = Math.floor(s % 60);
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }

    function togglePlay() {
        const isPaused = vids.front.paused;
        Object.values(vids).forEach(v => { if(v.src) isPaused ? v.play() : v.pause(); });
        document.getElementById('pBtn').innerText = isPaused ? "⏸ PAUSE" : "▶ LECTURE";
    }

    function deleteAll() {
        if(confirm("Voulez-vous vraiment TOUT supprimer ?")) {
            const fd = new FormData(); fd.append('delete_all', '1');
            fetch('', {method:'POST', body:fd}).then(() => location.reload());
        }
    }

    function formatTime(s) {
        if(isNaN(s)) return "00:00";
        const mins = Math.floor(s / 60);
        const secs = Math.floor(s % 60);
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }

    function togglePlay() {
        const isPaused = vids.front.paused;
        Object.values(vids).forEach(v => { if(v.src) isPaused ? v.play() : v.pause(); });
        document.getElementById('pBtn').innerText = isPaused ? "⏸ PAUSE" : "▶ LECTURE";
    }

    function deleteAll() {
        if(confirm("Voulez-vous vraiment TOUT supprimer ?")) {
            const fd = new FormData(); 
            fd.append('delete_all', '1');
            fetch('', {method:'POST', body:fd}).then(() => location.reload());
        }
    }

    // Rendre le panneau de contrôle draggable et resizable
    (function() {
        const floatingControls = document.getElementById('floating-controls');
        const resizeHandle = document.getElementById('resize-handle');
        
        // Variables pour le drag
        let isDragging = false;
        let currentX;
        let currentY;
        let initialX;
        let initialY;
        let xOffset = 0;
        let yOffset = 0;

        // Variables pour le resize
        let isResizing = false;
        let initialWidth;
        let initialHeight;
        let startX;
        let startY;

        // DRAG
        floatingControls.addEventListener('mousedown', dragStart);
        document.addEventListener('mousemove', drag);
        document.addEventListener('mouseup', dragEnd);

        floatingControls.addEventListener('touchstart', dragStart);
        document.addEventListener('touchmove', drag);
        document.addEventListener('touchend', dragEnd);

        function dragStart(e) {
            // Ne pas drag si on clique sur le seekbar, les boutons, ou la poignée de resize
            if (e.target.id === 'seekbar' || 
                e.target.tagName === 'BUTTON' || 
                e.target.id === 'resize-handle' ||
                e.target.classList.contains('indicator')) {
                return;
            }

            if (e.type === 'touchstart') {
                initialX = e.touches[0].clientX - xOffset;
                initialY = e.touches[0].clientY - yOffset;
            } else {
                initialX = e.clientX - xOffset;
                initialY = e.clientY - yOffset;
            }

            isDragging = true;
        }

        function drag(e) {
            if (isDragging) {
                e.preventDefault();

                if (e.type === 'touchmove') {
                    currentX = e.touches[0].clientX - initialX;
                    currentY = e.touches[0].clientY - initialY;
                } else {
                    currentX = e.clientX - initialX;
                    currentY = e.clientY - initialY;
                }

                xOffset = currentX;
                yOffset = currentY;

                setTranslate(currentX, currentY, floatingControls);
            }
            
            if (isResizing) {
                e.preventDefault();
                
                const clientX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
                const clientY = e.type === 'touchmove' ? e.touches[0].clientY : e.clientY;
                
                const newWidth = Math.max(320, initialWidth + (clientX - startX));
                const newHeight = Math.max(200, initialHeight + (clientY - startY));
                
                floatingControls.style.width = newWidth + 'px';
                floatingControls.style.height = newHeight + 'px';
                
                // Adapter la taille des éléments en fonction de la largeur du panneau
                adaptSizes(newWidth);
            }
        }

        function dragEnd(e) {
            if (isDragging) {
                initialX = currentX;
                initialY = currentY;
                savePosition();
            }
            if (isResizing) {
                saveSize();
            }
            isDragging = false;
            isResizing = false;
        }

        function setTranslate(xPos, yPos, el) {
            el.style.transform = `translate(${xPos}px, ${yPos}px)`;
        }

        // RESIZE
        resizeHandle.addEventListener('mousedown', resizeStart);
        resizeHandle.addEventListener('touchstart', resizeStart);

        function resizeStart(e) {
            e.stopPropagation();
            isResizing = true;
            
            const rect = floatingControls.getBoundingClientRect();
            initialWidth = rect.width;
            initialHeight = rect.height;
            
            if (e.type === 'touchstart') {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            } else {
                startX = e.clientX;
                startY = e.clientY;
            }
        }

        // Adapter les tailles des éléments selon la largeur du panneau
        function adaptSizes(width) {
            const scale = Math.max(0.7, Math.min(1.5, width / 400));
            
            // Indicateurs
            document.querySelectorAll('.indicator').forEach(el => {
                if (el.id === 'speed-display') {
                    el.style.fontSize = (28 * scale) + 'px';
                    el.style.minWidth = (100 * scale) + 'px';
                } else if (el.id === 'autopilot-indicator') {
                    el.style.fontSize = (30 * scale) + 'px';
                } else if (el.id === 'gear-display') {
                    el.style.fontSize = (24 * scale) + 'px';
                    el.style.minWidth = (40 * scale) + 'px';
                    el.style.padding = (4 * scale) + 'px ' + (8 * scale) + 'px';
                } else {
                    el.style.fontSize = (35 * scale) + 'px';
                }
            });
            
            // Timer
            document.getElementById('timer').style.fontSize = (13 * scale) + 'px';
            
            // Bouton
            document.getElementById('pBtn').style.padding = (10 * scale) + 'px';
            document.getElementById('pBtn').style.fontSize = (14 * scale) + 'px';
        }

        // Sauvegarder/restaurer position et taille
        function savePosition() {
            localStorage.setItem('controls-position', JSON.stringify({x: xOffset, y: yOffset}));
        }

        function saveSize() {
            const rect = floatingControls.getBoundingClientRect();
            localStorage.setItem('controls-size', JSON.stringify({
                width: rect.width,
                height: rect.height
            }));
        }

        // Restaurer position
        const savedPos = localStorage.getItem('controls-position');
        if (savedPos) {
            const pos = JSON.parse(savedPos);
            xOffset = pos.x;
            yOffset = pos.y;
            setTranslate(pos.x, pos.y, floatingControls);
        }

        // Restaurer taille
        const savedSize = localStorage.getItem('controls-size');
        if (savedSize) {
            const size = JSON.parse(savedSize);
            floatingControls.style.width = size.width + 'px';
            floatingControls.style.height = size.height + 'px';
            adaptSizes(size.width);
        }
    })();
</script>
</body>
</html>
