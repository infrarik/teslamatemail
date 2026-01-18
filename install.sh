#!/bin/bash

################################################################################
# Script d'installation COMPLET TeslaMate Mail
# Version 3.0 - Installation automatisée complète
#
# Ce script fait TOUT :
# - Installation des dépendances
# - Configuration Postfix (SMTP)
# - Configuration Apache/PHP
# - Déploiement des fichiers
# - Configuration Docker (si nécessaire)
# - Configuration Cron
# - Configuration Logrotate
################################################################################

set -e

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ZIP_FILE="$SCRIPT_DIR/files.zip"

clear
echo -e "${BLUE}╔═══════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║     Installation TeslaMate Mail v3.0                  ║${NC}"
echo -e "${BLUE}║     Copyright © 2026 monserveur.fr / Eric BERTREM        ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""

# Vérifier si root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}✗ Ce script doit être exécuté en tant que root${NC}"
    echo "Utilisez: sudo bash install.sh"
    exit 1
fi

# Vérifier la présence du fichier ZIP
if [ ! -f "$ZIP_FILE" ]; then
    echo -e "${RED}✗ Erreur: Le fichier files.zip est introuvable !${NC}"
    echo -e "Assurez-vous que ${YELLOW}files.zip${NC} est dans le même répertoire que ce script."
    exit 1
fi

echo -e "${CYAN}📦 Fichier files.zip détecté${NC}"
echo ""

# ============================================================================
# COLLECTE DES INFORMATIONS UTILISATEUR
# ============================================================================
echo -e "${MAGENTA}╔═══════════════════════════════════════════════════════╗${NC}"
echo -e "${MAGENTA}║          CONFIGURATION DU SERVEUR EMAIL                ║${NC}"
echo -e "${MAGENTA}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""

read -p "Hostname du serveur (ex: teslamate.monserveur.fr) : " HOSTNAME
HOSTNAME=${HOSTNAME:-teslamate.local}

read -p "Serveur SMTP (ex: mail.monserveur.fr) : " SMTP_HOST
if [ -z "$SMTP_HOST" ]; then
    echo -e "${RED}✗ Le serveur SMTP est obligatoire${NC}"
    exit 1
fi

read -p "Port SMTP (465 pour SMTPS, 587 pour STARTTLS) [465] : " SMTP_PORT
SMTP_PORT=${SMTP_PORT:-465}

echo ""
echo "Type de sécurité :"
echo "  1) SMTPS (Port 465 - TLS Wrapper)"
echo "  2) STARTTLS (Port 587 - TLS opportuniste)"
read -p "Choix [1] : " SECURITY_TYPE
SECURITY_TYPE=${SECURITY_TYPE:-1}

read -p "Login SMTP (ex: alerte@monserveur.fr) : " SMTP_USER
if [ -z "$SMTP_USER" ]; then
    echo -e "${RED}✗ Le login SMTP est obligatoire${NC}"
    exit 1
fi

read -sp "Mot de passe SMTP : " SMTP_PASS
echo ""
if [ -z "$SMTP_PASS" ]; then
    echo -e "${RED}✗ Le mot de passe SMTP est obligatoire${NC}"
    exit 1
fi

read -p "Email expéditeur (ex: noreply@monserveur.fr) : " SMTP_FROM
SMTP_FROM=${SMTP_FROM:-noreply@$HOSTNAME}

read -p "Email destinataire par défaut : " DEFAULT_EMAIL
DEFAULT_EMAIL=${DEFAULT_EMAIL:-admin@$HOSTNAME}

echo ""
echo -e "${CYAN}Configuration email enregistrée${NC}"
echo ""

# ============================================================================
# ÉTAPE 1 : Installation des dépendances
# ============================================================================
echo -e "${GREEN}[1/10] Installation des dépendances système${NC}"
echo -e "${YELLOW}→ Mise à jour des paquets...${NC}"

export DEBIAN_FRONTEND=noninteractive
apt update -qq

echo -e "${YELLOW}→ Installation Apache, PHP, PostgreSQL, outils...${NC}"
apt install -y \
    apache2 \
    php \
    libapache2-mod-php \
    php-pgsql \
    php-json \
    php-mbstring \
    postgresql-client \
    unzip \
    zip \
    curl \
    wget \
    logrotate \
    net-tools

