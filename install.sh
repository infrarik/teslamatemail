#!/bin/bash

################################################################################
# Script d'installation COMPLET TeslaMate Mail
# Version 3.7 - Ajout auth.php et auth_check.php, permissions setup 664
#
# Ce script fait TOUT :
# - Installation des dépendances
# - Configuration Postfix (SMTP)
# - Configuration Apache/PHP
# - Déploiement intégral (www -> /var/www/html, root -> /root)
# - Configuration Docker & Nettoyage yaml
# - Écriture de cgi-bin/setup
# - Création du log et installation du cron hebdomadaire
# - Récapitulatif détaillé de la configuration
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
echo -e "${BLUE}║     Installation TeslaMate Mail v3.7                  ║${NC}"
echo -e "${BLUE}║     Copyright © 2026 monserveur.fr / Eric BERTREM     ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""

# Vérifier si root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}✗ Ce script doit être exécuté en tant que root${NC}"
    exit 1
fi

# Vérifier la présence du fichier ZIP
if [ ! -f "$ZIP_FILE" ]; then
    echo -e "${RED}✗ Erreur: Le fichier files.zip est introuvable !${NC}"
    exit 1
fi

# ============================================================================
# COLLECTE DES INFORMATIONS UTILISATEUR
# ============================================================================
echo -e "${MAGENTA}╔═══════════════════════════════════════════════════════╗${NC}"
echo -e "${MAGENTA}║          CONFIGURATION DU SERVEUR EMAIL                ║${NC}"
echo -e "${MAGENTA}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""

read -p "Hostname du serveur (ex: teslamate.local) : " HOSTNAME
HOSTNAME=${HOSTNAME:-teslamate.local}

read -p "Serveur SMTP (ex: mail.monserveur.fr) : " SMTP_HOST
if [ -z "$SMTP_HOST" ]; then echo -e "${RED}✗ SMTP obligatoire${NC}"; exit 1; fi

read -p "Port SMTP [465] : " SMTP_PORT
SMTP_PORT=${SMTP_PORT:-465}

read -p "Type de sécurité (1: SMTPS 465, 2: STARTTLS 587) [1] : " SECURITY_TYPE
SECURITY_TYPE=${SECURITY_TYPE:-1}

read -p "Login SMTP : " SMTP_USER
read -sp "Mot de passe SMTP : " SMTP_PASS
echo ""

read -p "Email expéditeur : " SMTP_FROM
read -p "Email destinataire par défaut : " DEFAULT_EMAIL

read -p "Prix du kWh (ex: 0.2500) [0.0000] : " KWH_PRICE
KWH_PRICE=${KWH_PRICE:-0.0000}

# ============================================================================
# ÉTAPE 1 : Installation des dépendances
# ============================================================================
echo -e "${GREEN}[1/8] Installation des dépendances système${NC}"
export DEBIAN_FRONTEND=noninteractive
apt update -qq
apt install -y apache2 php libapache2-mod-php php-pgsql php-json php-mbstring \
    postgresql-client unzip zip curl wget logrotate net-tools \
    postfix mailutils libsasl2-2 libsasl2-modules ca-certificates mosquitto-clients

# ============================================================================
# ÉTAPE 2 : Configuration de Postfix
# ============================================================================
echo -e "${GREEN}[2/8] Configuration de Postfix${NC}"
DOMAIN=$(echo "$SMTP_FROM" | cut -d'@' -f2)

cat > /etc/postfix/main.cf <<EOF
myhostname = $HOSTNAME
mydomain = $DOMAIN
myorigin = \$mydomain
relayhost = [$SMTP_HOST]:$SMTP_PORT
smtp_sasl_auth_enable = yes
smtp_sasl_password_maps = hash:/etc/postfix/sasl_passwd
smtp_sasl_security_options = noanonymous
smtp_tls_security_level = encrypt
smtp_tls_wrappermode = $([ "$SECURITY_TYPE" = "1" ] && echo "yes" || echo "no")
smtp_generic_maps = hash:/etc/postfix/generic
EOF

echo "[$SMTP_HOST]:$SMTP_PORT $SMTP_USER:$SMTP_PASS" > /etc/postfix/sasl_passwd
chmod 600 /etc/postfix/sasl_passwd
postmap /etc/postfix/sasl_passwd

