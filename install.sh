#!/bin/bash

################################################################################
# Script d'installation COMPLET TeslaMate Mail
# Version 3.1 - Installation automatisée complète avec Mosquitto
# 
# Ce script fait TOUT :
# - Installation des dépendances
# - Configuration Postfix (SMTP)
# - Configuration Mosquitto (MQTT) - OPTIONNEL
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
echo -e "${BLUE}║     Installation TeslaMate Mail v3.1                  ║${NC}"
echo -e "${BLUE}║     Copyright © 2026 monwifi.fr / Eric BERTREM        ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""

# Vérifier si root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}✖ Ce script doit être exécuté en tant que root${NC}"
    echo "Utilisez: sudo bash install.sh"
    exit 1
fi

# Vérifier la présence du fichier ZIP
if [ ! -f "$ZIP_FILE" ]; then
    echo -e "${RED}✖ Erreur: Le fichier files.zip est introuvable !${NC}"
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
    echo -e "${RED}✖ Le serveur SMTP est obligatoire${NC}"
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
    echo -e "${RED}✖ Le login SMTP est obligatoire${NC}"
    exit 1
fi

read -sp "Mot de passe SMTP : " SMTP_PASS
echo ""
if [ -z "$SMTP_PASS" ]; then
    echo -e "${RED}✖ Le mot de passe SMTP est obligatoire${NC}"
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
echo -e "${GREEN}[1/11] Installation des dépendances système${NC}"
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

echo -e "${GREEN}✓ Dépendances installées${NC}"
echo ""

# ============================================================================
# ÉTAPE 2 : Configuration Mosquitto (OPTIONNEL)
# ============================================================================
echo -e "${MAGENTA}╔═══════════════════════════════════════════════════════╗${NC}"
echo -e "${MAGENTA}║        CONFIGURATION DU SERVEUR MOSQUITTO (MQTT)       ║${NC}"
echo -e "${MAGENTA}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""

# Information sur TeslaMate
if [ -f "/var/lib/docker/volumes/teslamate_mosquitto-conf/_data/mosquitto.conf" ]; then
    echo -e "${YELLOW}ℹ️  Mosquitto de TeslaMate détecté (Docker)${NC}"
    echo "Ce script peut installer un serveur Mosquitto système indépendant."
    echo ""
fi

read -p "Voulez-vous installer et configurer le serveur MQTT Mosquitto système ? (o/N) : " install_mosquitto
install_mosquitto=${install_mosquitto:-n}

MQTT_HOST=""
MQTT_PORT="1883"
MQTT_USER=""
MQTT_PASSWORD=""