echo -e "${YELLOW}→ Installation Postfix, mailutils...${NC}"
debconf-set-selections <<< "postfix postfix/mailname string $HOSTNAME"
debconf-set-selections <<< "postfix postfix/main_mailer_type string 'Internet Site'"

apt install -y \
    postfix \
    mailutils \
    libsasl2-2 \
    libsasl2-modules \
    ca-certificates

echo -e "${YELLOW}→ Installation Mosquitto client (MQTT)...${NC}"
apt install -y mosquitto-clients

echo -e "${GREEN}✓ Dépendances installées${NC}"
echo ""

# ============================================================================
# ÉTAPE 2 : Configuration de Postfix
# ============================================================================
echo -e "${GREEN}[2/10] Configuration du serveur mail Postfix${NC}"

# Backup config originale
if [ -f /etc/postfix/main.cf ]; then
    cp /etc/postfix/main.cf /etc/postfix/main.cf.backup.$(date +%Y%m%d-%H%M%S)
fi

# Extraire le domaine
DOMAIN=$(echo "$SMTP_FROM" | cut -d'@' -f2)

# Configuration Postfix selon le type de sécurité
if [ "$SECURITY_TYPE" = "1" ]; then
    # SMTPS (Port 465)
    cat > /etc/postfix/main.cf <<EOF
# Configuration Postfix pour TeslaMate Mail
smtpd_banner = \$myhostname ESMTP
biff = no
append_dot_mydomain = no
readme_directory = no
compatibility_level = 2

# Nom du serveur
myhostname = $HOSTNAME
mydomain = $DOMAIN
myorigin = \$mydomain

# Destinations
mydestination = \$myhostname, localhost.localdomain, localhost
relayhost = [$SMTP_HOST]:$SMTP_PORT

# Réseaux autorisés
mynetworks = 127.0.0.0/8 [::ffff:127.0.0.0]/104 [::1]/128 192.168.0.0/16
mailbox_size_limit = 0
recipient_delimiter = +
inet_interfaces = all
inet_protocols = ipv4

# Authentification SASL
smtp_sasl_auth_enable = yes
smtp_sasl_password_maps = hash:/etc/postfix/sasl_passwd
smtp_sasl_security_options = noanonymous
smtp_sasl_mechanism_filter = plain, login

# TLS/SSL pour port 465 (SMTPS - TLS Wrapper)
smtp_tls_security_level = encrypt
smtp_tls_wrappermode = yes
smtp_tls_CAfile = /etc/ssl/certs/ca-certificates.crt
smtp_tls_session_cache_database = btree:\${data_directory}/smtp_scache

# Réécriture d'adresse
smtp_generic_maps = hash:/etc/postfix/generic
sender_canonical_maps = hash:/etc/postfix/sender_canonical
EOF
else
    # STARTTLS (Port 587)
    cat > /etc/postfix/main.cf <<EOF
# Configuration Postfix pour TeslaMate Mail
smtpd_banner = \$myhostname ESMTP
biff = no
append_dot_mydomain = no
readme_directory = no
compatibility_level = 2

# Nom du serveur
myhostname = $HOSTNAME
mydomain = $DOMAIN
myorigin = \$mydomain

# Destinations
mydestination = \$myhostname, localhost.localdomain, localhost
relayhost = [$SMTP_HOST]:$SMTP_PORT

# Réseaux autorisés
mynetworks = 127.0.0.0/8 [::ffff:127.0.0.0]/104 [::1]/128 192.168.0.0/16
mailbox_size_limit = 0
recipient_delimiter = +
inet_interfaces = all
inet_protocols = ipv4

# Authentification SASL
smtp_sasl_auth_enable = yes
smtp_sasl_password_maps = hash:/etc/postfix/sasl_passwd
smtp_sasl_security_options = noanonymous
smtp_sasl_mechanism_filter = plain, login

# TLS/SSL pour port 587 (STARTTLS)
smtp_use_tls = yes
smtp_tls_security_level = encrypt
smtp_tls_note_starttls_offer = yes
smtp_tls_CAfile = /etc/ssl/certs/ca-certificates.crt
smtp_tls_session_cache_database = btree:\${data_directory}/smtp_scache

# Réécriture d'adresse
smtp_generic_maps = hash:/etc/postfix/generic
sender_canonical_maps = hash:/etc/postfix/sender_canonical
EOF
fi

