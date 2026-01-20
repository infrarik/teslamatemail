<?php
// 1. Définition des chemins
$config_file = 'cgi-bin/setup';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- ÉTAPE A : LIRE LES VALEURS ACTUELLES DE SÉCURITÉ ---
    $protected_keys = ['email_enabled', 'telegram_enabled', 'mqtt_enabled'];
    $current_protected_values = [];
    
    // Valeurs par défaut si le fichier n'existe pas encore
    foreach ($protected_keys as $key) {
        $current_protected_values[$key] = 'false';
    }

    if (file_exists($config_file)) {
        $lines = file($config_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $k = strtolower(trim($parts[0]));
                if (in_array($k, $protected_keys)) {
                    $current_protected_values[$k] = trim($parts[1]);
                }
            }
        }
    }

    // --- ÉTAPE B : RÉCUPÉRER LES DONNÉES DU FORMULAIRE ---
    // On récupère les champs du formulaire de teslaconf.php
    $new_config = [
        'mqtt_host'          => $_POST['mqtt_host'] ?? '',
        'mqtt_port'          => $_POST['mqtt_port'] ?? '1883',
        'mqtt_user'          => $_POST['mqtt_user'] ?? '',
        'mqtt_pass'          => $_POST['mqtt_pass'] ?? '',
        'mqtt_topic'         => $_POST['mqtt_topic'] ?? '',
        'notification_email' => $_POST['notification_email'] ?? '',
        'telegram_bot_token' => $_POST['telegram_bot_token'] ?? '',
        'docker_path'        => $_POST['docker_path'] ?? ''
    ];

    // --- ÉTAPE C : FUSIONNER AVEC LES VALEURS PROTÉGÉES ---
    // On injecte les valeurs lues à l'étape A pour qu'elles ne soient pas perdues
    foreach ($current_protected_values as $key => $val) {
        $new_config[$key] = $val;
    }

    // --- ÉTAPE D : ÉCRITURE DU FICHIER ---
    $content = "### TeslaMate Config Updated - " . date('Y-m-d H:i:s') . " ###\n";
    foreach ($new_config as $key => $value) {
        $content .= "{$key}={$value}\n";
    }

    if (file_put_contents($config_file, $content)) {
        // Redirection vers la page de conf avec succès
        header('Location: teslaconf.php?saved=1');
        exit;
    } else {
        die("Erreur : Impossible d'écrire dans le fichier $config_file. Vérifiez les permissions.");
    }
} else {
    // Si accès direct sans POST
    header('Location: teslaconf.php');
    exit;
}

