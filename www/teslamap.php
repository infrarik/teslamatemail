<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- 1. LECTURE DU SETUP ---
$file = 'cgi-bin/setup';
$config = ['DOCKER_PATH' => ''];
if (file_exists($file)) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) { 
            $key = strtoupper(trim($parts[0]));
            $config[$key] = trim($parts[1]); 
        }
    }
}

// --- 2. EXTRACTION IDENTIFIANTS ---
$db_user = "teslamate"; $db_pass = "secret_password"; $db_name = "teslamate";
if (!empty($config['DOCKER_PATH']) && file_exists($config['DOCKER_PATH'])) {
    $docker_content = file_get_contents($config['DOCKER_PATH']);
    if (preg_match('/POSTGRES_USER[:=]\s*(\S+)/', $docker_content, $m)) $db_user = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_PASSWORD[:=]\s*(\S+)/', $docker_content, $m)) $db_pass = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_DB[:=]\s*(\S+)/', $docker_content, $m)) $db_name = str_replace(['"', "'"], '', trim($m[1]));
}

// --- 3. RÉCUPÉRATION DES DONNÉES ---
$error_message = ""; $trajets = []; $positions = []; $cars = [];
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_drive_id = $_GET['drive_id'] ?? null;
$total_km = 0; $total_kwh = 0;

