<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- 1. LECTURE DU SETUP ---
$file = 'cgi-bin/setup';
$config = ['DOCKER_PATH' => '', 'LANGUAGE' => 'FR'];
if (file_exists($file)) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (empty(trim($line)) || $line[0] === '#' || strpos($line, '=') === false) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) { 
            $key = strtoupper(trim($parts[0]));
            $config[$key] = trim($parts[1]); 
        }
    }
}

// --- 2. GESTION DE LA LANGUE ---
$lang = (isset($config['LANGUAGE']) && strtolower($config['LANGUAGE']) === 'en') ? 'en' : 'fr';

$txt = [
    'fr' => [
        'title' => 'Historique',
        'vehicle' => 'Véhicule',
        'date' => 'Date',
        'back' => 'Retour',
        'to' => 'Vers',
        'unknown' => 'Inconnu',
        'start' => 'D',
        'end' => 'A',
        'btn_kml' => 'EXPORT GOOGLE EARTH (KML)',
        'btn_3d' => 'CARTE DES ALTITUDES (3D)',
        'btn_2d' => 'RETOURNER À LA CARTE (2D)',
        'btn_full_day' => 'JOURNÉE ENTIÈRE',
        'alt_label' => 'Altitude',
        'speed_label' => 'Vitesse',
        'temp_label' => 'Temp.'
    ],
    'en' => [
        'title' => 'History',
        'vehicle' => 'Vehicle',
        'date' => 'Date',
        'back' => 'Back',
        'to' => 'To',
        'unknown' => 'Unknown',
        'start' => 'S',
        'end' => 'E',
        'btn_kml' => 'EXPORT GOOGLE EARTH (KML)',
        'btn_3d' => 'ALTITUDE MAP (3D)',
        'btn_2d' => 'BACK TO MAP (2D)',
        'btn_full_day' => 'FULL DAY VIEW',
        'alt_label' => 'Altitude',
        'speed_label' => 'Speed',
        'temp_label' => 'Temp.'
    ]
][$lang];

// --- 3. CONFIG DB ---
$server_ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
$db_user = "teslamate"; $db_pass = "secret_password"; $db_name = "teslamate";
if (!empty($config['DOCKER_PATH']) && file_exists($config['DOCKER_PATH'])) {
    $docker_content = file_get_contents($config['DOCKER_PATH']);
    if (preg_match('/POSTGRES_USER[:=]\s*(\S+)/', $docker_content, $m)) $db_user = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_PASSWORD[:=]\s*(\S+)/', $docker_content, $m)) $db_pass = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_DB[:=]\s*(\S+)/', $docker_content, $m)) $db_name = str_replace(['"', "'"], '', trim($m[1]));
}

// --- LOGIQUE EXPORT KML ---
if (isset($_GET['export_kml'])) {
    try {
        $pdo_k = new PDO("pgsql:host=$server_ip;port=5432;dbname=$db_name", $db_user, $db_pass);
        if (isset($_GET['drive_id'])) {
            $stmt_k = $pdo_k->prepare("SELECT date, latitude, longitude, elevation FROM positions WHERE drive_id = ? AND latitude IS NOT NULL ORDER BY date ASC");
            $stmt_k->execute([$_GET['drive_id']]);
            $filename = "trip_".$_GET['drive_id'].".kml";
        } else {
            $stmt_k = $pdo_k->prepare("SELECT p.date, p.latitude, p.longitude, p.elevation FROM positions p JOIN drives d ON p.drive_id = d.id WHERE d.car_id = ? AND DATE(d.start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') = ? AND p.latitude IS NOT NULL ORDER BY p.date ASC");
            $stmt_k->execute([$_GET['car_id'], $_GET['date']]);
            $filename = "full_day_".$_GET['date'].".kml";
        }
        $rows = $stmt_k->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) >= 2) {
            header('Content-Type: application/vnd.google-earth.kml+xml');
            header('Content-Disposition: attachment; filename="'.$filename.'"');
            echo '<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2"><Document><Placemark><gx:Track><altitudeMode>relativeToGround</altitudeMode>';
            foreach ($rows as $r) echo '<when>'.date('Y-m-d\TH:i:s\Z', strtotime($r['date'])).'</when>';
            foreach ($rows as $r) echo '<gx:coord>'.$r['longitude'].' '.$r['latitude'].' '.round($r['elevation'] ?? 0, 1).'</gx:coord>';
            echo '</gx:Track></Placemark></Document></kml>';
            exit;
        }
    } catch (Exception $e) {}
}

// --- 4. RÉCUPÉRATION DONNÉES ---
$trajets = []; $positions = []; $cars = [];
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_drive_id = $_GET['drive_id'] ?? null;
$is_full_day = isset($_GET['full_day']) || (!$selected_drive_id && isset($_GET['date']));

