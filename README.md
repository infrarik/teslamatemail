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

## À propos de la licence

1. **Obligation de Copyleft :** Toute modification doit rester sous licence GPL.
2. **Accès au Code Source :** Obligation de fournir le code source.
3. **Absence de Garantie :** Distribué sans aucune garantie.

================================================================================

# TeslaMate Mail (English Version)

Copyright (C) 2026  monwifi.fr / Eric B.

This program is free software: you can redistribute it and/or modify 
it under the terms of the GNU General Public License (GNU GPL) as published 
by the Free Software Foundation, either version 3 of the License, or 
(at your option) any later version.

**Important Note:** TeslaMate Mail has no official link with the TeslaMate project. It is solely an add-on that uses an already installed TeslaMate instance.

---

## Main Features (EN)

TeslaMate Mail allows you to notify and transmit your vehicle's charging data via three distinct channels:

### 🤖 Telegram Bot (Status Notifications)
The bot sends formatted messages for the following events:
* **Configuration Test:** "🔔 TeslaMate Notification Test ✅ Your Telegram bot is configured correctly! 📅 [Date and Time]"
* **Charging Finished:** "✅ Charging finished 🔋 Battery: [level]% 📅 [Date and Time]"
Other messages are possible, not yet activated.

### 📡 MQTT Integration (Raw Data)
At the end of each charging session, the program publishes a JSON-formatted frame:
* **Frame Example:** `{"id":837,"kwh":10.02,"soc":100,"duration":169}`

### 📧 Email Notifications
* **End of Charge:** Sends a summary email indicating the end of the session and the number of **kWh consumed**.

---

## Prerequisites

To run TeslaMate Mail, you must configure:
1. **TeslaMate Instance:** Access to the Postgres database.
2. **SMTP Server:** Credentials for sending emails.
3. **MQTT Broker:** A server (e.g., Mosquitto) for JSON frames.
4. **Telegram Bot:** An `API Token` and your `Chat ID`.

---

## About the License

1. **Copyleft Obligation:** Modifications must remain under the GPL license.
2. **Access to Source Code:** Obligation to provide the full source code.
3. **No Warranty:** Distributed with no warranty.
