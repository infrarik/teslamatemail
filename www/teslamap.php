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
        'temp_label' => 'Temp.',
        'total_km' => 'Distance Totale',
        'search_placeholder' => 'Rechercher une ville...',
        'no_results' => 'Aucun trajet à proximité.'
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
        'temp_label' => 'Temp.',
        'total_km' => 'Total Distance',
        'search_placeholder' => 'Search city...',
        'no_results' => 'No trips nearby.'
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
$search_query = $_GET['search'] ?? null;
$is_full_day = isset($_GET['full_day']) || (!$selected_drive_id && isset($_GET['date']) && !$search_query);
$total_day_km = 0;

try {
    $pdo = new PDO("pgsql:host=$server_ip;port=5432;dbname=$db_name", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("SET TIME ZONE 'Europe/Paris'");

    $stmt_cars = $pdo->query("SELECT id, COALESCE(name, model) as display_name FROM cars ORDER BY id ASC");
    $cars = $stmt_cars->fetchAll(PDO::FETCH_ASSOC);
    $selected_car_id = $_GET['car_id'] ?? ($cars[0]['id'] ?? null);

    if ($selected_car_id) {
        if ($search_query && isset($_GET['lat']) && isset($_GET['lng'])) {
            $sql = "SELECT d.id, (d.start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') as start_date_local, 
                    ROUND(d.distance::numeric, 1) as km, a_e.display_name as end_point,
                    DATE(d.start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') as trip_date
                    FROM drives d 
                    LEFT JOIN addresses a_e ON d.end_address_id = a_e.id 
                    JOIN positions p ON p.drive_id = d.id
                    WHERE d.car_id = :car_id 
                    AND (6371 * acos(cos(radians(:lat)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians(:lng)) + sin(radians(:lat)) * sin(radians(p.latitude)))) < 5
                    GROUP BY d.id, a_e.display_name, trip_date
                    ORDER BY d.start_date DESC LIMIT 50";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['car_id' => $selected_car_id, 'lat' => $_GET['lat'], 'lng' => $_GET['lng']]);
        } else {
            $sql = "SELECT d.id, (d.start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') as start_date_local, ROUND(d.distance::numeric, 1) as km, a_e.display_name as end_point FROM drives d LEFT JOIN addresses a_e ON d.end_address_id = a_e.id WHERE d.car_id = :car_id AND DATE(d.start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') = :date ORDER BY d.start_date DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['car_id' => $selected_car_id, 'date' => $selected_date]);
        }
        $trajets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($trajets as $t) $total_day_km += $t['km'];

        if ($selected_drive_id) {
            $stmt_p = $pdo->prepare("SELECT latitude, longitude, elevation, speed, outside_temp, drive_id FROM positions WHERE drive_id = ? AND latitude IS NOT NULL ORDER BY date ASC");
            $stmt_p->execute([$selected_drive_id]);
            $positions = $stmt_p->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($is_full_day && !$search_query) {
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
        .top-nav { display: flex; align-items: center; gap: 15px; margin-bottom: 10px; }
        .back-button { width: 35px; height: 35px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: 0.2s; }
        .back-button:hover { background: #dc2626; }
        .back-button svg { width: 20px; height: 20px; stroke: white; fill: none; stroke-width: 2.5; }

        .search-box { position: relative; margin-bottom: 10px; }
        .search-box input { width: 100%; padding: 10px 65px 10px 10px; background: #333; border: 1px solid #444; color: white; border-radius: 4px; box-sizing: border-box; }
        .search-controls { position: absolute; right: 10px; top: 9px; display: flex; gap: 8px; align-items: center; }
        .search-controls svg { width: 18px; height: 18px; fill: #888; cursor: pointer; transition: 0.2s; }
        .search-controls svg:hover { fill: #dc2626; }

        label { display: block; font-size: 10px; color: #888; text-transform: uppercase; margin: 10px 0 5px; }
        .input-field { width: 100%; padding: 10px; background: #333; border: 1px solid #444; color: white; border-radius: 4px; box-sizing: border-box; }
        
        .summary-day { background: #059669; color: white; padding: 15px; margin: 10px; border-radius: 8px; text-align: center; }
        .list-container { flex: 1; overflow-y: auto; padding: 10px; }
        .trajet-card { background: #2a2a2a; border-radius: 8px; padding: 15px; margin-bottom: 10px; cursor: pointer; border: 2px solid transparent; transition: 0.2s; }
        .trajet-card.active { border-color: #dc2626; background: #331a1a; }
        .trajet-card:hover { background: #333; }
        
        .btn-action { display: block; padding: 12px; margin: 5px 0; border-radius: 6px; text-align: center; text-decoration: none; font-weight: bold; font-size: 11px; text-transform: uppercase; cursor: pointer; border: none; width: 100%; box-sizing: border-box; }
        .btn-kml { background: #dc2626; color: white; }
        .btn-3d { background: #3b82f6; color: white; }
        .btn-2d { background: #444; color: white; }
        .btn-full-day { background: #444; color: white; margin-top: 10px; }
        .btn-full-day.active { background: #059669; }
        
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

        <div class="search-box">
            <input type="text" id="citySearch" placeholder="<?= $txt['search_placeholder'] ?>" value="<?= htmlspecialchars($search_query ?? '') ?>" onkeypress="if(event.key === 'Enter') searchCity()">
            <div class="search-controls">
                <?php if($search_query): ?>
                    <svg onclick="location.href='?'" viewBox="0 0 24 24" title="Reset"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                <?php endif; ?>
                <svg onclick="searchCity()" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            </div>
        </div>
        
        <label><?= $txt['vehicle'] ?></label>
        <select class="input-field" onchange="location.href='?date=<?= $selected_date ?>&car_id='+this.value">
            <?php foreach ($cars as $car): ?>
                <option value="<?= $car['id'] ?>" <?= $selected_car_id == $car['id'] ? 'selected' : '' ?>><?= htmlspecialchars($car['display_name']) ?></option>
            <?php endforeach; ?>
        </select>
        
        <label><?= $txt['date'] ?></label>
        <input type="date" class="input-field" value="<?= $selected_date ?>" onchange="location.href='?car_id=<?= $selected_car_id ?>&date='+this.value">
        
        <?php if (!$search_query): ?>
            <a href="?car_id=<?= $selected_car_id ?>&date=<?= $selected_date ?>&full_day=1" class="btn-action btn-full-day <?= ($is_full_day && !$selected_drive_id) ? 'active' : '' ?>">
                <?= $txt['btn_full_day'] ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($positions)): ?>
            <?php if ($selected_drive_id || ($is_full_day && !$search_query)): ?>
                <div style="margin-top:10px;">
                    <a href="?export_kml=1&<?= $selected_drive_id ? "drive_id=$selected_drive_id" : "car_id=$selected_car_id&date=$selected_date" ?>" class="btn-action btn-kml"><?= $txt['btn_kml'] ?></a>
                    <button id="btnShow3D" class="btn-action btn-3d" onclick="toggleVision('3D')"><?= $txt['btn_3d'] ?></button>
                    <button id="btnShow2D" class="btn-action btn-2d" style="display:none;" onclick="toggleVision('2D')"><?= $txt['btn_2d'] ?></button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($is_full_day && !$selected_drive_id && !$search_query): ?>
        <div class="summary-day">
            <div style="font-size:10px; text-transform:uppercase; opacity:0.8;"><?= $txt['total_km'] ?></div>
            <div style="font-size:24px; font-weight:800;"><?= number_format($total_day_km, 1, ',', ' ') ?> km</div>
        </div>
    <?php endif; ?>

    <div class="list-container">
        <?php if (empty($trajets)): ?>
            <p style="text-align:center; color:#888; margin-top:20px;"><?= $txt['no_results'] ?></p>
        <?php endif; ?>

        <?php foreach ($trajets as $t): 
            $card_url = "?car_id=$selected_car_id&drive_id={$t['id']}";
            if ($search_query) $card_url .= "&search=".urlencode($search_query)."&lat=".$_GET['lat']."&lng=".$_GET['lng'];
            else $card_url .= "&date=$selected_date";
        ?>
            <div class="trajet-card <?= $selected_drive_id == $t['id'] ? 'active' : '' ?>" onclick="location.href='<?= $card_url ?>'">
                <div style="display:flex; justify-content:space-between;">
                    <span style="font-weight:bold;">
                        <?= isset($t['trip_date']) ? date('d/m ', strtotime($t['trip_date'])) : '' ?>
                        <?= date('H:i', strtotime($t['start_date_local'])) ?>
                    </span>
                    <span style="color:#dc2626; font-weight:bold;"><?= $t['km'] ?> km</span>
                </div>
                <div style="font-size:11px; color:#aaa; margin-top:5px;"><?= $txt['to'] ?> : <?= htmlspecialchars($t['end_point'] ?? $txt['unknown']) ?></div>
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
    async function searchCity() {
        const query = document.getElementById('citySearch').value;
        if (query.length < 2) return;
        try {
            const resp = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`);
            const data = await resp.json();
            if (data.length > 0) {
                location.href = `?car_id=<?= $selected_car_id ?>&search=${encodeURIComponent(query)}&lat=${data[0].lat}&lng=${data[0].lon}`;
            }
        } catch (e) { alert('Erreur lors de la recherche'); }
    }

    const map = L.map('map', { wheelDebounceTime: 150 }).setView([46, 2], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    function getColor(s) {
        return s > 110 ? '#ef4444' : s > 80 ? '#f97316' : s > 50 ? '#eab308' : s > 30 ? '#22c55e' : '#3b82f6';
    }

    <?php if (!empty($positions)): ?>
    const pos = <?= json_encode($positions) ?>;
    const hoverMarker = L.circleMarker([0,0], {radius: 7, fillOpacity: 0.8, color: '#fff', fillColor: '#dc2626', weight: 2}).addTo(map);
    
    for (let i = 0; i < pos.length - 1; i++) {
        if (pos[i].drive_id !== pos[i+1].drive_id) continue;
        let line = L.polyline([[pos[i].latitude, pos[i].longitude],[pos[i+1].latitude, pos[i+1].longitude]], {
            color: getColor(pos[i].speed), weight: 6, opacity: 0.9
        }).addTo(map);
        line.on('mousemove', function(e) {
            let d = pos[i];
            hoverMarker.setLatLng(e.latlng);
            hoverMarker.bindTooltip(`<b><?= $txt['speed_label'] ?>:</b> ${Math.round(d.speed)} km/h<br><b><?= $txt['alt_label'] ?>:</b> ${Math.round(d.elevation || 0)} m<br><b><?= $txt['temp_label'] ?>:</b> ${d.outside_temp || '--'} °C`, {sticky:true, className:'leaflet-tooltip-custom'}).openTooltip();
        });
        line.on('mouseout', () => hoverMarker.closeTooltip());
    }

    map.fitBounds(L.latLngBounds(pos.map(p => [p.latitude, p.longitude])), {padding:[50,50]});
    L.marker([pos[0].latitude, pos[0].longitude], {icon: L.divIcon({className:'custom-marker', html:'<?= $txt['start'] ?>', iconSize:[24,24]})}).addTo(map).getElement().style.backgroundColor='#22c55e';
    L.marker([pos[pos.length-1].latitude, pos[pos.length-1].longitude], {icon: L.divIcon({className:'custom-marker', html:'<?= $txt['end'] ?>', iconSize:[24,24]})}).addTo(map).getElement().style.backgroundColor='#dc2626';

    let plotlyRendered = false;
    function toggleVision(mode) {
        document.getElementById('map').style.visibility = (mode === '3D' ? 'hidden' : 'visible');
        document.getElementById('plot3d').style.display = (mode === '3D' ? 'block' : 'none');
        document.getElementById('btnShow3D').style.display = (mode === '3D' ? 'none' : 'block');
        document.getElementById('btnShow2D').style.display = (mode === '3D' ? 'block' : 'none');
        if (mode === '3D' && !plotlyRendered) renderPlotly();
    }

    function renderPlotly() {
        let traces = [];
        let cx = [], cy = [], cz = [], cs = [], ct = [];
        for (let i = 0; i < pos.length; i++) {
            cx.push(pos[i].longitude); cy.push(pos[i].latitude); cz.push(pos[i].elevation || 0); cs.push(pos[i].speed);
            ct.push(`<?= $txt['speed_label'] ?>: ${Math.round(pos[i].speed)} km/h<br><?= $txt['alt_label'] ?>: ${Math.round(pos[i].elevation || 0)}m<br><?= $txt['temp_label'] ?>: ${pos[i].outside_temp || '--'}°C`);
            if (i < pos.length - 1 && pos[i].drive_id !== pos[i+1].drive_id) {
                traces.push({type:'scatter3d',mode:'lines',x:cx,y:cy,z:cz,line:{width:6,color:cs,colorscale:'Viridis'},text:ct,hoverinfo:'text'});
                cx = []; cy = []; cz = []; cs = []; ct = [];
            }
        }
        traces.push({type:'scatter3d',mode:'lines',x:cx,y:cy,z:cz,line:{width:6,color:cs,colorscale:'Viridis',colorbar:{title:'km/h',thickness:15}},text:ct,hoverinfo:'text'});
        traces.push({type:'scatter3d',mode:'text+markers',x:[pos[0].longitude,pos[pos.length-1].longitude],y:[pos[0].latitude,pos[pos.length-1].latitude],z:[(pos[0].elevation??0)+10,(pos[pos.length-1].elevation??0)+10],text:['<?= $txt['start'] ?>','<?= $txt['end'] ?>'],marker:{size:4,color:['#22c55e','#dc2626']},textfont:{color:['#22c55e','#dc2626'],size:14,family:'Arial Black'},hoverinfo:'none'});
        Plotly.newPlot('plot3d', traces, {paper_bgcolor:'#000', margin:{l:0,r:0,b:0,t:0}, showlegend:false, scene:{xaxis:{color:'#888',gridcolor:'#333',title:''},yaxis:{color:'#888',gridcolor:'#333',title:''},zaxis:{title:'<?= $txt["alt_label"] ?> (m)',color:'#888',gridcolor:'#333'},aspectratio:{x:1,y:1,z:0.3}}});
        plotlyRendered = true;
    }
    <?php endif; ?>
</script>
</body>
</html>
