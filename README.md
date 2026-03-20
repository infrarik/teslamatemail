# TeslaMate Mail

**Copyright (C) 2026 monwifi.fr / Eric B.**  
Licence GNU GPL v3 — Logiciel libre, sans garantie.

🇬🇧 **[English version — click here](#english-version)**

> **Note :** TeslaMate Mail n'a aucun lien officiel avec le projet TeslaMate. C'est uniquement un module complémentaire qui exploite une instance TeslaMate déjà installée, sans jamais modifier sa base de données.

---

## 🆕 Nouveautés récentes

- **Prix du kWh par lieu** : il est maintenant possible de définir un prix du kWh différent pour chaque lieu (geofence). Le prix est mémorisé automatiquement par lieu et appliqué dans tous les calculs, rapports et exports. Si aucun prix spécifique n'est défini pour un lieu, le prix général est utilisé.
- **Mémorisation du prix du kWh** : le prix saisi est automatiquement sauvegardé et pré-rempli à chaque visite.
- **Rapports email enrichis** : l'envoi par email produit désormais un rapport HTML mis en page avec un **PDF en pièce jointe**, généré sans aucune dépendance externe. Cela s'applique aussi bien à l'envoi manuel depuis le calculateur qu'au rapport hebdomadaire automatique.
- **Consommation aux 100 km** : affichée dans les résultats, les PDF, les emails et les rapports automatiques.
- **Rapport hebdomadaire automatique** : envoi chaque lundi à 4h du matin de la semaine précédente, activable/désactivable depuis l'interface sans toucher à la configuration système.
- **Carte des altitudes (3D) enrichie** : les points de départ, d'arrivée, les pauses et les charges affichent une infobulle complète avec l'heure, l'altitude, la température et la ville (géocodage automatique via OpenStreetMap). Chaque point de la trace affiche également ces informations au survol.
- **Interface mobile améliorée** : panneau d'informations rétractable d'un simple bouton pour maximiser la carte sur petit écran.

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

- Visualisation des trajets d'une journée complète ou d'un trajet individuel
- Tracé coloré selon la vitesse (palette de couleurs de vert à rouge foncé)
- Affichage des températures, altitudes, dénivelé cumulé positif, vitesse max
- **Marqueurs interactifs** sur la carte :
  - 🟢 **Départ (D)** et 🔴 **Arrivée (A)** avec niveau de batterie
  - 🔵 **Pauses parking (P)** : durée, altitude, température extérieure
  - 🟡 **Charges (⚡)** : kWh ajoutés, kWh consommés, durée, altitude, température
- Infobulle au survol de la trace : heure, altitude, température, vitesse
- Capture d'écran de la carte 2D
- Export vidéo animé du trajet
- Export KML pour Google Earth
- Sélecteur de fond de carte : Plan / Satellite / Mixte
- Interface responsive mobile/tablette avec panneau rétractable

### 🏔️ Carte des altitudes (3D)

- Tracé 3D interactif (Plotly) coloré selon l'altitude (palette Viridis)
- **Marqueurs annotés avec infobulles complètes** :
  - 🟢 **Départ (D)** : heure, altitude, température, ville (Nominatim)
  - 🔴 **Arrivée (A)** : heure, altitude, température, ville (Nominatim)
  - 🔵 **Pauses parking (P)** : durée, heure, altitude, température, ville
  - 🟡 **Charges (⚡)** : kWh ajoutés, durée, heure, altitude, température, ville
- **Infobulles sur chaque point de la trace** : heure, altitude, température, vitesse
- Géocodage inverse asynchrone via **Nominatim / OpenStreetMap** (sans clé API)
- Les villes apparaissent progressivement sur les marqueurs au chargement

### 📊 Calculateur de consommation

- Calcul sur période personnalisée ou raccourcis : cette semaine, semaine dernière, ce mois, mois dernier, cette année, année précédente
- Résultats : distance, nombre de charges, énergie ajoutée, énergie consommée, coût total, **consommation moyenne aux 100 km**
- Filtrage par véhicule et par geofence
- Mode **V2L** (Vehicle-to-Load)
- Export **PDF** (généré en PHP pur, sans dépendance externe)
- Export **CSV**
- **Envoi par email** : rapport HTML mis en page + PDF en pièce jointe, identique au rapport hebdomadaire

### 📧 Rapport hebdomadaire automatique

- Envoi automatique chaque **lundi à 4h du matin** (cron)
- Couvre la **semaine précédente** (lundi → dimanche)
- Contenu : KPIs (distance, charges, kWh ajoutés, kWh consommés, coût, conso/100km) + tableau détaillé
- **PDF en pièce jointe** généré en PHP pur (aucune dépendance externe)
- Activation / désactivation via un **toggle dans teslacalcul.php** (écrit `RAPPORT_HEBDO=True/False` dans `cgi-bin/setup`)
- Le cron est fixe et permanent ; c'est le flag dans `setup` qui contrôle l'envoi
- Prix du kWh lu depuis `cgi-bin/setup` (clé `KWH_PRICE`)
- Bouton **"ENVOI RAPPORT SEMAINE"** pour envoyer la semaine en cours à la demande

### 🤖 Bot Telegram

- Notification de fin de charge : niveau de batterie, date/heure
- Test de configuration
- D'autres événements sont prévus

### 📡 Intégration MQTT

- Publication JSON à chaque fin de charge :  
  `{"id":837,"kwh":10.02,"soc":100,"duration":169}`

### 📱 Interface

- Application **PWA** installable sur mobile (icône, service worker)
- Accès protégé par **code PIN** à 4 chiffres (configurable dans `setup`)
- Interface bilingue **Français / Anglais**

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
- **Groupe Facebook Tesla Model Y - France** — retours et soutien

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

- **Per-location kWh pricing**: it is now possible to set a different kWh price for each location (geofence). The price is automatically saved per location and applied across all calculations, reports and exports. If no specific price is defined for a location, the general price is used.
- **kWh price memory**: the price entered is automatically saved and pre-filled on every visit.
- **Enhanced email reports**: sending by email now produces a formatted HTML report with a **PDF attachment**, generated without any external dependency. This applies to both manual sending from the calculator and the automatic weekly report.
- **Consumption per 100 km**: displayed in results, PDFs, emails and automatic reports.
- **Automatic weekly report**: sent every Monday at 4:00 AM covering the previous week, can be enabled or disabled directly from the interface without touching system configuration.
- **Enriched altitude map (3D)**: departure, arrival, parking stops and charging markers now display a full tooltip with time, altitude, temperature and city name (automatic geocoding via OpenStreetMap). Each point on the track also shows this information on hover.
- **Improved mobile interface**: collapsible information panel with a single button to maximize the map on small screens.

---

## Table of Contents

- [Features](#features)
- [Prerequisites](#prerequisites)
- [Installation](#installation-1)
- [Update](#update)
- [Configuration](#configuration-1)
- [Technical Checks](#technical-checks)
- [Interactions with TeslaMate](#interactions-with-teslamate)
- [Tesla Referral](#tesla-referral)
- [Acknowledgements](#acknowledgements)

---

## Features

### 🗺️ Trip Map (2D)

- Visualization of a full day or individual trip
- Color-coded track by speed (green to dark red palette)
- Display of temperatures, altitudes, cumulative elevation gain, max speed
- **Interactive markers** on the map:
  - 🟢 **Departure (D)** and 🔴 **Arrival (A)** with battery level
  - 🔵 **Parking stops (P)**: duration, altitude, outside temperature
  - 🟡 **Charging sessions (⚡)**: kWh added, kWh used, duration, altitude, temperature
- Tooltip on track hover: time, altitude, temperature, speed
- 2D map screenshot
- Animated trip video export
- KML export for Google Earth
- Map layer selector: Map / Satellite / Hybrid
- Responsive mobile/tablet interface with collapsible panel

### 🏔️ Altitude Map (3D)

- Interactive 3D track (Plotly) color-coded by altitude (Viridis palette)
- **Annotated markers with full tooltips**:
  - 🟢 **Departure (D)**: time, altitude, temperature, city (Nominatim)
  - 🔴 **Arrival (A)**: time, altitude, temperature, city (Nominatim)
  - 🔵 **Parking stops (P)**: duration, time, altitude, temperature, city
  - 🟡 **Charging sessions (⚡)**: kWh added, duration, time, altitude, temperature, city
- **Tooltip on each track point**: time, altitude, temperature, speed
- Asynchronous reverse geocoding via **Nominatim / OpenStreetMap** (no API key required)
- City names appear progressively on markers as they load

### 📊 Consumption Calculator

- Calculation over a custom period or quick shortcuts: this week, last week, this month, last month, this year, last year
- Results: distance, number of charges, energy added, energy used, total cost, **average consumption per 100 km**
- Filter by vehicle and geofence
- **V2L** mode (Vehicle-to-Load)
- **PDF export** (generated in pure PHP, no external dependency)
- **CSV export**
- **Send by email**: formatted HTML report + PDF attachment, identical to the weekly report

### 📧 Automatic Weekly Report

- Automatic send every **Monday at 4:00 AM** (cron)
- Covers the **previous week** (Monday → Sunday)
- Content: KPIs (distance, charges, kWh added, kWh used, cost, consumption/100km) + detailed table
- **PDF attachment** generated in pure PHP (no external dependency)
- Enable / disable via a **toggle in teslacalcul.php** (writes `RAPPORT_HEBDO=True/False` to `cgi-bin/setup`)
- The cron is fixed and permanent; the flag in `setup` controls whether the email is sent
- kWh price read from `cgi-bin/setup` (key `KWH_PRICE`)
- **"SEND WEEK REPORT"** button to send the current week on demand

### 🤖 Telegram Bot

- End-of-charge notification: battery level, date/time
- Configuration test
- More events planned

### 📡 MQTT Integration

- JSON publish at each end of charge:  
  `{"id":837,"kwh":10.02,"soc":100,"duration":169}`

### 📱 Interface

- **PWA** application installable on mobile (icon, service worker)
- Access protected by a **4-digit PIN code** (configurable in `setup`)
- **French / English** bilingual interface

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
- **Facebook Group Tesla Model Y - France** — feedback and support

---

## License

This program is distributed under the **GNU GPL v3** license.

1. **Copyleft**: any modification must remain under the GPL license
2. **Source code**: obligation to provide it upon redistribution
3. **No warranty**: distributed as-is, without any warranty of any kind

---

*TeslaMate Mail — monwifi.fr / Eric B. — 2026*
