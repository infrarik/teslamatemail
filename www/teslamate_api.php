<?php
header('Content-Type: application/json');
ini_set('display_errors', 0); // On cache les erreurs pour ne pas casser le JSON
error_reporting(E_ALL);

// --- 1. LECTURE SETUP (Correction de la casse pour la clé) ---
$setup_file = 'cgi-bin/setup';
$config = [];
if (file_exists($setup_file)) {
    $lines = file($setup_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = strtoupper(trim($parts[0])); // On force DOCKER_PATH
            $config[$key] = trim($parts[1]);
        }
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

// --- 3. CONNEXION ET REQUÊTE ---
try {
    $pdo = new PDO("pgsql:host=localhost;port=5432;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "SELECT 
                c.name,
                (SELECT state FROM public.states WHERE car_id = c.id ORDER BY start_date DESC LIMIT 1) as state,
                p.speed, p.odometer, p.ideal_battery_range_km, p.battery_level, 
                p.outside_temp, p.inside_temp, p.est_battery_range_km,
                p.tpms_pressure_fl, p.tpms_pressure_fr, p.tpms_pressure_rl, p.tpms_pressure_rr
            FROM public.cars c
            JOIN public.positions p ON p.car_id = c.id
            ORDER BY p.date DESC LIMIT 1";
            
    $stmt = $pdo->query($sql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        echo json_encode(['error' => 'Aucune donnée']);
    } else {
        echo json_encode($row); // On renvoie tout brut pour le JS
    }

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