try {
    $pdo = new PDO("pgsql:host=$server_ip;port=5432;dbname=$db_name", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("SET TIME ZONE 'Europe/Paris'");

    $stmt_cars = $pdo->query("SELECT id, COALESCE(name, model) as display_name FROM cars ORDER BY id ASC");
    $cars = $stmt_cars->fetchAll(PDO::FETCH_ASSOC);
    $selected_car_id = $_GET['car_id'] ?? ($cars[0]['id'] ?? null);

    if ($selected_car_id) {
        $sql = "SELECT d.id, (d.start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') as start_date_local, ROUND(d.distance::numeric, 1) as km, a_e.display_name as end_point FROM drives d LEFT JOIN addresses a_e ON d.end_address_id = a_e.id WHERE d.car_id = :car_id AND DATE(d.start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') = :date ORDER BY d.start_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['car_id' => $selected_car_id, 'date' => $selected_date]);
        $trajets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($selected_drive_id) {
            $stmt_p = $pdo->prepare("SELECT latitude, longitude, elevation, speed, outside_temp, drive_id FROM positions WHERE drive_id = ? AND latitude IS NOT NULL ORDER BY date ASC");
            $stmt_p->execute([$selected_drive_id]);
            $positions = $stmt_p->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($is_full_day) {
            $stmt_p = $pdo->prepare("SELECT p.latitude, p.longitude, p.elevation, p.speed, p.outside_temp, p.drive_id FROM positions p JOIN drives d ON p.drive_id = d.id WHERE d.car_id = ? AND DATE(d.start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') = ? AND p.latitude IS NOT NULL ORDER BY p.date ASC");
            $stmt_p->execute([$selected_car_id, $selected_date]);
            $positions = $stmt_p->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) { die($e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $txt['title'] ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://cdn.plot.ly/plotly-2.24.1.min.js"></script>
    <style>
        body { font-family: -apple-system, sans-serif; background: #111; color: #eee; margin: 0; display: flex; height: 100vh; overflow: hidden; }
        #sidebar { width: 380px; background: #1c1c1c; border-right: 1px solid #333; display: flex; flex-direction: column; z-index: 10; }
        #main-viz { flex: 1; position: relative; background: #000; }
        #map, #plot3d { width: 100%; height: 100%; position: absolute; top: 0; left: 0; }
        #plot3d { display: none; }
        
        .header { padding: 15px 20px; background: #252525; border-bottom: 1px solid #333; }
        .top-nav { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .back-button { width: 35px; height: 35px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: 0.2s; }
        .back-button:hover { background: #dc2626; }
        .back-button svg { width: 20px; height: 20px; stroke: white; fill: none; stroke-width: 2.5; }

        label { display: block; font-size: 10px; color: #888; text-transform: uppercase; margin: 10px 0 5px; }
        .input-field { width: 100%; padding: 10px; background: #333; border: 1px solid #444; color: white; border-radius: 4px; box-sizing: border-box; }
        .list-container { flex: 1; overflow-y: auto; padding: 10px; }
        .trajet-card { background: #2a2a2a; border-radius: 8px; padding: 15px; margin-bottom: 10px; cursor: pointer; border: 2px solid transparent; }
        .trajet-card.active { border-color: #dc2626; background: #331a1a; }
        
        .btn-action { display: block; padding: 12px; margin: 5px 0; border-radius: 6px; text-align: center; text-decoration: none; font-weight: bold; font-size: 11px; text-transform: uppercase; cursor: pointer; border: none; width: 100%; box-sizing: border-box; }
        .btn-kml { background: #dc2626; color: white; }
        .btn-3d { background: #3b82f6; color: white; }
        .btn-2d { background: #444; color: white; }
        .btn-full-day { background: #059669; color: white; margin-top: 10px; }
        .btn-action:hover { filter: brightness(1.2); }
        
        .custom-marker { display: flex; align-items: center; justify-content: center; font-weight: bold; color: white; border: 2px solid white; border-radius: 50%; box-shadow: 0 0 10px rgba(0,0,0,0.5); }
        .leaflet-tooltip-custom { background: rgba(0,0,0,0.9); border: 1px solid #dc2626; color: white; font-weight: bold; border-radius: 4px; padding: 5px 10px; font-size: 12px; }
    </style>
</head>
<body>

<div id="sidebar">
    <div class="header">
        <div class="top-nav">
            <a href="tesla.php" class="back-button" title="<?= $txt['back'] ?>">
                <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            </a>
            <h1 style="margin:0; font-size:18px; color:#dc2626;"><?= $txt['title'] ?></h1>
        </div>
        
        <label><?= $txt['vehicle'] ?></label>
        <select class="input-field" onchange="location.href='?date=<?= $selected_date ?>&car_id='+this.value">
            <?php foreach ($cars as $car): ?>
                <option value="<?= $car['id'] ?>" <?= $selected_car_id == $car['id'] ? 'selected' : '' ?>><?= htmlspecialchars($car['display_name']) ?></option>
            <?php endforeach; ?>
        </select>
        
        <label><?= $txt['date'] ?></label>
        <input type="date" class="input-field" value="<?= $selected_date ?>" onchange="location.href='?car_id=<?= $selected_car_id ?>&date='+this.value">
        
        <a href="?car_id=<?= $selected_car_id ?>&date=<?= $selected_date ?>&full_day=1" class="btn-action btn-full-day"><?= $txt['btn_full_day'] ?></a>

        <?php if ($is_full_day && !empty($positions) && !$selected_drive_id): ?>
            <div style="margin-top:10px;">
                <a href="?export_kml=1&car_id=<?= $selected_car_id ?>&date=<?= $selected_date ?>" class="btn-action btn-kml"><?= $txt['btn_kml'] ?></a>
                <button id="btnShow3D" class="btn-action btn-3d" onclick="toggleVision('3D')"><?= $txt['btn_3d'] ?></button>
                <button id="btnShow2D" class="btn-action btn-2d" style="display:none;" onclick="toggleVision('2D')"><?= $txt['btn_2d'] ?></button>
            </div>
        <?php endif; ?>
    </div>

    <div class="list-container">
        <?php foreach ($trajets as $t): ?>
            <div class="trajet-card <?= $selected_drive_id == $t['id'] ? 'active' : '' ?>" onclick="if(event.target.tagName !== 'A') location.href='?car_id=<?= $selected_car_id ?>&date=<?= $selected_date ?>&drive_id=<?= $t['id'] ?>'">
                <div style="display:flex; justify-content:space-between;">
                    <span style="font-weight:bold;"><?= date('H:i', strtotime($t['start_date_local'])) ?></span>
                    <span style="color:#dc2626; font-weight:bold;"><?= $t['km'] ?> km</span>
                </div>
                <div style="font-size:11px; color:#aaa; margin-top:5px;"><?= $txt['to'] ?> : <?= htmlspecialchars($t['end_point'] ?? $txt['unknown']) ?></div>
                
                <?php if ($selected_drive_id == $t['id']): ?>
                    <div style="margin-top:15px;" onclick="event.stopPropagation();">
                        <a href="?export_kml=1&drive_id=<?= $t['id'] ?>" class="btn-action btn-kml"><?= $txt['btn_kml'] ?></a>
                        <button id="btnShow3D" class="btn-action btn-3d" onclick="toggleVision('3D')"><?= $txt['btn_3d'] ?></button>
                        <button id="btnShow2D" class="btn-action btn-2d" style="display:none;" onclick="toggleVision('2D')"><?= $txt['btn_2d'] ?></button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="main-viz">
    <div id="map"></div>
    <div id="plot3d"></div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const map = L.map('map', { wheelDebounceTime: 150 }).setView([46, 2], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    function getColor(s) {
        return s > 110 ? '#ef4444' : s > 80 ? '#f97316' : s > 50 ? '#eab308' : s > 30 ? '#22c55e' : '#3b82f6';
    }

    <?php if (!empty($positions)): 
        $lats = []; $lons = []; $alts = []; $speeds = []; $texts_3d = [];
        foreach ($positions as $p) {
            $lats[] = $p['latitude']; $lons[] = $p['longitude'];
            $altV = $p['elevation'] ?? 0;
            $speedV = $p['speed'] ?? 0;
            $tempV = $p['outside_temp'] ?? '--';
            $alts[] = $altV; $speeds[] = $speedV;
            $texts_3d[] = $txt['speed_label'].": ".round($speedV)." km/h<br>".$txt['alt_label'].": ".round($altV)."m<br>".$txt['temp_label'].": ".$tempV."°C";
        }
    ?>
    const pos = <?= json_encode($positions) ?>;
    const hoverMarker = L.circleMarker([0,0], {radius: 7, fillOpacity: 0.8, color: '#fff', fillColor: '#dc2626', weight: 2}).addTo(map);
    
    for (let i = 0; i < pos.length - 1; i++) {
        if (pos[i].drive_id !== pos[i+1].drive_id) continue;

        let line = L.polyline([[pos[i].latitude, pos[i].longitude],[pos[i+1].latitude, pos[i+1].longitude]], {
            color: getColor(pos[i].speed), weight: 6, opacity: 0.9
        }).addTo(map);

        line.on('mousemove', function(e) {
            let data = pos[i];
            hoverMarker.setLatLng(e.latlng);
            let content = `
                <b><?= $txt['speed_label'] ?>:</b> ${Math.round(data.speed)} km/h<br>
                <b><?= $txt['alt_label'] ?>:</b> ${Math.round(data.elevation || 0)} m<br>
                <b><?= $txt['temp_label'] ?>:</b> ${data.outside_temp || '--'} °C
            `;
            hoverMarker.bindTooltip(content, {sticky: true, className: 'leaflet-tooltip-custom'}).openTooltip();
        });
        
        line.on('mouseout', function() { hoverMarker.closeTooltip(); });
    }

    const bounds = L.latLngBounds(pos.map(p => [p.latitude, p.longitude]));
    map.fitBounds(bounds, {padding:[50,50]});

    // MARQUEURS D ET A AUX EXTRÉMITÉS DU JEU DE DONNÉES COMPLET
    L.marker([pos[0].latitude, pos[0].longitude], {
        icon: L.divIcon({className:'custom-marker', html:'<?= $txt['start'] ?>', iconSize:[24,24]})
    }).addTo(map).getElement().style.backgroundColor='#22c55e';

    L.marker([pos[pos.length-1].latitude, pos[pos.length-1].longitude], {
        icon: L.divIcon({className:'custom-marker', html:'<?= $txt['end'] ?>', iconSize:[24,24]})
    }).addTo(map).getElement().style.backgroundColor='#dc2626';

    let plotlyRendered = false;
    function toggleVision(mode) {
        const mapDiv = document.getElementById('map');
        const plotDiv = document.getElementById('plot3d');
        const btn3D = document.getElementById('btnShow3D');
        const btn2D = document.getElementById('btnShow2D');

        if (mode === '3D') {
            mapDiv.style.visibility = 'hidden';
            plotDiv.style.display = 'block';
            if (btn3D) btn3D.style.display = 'none';
            if (btn2D) btn2D.style.display = 'block';
            if (!plotlyRendered) renderPlotly();
        } else {
            plotDiv.style.display = 'none';
            mapDiv.style.visibility = 'visible';
            if (btn2D) btn2D.style.display = 'none';
            if (btn3D) btn3D.style.display = 'block';
        }
    }

    function renderPlotly() {
        let traces = [];
        let currentX = [], currentY = [], currentZ = [], currentSpeed = [], currentText = [];
        
        for (let i = 0; i < pos.length; i++) {
            currentX.push(pos[i].longitude);
            currentY.push(pos[i].latitude);
            currentZ.push(pos[i].elevation || 0);
            currentSpeed.push(pos[i].speed);
            currentText.push(`<?= $txt['speed_label'] ?>: ${Math.round(pos[i].speed)} km/h<br><?= $txt['alt_label'] ?>: ${Math.round(pos[i].elevation || 0)}m<br><?= $txt['temp_label'] ?>: ${pos[i].outside_temp || '--'}°C`);

            if (i < pos.length - 1 && pos[i].drive_id !== pos[i+1].drive_id) {
                traces.push(createTrace(currentX, currentY, currentZ, currentSpeed, currentText, traces.length === 0));
                currentX = []; currentY = []; currentZ = []; currentSpeed = []; currentText = [];
            }
        }
        traces.push(createTrace(currentX, currentY, currentZ, currentSpeed, currentText, traces.length === 0));

        // AJOUT DES MARQUEURS D ET A AUX EXTRÉMITÉS DU TRACÉ 3D
        const endPointsMarker = { 
            type:'scatter3d', mode:'text+markers', 
            x:[pos[0].longitude, pos[pos.length-1].longitude], 
            y:[pos[0].latitude, pos[pos.length-1].latitude], 
            z:[(pos[0].elevation??0)+10, (pos[pos.length-1].elevation??0)+10], 
            text:['<?= $txt['start'] ?>','<?= $txt['end'] ?>'], 
            marker:{size:4, color:['#22c55e','#dc2626']}, 
            textfont:{color:['#22c55e','#dc2626'], size:14, family:'Arial Black'},
            hoverinfo: 'none'
        };
        traces.push(endPointsMarker);

        Plotly.newPlot('plot3d', traces, {
            paper_bgcolor:'#000', margin:{l:0,r:0,b:0,t:0}, showlegend:false,
            scene:{
                xaxis:{color:'#888', gridcolor:'#333', title:''}, 
                yaxis:{color:'#888', gridcolor:'#333', title:''}, 
                zaxis:{title:'<?= $txt["alt_label"] ?> (m)', color:'#888', gridcolor:'#333'}, 
                aspectratio:{x:1,y:1,z:0.3}
            }
        });
        plotlyRendered = true;
    }

    function createTrace(x, y, z, color, text, showColorBar) {
        return {
            type:'scatter3d', mode:'lines', x:x, y:y, z:z,
            line:{width:6, color:color, colorscale:'Viridis', colorbar: showColorBar ? {title:'km/h', thickness:15} : undefined},
            text: text, hoverinfo: 'text'
        };
    }
    <?php endif; ?>
</script>
</body>
</html>
