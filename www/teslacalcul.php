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

// --- 4. RÉCUPÉRATION DES VÉHICULES ET GEOFENCES ---
$stmt_cars = $pdo->query("SELECT id, COALESCE(name, model) as display_name FROM cars ORDER BY id ASC");
$cars = $stmt_cars->fetchAll(PDO::FETCH_ASSOC);

$stmt_geo = $pdo->query("SELECT id, name FROM geofences ORDER BY name ASC");
$geofences = $stmt_geo->fetchAll(PDO::FETCH_ASSOC);

// --- 5. LOGIQUE DE CALCUL ---
$resultats = ['nb' => 0, 'total_kwh' => 0, 'total_km' => 0];
$historique_fusionne = [];
$date_debut = $_POST['date_debut'] ?? date('Y-m-01');
$date_fin = $_POST['date_fin'] ?? date('Y-m-d');
$selected_car = $_POST['car_id'] ?? ($cars[0]['id'] ?? 1);
$selected_geo = $_POST['geofence'] ?? 'TOUS';
$export_complet = isset($_POST['export_complet']) ? 1 : 0;
$cols = $_POST['cols'] ?? ['date', 'kwh', 'duree', 'km', 'ville', 'gps'];
$status_message = ""; $status_type = "";

// Trouver le nom du véhicule sélectionné pour les titres
$car_name_display = "Véhicule inconnu";
foreach ($cars as $car) {
    if ($car['id'] == $selected_car) {
        $car_name_display = $car['display_name'];
        break;
    }
}

