<?php
header('Content-Type: application/json');

$token = $_POST['token'] ?? '';

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token manquant']);
    exit;
}

// Charger la liste des utilisateurs
$users_file = 'cgi-bin/telegram_users.json';
$users = [];

if (file_exists($users_file)) {
    $users = json_decode(file_get_contents($users_file), true) ?? [];
}

if (empty($users)) {
    echo json_encode(['success' => false, 'message' => 'Aucun destinataire configuré']);
    exit;
}

// Fonction pour envoyer un message Telegram
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
    
    if ($result === false) {
        return false;
    }
    
    $response = json_decode($result, true);
    return isset($response['ok']) && $response['ok'] === true;
}

// Message de test
$message = "🔔 <b>Test de notification TeslaMate</b>\n\n";
$message .= "✅ Votre bot Telegram est configuré correctement !\n";
$message .= "📅 " . date('d/m/Y H:i:s');

// Envoi à tous les utilisateurs
$success_count = 0;
$error_count = 0;
$errors = [];

foreach ($users as $user) {
    if (isset($user['chat_id']) && $user['active']) {
        if (sendTelegramMessage($token, $user['chat_id'], $message)) {
            $success_count++;
        } else {
            $error_count++;
            $errors[] = $user['name'];
        }
    }
}

if ($success_count > 0) {
    $msg = "Message envoyé à {$success_count} destinataire(s)";
    if ($error_count > 0) {
        $msg .= " (échec pour: " . implode(', ', $errors) . ")";
    }
    echo json_encode(['success' => true, 'message' => $msg]);
} else {
    echo json_encode(['success' => false, 'message' => 'Échec d\'envoi à tous les destinataires. Vérifiez le token et les Chat IDs.']);
}
?>