if [[ "$install_mosquitto" =~ ^[oO]$ ]]; then
    echo ""
    echo -e "${GREEN}[2/11] Installation et configuration de Mosquitto${NC}"
    
    # Installation de Mosquitto
    if command -v mosquitto &> /dev/null; then
        echo -e "${YELLOW}Mosquitto est déjà installé.${NC}"
        mosquitto -h | head -1
    else
        echo -e "${YELLOW}→ Installation de Mosquitto et client MQTT...${NC}"
        apt-get install -y mosquitto mosquitto-clients
        
        if [ $? -eq 0 ]; then
            echo -e "${GREEN}✓ Mosquitto installé avec succès${NC}"
        else
            echo -e "${RED}✗ Erreur lors de l'installation de Mosquitto${NC}"
            exit 1
        fi
    fi
    
    # Activer et démarrer le service
    systemctl enable mosquitto
    systemctl start mosquitto
    
    echo ""
    echo -e "${BLUE}Configuration du broker MQTT${NC}"
    echo ""
    
    # Demander le port
    read -p "Port d'écoute de Mosquitto (défaut: 1883) : " MQTT_PORT
    MQTT_PORT=${MQTT_PORT:-1883}
    
    # Demander si on veut créer un utilisateur
    echo ""
    read -p "Voulez-vous créer un utilisateur MQTT avec authentification ? (O/n) : " create_user
    create_user=${create_user:-o}
    
    if [[ "$create_user" =~ ^[oO]$ ]]; then
        echo ""
        read -p "Nom d'utilisateur MQTT : " MQTT_USER
        while [[ -z "$MQTT_USER" ]]; do
            echo -e "${RED}Le nom d'utilisateur ne peut pas être vide.${NC}"
            read -p "Nom d'utilisateur MQTT : " MQTT_USER
        done
        
        echo -e "${BLUE}Création de l'utilisateur avec mosquitto_passwd...${NC}"
        echo -e "${YELLOW}(Vous allez devoir saisir le mot de passe deux fois)${NC}"
        
        # Créer l'utilisateur avec mosquitto_passwd (mode interactif)
        mosquitto_passwd -c /etc/mosquitto/passwd "$MQTT_USER"
        
        if [ $? -ne 0 ]; then
            echo -e "${RED}✗ Erreur lors de la création de l'utilisateur${NC}"
            exit 1
        fi
        
        # Demander le mot de passe pour le sauvegarder (pour les tests et config)
        echo ""
        read -sp "Ressaisir le mot de passe pour la configuration : " MQTT_PASSWORD
        echo ""
        
        # Sécuriser le fichier de mots de passe
        chmod 600 /etc/mosquitto/passwd
        chown mosquitto:mosquitto /etc/mosquitto/passwd
        
        echo -e "${GREEN}✓ Utilisateur MQTT créé avec succès${NC}"
    fi
    
    # Vérifier si teslamate.conf existe
    echo ""
    echo -e "${YELLOW}Vérification des configurations existantes...${NC}"
    
    create_config=false
    if [ -f "/etc/mosquitto/conf.d/teslamate.conf" ]; then
        echo -e "${YELLOW}⚠️  Un fichier /etc/mosquitto/conf.d/teslamate.conf existe déjà.${NC}"
        echo "Contenu actuel :"
        echo "---"
        cat /etc/mosquitto/conf.d/teslamate.conf
        echo "---"
        echo ""
        read -p "Voulez-vous le remplacer ? (o/N) : " replace_conf
        replace_conf=${replace_conf:-n}
        
        if [[ ! "$replace_conf" =~ ^[oO]$ ]]; then
            echo -e "${YELLOW}Le fichier existant est conservé. Modification manuelle nécessaire.${NC}"
        else
            # Sauvegarder l'ancien fichier
            backup_file="/etc/mosquitto/conf.d/teslamate.conf.backup.$(date +%Y%m%d_%H%M%S)"
            cp /etc/mosquitto/conf.d/teslamate.conf "$backup_file"
            echo -e "${GREEN}Sauvegarde créée : $backup_file${NC}"
            create_config=true
        fi
    else
        create_config=true
    fi
    
    # Créer la configuration si nécessaire
    if [ "$create_config" = true ]; then
        echo -e "${BLUE}Création de la configuration Mosquitto...${NC}"
        
        cat > /etc/mosquitto/conf.d/teslamate.conf << EOF
# Configuration Mosquitto pour TeslaMate
# Généré le $(date)

listener $MQTT_PORT
protocol mqtt

EOF

        if [[ -n "$MQTT_USER" ]]; then
            cat >> /etc/mosquitto/conf.d/teslamate.conf << EOF
# Authentification requise
allow_anonymous false
password_file /etc/mosquitto/passwd
EOF
            echo -e "${GREEN}✓ Configuration avec authentification créée${NC}"
        else
            cat >> /etc/mosquitto/conf.d/teslamate.conf << EOF