# Fichier de mots de passe SMTP
cat > /etc/postfix/sasl_passwd <<EOF
[$SMTP_HOST]:$SMTP_PORT $SMTP_USER:$SMTP_PASS
EOF
chmod 600 /etc/postfix/sasl_passwd
postmap /etc/postfix/sasl_passwd

# Réécriture d'adresse générique
cat > /etc/postfix/generic <<EOF
root@$HOSTNAME $SMTP_FROM
@$HOSTNAME $SMTP_FROM
root $SMTP_FROM
EOF
chmod 600 /etc/postfix/generic
postmap /etc/postfix/generic

# Canonical mapping
cat > /etc/postfix/sender_canonical <<EOF
root $SMTP_FROM
@$HOSTNAME $SMTP_FROM
EOF
chmod 600 /etc/postfix/sender_canonical
postmap /etc/postfix/sender_canonical

# Configuration des aliases
cat > /etc/aliases <<EOF
mailer-daemon: postmaster
postmaster: root
nobody: root
hostmaster: root
usenet: root
news: root
webmaster: root
www: root
ftp: root
abuse: root
noc: root
root: $DEFAULT_EMAIL
EOF
newaliases

# Configuration du hostname
hostnamectl set-hostname $HOSTNAME 2>/dev/null || true
if ! grep -q "$HOSTNAME" /etc/hosts; then
    echo "127.0.0.1 $HOSTNAME" >> /etc/hosts
fi

# Redémarrer Postfix
systemctl restart postfix
systemctl enable postfix >/dev/null 2>&1

echo -e "${GREEN}✓ Postfix configuré${NC}"
echo ""

# ============================================================================
# ÉTAPE 3 : Configuration d'Apache
# ============================================================================
echo -e "${GREEN}[3/10] Configuration d'Apache${NC}"

# Activer les modules PHP
a2enmod php* 2>/dev/null || true

# Supprimer l'index.html par défaut
echo -e "${YELLOW}→ Suppression de l'index.html par défaut...${NC}"
rm -f /var/www/html/index.html

# Redémarrer Apache
systemctl restart apache2
systemctl enable apache2 >/dev/null 2>&1

echo -e "${GREEN}✓ Apache configuré${NC}"
echo ""

# ============================================================================
# ÉTAPE 4 : Extraction de l'archive
# ============================================================================
echo -e "${GREEN}[4/10] Extraction de l'archive files.zip${NC}"

TEMP_EXTRACT="/tmp/teslamate_extract_$$"
mkdir -p "$TEMP_EXTRACT"

unzip -q "$ZIP_FILE" -d "$TEMP_EXTRACT"

echo -e "${GREEN}✓ Archive extraite${NC}"
echo ""

# ============================================================================
# ÉTAPE 5 : Déploiement des fichiers web
# ============================================================================
echo -e "${GREEN}[5/10] Déploiement des fichiers web${NC}"

WWW_SOURCE="$TEMP_EXTRACT/www"
WWW_DEST="/var/www/html"