echo "root $SMTP_FROM" > /etc/postfix/generic
postmap /etc/postfix/generic

systemctl restart postfix

# ============================================================================
# ÉTAPE 3 : Extraction et déploiement (TOUS LES FICHIERS)
# ============================================================================
echo -e "${GREEN}[3/8] Déploiement des fichiers (Archive Intégrale)${NC}"
TEMP_EXTRACT="/tmp/teslamate_extract_$$"
mkdir -p "$TEMP_EXTRACT"
unzip -q "$ZIP_FILE" -d "$TEMP_EXTRACT"

# Déploiement WWW
if [ -d "$TEMP_EXTRACT/www" ]; then
    cp -r "$TEMP_EXTRACT/www"/. /var/www/html/
    mkdir -p /var/www/html/cgi-bin
    chown -R www-data:www-data /var/www/html/
    echo -e "   ${CYAN}→ /var/www/html/ déployé${NC}"
fi

# Déploiement ROOT
if [ -d "$TEMP_EXTRACT/root" ]; then
    cp -r "$TEMP_EXTRACT/root"/. /root/
    chmod +x /root/*.sh 2>/dev/null || true
    echo -e "   ${CYAN}→ /root/ déployé${NC}"
fi

rm -rf "$TEMP_EXTRACT"

# ============================================================================
# ÉTAPE 4 : Configuration Docker & Nettoyage YAML
# ============================================================================
echo -e "${GREEN}[4/8] Configuration Docker${NC}"
DOCKER_COMPOSE_PATH=""
for path in "/opt/teslamate/docker-compose.yml" "/home/$USER/teslamate/docker-compose.yml" "./docker-compose.yml"; do
    if [ -f "$path" ]; then DOCKER_COMPOSE_PATH="$path"; break; fi
done

if [ -n "$DOCKER_COMPOSE_PATH" ]; then
    sed -i 's/[[:blank:]]#.*//' "$DOCKER_COMPOSE_PATH"
    DB_USER=$(grep "POSTGRES_USER="     "$DOCKER_COMPOSE_PATH" | cut -d'=' -f2 | xargs || echo "Non trouvé")
    DB_PASS=$(grep "POSTGRES_PASSWORD=" "$DOCKER_COMPOSE_PATH" | cut -d'=' -f2 | xargs || echo "Non trouvé")
    DB_NAME=$(grep "POSTGRES_DB="       "$DOCKER_COMPOSE_PATH" | cut -d'=' -f2 | xargs || echo "Non trouvé")
fi

# ============================================================================
# ÉTAPE 5 : Écriture du fichier setup (cgi-bin/setup)
# ============================================================================
echo -e "${GREEN}[5/8] Écriture de la configuration cgi-bin/setup${NC}"
mkdir -p /var/www/html/cgi-bin
SETUP_FILE="/var/www/html/cgi-bin/setup"

touch "$SETUP_FILE"
for KEY in NOTIFICATION_EMAIL KWH_PRICE DOCKER_PATH LANGUAGE CURRENCY RAPPORT_HEBDO; do
    sed -i "/^${KEY}=/Id" "$SETUP_FILE"
done

cat >> "$SETUP_FILE" <<EOF
NOTIFICATION_EMAIL=$DEFAULT_EMAIL
KWH_PRICE=$KWH_PRICE
DOCKER_PATH=${DOCKER_COMPOSE_PATH:-}
LANGUAGE=fr
CURRENCY=EUR
RAPPORT_HEBDO=False
EOF

chown www-data:www-data "$SETUP_FILE"
chmod 664 "$SETUP_FILE"
echo -e "   ${CYAN}→ cgi-bin/setup écrit${NC}"

# ============================================================================
# ÉTAPE 6 : Création du fichier de log
# ============================================================================
echo -e "${GREEN}[6/8] Création du fichier de log${NC}"
touch /var/log/tesla_rapport.log
chmod 666 /var/log/tesla_rapport.log
echo -e "   ${CYAN}Log : /var/log/tesla_rapport.log${NC}"

