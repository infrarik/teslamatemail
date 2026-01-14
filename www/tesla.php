<?php
// --- 1. CONFIGURATION (Identique à teslamap.php) ---
$file = 'cgi-bin/setup';
$config = ['DOCKER_PATH' => ''];
if (file_exists($file)) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) { $config[trim($parts[0])] = trim($parts[1]); }
    }
}

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
    $pdo = new PDO("pgsql:host=localhost;port=5432;dbname=$db_name", $db_user, $db_pass);
    // Requête pour la toute dernière valeur de kWh ajoutés
    $sql = "SELECT charge_energy_added FROM charging_processes WHERE end_date IS NOT NULL ORDER BY end_date DESC LIMIT 1";
    $last_charge_kwh = (float)$pdo->query($sql)->fetchColumn();
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
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title>TeslaMate Mobile</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
    .animate-spin { animation: spin 1s linear infinite; }
    .animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
  </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-black text-white min-h-screen">
  <div id="app"></div>

  <script>
    const API_URL = 'teslamate_api.php';
    const REFRESH_INTERVAL = 30; 
    // Injection de la valeur récupérée en PHP
    const PHP_LAST_CHARGE = <?= round($last_charge_kwh, 2) ?>;

    let carData = null;
    let loading = false;
    let error = null;
    let lastUpdate = null;

    async function fetchData() {
      loading = true;
      render();
      try {
        const response = await fetch(API_URL);
        if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);
        const data = await response.json();
        if (data.error) throw new Error(data.error);
        carData = data;
        lastUpdate = new Date();
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

      app.innerHTML = `
        <div class="min-h-screen pb-20">
          <div class="bg-gray-800/90 backdrop-blur-sm border-b border-gray-700 p-4 sticky top-0 z-10">
            <div class="max-w-2xl mx-auto flex flex-col items-center space-y-4">
              <div class="text-center py-1">
                <h1 class="text-2xl font-bold leading-tight text-white tracking-tight">${carData.display_name}</h1>
                <p class="text-[10px] text-gray-400 uppercase tracking-[0.2em] font-medium">TeslaMate-Mail</p>
              </div>

              <div class="w-full flex items-center">
                <div class="w-10"></div> 
                <div class="flex-1 flex justify-center items-center gap-4">
                  <a href="teslamap.php" class="p-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors">
                    <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A2 2 0 013 15.488V5.13a2 2 0 011.106-1.789L9 1m0 19v-19m0 19l6-3m-6-16l6 3m0 0l5.447-2.724A2 2 0 0121 4.512v10.358a2 2 0 01-1.106 1.789L15 20m0-19v19"></path></svg>
                  </a>
                  <a href="teslamail.php" class="p-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors">
                    <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                  </a>
                  <a href="teslacalcul.php" class="p-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors">
                    <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                  </a>
                  <a href="teslaconf.php" class="p-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors">
                    <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                  </a>
                </div>
                <button onclick="fetchData()" class="p-2 bg-gray-700 hover:bg-gray-600 rounded-full flex-shrink-0 transition-colors">
                  <svg class="w-5 h-5 ${loading ? 'animate-spin' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </button>
              </div>
            </div>
          </div>

          <div class="px-4 pt-6 space-y-4 max-w-2xl mx-auto pb-6">
            <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl p-6 border border-gray-700 shadow-xl flex justify-between items-center">
              <div>
                <h2 class="text-lg font-semibold">État</h2>
                <p class="text-xs text-gray-500">MàJ: ${lastUpdate ? lastUpdate.toLocaleTimeString('fr-FR') : '--'}</p>
              </div>
              <span class="px-4 py-1.5 rounded-full text-sm font-medium ${carData.state === 'online' ? 'bg-green-500/20 text-green-400 border border-green-500/30' : 'bg-gray-500/20 text-gray-400 border border-gray-500/30'}">
                ${carData.state}
              </span>
            </div>

            <div class="bg-gradient-to-br from-green-900/20 to-gray-900 rounded-2xl p-6 border border-green-700/30 shadow-xl">
              <div class="flex items-center gap-3 mb-5">
                <div class="p-3 bg-green-500/20 rounded-xl"><svg class="w-7 h-7 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg></div>
                <h2 class="text-xl font-bold">Batterie</h2>
              </div>
              <div class="flex justify-between items-end mb-3">
                <span class="text-5xl font-bold text-green-400">${carData.battery_level}%</span>
                <span class="text-gray-400 text-sm">${Math.round(carData.ideal_battery_range_km)} km</span>
              </div>
              <div class="relative w-full bg-gray-700 rounded-full h-4 overflow-hidden mb-6">
                <div class="absolute inset-0 bg-gradient-to-r from-green-600 to-green-400" style="width: ${carData.battery_level}%"></div>
              </div>
              
              <div class="grid grid-cols-2 gap-4">
                <div class="bg-gray-800/50 rounded-xl p-4 text-center">
                  <p class="text-gray-400 text-xs mb-1 font-bold">Odomètre</p>
                  <p class="text-xl font-bold text-white">${carData.odometer.toLocaleString('fr-FR')} km</p>
                </div>
                <div class="bg-gray-800/50 rounded-xl p-4 text-center">
                  <p class="text-gray-400 text-xs mb-1 font-bold">Vitesse</p>
                  <p class="text-xl font-bold text-white">${carData.speed || 0} km/h</p>
                </div>
              </div>
            </div>

            <div class="bg-gradient-to-br from-blue-900/20 to-gray-900 rounded-2xl p-5 border border-blue-700/30 shadow-xl text-center">
                <p class="text-gray-400 text-[10px] uppercase font-bold tracking-widest mb-1">Dernière charge</p>
                <p class="text-3xl font-black text-white">${PHP_LAST_CHARGE}</p>
                <p class="text-[10px] text-gray-500 uppercase font-bold tracking-tighter">kWh ajoutés</p>
            </div>

            <div class="bg-gradient-to-br from-blue-900/10 to-gray-900 rounded-2xl p-6 border border-gray-700 shadow-xl">
              <div class="grid grid-cols-2 gap-4">
                <div class="text-center">
                  <p class="text-gray-400 text-xs mb-2 font-bold">🏠 Intérieur</p>
                  <p class="text-2xl font-bold text-orange-400">${parseFloat(carData.inside_temp).toFixed(1)}°C</p>
                </div>
                <div class="text-center">
                  <p class="text-gray-400 text-xs mb-2 font-bold">🌡 Extérieur</p>
                  <p class="text-2xl font-bold text-blue-400">${parseFloat(carData.outside_temp).toFixed(1)}°C</p>
                </div>
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
