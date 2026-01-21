<?php
// --- 1. CONFIGURATION & LECTURE DU SETUP ---
$file = 'cgi-bin/setup';

// Valeurs par défaut
$config = [
    'notification_email' => 'Non configuré',
    'email_enabled' => 'False',
    'mqtt_enabled' => 'False',
    'telegram_enabled' => 'False',
    'LANGUAGE' => 'fr' // Par défaut
];

if (file_exists($file)) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            // On stocke tout en majuscule pour la clé dans $config pour uniformiser
            $config[strtoupper($key)] = $val;
        }
    }
}

// Détermination de la langue
$lang = (isset($config['LANGUAGE']) && strtolower($config['LANGUAGE']) === 'en') ? 'en' : 'fr';

// --- 2. DICTIONNAIRE DE TRADUCTION ---
$texts = [
    'fr' => [
        'title' => 'Notifications - TeslaMate',
        'h1' => 'Notifications',
        'lbl_email' => 'Email de destination',
        'lbl_telegram' => 'Service Telegram',
        'val_telegram' => 'Bot de notification',
        'lbl_mqtt' => 'Service MQTT',
        'val_mqtt' => 'Transmission Broker',
        'status_email' => 'Email :',
        'status_tg' => 'Telegram :',
        'status_mqtt' => 'MQTT :',
        'enabled' => 'ACTIVÉ',
        'disabled' => 'DÉSACTIVÉ',
        'running' => 'EN SERVICE',
        'stopped' => 'ARRÊTÉ',
        'btn_on' => 'ACTIVER',
        'btn_off' => 'DÉSACTIVER',
        'dash' => 'Dashboard',
        'conf' => 'Configuration'
    ],
    'en' => [
        'title' => 'Notifications - TeslaMate',
        'h1' => 'Notifications',
        'lbl_email' => 'Destination Email',
        'lbl_telegram' => 'Telegram Service',
        'val_telegram' => 'Notification Bot',
        'lbl_mqtt' => 'MQTT Service',
        'val_mqtt' => 'Broker Transmission',
        'status_email' => 'Email:',
        'status_tg' => 'Telegram:',
        'status_mqtt' => 'MQTT:',
        'enabled' => 'ENABLED',
        'disabled' => 'DISABLED',
        'running' => 'RUNNING',
        'stopped' => 'STOPPED',
        'btn_on' => 'ENABLE',
        'btn_off' => 'DISABLE',
        'dash' => 'Dashboard',
        'conf' => 'Settings'
    ]
];
$t = $texts[$lang];

