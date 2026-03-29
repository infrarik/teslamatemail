# TeslaMate Mail

**Copyright (C) 2026 monwifi.fr / Eric B.**  
Licence GNU GPL v3 — Logiciel libre, sans garantie.

🇬🇧 **[English version — click here](#english-version)**

> **Note :** TeslaMate Mail n'a aucun lien officiel avec le projet TeslaMate. C'est uniquement un module complémentaire qui exploite une instance TeslaMate déjà installée, sans jamais modifier sa base de données.

---

## 🆕 Nouveautés récentes

- **Plage de dates** : sélection d'une période (ex. du 15 au 20) directement depuis l'interface. La sidebar affiche un résumé par jour cliquable, avec le total km et le nombre de trajets. Un bouton **PÉRIODE ENTIÈRE** fusionne tous les trajets sur la carte.
- **Navigation par flèches** : boutons ◀ ▶ pour avancer ou reculer d'un jour sans ouvrir le sélecteur de date.
- **Statistiques de charge sur la période** : dans le résumé de plage, affichage du total kWh rechargés et du nombre de charges.
- **Graphique batterie** : courbe % batterie en fonction du temps, affichée en bas de carte sur un trajet individuel ou une journée entière. Le survol/touch déplace le marqueur voiture sur la carte.
- **Barre de lecture déplaçable** : la barre PLAY/slider d'animation du trajet est désormais glissable librement sur la carte (souris et touch).
- **Légende des vitesses déplaçable** : le cadre "Vitesses (km/h)" est lui aussi glissable librement sur la carte.
- **Compatibilité navigateur Tesla** : détection fiable du navigateur embarqué Tesla (Chrome Linux x86_64, touch, sans Android). Le numpad tactile de saisie de date est forcé sur Tesla même si le navigateur supporte `input[type=date]`. Les boutons du numpad répondent au touch avec `touchend`.
- **Température batterie sur les charges** : les infobulles ⚡ affichent la température batterie début → fin (si disponible dans votre version de TeslaMate).
- **Chauffe batterie sur les charges** : affichage ON/OFF du chauffage batterie pendant la charge (lu depuis la table `positions` via `position_id`).
- **Prix du kWh par lieu** : possibilité de définir un prix du kWh différent pour chaque lieu (geofence). Le prix est mémorisé automatiquement et appliqué dans tous les calculs, rapports et exports.
- **Mémorisation du prix du kWh** : le prix saisi est automatiquement sauvegardé et pré-rempli à chaque visite.
- **Rapports email enrichis** : l'envoi par email produit un rapport HTML mis en page avec un **PDF en pièce jointe**, généré sans aucune dépendance externe.
- **Graphique de consommation dans les PDF** : les rapports PDF incluent un graphique à barres de la consommation journalière.
- **Consommation aux 100 km** : affichée dans les résultats, les PDF, les emails et les rapports automatiques.
- **Rapport hebdomadaire automatique** : envoi chaque lundi à 4h du matin de la semaine précédente, activable/désactivable depuis l'interface.
- **Niveau de batterie sur les charges** : les marqueurs ⚡ affichent le niveau de batterie au début et à la fin de chaque charge (carte 2D et 3D).
- **Niveau de batterie en temps réel** : au survol de la trace sur la carte 2D, le niveau de batterie s'affiche dans la barre d'information.
- **Marqueurs altitude max/min** : cercle rouge **+** au point le plus haut, cercle bleu **−** au point le plus bas, sur les deux cartes.
- **Analyse de conduite** : panneau flottant et déplaçable avec score éco (0–100), accélérations/freinages brusques, % temps haute vitesse et consommation estimée.
- **Contrôles sidebar repliables** : une flèche permet de masquer le sélecteur de véhicule, la date et les boutons pour maximiser la liste des trajets.
- **Sécurité PIN renforcée** : vérification côté serveur, invisible dans la source. Blocage 5 minutes après 3 tentatives incorrectes avec compte à rebours.
- **Carte des altitudes (3D) enrichie** : infobulles complètes sur tous les marqueurs avec heure, altitude, température et ville (géocodage automatique Nominatim).
- **Interface mobile améliorée** : panneau d'informations rétractable d'un bouton.

---

## Sommaire

- [Fonctionnalités](#fonctionnalités)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Mise à jour](#mise-à-jour)
- [Configuration](#configuration)
- [Vérifications techniques](#vérifications-techniques)
- [Interactions avec TeslaMate](#interactions-avec-teslamate)
- [Parrainage Tesla](#parrainage-tesla)
- [Remerciements](#remerciements)

---

## Fonctionnalités

### 🗺️ Carte des trajets (2D)

- Visualisation des trajets d'une journée complète, d'un trajet individuel ou d'une **plage de dates**
- Navigation jour par jour avec les boutons **◀ ▶**
- Mode **plage de dates** : résumé par jour dans la sidebar, bouton **PÉRIODE ENTIÈRE** pour fusionner tous les trajets
- Statistiques de charge sur la période : total kWh rechargés, nombre de charges
- Tracé coloré selon la vitesse (palette de couleurs de vert à rouge foncé)
- **Légende des vitesses déplaçable** librement sur la carte
- Affichage des températures, altitudes, dénivelé cumulé positif, vitesse max
- **Marqueurs interactifs** sur la carte :
  - 🟢 **Départ (D)** et 🔴 **Arrivée (A)** avec niveau de batterie
  - 🔵 **Pauses parking (P)** : durée, altitude, température extérieure
  - 🟡 **Charges (⚡)** : kWh ajoutés/consommés, durée, niveau batterie début→fin, température batterie début→fin, chauffe batterie ON/OFF
- Infobulle au survol/tap de la trace : heure, altitude, température, vitesse, niveau de batterie
- **Graphique batterie** : courbe % en bas de carte (trajet individuel et journée entière), tooltip au survol/touch avec synchronisation du marqueur voiture
- **Barre de lecture déplaçable** : PLAY/PAUSE, vitesse ×1/×5/×10/×20, slider, export vidéo
- Capture d'écran de la carte 2D
- Export KML pour Google Earth
- Sélecteur de fond de carte : Plan / Satellite / Mixte
- Interface responsive mobile/tablette avec panneau rétractable

### 🏔️ Carte des altitudes (3D)

- Tracé 3D interactif (Plotly) coloré selon l'altitude (palette Viridis)
- **Marqueurs annotés avec infobulles complètes** :
  - 🟢 **Départ (D)** : heure, altitude, température, ville (Nominatim)
  - 🔴 **Arrivée (A)** : heure, altitude, température, ville (Nominatim)
  - 🔵 **Pauses parking (P)** : durée, heure, altitude, température, ville
  - 🟡 **Charges (⚡)** : kWh ajoutés, durée, heure, altitude, température, ville, température batterie, chauffe batterie
- **Infobulles sur chaque point de la trace** : heure, altitude, température, vitesse, niveau de batterie
- Géocodage inverse asynchrone via **Nominatim / OpenStreetMap** (sans clé API)
- Les villes apparaissent progressivement sur les marqueurs au chargement

### 📊 Calculateur de consommation

- Calcul sur période personnalisée ou raccourcis : cette semaine, semaine dernière, ce mois, mois dernier, cette année, année précédente
- Résultats : distance, nombre de charges, énergie ajoutée, énergie consommée, coût total, **consommation moyenne aux 100 km**
- Filtrage par véhicule et par geofence
- Mode **V2L** (Vehicle-to-Load)
- Export **PDF** (généré en PHP pur, sans dépendance externe) avec graphique de consommation journalière
- Export **CSV**
- **Envoi par email** : rapport HTML mis en page + PDF en pièce jointe

### 📧 Rapport hebdomadaire automatique

- Envoi automatique chaque **lundi à 4h du matin** (cron)
- Couvre la **semaine précédente** (lundi → dimanche)
- Contenu : KPIs (distance, charges, kWh ajoutés, kWh consommés, coût, conso/100km) + tableau détaillé + graphique
- **PDF en pièce jointe** généré en PHP pur (aucune dépendance externe)
- Activation / désactivation via un **toggle dans teslacalcul.php**
- Bouton **"ENVOI RAPPORT SEMAINE"** pour envoyer la semaine en cours à la demande

### 🤖 Bot Telegram

- Notification de fin de charge : niveau de batterie, date/heure
- Test de configuration
- D'autres événements sont prévus

### 📡 Intégration MQTT

- Publication JSON à chaque fin de charge :  
  `{"id":837,"kwh":10.02,"soc":100,"duration":169}`

### 🔧 Outils de diagnostic

- **table.php** : explorateur de la base de données TeslaMate — liste toutes les tables avec colonnes, types, nullable, valeur par défaut. Recherche en temps réel par nom de table ou de colonne.
- **test.php** : détection du navigateur Tesla — 18 méthodes de détection PHP et JavaScript, verdict global.

### 📱 Interface

- Application **PWA** installable sur mobile (icône, service worker)
- Accès protégé par **code PIN** à 4 chiffres (configurable dans `setup`)
- Interface bilingue **Français / Anglais**
- **Compatibilité Tesla** : numpad tactile forcé pour la saisie de dates, détection fiable du navigateur embarqué

---

## Prérequis

| Élément | Description |
|---|---|
| Instance TeslaMate | Accès à la base de données PostgreSQL |
| Serveur SMTP | Identifiants pour l'envoi des emails |
| Broker MQTT | Serveur Mosquitto ou équivalent |
| Bot Telegram | API Token + Chat ID |
| Serveur Linux | Apache + PHP + accès root |

---

## Installation

```bash
# 1. Copiez dans /root
files.zip  install.sh  installweb.sh

# 2. Lancez l'installation complète
bash install.sh
```

Le script `install.sh` effectue automatiquement :
- Installation des dépendances (Apache, PHP, Postfix, etc.)
- Configuration Postfix (SMTP sortant)
- Déploiement des fichiers web dans `/var/www/html`
- Écriture de `cgi-bin/setup` avec votre configuration (email, prix kWh, etc.)
- Création du fichier de log `/var/log/tesla_rapport.log`
- Installation du **cron hebdomadaire** (lundi 4h)

```bash
# 3. Accédez à l'interface
http://votre_ip/
```

Configurez TeslaMate Mail via la roue dentée de l'écran principal.

---

## Mise à jour

Lorsqu'un nouveau `files.zip` est disponible :

```bash
# Depuis /root
bash installweb.sh
```

Ce script met à jour tous les fichiers PHP **sans jamais toucher à votre configuration** (`cgi-bin/setup` est préservé, les clés manquantes sont ajoutées automatiquement).

---

## Configuration

Toute la configuration est centralisée dans `/var/www/html/cgi-bin/setup` :

| Clé | Description |
|---|---|
| `notification_email` | Email destinataire |
| `KWH_PRICE` | Prix du kWh (ex: `0.2500`) |
| `RAPPORT_HEBDO` | `True` / `False` — envoi hebdomadaire actif |
| `DOCKER_PATH` | Chemin vers `docker-compose.yml` |
| `LANGUAGE` | `fr` ou `en` |
| `CURRENCY` | `EUR`, `USD`, etc. |
| `email_enabled` | `True` / `False` |
| `telegram_enabled` | `True` / `False` |
| `mqtt_enabled` | `True` / `False` |
| `code` | Code PIN d'accès (4 chiffres) |

---

## Vérifications techniques

```bash
# Configuration principale
cat /var/www/html/cgi-bin/setup

# Dernière session de charge traitée
cat /var/www/html/cgi-bin/lastchargeid

# Destinataires Telegram
cat /var/www/html/cgi-bin/telegram_user.json

# Logs du rapport hebdomadaire
tail -50 /var/log/tesla_rapport.log

# Crontab actif
crontab -l

# Test manuel du rapport
php /var/www/html/tesla_rapport_hebdo.php

# Écouter le topic MQTT
mosquitto_sub -t "teslamate/tmy" -v
```

---

## Interactions avec TeslaMate

TeslaMate Mail interagit avec TeslaMate de façon **strictement non destructive** :
- Suppression des commentaires dans `docker-compose.yml` (une seule fois, à l'installation)
- Lecture seule de la base de données PostgreSQL
- Aucune modification des données TeslaMate

---

## Parrainage Tesla

Si ce développement libre vous est utile, n'hésitez pas à utiliser le lien de parrainage Tesla présent en bas de la page principale — c'est un coup de pouce apprécié pour continuer à faire évoluer le projet. Merci !

---

## Remerciements

- **Jérôme Y.** — débogage et idées de nouvelles fonctionnalités

---

## Licence

Ce programme est distribué sous licence **GNU GPL v3**.

1. **Copyleft** : toute modification doit rester sous licence GPL
2. **Code source** : obligation de le fournir si redistribution
3. **Absence de garantie** : distribué tel quel, sans garantie d'aucune sorte

---

*TeslaMate Mail — monwifi.fr / Eric B. — 2026*

---
---

# English Version

<a name="english-version"></a>

# TeslaMate Mail

**Copyright (C) 2026 monwifi.fr / Eric B.**  
GNU GPL v3 License — Free software, no warranty.

🇫🇷 **[Version française — cliquez ici](#teslamate-mail)**

> **Note:** TeslaMate Mail has no official connection with the TeslaMate project. It is strictly an add-on that uses an existing TeslaMate installation, without ever modifying its database.

---

## 🆕 Recent Updates

- **Date range selection**: select a period (e.g. March 15 to 20) directly from the interface. The sidebar shows a clickable per-day summary with total km and number of trips. A **FULL PERIOD** button merges all trips on the map.
- **Day navigation arrows**: ◀ ▶ buttons to go forward or back one day without opening the date picker.
- **Charge statistics for the period**: in the range summary, total kWh recharged and number of charging sessions.
- **Battery chart**: battery % curve over time, shown at the bottom of the map for an individual trip or full-day view. Hover/touch moves the car marker on the map.
- **Draggable playback bar**: the PLAY/slider animation bar can now be freely dragged across the map (mouse and touch).
- **Draggable speed legend**: the "Speed (km/h)" legend box is also freely draggable on the map.
- **Tesla browser compatibility**: reliable detection of the Tesla embedded browser (Chrome Linux x86_64, touch, no Android). The touch numpad for date entry is forced on Tesla even if the browser supports `input[type=date]`. Numpad buttons respond to touch via `touchend`.
- **Battery temperature on charges**: ⚡ tooltips now show battery temperature start → end (if available in your TeslaMate version).
- **Battery heater on charges**: ON/OFF display of battery heating during charge (read from `positions` table via `position_id`).
- **Per-location kWh pricing**: set a different kWh price per geofence. Saved automatically and applied in all calculations, reports and exports.
- **kWh price memory**: the price entered is automatically saved and pre-filled on every visit.
- **Enhanced email reports**: HTML report with a **PDF attachment**, generated without any external dependency.
- **Consumption chart in PDFs**: PDF reports include a daily consumption bar chart.
- **Consumption per 100 km**: displayed in results, PDFs, emails and automatic reports.
- **Automatic weekly report**: sent every Monday at 4:00 AM covering the previous week, enable/disable directly from the interface.
- **Battery level on charging markers**: ⚡ markers show battery level at start and end of each charging session (2D and 3D maps).
- **Real-time battery level**: hovering over the track on the 2D map shows the battery level in the info bar.
- **Altitude max/min markers**: red **+** circle at the highest point, blue **−** circle at the lowest, on both maps.
- **Driving analysis**: floating draggable panel with eco score (0–100), hard accelerations/brakings, % time at high speed and estimated consumption.
- **Collapsible sidebar controls**: an arrow hides the vehicle selector, date picker and action buttons to maximize the trip list.
- **Strengthened PIN security**: server-side verification, invisible in page source. 5-minute lockout after 3 failed attempts with countdown display.
- **Enriched altitude map (3D)**: full tooltips on all markers with time, altitude, temperature and city (automatic Nominatim geocoding).
- **Improved mobile interface**: collapsible info panel with a single button.

---

## Table of Contents

- [Features](#features)
- [Prerequisites](#prerequisites)
- [Installation](#installation-1)
- [Update](#update)
- [Configuration](#configuration-1)
- [Technical Checks](#technical-checks)
- [Interactions with TeslaMate](#interactions-with-teslamate-1)
- [Tesla Referral](#tesla-referral)
- [Acknowledgements](#acknowledgements)

---

## Features

### 🗺️ Trip Map (2D)

- Visualization of a full day, individual trip or **date range**
- Day-by-day navigation with **◀ ▶** buttons
- **Date range mode**: per-day sidebar summary, **FULL PERIOD** button to merge all trips
- Period charge statistics: total kWh recharged, number of charging sessions
- Color-coded track by speed (green to dark red palette)
- **Draggable speed legend** freely positioned on the map
- Display of temperatures, altitudes, cumulative elevation gain, max speed
- **Interactive markers** on the map:
  - 🟢 **Departure (D)** and 🔴 **Arrival (A)** with battery level
  - 🔵 **Parking stops (P)**: duration, altitude, outside temperature
  - 🟡 **Charging sessions (⚡)**: kWh added/used, duration, battery level start→end, battery temperature start→end, battery heater ON/OFF
- Tooltip on track hover/tap: time, altitude, temperature, speed, battery level
- **Battery chart**: % curve at the bottom of the map, tooltip on hover/touch syncs car marker
- **Draggable playback bar**: PLAY/PAUSE, speed ×1/×5/×10/×20, slider, video export
- 2D map screenshot
- KML export for Google Earth
- Map layer selector: Map / Satellite / Hybrid
- Responsive mobile/tablet interface with collapsible panel

### 🏔️ Altitude Map (3D)

- Interactive 3D track (Plotly) color-coded by altitude (Viridis palette)
- **Annotated markers with full tooltips**:
  - 🟢 **Departure (D)**: time, altitude, temperature, city (Nominatim)
  - 🔴 **Arrival (A)**: time, altitude, temperature, city (Nominatim)
  - 🔵 **Parking stops (P)**: duration, time, altitude, temperature, city
  - 🟡 **Charging sessions (⚡)**: kWh added, duration, time, altitude, temperature, city, battery temperature, battery heater
- **Tooltip on each track point**: time, altitude, temperature, speed, battery level
- Asynchronous reverse geocoding via **Nominatim / OpenStreetMap** (no API key required)
- City names appear progressively on markers as they load

### 📊 Consumption Calculator

- Calculation over a custom period or quick shortcuts: this week, last week, this month, last month, this year, last year
- Results: distance, number of charges, energy added, energy used, total cost, **average consumption per 100 km**
- Filter by vehicle and geofence
- **V2L** mode (Vehicle-to-Load)
- **PDF export** (pure PHP, no external dependency) with daily consumption bar chart
- **CSV export**
- **Send by email**: formatted HTML report + PDF attachment

### 📧 Automatic Weekly Report

- Automatic send every **Monday at 4:00 AM** (cron)
- Covers the **previous week** (Monday → Sunday)
- Content: KPIs (distance, charges, kWh added, kWh used, cost, consumption/100km) + detailed table + chart
- **PDF attachment** generated in pure PHP (no external dependency)
- Enable / disable via a **toggle in teslacalcul.php**
- **"SEND WEEK REPORT"** button to send the current week on demand

### 🤖 Telegram Bot

- End-of-charge notification: battery level, date/time
- Configuration test
- More events planned

### 📡 MQTT Integration

- JSON publish at each end of charge:  
  `{"id":837,"kwh":10.02,"soc":100,"duration":169}`

### 🔧 Diagnostic Tools

- **table.php**: TeslaMate database explorer — lists all tables with columns, types, nullable, default values. Real-time search by table or column name.
- **test.php**: Tesla browser detection — 18 PHP and JavaScript detection methods, global verdict.

### 📱 Interface

- **PWA** application installable on mobile (icon, service worker)
- Access protected by a **4-digit PIN code** (configurable in `setup`)
- **French / English** bilingual interface
- **Tesla compatibility**: forced touch numpad for date entry, reliable embedded browser detection

---

## Prerequisites

| Element | Description |
|---|---|
| TeslaMate Instance | Access to the PostgreSQL database |
| SMTP Server | Credentials for sending emails |
| MQTT Broker | Mosquitto server or equivalent |
| Telegram Bot | API Token + Chat ID |
| Linux Server | Apache + PHP + root access |

---

## Installation

```bash
# 1. Copy to /root
files.zip  install.sh  installweb.sh

# 2. Run the full installation
bash install.sh
```

The `install.sh` script automatically handles:
- Dependency installation (Apache, PHP, Postfix, etc.)
- Postfix configuration (outgoing SMTP)
- Web file deployment to `/var/www/html`
- Writing `cgi-bin/setup` with your configuration (email, kWh price, etc.)
- Creating the log file `/var/log/tesla_rapport.log`
- Installing the **weekly cron job** (Monday 4am)

```bash
# 3. Access the interface
http://your_ip/
```

Configure TeslaMate Mail via the gear icon on the main screen.

---

## Update

When a new `files.zip` is available:

```bash
# From /root
bash installweb.sh
```

This script updates all PHP files **without ever touching your configuration** (`cgi-bin/setup` is preserved, any missing keys are added automatically).

---

## Configuration

All configuration is centralized in `/var/www/html/cgi-bin/setup`:

| Key | Description |
|---|---|
| `notification_email` | Recipient email address |
| `KWH_PRICE` | Price per kWh (e.g. `0.2500`) |
| `RAPPORT_HEBDO` | `True` / `False` — weekly report active |
| `DOCKER_PATH` | Path to `docker-compose.yml` |
| `LANGUAGE` | `fr` or `en` |
| `CURRENCY` | `EUR`, `USD`, etc. |
| `email_enabled` | `True` / `False` |
| `telegram_enabled` | `True` / `False` |
| `mqtt_enabled` | `True` / `False` |
| `code` | 4-digit access PIN code |

---

## Technical Checks

```bash
# Main configuration
cat /var/www/html/cgi-bin/setup

# Last processed charging session
cat /var/www/html/cgi-bin/lastchargeid

# Telegram recipients
cat /var/www/html/cgi-bin/telegram_user.json

# Weekly report logs
tail -50 /var/log/tesla_rapport.log

# Active crontab
crontab -l

# Manual report test
php /var/www/html/tesla_rapport_hebdo.php

# Listen to MQTT topic
mosquitto_sub -t "teslamate/tmy" -v
```

---

## Interactions with TeslaMate

TeslaMate Mail interacts with TeslaMate in a **strictly non-destructive** way:
- Removal of comments from `docker-compose.yml` (once, at installation)
- Read-only access to the PostgreSQL database
- No modification of TeslaMate data

---

## Tesla Referral

If you find this free open-source project useful, feel free to use the Tesla referral link at the bottom of the main page — it's a welcome boost to keep the project evolving. Thank you!

---

## Acknowledgements

- **Jérôme Y.** — debugging and new feature ideas

---

## License

This program is distributed under the **GNU GPL v3** license.

1. **Copyleft**: any modification must remain under the GPL license
2. **Source code**: obligation to provide it upon redistribution
3. **No warranty**: distributed as-is, without any warranty of any kind

---

*TeslaMate Mail — monwifi.fr / Eric B. — 2026*
