<?php
// --- 1. LECTURE DU SETUP ---
$file = 'cgi-bin/setup';
$config = ['NOTIFICATION_EMAIL' => '', 'DOCKER_PATH' => '', 'LANGUAGE' => 'fr', 'CURRENCY' => 'EUR'];

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

// Détection de la langue unique via setup
$lang = (isset($config['LANGUAGE']) && strtolower($config['LANGUAGE']) === 'en') ? 'en' : 'fr';
$monnaie = $config['CURRENCY'] ?? 'EUR';

// --- 2. DICTIONNAIRE DE TRADUCTION ---
$texts = [
    'fr' => [
        'title' => "Consommation",
        'lbl_car' => "Véhicule",
        'lbl_geo' => "Lieu (Geofence)",
        'all_locations' => "-- TOUS LES LIEUX --",
        'lbl_start' => "Début",
        'lbl_end' => "Fin",
        'lbl_price' => "Prix du kWh",
        'lbl_details' => "Export complet",
        'lbl_select_all' => "TOUT SÉLECTIONNER",
        'btn_valider' => "VALIDER",
        'res_dist' => "Distance :",
        'res_count' => "Charges :",
        'res_energy' => "Énergie :",
        'res_cost' => "Coût total :",
        'btn_email' => "ENVOI PAR EMAIL",
        'btn_pdf' => "TÉLÉCHARGER PDF",
        'btn_csv' => "TÉLÉCHARGER CSV",
        'report_title' => "Rapport TeslaMate",
        'period' => "Période",
        'car' => "Véhicule",
        'dist_label' => "Distance",
        'charges_label' => "Charges",
        'energy_label' => "Énergie",
        'cost_label' => "Coût",
        'print_btn' => "Imprimer / PDF",
        'sent_to' => "Envoyé à",
        'cols' => ['date'=>'Date','kwh'=>'kWh chargés','cost'=>'Coût','duree'=>'Durée de charge','km'=>'km parcourus','ville'=>'Ville','gps'=>'GPS']
    ],
    'en' => [
        'title' => "Consumption",
        'lbl_car' => "Vehicle",
        'lbl_geo' => "Location (Geofence)",
        'all_locations' => "-- ALL LOCATIONS --",
        'lbl_start' => "Start",
        'lbl_end' => "End",
        'lbl_price' => "kWh Price",
        'lbl_details' => "Detailed Export",
        'lbl_select_all' => "SELECT ALL",
        'btn_valider' => "CALCULATE",
        'res_dist' => "Distance:",
        'res_count' => "Charges:",
        'res_energy' => "Energy:",
        'res_cost' => "Total Cost:",
        'btn_email' => "SEND BY EMAIL",
        'btn_pdf' => "DOWNLOAD PDF",
        'btn_csv' => "DOWNLOAD CSV",
        'report_title' => "TeslaMate Report",
        'period' => "Period",
        'car' => "Vehicle",
        'dist_label' => "Distance",
        'charges_label' => "Charges",
        'energy_label' => "Energy",
        'cost_label' => "Cost",
        'print_btn' => "Print / PDF",
        'sent_to' => "Sent to",
        'cols' => ['date'=>'Date','kwh'=>'kWh charged','cost'=>'Cost','duree'=>'Duration','km'=>'km driven','ville'=>'City','gps'=>'GPS']
    ]
];
$t = $texts[$lang];

// --- 3. EXTRACTION IDENTIFIANTS DOCKER ---
$db_user = "teslamate"; $db_pass = "secret_password"; $db_name = "teslamate";
if (!empty($config['DOCKER_PATH']) && file_exists($config['DOCKER_PATH'])) {
    $docker_content = file_get_contents($config['DOCKER_PATH']);
    if (preg_match('/POSTGRES_USER[:=]\s*(\S+)/', $docker_content, $m)) $db_user = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_PASSWORD[:=]\s*(\S+)/', $docker_content, $m)) $db_pass = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_DB[:=]\s*(\S+)/', $docker_content, $m)) $db_name = str_replace(['"', "'"], '', trim($m[1]));
}