// --- 3. TRAITEMENT DES CHANGEMENTS D'ÉTAT (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_type'])) {
    if (file_exists($file)) {
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $type = $_POST['toggle_type'];  
        $current_val = $_POST['current_val'];
        $new_val = ($current_val === 'True') ? 'False' : 'True';
        
        $newLines = [];
        $found = false;
        
        foreach ($lines as $line) {
            // On vérifie la clé sans tenir compte de la casse
            if (preg_match("/^" . preg_quote($type, '/') . "=/i", trim($line))) {
                $newLines[] = $type . '=' . $new_val;
                $found = true;
            } else {
                $newLines[] = $line;
            }
        }
        if (!$found) $newLines[] = $type . '=' . $new_val;
        
        file_put_contents($file, implode("\n", $newLines));
        header("Location: teslanotif.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['title']; ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); color: #fff; margin: 0; padding: 20px; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { background: rgba(255, 255, 255, 0.05); border-radius: 20px; padding: 40px; max-width: 500px; width: 100%; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); }
        h1 { margin: 0 0 30px 0; font-size: 28px; text-align: center; color: #dc2626; }
        .info-block { background: rgba(0, 0, 0, 0.3); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .label { font-size: 11px; color: #999; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; }
        .value { font-size: 16px; font-weight: bold; color: #fff; margin-bottom: 10px; }
        .status { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-bottom: 15px; }
        .status.active { background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
        .status.inactive { background: rgba(156, 163, 175, 0.2); color: #9ca3af; border: 1px solid rgba(156, 163, 175, 0.3); }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1); }
        .link { display: inline-block; margin: 5px; padding: 12px 20px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 10px; transition: 0.3s; font-size: 14px; }
        .link:hover { background: rgba(255,255,255,0.2); }
        .link.primary { background: #dc2626; }
        .link.primary:hover { background: #b91c1c; }
        .toggle-btn { width: 100%; padding: 12px; border: none; border-radius: 10px; color: white; font-size: 14px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-enable { background: #16a34a; }
        .btn-disable { background: #4b5563; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $t['h1']; ?></h1>

        <div class="info-block">
            <div class="label"><?php echo $t['lbl_email']; ?></div>
            <div class="value"><?php echo htmlspecialchars($config['NOTIFICATION_EMAIL'] ?? 'Non configuré'); ?></div>
            <span class="status <?php echo ($config['EMAIL_ENABLED'] === 'True') ? 'active' : 'inactive'; ?>">
                <?php echo $t['status_email'] . ' ' . (($config['EMAIL_ENABLED'] === 'True') ? $t['enabled'] : $t['disabled']); ?>
            </span>
            <form method="POST">
                <input type="hidden" name="toggle_type" value="email_enabled">
                <input type="hidden" name="current_val" value="<?php echo $config['EMAIL_ENABLED']; ?>">
                <button type="submit" class="toggle-btn <?php echo ($config['EMAIL_ENABLED'] === 'True') ? 'btn-disable' : 'btn-enable'; ?>">
                    <?php echo (($config['EMAIL_ENABLED'] === 'True') ? $t['btn_off'] : $t['btn_on']) . ' EMAIL'; ?>
                </button>
            </form>
        </div>

        <div class="info-block">
            <div class="label"><?php echo $t['lbl_telegram']; ?></div>
            <div class="value"><?php echo $t['val_telegram']; ?></div>
            <span class="status <?php echo ($config['TELEGRAM_ENABLED'] === 'True') ? 'active' : 'inactive'; ?>">
                <?php echo $t['status_tg'] . ' ' . (($config['TELEGRAM_ENABLED'] === 'True') ? $t['enabled'] : $t['disabled']); ?>
            </span>
            <form method="POST">
                <input type="hidden" name="toggle_type" value="telegram_enabled">
                <input type="hidden" name="current_val" value="<?php echo $config['TELEGRAM_ENABLED']; ?>">
                <button type="submit" class="toggle-btn <?php echo ($config['TELEGRAM_ENABLED'] === 'True') ? 'btn-disable' : 'btn-enable'; ?>">
                    <?php echo (($config['TELEGRAM_ENABLED'] === 'True') ? $t['btn_off'] : $t['btn_on']) . ' TELEGRAM'; ?>
                </button>
            </form>
        </div>

        <div class="info-block">
            <div class="label"><?php echo $t['lbl_mqtt']; ?></div>
            <div class="value"><?php echo $t['val_mqtt']; ?></div>
            <span class="status <?php echo ($config['MQTT_ENABLED'] === 'True') ? 'active' : 'inactive'; ?>">
                <?php echo $t['status_mqtt'] . ' ' . (($config['MQTT_ENABLED'] === 'True') ? $t['running'] : $t['stopped']); ?>
            </span>
            <form method="POST">
                <input type="hidden" name="toggle_type" value="mqtt_enabled">
                <input type="hidden" name="current_val" value="<?php echo $config['MQTT_ENABLED']; ?>">
                <button type="submit" class="toggle-btn <?php echo ($config['MQTT_ENABLED'] === 'True') ? 'btn-disable' : 'btn-enable'; ?>">
                    <?php echo (($config['MQTT_ENABLED'] === 'True') ? $t['btn_off'] : $t['btn_on']) . ' MQTT'; ?>
                </button>
            </form>
        </div>

        <div class="footer">
            <a href="tesla.php" class="link primary"><?php echo $t['dash']; ?></a>
            <a href="teslaconf.php" class="link"><?php echo $t['conf']; ?></a>
        </div>
    </div>
</body>
</html>
