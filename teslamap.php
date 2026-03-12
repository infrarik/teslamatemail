<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- 1. CONFIGURATION & SETUP ---
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

$lang = (isset($config['LANGUAGE']) && strtolower($config['LANGUAGE']) === 'en') ? 'en' : 'fr';
$txt = [
    'fr' => [
        'title' => 'Historique', 'vehicle' => 'Véhicule', 'date' => 'Date', 'back' => 'Retour', 'to' => 'Vers',
        'unknown' => 'Inconnu', 'start' => 'D', 'end' => 'A', 'btn_kml' => 'EXPORT GOOGLE EARTH (KML)',
        'btn_3d' => 'CARTE DES ALTITUDES (3D)', 'btn_2d' => 'RETOURNER À LA CARTE (2D)', 'btn_full_day' => 'JOURNÉE ENTIÈRE',
        'total_km' => 'Distance Totale', 'search_placeholder' => 'Rechercher une ville...', 'btn_snap' => '📸 CAPTURE',
        'legend_speed' => 'Vitesses (km/h)', 'vmax' => 'V.Max', 'elev_gain' => 'D+', 'duration' => 'Durée', 
        'elev_title' => 'Dénivelé cumulé positif', 'layer_plan' => 'Plan seul', 'layer_sat' => 'Satellite seul', 'layer_hybrid' => 'Mixte (Satellite + Noms)',
        'battery' => 'Batterie'
    ],
    'en' => [
        'title' => 'History', 'vehicle' => 'Vehicle', 'date' => 'Date', 'back' => 'Back', 'to' => 'To',
        'unknown' => 'Unknown', 'start' => 'S', 'end' => 'E', 'btn_kml' => 'EXPORT GOOGLE EARTH (KML)',
        'btn_3d' => 'ALTITUDE MAP (3D)', 'btn_2d' => 'BACK TO MAP (2D)', 'btn_full_day' => 'FULL DAY VIEW',
        'total_km' => 'Total Distance', 'search_placeholder' => 'Search city...', 'btn_snap' => '📸 SNAP',
        'legend_speed' => 'Speed (km/h)', 'vmax' => 'Max Spd', 'elev_gain' => 'Gain', 'duration' => 'Duration', 
        'elev_title' => 'Total elevation gain', 'layer_plan' => 'Map only', 'layer_sat' => 'Satellite only', 'layer_hybrid' => 'Hybrid (Sat + Names)',
        'battery' => 'Battery'
    ]
][$lang];

// --- 2. BASE DE DONNÉES ---
$server_ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';

if (!empty($config['DOCKER_PATH']) && file_exists($config['DOCKER_PATH'])) {
    $docker_content = file_get_contents($config['DOCKER_PATH']);
    if (preg_match('/POSTGRES_USER[:=]\s*(\S+)/', $docker_content, $m)) $db_user = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_PASSWORD[:=]\s*(\S+)/', $docker_content, $m)) $db_pass = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_DB[:=]\s*(\S+)/', $docker_content, $m)) $db_name = str_replace(['"', "'"], '', trim($m[1]));
}

// --- 3. EXPORT KML ---
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
$trajets = []; $positions = []; $cars = []; $pauses = [];
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_drive_id = $_GET['drive_id'] ?? null;
$search_query = $_GET['search'] ?? null;
$search_lat = $_GET['lat'] ?? null;
$search_lng = $_GET['lng'] ?? null;
$is_full_day = isset($_GET['full_day']) || (!$selected_drive_id && isset($_GET['date']) && !$search_query);
$total_day_km = 0; $v_max_trip = 0; $elev_gain_trip = 0; $trip_duration_str = "--";

