<?php
header('Content-Type: application/json');
// On force l'affichage des erreurs PHP pour le debug si le JSON casse
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

// --- 3. CONNEXION ET REQUÊTE ---
try {
    $pdo = new PDO("pgsql:host=localhost;port=5432;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Requête simplifiée au maximum pour tester
    $sql = "SELECT 
                c.name, 
                s.state, 
                p.battery_level, 
                p.ideal_battery_range_km, 
                p.outside_temp, 
                p.inside_temp, 
                p.speed, 
                p.odometer 
            FROM public.cars c
            JOIN public.positions p ON p.car_id = c.id
            JOIN public.states s ON s.car_id = c.id
            ORDER BY p.date DESC LIMIT 1";
            
    $stmt = $pdo->query($sql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        echo json_encode(['error' => 'Aucune ligne retournée par la DB']);
    } else {
        // On prépare la réponse pour tesla.html
        $response = [
            'display_name'           => $row['name'] ?? 'Tesla',
            'state'                  => $row['state'] ?? 'unknown',
            'battery_level'          => $row['battery_level'] ?? 0,
            'ideal_battery_range_km' => $row['ideal_battery_range_km'] ?? 0,
            'outside_temp'           => $row['outside_temp'] ?? 0,
            'inside_temp'            => $row['inside_temp'] ?? 0,
            'speed'                  => $row['speed'] ?? 0,
            'odometer'               => round($row['odometer'] ?? 0)
        ];
        echo json_encode($response);
    }

} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur SQL : ' . $e->getMessage()]);
}