if [ -d "$WWW_SOURCE" ]; then
    echo -e "${YELLOW}→ Copie des fichiers HTML/PHP...${NC}"
    # Copier tous les fichiers HTML et PHP
    find "$WWW_SOURCE" -maxdepth 1 -type f \( -name "*.html" -o -name "*.php" -o -name "*.png" -o -name "*.jpg" -o -name "*.gif" \) -exec cp {} "$WWW_DEST/" \;

    echo -e "${YELLOW}→ Configuration du répertoire cgi-bin...${NC}"
    # Créer le répertoire cgi-bin
    mkdir -p "$WWW_DEST/cgi-bin"

    # Copier le contenu de cgi-bin
    if [ -d "$WWW_SOURCE/cgi-bin" ]; then
        cp -r "$WWW_SOURCE/cgi-bin"/* "$WWW_DEST/cgi-bin/" 2>/dev/null || true
        echo -e "${CYAN}  ✓ Fichiers cgi-bin copiés${NC}"

        # Lister ce qui a été copié
        if [ -f "$WWW_DEST/cgi-bin/setup" ]; then
            echo -e "${CYAN}    • setup${NC}"
        fi
        if [ -f "$WWW_DEST/cgi-bin/lastchargeid" ]; then
            echo -e "${CYAN}    • lastchargeid${NC}"
        fi
    else
        echo -e "${YELLOW}  ⚠ Pas de répertoire cgi-bin dans l'archive, création manuelle${NC}"
    fi

    echo -e "${YELLOW}→ Mise à jour du fichier setup...${NC}"
    # Mettre à jour le fichier setup avec la config email
    cat > "$WWW_DEST/cgi-bin/setup" <<EOF
### TeslaMate Mail Config - Initialized $(date '+%Y-%m-%d %H:%M:%S') ###
mqtt_host=
mqtt_port=1883
mqtt_user=
mqtt_pass=
mqtt_topic=teslamate/cars/1
notification_email=$DEFAULT_EMAIL
docker_path=/opt/teslamate/docker-compose.yml
mqtt_enabled=False
email_enabled=False
EOF

    # Créer lastchargeid s'il n'existe pas
    if [ ! -f "$WWW_DEST/cgi-bin/lastchargeid" ]; then
        echo "0" > "$WWW_DEST/cgi-bin/lastchargeid"
        echo -e "${CYAN}  ✓ lastchargeid créé${NC}"
    fi

    echo -e "${YELLOW}→ Configuration des permissions...${NC}"
    # Permissions
    chown -R www-data:www-data "$WWW_DEST/cgi-bin"
    chmod 755 "$WWW_DEST/cgi-bin"
    chmod 666 "$WWW_DEST/cgi-bin/setup" "$WWW_DEST/cgi-bin/lastchargeid"
    chmod 644 "$WWW_DEST"/*.html "$WWW_DEST"/*.php 2>/dev/null || true

    echo -e "${GREEN}✓ Fichiers web déployés${NC}"
    echo -e "${GREEN}✓ Répertoire cgi-bin configuré avec permissions${NC}"
else
    echo -e "${YELLOW}⚠ Aucun répertoire 'www' trouvé dans l'archive${NC}"
fi
echo ""

# ============================================================================
# ÉTAPE 6 : Déploiement des scripts root
# ============================================================================
echo -e "${GREEN}[6/10] Déploiement des scripts dans /root${NC}"

ROOT_SOURCE="$TEMP_EXTRACT/root"
ROOT_DEST="/root"

if [ -d "$ROOT_SOURCE" ]; then
    # Copier tous les fichiers .sh
    find "$ROOT_SOURCE" -maxdepth 1 -type f -name "*.sh" -exec cp {} "$ROOT_DEST/" \;

    # Rendre les scripts exécutables
    chmod +x "$ROOT_DEST"/*.sh 2>/dev/null || true

    echo -e "${GREEN}✓ Scripts déployés dans /root${NC}"
else
    echo -e "${YELLOW}⚠ Aucun répertoire 'root' trouvé dans l'archive${NC}"
fi
echo ""

# ============================================================================
# ÉTAPE 7 : Configuration Docker (si TeslaMate est installé)
# ============================================================================
echo -e "${GREEN}[7/10] Recherche et configuration de Docker${NC}"

DOCKER_COMPOSE_PATH=""

# Chercher docker-compose.yml
for path in "/opt/teslamate/docker-compose.yml" "/home/*/teslamate/docker-compose.yml" "$HOME/teslamate/docker-compose.yml"; do
    if [ -f "$path" ]; then
        DOCKER_COMPOSE_PATH="$path"
        break
    fi
done

if [ -z "$DOCKER_COMPOSE_PATH" ]; then
    echo -e "${YELLOW}⚠ Docker-compose.yml non trouvé, recherche manuelle...${NC}"
    read -p "Chemin vers docker-compose.yml (ou ENTER pour ignorer) : " USER_PATH
    if [ -n "$USER_PATH" ] && [ -f "$USER_PATH" ]; then
        DOCKER_COMPOSE_PATH="$USER_PATH"
    fi
fi