if (isset($_POST['calculer']) || isset($_POST['envoyer_email']) || isset($_POST['telecharger_pdf']) || isset($_POST['telecharger_csv'])) {
    
    try {
        $params = ['debut' => $date_debut, 'fin' => $date_fin, 'car_id' => $selected_car];
        
        $where_charge = " WHERE cp.car_id = :car_id AND cp.start_date >= :debut AND cp.start_date < (:fin::date + interval '1 day') AND cp.charge_energy_added > 0";
        if ($selected_geo !== 'TOUS') {
            $where_charge .= " AND cp.geofence_id = :geo_id";
            $params['geo_id'] = $selected_geo;
        }

        $sql_rec = "SELECT cp.start_date::date as date_f, SUM(cp.charge_energy_added) as kwh, SUM(EXTRACT(EPOCH FROM (cp.end_date - cp.start_date))/60) as duree, MAX(p.latitude) as lat, MAX(p.longitude) as lon, MAX(a.city) as ville
                    FROM charging_processes cp 
                    LEFT JOIN addresses a ON a.id = cp.address_id
                    LEFT JOIN positions p ON p.id = a.id
                    " . $where_charge . " GROUP BY cp.start_date::date";
        $stmt_rec = $pdo->prepare($sql_rec);
        $stmt_rec->execute($params);
        $charges_par_jour = $stmt_rec->fetchAll(PDO::FETCH_ASSOC);

        $sql_tra = "SELECT start_date::date as date_f, SUM(distance) as km, SUM(EXTRACT(EPOCH FROM (end_date - start_date))/60) as duree 
                    FROM drives WHERE car_id = :car_id AND start_date >= :debut AND start_date < (:fin::date + interval '1 day') AND distance > 0.1 
                    GROUP BY start_date::date";
        $stmt_tra = $pdo->prepare($sql_tra);
        $stmt_tra->execute(['car_id' => $selected_car, 'debut' => $date_debut, 'fin' => $date_fin]);
        $trajets_par_jour = $stmt_tra->fetchAll(PDO::FETCH_ASSOC);

        $temp_hist = [];
        $total_kwh_accumule = 0; $total_km_accumule = 0; $compteur_jours_charge = 0;

        foreach ($charges_par_jour as $c) {
            $d = $c['date_f'];
            $temp_hist[$d] = ['date' => $d, 'kwh' => $c['kwh'], 'duree' => $c['duree'], 'km' => 0, 'ville' => $c['ville'], 'gps' => round($c['lat'],5).','.round($c['lon'],5)];
            $total_kwh_accumule += $c['kwh'];
            if ($c['kwh'] > 0) $compteur_jours_charge++;
        }
        foreach ($trajets_par_jour as $t) {
            $d = $t['date_f'];
            if (!isset($temp_hist[$d])) {
                $temp_hist[$d] = ['date' => $d, 'kwh' => 0, 'duree' => $t['duree'], 'km' => $t['km'], 'ville' => '', 'gps' => ''];
            } else {
                $temp_hist[$d]['km'] += $t['km'];
                $temp_hist[$d]['duree'] += $t['duree'];
            }
            $total_km_accumule += $t['km'];
        }
        ksort($temp_hist);
        $historique_fusionne = $temp_hist;
        $resultats = ['nb' => $compteur_jours_charge, 'total_kwh' => round($total_kwh_accumule, 2), 'total_km' => round($total_km_accumule, 1)];

    } catch (Exception $e) { die("Erreur : " . $e->getMessage()); }

    // --- ENVOI EMAIL ---
    if (isset($_POST['envoyer_email']) && !empty($config['NOTIFICATION_EMAIL'])) {
        $to = $config['NOTIFICATION_EMAIL']; 
        $subject = "Rapport TeslaMate - $car_name_display - $date_debut au $date_fin";
        $body = "Véhicule : $car_name_display\n";
        $body .= "Distance : ".$resultats['total_km']." km | Energie : ".$resultats['total_kwh']." kWh | Charges : ".$resultats['nb']."\n\nDétail :\n";
        foreach ($historique_fusionne as $l) {
            $line = [];
            if(in_array('date', $cols)) $line[] = date('d/m/Y', strtotime($l['date']));
            if(in_array('kwh', $cols)) $line[] = ($l['kwh']>0?round($l['kwh'],2).'kWh':'');
            if(in_array('km', $cols)) $line[] = ($l['km']>0?round($l['km'],1).'km':'');
            if(in_array('ville', $cols)) $line[] = $l['ville'];
            $body .= implode(' | ', array_filter($line)) . "\n";
        }
        mail($to, $subject, $body, "From: noreply@teslamate.local");
        $status_message = "Envoyé à $to"; $status_type = "success";
    }

    // --- PDF / PRINT ---
    if (isset($_POST['telecharger_pdf'])) {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:sans-serif;padding:20px} h1{color:#dc2626;text-align:center} table{width:100%;border-collapse:collapse;margin-top:20px} td,th{border:1px solid #ddd;padding:10px;text-align:center;font-size:13px} th{background:#dc2626;color:#fff}</style></head><body>';
        echo '<h1>Rapport TeslaMate - '.htmlspecialchars($car_name_display).'</h1>';
        echo '<p style="text-align:center">Période : '.$date_debut.' au '.$date_fin.'</p>';
        echo '<div style="margin-bottom:20px;text-align:center"><strong>Distance :</strong> '.$resultats['total_km'].' km | <strong>Charges :</strong> '.$resultats['nb'].' | <strong>Energie :</strong> '.$resultats['total_kwh'].' kWh</div>';
        echo '<table><tr>';
        foreach($cols as $c) {
            $labels = ['date'=>'Date','kwh'=>'kWh','duree'=>'Durée','km'=>'km','ville'=>'Ville','gps'=>'GPS'];
            echo "<th>".$labels[$c]."</th>";
        }
        echo '</tr>';
        foreach ($historique_fusionne as $l) {
            echo '<tr>';
            if(in_array('date', $cols)) echo '<td>'.date('d/m/Y', strtotime($l['date'])).'</td>';
            if(in_array('kwh', $cols)) echo '<td>'.($l['kwh']>0?round($l['kwh'],2):'').'</td>';
            if(in_array('duree', $cols)) echo '<td>'.round($l['duree']).' min</td>';
            if(in_array('km', $cols)) echo '<td>'.($l['km']>0?round($l['km'],1):'').'</td>';
            if(in_array('ville', $cols)) echo '<td>'.htmlspecialchars($l['ville']).'</td>';
            if(in_array('gps', $cols)) echo '<td>'.($l['gps'] != '0,0' ? $l['gps'] : '-').'</td>';
            echo '</tr>';
        }
        echo '</table><br><button onclick="window.print()" style="display:block;margin:auto;padding:10px 20px;background:#dc2626;color:#fff;border:none;border-radius:5px;cursor:pointer">Imprimer / PDF</button></body></html>';
        exit;
    }

    // --- CSV ---
    if (isset($_POST['telecharger_csv'])) {
        header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="export.csv"');
        $f = fopen('php://output', 'w'); 
        fputcsv($f, array_map('strtoupper', $cols), ';');
        foreach($historique_fusionne as $l) {
            $row = [];
            foreach($cols as $c) $row[] = $l[$c] ?? '';
            fputcsv($f, $row, ';');
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
        input[type="date"], select { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.3); color: #fff; font-size: 16px; box-sizing: border-box; }
        .checkbox-group { background: rgba(0, 0, 0, 0.3); padding: 15px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1); margin-top: 10px; display: none; }
        .checkbox-item { display: flex; align-items: center; margin-bottom: 8px; font-size: 14px; cursor: pointer; }
        .checkbox-item input { margin-right: 12px; width: 18px; height: 18px; }
        .btn { width: 100%; padding: 14px; border: none; border-radius: 10px; color: white; font-weight: bold; cursor: pointer; margin-top: 20px; }
        .btn-calc { background: #16a34a; }
        .btn-mail { background: #2563eb; }
        .btn-pdf { background: #dc2626; }
        .btn-csv { background: #16a34a; }
        .result-box { background: rgba(0, 0, 0, 0.4); border-radius: 12px; padding: 20px; margin-top: 30px; border-left: 4px solid #16a34a; }
        .result-item { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 18px; }
        .result-value { font-weight: bold; color: #4ade80; }
        .alert { padding: 12px; border-radius: 8px; text-align: center; margin-bottom: 20px; }
        #export_complet:checked ~ .checkbox-group { display: block; }
    </style>
</head>
<body>
    <div class="container">
        <a href="tesla.php" class="back-button"><svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
        <h1>Consommation</h1>
        <?php if ($status_message): ?><div class="alert success"><?php echo $status_message; ?></div><?php endif; ?>
        
        <form method="POST">
            <label>Véhicule</label>
            <select name="car_id">
                <?php foreach ($cars as $car): ?>
                    <option value="<?php echo $car['id']; ?>" <?php if($selected_car == $car['id']) echo 'selected'; ?>><?php echo htmlspecialchars($car['display_name']); ?></option>
                <?php endforeach; ?>
            </select>

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
            
            <div style="margin-top:20px;">
                <input type="checkbox" id="export_complet" name="export_complet" <?php if($export_complet) echo 'checked'; ?>>
                <label for="export_complet" style="display:inline; text-transform:none; color:white; font-size:14px; margin-left:10px;">Export complet (Détails)</label>
                
                <div class="checkbox-group">
                    <label class="checkbox-item" style="color:#4ade80; font-weight:bold;">
                        <input type="checkbox" id="toggleAll" checked> TOUT SÉLECTIONNER
                    </label>
                    <hr style="border:0; border-top:1px solid rgba(255,255,255,0.1); margin:10px 0;">
                    <?php 
                    $available_cols = ['date' => 'Date', 'kwh' => 'kWh', 'duree' => 'Durée', 'km' => 'km', 'ville' => 'Ville', 'gps' => 'GPS'];
                    foreach($available_cols as $id => $label): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="cols[]" value="<?php echo $id; ?>" class="col-check" <?php if(in_array($id, $cols)) echo 'checked'; ?>> <?php echo $label; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" name="calculer" class="btn btn-calc">VALIDER</button>

            <?php if ($resultats['total_km'] > 0 || $resultats['total_kwh'] > 0): ?>
                <div class="result-box">
                    <div class="result-item"><span>Distance :</span><span class="result-value"><?php echo $resultats['total_km']; ?> km</span></div>
                    <div class="result-item"><span>Charges :</span><span class="result-value"><?php echo $resultats['nb']; ?></span></div>
                    <div class="result-item"><span>Énergie :</span><span class="result-value"><?php echo $resultats['total_kwh']; ?> kWh</span></div>
                </div>
                <?php if (!empty($config['NOTIFICATION_EMAIL'])): ?>
                    <button type="submit" name="envoyer_email" class="btn btn-mail">ENVOI PAR EMAIL</button>
                <?php endif; ?>
                <button type="submit" name="telecharger_pdf" class="btn btn-pdf" formtarget="_blank">TÉLÉCHARGER PDF</button>
                <button type="submit" name="telecharger_csv" class="btn btn-csv">TÉLÉCHARGER CSV</button>
            <?php endif; ?>
        </form>
    </div>

    <script>
        const toggleAll = document.getElementById('toggleAll');
        const checkboxes = document.querySelectorAll('.col-check');
        toggleAll.addEventListener('change', function() {
            checkboxes.forEach(cb => { cb.checked = toggleAll.checked; });
        });
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                if(!this.checked) toggleAll.checked = false;
                if(document.querySelectorAll('.col-check:checked').length === checkboxes.length) toggleAll.checked = true;
            });
        });
    </script>
</body>
</html>