// --- 4. CONNEXION DB ---
try {
    $pdo = new PDO("pgsql:host=localhost;port=5432;dbname=$db_name", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// --- 5. RÉCUPÉRATION DES VÉHICULES ET GEOFENCES ---
$stmt_cars = $pdo->query("SELECT id, COALESCE(name, model) as display_name FROM cars ORDER BY id ASC");
$cars = $stmt_cars->fetchAll(PDO::FETCH_ASSOC);
$stmt_geo = $pdo->query("SELECT id, name FROM geofences ORDER BY name ASC");
$geofences = $stmt_geo->fetchAll(PDO::FETCH_ASSOC);

// --- 6. LOGIQUE DE CALCUL ET EXPORT ---
$resultats = ['nb' => 0, 'total_kwh' => 0, 'total_km' => 0, 'total_cost' => 0];
$historique_fusionne = [];
$date_debut = $_POST['date_debut'] ?? date('Y-m-01');
$date_fin = $_POST['date_fin'] ?? date('Y-m-d');
$kwh_price = floatval($_POST['kwh_price'] ?? 0);
$selected_car = $_POST['car_id'] ?? ($cars[0]['id'] ?? 1);
$selected_geo = $_POST['geofence'] ?? 'TOUS';
$export_complet = isset($_POST['export_complet']) ? 1 : 0;
$cols = $_POST['cols'] ?? ['date', 'kwh', 'cost', 'duree', 'km', 'ville', 'gps'];
$status_message = "";

$car_name_display = "Vehicle";
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
            $cost_day = $c['kwh'] * $kwh_price;
            $temp_hist[$d] = ['date' => $d, 'kwh' => $c['kwh'], 'cost' => $cost_day, 'duree' => $c['duree'], 'km' => 0, 'ville' => $c['ville'], 'gps' => round($c['lat'],5).','.round($c['lon'],5)];
            $total_kwh_accumule += $c['kwh'];
            if ($c['kwh'] > 0) $compteur_jours_charge++;
        }
        foreach ($trajets_par_jour as $trajet) {
            $d = $trajet['date_f'];
            if (!isset($temp_hist[$d])) {
                $temp_hist[$d] = ['date' => $d, 'kwh' => 0, 'cost' => 0, 'duree' => $trajet['duree'], 'km' => $trajet['km'], 'ville' => '', 'gps' => ''];
            } else {
                $temp_hist[$d]['km'] += $trajet['km'];
                $temp_hist[$d]['duree'] += $trajet['duree'];
            }
            $total_km_accumule += $trajet['km'];
        }
        ksort($temp_hist);
        $historique_fusionne = $temp_hist;
        $resultats = [
            'nb' => $compteur_jours_charge, 
            'total_kwh' => round($total_kwh_accumule, 2), 
            'total_km' => round($total_km_accumule, 1),
            'total_cost' => round($total_kwh_accumule * $kwh_price, 2)
        ];

    } catch (Exception $e) { die("Erreur : " . $e->getMessage()); }

    // --- EMAIL ---
    if (isset($_POST['envoyer_email']) && !empty($config['NOTIFICATION_EMAIL'])) {
        $to = $config['NOTIFICATION_EMAIL']; 
        $subject = $t['report_title'] . " - $car_name_display - $date_debut / $date_fin";
        $body = $t['car'] . " : $car_name_display\n";
        $body .= $t['dist_label'] . " : ".$resultats['total_km']." km | ".$t['energy_label'] . " : ".$resultats['total_kwh']." kWh | ".$t['cost_label'] . " : ".$resultats['total_cost']." $monnaie\n\nDétails :\n";
        foreach ($historique_fusionne as $l) {
            $line = [];
            if(in_array('date', $cols)) $line[] = date('d/m/Y', strtotime($l['date']));
            if(in_array('kwh', $cols)) $line[] = ($l['kwh']>0 ? round($l['kwh'],2).' kWh' : '');
            if(in_array('cost', $cols)) $line[] = ($l['cost']>0 ? round($l['cost'],2).' '.$monnaie : '');
            if(in_array('km', $cols)) $line[] = ($l['km']>0 ? round($l['km'],1).' km' : '');
            $body .= implode(' | ', array_filter($line)) . "\n";
        }
        mail($to, $subject, $body, "From: noreply@teslamate.local");
        $status_message = $t['sent_to'] . " $to";
    }

    // --- PDF ---
    if (isset($_POST['telecharger_pdf'])) {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:sans-serif;padding:20px} h1{color:#dc2626;text-align:center} table{width:100%;border-collapse:collapse;margin-top:20px} td,th{border:1px solid #ddd;padding:10px;text-align:center;font-size:13px} th{background:#dc2626;color:#fff}</style></head><body>';
        echo '<h1>'.$t['report_title'].' - '.htmlspecialchars($car_name_display).'</h1>';
        echo '<p style="text-align:center">'.$t['period'].' : '.$date_debut.' au '.$date_fin.'</p>';
        echo '<div style="margin-bottom:20px;text-align:center"><strong>'.$t['dist_label'].' :</strong> '.$resultats['total_km'].' km | <strong>'.$t['energy_label'].' :</strong> '.$resultats['total_kwh'].' kWh | <strong>'.$t['cost_label'].' :</strong> '.$resultats['total_cost'].' '.$monnaie.'</div>';
        echo '<table><tr>';
        foreach($cols as $c) echo "<th>".$t['cols'][$c]."</th>";
        echo '</tr>';
        foreach ($historique_fusionne as $l) {
            echo '<tr>';
            if(in_array('date', $cols)) echo '<td>'.date('d/m/Y', strtotime($l['date'])).'</td>';
            if(in_array('kwh', $cols)) echo '<td>'.($l['kwh']>0?round($l['kwh'],2):'').'</td>';
            if(in_array('cost', $cols)) echo '<td>'.($l['cost']>0?round($l['cost'],2):'').'</td>';
            if(in_array('duree', $cols)) echo '<td>'.round($l['duree']).' min</td>';
            if(in_array('km', $cols)) echo '<td>'.($l['km']>0?round($l['km'],1):'').'</td>';
            if(in_array('ville', $cols)) echo '<td>'.htmlspecialchars($l['ville']).'</td>';
            if(in_array('gps', $cols)) echo '<td>'.($l['gps'] != '0,0' ? $l['gps'] : '-').'</td>';
            echo '</tr>';
        }
        echo '</table><br><button onclick="window.print()" style="display:block;margin:auto;padding:10px 20px;background:#dc2626;color:#fff;border:none;border-radius:5px;cursor:pointer">'.$t['print_btn'].'</button></body></html>';
        exit;
    }

    // --- CSV ---
    if (isset($_POST['telecharger_csv'])) {
        header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="export.csv"');
        $f = fopen('php://output', 'w'); 
        $headers = [];
        foreach($cols as $c) $headers[] = $t['cols'][$c];
        fputcsv($f, $headers, ';');
        foreach($historique_fusionne as $l) {
            $row = [];
            foreach($cols as $c) {
                if ($c === 'cost') {
                    $row[] = ($l['cost'] > 0 ? round($l['cost'], 2) : '');
                } else {
                    $row[] = $l[$c] ?? '';
                }
            }
            fputcsv($f, $row, ';');
        }
        fclose($f); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
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
        input[type="date"], input[type="number"], select { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.3); color: #fff; font-size: 16px; box-sizing: border-box; }
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
        .alert { padding: 12px; border-radius: 8px; text-align: center; margin-bottom: 20px; background: rgba(34, 197, 94, 0.2); color: #4ade80; }
        #export_complet:checked ~ .checkbox-group { display: block; }
        .price-input-container { position: relative; }
        .currency-badge { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #999; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <a href="tesla.php" class="back-button"><svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
        <h1><?php echo $t['title']; ?></h1>
        
        <?php if ($status_message): ?><div class="alert"><?php echo $status_message; ?></div><?php endif; ?>
        
        <form method="POST">
            <label><?php echo $t['lbl_car']; ?></label>
            <select name="car_id">
                <?php foreach ($cars as $car): ?>
                    <option value="<?php echo $car['id']; ?>" <?php if($selected_car == $car['id']) echo 'selected'; ?>><?php echo htmlspecialchars($car['display_name']); ?></option>
                <?php endforeach; ?>
            </select>

            <label><?php echo $t['lbl_geo']; ?></label>
            <select name="geofence">
                <option value="TOUS"><?php echo $t['all_locations']; ?></option>
                <?php foreach ($geofences as $geo): ?>
                    <option value="<?php echo $geo['id']; ?>" <?php if($selected_geo == $geo['id']) echo 'selected'; ?>><?php echo htmlspecialchars($geo['name']); ?></option>
                <?php endforeach; ?>
            </select>
            
            <label><?php echo $t['lbl_start']; ?></label>
            <input type="date" name="date_debut" value="<?php echo $date_debut; ?>" required>
            
            <label><?php echo $t['lbl_end']; ?></label>
            <input type="date" name="date_fin" value="<?php echo $date_fin; ?>" required>

            <label><?php echo $t['lbl_price']; ?> (<?php echo $monnaie; ?>)</label>
            <div class="price-input-container">
                <input type="number" name="kwh_price" step="0.0001" value="<?php echo $kwh_price; ?>" placeholder="0.0000">
                <span class="currency-badge"><?php echo $monnaie; ?></span>
            </div>
            
            <div style="margin-top:20px;">
                <input type="checkbox" id="export_complet" name="export_complet" <?php if($export_complet) echo 'checked'; ?>>
                <label for="export_complet" style="display:inline; text-transform:none; color:white; font-size:14px; margin-left:10px;"><?php echo $t['lbl_details']; ?></label>
                
                <div class="checkbox-group">
                    <label class="checkbox-item"><input type="checkbox" id="toggleAll" checked> <span><?php echo $t['lbl_select_all']; ?></span></label>
                    <?php foreach($t['cols'] as $key => $label): ?>
                    <label class="checkbox-item"><input type="checkbox" name="cols[]" value="<?php echo $key; ?>" class="col-check" <?php if(in_array($key, $cols)) echo 'checked'; ?>> <span><?php echo $label; ?></span></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" name="calculer" class="btn btn-calc"><?php echo $t['btn_valider']; ?></button>

            <?php if ($resultats['total_km'] > 0 || $resultats['total_kwh'] > 0): ?>
                <div class="result-box">
                    <div class="result-item"><span><?php echo $t['res_dist']; ?></span><span class="result-value"><?php echo $resultats['total_km']; ?> km</span></div>
                    <div class="result-item"><span><?php echo $t['res_count']; ?></span><span class="result-value"><?php echo $resultats['nb']; ?></span></div>
                    <div class="result-item"><span><?php echo $t['res_energy']; ?></span><span class="result-value"><?php echo $resultats['total_kwh']; ?> kWh</span></div>
                    <div class="result-item"><span><?php echo $t['res_cost']; ?></span><span class="result-value"><?php echo $resultats['total_cost']; ?> <?php echo $monnaie; ?></span></div>
                </div>
                
                <?php if (!empty($config['NOTIFICATION_EMAIL'])): ?>
                    <button type="submit" name="envoyer_email" class="btn btn-mail"><?php echo $t['btn_email']; ?></button>
                <?php endif; ?>
                
                <button type="submit" name="telecharger_pdf" class="btn btn-pdf" formtarget="_blank"><?php echo $t['btn_pdf']; ?></button>
                <button type="submit" name="telecharger_csv" class="btn btn-csv"><?php echo $t['btn_csv']; ?></button>
            <?php endif; ?>
        </form>
    </div>

    <script>
        const toggleAll = document.getElementById('toggleAll');
        const checkboxes = document.querySelectorAll('.col-check');
        if(toggleAll) {
            toggleAll.addEventListener('change', function() { 
                checkboxes.forEach(cb => { cb.checked = toggleAll.checked; }); 
            });
        }
    </script>
</body>
</html>