# Pas d'authentification (mode anonyme)
allow_anonymous true
EOF
            echo -e "${YELLOW}✓ Configuration en mode anonyme créée${NC}"
        fi
    fi
    
    # Redémarrer Mosquitto
    echo ""
    echo -e "${BLUE}Redémarrage de Mosquitto...${NC}"
    systemctl restart mosquitto
    
    # Vérifier le statut
    sleep 2
    if systemctl is-active --quiet mosquitto; then
        echo -e "${GREEN}✓ Mosquitto redémarré avec succès${NC}"
    else
        echo -e "${RED}✗ Erreur lors du redémarrage de Mosquitto${NC}"
        echo "Logs des dernières erreurs :"
        journalctl -u mosquitto -n 20 --no-pager
    fi
    
    # Test de connexion
    echo ""
    echo -e "${BLUE}Test de connexion au broker MQTT...${NC}"
    
    # Terminal 1 : Subscriber
    if [[ -n "$MQTT_USER" ]]; then
        timeout 5 mosquitto_sub -h localhost -p "$MQTT_PORT" -u "$MQTT_USER" -P "$MQTT_PASSWORD" -t "test/connection" > /tmp/mqtt_test.log 2>&1 &
        SUB_PID=$!
        sleep 1
        
        mosquitto_pub -h localhost -p "$MQTT_PORT" -u "$MQTT_USER" -P "$MQTT_PASSWORD" -t "test/connection" -m "Hello from Mosquitto!"
        PUB_RESULT=$?
    else
        timeout 5 mosquitto_sub -h localhost -p "$MQTT_PORT" -t "test/connection" > /tmp/mqtt_test.log 2>&1 &
        SUB_PID=$!
        sleep 1
        
        mosquitto_pub -h localhost -p "$MQTT_PORT" -t "test/connection" -m "Hello from Mosquitto!"
        PUB_RESULT=$?
    fi
    
    # Attendre un peu pour la réception
    sleep 2
    kill $SUB_PID 2>/dev/null
    
    # Vérifier les résultats
    if [ $PUB_RESULT -eq 0 ] && grep -q "Hello from Mosquitto!" /tmp/mqtt_test.log 2>/dev/null; then
        echo -e "${GREEN}✓✓ Test de connexion MQTT RÉUSSI ✓✓${NC}"
    else
        echo -e "${YELLOW}⚠️  Test de connexion incomplet (peut être normal)${NC}"
    fi
    
    rm -f /tmp/mqtt_test.log
    
    # Définir MQTT_HOST pour la config
    MQTT_HOST="localhost"
    
    echo -e "${GREEN}✓ Configuration Mosquitto terminée${NC}"
else
    echo -e "${YELLOW}Installation de Mosquitto ignorée.${NC}"
    echo -e "${YELLOW}→ Installation du client MQTT uniquement...${NC}"
    apt install -y mosquitto-clients
    echo ""
fi
echo ""

# ============================================================================
# ÉTAPE 3 : Configuration de Postfix
# ============================================================================
echo -e "${GREEN}[3/11] Configuration du serveur mail Postfix${NC}"

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
# ÉTAPE 4 : Configuration d'Apache
# ============================================================================
echo -e "${GREEN}[4/11] Configuration d'Apache${NC}"

# Activer les modules PHP
a2enmod php* 2>/dev/null || true

# Redémarrer Apache
systemctl restart apache2
systemctl enable apache2 >/dev/null 2>&1

echo -e "${GREEN}✓ Apache configuré${NC}"
echo ""

# ============================================================================
# ÉTAPE 5 : Extraction de l'archive
# ============================================================================
echo -e "${GREEN}[5/11] Extraction de l'archive files.zip${NC}"

TEMP_EXTRACT="/tmp/teslamate_extract_$$"
mkdir -p "$TEMP_EXTRACT"

unzip -q "$ZIP_FILE" -d "$TEMP_EXTRACT"

echo -e "${GREEN}✓ Archive extraite${NC}"
echo ""

# ============================================================================
# ÉTAPE 6 : Déploiement des fichiers web
# ============================================================================
echo -e "${GREEN}[6/11] Déploiement des fichiers web${NC}"

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
    # Mettre à jour le fichier setup avec la config email et MQTT
    cat > "$WWW_DEST/cgi-bin/setup" <<EOF
### TeslaMate Mail Config - Initialized $(date '+%Y-%m-%d %H:%M:%S') ###
mqtt_host=$MQTT_HOST
mqtt_port=$MQTT_PORT
mqtt_user=$MQTT_USER
mqtt_pass=$MQTT_PASSWORD
mqtt_topic=teslamate/cars/1
notification_email=$DEFAULT_EMAIL
docker_path=/opt/teslamate/docker-compose.yml
mqtt_enabled=$([ -n "$MQTT_HOST" ] && echo "True" || echo "False")
email_enabled=True
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
# ÉTAPE 7 : Déploiement des scripts root
# ============================================================================
echo -e "${GREEN}[7/11] Déploiement des scripts dans /root${NC}"

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
# ÉTAPE 8 : Suppression index.html par défaut Apache
# ============================================================================
echo -e "${GREEN}[8/11] Nettoyage de l'installation Apache${NC}"

