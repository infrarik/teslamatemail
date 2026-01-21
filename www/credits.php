<?php
// --- 1. CONFIGURATION & LANGUE ---
$file = "/var/www/html/cgi-bin/setup";

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

$config = read_setup($file);
$lang = (isset($config['LANGUAGE']) && strtoupper($config['LANGUAGE']) === 'EN') ? 'en' : 'fr';

// --- 2. TRADUCTIONS ---
$texts = [
    'fr' => [
        'title' => 'Crédits - TeslaMate Mobile',
        'header' => 'Crédits',
        'tm_desc' => "TeslaMate est un outil open-source puissant et auto-hébergé pour l'enregistrement et la visualisation des données de votre Tesla. Développé par Adrian Kumpf et la communauté.",
        'github' => 'Voir sur GitHub',
        'dev_title' => 'Développement',
        'dev_by' => 'Interface mobile développée par',
        'tech_title' => 'Technologies utilisées',
        'tech_tm' => 'TeslaMate - Collecte et stockage des données',
        'tech_grafana' => 'Grafana - Visualisation des données',
        'tech_db' => 'PostgreSQL - Base de données',
        'tech_php' => 'PHP - Backend',
        'tech_tailwind' => 'Tailwind CSS - Interface utilisateur',
        'legal_title' => 'Mentions légales',
        'legal_tesla' => 'est une marque déposée de Tesla, Inc. Ce projet n\'est pas affilié à, approuvé par ou associé à Tesla, Inc.',
        'legal_tm' => 'est un projet open-source sous licence MIT. Tous les droits appartiennent à leurs propriétaires respectifs.',
        'legal_warranty' => 'Cette interface mobile est fournie "telle quelle", sans garantie d\'aucune sorte. L\'utilisation se fait à vos propres risques.',
        'rights' => 'Tous droits réservés',
        'license_title' => 'Licence',
        'license_desc' => 'Ce projet est distribué sous licence GNU General Public License (GPL). Vous êtes libre de l\'utiliser, le modifier et le distribuer selon les termes de cette licence.',
        'footer_referral' => 'Parrainage Tesla'
    ],
    'en' => [
        'title' => 'Credits - TeslaMate Mobile',
        'header' => 'Credits',
        'tm_desc' => "TeslaMate is a powerful, self-hosted open-source tool for logging and visualizing your Tesla's data. Developed by Adrian Kumpf and the community.",
        'github' => 'View on GitHub',
        'dev_title' => 'Development',
        'dev_by' => 'Mobile interface developed by',
        'tech_title' => 'Technologies used',
        'tech_tm' => 'TeslaMate - Data collection and storage',
        'tech_grafana' => 'Grafana - Data visualization',
        'tech_db' => 'PostgreSQL - Database',
        'tech_php' => 'PHP - Backend',
        'tech_tailwind' => 'Tailwind CSS - User Interface',
        'legal_title' => 'Legal Notice',
        'legal_tesla' => 'is a registered trademark of Tesla, Inc. This project is not affiliated with, endorsed by, or associated with Tesla, Inc.',
        'legal_tm' => 'is an open-source project under MIT license. All rights belong to their respective owners.',
        'legal_warranty' => 'This mobile interface is provided "as is", without warranty of any kind. Use at your own risk.',
        'rights' => 'All rights reserved',
        'license_title' => 'License',
        'license_desc' => 'This project is distributed under the GNU General Public License (GPL). You are free to use, modify, and distribute it under the terms of this license.',
        'footer_referral' => 'Tesla Referral'
    ]
];

