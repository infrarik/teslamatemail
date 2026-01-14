<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dir = 'cgi-bin';
    $config_file = $dir . '/setup';

    // Récupération des données du formulaire
    $mqtt_host = $_POST['mqtt_host'] ?? '';
    $mqtt_port = $_POST['mqtt_port'] ?? '1883';
    $mqtt_user = $_POST['mqtt_user'] ?? '';
    $mqtt_pass = $_POST['mqtt_pass'] ?? '';
    $mqtt_topic = $_POST['mqtt_topic'] ?? 'teslamate/cars/1';
    $notification_email = $_POST['notification_email'] ?? '';
    $docker_path = $_POST['docker_path'] ?? '';
    $telegram_bot_token = $_POST['telegram_bot_token'] ?? '';

    // Création du contenu du fichier
    $content = "### TeslaMate Configuration ###\n";
    $content .= "mqtt_host={$mqtt_host}\n";
    $content .= "mqtt_port={$mqtt_port}\n";
    $content .= "mqtt_user={$mqtt_user}\n";
    $content .= "mqtt_pass={$mqtt_pass}\n";
    $content .= "mqtt_topic={$mqtt_topic}\n";
    $content .= "notification_email={$notification_email}\n";
    $content .= "docker_path={$docker_path}\n";
    $content .= "telegram_bot_token={$telegram_bot_token}\n";

    // Sauvegarde du fichier
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Sauvegarde et redirection avec le paramètre 'saved' pour afficher l'alerte
    if (file_put_contents($config_file, $content)) {
        header('Location: teslaconf.php?saved=1');
    } else {
        header('Location: teslaconf.php?error=1');
    }
    exit;
}
?>

