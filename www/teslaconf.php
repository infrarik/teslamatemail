<?php
// 1. Définition des chemins
$dir = 'cgi-bin';
$config_file = $dir . '/setup';
$users_file = $dir . '/telegram_users.json';

// 2. Vérification et création du dossier si nécessaire
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// 3. Initialisation des variables par défaut
$config = [
    'mqtt_host' => '',
    'mqtt_port' => '1883',
    'mqtt_user' => '',
    'mqtt_pass' => '',
    'mqtt_topic' => 'teslamate/cars/1',
    'notification_email' => '',
    'docker_path' => '',
    'telegram_bot_token' => ''
];

// 4. Lecture du fichier si existant
if (file_exists($config_file)) {
    $lines = file($config_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorer les commentaires
        if (strpos(trim($line), '#') === 0) continue;
        
        // Séparer la clé et la valeur
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = strtolower(trim($parts[0])); // On force la clé en minuscule pour la correspondance
            $val = trim($parts[1]);             // On garde la valeur telle quelle (casse respectée)
            
            if (array_key_exists($key, $config)) {
                $config[$key] = $val;
            }
        }
    }
} else {
    // Création du fichier par défaut si inexistant
    $default_content = "### TeslaMate Config Initialized ###\n";
    foreach ($config as $k => $v) {
        $default_content .= "{$k}={$v}\n";
    }
    file_put_contents($config_file, $default_content);
}

// 5. Gestion des utilisateurs Telegram
$telegram_users = [];
if (file_exists($users_file)) {
    $telegram_users = json_decode(file_get_contents($users_file), true) ?? [];
}

// 6. Traitement suppression utilisateur
if (isset($_POST['delete_user'])) {
    $index = intval($_POST['delete_user']);
    if (isset($telegram_users[$index])) {
        array_splice($telegram_users, $index, 1);
        file_put_contents($users_file, json_encode($telegram_users, JSON_PRETTY_PRINT));
        header('Location: ' . $_SERVER['PHP_SELF'] . '?saved=1');
        exit;
    }
}

