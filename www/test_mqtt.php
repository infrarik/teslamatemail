<?php
header('Content-Type: application/json');

$host  = $_POST['host'] ?? '';
$port  = intval($_POST['port'] ?? 1883);
$user  = $_POST['user'] ?? '';
$pass  = $_POST['pass'] ?? '';
$topic = $_POST['topic'] ?? 'test/topic';
$msg   = "mqtt en service";

if (empty($host)) {
    echo json_encode(['success' => false, 'message' => 'Hôte manquant']);
    exit;
}

$socket = @fsockopen($host, $port, $errno, $errstr, 5);
if (!$socket) {
    echo json_encode(['success' => false, 'message' => "Connexion impossible : $errstr"]);
    exit;
}

// --- MQTT CONNECT PAQUET ---
$clientId = "TeslaMate_Test_" . rand(1000, 9999);
$protocolName = "MQTT";
$connectFlags = 0x02; // Clean session
if ($user) $connectFlags |= 0x80;
if ($pass) $connectFlags |= 0x40;

$payload = pack('n', strlen($clientId)) . $clientId;
if ($user) $payload .= pack('n', strlen($user)) . $user;
if ($pass) $payload .= pack('n', strlen($pass)) . $pass;

$head = pack('n', strlen($protocolName)) . $protocolName . chr(0x04) . chr($connectFlags) . pack('n', 60);
$connectPacket = chr(0x10) . encodeLength(strlen($head) + strlen($payload)) . $head . $payload;

fwrite($socket, $connectPacket);
$response = fread($socket, 4); // Attend CONNACK

if (ord($response[0] ?? '') != 0x20) {
    echo json_encode(['success' => false, 'message' => "Le broker a refusé la connexion (Code: ".ord($response[3] ?? 0).")"]);
    fclose($socket);
    exit;
}

// --- MQTT PUBLISH PAQUET ---
$publishPayload = pack('n', strlen($topic)) . $topic . $msg;
$publishPacket = chr(0x30) . encodeLength(strlen($publishPayload)) . $publishPayload;

fwrite($socket, $publishPacket);
fclose($socket);

echo json_encode(['success' => true]);

// Fonction utilitaire pour la longueur variable MQTT
function encodeLength($l) {
    $string = "";
    do {
        $digit = $l % 128;
        $l = floor($l / 128);
        if ($l > 0) $digit = ($digit | 0x80);
        $string .= chr($digit);
    } while ($l > 0);
    return $string;
}