# Configuration logrotate
cat > /etc/logrotate.d/teslamate-mail << 'EOF'
/var/log/tesla_rapport.log {
    weekly
    rotate 12
    compress
    delaycompress
    missingok
    notifempty
    create 666 root root
}
EOF
echo -e "   ${CYAN}→ Logrotate configuré (hebdo, 12 semaines)${NC}"

# ============================================================================
# ÉTAPE 7 : Installation du cron hebdomadaire (fixe, activé via setup)
# ============================================================================
echo -e "${GREEN}[7/8] Installation du cron hebdomadaire (lundi 4h)${NC}"
CRON_SCRIPT="/var/www/html/tesla_rapport_hebdo.php"
CRON_LOG="/var/log/tesla_rapport.log"
CRON_LINE="0 4 * * 1 php $CRON_SCRIPT >> $CRON_LOG 2>&1"
CRON_MARKER="tesla_rapport_hebdo"

if crontab -l 2>/dev/null | grep -q "$CRON_MARKER"; then
    echo -e "   ${YELLOW}⚠ Cron déjà présent, non modifié${NC}"
else
    (crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -
    echo -e "   ${CYAN}Cron installé : $CRON_LINE${NC}"
fi

# ============================================================================
# ÉTAPE 8 : Vérification Apache / PHP
# ============================================================================
echo -e "${GREEN}[8/8] Vérification Apache / PHP${NC}"
a2enmod php* 2>/dev/null || true
systemctl enable apache2 2>/dev/null || true

# Désactiver PrivateTmp pour Apache (nécessaire pour accéder à /tmp depuis PHP)
mkdir -p /etc/systemd/system/apache2.service.d/
echo -e "[Service]\nPrivateTmp=false" > /etc/systemd/system/apache2.service.d/override.conf
echo -e "   ${CYAN}→ PrivateTmp désactivé pour Apache (accès /tmp depuis PHP)${NC}"
systemctl daemon-reload
systemctl restart apache2

# ============================================================================
# RÉSUMÉ FINAL
# ============================================================================
clear
echo -e "${BLUE}╔═══════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║      RÉCAPITULATIF DE LA CONFIGURATION                ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${CYAN}📧 CONFIGURATION EMAIL (SMTP) :${NC}"
echo -e "   Serveur Host     : ${YELLOW}$SMTP_HOST${NC}"
echo -e "   Port / Sécurité  : ${YELLOW}$SMTP_PORT ($([ "$SECURITY_TYPE" = "1" ] && echo "SMTPS" || echo "STARTTLS"))${NC}"
echo -e "   Utilisateur      : ${YELLOW}$SMTP_USER${NC}"
echo -e "   Expéditeur       : ${YELLOW}$SMTP_FROM${NC}"
echo -e "   Destinataire     : ${YELLOW}$DEFAULT_EMAIL${NC}"
echo -e "   Prix kWh         : ${YELLOW}$KWH_PRICE EUR${NC}"
echo ""

if [ -n "$DOCKER_COMPOSE_PATH" ]; then
    echo -e "${CYAN}🐳 CONFIGURATION DOCKER-COMPOSE (BASE DE DONNÉES) :${NC}"
    echo -e "   Fichier source   : ${YELLOW}$DOCKER_COMPOSE_PATH${NC}"
    echo -e "   Database Name    : ${GREEN}$DB_NAME${NC}"
    echo -e "   Database User    : ${GREEN}$DB_USER${NC}"
    echo -e "   Database Pass    : ${GREEN}$DB_PASS${NC}"
else
    echo -e "${RED}⚠ Aucune donnée Docker extraite (fichier non trouvé).${NC}"
fi

echo ""
echo -e "${CYAN}⏰ CRON HEBDOMADAIRE :${NC}"
echo -e "   ${GREEN}$CRON_LINE${NC}"
echo -e "   Log    : ${YELLOW}$CRON_LOG${NC}"
echo -e "   Active : via le toggle dans teslacalcul.php (RAPPORT_HEBDO dans cgi-bin/setup)"
echo ""
echo -e "${CYAN}🌐 ACCÈS :${NC}"
IP_ADDR=$(hostname -I | awk '{print $1}')
echo -e "   URL : ${GREEN}http://$IP_ADDR/tesla.php${NC}"
echo ""