try {
    $pdo = new PDO("pgsql:host=localhost;port=5432;dbname=$db_name", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    $pdo->exec("SET TIME ZONE 'Europe/Paris'");
    date_default_timezone_set('Europe/Paris');

    // 3a. Récupération des véhicules
    $stmt_cars = $pdo->query("SELECT id, COALESCE(name, model) as display_name FROM cars ORDER BY id ASC");
    $cars = $stmt_cars->fetchAll(PDO::FETCH_ASSOC);
    
    // Déterminer le véhicule sélectionné (par défaut le premier)
    $selected_car_id = $_GET['car_id'] ?? ($cars[0]['id'] ?? null);

    if ($selected_car_id) {
        // 3b. Requête des Trajets filtrée par voiture
        $sql_trajets = "SELECT d.id, 
                               (d.start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') as start_date_local, 
                               ROUND(d.distance::numeric, 1) as km,
                               a_e.display_name as end_point
                        FROM drives d
                        LEFT JOIN addresses a_e ON d.end_address_id = a_e.id
                        WHERE d.car_id = :car_id 
                        AND DATE(d.start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') = :date
                        ORDER BY d.start_date DESC";
        
        $stmt = $pdo->prepare($sql_trajets);
        $stmt->execute(['car_id' => $selected_car_id, 'date' => $selected_date]);
        $trajets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($trajets as $t) $total_km += $t['km'];

        // 3c. Requête des Charges filtrée par voiture
        $sql_charge = "SELECT SUM(charge_energy_added) as kwh 
                       FROM charging_processes 
                       WHERE car_id = :car_id 
                       AND DATE(end_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') = :date";
        
        $stmt_c = $pdo->prepare($sql_charge);
        $stmt_c->execute(['car_id' => $selected_car_id, 'date' => $selected_date]);
        $total_kwh = round((float)($stmt_c->fetchColumn() ?? 0), 1);

        // 3d. Positions GPS pour la carte
        if ($selected_drive_id) {
            $stmt_p = $pdo->prepare("SELECT latitude, longitude, speed FROM positions WHERE drive_id = ? AND latitude IS NOT NULL ORDER BY date ASC");
            $stmt_p->execute([$selected_drive_id]);
            $positions = $stmt_p->fetchAll(PDO::FETCH_ASSOC);
        }
    }

} catch (PDOException $e) { 
    $error_message = "Erreur SQL : " . $e->getMessage(); 
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Historique</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { font-family: -apple-system, sans-serif; background: #111; color: #eee; margin: 0; display: flex; height: 100vh; overflow: hidden; }
        #sidebar { width: 380px; background: #1c1c1c; border-right: 1px solid #333; display: flex; flex-direction: column; z-index: 10; }
        #map-container { flex: 1; position: relative; background: #000; }
        #map { height: 100%; width: 100%; }
        
        .header { padding: 15px 20px; background: #252525; border-bottom: 1px solid #333; }
        .top-nav { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .back-button { 
            width: 35px; height: 35px; background: rgba(255,255,255,0.1); border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; transition: 0.3s; text-decoration: none;
        }
        .back-button:hover { background: #dc2626; }
        .back-button svg { width: 20px; height: 20px; stroke: white; fill: none; stroke-width: 2.5; }
        
        label { display: block; font-size: 10px; color: #888; text-transform: uppercase; margin-bottom: 5px; margin-top: 10px; }
        .input-field { width: 100%; padding: 10px; background: #333; border: 1px solid #444; color: white; border-radius: 4px; box-sizing: border-box; outline: none; margin-bottom: 10px; }
        
        .stats-bar { padding: 15px 20px; background: #222; border-bottom: 1px solid #333; }
        .stats-date { font-size: 14px; font-weight: bold; color: #fff; margin-bottom: 8px; text-transform: capitalize; }
        .stats-grid { display: flex; justify-content: space-between; font-size: 13px; }
        .km-val { color: #dc2626; font-weight: bold; font-size: 15px; }
        .kwh-val { color: #3b82f6; font-weight: bold; font-size: 15px; }

        .list-container { flex: 1; overflow-y: auto; padding: 10px; }
        .trajet-card { 
            background: #2a2a2a; border-radius: 8px; padding: 15px; margin-bottom: 10px; 
            cursor: pointer; border: 2px solid transparent; transition: 0.2s; 
        }
        .trajet-card:hover { background: #333; }
        .trajet-card.active { border-color: #dc2626; background: #331a1a; }
        .trajet-header { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .time { font-weight: bold; color: #fff; }
        .addr { font-size: 11px; color: #aaa; line-height: 1.4; }

        .legend { 
            position: absolute; bottom: 30px; right: 20px; background: rgba(0,0,0,0.85); 
            padding: 12px; border-radius: 8px; border: 1px solid #444; z-index: 1000; font-size: 11px;
        }
        .legend-item { display: flex; align-items: center; margin-bottom: 4px; gap: 8px; }
        .legend-color { width: 12px; height: 12px; border-radius: 2px; }
    </style>
</head>
<body>

<div id="sidebar">
    <div class="header">
        <div class="top-nav">
            <a href="tesla.php" class="back-button">
                <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            </a>
            <h1 style="margin:0; font-size:18px; color:#dc2626;">Historique</h1>
        </div>

        <label>Véhicule</label>
        <select class="input-field" onchange="location.href='?date=<?= $selected_date ?>&car_id='+this.value">
            <?php foreach ($cars as $car): ?>
                <option value="<?= $car['id'] ?>" <?= $selected_car_id == $car['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($car['display_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Date</label>
        <input type="date" class="input-field" value="<?= $selected_date ?>" onchange="location.href='?car_id=<?= $selected_car_id ?>&date='+this.value">
    </div>

    <div class="stats-bar">
        <div class="stats-date"><?= date('d M Y', strtotime($selected_date)) ?></div>
        <div class="stats-grid">
            <span>Distance : <span class="km-val"><?= number_format($total_km, 1, ',', ' ') ?> km</span></span>
            <span>Chargé : <span class="kwh-val"><?= number_format($total_kwh, 1, ',', ' ') ?> kWh</span></span>
        </div>
    </div>

    <div class="list-container">
        <?php if ($error_message) echo "<div style='color:#ff8888; padding:10px;'>$error_message</div>"; ?>
        <?php if (empty($trajets) && !$error_message) echo "<p style='text-align:center; color:#666;'>Aucun trajet enregistré.</p>"; ?>
        
        <?php foreach ($trajets as $t): ?>
            <div class="trajet-card <?= $selected_drive_id == $t['id'] ? 'active' : '' ?>" 
                 onclick="location.href='?car_id=<?= $selected_car_id ?>&date=<?= $selected_date ?>&drive_id=<?= $t['id'] ?>'">
                <div class="trajet-header">
                    <span class="time"><?= date('H:i', strtotime($t['start_date_local'])) ?></span>
                    <span class="km-val"><?= $t['km'] ?> km</span>
                </div>
                <div class="addr">Vers : <?= htmlspecialchars($t['end_point'] ?? 'Inconnu') ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="map-container">
    <div id="map"></div>
    <?php if (!empty($positions)): ?>
    <div class="legend">
        <div class="legend-item"><div class="legend-color" style="background:#22c55e;"></div><span>0-30 km/h</span></div>
        <div class="legend-item"><div class="legend-color" style="background:#eab308;"></div><span>31-50 km/h</span></div>
        <div class="legend-item"><div class="legend-color" style="background:#f97316;"></div><span>51-80 km/h</span></div>
        <div class="legend-item"><div class="legend-color" style="background:#dc2626;"></div><span>> 110 km/h</span></div>
    </div>
    <?php endif; ?>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const map = L.map('map').setView([46.6, 2.2], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OSM' }).addTo(map);

    function getColor(speed) {
        if (speed <= 30) return '#22c55e';
        if (speed <= 50) return '#eab308';
        if (speed <= 80) return '#f97316';
        if (speed <= 110) return '#92400e';
        return '#dc2626';
    }

    <?php if (!empty($positions)): ?>
        const pos = <?= json_encode($positions) ?>;
        for (let i = 0; i < pos.length - 1; i++) {
            L.polyline([[pos[i].latitude, pos[i].longitude], [pos[i+1].latitude, pos[i+1].longitude]], {
                color: getColor(parseFloat(pos[i].speed || 0)),
                weight: 5, opacity: 0.9
            }).addTo(map);
        }
        const pts = pos.map(p => [p.latitude, p.longitude]);
        map.fitBounds(L.latLngBounds(pts), {padding: [50, 50]});
        
        const departIcon = L.divIcon({
            className: 'custom-marker',
            html: '<div style="background:#22c55e; color:#fff; width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:16px; border:3px solid #fff; box-shadow:0 2px 8px rgba(0,0,0,0.5);">D</div>',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });
        
        const arriveeIcon = L.divIcon({
            className: 'custom-marker',
            html: '<div style="background:#dc2626; color:#fff; width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:16px; border:3px solid #fff; box-shadow:0 2px 8px rgba(0,0,0,0.5);">A</div>',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });
        
        L.marker(pts[0], {icon: departIcon}).addTo(map).bindPopup("Départ");
        L.marker(pts[pts.length - 1], {icon: arriveeIcon}).addTo(map).bindPopup("Arrivée");
    <?php endif; ?>
</script>
</body>
</html>
