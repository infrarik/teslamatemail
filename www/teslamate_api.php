<?php
header('Content-Type: application/json');

// --- 1. CONFIGURATION ---
$file = "/var/www/html/cgi-bin/setup";

function read_setup($file) {
    $cfg = [];
    if (file_exists($file)) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $cfg[strtoupper(trim($key))] = trim($value);
            }
        }
    }
    return $cfg;
}

$config = read_setup($file);
$server_ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
$db_user = "teslamate"; $db_pass = "secret_password"; $db_name = "teslamate";

if (!empty($config['DOCKER_PATH']) && file_exists($config['DOCKER_PATH'])) {
    $docker_content = file_get_contents($config['DOCKER_PATH']);
    if (preg_match('/POSTGRES_USER[:=]\s*(\S+)/', $docker_content, $m)) $db_user = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_PASSWORD[:=]\s*(\S+)/', $docker_content, $m)) $db_pass = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_DB[:=]\s*(\S+)/', $docker_content, $m)) $db_name = str_replace(['"', "'"], '', trim($m[1]));
}

try {
    $pdo = new PDO("pgsql:host=$server_ip;port=5432;dbname=$db_name", $db_user, $db_pass);
    
    // REQUÊTE MISE À JOUR : Récupération de added ET used
    $sql = "
        SELECT 
            p.battery_level, p.odometer, p.inside_temp, p.outside_temp, 
            p.rated_battery_range_km as est_battery_range_km,
            p.tpms_pressure_fl, p.tpms_pressure_fr, p.tpms_pressure_rl, p.tpms_pressure_rr,
            s.state,
            c.name,
            -- Puissance de charge actuelle (kW)
            (SELECT charger_power FROM charges ORDER BY date DESC LIMIT 1) as charger_actual_power_kw,
            -- Énergie ajoutée à la batterie (kWh)
            (SELECT charge_energy_added FROM charging_processes WHERE end_date IS NULL ORDER BY start_date DESC LIMIT 1) as energy_added_kwh,
            -- Énergie consommée à la prise (kWh)
            (SELECT charge_energy_used FROM charging_processes WHERE end_date IS NULL ORDER BY start_date DESC LIMIT 1) as energy_used_kwh,
            -- Vérification si une session est active
            (SELECT COUNT(*) FROM charging_processes WHERE end_date IS NULL) as active_charging_sessions
        FROM positions p
        LEFT JOIN states s ON s.car_id = p.car_id AND s.end_date IS NULL
        LEFT JOIN cars c ON c.id = p.car_id
        ORDER BY p.date DESC 
        LIMIT 1
    ";

    $stmt = $pdo->query($sql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $isCharging = ($row['active_charging_sessions'] > 0 || (float)$row['charger_actual_power_kw'] > 0);
        
        $data = [
            "name"                 => $row['name'],
            "state"                => $isCharging ? "charging" : $row['state'],
            "battery_level"        => (int)$row['battery_level'],
            "odometer"             => (float)$row['odometer'],
            "est_battery_range_km" => (float)$row['est_battery_range_km'],
            "inside_temp"          => (float)$row['inside_temp'],
            "outside_temp"         => (float)$row['outside_temp'],
            "tpms_pressure_fl"     => (float)$row['tpms_pressure_fl'],
            "tpms_pressure_fr"     => (float)$row['tpms_pressure_fr'],
            "tpms_pressure_rl"     => (float)$row['tpms_pressure_rl'],
            "tpms_pressure_rr"     => (float)$row['tpms_pressure_rr'],
            "charger_power_kw"     => (float)$row['charger_actual_power_kw'],
            "energy_added_kwh"     => (float)($row['energy_added_kwh'] ?? 0),
            "energy_used_kwh"      => (float)($row['energy_used_kwh'] ?? 0),
            "is_charging"          => $isCharging
        ];
        echo json_encode($data);
    }

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}

