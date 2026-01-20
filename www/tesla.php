<?php
// --- 1. CONFIGURATION ---
$file = 'cgi-bin/setup';
$config = [];
if (file_exists($file)) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) { 
            $config[strtoupper(trim($parts[0]))] = trim($parts[1]); 
        }
    }
}

// On récupère l'IP du serveur pour remplacer localhost
$server_ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';

$db_user = "teslamate"; $db_pass = "secret_password"; $db_name = "teslamate";
if (!empty($config['DOCKER_PATH']) && file_exists($config['DOCKER_PATH'])) {
    $docker_content = file_get_contents($config['DOCKER_PATH']);
    if (preg_match('/POSTGRES_USER[:=]\s*(\S+)/', $docker_content, $m)) $db_user = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_PASSWORD[:=]\s*(\S+)/', $docker_content, $m)) $db_pass = str_replace(['"', "'"], '', trim($m[1]));
    if (preg_match('/POSTGRES_DB[:=]\s*(\S+)/', $docker_content, $m)) $db_name = str_replace(['"', "'"], '', trim($m[1]));
}

// --- 2. RÉCUPÉRATION SQL DE LA DERNIÈRE CHARGE ---
$last_charge_kwh = 0;
try {
    // Remplacement de localhost par l'IP du serveur dans le DSN
    $pdo = new PDO("pgsql:host=$server_ip;port=5432;dbname=$db_name", $db_user, $db_pass);
    $sql = "SELECT charge_energy_added FROM charging_processes WHERE end_date IS NOT NULL ORDER BY end_date DESC LIMIT 1";
    $result = $pdo->query($sql)->fetchColumn();
    $last_charge_kwh = $result ? round((float)$result, 2) : 0;
} catch (Exception $e) {
    $last_charge_kwh = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#dc2626">
  <title>TeslaMate Mail</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @keyframes pulse-soft { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
    .animate-pulse-soft { animation: pulse-soft 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
  </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-black text-white min-h-screen">
  <div id="app"></div>

  <script>
    const API_URL = 'teslamate_api.php';
    const REFRESH_INTERVAL = 30; 
    const PHP_LAST_CHARGE = <?= $last_charge_kwh ?>;
    const SERVER_IP = '<?= $server_ip ?>';
    let carData = null;
    let loading = false;
    let error = null;

    async function fetchData() {
      loading = true; render();
      try {
        const response = await fetch(API_URL);
        if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);
        const data = await response.json();
        if (data.error) throw new Error(data.error);
        carData = data; 
        error = null;
      } catch (err) { 
        error = err.message; 
      } finally { 
        loading = false; 
        render(); 
      }
    }

    function render() {
      const app = document.getElementById('app');
      if (loading && !carData) { 
          app.innerHTML = `<div class="min-h-screen flex items-center justify-center text-gray-400">Chargement...</div>`; 
          return; 
      }
      if (error && !carData) { 
          app.innerHTML = `<div class="p-6 text-center text-red-500">${error}</div>`; 
          return; 
      }
      if (!carData) return;

      const carName = carData.name || 'Ma Tesla';

      app.innerHTML = `
        <div class="min-h-screen pb-20">
          <div class="bg-gray-800/90 backdrop-blur-sm border-b border-gray-700 p-4 sticky top-0 z-10">
            <div class="max-w-2xl mx-auto flex flex-col items-center space-y-4">
              <div class="text-center py-1">
                <h1 class="text-2xl font-bold text-white tracking-tight">${carName}</h1>
                <h2 class="text-xl font-bold text-red-600">TeslaMate Mail</h2>
              </div>
              <div class="w-full flex items-center justify-between">
                <div class="w-10"></div>
                <div class="flex-1 flex justify-center items-center gap-4">
                  <a href="teslamap.php" class="p-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"><svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A2 2 0 013 15.488V5.13a2 2 0 011.106-1.789L9 1m0 19v-19m0 19l6-3m-6-16l6 3m0 0l5.447-2.724A2 2 0 0121 4.512v10.358a2 2 0 01-1.106 1.789L15 20m0-19v19"></path></svg></a>
                  <a href="teslamail.php" class="p-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"><svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg></a>
                  <a href="teslacalcul.php" class="p-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"><svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg></a>
                  <a href="teslaconf.php" class="p-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"><svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg></a>
                </div>
                <button onclick="fetchData()" class="p-2 bg-gray-700 hover:bg-gray-600 rounded-full transition-colors shadow-lg">
                  <svg class="w-5 h-5 ${loading ? 'animate-spin text-red-500' : 'text-gray-300'}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </button>
              </div>

	        <div class="w-full mt-4">
                <div class="flex justify-center gap-8">
                  <a href="http://${SERVER_IP}:4000" target="_blank" class="flex flex-col items-center gap-2 text-xl font-bold text-gray-500 hover:text-white transition-colors uppercase tracking-wide">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <span>Teslamate</span>
                  </a>
                  <a href="http://${SERVER_IP}:3000" target="_blank" class="flex flex-col items-center gap-2 text-xl font-bold text-gray-500 hover:text-white transition-colors uppercase tracking-wide">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                    </svg>
                    <span>Grafana</span>
                  </a>
                </div>
              </div>

            </div>
          </div>

          <div class="px-4 pt-6 space-y-4 max-w-2xl mx-auto">
            <div class="grid grid-cols-2 gap-4">
              <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl p-4 border border-gray-700 shadow-xl">
                <p class="text-xs text-gray-500 uppercase font-bold mb-1">État</p>
                <div class="flex items-center gap-2">
                  <div class="w-2 h-2 rounded-full ${carData.state === 'online' ? 'bg-green-500' : 'bg-gray-500'}"></div>
                  <span class="text-lg font-bold">${carData.state}</span>
                </div>
              </div>
              <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl p-4 border border-gray-700 shadow-xl text-right">
                <p class="text-xs text-gray-500 uppercase font-bold mb-1">Kilométrage</p>
                <span class="text-lg font-bold">${Math.round(carData.odometer).toLocaleString()} <span class="text-xs text-gray-400">km</span></span>
              </div>
            </div>

            <div class="bg-gradient-to-br from-green-900/20 to-gray-900 rounded-3xl p-6 border border-green-700/30 shadow-2xl relative overflow-hidden">
              <div class="flex items-center gap-3 mb-6">
                <div class="p-3 bg-green-500/20 rounded-xl"><svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg></div>
                <h2 class="text-xl font-bold">Batterie</h2>
              </div>
              <div class="flex justify-between items-end mb-4">
                <span class="text-6xl font-black text-green-400 tracking-tighter">${carData.battery_level}%</span>
                <div class="text-right">
                  <p class="text-gray-400 text-sm">Autonomie estimée</p>
                  <p class="text-2xl font-bold">${Math.round(carData.est_battery_range_km)} <span class="text-sm font-normal">km</span></p>
                </div>
              </div>
              <div class="w-full bg-gray-800 rounded-full h-4 p-0.5 border border-gray-700">
                <div class="bg-gradient-to-r from-green-600 to-green-400 h-full rounded-full transition-all duration-1000" style="width: ${carData.battery_level}%"></div>
              </div>
            </div>

            <div class="bg-gray-800/50 rounded-2xl p-6 border border-gray-700 shadow-xl grid grid-cols-2 gap-8">
                <div class="text-center">
                  <p class="text-xs font-bold text-gray-500 uppercase mb-2">Intérieur</p>
                  <p class="text-4xl font-bold text-orange-400">${parseFloat(carData.inside_temp || 0).toFixed(1)}°</p>
                </div>
                <div class="text-center">
                  <p class="text-xs font-bold text-gray-500 uppercase mb-2">Extérieur</p>
                  <p class="text-4xl font-bold text-blue-400">${parseFloat(carData.outside_temp || 0).toFixed(1)}°</p>
                </div>
            </div>

            <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-3xl p-6 border border-gray-700 shadow-xl">
              <div class="flex items-center gap-3 mb-6">
                <div class="p-3 bg-blue-500/20 rounded-xl"><svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                <h2 class="text-xl font-bold">Pneus <span class="text-sm font-normal text-gray-500">(bar)</span></h2>
              </div>
              <div class="grid grid-cols-2 gap-6 relative">
                <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-px h-full bg-gray-700/50"></div>
                <div class="space-y-6">
                  <div class="text-center">
                    <p class="text-xs font-bold text-gray-500 uppercase mb-2">Avant Gauche</p>
                    <p class="text-3xl font-bold ${carData.tpms_pressure_fl < 2.6 ? 'text-red-500 animate-pulse-soft' : 'text-white'}">${parseFloat(carData.tpms_pressure_fl || 0).toFixed(1)}</p>
                  </div>
                  <div class="text-center">
                    <p class="text-xs font-bold text-gray-500 uppercase mb-2">Arrière Gauche</p>
                    <p class="text-3xl font-bold ${carData.tpms_pressure_rl < 2.6 ? 'text-red-500 animate-pulse-soft' : 'text-white'}">${parseFloat(carData.tpms_pressure_rl || 0).toFixed(1)}</p>
                  </div>
                </div>
                <div class="space-y-6">
                  <div class="text-center">
                    <p class="text-xs font-bold text-gray-500 uppercase mb-2">Avant Droit</p>
                    <p class="text-3xl font-bold ${carData.tpms_pressure_fr < 2.6 ? 'text-red-500 animate-pulse-soft' : 'text-white'}">${parseFloat(carData.tpms_pressure_fr || 0).toFixed(1)}</p>
                  </div>
                  <div class="text-center">
                    <p class="text-xs font-bold text-gray-500 uppercase mb-2">Arrière Droit</p>
                    <p class="text-3xl font-bold ${carData.tpms_pressure_rr < 2.6 ? 'text-red-500 animate-pulse-soft' : 'text-white'}">${parseFloat(carData.tpms_pressure_rr || 0).toFixed(1)}</p>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-blue-600/20 rounded-2xl p-5 border border-blue-500/30 text-center shadow-xl">
                <p class="text-sm font-bold text-blue-400 uppercase mb-1">Dernière charge</p>
                <p class="text-4xl font-black text-white">${PHP_LAST_CHARGE} <span class="text-xl font-normal text-blue-300">kWh ajoutés</span></p>
            </div>
            
            <p class="text-center text-[10px] text-gray-600 pt-4 uppercase tracking-widest">Mise à jour automatique toutes les 30s</p>
            
            <div class="border-t border-gray-700 mt-8 pt-6">
              <div class="flex justify-center items-center gap-6 text-xs text-gray-500">
                <a href="credits.php" class="hover:text-white transition-colors">Crédits</a>
                <span class="text-gray-700">•</span>
                <a href="parrain.php" class="hover:text-white transition-colors">Parrainage Tesla</a>
                <span class="text-gray-700">•</span>
                <span>Version 1.2 / 20/01/2026</span>
              </div>
            </div>
          </div>
        </div>
      `;
    }

    fetchData();
    setInterval(fetchData, REFRESH_INTERVAL * 1000);
  </script>
</body>
</html>
