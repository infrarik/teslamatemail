<?php
/**
 * Exemple de script pour envoyer une notification de charge
 * Ce script peut être appelé par un listener MQTT ou un cron
 */

require_once 'telegram_helper.php';

// Exemple 1: Message simple
$result = sendTeslaMateNotification("🔌 La charge a démarré !");

if ($result['success']) {
    echo "✅ Message envoyé à {$result['sent']} destinataire(s)\n";
} else {
    echo "❌ Échec d'envoi\n";
}

// Exemple 2: Message formaté avec batterie
$battery_level = 45; // Récupéré depuis MQTT
$message = TelegramMessages::chargingStarted($battery_level);
sendTeslaMateNotification($message);

// Exemple 3: Message personnalisé
$custom_message = TelegramMessages::custom(
    "Charge optimisée",
    "La charge se terminera à 8h00 demain matin.",
    "⏰"
);
sendTeslaMateNotification($custom_message);

// Exemple 4: Notification batterie faible
$battery = 15;
if ($battery < 20) {
    $alert = TelegramMessages::lowBattery($battery);
    sendTeslaMateNotification($alert);
}

// Exemple 5: Mise à jour disponible
$new_version = "2024.2.15";
$update_msg = TelegramMessages::updateAvailable($new_version);
sendTeslaMateNotification($update_msg);

// Exemple 6: Notification avec données MQTT
// Supposons que vous recevez ces données depuis MQTT
$mqtt_data = [
    'event' => 'charging_complete',
    'battery_level' => 85,
    'charge_added' => 45.2,
    'duration' => '2h 15min'
];

$detailed_message = "✅ <b>Charge terminée</b>\n\n";
$detailed_message .= "🔋 Batterie: {$mqtt_data['battery_level']}%\n";
$detailed_message .= "⚡ Ajouté: {$mqtt_data['charge_added']} kWh\n";
$detailed_message .= "⏱ Durée: {$mqtt_data['duration']}\n";
$detailed_message .= "📅 " . date('d/m/Y H:i');

sendTeslaMateNotification($detailed_message);
?>