$t = $texts[$lang];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#dc2626">
  <title><?= $t['title'] ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-black text-white min-h-screen">
  
  <div class="min-h-screen pb-20">
    <div class="bg-gray-800/90 backdrop-blur-sm border-b border-gray-700 p-4 sticky top-0 z-10">
      <div class="max-w-2xl mx-auto">
        <div class="flex items-center justify-between">
          <a href="tesla.php" class="p-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors">
            <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
          </a>
          <h1 class="text-2xl font-bold text-white tracking-tight"><?= $t['header'] ?></h1>
          <div class="w-9"></div>
        </div>
      </div>
    </div>

    <div class="px-4 pt-6 space-y-6 max-w-2xl mx-auto">
      
      <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl p-6 border border-gray-700 shadow-xl">
        <div class="flex items-center gap-3 mb-4">
          <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
          </svg>
          <h2 class="text-xl font-bold text-white">TeslaMate</h2>
        </div>
        <p class="text-gray-400 text-sm leading-relaxed mb-3">
          <?= $t['tm_desc'] ?>
        </p>
        <a href="https://github.com/teslamate-org/teslamate" target="_blank" class="inline-flex items-center gap-2 text-red-500 hover:text-red-400 text-sm font-semibold transition-colors">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
          </svg>
          <?= $t['github'] ?>
        </a>
      </div>

      <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl p-6 border border-gray-700 shadow-xl">
        <div class="flex items-center gap-3 mb-4">
          <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
          </svg>
          <h2 class="text-xl font-bold text-white"><?= $t['dev_title'] ?></h2>
        </div>
        <p class="text-gray-400 text-sm leading-relaxed mb-2">
          <?= $t['dev_by'] ?> <span class="text-white font-semibold">Eric B.</span>
        </p>
        <a href="https://monwifi.fr" target="_blank" class="inline-flex items-center gap-2 text-blue-500 hover:text-blue-400 text-sm font-semibold transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
          </svg>
          Monwifi.fr
        </a>
      </div>

      <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl p-6 border border-gray-700 shadow-xl">
        <div class="flex items-center gap-3 mb-4">
          <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
          </svg>
          <h2 class="text-xl font-bold text-white"><?= $t['tech_title'] ?></h2>
        </div>
        <ul class="space-y-2 text-gray-400 text-sm">
          <li class="flex items-center gap-2">
            <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
            <?= $t['tech_tm'] ?>
          </li>
          <li class="flex items-center gap-2">
            <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
            <?= $t['tech_grafana'] ?>
          </li>
          <li class="flex items-center gap-2">
            <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
            <?= $t['tech_db'] ?>
          </li>
          <li class="flex items-center gap-2">
            <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
            <?= $t['tech_php'] ?>
          </li>
          <li class="flex items-center gap-2">
            <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
            <?= $t['tech_tailwind'] ?>
          </li>
        </ul>
      </div>

      <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl p-6 border border-gray-700 shadow-xl">
        <div class="flex items-center gap-3 mb-4">
          <svg class="w-8 h-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
          </svg>
          <h2 class="text-xl font-bold text-white"><?= $t['legal_title'] ?></h2>
        </div>
        <div class="space-y-3 text-gray-400 text-xs leading-relaxed">
          <p>
            <span class="text-white font-semibold">Tesla®</span> <?= $t['legal_tesla'] ?>
          </p>
          <p>
            <span class="text-white font-semibold">TeslaMate™</span> <?= $t['legal_tm'] ?>
          </p>
          <p>
            <?= $t['legal_warranty'] ?>
          </p>
          <p class="text-gray-500 text-[10px] pt-2">
            © 2026 Eric B. / Monwifi.fr - <?= $t['rights'] ?>
          </p>
        </div>
      </div>

      <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl p-6 border border-gray-700 shadow-xl">
        <div class="flex items-center gap-3 mb-4">
          <svg class="w-8 h-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
          <h2 class="text-xl font-bold text-white"><?= $t['license_title'] ?></h2>
        </div>
        <p class="text-gray-400 text-sm leading-relaxed">
          <?= $t['license_desc'] ?>
        </p>
      </div>

      <div class="border-t border-gray-700 mt-8 pt-6">
        <div class="flex justify-center items-center gap-6 text-xs text-gray-500">
          <a href="credits.php" class="text-white"><?= $t['header'] ?></a>
          <span class="text-gray-700">•</span>
          <a href="parrain.php" class="hover:text-white transition-colors"><?= $t['footer_referral'] ?></a>
          <span class="text-gray-700">•</span>
          <span>Version 1.2 / 20/01/2026</span>
        </div>
      </div>

    </div>
  </div>

</body>
</html>