if [ -f "/var/www/html/index.html" ]; then
    rm -f /var/www/html/index.html
    echo -e "${GREEN}✓ Fichier index.html par défaut supprimé${NC}"
fi
echo ""

# ============================================================================
# ÉTAPE 9 : Configuration Docker (si TeslaMate est installé)
# ============================================================================
echo -e "${GREEN}[9/11] Recherche et configuration de Docker${NC}"

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
            if grep -q "database:" "$DOCKER_COMPOSE_PATH"; then
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
# ÉTAPE 10 : Configuration du Cron
# ============================================================================
echo -e "${GREEN}[10/11] Configuration de la tâche planifiée${NC}"

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
# ÉTAPE 11 : Configuration Logrotate
# ============================================================================
echo -e "${GREEN}[11/11] Configuration de Logrotate${NC}"

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
# Test de configuration email
# ============================================================================
echo -e "${GREEN}Test de configuration email${NC}"

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

if [[ -n "$MQTT_HOST" ]]; then
    echo -e "${CYAN}📡 Configuration MQTT :${NC}"
    echo -e "   Broker           : ${YELLOW}$MQTT_HOST:$MQTT_PORT${NC}"
    if [[ -n "$MQTT_USER" ]]; then
        echo -e "   Utilisateur      : ${YELLOW}$MQTT_USER${NC}"
        echo -e "   Authentification : ${GREEN}ACTIVÉE${NC}"
    else
        echo -e "   Authentification : ${YELLOW}Aucune (anonymous)${NC}"
    fi
    echo ""
fi

echo -e "${CYAN}📂 Fichiers installés :${NC}"
echo -e "   /var/www/html/              → Fichiers web"
echo -e "   /var/www/html/cgi-bin/      → Configuration"
echo -e "   /root/teslacharge.sh        → Script monitoring"
echo -e "   /var/log/teslacharge.log    → Logs"
echo ""
echo -e "${CYAN}🌐 Accès web :${NC}"
SERVER_IP=$(hostname -I | awk '{print $1}')
echo -e "   Dashboard   : ${GREEN}http://$SERVER_IP/tesla.html${NC}"
echo -e "   Config      : ${GREEN}http://$SERVER_IP/teslaconf.php${NC}"
echo -e "   Accueil     : ${GREEN}http://$SERVER_IP/index.php${NC}"
echo ""
echo -e "${CYAN}⚙️ Prochaines étapes :${NC}"
echo -e "   ${YELLOW}1.${NC} Accédez à l'interface web"
if [[ -z "$MQTT_HOST" ]]; then
    echo -e "   ${YELLOW}2.${NC} Configurez MQTT dans teslaconf.php"
else
    echo -e "   ${YELLOW}2.${NC} MQTT déjà configuré ✓"
fi
echo -e "   ${YELLOW}3.${NC} Vérifiez le chemin Docker si nécessaire"
echo -e "   ${YELLOW}4.${NC} Activez les notifications dans teslamail.php"
echo ""
echo -e "${CYAN}📋 Commandes utiles :${NC}"
echo -e "   Logs mail      : ${GREEN}tail -f /var/log/mail.log${NC}"
echo -e "   Logs charges   : ${GREEN}tail -f /var/log/teslacharge.log${NC}"
echo -e "   État Postfix   : ${GREEN}systemctl status postfix${NC}"
echo -e "   État Apache    : ${GREEN}systemctl status apache2${NC}"
if [[ -n "$MQTT_HOST" ]]; then
    echo -e "   État Mosquitto : ${GREEN}systemctl status mosquitto${NC}"
fi
echo -e "   Crontab        : ${GREEN}crontab -l${NC}"
echo -e "   Test manuel    : ${GREEN}/root/teslacharge.sh${NC}"
echo ""
echo -e "${YELLOW}📌 N'oubliez pas de configurer TeslaMate Mail dans l'interface web !${NC}"
echo ""
echo -e "${CYAN}Support : GitHub - TeslaMate-Mail${NC}"
echo -e "${CYAN}Licence : GNU GPL v3${NC}"
echo ""