if [ -n "$DOCKER_COMPOSE_PATH" ]; then
    echo -e "${CYAN}→ Docker-compose trouvé : $DOCKER_COMPOSE_PATH${NC}"

    # Backup
    cp "$DOCKER_COMPOSE_PATH" "${DOCKER_COMPOSE_PATH}.backup.$(date +%Y%m%d-%H%M%S)"

    # --- NETTOYAGE DES COMMENTAIRES ---
    echo -e "${YELLOW}→ Nettoyage des commentaires dans le docker-compose...${NC}"
    sed -i 's/[[:space:]]*#.*//' "$DOCKER_COMPOSE_PATH"
    echo -e "${GREEN}✓ Commentaires supprimés${NC}"

    # Vérifier si PostgreSQL est exposé
    if ! grep -q "5432:5432" "$DOCKER_COMPOSE_PATH"; then
        echo -e "${YELLOW}→ PostgreSQL n'est pas exposé, modification nécessaire${NC}"
        echo -e "${YELLOW}⚠ ATTENTION: Vous devrez ajouter manuellement dans docker-compose.yml :${NC}"
        echo -e "${CYAN}  database:${NC}"
        echo -e "${CYAN}    ports:${NC}"
        echo -e "${CYAN}      - \"5432:5432\"${NC}"
        echo ""
        read -p "Voulez-vous que je tente d'ajouter automatiquement ? (o/N) : " -n 1 -r
        echo ""

        if [[ $REPLY =~ ^[Oo]$ ]]; then
            # Arrêter Docker Compose
            DOCKER_DIR=$(dirname "$DOCKER_COMPOSE_PATH")
            cd "$DOCKER_DIR"

            echo -e "${YELLOW}→ Arrêt des conteneurs Docker...${NC}"
            docker-compose down 2>/dev/null || docker compose down 2>/dev/null || true

            # Ajouter le port mapping de manière plus robuste
            # Chercher la ligne "database:" et ajouter ports après
            if grep -q "database:" "$DOCKER_COMPOSE_PATH"; then
                # Créer une copie temporaire
                TEMP_FILE=$(mktemp)
                awk '/database:/ {print; print "    ports:"; print "      - \"5432:5432\""; next} 1' "$DOCKER_COMPOSE_PATH" > "$TEMP_FILE"
                mv "$TEMP_FILE" "$DOCKER_COMPOSE_PATH"

                echo -e "${GREEN}✓ Port mapping ajouté${NC}"
            else
                echo -e "${RED}✗ Impossible de trouver 'database:' dans le fichier${NC}"
            fi

            # Redémarrer Docker Compose
            echo -e "${YELLOW}→ Redémarrage des conteneurs...${NC}"
            docker-compose up -d 2>/dev/null || docker compose up -d 2>/dev/null || true

            echo -e "${GREEN}✓ Docker redémarré${NC}"
        fi
    else
        echo -e "${GREEN}✓ PostgreSQL déjà exposé sur le port 5432${NC}"
    fi

    # Mettre à jour le chemin dans setup
    sed -i "s|docker_path=.*|docker_path=$DOCKER_COMPOSE_PATH|" "$WWW_DEST/cgi-bin/setup"
else
    echo -e "${YELLOW}⚠ Docker-compose.yml non trouvé${NC}"
    echo -e "${YELLOW}  Vous devrez configurer le chemin manuellement dans teslaconf.php${NC}"
fi
echo ""

# ============================================================================
# ÉTAPE 8 : Configuration du Cron
# ============================================================================
echo -e "${GREEN}[8/10] Configuration de la tâche planifiée${NC}"

read -p "Configurer le cron pour vérifier les charges automatiquement ? (O/n) : " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Nn]$ ]]; then
    CRON_INTERVAL="5"
    read -p "Intervalle en minutes (défaut: 5) : " USER_INTERVAL
    [ -n "$USER_INTERVAL" ] && CRON_INTERVAL="$USER_INTERVAL"

    CRON_LINE="*/$CRON_INTERVAL * * * * /root/teslacharge.sh >> /var/log/teslacharge.log 2>&1"

    # Ajouter au crontab si pas déjà présent
    (crontab -l 2>/dev/null | grep -v "teslacharge.sh"; echo "$CRON_LINE") | crontab -

    # Créer le fichier de log
    touch /var/log/teslacharge.log
    chmod 644 /var/log/teslacharge.log

    echo -e "${GREEN}✓ Cron configuré : vérification toutes les $CRON_INTERVAL minutes${NC}"
else
    echo -e "${CYAN}ℹ Configuration cron ignorée${NC}"
fi
echo ""

# ============================================================================
# ÉTAPE 9 : Configuration Logrotate
# ============================================================================
echo -e "${GREEN}[9/10] Configuration de Logrotate${NC}"