try {
    $pdo = new PDO("pgsql:host=$server_ip;port=5432;dbname=$db_name", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("SET TIME ZONE 'Europe/Paris'");
    $cars = $pdo->query("SELECT id, COALESCE(name, model) as display_name FROM cars ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $selected_car_id = $_GET['car_id'] ?? ($cars[0]['id'] ?? null);

    if ($selected_car_id) {
        if ($search_query && $search_lat && $search_lng) {
            $sql = "SELECT d.id, (d.start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') as start_date_local, ROUND(d.distance::numeric, 1) as km, a_e.display_name as end_point, DATE(d.start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') as trip_date FROM drives d LEFT JOIN addresses a_e ON d.end_address_id = a_e.id JOIN positions p ON p.drive_id = d.id WHERE d.car_id = :car_id AND (6371 * acos(cos(radians(:lat)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians(:lng)) + sin(radians(:lat)) * sin(radians(p.latitude)))) < 5 GROUP BY d.id, a_e.display_name, trip_date ORDER BY d.start_date DESC LIMIT 50";
            $stmt = $pdo->prepare($sql); $stmt->execute(['car_id' => $selected_car_id, 'lat' => $search_lat, 'lng' => $search_lng]);
        } else {
            $sql = "SELECT d.id, (d.start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') as start_date_local, (d.end_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') as end_date_local, ROUND(d.distance::numeric, 1) as km, a_e.display_name as end_point FROM drives d LEFT JOIN addresses a_e ON d.end_address_id = a_e.id WHERE d.car_id = :car_id AND DATE(d.start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') = :date ORDER BY d.start_date ASC";
            $stmt = $pdo->prepare($sql); $stmt->execute(['car_id' => $selected_car_id, 'date' => $selected_date]);
        }
        $trajets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fonction helper pour chercher une charge après un trajet
        $fn_get_charge = function($drive_end_local, $car_id, $next_start_local = null) use ($pdo) {
            $extra = $next_start_local ? "AND (end_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') <= :start_next" : "";
            $params = ['car_id' => $car_id, 'end_prev' => $drive_end_local];
            if ($next_start_local) $params['start_next'] = $next_start_local;
            $stmt_ch = $pdo->prepare("SELECT charge_energy_added, charge_energy_used, EXTRACT(EPOCH FROM (end_date - start_date))/60 as duree_charge FROM charging_processes WHERE car_id = :car_id AND (start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') >= :end_prev $extra AND charge_energy_added > 0 ORDER BY start_date ASC LIMIT 1");
            $stmt_ch->execute($params);
            return $stmt_ch->fetch(PDO::FETCH_ASSOC);
        };

        if ($is_full_day) {
            for ($i = 0; $i < count($trajets) - 1; $i++) {
                $end_prev = strtotime($trajets[$i]['end_date_local']);
                $start_next = strtotime($trajets[$i+1]['start_date_local']);
                $diff = ($start_next - $end_prev) / 60;
                if ($diff >= 5) {
                    $stmt_p = $pdo->prepare("SELECT latitude, longitude FROM positions WHERE drive_id = ? ORDER BY date DESC LIMIT 1");
                    $stmt_p->execute([$trajets[$i]['id']]);
                    $p_loc = $stmt_p->fetch(PDO::FETCH_ASSOC);
                    if ($p_loc) {
                        $charge = $fn_get_charge($trajets[$i]['end_date_local'], $selected_car_id, $trajets[$i+1]['start_date_local']);
                        $pauses[] = [
                            'lat'          => $p_loc['latitude'],
                            'lng'          => $p_loc['longitude'],
                            'dur'          => round($diff),
                            'is_charge'    => $charge ? true : false,
                            'kwh_added'    => $charge ? round($charge['charge_energy_added'], 1) : 0,
                            'kwh_used'     => $charge ? round($charge['charge_energy_used'], 1) : 0,
                            'duree_charge' => $charge ? round($charge['duree_charge']) : 0,
                        ];
                    }
                }
            }
        } elseif ($selected_drive_id) {
            // Vue trajet individuel : chercher une charge après la fin du trajet
            $current_drive = null;
            foreach ($trajets as $t) {
                if ($t['id'] == $selected_drive_id) { $current_drive = $t; break; }
            }
            if ($current_drive && !empty($current_drive['end_date_local'])) {
                // Borne de fin : trajet suivant s'il existe, sinon +2h max
                $next_drive = null;
                foreach ($trajets as $t) {
                    if (strtotime($t['start_date_local']) > strtotime($current_drive['end_date_local'])) {
                        $next_drive = $t;
                        break;
                    }
                }
                $charge_end_limit = $next_drive
                    ? $next_drive['start_date_local']
                    : date('Y-m-d H:i:s', strtotime($current_drive['end_date_local']) + 7200);
                $charge = $fn_get_charge($current_drive['end_date_local'], $selected_car_id, $charge_end_limit);
                if ($charge) {
                    $stmt_p = $pdo->prepare("SELECT latitude, longitude FROM positions WHERE drive_id = ? ORDER BY date DESC LIMIT 1");
                    $stmt_p->execute([$selected_drive_id]);
                    $p_loc = $stmt_p->fetch(PDO::FETCH_ASSOC);
                    if ($p_loc) {
                        $pauses[] = [
                            'lat'          => $p_loc['latitude'],
                            'lng'          => $p_loc['longitude'],
                            'dur'          => 0,
                            'is_charge'    => true,
                            'kwh_added'    => round($charge['charge_energy_added'], 1),
                            'kwh_used'     => round($charge['charge_energy_used'], 1),
                            'duree_charge' => round($charge['duree_charge']),
                        ];
                    }
                }
            }
        }

        foreach($trajets as $t) $total_day_km += $t['km'];

        if ($selected_drive_id || ($is_full_day && !$search_query)) {
            $sql_p = $selected_drive_id ? "SELECT date, latitude, longitude, elevation, speed, outside_temp, inside_temp, drive_id, battery_level FROM positions WHERE drive_id = ? AND latitude IS NOT NULL ORDER BY date ASC" 
                                      : "SELECT p.date, p.latitude, p.longitude, p.elevation, p.speed, p.outside_temp, p.inside_temp, p.drive_id, p.battery_level FROM positions p JOIN drives d ON p.drive_id = d.id WHERE d.car_id = ? AND DATE(d.start_date AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris') = ? AND p.latitude IS NOT NULL ORDER BY p.date ASC";
            $stmt_p = $pdo->prepare($sql_p);
            $stmt_p->execute($selected_drive_id ? [$selected_drive_id] : [$selected_car_id, $selected_date]);
            $positions = $stmt_p->fetchAll(PDO::FETCH_ASSOC);

            if (count($positions) > 1) {
                $start_time = strtotime($positions[0]['date']);
                $end_time = strtotime($positions[count($positions)-1]['date']);
                $diff_sec = $end_time - $start_time;
                $h = floor($diff_sec / 3600);
                $m = floor(($diff_sec % 3600) / 60);
                $trip_duration_str = ($h > 0) ? $h."h".str_pad($m, 2, '0', STR_PAD_LEFT) : $m."min";
            }

            foreach ($positions as $idx => $p) {
                if ($p['speed'] > $v_max_trip) $v_max_trip = $p['speed'];
                if ($idx > 0 && $p['elevation'] > $positions[$idx-1]['elevation']) {
                    $elev_gain_trip += ($p['elevation'] - $positions[$idx-1]['elevation']);
                }
            }
        }
    }
} catch (PDOException $e) { die($e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $txt['title'] ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://cdn.plot.ly/plotly-2.24.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
        .search-icons { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); display: flex; gap: 8px; align-items: center; }
        .search-icons svg { width: 18px; height: 18px; fill: #888; cursor: pointer; transition: 0.2s; }
        .search-icons svg:hover { fill: #fff; }

        label { display: block; font-size: 10px; color: #888; text-transform: uppercase; margin: 10px 0 5px; }
        .input-field { width: 100%; padding: 10px; background: #333; border: 1px solid #444; color: white; border-radius: 4px; box-sizing: border-box; margin-bottom: 10px; }
        
        .btn-action { display: block; padding: 12px; margin: 5px 0; border-radius: 6px; text-align: center; text-decoration: none; font-weight: bold; font-size: 11px; text-transform: uppercase; cursor: pointer; border: none; width: 100%; color: white; }
        .btn-kml { background: #dc2626; } .btn-3d { background: #3b82f6; } .btn-2d { background: #444; }
        .btn-full-day { background: #444; } .btn-full-day.active { background: #059669; }
        
        .summary-day { background: #059669; color: white; padding: 15px; margin: 10px; border-radius: 8px; text-align: center; }
        .trajet-card { background: #2a2a2a; border-radius: 8px; padding: 15px; margin: 10px; cursor: pointer; border: 2px solid transparent; }
        .trajet-card.active { border-color: #dc2626; background: #331a1a; }
        
        #replay-bar { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.85); padding: 15px; border-radius: 30px; display: flex; align-items: center; gap: 12px; z-index: 1000; border: 1px solid #444; }
        .play-btn, .save-video-btn { background: #dc2626; border: none; color: white; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; }
        .save-video-btn { background: #3b82f6; }

        #gen-overlay { display: none; position: absolute; inset: 0; background: rgba(0,0,0,0.9); z-index: 2000; flex-direction: column; align-items: center; justify-content: center; }
        .progress-box { width: 300px; background: #333; height: 8px; border-radius: 4px; margin-top: 15px; overflow: hidden; }
        .progress-bar { width: 0%; height: 100%; background: #3b82f6; transition: 0.1s; }
        
        #info-box { 
            position: absolute; top: 15px; left: 50%; transform: translateX(-50%); 
            z-index: 1000; background: rgba(0,0,0,0.85); color: white; 
            padding: 10px 20px; border-radius: 20px; font-size: 13px; 
            border: 1px solid #444; display: none; pointer-events: none;
            white-space: nowrap; box-shadow: 0 4px 15px rgba(0,0,0,0.5);
        }
        #info-box span.item { margin: 0 10px; }
        #info-box .stats-divider { border-left: 1px solid #555; height: 20px; margin: 0 10px; }

        #speed-legend {
            position: absolute; bottom: 25px; right: 25px; 
            z-index: 1000; background: rgba(0,0,0,0.85); color: white; 
            padding: 12px; border-radius: 8px; font-size: 11px; 
            border: 1px solid #444; display: none;
        }
        .legend-title { font-weight: bold; margin-bottom: 8px; font-size: 10px; text-transform: uppercase; color: #888; border-bottom: 1px solid #333; padding-bottom: 4px; }
        .legend-row { display: flex; align-items: center; margin-bottom: 4px; gap: 8px; }
        .color-dot { width: 10px; height: 10px; border-radius: 2px; }

        .custom-marker { display: flex; align-items: center; justify-content: center; font-weight: bold; color: white; border: 2px solid white; border-radius: 50%; width: 22px; height: 22px; font-size: 10px; cursor: pointer; }
        .pause-marker { background: #3b82f6; border: 2px solid #fff; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 10px; color: white; box-shadow: 0 0 10px rgba(0,0,0,0.5); }
        .charge-marker { background: #f59e0b; border: 2px solid #fff; border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; font-size: 14px; box-shadow: 0 0 10px rgba(245,158,11,0.6); cursor: pointer; }
        .snap-btn { position: absolute; top: 15px; right: 50px; z-index: 1000; padding: 8px 12px; background: rgba(5, 150, 105, 0.85); color: white; border-radius: 6px; font-size: 11px; cursor: pointer; border: 1px solid rgba(255,255,255,0.2); }

        /* ===== RESPONSIVE MOBILE / TABLETTE ===== */
        @media (max-width: 768px) {
            body { flex-direction: column; height: 100dvh; overflow: hidden; }

            /* Sidebar devient un panneau compact en haut */
            #sidebar {
                width: 100%;
                height: auto;
                max-height: 42vh;
                border-right: none;
                border-bottom: 2px solid #333;
                overflow-y: auto;
                flex-shrink: 0;
            }

            /* Header plus compact */
            .header { padding: 10px 14px; }
            .top-nav { margin-bottom: 8px; gap: 10px; }
            h1 { font-size: 16px !important; }
            .back-button { width: 32px; height: 32px; flex-shrink: 0; }

            /* Recherche + champs plus compacts */
            .search-box { margin-bottom: 6px; }
            .search-box input { padding: 8px 50px 8px 10px; font-size: 14px; }
            label { margin: 6px 0 3px; font-size: 9px; }
            .input-field { padding: 8px 10px; font-size: 14px; margin-bottom: 6px; }

            /* Boutons action : rangée horizontale scrollable */
            .header > div[style*="margin-top:10px"] { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 4px; }
            .btn-action { 
                display: inline-block !important; 
                width: auto !important; 
                padding: 9px 12px; 
                font-size: 10px; 
                white-space: nowrap; 
                flex-shrink: 0;
                margin: 0 !important;
            }
            .btn-full-day { width: 100% !important; display: block !important; margin: 0 0 4px !important; }

            /* Liste des trajets : horizontale scrollable */
            #sidebar > div[style*="flex:1"] {
                display: flex;
                flex-direction: row;
                overflow-x: auto;
                overflow-y: hidden;
                padding: 6px 10px;
                gap: 8px;
                flex: none;
            }
            .trajet-card {
                min-width: 130px;
                max-width: 150px;
                padding: 10px;
                margin: 0;
                flex-shrink: 0;
                font-size: 13px;
            }
            .summary-day { 
                margin: 6px 10px; 
                padding: 10px; 
                flex-shrink: 0; 
            }
            .summary-day > div:first-child { font-size: 9px; }
            .summary-day > div:last-child { font-size: 20px !important; }

            /* Zone carte : prend le reste */
            #main-viz { flex: 1; min-height: 0; position: relative; }
            #map, #plot3d { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }

            /* Info-box : plus petite, adaptée au tactile */
            #info-box {
                top: 8px;
                left: 8px;
                right: 8px;
                transform: none;
                width: auto;
                font-size: 11px;
                padding: 7px 12px;
                white-space: normal;
                flex-wrap: wrap;
                gap: 4px;
                justify-content: center;
            }
            #info-box span.item { margin: 0 4px; }
            #info-box .stats-divider { display: none; }

            /* Légende vitesse : repositionnée en bas à droite, plus petite */
            #speed-legend {
                bottom: 80px;
                right: 10px;
                font-size: 10px;
                padding: 8px;
            }

            /* Replay bar : plus compacte, centrée en bas */
            #replay-bar {
                bottom: 12px;
                padding: 10px 14px;
                gap: 8px;
                max-width: calc(100% - 40px);
            }
            #replay-bar input[type="range"] { width: 100px; min-width: 80px; }
            .play-btn, .save-video-btn { width: 34px; height: 34px; font-size: 15px; flex-shrink: 0; }

            /* Snap button */
            .snap-btn { top: auto; bottom: 90px; right: 10px; font-size: 10px; padding: 7px 10px; }

            /* Contrôle couches Leaflet : déplacé plus haut pour ne pas chevaucher replay */
            .leaflet-top.leaflet-right { top: 8px; }
        }

        /* Petits mobiles */
        @media (max-width: 420px) {
            #sidebar { max-height: 45vh; }
            #info-box { font-size: 10px; }
            .trajet-card { min-width: 115px; font-size: 12px; }
        }
    </style>
</head>
<body>

<div id="gen-overlay">
    <div style="font-weight:bold; font-size:18px; color:#fff;">GÉNÉRATION VIDÉO COMPATIBLE</div>
    <div id="gen-status" style="margin-top:5px; font-size:12px; color:#aaa;">Préparation...</div>
    <div class="progress-box"><div id="p-bar" class="progress-bar"></div></div>
</div>

<div id="sidebar">
    <div class="header">
        <div class="top-nav">
            <a href="tesla.php" class="back-button"><svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg></a>
            <h1 style="margin:0; font-size:18px; color:#dc2626;"><?= $txt['title'] ?></h1>
        </div>

        <div class="search-box">
            <input type="text" id="citySearch" placeholder="<?= $txt['search_placeholder'] ?>" value="<?= htmlspecialchars($search_query ?? '') ?>" onkeypress="if(event.key === 'Enter') searchCity()">
            <div class="search-icons">
                <?php if($search_query): ?>
                    <svg class="close-icon" onclick="clearSearch()" viewBox="0 0 24 24" style="fill:#dc2626;"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
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
        
        <a href="?car_id=<?= $selected_car_id ?>&date=<?= $selected_date ?>&full_day=1" class="btn-action btn-full-day <?= ($is_full_day && !$selected_drive_id) ? 'active' : '' ?>"><?= $txt['btn_full_day'] ?></a>
        
        <?php if (!empty($positions)): ?>
            <div style="margin-top:10px;">
                <a href="?export_kml=1&<?= $selected_drive_id ? "drive_id=$selected_drive_id" : "car_id=$selected_car_id&date=$selected_date" ?>" class="btn-action btn-kml"><?= $txt['btn_kml'] ?></a>
                <button id="btnShow3D" class="btn-action btn-3d" onclick="toggleVision('3D')"><?= $txt['btn_3d'] ?></button>
                <button id="btnShow2D" class="btn-action btn-2d" style="display:none;" onclick="toggleVision('2D')"><?= $txt['btn_2d'] ?></button>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($is_full_day && !$selected_drive_id): ?>
        <div class="summary-day">
            <div style="font-size:10px; opacity:0.8;"><?= $txt['total_km'] ?></div>
            <div style="font-size:24px; font-weight:800;"><?= number_format($total_day_km, 1, ',', ' ') ?> km</div>
        </div>
    <?php endif; ?>

    <div style="flex:1; overflow-y:auto;">
        <?php foreach ($trajets as $t): ?>
            <?php $trip_url = "?car_id=$selected_car_id&drive_id={$t['id']}&date=$selected_date" . ($search_query ? "&search=".urlencode($search_query)."&lat=$search_lat&lng=$search_lng" : ""); ?>
            <div class="trajet-card <?= $selected_drive_id == $t['id'] ? 'active' : '' ?>" onclick="location.href='<?= $trip_url ?>'">
                <div style="display:flex; justify-content:space-between;">
                    <span style="font-weight:bold;"><?= date('H:i', strtotime($t['start_date_local'])) ?></span>
                    <span style="color:#dc2626; font-weight:bold;"><?= $t['km'] ?> km</span>
                </div>
                <div style="font-size:11px; color:#aaa; margin-top:5px;"><?= $txt['to'] ?> : <?= htmlspecialchars($t['end_point'] ?? $txt['unknown']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="main-viz">
    <button class="snap-btn" onclick="takeSnapshot()"><?= $txt['btn_snap'] ?></button>
    
    <div id="info-box">
        <span class="item">⏱️ <b><?= $txt['duration'] ?>:</b> <?= $trip_duration_str ?></span>
        <span class="item">🌡️ <span id="info-temp">--</span>°C</span>
        <span class="item">⏲️ <span id="info-speed">--</span> km/h</span>
        <span class="item">⛰️ <span id="info-elevation">--</span> m</span>
        <div class="stats-divider"></div>
        <span class="item">🏁 <b><?= $txt['vmax'] ?>:</b> <?= round($v_max_trip) ?> km/h</span>
        <span class="item" title="<?= $txt['elev_title'] ?>">📈 <b><?= $txt['elev_gain'] ?>:</b> <?= round($elev_gain_trip) ?> m</span>
    </div>

    <div id="map"></div>
    <div id="plot3d"></div>

    <div id="speed-legend">
        <div class="legend-title"><?= $txt['legend_speed'] ?></div>
        <div class="legend-row"><div class="color-dot" style="background:#7f1d1d;"></div> > 131</div>
        <div class="legend-row"><div class="color-dot" style="background:#dc2626;"></div> 111 - 130</div>
        <div class="legend-row"><div class="color-dot" style="background:#ea580c;"></div> 91 - 110</div>
        <div class="legend-row"><div class="color-dot" style="background:#f97316;"></div> 81 - 90</div>
        <div class="legend-row"><div class="color-dot" style="background:#eab308;"></div> 71 - 80</div>
        <div class="legend-row"><div class="color-dot" style="background:#84cc16;"></div> 51 - 70</div>
        <div class="legend-row"><div class="color-dot" style="background:#22c55e;"></div> 0 - 50</div>
    </div>

    <?php if(!empty($positions)): ?>
    <div id="replay-bar">
        <button class="play-btn" id="playBtn" onclick="togglePlay()">▶</button>
        <select id="speedSelector" style="background: #333; color: white; border: 1px solid #444; border-radius: 4px; padding: 5px; font-size: 11px; cursor: pointer;">
            <option value="1">x1</option>
            <option value="5">x5</option>
            <option value="10" selected>x10</option>
            <option value="20">x20</option>
        </select>
        <button class="save-video-btn" title="Générer Vidéo" onclick="generateVideoFast()">💾</button>
        <input type="range" id="replayRange" min="0" max="<?= count($positions)-1 ?>" value="0" oninput="updateCarPos(this.value)">
    </div>
    <?php endif; ?>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const pos = <?= json_encode($positions) ?>;
    const isSingleDrive = <?= $selected_drive_id ? 'true' : 'false' ?>;
    const pauses = <?= json_encode($pauses) ?>;
    
    // Configuration des couches de carte
    const nightLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { crossOrigin: 'anonymous' });
    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri', crossOrigin: 'anonymous'
    });
    const hybridLayer = L.layerGroup([
        satelliteLayer,
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_only_labels/{z}/{x}/{y}{r}.png', { 
            pane: 'shadowPane', crossOrigin: 'anonymous' 
        })
    ]);

    const map = L.map('map', { 
        preferCanvas: true,
        layers: [nightLayer]
    }).setView([46, 2], 6);

    const baseMaps = {
        "<?= $txt['layer_plan'] ?>": nightLayer,
        "<?= $txt['layer_sat'] ?>": satelliteLayer,
        "<?= $txt['layer_hybrid'] ?>": hybridLayer
    };
    L.control.layers(baseMaps, null, {position: 'topright'}).addTo(map);

    let carMarker = L.circleMarker([0,0], {radius: 8, color: '#fff', fillColor: '#dc2626', fillOpacity: 1, weight: 3, zIndexOffset:1000}).addTo(map);
    let pathLine = null;
    let playInterval = null;

    function getSpeedColor(speed) {
        if (speed <= 50) return '#22c55e';
        if (speed <= 70) return '#84cc16';
        if (speed <= 80) return '#eab308';
        if (speed <= 90) return '#f97316';
        if (speed <= 110) return '#ea580c';
        if (speed <= 130) return '#dc2626';
        return '#7f1d1d';
    }

    if(pos.length > 0) {
        document.getElementById('speed-legend').style.display = 'block';
        let latlngs = pos.map(p => [parseFloat(p.latitude), parseFloat(p.longitude)]);
        
        for(let i = 0; i < pos.length - 1; i++) {
            const speed = parseFloat(pos[i].speed) || 0;
            const nextSpeed = parseFloat(pos[i+1].speed) || 0;
            const avgSpeed = (speed + nextSpeed) / 2;
            let color = getSpeedColor(avgSpeed);
            
            let segment = L.polyline([
                [parseFloat(pos[i].latitude), parseFloat(pos[i].longitude)],
                [parseFloat(pos[i+1].latitude), parseFloat(pos[i+1].longitude)]
            ], {color: color, weight: 6, opacity: 0.8}).addTo(map);

            segment.on('mouseover', function() {
                document.getElementById('info-box').style.display = 'flex';
                this.setStyle({weight: 12, opacity: 1});
                updateInfoBox(i);
            });
            segment.on('mouseout', function() {
                this.setStyle({weight: 6, opacity: 0.8});
            });
        }
        
        pathLine = L.polyline(latlngs, {color: 'transparent', weight: 15, zIndex: 500}).addTo(map);
        pathLine.on('mousemove', function(e) {
            let minDest = Infinity;
            let closestIdx = 0;
            pos.forEach((p, idx) => {
                let d = e.latlng.distanceTo([p.latitude, p.longitude]);
                if(d < minDest) { minDest = d; closestIdx = idx; }
            });
            document.getElementById('info-box').style.display = 'flex';
            updateInfoBox(closestIdx);
        });

        if(isSingleDrive) {
            const batStart = pos[0].battery_level ? ` | <?= $txt['battery'] ?>: ${pos[0].battery_level}%` : '';
            const batEnd = pos[pos.length-1].battery_level ? ` | <?= $txt['battery'] ?>: ${pos[pos.length-1].battery_level}%` : '';

            L.marker(latlngs[0], {
                icon: L.divIcon({className:'custom-marker', html:'<?= $txt['start'] ?>', iconAnchor:[11,11]}),
                title: '<?= $txt['start'] ?>' + batStart
            }).addTo(map).getElement().style.backgroundColor='#22c55e';
            
            L.marker(latlngs[latlngs.length-1], {
                icon: L.divIcon({className:'custom-marker', html:'<?= $txt['end'] ?>', iconAnchor:[11,11]}),
                title: '<?= $txt['end'] ?>' + batEnd
            }).addTo(map).getElement().style.backgroundColor='#dc2626';
            // Afficher l'éclair de charge après le trajet (si applicable)
            pauses.forEach(p => {
                if (p.is_charge) {
                    const h = Math.floor(p.duree_charge / 60);
                    const m = p.duree_charge % 60;
                    const dureeStr = h > 0 ? `${h}h${String(m).padStart(2,'0')}` : `${m} min`;
                    const tooltip = `⚡ Charge : ${p.kwh_added} kWh ajoutés / ${p.kwh_used} kWh consommés\n⏱️ Durée : ${dureeStr}`;
                    L.marker([p.lat, p.lng], {
                        icon: L.divIcon({className: 'charge-marker', html: '⚡', iconAnchor: [13, 13]}),
                        title: tooltip
                    }).addTo(map).bindPopup(
                        `<b>⚡ Charge</b><br>` +
                        `Ajoutés : <b>${p.kwh_added} kWh</b><br>` +
                        `Consommés : <b>${p.kwh_used} kWh</b><br>` +
                        `Durée : <b>${dureeStr}</b>`
                    );
                }
            });
        } else {
            pauses.forEach(p => {
                if (p.is_charge) {
                    const h = Math.floor(p.duree_charge / 60);
                    const m = p.duree_charge % 60;
                    const dureeStr = h > 0 ? `${h}h${String(m).padStart(2,'0')}` : `${m} min`;
                    const tooltip = `⚡ Charge : ${p.kwh_added} kWh ajoutés / ${p.kwh_used} kWh consommés\n⏱️ Durée : ${dureeStr}`;
                    L.marker([p.lat, p.lng], {
                        icon: L.divIcon({className: 'charge-marker', html: '⚡', iconAnchor: [13, 13]}),
                        title: tooltip
                    }).addTo(map).bindPopup(
                        `<b>⚡ Charge</b><br>` +
                        `Ajoutés : <b>${p.kwh_added} kWh</b><br>` +
                        `Consommés : <b>${p.kwh_used} kWh</b><br>` +
                        `Durée : <b>${dureeStr}</b>`
                    );
                } else {
                    L.marker([p.lat, p.lng], {
                        icon: L.divIcon({className:'pause-marker', html:'P', iconAnchor:[10,10]})
                    }).addTo(map).bindPopup(`Pause de ${p.dur} min`);
                }
            });
        }

        map.fitBounds(L.latLngBounds(latlngs), {padding:[50,50]});
        updateCarPos(0);
    }

    function updateInfoBox(idx) {
        if(!pos[idx]) return;
        const infoBox = document.getElementById('info-box');
        infoBox.style.display = 'flex';
        
        const temp = pos[idx].outside_temp || pos[idx].inside_temp || null;
        document.getElementById('info-temp').textContent = temp !== null ? Math.round(temp) : '--';
        document.getElementById('info-speed').textContent = Math.round(pos[idx].speed || 0);
        document.getElementById('info-elevation').textContent = Math.round(pos[idx].elevation || 0);
    }

    function updateCarPos(idx) {
        if(!pos[idx]) return;
        carMarker.setLatLng([pos[idx].latitude, pos[idx].longitude]);
        document.getElementById('replayRange').value = idx;
        updateInfoBox(idx);
    }

    function togglePlay() {
        const btn = document.getElementById('playBtn');
        if(playInterval) { clearInterval(playInterval); playInterval = null; btn.innerText = "▶"; }
        else {
            btn.innerText = "⏸";
            playInterval = setInterval(() => {
                let r = document.getElementById('replayRange');
                let next = parseInt(r.value) + 1;
                if(next < pos.length) updateCarPos(next);
                else { clearInterval(playInterval); playInterval = null; btn.innerText = "▶"; }
            }, 50);
        }
    }

    async function generateVideoFast() {
        const overlay = document.getElementById('gen-overlay');
        const pBar = document.getElementById('p-bar');
        const statusText = document.getElementById('gen-status');
        overlay.style.display = 'flex';
        const speedMultiplier = parseInt(document.getElementById('speedSelector').value) || 10;
        
        document.getElementById('replay-bar').style.display = 'none';
        document.querySelector('.snap-btn').style.display = 'none';
        document.getElementById('speed-legend').style.display = 'none';

        const mapContainer = document.getElementById('map');
        const canvas = document.createElement('canvas');
        const bounds = mapContainer.getBoundingClientRect();
        canvas.width = bounds.width; canvas.height = bounds.height;
        const ctx = canvas.getContext('2d');
        const stream = canvas.captureStream(30);

        const recorder = new MediaRecorder(stream, { 
            mimeType: 'video/mp4;codecs=avc1.42E01E',
            videoBitsPerSecond: 5000000 
        });

        const chunks = [];
        recorder.ondataavailable = e => chunks.push(e.data);
        recorder.onstop = () => {
            const blob = new Blob(chunks, { type: 'video/mp4' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = `tesla_trip_${Date.now()}.mp4`;
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
            location.reload(); 
        };

        recorder.start();
        const totalFrames = pos.length;
        const captureStep = 3 * speedMultiplier;
        const framesToCapture = Math.ceil(totalFrames / captureStep);
        
        for(let frameIndex = 0; frameIndex < framesToCapture; frameIndex++) {
            const i = Math.min(frameIndex * captureStep, totalFrames - 1);
            updateCarPos(i);
            
            await new Promise(r => setTimeout(r, 20));
            const frameCanvas = await html2canvas(mapContainer, { useCORS: true, logging: false, scale: 1 });
            ctx.drawImage(frameCanvas, 0, 0, canvas.width, canvas.height);
            
            const progress = ((frameIndex + 1) / framesToCapture * 100);
            pBar.style.width = progress + "%";
            statusText.innerText = `Capture : ${Math.round(progress)}%`;
        }
        setTimeout(() => recorder.stop(), 1000);
    }

    async function searchCity() {
        const q = document.getElementById('citySearch').value;
        if (!q.trim()) return;
        const r = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=1`);
        const d = await r.json();
        if(d.length>0) location.href=`?car_id=<?= $selected_car_id ?>&search=${encodeURIComponent(q)}&lat=${d[0].lat}&lng=${d[0].lon}`;
    }

    function clearSearch() { location.href=`?car_id=<?= $selected_car_id ?>&date=<?= $selected_date ?>`; }

    function toggleVision(mode) {
        let is3D = mode === '3D';
        document.getElementById('map').style.display = is3D ? 'none' : 'block';
        document.getElementById('plot3d').style.display = is3D ? 'block' : 'none';
        document.getElementById('btnShow3D').style.display = is3D ? 'none' : 'block';
        document.getElementById('btnShow2D').style.display = is3D ? 'block' : 'none';
        document.getElementById('speed-legend').style.display = is3D ? 'none' : 'block';
        if(is3D) render3D();
    }

    function render3D() {
        const hoverTexts = pos.map(p => `Vitesse: ${Math.round(p.speed || 0)} km/h<br>Alt: ${Math.round(p.elevation || 0)} m${p.battery_level ? '<br>Bat: '+p.battery_level+'%' : ''}`);
        const mainData = {
            type: 'scatter3d', mode: 'lines',
            x: pos.map(p => p.longitude), y: pos.map(p => p.latitude), z: pos.map(p => p.elevation || 0),
            line: { width: 6, color: pos.map(p => p.elevation || 0), colorscale: 'Viridis' },
            text: hoverTexts, hoverinfo: 'text'
        };
        const plotData = [mainData];
        if(isSingleDrive && pos.length > 0) {
            const startP = pos[0]; const endP = pos[pos.length-1];
            plotData.push({
                type: 'scatter3d', mode: 'text', x: [startP.longitude], y: [startP.latitude], z: [startP.elevation || 0],
                text: ['<?= $txt['start'] ?>'], textposition: 'top center', textfont: { color: '#fff', size: 14 },
                marker: { color: '#22c55e', size: 8 }, hoverinfo: 'none'
            });
            plotData.push({
                type: 'scatter3d', mode: 'text', x: [endP.longitude], y: [endP.latitude], z: [endP.elevation || 0],
                text: ['<?= $txt['end'] ?>'], textposition: 'top center', textfont: { color: '#fff', size: 14 },
                marker: { color: '#dc2626', size: 8 }, hoverinfo: 'none'
            });
        }
        Plotly.newPlot('plot3d', plotData, { 
            paper_bgcolor: '#000', plot_bgcolor: '#000', margin: {l:0, r:0, b:0, t:0}, 
            scene: { 
                aspectratio: {x:1, y:1, z:0.3},
                xaxis: {backgroundcolor: '#000', gridcolor: '#333', showbackground: true, title: ''},
                yaxis: {backgroundcolor: '#000', gridcolor: '#333', showbackground: true, title: ''},
                zaxis: {backgroundcolor: '#000', gridcolor: '#333', showbackground: true, title: 'Altitude (m)'}
            }
        });
        document.getElementById('info-box').style.display = 'flex';
    }

    async function takeSnapshot() {
        const canvas = await html2canvas(document.getElementById('map'), { useCORS: true });
        const link = document.createElement('a');
        link.download = 'tesla_snap.png'; link.href = canvas.toDataURL(); link.click();
    }
</script>
</body>
</html>
