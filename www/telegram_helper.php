<?php
/**
 * Helper pour envoyer des notifications Telegram
 * Usage: 
 * require_once 'telegram_helper.php';
 * sendTeslaMateNotification("🚗 Charge terminée à 85%");
 */

class TelegramHelper {
    private $config = [];
    private $users = [];
    
    public function __construct() {
        $this->loadConfig();
        $this->loadUsers();
    }
    
    private function loadConfig() {
        $config_file = 'cgi-bin/setup';
        if (file_exists($config_file)) {
            $lines = file($config_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $key = strtolower(trim($parts[0]));
                    $val = trim($parts[1]);
                    $this->config[$key] = $val;
                }
            }
        }
    }
    
    private function loadUsers() {
        $users_file = 'cgi-bin/telegram_users.json';
        if (file_exists($users_file)) {
            $this->users = json_decode(file_get_contents($users_file), true) ?? [];
        }
    }
    
    public function sendMessage($message, $parse_mode = 'HTML') {
        $token = $this->config['telegram_bot_token'] ?? '';
        
        if (empty($token)) {
            return ['success' => false, 'message' => 'Token Telegram non configuré'];
        }
        
        if (empty($this->users)) {
            return ['success' => false, 'message' => 'Aucun destinataire configuré'];
        }
        
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        foreach ($this->users as $user) {
            if (isset($user['chat_id']) && $user['active']) {
                if ($this->sendToUser($token, $user['chat_id'], $message, $parse_mode)) {
                    $success_count++;
                } else {
                    $error_count++;
                    $errors[] = $user['name'];
                }
            }
        }
        
        return [
            'success' => $success_count > 0,
            'sent' => $success_count,
            'failed' => $error_count,
            'errors' => $errors
        ];
    }
    
    private function sendToUser($token, $chat_id, $message, $parse_mode) {
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        
        $data = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => $parse_mode
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
}

/**
 * Fonction raccourci pour envoyer une notification
 */
function sendTeslaMateNotification($message) {
    $telegram = new TelegramHelper();
    return $telegram->sendMessage($message);
}

/**
 * Formatage de messages prédéfinis
 */
class TelegramMessages {
    public static function chargingStarted($battery_level) {
        return "🔌 <b>Charge démarrée</b>\n" .
               "🔋 Batterie: {$battery_level}%\n" .
               "📅 " . date('d/m/Y H:i');
    }
    
    public static function chargingComplete($battery_level) {
        return "✅ <b>Charge terminée</b>\n" .
               "🔋 Batterie: {$battery_level}%\n" .
               "📅 " . date('d/m/Y H:i');
    }
    
    public static function lowBattery($battery_level) {
        return "⚠️ <b>Batterie faible</b>\n" .
               "🔋 Batterie: {$battery_level}%\n" .
               "📅 " . date('d/m/Y H:i');
    }
    
    public static function updateAvailable($version) {
        return "🆕 <b>Mise à jour disponible</b>\n" .
               "📦 Version: {$version}\n" .
               "📅 " . date('d/m/Y H:i');
    }
    
    public static function doorOpen() {
        return "🚪 <b>Porte ouverte</b>\n" .
               "⚠️ Vérifiez votre véhicule\n" .
               "📅 " . date('d/m/Y H:i');
    }
    
    public static function custom($title, $message, $icon = "ℹ️") {
        return "{$icon} <b>{$title}</b>\n" .
               "{$message}\n" .
               "📅 " . date('d/m/Y H:i');
    }
}
?>

