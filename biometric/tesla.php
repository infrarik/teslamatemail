<?php
// 1. Initialisation de la session
session_start();

// 2. VERROU DE SÉCURITÉ : Retour à index.php si non autorisé
if (!isset($_SESSION['authorized']) || $_SESSION['authorized'] !== true) {
    header('Location: index.php');
    exit;
}

// --- 3. CONFIGURATION ---
$file = "/var/www/html/cgi-bin/setup";

/**
 * Lit le fichier de configuration et retourne un tableau associatif
 */
function read_setup($file) {
    $cfg = [];
    if (file_exists($file)) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $cfg[strtoupper(trim($key))] = trim($value);
            }
        }
    }
    return $cfg;
}

/**
 * Sauvegarde ou met à jour une clé spécifique dans le fichier
 */
function save_setup_key($file, $keyToSave, $valueToSave) {
    $lines = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $found = false;
    $keyToSave = strtolower($keyToSave); 
    
    foreach ($lines as $idx => $line) {
        if (preg_match("/^" . preg_quote($keyToSave) . "\s*=/i", trim($line))) {
            $lines[$idx] = "$keyToSave=$valueToSave";
            $found = true;
            break;
        }
    }
    if (!$found) $lines[] = "$keyToSave=$valueToSave";
    file_put_contents($file, implode("\n", $lines) . "\n", LOCK_EX);
}

// Chargement de la config actuelle
$config = read_setup($file);

// Traitement de la requête AJAX pour la langue (pointe vers testlindex.php)
if (isset($_GET['save_lang'])) {
    $lang = strtoupper(trim($_GET['save_lang']));
    if ($lang === 'FR' || $lang === 'EN') {
        save_setup_key($file, 'language', $lang);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'saved' => $lang]);
        exit;
    }
}

// Langue par défaut
if (!isset($config['LANGUAGE'])) {
    save_setup_key($file, 'language', 'FR');
    $config['LANGUAGE'] = 'FR';
}
$default_lang = strtolower($config['LANGUAGE']);

// --- RÉCUPÉRATION DES INFOS DOCKER / SQL ---
$server_ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
$db_user = "teslamate"; $db_pass = "secret_password"; $db_name = "teslamate";

if (!empty($config['DOCKER_PATH']) && file_exists($config['DOCKER_PATH'])) {
    $docker_content = file_get_contents($config['DOCKER_PATH']);
    if (preg_match('/POSTGRES_USER[:=]\s*(\S+)/', $docker_content, $m)) $db_user = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_PASSWORD[:=]\s*(\S+)/', $docker_content, $m)) $db_pass = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_DB[:=]\s*(\S+)/', $docker_content, $m)) $db_name = str_replace(['"', "'"], '', trim($m[1]));
}

