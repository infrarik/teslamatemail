# Configuration Telegram pour TeslaMate Mail

## 📋 Présentation

Cette configuration permet d'envoyer des notifications Telegram depuis votre installation TeslaMate. Vous pouvez configurer plusieurs destinataires et tester l'envoi facilement.

## 🚀 Installation

### 1. Créer un bot Telegram

1. Ouvrez Telegram et recherchez **@BotFather**
2. Envoyez la commande `/newbot`
3. Suivez les instructions :
   - Choisissez un nom pour votre bot (ex: "TeslaMate Notifications")
   - Choisissez un username (doit finir par "bot", ex: "teslamate_notif_bot")
4. **Copiez le token** fourni (format: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)
5. Cherchez le Bot que vous venez de créer et envoyez lui la commande de
   démarrage : /start
   Sans cette commande, il ne pourra pas être utilisé !!

### 2. Obtenir votre Chat ID

1. Recherchez **@userinfobot** dans Telegram
2. Démarrez une conversation
3. Le bot vous donnera votre **Chat ID** (ex: `123456789`)
4. Copiez ce numéro

### 3. Configuration dans teslaconf.php

1. Accédez à `teslaconf.php` dans votre navigateur
2. Dans la section **"Bot Telegram"** :
   - Collez le **token** de votre bot
3. Dans la section **"Destinataires Telegram"** :
   - Ajoutez votre nom
   - Collez votre **Chat ID**
   - Cliquez sur "Ajouter un destinataire"
4. Cliquez sur **"TEST TELEGRAM"** pour vérifier
5. Cliquez sur **"SAUVEGARDER"** pour enregistrer

## 📁 Fichiers créés

```
├── teslaconf.php                  (interface de configuration)
├── teslaconfig_handler.php        (traitement de la sauvegarde)
├── test_telegram.php              (test d'envoi)
├── telegram_helper.php            (bibliothèque d'envoi)
├── notification_charging.php      (exemple d'utilisation)
└── cgi-bin/
    ├── setup                      (fichier de configuration)
    └── telegram_users.json        (liste des destinataires)
```

## 🔧 Utilisation dans vos scripts

### Envoi simple

```php
<?php
require_once 'telegram_helper.php';

sendTeslaMateNotification("🚗 Votre Tesla est prête !");
?>
```

### Messages prédéfinis

```php
<?php
require_once 'telegram_helper.php';

// Charge démarrée
$battery = 45;
$msg = TelegramMessages::chargingStarted($battery);
sendTeslaMateNotification($msg);

// Charge terminée
$msg = TelegramMessages::chargingComplete(85);
sendTeslaMateNotification($msg);

// Batterie faible
$msg = TelegramMessages::lowBattery(15);
sendTeslaMateNotification($msg);

// Mise à jour disponible
$msg = TelegramMessages::updateAvailable("2024.2.15");
sendTeslaMateNotification($msg);

// Message personnalisé
$msg = TelegramMessages::custom(
    "Rappel",
    "N'oubliez pas de brancher votre Tesla ce soir !",
    "⚡"
);
sendTeslaMateNotification($msg);
?>
```

### Message personnalisé complet

```php
<?php
require_once 'telegram_helper.php';

$message = "🔋 <b>État de la batterie</b>\n\n";
$message .= "📊 Niveau: 65%\n";
$message .= "⚡ Autonomie: 320 km\n";
$message .= "🌡 Température: 22°C\n";
$message .= "📅 " . date('d/m/Y H:i');

$result = sendTeslaMateNotification($message);

if ($result['success']) {
    echo "✅ Envoyé à {$result['sent']} personne(s)\n";
}
?>
```

## 🎨 Formatage des messages

Telegram supporte le formatage HTML :

```php
$message = "<b>Texte en gras</b>\n";
$message .= "<i>Texte en italique</i>\n";
$message .= "<u>Texte souligné</u>\n";
$message .= "<code>Code</code>\n";
$message .= "<a href='https://tesla.com'>Lien</a>";
```

### Emojis utiles

- 🚗 🔌 ⚡ 🔋 
- ✅ ❌ ⚠️ ℹ️
- 📊 📈 📉 📅 
- 🌡️ 🔥 ❄️ 💧
- 🏁 🚦 🅿️ 🔒
- 📍 🗺️ 🧭 📡

## 👥 Gestion multi-utilisateurs

Vous pouvez ajouter plusieurs destinataires :

1. Chaque utilisateur doit obtenir son **Chat ID** via @userinfobot
2. Ajoutez-les dans la section "Destinataires Telegram"
3. Les notifications seront envoyées à tous les destinataires actifs

### Désactiver temporairement un utilisateur

Pour l'instant, supprimez l'utilisateur via l'interface. Une fonctionnalité de désactivation temporaire pourra être ajoutée ultérieurement.

## 🔍 Dépannage

### Le test échoue

1. **Vérifiez le token** : Il doit être exact (copié depuis @BotFather)
2. **Vérifiez le Chat ID** : Doit être un nombre (pas de texte)
3. **Testez avec @userinfobot** pour confirmer votre Chat ID

### Le bot ne répond pas

1. Assurez-vous d'avoir **démarré** une conversation avec votre bot
2. Recherchez votre bot dans Telegram (par son @username)
3. Cliquez sur "START" ou "DÉMARRER"

### Erreur "Chat not found"

Le Chat ID est incorrect ou l'utilisateur n'a pas démarré de conversation avec le bot.

### Limite de taux (Rate Limit)

Telegram limite à environ 30 messages/seconde par bot. Pour un usage normal avec TeslaMate, cette limite n'est jamais atteinte.

## 📊 Intégration avec MQTT

Pour recevoir des notifications automatiques basées sur les événements TeslaMate, vous devrez créer un listener MQTT qui appelle les scripts de notification.

Exemple avec Node-RED ou un script Python qui écoute les topics MQTT et appelle vos scripts PHP.

## 🔐 Sécurité

- Le token du bot et les Chat IDs sont stockés dans `cgi-bin/setup` et `cgi-bin/telegram_users.json`
- Assurez-vous que ces fichiers ne sont **pas accessibles** via HTTP
- Configurez votre serveur web pour bloquer l'accès au dossier `cgi-bin/`

### Configuration Apache (.htaccess)

```apache
<Directory "/path/to/cgi-bin">
    Require all denied
</Directory>
```

### Configuration Nginx

```nginx
location /cgi-bin/ {
    deny all;
}
```

## 📞 Support

Pour toute question ou problème, vérifiez :
1. Les logs de votre serveur web
2. La console du navigateur (F12) lors des tests
3. La documentation officielle de Telegram Bot API

---

✨ **Bon usage de vos notifications Telegram avec TeslaMate !**
