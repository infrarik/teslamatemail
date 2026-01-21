<?php
header('Content-Type: text/html; charset=utf-8');

// --- 1. INITIALISATION ---
$setup_file = 'cgi-bin/setup';
$docker_path = '';
$db_user = "Non trouvé";
$db_pass = "Non trouvé";
$db_name = "Non trouvé";
$extraction_status = "En attente...";

// --- 2. RÉCUPÉRATION DU CHEMIN DEPUIS SETUP ---
if (file_exists($setup_file)) {
    $lines = file($setup_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode('=', $line, 2);
        if (count($parts) === 2 && strtoupper(trim($parts[0])) === 'DOCKER_PATH') {
            $docker_path = trim($parts[1]);
        }
    }
}

// --- 3. EXTRACTION PRÉCISE DES DONNÉES ---
if (!empty($docker_path) && file_exists($docker_path)) {
    $content = file_get_contents($docker_path);
    
    // Extraction du User
    if (preg_match('/POSTGRES_USER[:=]\s*(\S+)/', $content, $matches)) {
        $db_user = str_replace(['"', "'"], '', trim($matches[1]));
    }
    
    // Extraction du Password
    if (preg_match('/POSTGRES_PASSWORD[:=]\s*(\S+)/', $content, $matches)) {
        $db_pass = str_replace(['"', "'"], '', trim($matches[1]));
    }
    
    // Extraction de la DB
    if (preg_match('/POSTGRES_DB[:=]\s*(\S+)/', $content, $matches)) {
        $db_name = str_replace(['"', "'"], '', trim($matches[1]));
    }
    
    $extraction_status = "Extraction réussie depuis : " . htmlspecialchars($docker_path);
} else {
    $extraction_status = "Erreur : Chemin DOCKER_PATH invalide ou fichier introuvable.";
}

// --- 4. AFFICHAGE DES RÉSULTATS ---
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Extraction Données PostgreSQL</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-white font-sans p-10">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-3xl font-bold text-red-500 mb-2">Données PostgreSQL Extraites</h1>
        <p class="text-gray-400 mb-8 italic"><?= $extraction_status ?></p>

        <div class="grid grid-cols-1 gap-6">
            <div class="bg-slate-800 p-6 rounded-xl border-l-4 border-blue-500 shadow-lg">
                <span class="text-blue-400 text-xs font-bold uppercase tracking-wider">Utilisateur (USER)</span>
                <p class="text-2xl font-mono mt-1"><?= htmlspecialchars($db_user) ?></p>
            </div>

            <div class="bg-slate-800 p-6 rounded-xl border-l-4 border-green-500 shadow-lg">
                <span class="text-green-400 text-xs font-bold uppercase tracking-wider">Mot de passe (PASS)</span>
                <p class="text-2xl font-mono mt-1"><?= htmlspecialchars($db_pass) ?></p>
            </div>

            <div class="bg-slate-800 p-6 rounded-xl border-l-4 border-purple-500 shadow-lg">
                <span class="text-purple-400 text-xs font-bold uppercase tracking-wider">Base de données (DB)</span>
                <p class="text-2xl font-mono mt-1"><?= htmlspecialchars($db_name) ?></p>
            </div>
        </div>

        <div class="mt-10 p-4 bg-slate-800/50 rounded-lg text-sm text-gray-400 border border-slate-700">
            <strong>Note de debug :</strong> Si les valeurs affichées sont "Non trouvé", vérifiez que les variables dans votre fichier <code><?= basename($docker_path) ?></code> s'appellent bien <code>POSTGRES_USER</code>, <code>POSTGRES_PASSWORD</code> et <code>POSTGRES_DB</code>.
        </div>
    </div>
</body>
</html>