// 7. Ajout d'un utilisateur
if (isset($_POST['add_telegram_user'])) {
    $name = trim($_POST['telegram_user_name']);
    $chat_id = trim($_POST['telegram_chat_id']);
    
    if ($name && $chat_id) {
        $telegram_users[] = [
            'name' => $name,
            'chat_id' => $chat_id,
            'active' => true
        ];
        file_put_contents($users_file, json_encode($telegram_users, JSON_PRETTY_PRINT));
        header('Location: ' . $_SERVER['PHP_SELF'] . '?saved=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#dc2626">
  <title>Configuration TeslaMate</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-black text-white min-h-screen">
  <div class="max-w-2xl mx-auto p-4 pb-12">
    <div class="flex items-center gap-4 mb-8 pt-4">
      <a href="tesla.php" class="p-2 bg-gray-800 hover:bg-gray-700 rounded-full transition-colors">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
      </a>
      <h1 class="text-2xl font-bold">Configuration</h1>
    </div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="mb-6 p-4 bg-orange-500/20 border border-orange-500/50 rounded-2xl flex items-center gap-4 animate-pulse">
        <div class="p-2 bg-orange-500 rounded-full text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
        <div>
            <p class="text-orange-300 font-bold">Configuration enregistrée !</p>
            <p class="text-orange-200/80 text-sm">Pensez à <a href="teslanotif.php" class="underline font-bold hover:text-white transition-colors">activer les notifications</a> suite à ces changements.</p>
        </div>
    </div>
    <?php endif; ?>

    <form id="configForm" action="teslaconfig_handler.php" method="POST" class="space-y-6">
      
      <div class="bg-gray-800/50 border border-gray-700 rounded-2xl p-6 shadow-xl">
        <div class="flex items-center gap-3 mb-6">
          <div class="p-2 bg-red-500/20 rounded-lg">
            <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
          </div>
          <h2 class="text-xl font-semibold">Serveur MQTT</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-2">
            <label class="text-sm text-gray-400 ml-1">Adresse Serveur</label>
            <input type="text" name="mqtt_host" id="mqtt_host" value="<?php echo htmlspecialchars($config['mqtt_host']); ?>" placeholder="ex: 192.168.1.50" required
              class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 focus:border-red-500 focus:ring-1 focus:ring-red-500 outline-none transition-all text-white">
          </div>
          <div class="space-y-2">
            <label class="text-sm text-gray-400 ml-1">Port</label>
            <input type="number" name="mqtt_port" id="mqtt_port" value="<?php echo htmlspecialchars($config['mqtt_port']); ?>" placeholder="1883" required
              class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 focus:border-red-500 focus:ring-1 focus:ring-red-500 outline-none transition-all text-white">
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
          <div class="space-y-2">
            <label class="text-sm text-gray-400 ml-1">Login</label>
            <input type="text" name="mqtt_user" id="mqtt_user" value="<?php echo htmlspecialchars($config['mqtt_user']); ?>" placeholder="Utilisateur"
              class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 focus:border-red-500 focus:ring-1 focus:ring-red-500 outline-none transition-all text-white">
          </div>
          <div class="space-y-2">
            <label class="text-sm text-gray-400 ml-1">Mot de passe</label>
            <input type="password" name="mqtt_pass" id="mqtt_pass" value="<?php echo htmlspecialchars($config['mqtt_pass']); ?>" placeholder="••••••••"
              class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 focus:border-red-500 focus:ring-1 focus:ring-red-500 outline-none transition-all text-white">
          </div>
        </div>

        <div class="mt-4 space-y-2">
          <label class="text-sm text-gray-400 ml-1">Topic de base</label>
          <input type="text" name="mqtt_topic" id="mqtt_topic" value="<?php echo htmlspecialchars($config['mqtt_topic']); ?>" placeholder="teslamate/cars/1" required
            class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 focus:border-red-500 focus:ring-1 focus:ring-red-500 outline-none transition-all text-white">
        </div>
      </div>

      <div class="bg-gray-800/50 border border-gray-700 rounded-2xl p-6 shadow-xl">
        <div class="flex items-center gap-3 mb-6">
          <div class="p-2 bg-blue-500/20 rounded-lg">
            <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
          </div>
          <h2 class="text-xl font-semibold">Notifications Email</h2>
        </div>

        <div class="space-y-2">
          <label class="text-sm text-gray-400 ml-1">Email de destination</label>
          <input type="email" id="notification_email" name="notification_email" value="<?php echo htmlspecialchars($config['notification_email']); ?>" placeholder="votre@email.com" required
            class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all text-white">
        </div>
      </div>

      <div class="bg-gray-800/50 border border-gray-700 rounded-2xl p-6 shadow-xl">
        <div class="flex items-center gap-3 mb-6">
          <div class="p-2 bg-cyan-500/20 rounded-lg">
            <svg class="w-6 h-6 text-cyan-500" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.903-1.056-.692-1.653-1.123-2.678-1.799-1.185-.781-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.139-5.062 3.345-.479.329-.913.489-1.302.481-.428-.009-1.252-.242-1.865-.442-.752-.244-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635.099-.002.321.023.465.14.121.099.155.232.171.326.016.094.036.308.02.475z"/>
            </svg>
          </div>
          <h2 class="text-xl font-semibold">Bot Telegram</h2>
        </div>

        <div class="space-y-2 mb-4">
          <label class="text-sm text-gray-400 ml-1">Token du Bot Telegram</label>
          <input type="text" name="telegram_bot_token" id="telegram_bot_token" value="<?php echo htmlspecialchars($config['telegram_bot_token']); ?>" placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz" required
            class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition-all text-white font-mono text-sm">
        </div>

        <div class="bg-cyan-500/10 border border-cyan-500/30 rounded-lg p-3 text-xs text-cyan-300">
          <p class="font-semibold mb-1">💡 Comment créer un bot Telegram :</p>
          <ol class="list-decimal ml-4 space-y-1">
            <li>Recherchez <strong>@BotFather</strong> dans Telegram</li>
            <li>Envoyez la commande <code class="bg-black/30 px-1 rounded">/newbot</code></li>
            <li>Copiez le <strong>token</strong> fourni et collez-le ci-dessus</li>
          </ol>
        </div>
      </div>

      <div class="bg-gray-800/50 border border-gray-700 rounded-2xl p-6 shadow-xl">
        <div class="flex items-center gap-3 mb-6">
          <div class="p-2 bg-purple-500/20 rounded-lg">
            <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
          </div>
          <h2 class="text-xl font-semibold">Docker</h2>
        </div>

        <div class="space-y-2">
          <label class="text-sm text-gray-400 ml-1">Chemin vers docker-compose.yml</label>
          <input type="text" name="docker_path" value="<?php echo htmlspecialchars($config['docker_path']); ?>" placeholder="/opt/teslamate/docker-compose.yml" required
            class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 outline-none transition-all text-white font-mono text-sm">
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4">
        <button type="button" id="btnTestMqtt"
          class="bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded-2xl transition-all flex items-center justify-center gap-2">
          <span>TEST MQTT</span>
          <div id="loader" class="hidden w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
        </button>

        <button type="button" id="btnTestEmail"
          class="bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded-2xl transition-all flex items-center justify-center gap-2">
          <span>TEST EMAIL</span>
          <div id="loaderEmail" class="hidden w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
        </button>

        <button type="button" id="btnTestTelegram"
          class="bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded-2xl transition-all flex items-center justify-center gap-2">
          <span>TEST TELEGRAM</span>
          <div id="loaderTelegram" class="hidden w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
        </button>

        <div class="md:col-span-3">
          <button type="submit"   
            class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-4 rounded-2xl shadow-lg shadow-red-900/20 transition-all transform active:scale-[0.98]">
            SAUVEGARDER LA CONFIGURATION GÉNÉRALE
          </button>
        </div>
      </div>

      <div id="testResult" class="hidden p-4 rounded-xl text-center font-medium text-sm"></div>
    </form>

    <div class="bg-gray-800/50 border border-gray-700 rounded-2xl p-6 shadow-xl mt-6">
      <div class="flex items-center gap-3 mb-6">
        <div class="p-2 bg-cyan-500/20 rounded-lg">
          <svg class="w-6 h-6 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
          </svg>
        </div>
        <h2 class="text-xl font-semibold">Destinataires Telegram</h2>
      </div>

      <?php if (!empty($telegram_users)): ?>
      <div class="space-y-3 mb-6">
        <?php foreach ($telegram_users as $index => $user): ?>
        <div class="bg-gray-900 border border-gray-700 rounded-xl p-4 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-cyan-500/20 rounded-full flex items-center justify-center">
              <span class="text-cyan-400 font-bold"><?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?></span>
            </div>
            <div>
              <div class="font-semibold"><?php echo htmlspecialchars($user['name']); ?></div>
              <div class="text-sm text-gray-500 font-mono"><?php echo htmlspecialchars($user['chat_id']); ?></div>
            </div>
          </div>
          <form method="POST" class="inline">
            <input type="hidden" name="delete_user" value="<?php echo $index; ?>">
            <button type="submit" onclick="return confirm('Supprimer cet utilisateur ?')"
              class="p-2 bg-red-500/20 hover:bg-red-500/30 text-red-400 rounded-lg transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
              </svg>
            </button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="bg-gray-900/50 border border-gray-700 rounded-xl p-6 text-center text-gray-500 mb-6">
        Aucun destinataire configuré
      </div>
      <?php endif; ?>

      <form method="POST" class="bg-gray-900 border border-gray-700 rounded-xl p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div class="space-y-2">
            <label class="text-sm text-gray-400 ml-1">Utilisateur Telegram</label>
            <input type="text" name="telegram_user_name" placeholder="Ex: @mon_nom_telegram" required
              class="w-full bg-gray-800 border border-gray-600 rounded-xl px-4 py-3 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition-all text-white">
          </div>
          <div class="space-y-2">
            <label class="text-sm text-gray-400 ml-1">Chat ID</label>
            <input type="text" name="telegram_chat_id" placeholder="Ex: 123456789" required
              class="w-full bg-gray-800 border border-gray-600 rounded-xl px-4 py-3 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition-all text-white font-mono">
          </div>
        </div>
          
        <button type="submit" name="add_telegram_user"
          class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 rounded-xl transition-all transform active:scale-[0.98] flex items-center justify-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
          </svg>
          Ajouter un destinataire
        </button>

        <div class="bg-cyan-500/10 border border-cyan-500/30 rounded-lg p-3 text-xs text-cyan-300 mt-4">
          <p class="font-semibold mb-1">💡 Comment obtenir votre Chat ID :</p>
          <ol class="list-decimal ml-4 space-y-1">
            <li>Recherchez <strong>@userinfobot</strong> dans Telegram</li>
            <li>Démarrez une conversation avec lui pour obtenir votre ID</li>
          </ol>
        </div>
      </form>
    </div>

  </div>

  <script>
    function showResult(type, message) {
        const resultZone = document.getElementById('testResult');
        resultZone.classList.remove('hidden');
        if(type === 'success') {
            resultZone.className = "p-4 rounded-xl text-center font-medium text-sm bg-green-500/20 text-green-400 border border-green-500/50";
        } else if(type === 'error') {
            resultZone.className = "p-4 rounded-xl text-center font-medium text-sm bg-red-500/20 text-red-400 border border-red-500/50";
        } else {
            resultZone.className = "p-4 rounded-xl text-center font-medium text-sm bg-orange-500/20 text-orange-400 border border-orange-500/50";
        }
        resultZone.innerText = message;
    }

    // Gestion des clics boutons tests (MQTT, Email, Telegram)
    async function runTest(endpoint, bodyData, btnId, loaderId) {
        const btn = document.getElementById(btnId);
        const loader = document.getElementById(loaderId);
        btn.disabled = true;
        loader.classList.remove('hidden');

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: bodyData
            });
            const data = await response.json();
            if(data.success) {
                showResult('success', "✅ Succès !");
            } else {
                showResult('error', "❌ Erreur : " + data.message);
            }
        } catch (e) {
            showResult('warning', "⚠ Erreur réseau ou serveur.");
        } finally {
            btn.disabled = false;
            loader.classList.add('hidden');
        }
    }

    document.getElementById('btnTestMqtt').addEventListener('click', function() {
        const host = document.getElementById('mqtt_host').value;
        const port = document.getElementById('mqtt_port').value;
        const user = document.getElementById('mqtt_user').value;
        const pass = document.getElementById('mqtt_pass').value;
        const topic = document.getElementById('mqtt_topic').value;
        runTest('test_mqtt.php', `host=${encodeURIComponent(host)}&port=${encodeURIComponent(port)}&user=${encodeURIComponent(user)}&pass=${encodeURIComponent(pass)}&topic=${encodeURIComponent(topic)}`, 'btnTestMqtt', 'loader');
    });

    document.getElementById('btnTestEmail').addEventListener('click', function() {
        const email = document.getElementById('notification_email').value;
        runTest('test_email.php', `email=${encodeURIComponent(email)}`, 'btnTestEmail', 'loaderEmail');
    });

    document.getElementById('btnTestTelegram').addEventListener('click', function() {
        const token = document.getElementById('telegram_bot_token').value;
        runTest('test_telegram.php', `token=${encodeURIComponent(token)}`, 'btnTestTelegram', 'loaderTelegram');
    });
  </script>
</body>
</html>
