<?php
// --- 1. LECTURE DU SETUP ---
$file = 'cgi-bin/setup';
$config = ['notification_email' => '', 'DOCKER_PATH' => ''];

if (file_exists($file)) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) { $config[trim($parts[0])] = trim($parts[1]); }
    }
}

// --- 2. EXTRACTION IDENTIFIANTS DOCKER ---
$db_user = "teslamate"; $db_pass = "secret_password"; $db_name = "teslamate"; // Valeurs par défaut

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
$date_debut = $_POST['date_debut'] ?? date('Y-m-01');
$date_fin = $_POST['date_fin'] ?? date('Y-m-d');
$selected_geo = $_POST['geofence'] ?? 'TOUS';
$export_complet = isset($_POST['export_complet']) ? 1 : 0;
$status_message = ""; $status_type = "";

if (isset($_POST['calculer']) || isset($_POST['envoyer_email']) || isset($_POST['telecharger_pdf']) || isset($_POST['telecharger_csv'])) {
    $params = ['debut' => $date_debut, 'fin' => $date_fin];
    
    // Correction ici : on ajoute cp.charge_energy_added > 0 pour ne pas compter les recharges vides
    $where = " WHERE cp.start_date >= :debut AND cp.start_date < (:fin::date + interval '1 day') AND cp.charge_energy_added > 0";

    if ($selected_geo !== 'TOUS') {
        $where .= " AND cp.geofence_id = :geo_id";
        $params['geo_id'] = $selected_geo;
    }

    // 1. Calcul du résumé (Nombre et Total kWh)
    $sql = "SELECT COUNT(cp.id) as nb, ROUND(SUM(cp.charge_energy_added)::numeric, 2) as total_kwh FROM charging_processes cp" . $where;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Récupération détaillée si nécessaire
    if ($export_complet && (isset($_POST['envoyer_email']) || isset($_POST['telecharger_pdf']) || isset($_POST['telecharger_csv']))) {
        $sql_detail = "SELECT  
            cp.start_date::date as date_charge,
            TO_CHAR(cp.start_date, 'HH24:MI') as heure_charge,
            p.latitude,
            p.longitude,
            ROUND(cp.charge_energy_added::numeric, 2) as kwh,
            ROUND(EXTRACT(EPOCH FROM (cp.end_date - cp.start_date))/60) as duree_minutes
        FROM charging_processes cp
        LEFT JOIN positions p ON p.id = (
            SELECT id FROM positions  
            WHERE date >= cp.start_date  
            ORDER BY date ASC LIMIT 1
        )" . $where . " ORDER BY cp.start_date, heure_charge";
        
        $stmt_detail = $pdo->prepare($sql_detail);
        $stmt_detail->execute($params);
        $recharges_detail = $stmt_detail->fetchAll(PDO::FETCH_ASSOC);
    }

    // ENVOI EMAIL
    if (isset($_POST['envoyer_email']) && !empty($config['notification_email'])) {
        $to = $config['notification_email'];
        $subject = "Rapport TeslaMate";
        $body = "Période : $date_debut au $date_fin\nCharges : " . $resultats['nb'] . "\nTotal : " . ($resultats['total_kwh'] ?? 0) . " kWh";
        
        if ($export_complet && count($recharges_detail) > 0) {
            $body .= "\n\n=== DÉTAIL DES RECHARGES ===\n\n";
            foreach ($recharges_detail as $r) {
                $body .= "Date: " . date('d/m/Y', strtotime($r['date_charge'])) . " | ";
                $body .= "Heure: " . $r['heure_charge'] . " | ";
                $body .= "GPS: " . round($r['latitude'], 6) . ", " . round($r['longitude'], 6) . " | ";
                $body .= "kWh: " . $r['kwh'] . " | ";
                $body .= "Durée: " . floor($r['duree_minutes']/60) . "h" . ($r['duree_minutes']%60) . "m\n";
            }
        }
        
        if (mail($to, $subject, $body, "From: noreply@teslamate.local")) {
            $status_message = "Envoyé à $to"; $status_type = "success";
        }
    }

    // TÉLÉCHARGEMENT PDF
    if (isset($_POST['telecharger_pdf'])) {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapport TeslaMate</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #fff; color: #000; }
        h1 { color: #dc2626; text-align: center; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #dc2626; color: white; padding: 10px; text-align: left; }
        td { padding: 8px; border: 1px solid #ddd; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .info { margin: 20px 0; font-size: 16px; line-height: 1.8; }
        .info strong { color: #dc2626; }
        @media print {
            body { margin: 20px; }
            button { display: none; }
        }
        .print-btn { background: #dc2626; color: white; border: none; padding: 12px 24px; 
                     border-radius: 8px; font-size: 16px; cursor: pointer; margin: 20px 0; }
        .print-btn:hover { background: #b91c1c; }
    </style>
</head>
<body>
    <h1>Rapport de consommation TeslaMate</h1>
    <div class="info">
        <p><strong>Période :</strong> ' . date('d/m/Y', strtotime($date_debut)) . ' au ' . date('d/m/Y', strtotime($date_fin)) . '</p>
        <p><strong>Charges :</strong> ' . $resultats['nb'] . '</p>
        <p><strong>Énergie totale :</strong> ' . ($resultats['total_kwh'] ?? 0) . ' kWh</p>
    </div>';
        
        if ($export_complet && count($recharges_detail) > 0) {
            $html .= '<h2>Détail des recharges</h2>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Heure</th>
                    <th>Position GPS</th>
                    <th>kWh</th>
                    <th>Durée</th>
                </tr>';
            
            foreach ($recharges_detail as $r) {
                $html .= '<tr>
                    <td>' . date('d/m/Y', strtotime($r['date_charge'])) . '</td>
                    <td>' . $r['heure_charge'] . '</td>
                    <td>' . round($r['latitude'], 6) . ', ' . round($r['longitude'], 6) . '</td>
                    <td>' . $r['kwh'] . '</td>
                    <td>' . floor($r['duree_minutes']/60) . 'h' . ($r['duree_minutes']%60) . 'm</td>
                </tr>';
            }
            
            $html .= '</table>';
        }
        
        $html .= '<button class="print-btn" onclick="window.print()">🖨 Imprimer / Enregistrer en PDF</button>';
        $html .= '</body></html>';
        
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    // TÉLÉCHARGEMENT CSV
    if (isset($_POST['telecharger_csv'])) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="teslamate_' . $date_debut . '_' . $date_fin . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo "\xEF\xBB\xBF"; // BOM UTF-8
        $output = fopen('php://output', 'w');
        
        if ($export_complet && count($recharges_detail) > 0) {
            fputcsv($output, ['Date', 'Heure', 'Position GPS', 'kWh', 'Durée'], ';');
            foreach ($recharges_detail as $r) {
                fputcsv($output, [
                    date('d/m/Y', strtotime($r['date_charge'])),
                    $r['heure_charge'],
                    round($r['latitude'], 6) . ', ' . round($r['longitude'], 6),
                    str_replace('.', ',', $r['kwh']),
                    floor($r['duree_minutes']/60) . 'h' . ($r['duree_minutes']%60) . 'm'
                ], ';');
            }
        } else {
            fputcsv($output, ['Période', 'Charges', 'Énergie (kWh)'], ';');
            fputcsv($output, [
                date('d/m/Y', strtotime($date_debut)) . ' - ' . date('d/m/Y', strtotime($date_fin)),
                $resultats['nb'],
                str_replace('.', ',', $resultats['total_kwh'] ?? 0)
            ], ';');
        }
        
        fclose($output);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TeslaCalcul - Consommation</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); color: #fff; margin: 0; padding: 20px; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { background: rgba(255, 255, 255, 0.05); border-radius: 20px; padding: 40px; max-width: 500px; width: 100%; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); position: relative; }
        .back-button { position: absolute; top: 20px; left: 20px; width: 40px; height: 40px; background: rgba(255, 255, 255, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.3s; text-decoration: none; }
        .back-button svg { width: 24px; height: 24px; stroke: #fff; stroke-width: 2; fill: none; }
        h1 { margin: 10px 0 30px 0; font-size: 28px; text-align: center; color: #dc2626; }
        label { display: block; font-size: 11px; color: #999; text-transform: uppercase; margin-bottom: 8px; margin-top: 15px; }
        input, select { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.3); color: #fff; font-size: 16px; box-sizing: border-box; outline: none; }
        select option { background: #2d2d2d; }
        .checkbox-container { display: flex; align-items: center; margin-top: 20px; background: rgba(0, 0, 0, 0.3); padding: 15px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.2); }
        .checkbox-container input[type="checkbox"] { width: auto; margin-right: 10px; }
        .checkbox-container label { margin: 0; text-transform: none; font-size: 14px; color: #fff; cursor: pointer; }
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
        .error { background: rgba(220, 38, 38, 0.2); color: #f87171; }
    </style>
</head>
<body>
    <div class="container">
        <a href="tesla.html" class="back-button">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <h1>Consommation</h1>
        <?php if ($status_message): ?>
            <div class="alert <?php echo $status_type; ?>"><?php echo $status_message; ?></div>
        <?php endif; ?>
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
                <label for="export_complet">Export complet (date, heure, GPS, kWh, durée)</label>
            </div>
            
            <button type="submit" name="calculer" class="btn btn-calc">VALIDER</button>
            <?php if ($resultats): ?>
                <div class="result-box">
                    <div class="result-item"><span>Charges :</span><span class="result-value"><?php echo $resultats['nb']; ?></span></div>
                    <div class="result-item"><span>Énergie :</span><span class="result-value"><?php echo $resultats['total_kwh'] ?? 0; ?> kWh</span></div>
                </div>
                <?php if (!empty($config['notification_email'])): ?>
                    <button type="submit" name="envoyer_email" class="btn btn-mail">ENVOI PAR EMAIL</button>
                <?php endif; ?>
                <button type="submit" name="telecharger_pdf" class="btn btn-pdf" formtarget="_blank">TÉLÉCHARGER PDF</button>
                <button type="submit" name="telecharger_csv" class="btn btn-csv">TÉLÉCHARGER CSV</button>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>