cat > /etc/logrotate.d/teslacharge <<'EOF'
/var/log/teslacharge.log {
    weekly
    rotate 4
    compress
    delaycompress
    missingok
    notifempty
    create 0640 root root
}
EOF

echo -e "${GREEN}✓ Logrotate configuré${NC}"
echo ""

# ============================================================================
# ÉTAPE 10 : Test de configuration email
# ============================================================================
echo -e "${GREEN}[10/10] Test de configuration email${NC}"

read -p "Envoyer un email de test à $DEFAULT_EMAIL ? (O/n) : " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Nn]$ ]]; then
    echo "Test d'installation TeslaMate Mail - $(date)" | mail -s "Test TeslaMate Mail" -r "$SMTP_FROM" "$DEFAULT_EMAIL" 2>&1

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Email de test envoyé${NC}"
    else
        echo -e "${YELLOW}⚠ Erreur lors de l'envoi (vérifiez /var/log/mail.log)${NC}"
    fi
fi
echo ""

# ============================================================================
# Nettoyage
# ============================================================================
rm -rf "$TEMP_EXTRACT"

# ============================================================================
# RÉSUMÉ FINAL
# ============================================================================
clear
echo -e "${BLUE}╔═══════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║      INSTALLATION TERMINÉE AVEC SUCCÈS ! 🎉           ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${CYAN}🔧 Configuration Email :${NC}"
echo -e "   Hostname         : ${YELLOW}$HOSTNAME${NC}"
echo -e "   Serveur SMTP     : ${YELLOW}$SMTP_HOST:$SMTP_PORT${NC}"
echo -e "   Sécurité         : ${YELLOW}$([ "$SECURITY_TYPE" = "1" ] && echo "SMTPS (TLS Wrapper)" || echo "STARTTLS")${NC}"
echo -e "   Login            : ${YELLOW}$SMTP_USER${NC}"
echo -e "   Expéditeur       : ${YELLOW}$SMTP_FROM${NC}"
echo -e "   Destinataire     : ${YELLOW}$DEFAULT_EMAIL${NC}"
echo ""
echo -e "${CYAN}📁 Fichiers installés :${NC}"
echo -e "   /var/www/html/              → Fichiers web"
echo -e "   /var/www/html/cgi-bin/      → Configuration"
echo -e "   /root/teslacharge.sh        → Script monitoring"
echo -e "   /var/log/teslacharge.log    → Logs"
echo ""
echo -e "${CYAN}🌐 Accès web :${NC}"
SERVER_IP=$(hostname -I | awk '{print $1}')
echo -e "   Dashboard   : ${GREEN}http://$SERVER_IP/tesla.php${NC}"
echo -e "   Config      : ${GREEN}http://$SERVER_IP/teslaconf.php${NC}"
echo -e "   Accueil     : ${GREEN}http://$SERVER_IP/index.php${NC}"
echo ""
echo -e "${CYAN}⚙️ Prochaines étapes :${NC}"
echo -e "   ${YELLOW}1.${NC} Accédez à l'interface web"
echo -e "   ${YELLOW}2.${NC} Configurez MQTT dans teslaconf.php (optionnel)"
echo -e "   ${YELLOW}3.${NC} Vérifiez le chemin Docker si nécessaire"
echo -e "   ${YELLOW}4.${NC} Activez les notifications dans teslamail.php"
echo ""
echo -e "${CYAN}📋 Commandes utiles :${NC}"
echo -e "   Logs mail      : ${GREEN}tail -f /var/log/mail.log${NC}"
echo -e "   Logs charges   : ${GREEN}tail -f /var/log/teslacharge.log${NC}"
echo -e "   État Postfix   : ${GREEN}systemctl status postfix${NC}"
echo -e "   État Apache    : ${GREEN}systemctl status apache2${NC}"
echo -e "   Crontab        : ${GREEN}crontab -l${NC}"
echo -e "   Test manuel    : ${GREEN}/root/teslacharge.sh${NC}"
echo ""
echo -e "${YELLOW}📝 N'oubliez pas de configurer TeslaMate dans l'interface web !${NC}"
echo ""
echo -e "${CYAN}Support : GitHub - TeslaMate-Mail${NC}"
echo -e "${CYAN}Licence : GNU GPL v3${NC}"
echo ""
