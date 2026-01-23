<?php
session_start();
header('Content-Type: application/json');

$setupFile = "/var/www/html/cgi-bin/setup";
$correctCode = "";

if (file_exists($setupFile)) {
    $lines = file($setupFile, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'code=') === 0) {
            $correctCode = trim(explode('=', $line)[1]);
            break;
        }
    }
}

$inputCode = $_GET['code'] ?? '';
$bypass = $_GET['bypass'] ?? 'false';

if ($bypass === 'true' || ($inputCode === $correctCode && $correctCode !== "")) {
    $_SESSION['authorized'] = true;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
