<?php
/**
 * Script d'envoi de test Telegram manuel
 * Lit le token dans cgi-bin/setup et les utilisateurs dans cgi-bin/telegram_users.json
 */

$setup_file = 'cgi-bin/setup';
$users_file = 'cgi-bin/telegram_users.json';
$token = '';

// 1. Extraction du token depuis le fichier setup
if (file_exists($setup_file)) {
    $lines = file($setup_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2 && strtolower(trim($parts[0])) === 'telegram_bot_token') {
            $token = trim($parts[1]);
            break;
        }
    }
}

if (empty($token)) {
    die("❌ Erreur : Token Telegram non trouvé dans $setup_file\n");
}

// 2. Chargement des utilisateurs
if (!file_exists($users_file)) {
    die("❌ Erreur : Fichier des utilisateurs $users_file introuvable\n");
}

$users = json_decode(file_get_contents($users_file), true) ?? [];

if (empty($users)) {
    die("⚠️ Aucun utilisateur configuré dans $users_file\n");
}

// 3. Fonction d'envoi
function sendTelegramMessage($token, $chat_id, $message) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) return false;
    $response = json_decode($result, true);
    return isset($response['ok']) && $response['ok'] === true;
}

// 4. Execution de l'envoi
echo "🚀 Envoi du test en cours...\n\n";

$message = "🔔 <b>Test Manuel TeslaMate</b>\n";
$message .= "Ceci est un message de test envoyé depuis le script PHP.\n";
$message .= "📅 Date : " . date('d/m/Y H:i:s');

foreach ($users as $user) {
    if (isset($user['chat_id']) && ($user['active'] ?? true)) {
        echo "📤 Envoi vers " . htmlspecialchars($user['name']) . " (" . $user['chat_id'] . ") : ";
        if (sendTelegramMessage($token, $user['chat_id'], $message)) {
            echo "✅ Succès\n";
        } else {
            echo "❌ Échec\n";
        }
    }
}
