<?php
header('Content-Type: application/json');

// 1. Localisation du fichier de configuration
$config_file = __DIR__ . '/cgi-bin/setup';
$target_email = '';

// 2. Extraction de l'email depuis le fichier setup
if (file_exists($config_file)) {
    $lines = file($config_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Recherche la ligne qui contient notification_email (insensible à la casse)
        if (stripos(trim($line), 'notification_email') === 0) {
            $parts = explode('=', $line, 2);
            if (isset($parts[1])) {
                $target_email = trim($parts[1]);
            }
        }
    }
}

// 3. Validation de l'email trouvé
if (empty($target_email) || !filter_var($target_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false, 
        'message' => "Email invalide ou absent du fichier setup : '" . htmlspecialchars($target_email) . "'"
    ]);
    exit;
}

// 4. Préparation de l'envoi (Identique à la commande CLI qui fonctionne)
$subject = "Test TeslaMate";
$message = "Ceci est un test d'envoi depuis Teslamate Mail vers : " . $target_email;

// Construction des headers simplifiée pour coller à la commande 'mail'
$from = "teslamate@" . gethostname();
$headers = "From: <$from>\r\n";
$headers .= "Reply-To: <$from>\r\n";
$headers .= "Content-Type: text/plain; charset=utf-8";

// 5. Envoi
// Utilisation du paramètre -f pour forcer l'enveloppe de l'expéditeur (indispensable pour Postfix)
if (mail($target_email, $subject, $message, $headers, "-f$from")) {
    echo json_encode([
        'success' => true, 
        'message' => "Succès : Email envoyé à " . $target_email
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => "Erreur système : PHP n'a pas pu transmettre à Postfix."
    ]);
}
exit;

