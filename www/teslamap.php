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
        'distance' => 'Distance',
        'charged' => 'Chargé',
        'to' => 'Vers',
        'unknown' => 'Inconnu',
        'none' => 'Aucun trajet enregistré.',
        'start' => 'Départ',
        'end' => 'Arrivée',
        'btn_kml' => 'EXPORT GOOGLE EARTH (KML)',
        'btn_3d' => 'CARTE DES ALTITUDES',
        'btn_2d' => 'RETOURNER À LA CARTE 2D',
        'alt_label' => 'Altitude (m)',
        'speed_label' => 'Vitesse'
    ],
    'en' => [
        'title' => 'History',
        'vehicle' => 'Vehicle',
        'date' => 'Date',
        'back' => 'Back',
        'distance' => 'Distance',
        'charged' => 'Charged',
        'to' => 'To',
        'unknown' => 'Unknown',
        'none' => 'No drives recorded.',
        'start' => 'Start',
        'end' => 'Arrival',
        'btn_kml' => 'EXPORT GOOGLE EARTH (KML)',
        'btn_3d' => 'ALTITUDE MAP (3D)',
        'btn_2d' => 'BACK TO 2D MAP',
        'alt_label' => 'Altitude (m)',
        'speed_label' => 'Speed'
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
if (isset($_GET['export_kml']) && isset($_GET['drive_id'])) {
    try {
        $pdo_k = new PDO("pgsql:host=$server_ip;port=5432;dbname=$db_name", $db_user, $db_pass);
        $stmt_k = $pdo_k->prepare("SELECT latitude, longitude, elevation FROM positions WHERE drive_id = ? AND latitude IS NOT NULL ORDER BY date ASC");
        $stmt_k->execute([$_GET['drive_id']]);
        $raw_points = $stmt_k->fetchAll(PDO::FETCH_ASSOC);
        $clean_coords = [];
        $last_lat = null; $last_lon = null;
        foreach ($raw_points as $p) {
            $lat = round($p['latitude'], 6); $lon = round($p['longitude'], 6); $alt = round($p['elevation'] ?? 0, 1);
            if ($lat !== $last_lat || $lon !== $last_lon) {
                $clean_coords[] = "$lon,$lat,$alt";
                $last_lat = $lat; $last_lon = $lon;
            }
        }
        if (count($clean_coords) >= 2) {
            header('Content-Type: application/vnd.google-earth.kml+xml');
            header('Content-Disposition: attachment; filename="trip_'.$_GET['drive_id'].'.kml"');
            echo '<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document><Placemark><LineString><extrude>1</extrude><tessellate>1</tessellate><altitudeMode>relativeToGround</altitudeMode><coordinates>'.implode("\n", $clean_coords).'</coordinates></LineString></Placemark></Document></kml>';
            exit;
        }
    } catch (Exception $e) {}
}

// --- 4. RÉCUPÉRATION DONNÉES ---
$trajets = []; $positions = []; $cars = [];
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_drive_id = $_GET['drive_id'] ?? null;
$total_km = 0;

try {
    $pdo = new PDO("pgsql:host=$server_ip;port=5432;dbname=$db_name", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("SET TIME ZONE 'Europe/Paris'");

    $stmt_cars = $pdo->query("SELECT id, COALESCE(name, model) as display_name FROM cars ORDER BY id ASC");
    $cars = $stmt_cars->fetchAll(PDO::FETCH_ASSOC);
    $selected_car_id = $_GET['car_id'] ?? ($cars[0]['id'] ?? null);

    if ($selected_car_id) {
        $sql_trajets = "SELECT d.id, (d.start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') as start_date_local, ROUND(d.distance::numeric, 1) as km, a_e.display_name as end_point FROM drives d LEFT JOIN addresses a_e ON d.end_address_id = a_e.id WHERE d.car_id = :car_id AND DATE(d.start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') = :date ORDER BY d.start_date DESC";
        $stmt = $pdo->prepare($sql_trajets);
        $stmt->execute(['car_id' => $selected_car_id, 'date' => $selected_date]);
        $trajets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($trajets as $t) $total_km += $t['km'];

        if ($selected_drive_id) {
            $stmt_p = $pdo->prepare("SELECT latitude, longitude, elevation, speed FROM positions WHERE drive_id = ? AND latitude IS NOT NULL ORDER BY date ASC");
            $stmt_p->execute([$selected_drive_id]);
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
        .back-button { width: 35px; height: 35px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; }
        .back-button:hover { background: #dc2626; }
        .back-button svg { width: 20px; height: 20px; stroke: white; fill: none; stroke-width: 2.5; }
        
        label { display: block; font-size: 10px; color: #888; text-transform: uppercase; margin: 10px 0 5px; }
        .input-field { width: 100%; padding: 10px; background: #333; border: 1px solid #444; color: white; border-radius: 4px; box-sizing: border-box; }
        
        .list-container { flex: 1; overflow-y: auto; padding: 10px; }
        .trajet-card { background: #2a2a2a; border-radius: 8px; padding: 15px; margin-bottom: 10px; cursor: pointer; border: 2px solid transparent; }
        .trajet-card.active { border-color: #dc2626; background: #331a1a; }
        
        .btn-action { display: block; padding: 12px; margin: 5px 0; border-radius: 6px; text-align: center; text-decoration: none; font-weight: bold; font-size: 11px; text-transform: uppercase; cursor: pointer; }
        .btn-kml { background: #dc2626; color: white; }
        .btn-3d { background: #3b82f6; color: white; }
        .btn-2d { background: #444; color: white; display: none; }
        .btn-action:hover { filter: brightness(1.2); }
        
        .km-val { color: #dc2626; font-weight: bold; }
    </style>
</head>
<body>

<div id="sidebar">
    <div class="header">
        <div class="top-nav">
            <a href="tesla.php" class="back-button"><svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg></a>
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
    </div>

    <div class="list-container">
        <?php foreach ($trajets as $t): ?>
            <div class="trajet-card <?= $selected_drive_id == $t['id'] ? 'active' : '' ?>" onclick="if(event.target.tagName !== 'A') location.href='?car_id=<?= $selected_car_id ?>&date=<?= $selected_date ?>&drive_id=<?= $t['id'] ?>'">
                <div style="display:flex; justify-content:space-between;">
                    <span style="font-weight:bold;"><?= date('H:i', strtotime($t['start_date_local'])) ?></span>
                    <span class="km-val"><?= $t['km'] ?> km</span>
                </div>
                <div style="font-size:11px; color:#aaa; margin-top:5px;"><?= $txt['to'] ?> : <?= htmlspecialchars($t['end_point'] ?? $txt['unknown']) ?></div>
                
                <?php if ($selected_drive_id == $t['id']): ?>
                    <div style="margin-top:15px;">
                        <a href="?export_kml=1&drive_id=<?= $t['id'] ?>" class="btn-action btn-kml"><?= $txt['btn_kml'] ?></a>
                        <a id="btnShow3D" class="btn-action btn-3d" onclick="toggleVision('3D'); return false;"><?= $txt['btn_3d'] ?></a>
                        <a id="btnShow2D" class="btn-action btn-2d" onclick="toggleVision('2D'); return false;"><?= $txt['btn_2d'] ?></a>
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
    // 2D Map Init
    const map = L.map('map').setView([46.6, 2.2], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    <?php if (!empty($positions)): 
        $lats = []; $lons = []; $alts = []; $speeds = []; $texts = [];
        foreach ($positions as $p) {
            $lats[] = $p['latitude']; $lons[] = $p['longitude'];
            $alts[] = $p['elevation'] ?? 0; $speeds[] = $p['speed'] ?? 0;
            $texts[] = round($p['speed'])." km/h | ".round($p['elevation'])."m";
        }
    ?>
    const pts = <?= json_encode(array_map(fn($p) => [$p['latitude'], $p['longitude']], $positions)) ?>;
    L.polyline(pts, {color: '#dc2626', weight: 5}).addTo(map);
    map.fitBounds(L.latLngBounds(pts), {padding: [50, 50]});

    let plotlyRendered = false;

    function toggleVision(mode) {
        const divMap = document.getElementById('map');
        const divPlot = document.getElementById('plot3d');
        const btn3D = document.getElementById('btnShow3D');
        const btn2D = document.getElementById('btnShow2D');

        if (mode === '3D') {
            divMap.style.visibility = 'hidden';
            divPlot.style.display = 'block';
            btn3D.style.display = 'none';
            btn2D.style.display = 'block';
            if (!plotlyRendered) renderPlotly();
        } else {
            divPlot.style.display = 'none';
            divMap.style.visibility = 'visible';
            btn2D.style.display = 'none';
            btn3D.style.display = 'block';
        }
    }

    function renderPlotly() {
        const data = [{
            type: 'scatter3d', mode: 'lines',
            x: <?= json_encode($lons) ?>, y: <?= json_encode($lats) ?>, z: <?= json_encode($alts) ?>,
            line: { width: 6, color: <?= json_encode($speeds) ?>, colorscale: 'Viridis', colorbar: { title: '<?= $txt["speed_label"] ?>', thickness: 15, tickfont: {color:'#eee'} } },
            text: <?= json_encode($texts) ?>, hoverinfo: 'text'
        }];
        const layout = {
            paper_bgcolor: '#000', margin: { l: 0, r: 0, b: 0, t: 0 },
            scene: {
                xaxis: { color: '#888', gridcolor: '#333' },
                yaxis: { color: '#888', gridcolor: '#333' },
                zaxis: { title: '<?= $txt["alt_label"] ?>', color: '#888', gridcolor: '#333' },
                aspectmode: 'manual', aspectratio: { x: 1, y: 1, z: 0.3 }
            }
        };
        Plotly.newPlot('plot3d', data, layout);
        plotlyRendered = true;
    }
    <?php endif; ?>
</script>
</body>
</html>