$last_charge_added = 0; $last_charge_used = 0;
try {
    $pdo = new PDO("pgsql:host=$server_ip;port=5432;dbname=$db_name", $db_user, $db_pass);
    $sql = "SELECT charge_energy_added, charge_energy_used FROM charging_processes WHERE end_date IS NOT NULL ORDER BY end_date DESC LIMIT 1";
    $stmt = $pdo->query($sql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $last_charge_added = round((float)$row['charge_energy_added'], 2);
        $last_charge_used = round((float)$row['charge_energy_used'], 2);
    }
} catch (Exception $e) { }
?>
<!DOCTYPE html>
<html lang="<?= $default_lang ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TeslaMate Mail</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @keyframes pulse-soft { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
    .animate-pulse-soft { animation: pulse-soft 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
    .lang-btn { cursor: pointer; transition: transform 0.2s; border: 2px solid transparent; }
    .lang-btn:hover { transform: scale(1.1); }
    .lang-active { border-color: white; border-radius: 4px; }
  </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-black text-white min-h-screen">
  <div id="app"></div>

  <script>
    const API_URL = 'teslamate_api.php';
    const REFRESH_INTERVAL = 30;   
    const PHP_LAST_CHARGE_ADDED = <?= $last_charge_added ?>;
    const PHP_LAST_CHARGE_USED = <?= $last_charge_used ?>;
    const SERVER_IP = '<?= $server_ip ?>';
     
    const i18n = {
      fr: {
        loading: "Chargement...", refresh: "Rafraîchir", tooltip_trips: "Trajets",
        tooltip_notif: "Notifications", tooltip_export: "Export / Calcul",
        tooltip_conf: "Configuration", state: "État", odometer: "Kilométrage",
        battery: "Batterie", est_range: "Autonomie estimée", interior: "Intérieur",
        exterior: "Extérieur", tires: "Pneus", fl: "Avant Gauche", fr: "Avant Droit",
        rl: "Arrière Gauche", rr: "Arrière Droit", last_charge: "Dernière charge",
        added: "kWh ajoutés", used: "kWh consommés", update_msg: "Mise à jour automatique toutes les 30s",
        credits: "Credits", referral: "Parrainage", referral2: "Lien de parrainage", charging: "EN CHARGE"
      },
      en: {
        loading: "Loading...", refresh: "Refresh", tooltip_trips: "Trips",
        tooltip_notif: "Notifications", tooltip_export: "Export / Calculator",
        tooltip_conf: "Settings", state: "Status", odometer: "Odometer",
        battery: "Battery", est_range: "Estimated Range", interior: "Interior",
        exterior: "Exterior", tires: "Tires", fl: "Front Left", fr: "Front Right",
        rl: "Rear Left", rr: "Rear Right", last_charge: "Last Charge",
        added: "kWh added", used: "kWh used", update_msg: "Auto-refresh every 30s",
        credits: "Credits", referral: "Referral", referral2: "Referral link", charging: "CHARGING"
      }
    };

    let currentLang = '<?= $default_lang ?>';
    let carData = null;
    let loading = false;

    async function setLanguage(lang) {
      if (currentLang === lang) return;
      currentLang = lang;
      render();
      try {
        await fetch(`testlindex.php?save_lang=${lang.toUpperCase()}&t=${Date.now()}`);
      } catch (e) { console.error(e); }
    }

    async function fetchData() {
      loading = true; render();
      try {
        const response = await fetch(API_URL);
        const data = await response.json();
        carData = data;
      } catch (err) { console.error(err); }  
      finally { loading = false; render(); }
    }

    function render() {
      const app = document.getElementById('app');
      if (!app) return;
      const t = i18n[currentLang];

      if (loading && !carData) {   
        app.innerHTML = `<div class="min-h-screen flex items-center justify-center text-gray-400">${t.loading}</div>`;   
        return;   
      }
      if (!carData) return;

      const isCharging = (carData.state === 'charging' || carData.is_charging === true || parseFloat(carData.charger_power) > 0);
      const displayState = isCharging ? t.charging : carData.state;

      app.innerHTML = `
        <div class="min-h-screen pb-12">
          <div class="bg-gray-800/90 backdrop-blur-sm border-b border-gray-700 p-4 sticky top-0 z-10">
            <div class="max-w-2xl mx-auto">
              <div class="flex justify-end gap-3 mb-2">
                <img src="https://flagcdn.com/w40/fr.png" class="lang-btn w-6 h-4 ${currentLang === 'fr' ? 'lang-active' : 'opacity-40'}" onclick="setLanguage('fr')" alt="FR">
                <img src="https://flagcdn.com/w40/gb.png" class="lang-btn w-6 h-4 ${currentLang === 'en' ? 'lang-active' : 'opacity-40'}" onclick="setLanguage('en')" alt="EN">
              </div>
              <div class="flex flex-col items-center space-y-4 text-center">
                <div>
                  <h1 class="text-7xl font-extrabold tracking-tighter text-transparent bg-clip-text bg-gradient-to-r from-white via-gray-100 to-gray-700">${carData.name || 'Tesla'}</h1>
                  <h2 class="text-xl font-bold text-red-600">TeslaMate Mail</h2>
                </div>
                <div class="w-full flex items-center justify-between">
                  <div class="w-10"></div>
                  <div class="flex-1 flex justify-center items-center gap-4">
                    <a href="teslamap.php" title="${t.tooltip_trips}" class="p-2 bg-gray-700 rounded-lg hover:bg-gray-600"><svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M9 20l-5.447-2.724A2 2 0 013 15.488V5.13a2 2 0 011.106-1.789L9 1m0 19v-19m0 19l6-3m-6-16l6 3m0 0l5.447-2.724A2 2 0 0121 4.512v10.358a2 2 0 01-1.106 1.789L15 20m0-19v19"></path></svg></a>
                    <a href="teslanotif.php" title="${t.tooltip_notif}" class="p-2 bg-gray-700 rounded-lg hover:bg-gray-600"><svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg></a>
                    <a href="teslacalcul.php" title="${t.tooltip_export}" class="p-2 bg-gray-700 rounded-lg hover:bg-gray-600"><svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg></a>
                    <a href="teslaconf.php" title="${t.tooltip_conf}" class="p-2 bg-gray-700 rounded-lg hover:bg-gray-600"><svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg></a>
                  </div>
                  <button onclick="fetchData()" title="${t.refresh}" class="p-2 bg-gray-700 rounded-full hover:bg-gray-600 transition-colors">
                    <svg class="w-5 h-5 ${loading ? 'animate-spin text-red-500' : 'text-gray-300'}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                  </button>
                </div>
                <div class="w-full mt-4 flex justify-center gap-8">
                    <a href="http://${SERVER_IP}:4000" target="_blank" class="text-xl font-bold text-gray-500 hover:text-white transition-colors">TESLAMATE</a>
                    <a href="http://${SERVER_IP}:3000" target="_blank" class="text-xl font-bold text-gray-500 hover:text-white transition-colors">GRAFANA</a>
                </div>
              </div>
            </div>
          </div>

          <div class="px-4 pt-6 space-y-4 max-w-2xl mx-auto">
            <div class="grid grid-cols-2 gap-4">
              <div class="bg-gray-800 rounded-2xl p-4 border border-gray-700 shadow-xl">
                <p class="text-xs text-gray-500 uppercase font-bold mb-1">${t.state}</p>
                <div class="flex items-center gap-2">
                  <div class="w-2 h-2 rounded-full ${isCharging ? 'bg-green-400 animate-pulse' : (carData.state === 'online' ? 'bg-green-500' : 'bg-gray-500')}"></div>
                  <span class="text-lg font-bold uppercase ${isCharging ? 'text-green-400' : ''}">${displayState}</span>
                </div>
              </div>
              <div class="bg-gray-800 rounded-2xl p-4 border border-gray-700 shadow-xl text-right">
                <p class="text-xs text-gray-500 uppercase font-bold mb-1">${t.odometer}</p>
                <span class="text-lg font-bold">${Math.round(carData.odometer).toLocaleString()} km</span>
              </div>
            </div>

            <div class="bg-gradient-to-br ${isCharging ? 'from-green-900/40 to-gray-900 border-green-700/50' : 'from-green-900/20 to-gray-900 border-green-700/30'} rounded-3xl p-6 border shadow-2xl">
              <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold">${t.battery}</h2>
                ${isCharging && carData.charger_power_kw ? `<span class="text-green-400 font-bold animate-pulse-soft">${carData.charger_power_kw} kW</span>` : ''}
              </div>
              <div class="flex justify-between items-end mb-4">
                <span class="text-6xl font-black text-green-400 tracking-tighter">${carData.battery_level}%</span>
                <div class="text-right">
                  <p class="text-gray-400 text-sm">${t.est_range}</p>
                  <p class="text-2xl font-bold">${Math.round(carData.est_battery_range_km)} km</p>
                </div>
              </div>
              <div class="w-full bg-gray-800 rounded-full h-4 p-0.5">
                <div class="${isCharging ? 'bg-green-400 shadow-[0_0_10px_rgba(74,222,128,0.5)]' : 'bg-green-500'} h-full rounded-full transition-all duration-1000" style="width: ${carData.battery_level}%"></div>
              </div>
            </div>

            <div class="bg-gray-800/50 rounded-2xl p-6 border border-gray-700 grid grid-cols-2 gap-8">
                <div class="text-center">
                  <p class="text-xs font-bold text-gray-500 uppercase mb-2">${t.interior}</p>
                  <p class="text-4xl font-bold text-orange-400">${parseFloat(carData.inside_temp || 0).toFixed(1)}°</p>
                </div>
                <div class="text-center">
                  <p class="text-xs font-bold text-gray-500 uppercase mb-2">${t.exterior}</p>
                  <p class="text-4xl font-bold text-blue-400">${parseFloat(carData.outside_temp || 0).toFixed(1)}°</p>
                </div>
            </div>

            <div class="bg-gray-800 rounded-3xl p-6 border border-gray-700">
              <h2 class="text-xl font-bold mb-6">${t.tires} (bar)</h2>
              <div class="grid grid-cols-2 gap-6 text-center">
                <div><p class="text-xs text-gray-500 uppercase">${t.fl}</p><p class="text-2xl font-bold">${parseFloat(carData.tpms_pressure_fl || 0).toFixed(1)}</p></div>
                <div><p class="text-xs text-gray-500 uppercase">${t.fr}</p><p class="text-2xl font-bold">${parseFloat(carData.tpms_pressure_fr || 0).toFixed(1)}</p></div>
                <div><p class="text-xs text-gray-500 uppercase">${t.rl}</p><p class="text-2xl font-bold">${parseFloat(carData.tpms_pressure_rl || 0).toFixed(1)}</p></div>
                <div><p class="text-xs text-gray-500 uppercase">${t.rr}</p><p class="text-2xl font-bold">${parseFloat(carData.tpms_pressure_rr || 0).toFixed(1)}</p></div>
              </div>
            </div>

            <div class="bg-blue-600/20 rounded-2xl p-5 border border-blue-500/30 text-center">
                <p class="text-sm font-bold text-blue-400 uppercase mb-1">${t.last_charge}</p>
                <div class="flex justify-center items-baseline gap-2">
                  <p class="text-4xl font-black text-white">${PHP_LAST_CHARGE_ADDED} <span class="text-xl font-normal text-blue-300">${t.added}</span></p>
                  <span class="text-gray-500 text-xl">|</span>
                  <p class="text-4xl font-black text-white">${PHP_LAST_CHARGE_USED} <span class="text-xl font-normal text-blue-300">${t.used}</span></p>
                </div>
            </div>
             
            <p class="text-center text-[10px] text-gray-600 pt-4 uppercase tracking-widest">${t.update_msg}</p>
          </div>

          <footer class="mt-12 mb-8 max-w-2xl mx-auto px-4">
            <div class="bg-gray-800 rounded-3xl p-6 border border-gray-700">
              <div class="grid grid-cols-2 gap-6 text-center">
                <a href="credits.php">
                  <p class="text-xs text-gray-500 uppercase font-bold mb-1">${t.credits}</p>
                  <p class="text-xl font-bold text-white uppercase tracking-tight">Credits</p>
                </a>
                <a href="parrain.php">
                  <p class="text-xs text-gray-500 uppercase font-bold mb-1">${t.referral}</p>
                  <p class="text-xl font-bold text-white uppercase tracking-tight">${t.referral2}</p>
                </a>
              </div>
            </div>
          </footer>
        </div>
      `;
    }

    fetchData();
    setInterval(fetchData, REFRESH_INTERVAL * 1000);
  </script>
</body>
</html>
