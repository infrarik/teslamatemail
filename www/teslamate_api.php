<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- 1. LECTURE SETUP ---
$setup_file = 'cgi-bin/setup';
$config = ['DOCKER_PATH' => ''];
if (file_exists($setup_file)) {
    $lines = file($setup_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) $config[trim($parts[0])] = trim($parts[1]);
    }
}

// --- 2. EXTRACTION DOCKER ---
$db_user = "teslamate"; $db_pass = "secret_password"; $db_name = "teslamate";
if (!empty($config['DOCKER_PATH']) && file_exists($config['DOCKER_PATH'])) {
    $docker_content = file_get_contents($config['DOCKER_PATH']);
    if (preg_match('/POSTGRES_USER[:=]\s*(\S+)/', $docker_content, $m)) $db_user = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_PASSWORD[:=]\s*(\S+)/', $docker_content, $m)) $db_pass = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_DB[:=]\s*(\S+)/', $docker_content, $m)) $db_name = str_replace(['"', "'"], '', trim($m[1]));
}

// --- 3. CONNEXION ET REQUÊTE COMPLÈTE ---
try {
    $pdo = new PDO("pgsql:host=localhost;port=5432;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Requête avec TOUS les champs disponibles + état réel
    $sql = "SELECT 
                c.name,
                (SELECT state FROM public.states WHERE car_id = c.id ORDER BY start_date DESC LIMIT 1) as state,
                p.date,
                p.latitude,
                p.longitude,
                p.speed,
                p.power,
                p.odometer,
                p.ideal_battery_range_km,
                p.battery_level,
                p.outside_temp,
                p.elevation,
                p.fan_status,
                p.driver_temp_setting,
                p.passenger_temp_setting,
                p.is_climate_on,
                p.is_rear_defroster_on,
                p.is_front_defroster_on,
                p.inside_temp,
                p.battery_heater,
                p.battery_heater_on,
                p.battery_heater_no_power,
                p.est_battery_range_km,
                p.rated_battery_range_km,
                p.usable_battery_level,
                p.tpms_pressure_fl,
                p.tpms_pressure_fr,
                p.tpms_pressure_rl,
                p.tpms_pressure_rr
            FROM public.cars c
            JOIN public.positions p ON p.car_id = c.id
            ORDER BY p.date DESC LIMIT 1";
            
    $stmt = $pdo->query($sql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        echo json_encode(['error' => 'Aucune ligne retournée par la DB']);
    } else {
        // Réponse complète avec tous les champs
        $response = [
            // Informations de base
            'display_name' => $row['name'] ?? 'Tesla',
            'state' => $row['state'] ?? 'unknown',
            'date' => $row['date'] ?? null,
            
            // Position et déplacement
            'latitude' => $row['latitude'] ?? 0,
            'longitude' => $row['longitude'] ?? 0,
            'speed' => $row['speed'] ?? 0,
            'power' => $row['power'] ?? 0,
            'odometer' => round($row['odometer'] ?? 0),
            'elevation' => $row['elevation'] ?? 0,
            
            // Batterie
            'battery_level' => $row['battery_level'] ?? 0,
            'usable_battery_level' => $row['usable_battery_level'] ?? 0,
            'ideal_battery_range_km' => $row['ideal_battery_range_km'] ?? 0,
            'est_battery_range_km' => $row['est_battery_range_km'] ?? 0,
            'rated_battery_range_km' => $row['rated_battery_range_km'] ?? 0,
            'battery_heater' => $row['battery_heater'] ?? false,
            'battery_heater_on' => $row['battery_heater_on'] ?? false,
            'battery_heater_no_power' => $row['battery_heater_no_power'] ?? false,
            
            // Température et climatisation
            'outside_temp' => $row['outside_temp'] ?? 0,
            'inside_temp' => $row['inside_temp'] ?? 0,
            'driver_temp_setting' => $row['driver_temp_setting'] ?? 0,
            'passenger_temp_setting' => $row['passenger_temp_setting'] ?? 0,
            'is_climate_on' => $row['is_climate_on'] ?? false,
            'is_rear_defroster_on' => $row['is_rear_defroster_on'] ?? false,
            'is_front_defroster_on' => $row['is_front_defroster_on'] ?? false,
            'fan_status' => $row['fan_status'] ?? 0,
            
            // Pression des pneus (TPMS)
            'tpms_pressure_fl' => $row['tpms_pressure_fl'] ?? 0,
            'tpms_pressure_fr' => $row['tpms_pressure_fr'] ?? 0,
            'tpms_pressure_rl' => $row['tpms_pressure_rl'] ?? 0,
            'tpms_pressure_rr' => $row['tpms_pressure_rr'] ?? 0
        ];
        echo json_encode($response);
    }

} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur SQL : ' . $e->getMessage()]);
}

