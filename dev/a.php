<?php
// --- 1. LECTURE DU SETUP ---
$file = 'cgi-bin/setup';
$config = ['NOTIFICATION_EMAIL' => '', 'DOCKER_PATH' => ''];

if (file_exists($file)) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) { 
            $config[strtoupper(trim($parts[0]))] = trim($parts[1]); 
        }
    }
}

// --- 2. EXTRACTION IDENTIFIANTS DOCKER ---
$db_user = "teslamate"; $db_pass = "secret_password"; $db_name = "teslamate";

if (!empty($config['DOCKER_PATH']) && file_exists($config['DOCKER_PATH'])) {
    $docker_content = file_get_contents($config['DOCKER_PATH']);
    if (preg_match('/POSTGRES_USER[:=]\s*(\S+)/', $docker_content, $m)) $db_user = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_PASSWORD[:=]\s*(\S+)/', $docker_content, $m)) $db_pass = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_DB[:=]\s*(\S+)/', $docker_content, $m)) $db_name = str_replace(['"', "'"], '', trim($m[1]));
}

// --- 3. CONNEXION DB ---
try {
    $pdo = new PDO("pgsql:host=localhost;port=5432;dbname=$db_name", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// --- 4. RÉCUPÉRATION GEOFENCES ---
$stmt_geo = $pdo->query("SELECT id, name FROM geofences ORDER BY name ASC");
$geofences = $stmt_geo->fetchAll(PDO::FETCH_ASSOC);

// --- 5. LOGIQUE DE CALCUL ---
$resultats = null;
$recharges_detail = [];
$trajets_detail = [];
$date_debut = $_POST['date_debut'] ?? date('Y-m-01');
$date_fin = $_POST['date_fin'] ?? date('Y-m-d');
$selected_geo = $_POST['geofence'] ?? 'TOUS';
$export_complet = isset($_POST['export_complet']) ? 1 : 0;
$status_message = ""; $status_type = "";

if (isset($_POST['calculer']) || isset($_POST['envoyer_email']) || isset($_POST['telecharger_pdf']) || isset($_POST['telecharger_csv'])) {
    
    try {
        $params = ['debut' => $date_debut, 'fin' => $date_fin];
        
        // -- A. Filtre Charges --
        $where_charge = " WHERE cp.start_date >= :debut AND cp.start_date < (:fin::date + interval '1 day') AND cp.charge_energy_added > 0";
        if ($selected_geo !== 'TOUS') {
            $where_charge .= " AND cp.geofence_id = :geo_id";
            $params['geo_id'] = $selected_geo;
        }

        // Synthèse charges
        $sql_charge = "SELECT COUNT(cp.id) as nb, ROUND(SUM(cp.charge_energy_added)::numeric, 2) as total_kwh FROM charging_processes cp" . $where_charge;
        $stmt = $pdo->prepare($sql_charge);
        $stmt->execute($params);
        $resultats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Synthèse Kilométrage
        $sql_km = "SELECT ROUND(SUM(distance)::numeric, 1) as total_km FROM drives WHERE start_date >= :debut AND start_date < (:fin::date + interval '1 day')";
        $stmt_km = $pdo->prepare($sql_km);
        $stmt_km->execute(['debut' => $date_debut, 'fin' => $date_fin]);
        $res_km = $stmt_km->fetch(PDO::FETCH_ASSOC);
        $resultats['total_km'] = $res_km['total_km'] ?? 0;

        // -- B. Détails (Si export coché) --
        if ($export_complet) {
            // Détails RECHARGES (avec GPS)
            $sql_recharges = "SELECT cp.start_date::date as date_charge, TO_CHAR(cp.start_date, 'HH24:MI') as heure_charge, p.latitude, p.longitude, ROUND(cp.charge_energy_added::numeric, 2) as kwh, ROUND(EXTRACT(EPOCH FROM (cp.end_date - cp.start_date))/60) as duree_minutes FROM charging_processes cp LEFT JOIN positions p ON p.id = (SELECT id FROM positions WHERE date >= cp.start_date ORDER BY date ASC LIMIT 1)" . $where_charge . " ORDER BY cp.start_date ASC";
            $stmt_rec = $pdo->prepare($sql_recharges);
            $stmt_rec->execute($params);
            $recharges_detail = $stmt_rec->fetchAll(PDO::FETCH_ASSOC);

            // Détails TRAJETS (avec Adresses corrigées)
            $sql_trajets = "SELECT d.start_date, d.end_date, ROUND(d.distance::numeric, 1) as km, a1.name as start_address FROM drives d LEFT JOIN addresses a1 ON d.start_address_id = a1.id WHERE d.start_date >= :debut AND d.start_date < (:fin::date + interval '1 day') AND d.distance > 0.1 ORDER BY d.start_date ASC";
            $stmt_trajets = $pdo->prepare($sql_trajets);
            $stmt_trajets->execute(['debut' => $date_debut, 'fin' => $date_fin]);
            $trajets_detail = $stmt_trajets->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (Exception $e) {
        die("Erreur lors du calcul : " . $e->getMessage());
    }

    // --- ENVOI EMAIL ---
    if (isset($_POST['envoyer_email']) && !empty($config['NOTIFICATION_EMAIL'])) {
        $to = $config['NOTIFICATION_EMAIL'];
        $subject = "Rapport TeslaMate - $date_debut au $date_fin";
        $body = "Distance : " . $resultats['total_km'] . " km\nCharges : " . $resultats['nb'] . "\nEnergie : " . ($resultats['total_kwh'] ?? 0) . " kWh\n";
        
        if ($export_complet) {
            $body .= "\n=== DETAILS RECHARGES ===\n";
            foreach ($recharges_detail as $r) {
                $body .= $r['date_charge'] . " " . $r['heure_charge'] . " | " . $r['kwh'] . " kWh | GPS: " . round($r['latitude'],4) . "," . round($r['longitude'],4) . "\n";
            }
        }
        mail($to, $subject, $body, "From: noreply@teslamate.local");
        $status_message = "Envoyé à $to"; $status_type = "success";
    }

    // --- PDF / PRINT ---
    if (isset($_POST['telecharger_pdf'])) {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:sans-serif;padding:20px} h1{color:#dc2626} table{width:100%;border-collapse:collapse;margin-top:20px} td,th{border:1px solid #ddd;padding:8px;font-size:12px} th{background:#dc2626;color:#fff}</style></head><body>';
        echo '<h1>Rapport TeslaMate</h1><p>Période : '.$date_debut.' au '.$date_fin.'</p>';
        echo '<ul><li>Distance : '.$resultats['total_km'].' km</li><li>Charges : '.$resultats['nb'].'</li><li>Energie : '.$resultats['total_kwh'].' kWh</li></ul>';
        
        if ($export_complet) {
            echo '<h2>Détail des recharges</h2><table><tr><th>Date</th><th>Heure</th><th>kWh</th><th>Durée</th><th>GPS</th></tr>';
            foreach ($recharges_detail as $r) {
                echo '<tr><td>'.$r['date_charge'].'</td><td>'.$r['heure_charge'].'</td><td>'.$r['kwh'].'</td><td>'.floor($r['duree_minutes']/60).'h'.($r['duree_minutes']%60).'m</td><td>'.round($r['latitude'],6).','.round($r['longitude'],6).'</td></tr>';
            }
            echo '</table>';

            echo '<h2>Détail des trajets</h2><table><tr><th>Départ</th><th>Km</th><th>Adresse</th></tr>';
            foreach ($trajets_detail as $t) {
                echo '<tr><td>'.date('d/m H:i', strtotime($t['start_date'])).'</td><td>'.$t['km'].'</td><td>'.htmlspecialchars($t['start_address'] ?? 'Inconnu').'</td></tr>';
            }
            echo '</table>';
        }
        echo '<br><button onclick="window.print()">Imprimer</button></body></html>';
        exit;
    }

    // --- CSV ---
    if (isset($_POST['telecharger_csv'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="export_tesla.csv"');
        $f = fopen('php://output', 'w');
        fputcsv($f, ['SECTION', 'DATE', 'VALEUR 1', 'VALEUR 2', 'INFO'], ';');
        fputcsv($f, ['SYNTHESE', $date_debut, $resultats['total_km'].' km', $resultats['nb'].' charges', $resultats['total_kwh'].' kWh'], ';');
        if($export_complet) {
            fputcsv($f, ['--- RECHARGES ---'], ';');
            foreach($recharges_detail as $r) fputcsv($f, ['RECHARGE', $r['date_charge'], $r['kwh'].' kWh', $r['heure_charge'], $r['latitude'].','.$r['longitude']], ';');
            fputcsv($f, ['--- TRAJETS ---'], ';');
            foreach($trajets_detail as $t) fputcsv($f, ['TRAJET', $t['start_date'], $t['km'].' km', '', $t['start_address']], ';');
        }
        fclose($f); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TeslaCalcul</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); color: #fff; margin: 0; padding: 20px; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { background: rgba(255, 255, 255, 0.05); border-radius: 20px; padding: 40px; max-width: 500px; width: 100%; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); position: relative; }
        .back-button { position: absolute; top: 20px; left: 20px; width: 40px; height: 40px; background: rgba(255, 255, 255, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; }
        .back-button svg { width: 24px; height: 24px; stroke: #fff; stroke-width: 2; fill: none; }
        h1 { margin: 10px 0 30px 0; font-size: 28px; text-align: center; color: #dc2626; }
        label { display: block; font-size: 11px; color: #999; text-transform: uppercase; margin-bottom: 8px; margin-top: 15px; }
        input, select { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.3); color: #fff; font-size: 16px; box-sizing: border-box; }
        .checkbox-container { display: flex; align-items: center; margin-top: 20px; background: rgba(0, 0, 0, 0.3); padding: 15px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.2); }
        .checkbox-container input { width: auto; margin-right: 10px; }
        .btn { width: 100%; padding: 14px; border: none; border-radius: 10px; color: white; font-weight: bold; cursor: pointer; margin-top: 20px; }
        .btn-calc { background: #16a34a; }
        .btn-mail { background: #2563eb; }
        .btn-pdf { background: #dc2626; }
        .btn-csv { background: #16a34a; }
        .result-box { background: rgba(0, 0, 0, 0.4); border-radius: 12px; padding: 20px; margin-top: 30px; border-left: 4px solid #16a34a; }
        .result-item { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 18px; }
        .result-value { font-weight: bold; color: #4ade80; }
        .alert { padding: 12px; border-radius: 8px; text-align: center; margin-bottom: 20px; }
        .success { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
    </style>
</head>
<body>
    <div class="container">
        <a href="tesla.php" class="back-button">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <h1>Consommation</h1>
        <?php if ($status_message): ?><div class="alert <?php echo $status_type; ?>"><?php echo $status_message; ?></div><?php endif; ?>
        <form method="POST">
            <label>Lieu (Geofence)</label>
            <select name="geofence">
                <option value="TOUS" <?php if($selected_geo == 'TOUS') echo 'selected'; ?>>-- TOUS LES LIEUX --</option>
                <?php foreach ($geofences as $geo): ?>
                    <option value="<?php echo $geo['id']; ?>" <?php if($selected_geo == $geo['id']) echo 'selected'; ?>><?php echo htmlspecialchars($geo['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <label>Début</label>
            <input type="date" name="date_debut" value="<?php echo $date_debut; ?>" required>
            <label>Fin</label>
            <input type="date" name="date_fin" value="<?php echo $date_fin; ?>" required>
            <div class="checkbox-container">
                <input type="checkbox" id="export_complet" name="export_complet" <?php if($export_complet) echo 'checked'; ?>>
                <label for="export_complet">Export complet (Détails)</label>
            </div>
            <button type="submit" name="calculer" class="btn btn-calc">VALIDER</button>
            <?php if ($resultats): ?>
                <div class="result-box">
                    <div class="result-item"><span>Distance :</span><span class="result-value"><?php echo $resultats['total_km']; ?> km</span></div>
                    <div class="result-item"><span>Charges :</span><span class="result-value"><?php echo $resultats['nb']; ?></span></div>
                    <div class="result-item"><span>Énergie :</span><span class="result-value"><?php echo $resultats['total_kwh'] ?? 0; ?> kWh</span></div>
                </div>
                <?php if (!empty($config['NOTIFICATION_EMAIL'])): ?>
                    <button type="submit" name="envoyer_email" class="btn btn-mail">ENVOI PAR EMAIL</button>
                <?php endif; ?>
                <button type="submit" name="telecharger_pdf" class="btn btn-pdf" formtarget="_blank">TÉLÉCHARGER PDF</button>
                <button type="submit" name="telecharger_csv" class="btn btn-csv">TÉLÉCHARGER CSV</button>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>
