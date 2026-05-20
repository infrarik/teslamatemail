# teslamate mail

**copyright (c) 2026 monwifi.fr / eric b.** licence gnu gpl v3 — logiciel libre, sans garantie.

🇬🇧 **[english version — click here](#english-version)**

> **note :** teslamate mail n'a aucun lien officiel avec le projet teslamate. c'est uniquement un module complémentaire qui exploite une instance teslamate déjà installée, sans jamais modifier sa base de données.

---

## 🆕 nouveautés récentes

- **plage de dates** : sélection d'une période (ex. du 15 au 20) directement depuis l'interface. la sidebar affiche un résumé par jour cliquable, avec le total km et le nombre de trajets. un bouton **période entière** fusionne tous les trajets sur la carte.
- **navigation par flèches** : boutons ◀ ▶ pour avancer ou reculer d'un jour sans ouvrir le sélecteur de date.
- **statistiques de charge sur la période** : dans le résumé de plage, affichage du total kwh rechargés et du nombre de charges.
- **graphique batterie** : courbe % batterie en fonction du temps, affichée en bas de carte sur un trajet individuel ou une journée entière. le survol/touch déplace le marqueur voiture sur la carte.
- **barre de lecture déplaçable** : la barre play/slider d'animation du trajet est désormais glissable librement sur la carte (souris et touch).
- **légende des vitesses déplaçable** : le cadre "vitesses (km/h)" est lui aussi glissable librement sur la carte.
- **compatibilité navigateur tesla** : détection fiable du navigateur embarqué tesla (chrome linux x86_64, touch, sans android). le numpad tactile de saisie de date est forcé sur tesla même si le navigateur supporte `input[type=date]`. les boutons du numpad répondent au touch avec `touchend`.
- **température batterie sur les charges** : les infobulles ⚡ affichent la température batterie début → fin (si disponible dans votre version de teslamate).
- **chauffe batterie sur les charges** : affichage on/off du chauffage batterie pendant la charge (lu depuis la table `positions` via `position_id`).
- **prix du kwh par lieu** : possibilité de définir un prix du kwh différent pour chaque lieu (geofence). le prix est mémorisé automatiquement et appliqué dans tous les calculs, rapports et exports.
- **gestion des majorations de prix** : possibilité d'ajouter un pourcentage (%) appliqué directement sur les prix du kwh (frais de session, taxes, etc.), avec option pour l'afficher ou le masquer dans l'interface de configuration.
- **mémorisation du prix du kwh** : le prix saisi est automatiquement sauvegardé et pré-rempli à chaque visite.
- **rapports email enrichis** : l'envoi par email produit un rapport html mis en page avec un **pdf en pièce jointe**, généré sans aucune dépendance externe.
- **graphique de consommation dans les pdf** : les rapports pdf incluent un graphique à barres de la consommation journalière.
- **consommation aux 100 km** : affichée dans les résultats, les pdf, les emails et les rapports automatiques.
- **rapport hebdomadaire automatique** : envoi chaque lundi à 4h du matin de la semaine précédente, activable/désactivable depuis l'interface.
- **niveau de batterie sur les charges** : les marqueurs ⚡ affichent le niveau de batterie au début et à la fin de chaque charge (carte 2d et 3d).
- **niveau de batterie en temps réel** : au survol de la trace sur la carte 2d, le niveau de batterie s'affiche dans la barre d'information.
- **marqueurs altitude max/min** : cercle rouge **+** au point le plus haut, cercle bleu **−** au point le plus bas, sur les deux cartes.
- **analyse de conduite** : panneau flottant et déplaçable avec score éco (0–100), accélérations/freinages brusques, % temps haute vitesse et consommation estimée.
- **contrôles sidebar repliables** : une flèche permet de masquer le sélecteur de véhicule, la date et les boutons pour maximiser la liste des trajets.
- **sécurité pin renforcée** : vérification côté serveur, invisible dans la source. blocage 5 minutes après 3 tentatives incorrectes avec compte à rebours.
- **carte des altitudes (3d) enrichie** : infobulles complètes sur tous les marqueurs avec heure, altitude, température et ville (géocodage automatique nominatim).
- **interface mobile améliorée** : panneau d'informations rétractable d'un bouton.
- **nouveau processus de mise à jour fluide** : l'écran principal vérifie la présence d'une mise à jour. si disponible, le bouton **télécharger** rapatrie systématiquement les fichiers `files.zip` et `installweb.sh` dans `/tmp`. l'interface demande ensuite par une confirmation visuelle claire s'il faut procéder à l'installation immédiate ou non (exécution du script à la demande depuis le web).

---

## sommaire

- [fonctionnalités](#fonctionnalités)
- [prérequis](#prérequis)
- [installation](#installation)
- [mise à jour](#mise-à-jour)
- [configuration](#configuration)
- [vérifications techniques](#vérifications-techniques)
- [interactions avec teslamate](#interactions-avec-teslamate)
- [parrainage tesla](#parrainage-tesla)
- [remerciements](#remerciements)

---

## fonctionnalités

### 🗺️ carte des trajets (2d)

- visualisation des trajets d'une journée complète, d'un trajet individuel ou d'une **plage de dates**
- navigation jour par jour avec les boutons **◀ ▶**
- mode **plage de dates** : résumé par jour dans la sidebar, bouton **période entière** pour fusionner tous les trajets
- statistiques de charge sur la période : total kwh rechargés, nombre de charges
- tracé coloré selon la vitesse (palette de couleurs de vert à rouge foncé)
- **légende des vitesses déplaçable** librement sur la carte
- affichage des températures, altitudes, dénivelé cumulé positif, vitesse max
- **marqueurs interactifs** sur la carte :
  - 🟢 **départ (d)** et 🔴 **arrivée (a)** avec niveau de batterie
  - 🔵 **pauses parking (p)** : durée, altitude, température extérieure
  - 🟡 **charges (⚡)** : kwh ajoutés/consommés, durée, niveau batterie début→fin, température batterie début→fin, chauffe batterie on/off
- infobulle au survol/tap de la trace : heure, altitude, température, vitesse, niveau de batterie
- **graphique batterie** : courbe % en bas de carte (trajet individuel et journée entière), tooltip au survol/touch avec synchronisation du marqueur voiture
- **barre de lecture déplaçable** : play/pause, vitesse ×1/×5/×10/×20, slider, export vidéo
- capture d'écran de la carte 2d
- export kml pour google earth
- sélecteur de fond de carte : plan / satellite / mixte
- interface responsive mobile/tablette avec panneau rétractable

### 🏔️ carte des altitudes (3d)

- tracé 3d interactif (plotly) coloré selon l'altitude (palette viridis)
- **marqueurs annotés avec infobulles complètes** :
  - 🟢 **départ (d)** : heure, altitude, température, ville (nominatim)
  - 🔴 **arrivée (a)** : heure, altitude, température, ville (nominatim)
  - 🔵 **pauses parking (p)** : durée, heure, altitude, température, ville
  - 🟡 **charges (⚡)** : kwh ajoutés, durée, heure, altitude, température, ville, température batterie, chauffe batterie
- **infobulles sur chaque point de la trace** : heure, altitude, température, vitesse, niveau de batterie
- géocodage inverse asynchrone via **nominatim / openstreetmap** (sans clé api)
- les villes apparaissent progressivement sur les marqueurs au chargement

### 📊 calculateur de consommation

- calcul sur période personnalisée ou raccourcis : cette semaine, semaine dernière, ce mois, mois dernier, cette année, année précédente
- résultats : distance, nombre de charges, énergie ajoutée, énergie consommée, coût total, **consommation moyenne aux 100 km**
- filtrage par véhicule et par geofence
- mode **v2l** (vehicle-to-load)
- export **pdf** (généré en php pur, sans dépendance externe) avec graphique de consommation journalière
- export **csv**
- **envoi par email** : rapport html mis en page + pdf en pièce jointe
- prise en compte des pourcentages de majoration applicables aux tarifs du kwh

### 📧 rapport hebdomadaire automatique

- envoi automatique chaque **lundi à 4h du matin** (cron)
- couvre la **semaine précédente** (lundi → dimanche)
- contenu : kpis (distance, charges, kwh ajoutés, kwh consommés, coût, conso/100km) + tableau détaillé + graphique
- **pdf en pièce jointe** généré en php pur (aucune dépendance externe)
- activation / désactivation via un **toggle dans teslacalcul.php**
- bouton **"envoi rapport semaine"** pour envoyer la semaine en cours à la demande

### 🤖 bot telegram

- notification de fin de charge : niveau de batterie, date/heure
- test de configuration
- d'autres événements sont prévus

### 📡 intégration mqtt

- publication json à chaque fin de charge :  
  `{"id":837,"kwh":10.02,"soc":100,"duration":169}`

### 🔧 outils de diagnostic

- **table.php** : explorateur de la base de données teslamate — liste toutes les tables avec colonnes, types, nullable, valeur par défaut. recherche en temps réel par nom de table ou de colonne.
- **test.php** : détection du navigateur tesla — 18 méthodes de détection php et javascript, verdict global.

### 📱 interface

- application **pwa** installable sur mobile (icône, service worker)
- accès protégé par **code pin** à 4 chiffres (configurable dans `setup`)
- interface bilingue **français / anglais**
- **compatibilité tesla** : numpad tactile forcé pour la saisie de dates, détection fiable du navigateur embarqué

### 🔄 mises à jour gérées depuis l'interface

- l'écran principal (`tesla.php`) vérifie silencieusement la disponibilité d'une mise à jour via l'api github (sans télécharger le zip)
- un bandeau jaune **🔄 mise à jour disponible** s'affiche avec un bouton **télécharger**
- au clic, `files.zip` et `installweb.sh` sont **dans tous les cas** téléchargés automatiquement dans `/tmp`
- une fois le téléchargement terminé, une fenêtre surgissante vous demande si vous souhaitez valider l'installation immédiate. si vous acceptez, l'installation se lance de façon synchrone en tâche de fond. sinon, les fichiers restent prêts dans `/tmp`.

---

## prérequis

| élément | description |
|---|---|
| instance teslamate | accès à la base de données postgresql |
| serveur smtp | identifiants pour l'envoi des emails |
| broker mqtt | serveur mosquitto ou équivalent |
| bot telegram | api token + chat id |
| serveur linux | apache + php + accès root |

---

## installation

```bash
# 1. copiez dans /root
files.zip  install.sh  installweb.sh

# 2. lancez l'installation complète
bash install.sh
