===>>> ENGLISH BELOW

# TeslaMate Mail

Copyright (C) 2026  monwifi.fr / Eric B.

Ce programme est un logiciel libre : vous pouvez le redistribuer et/ou le modifier 
selon les termes de la Licence Publique Générale GNU (GNU GPL) telle que publiée 
par la Free Software Foundation, soit la version 3 de ladite licence, ou 
(à votre discrétion) toute version ultérieure.

**Note importante :** TeslaMate Mail n'a aucun lien officiel avec le projet TeslaMate. C'est uniquement un ajout qui utilise une instance TeslaMate déjà installée.

---

## Fonctions principales (FR)

TeslaMate Mail permet de notifier et de transmettre les données de charge de votre véhicule via trois canaux distincts :

### 🤖 Bot Telegram (Notifications d'état)
Le bot envoie des messages formatés pour les événements suivants :
* **Test de configuration :** "🔔 Test de notification TeslaMate ✅ Votre bot Telegram est configuré correctement ! 📅 [Date et heure]"
* **Charge terminée :** "✅ Charge terminée 🔋 Batterie: [niveau]% 📅 [Date et heure]"
D'autres messages sont prévus, pas actifs pour le moment.

### 📡 Intégration MQTT (Données brutes)
Pour chaque fin de charge, le programme publie une trame au format JSON :
* **Exemple de trame :** `{"id":837,"kwh":10.02,"soc":100,"duration":169}`

### 📧 Notifications par Email
* **Fin de charge :** Envoi d'un e-mail récapitulatif indiquant la fin de la session et le nombre de **kWh consommés**.

---

## Prérequis

Pour faire fonctionner TeslaMate Mail, vous devez configurer :
1. **Instance TeslaMate :** Accès à la base de données Postgres.
2. **Serveur SMTP :** Identifiants pour l'envoi des emails.
3. **Broker MQTT :** Un serveur (ex: Mosquitto) pour les trames JSON.
4. **Bot Telegram :** Un `API Token` et votre `Chat ID`.

---

## Installation
1. copiez les fichiers files.zip, install.sh, installweb.sh, uninstall.sh dans votre
   répertoire /root
2. Depuis root, lancez : bash install.sh
   Répondez aux questions sur l'installation des emails, etc.
3. allez sur http://votre_ip pour configurer teslamate mail, en choisissant français ou anglais
   en haut à droite.

---

## Configuration

La configuration s'effectue en cliquant sur la roue dentelée de l'écran principal. Pensez à bien sauvegarder vos choix.

---

## Mise à jour

Vous pouvez effectuer une mise à jour des fichiers lorsque files.zip a été mis à jour.
Chargez le dans /root puis lancez le script : bash installweb.sh
Ce script procède à l'extraction et installera automatiquement les fichiers à jour, sans jamais toucher à
votre configuration.

---

## Interactions avec Teslamate :

Teslamate Web n'intervient qu'une seule fois sur Teslamate en retirant les commentaires de votre docker-compose.yml, 
à l'exclusion de toute autre modification. Teslamate Web se contente de récupérer les infos de la base de données de
Teslamate, de les interpréter sans jamais les modifier.

---

## Vérifications techniques

En cas de soucis, pensez à vérifier :
1. /var/www/html/cgi-bin/setup : ce fichier contient la configuration de votre Teslamate Mail.
2. /var/www/html/cgi-bin/lastchargeid : ce fichier contient le numéro de la dernière session de charge
   sur votre Teslamate.
3. /var/www/html/cgi-bin/telegram_user.json : ce fichier contient le ou les destinataires Telegram au format JSON
4. abonnez vous avec mosquitto_sub au topic d'envoi configuré sur votre Teslamate Mail, pratique pour vérifier le
   bon fonctionnement.

---

## Parrainage Tesla

Si ce développement GNU gratuit vous plait, n'hésitez pas à utiliser le lien de parraiange Tesla figurant en bas de la page principale,
c'est toujours un coup de pouce utile pour poursuivre et faire évoluer. Un grand merci par avance !


===============================================================================


## À propos de la licence

1. **Obligation de Copyleft :** Toute modification doit rester sous licence GPL.
2. **Accès au Code Source :** Obligation de fournir le code source.
3. **Absence de Garantie :** Distribué sans aucune garantie.




## TeslaMate Mail
Copyright (C) 2026 monwifi.fr / Eric B.

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License (GNU GPL) as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

Important Note: TeslaMate Mail has no official connection with the TeslaMate project. It is strictly an add-on that utilizes an existing TeslaMate installation.

## Main Features (EN)
TeslaMate Mail allows you to notify and transmit your vehicle's charging data via three distinct channels:

🤖 Telegram Bot (Status Notifications)
The bot sends formatted messages for the following events:

Configuration Test: "🔔 TeslaMate notification test ✅ Your Telegram bot is configured correctly! 📅 [Date and time]"

Charge Completed: "✅ Charge completed 🔋 Battery: [level]% 📅 [Date and time]" Other messages are planned but not currently active.

📡 MQTT Integration (Raw Data)
At the end of each charging session, the program publishes a frame in JSON format:

Frame Example: {"id":837,"kwh":10.02,"soc":100,"duration":169}

📧 Email Notifications
End of Charge: Sends a summary email indicating the end of the session and the number of kWh consumed.

## Prerequisites
To run TeslaMate Mail, you must configure:
1. TeslaMate Instance: Access to the Postgres database.
2. SMTP Server: Credentials for sending emails.
3. MQTT Broker: A server (e.g., Mosquitto) for JSON frames.
4. Telegram Bot: An API Token and your Chat ID.

## Installation
1. Copy the files files.zip, install.sh, installweb.sh, and uninstall.sh into your /root directory.
2. From root, run: bash install.sh Answer the questions regarding email installation, etc.
3. Go to http://your_ip to configure TeslaMate Mail, choosing French or English at the top right.

## Configuration
Configuration is done by clicking on the gear icon on the main screen. Remember to save your settings.

## Update
You can update the files whenever files.zip has been updated. Upload it to /root then run the script: bash installweb.sh This script will extract and automatically install the updated files without ever affecting your configuration.

## Interactions with TeslaMate
TeslaMate Web only interacts with TeslaMate once by removing comments from your docker-compose.yml, excluding any other modifications. TeslaMate Web simply retrieves information from the TeslaMate database and interprets it without ever modifying it.

## Technical Checks
In case of issues, please check:
1. /var/www/html/cgi-bin/setup: This file contains your TeslaMate Mail configuration.
2. /var/www/html/cgi-bin/lastchargeid: This file contains the ID number of the last charging session on your TeslaMate.
3. /var/www/html/cgi-bin/telegram_user.json: This file contains the Telegram recipient(s) in JSON format.

Subscribe with mosquitto_sub to the sending topic configured in your TeslaMate Mail; this is useful for verifying proper operation.

## Tesla Referral
If you enjoy this free GNU development, feel free to use the Tesla referral link at the bottom of the main page. It is always a helpful boost to continue and evolve the project. A huge thank you in advance!

===============================================================================

About the License
Copyleft Obligation: Any modification must remain under the GPL license.

Source Code Access: Obligation to provide the source code.

No Warranty: Distributed without any warranty.
