<?php
session_start();

// --- 1. LECTURE STRICTE DE LA LANGUE DANS LE SETUP ---
$dir = 'cgi-bin';
$config_file = $dir . '/setup';
$lang = 'fr'; // Langue par défaut

if (file_exists($config_file)) {
    $lines = file($config_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorer les commentaires
        if (strpos(trim($line), '#') === 0) continue;
        
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = strtoupper(trim($parts[0]));
            $val = strtolower(trim($parts[1]));
            if ($key === 'LANGUAGE') {
                $lang = ($val === 'en') ? 'en' : 'fr';
                break; 
            }
        }
    }
}

// Dictionnaire de traduction
$trans = [
    'fr' => [
        'title' => 'Configuration TeslaMate',
        'h1' => 'Configuration',
        'saved_title' => 'Configuration enregistrée !',
        'saved_msg' => 'Pensez à <a href="teslanotif.php" class="underline font-bold hover:text-white transition-colors">activer les notifications</a> suite à ces changements.',
        'mqtt_title' => 'Serveur MQTT',
        'mqtt_host' => 'Adresse Serveur',
        'mqtt_port' => 'Port',
        'mqtt_user' => 'Login',
        'mqtt_pass' => 'Mot de passe',
        'mqtt_topic' => 'Topic de base',
        'email_title' => 'Notifications Email',
        'email_dest' => 'Email de destination',
        'tg_title' => 'Bot Telegram',
        'tg_token' => 'Token du Bot Telegram',
        'tg_help_title' => '💡 Comment créer un bot Telegram :',
        'tg_help_1' => 'Recherchez <strong>@BotFather</strong> dans Telegram',
        'tg_help_2' => 'Envoyez la commande <code class="bg-black/30 px-1 rounded">/newbot</code>',
        'tg_help_3' => 'Copiez le <strong>token</strong> fourni et collez-le ci-dessus',
        'docker_title' => 'Docker',
        'docker_path' => 'Chemin vers docker-compose.yml',
        'btn_test_mqtt' => 'TEST MQTT',
        'btn_test_email' => 'TEST EMAIL',
        'btn_test_tg' => 'TEST TELEGRAM',
        'btn_save' => 'SAUVEGARDER LA CONFIGURATION GÉNÉRALE',
        'dest_title' => 'Destinataires Telegram',
        'dest_none' => 'Aucun destinataire configuré',
        'dest_user' => 'Utilisateur Telegram',
        'dest_chatid' => 'Chat ID',
        'dest_add' => 'Ajouter un destinataire',
        'dest_help_title' => '💡 Comment obtenir votre Chat ID :',
        'dest_help_1' => 'Recherchez <strong>@userinfobot</strong> dans Telegram',
        'dest_help_2' => 'Démarrez une conversation avec lui pour obtenir votre ID',
        'js_success' => '✅ Succès !',
        'js_error' => '❌ Erreur : ',
        'js_network' => '⚠ Erreur réseau ou serveur.',
        'confirm_del' => 'Supprimer cet utilisateur ?'
    ],
    'en' => [
        'title' => 'TeslaMate Configuration',
        'h1' => 'Configuration',
        'saved_title' => 'Configuration saved!',
        'saved_msg' => 'Remember to <a href="teslanotif.php" class="underline font-bold hover:text-white transition-colors">enable notifications</a> following these changes.',
        'mqtt_title' => 'MQTT Server',
        'mqtt_host' => 'Server Address',
        'mqtt_port' => 'Port',
        'mqtt_user' => 'Login',
        'mqtt_pass' => 'Password',
        'mqtt_topic' => 'Base Topic',
        'email_title' => 'Email Notifications',
        'email_dest' => 'Destination Email',
        'tg_title' => 'Telegram Bot',
        'tg_token' => 'Telegram Bot Token',
        'tg_help_title' => '💡 How to create a Telegram bot:',
        'tg_help_1' => 'Search for <strong>@BotFather</strong> in Telegram',
        'tg_help_2' => 'Send the command <code class="bg-black/30 px-1 rounded">/newbot</code>',
        'tg_help_3' => 'Copy the provided <strong>token</strong> and paste it above',
        'docker_title' => 'Docker',
        'docker_path' => 'Path to docker-compose.yml',
        'btn_test_mqtt' => 'TEST MQTT',
        'btn_test_email' => 'TEST EMAIL',
        'btn_test_tg' => 'TEST TELEGRAM',
        'btn_save' => 'SAVE GENERAL CONFIGURATION',
        'dest_title' => 'Telegram Recipients',
        'dest_none' => 'No recipients configured',
        'dest_user' => 'Telegram User',
        'dest_chatid' => 'Chat ID',
        'dest_add' => 'Add recipient',
        'dest_help_title' => '💡 How to get your Chat ID:',
        'dest_help_1' => 'Search for <strong>@userinfobot</strong> in Telegram',
        'dest_help_2' => 'Start a conversation with it to get your ID',
        'js_success' => '✅ Success!',
        'js_error' => '❌ Error: ',
        'js_network' => '⚠ Network or server error.',
        'confirm_del' => 'Delete this user?'
    ]
];
$t = $trans[$lang];

