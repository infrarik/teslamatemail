<?php
// --- CONFIGURATION ---
$file = 'cgi-bin/setup';

// 1. Initialisation des variables par défaut
$config = [
    'notification_email' => 'Non configuré',
    'email_enabled' => 'False',
    'mqtt_enabled' => 'False',
    'telegram_enabled' => 'False' // Nouvelle variable
];

// 2. Traitement des changements d'état (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_type'])) {
    if (file_exists($file)) {
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $type = $_POST['toggle_type']; // 'email_enabled', 'mqtt_enabled' ou 'telegram_enabled'
        $current_val = $_POST['current_val'];
        $new_val = ($current_val === 'True') ? 'False' : 'True';
        
        $newLines = [];
        $found = false;
        
        foreach ($lines as $line) {
            if (strpos(trim($line), $type . '=') === 0) {
                $newLines[] = $type . '=' . $new_val;
                $found = true;
            } else {
                $newLines[] = $line;
            }
        }
        if (!$found) $newLines[] = $type . '=' . $new_val;
        
        file_put_contents($file, implode("\n", $newLines));
        header("Location: teslamail.php");
        exit;
    }
}

// 3. Lecture du fichier setup pour l'affichage
if (file_exists($file)) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            $config[$key] = $val;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - TeslaMate</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); color: #fff; margin: 0; padding: 20px; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { background: rgba(255, 255, 255, 0.05); border-radius: 20px; padding: 40px; max-width: 500px; width: 100%; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); }
        h1 { margin: 0 0 30px 0; font-size: 28px; text-align: center; color: #dc2626; }
        .info-block { background: rgba(0, 0, 0, 0.3); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .label { font-size: 12px; color: #999; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; }
        .value { font-size: 18px; font-weight: bold; color: #fff; margin-bottom: 10px; }
        .status { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; margin-bottom: 15px; }
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
        <h1>Notifications</h1>

        <div class="info-block">
            <div class="label">Email de destination</div>
            <div class="value"><?php echo htmlspecialchars($config['notification_email']); ?></div>
            <span class="status <?php echo ($config['email_enabled'] === 'True') ? 'active' : 'inactive'; ?>">
                Email : <?php echo ($config['email_enabled'] === 'True') ? 'ACTIVÉ' : 'DÉSACTIVÉ'; ?>
            </span>
            <form method="POST">
                <input type="hidden" name="toggle_type" value="email_enabled">
                <input type="hidden" name="current_val" value="<?php echo $config['email_enabled']; ?>">
                <button type="submit" class="toggle-btn <?php echo ($config['email_enabled'] === 'True') ? 'btn-disable' : 'btn-enable'; ?>">
                    <?php echo ($config['email_enabled'] === 'True') ? 'DÉSACTIVER EMAIL' : 'ACTIVER EMAIL'; ?>
                </button>
            </form>
        </div>

        <div class="info-block">
            <div class="label">Service Telegram</div>
            <div class="value">Bot de notification</div>
            <span class="status <?php echo ($config['telegram_enabled'] === 'True') ? 'active' : 'inactive'; ?>">
                Telegram : <?php echo ($config['telegram_enabled'] === 'True') ? 'ACTIVÉ' : 'DÉSACTIVÉ'; ?>
            </span>
            <form method="POST">
                <input type="hidden" name="toggle_type" value="telegram_enabled">
                <input type="hidden" name="current_val" value="<?php echo $config['telegram_enabled']; ?>">
                <button type="submit" class="toggle-btn <?php echo ($config['telegram_enabled'] === 'True') ? 'btn-disable' : 'btn-enable'; ?>">
                    <?php echo ($config['telegram_enabled'] === 'True') ? 'DÉSACTIVER TELEGRAM' : 'ACTIVER TELEGRAM'; ?>
                </button>
            </form>
        </div>

        <div class="info-block">
            <div class="label">Service MQTT</div>
            <div class="value">Transmission Broker</div>
            <span class="status <?php echo ($config['mqtt_enabled'] === 'True') ? 'active' : 'inactive'; ?>">
                MQTT : <?php echo ($config['mqtt_enabled'] === 'True') ? 'EN SERVICE' : 'ARRÊTÉ'; ?>
            </span>
            <form method="POST">
                <input type="hidden" name="toggle_type" value="mqtt_enabled">
                <input type="hidden" name="current_val" value="<?php echo $config['mqtt_enabled']; ?>">
                <button type="submit" class="toggle-btn <?php echo ($config['mqtt_enabled'] === 'True') ? 'btn-disable' : 'btn-enable'; ?>">
                    <?php echo ($config['mqtt_enabled'] === 'True') ? 'DÉSACTIVER MQTT' : 'ACTIVER MQTT'; ?>
                </button>
            </form>
        </div>

        <div class="footer">
            <a href="tesla.html" class="link primary">Dashboard</a>
            <a href="teslaconf.php" class="link">Configuration</a>
        </div>
    </div>
</body>
</html>