// --- 2. LECTURE DES AUTRES PARAMÈTRES ---
$users_file = $dir . '/telegram_users.json';
if (!is_dir($dir)) mkdir($dir, 0755, true);

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

if (file_exists($config_file)) {
    $lines = file($config_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = strtolower(trim($parts[0]));
            if (array_key_exists($key, $config)) {
                $config[$key] = trim($parts[1]);
            }
        }
    }
}

$telegram_users = file_exists($users_file) ? (json_decode(file_get_contents($users_file), true) ?? []) : [];

// --- 3. ACTIONS POST ---
if (isset($_POST['delete_user'])) {
    $index = intval($_POST['delete_user']);
    if (isset($telegram_users[$index])) {
        array_splice($telegram_users, $index, 1);
        file_put_contents($users_file, json_encode($telegram_users, JSON_PRETTY_PRINT));
        header('Location: ' . $_SERVER['PHP_SELF'] . '?saved=1');
        exit;
    }
}

if (isset($_POST['add_telegram_user'])) {
    $name = trim($_POST['telegram_user_name']);
    $chat_id = trim($_POST['telegram_chat_id']);
    if ($name && $chat_id) {
        $telegram_users[] = ['name' => $name, 'chat_id' => $chat_id, 'active' => true];
        file_put_contents($users_file, json_encode($telegram_users, JSON_PRETTY_PRINT));
        header('Location: ' . $_SERVER['PHP_SELF'] . '?saved=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $t['title'] ?></title>
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
      <h1 class="text-2xl font-bold"><?= $t['h1'] ?></h1>
    </div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="mb-6 p-4 bg-orange-500/20 border border-orange-500/50 rounded-2xl flex items-center gap-4 animate-pulse">
        <div class="p-2 bg-orange-500 rounded-full text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </div>
        <div>
            <p class="text-orange-300 font-bold"><?= $t['saved_title'] ?></p>
            <p class="text-orange-200/80 text-sm"><?= $t['saved_msg'] ?></p>
        </div>
    </div>
    <?php endif; ?>

    <form id="configForm" action="teslaconfig_handler.php" method="POST" class="space-y-6">
      <input type="hidden" name="lang" value="<?= $lang ?>">

      <div class="bg-gray-800/50 border border-gray-700 rounded-2xl p-6 shadow-xl">
        <div class="flex items-center gap-3 mb-6">
          <div class="p-2 bg-red-500/20 rounded-lg"><svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg></div>
          <h2 class="text-xl font-semibold"><?= $t['mqtt_title'] ?></h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-2">
            <label class="text-sm text-gray-400 ml-1"><?= $t['mqtt_host'] ?></label>
            <input type="text" name="mqtt_host" id="mqtt_host" value="<?= htmlspecialchars($config['mqtt_host']); ?>" required class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 focus:border-red-500 focus:ring-1 focus:ring-red-500 outline-none transition-all text-white">
          </div>
          <div class="space-y-2">
            <label class="text-sm text-gray-400 ml-1"><?= $t['mqtt_port'] ?></label>
            <input type="number" name="mqtt_port" id="mqtt_port" value="<?= htmlspecialchars($config['mqtt_port']); ?>" required class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 focus:border-red-500 focus:ring-1 focus:ring-red-500 outline-none transition-all text-white">
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
          <div class="space-y-2">
            <label class="text-sm text-gray-400 ml-1"><?= $t['mqtt_user'] ?></label>
            <input type="text" name="mqtt_user" id="mqtt_user" value="<?= htmlspecialchars($config['mqtt_user']); ?>" class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 focus:border-red-500 focus:ring-1 focus:ring-red-500 outline-none transition-all text-white">
          </div>
          <div class="space-y-2">
            <label class="text-sm text-gray-400 ml-1"><?= $t['mqtt_pass'] ?></label>
            <input type="password" name="mqtt_pass" id="mqtt_pass" value="<?= htmlspecialchars($config['mqtt_pass']); ?>" class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 focus:border-red-500 focus:ring-1 focus:ring-red-500 outline-none transition-all text-white">
          </div>
        </div>
        <div class="mt-4 space-y-2">
          <label class="text-sm text-gray-400 ml-1"><?= $t['mqtt_topic'] ?></label>
          <input type="text" name="mqtt_topic" id="mqtt_topic" value="<?= htmlspecialchars($config['mqtt_topic']); ?>" required class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 focus:border-red-500 focus:ring-1 focus:ring-red-500 outline-none transition-all text-white">
        </div>
      </div>

      <div class="bg-gray-800/50 border border-gray-700 rounded-2xl p-6 shadow-xl">
        <div class="flex items-center gap-3 mb-6">
          <div class="p-2 bg-blue-500/20 rounded-lg"><svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg></div>
          <h2 class="text-xl font-semibold"><?= $t['email_title'] ?></h2>
        </div>
        <div class="space-y-2">
          <label class="text-sm text-gray-400 ml-1"><?= $t['email_dest'] ?></label>
          <input type="email" id="notification_email" name="notification_email" value="<?= htmlspecialchars($config['notification_email']); ?>" required class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all text-white">
        </div>
      </div>

      <div class="bg-gray-800/50 border border-gray-700 rounded-2xl p-6 shadow-xl">
        <div class="flex items-center gap-3 mb-6">
          <div class="p-2 bg-cyan-500/20 rounded-lg"><svg class="w-6 h-6 text-cyan-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.903-1.056-.692-1.653-1.123-2.678-1.799-1.185-.781-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.139-5.062 3.345-.479.329-.913.489-1.302.481-.428-.009-1.252-.242-1.865-.442-.752-.244-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635.099-.002.321.023.465.14.121.099.155.232.171.326.016.094.036.308.02.475z"/></svg></div>
          <h2 class="text-xl font-semibold"><?= $t['tg_title'] ?></h2>
        </div>
        <div class="space-y-2 mb-4">
          <label class="text-sm text-gray-400 ml-1"><?= $t['tg_token'] ?></label>
          <input type="text" name="telegram_bot_token" id="telegram_bot_token" value="<?= htmlspecialchars($config['telegram_bot_token']); ?>" required class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition-all text-white font-mono text-sm">
        </div>
        <div class="bg-cyan-500/10 border border-cyan-500/30 rounded-lg p-3 text-xs text-cyan-300">
          <p class="font-semibold mb-1"><?= $t['tg_help_title'] ?></p>
          <ol class="list-decimal ml-4 space-y-1">
            <li><?= $t['tg_help_1'] ?></li>
            <li><?= $t['tg_help_2'] ?></li>
            <li><?= $t['tg_help_3'] ?></li>
          </ol>
        </div>
      </div>

      <div class="bg-gray-800/50 border border-gray-700 rounded-2xl p-6 shadow-xl">
        <div class="flex items-center gap-3 mb-6">
          <div class="p-2 bg-purple-500/20 rounded-lg"><svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg></div>
          <h2 class="text-xl font-semibold"><?= $t['docker_title'] ?></h2>
        </div>
        <div class="space-y-2">
          <label class="text-sm text-gray-400 ml-1"><?= $t['docker_path'] ?></label>
          <input type="text" name="docker_path" value="<?= htmlspecialchars($config['docker_path']); ?>" required class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 outline-none transition-all text-white font-mono text-sm">
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4">
        <button type="button" id="btnTestMqtt" class="bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded-2xl transition-all flex items-center justify-center gap-2">
          <span><?= $t['btn_test_mqtt'] ?></span>
          <div id="loader" class="hidden w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
        </button>
        <button type="button" id="btnTestEmail" class="bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded-2xl transition-all flex items-center justify-center gap-2">
          <span><?= $t['btn_test_email'] ?></span>
          <div id="loaderEmail" class="hidden w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
        </button>
        <button type="button" id="btnTestTelegram" class="bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded-2xl transition-all flex items-center justify-center gap-2">
          <span><?= $t['btn_test_tg'] ?></span>
          <div id="loaderTelegram" class="hidden w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
        </button>

        <div class="md:col-span-3">
          <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-4 rounded-2xl shadow-lg transition-all transform active:scale-[0.98]">
            <?= $t['btn_save'] ?>
          </button>
        </div>
      </div>
      <div id="testResult" class="hidden p-4 rounded-xl text-center font-medium text-sm"></div>
    </form>

    <div class="bg-gray-800/50 border border-gray-700 rounded-2xl p-6 shadow-xl mt-6">
      <div class="flex items-center gap-3 mb-6">
        <div class="p-2 bg-cyan-500/20 rounded-lg"><svg class="w-6 h-6 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg></div>
        <h2 class="text-xl font-semibold"><?= $t['dest_title'] ?></h2>
      </div>

      <?php if (!empty($telegram_users)): ?>
      <div class="space-y-3 mb-6">
        <?php foreach ($telegram_users as $index => $user): ?>
        <div class="bg-gray-900 border border-gray-700 rounded-xl p-4 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-cyan-500/20 rounded-full flex items-center justify-center text-cyan-400 font-bold"><?= strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?></div>
            <div>
              <div class="font-semibold"><?= htmlspecialchars($user['name']); ?></div>
              <div class="text-sm text-gray-500 font-mono"><?= htmlspecialchars($user['chat_id']); ?></div>
            </div>
          </div>
          <form method="POST" class="inline">
            <input type="hidden" name="delete_user" value="<?= $index; ?>">
            <button type="submit" onclick="return confirm('<?= $t['confirm_del'] ?>')" class="p-2 bg-red-500/20 hover:bg-red-500/30 text-red-400 rounded-lg transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            </button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="bg-gray-900/50 border border-gray-700 rounded-xl p-6 text-center text-gray-500 mb-6"><?= $t['dest_none'] ?></div>
      <?php endif; ?>

      <form method="POST" class="bg-gray-900 border border-gray-700 rounded-xl p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div class="space-y-2">
            <label class="text-sm text-gray-400 ml-1"><?= $t['dest_user'] ?></label>
            <input type="text" name="telegram_user_name" required class="w-full bg-gray-800 border border-gray-600 rounded-xl px-4 py-3 text-white">
          </div>
          <div class="space-y-2">
            <label class="text-sm text-gray-400 ml-1"><?= $t['dest_chatid'] ?></label>
            <input type="text" name="telegram_chat_id" required class="w-full bg-gray-800 border border-gray-600 rounded-xl px-4 py-3 text-white font-mono">
          </div>
        </div>
        <button type="submit" name="add_telegram_user" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 rounded-xl transition-all flex items-center justify-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
          <?= $t['dest_add'] ?>
        </button>
      </form>
    </div>
  </div>

  <script>
    const LANG_JS = { success: "<?= $t['js_success'] ?>", error: "<?= $t['js_error'] ?>", network: "<?= $t['js_network'] ?>" };

    function showResult(type, message) {
        const r = document.getElementById('testResult');
        r.classList.remove('hidden');
        r.className = `p-4 rounded-xl text-center font-medium text-sm border ${type==='success'?'bg-green-500/20 text-green-400 border-green-500/50':'bg-red-500/20 text-red-400 border-red-500/50'}`;
        r.innerText = message;
    }

    async function runTest(endpoint, bodyData, btnId, loaderId) {
        const btn = document.getElementById(btnId);
        const loader = document.getElementById(loaderId);
        btn.disabled = true; loader.classList.remove('hidden');
        try {
            const response = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: bodyData });
            const data = await response.json();
            data.success ? showResult('success', LANG_JS.success) : showResult('error', LANG_JS.error + data.message);
        } catch (e) { showResult('error', LANG_JS.network); }
        btn.disabled = false; loader.classList.add('hidden');
    }

    document.getElementById('btnTestMqtt').onclick = () => {
        const h = document.getElementById('mqtt_host').value, p = document.getElementById('mqtt_port').value, u = document.getElementById('mqtt_user').value, pw = document.getElementById('mqtt_pass').value, t = document.getElementById('mqtt_topic').value;
        runTest('test_mqtt.php', `host=${encodeURIComponent(h)}&port=${encodeURIComponent(p)}&user=${encodeURIComponent(u)}&pass=${encodeURIComponent(pw)}&topic=${encodeURIComponent(t)}`, 'btnTestMqtt', 'loader');
    };
    document.getElementById('btnTestEmail').onclick = () => runTest('test_email.php', `email=${encodeURIComponent(document.getElementById('notification_email').value)}`, 'btnTestEmail', 'loaderEmail');
    document.getElementById('btnTestTelegram').onclick = () => runTest('test_telegram.php', `token=${encodeURIComponent(document.getElementById('telegram_bot_token').value)}`, 'btnTestTelegram', 'loaderTelegram');
  </script>
</body>
</html>
