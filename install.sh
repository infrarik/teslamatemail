#!/bin/bash

################################################################################
# Script d'installation COMPLET TeslaMate Mail
# Version 3.5 - Installation automatisée complète
#
# Ce script fait TOUT :
# - Installation des dépendances
# - Configuration Postfix (SMTP) avec double vérification pass
# - Configuration Apache/PHP
# - Nettoyage index.html par défaut
# - Déploiement intégral (www -> /var/www/html, root -> /root)
# - Configuration Docker & Nettoyage yaml (sed [[:blank:]])
# - Configuration Cron (5 min /bin/bash root)
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
echo -e "${BLUE}║     Installation TeslaMate Mail v3.5                  ║${NC}"
echo -e "${BLUE}║     Copyright © 2026 monserveur.fr / Eric BERTREM        ║${NC}"
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

# Double vérification du mot de passe SMTP
while true; do
    read -sp "Mot de passe SMTP : " SMTP_PASS
    echo ""
    read -sp "Confirmez le mot de passe SMTP : " SMTP_PASS_CONFIRM
    echo ""
    if [ "$SMTP_PASS" == "$SMTP_PASS_CONFIRM" ] && [ -n "$SMTP_PASS" ]; then
        break
    else
        echo -e "${RED}✗ Les mots de passe ne correspondent pas ou sont vides. Réessayez.${NC}"
    fi
done

read -p "Email expéditeur : " SMTP_FROM
read -p "Email destinataire par défaut : " DEFAULT_EMAIL

# ============================================================================
# ÉTAPE 1 : Installation des dépendances
# ============================================================================
echo -e "${GREEN}[1/9] Installation des dépendances système${NC}"
export DEBIAN_FRONTEND=noninteractive
apt update -qq
apt install -y apache2 php libapache2-mod-php php-pgsql php-json php-mbstring postgresql-client unzip zip curl wget logrotate net-tools postfix mailutils libsasl2-2 libsasl2-modules ca-certificates mosquitto-clients

# ============================================================================
# ÉTAPE 2 : Configuration de Postfix
# ============================================================================
echo -e "${GREEN}[2/9] Configuration de Postfix${NC}"
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
# ÉTAPE 3 : Nettoyage Apache
# ============================================================================
echo -e "${GREEN}[3/9] Nettoyage de l'installation Apache par défaut${NC}"
rm -f /var/www/html/index.html
echo -e "${GREEN}✓ index.html supprimé${NC}"

# ============================================================================
# ÉTAPE 4 & 5 : Extraction et Déploiement Intégral
# ============================================================================
echo -e "${GREEN}[4/9] Déploiement des fichiers (Archive Intégrale)${NC}"
TEMP_EXTRACT="/tmp/teslamate_extract_$$"
mkdir -p "$TEMP_EXTRACT"
unzip -q "$ZIP_FILE" -d "$TEMP_EXTRACT"

# Déploiement WWW (tous les fichiers)
if [ -d "$TEMP_EXTRACT/www" ]; then
    cp -r "$TEMP_EXTRACT/www"/. /var/www/html/
    mkdir -p /var/www/html/cgi-bin
    chown -R www-data:www-data /var/www/html/
    chmod -R 755 /var/www/html/
fi

# Déploiement ROOT (tous les fichiers)
if [ -d "$TEMP_EXTRACT/root" ]; then
    cp -r "$TEMP_EXTRACT/root"/. /root/
    chmod +x /root/*.sh 2>/dev/null || true
fi

# ============================================================================
# ÉTAPE 6 : Configuration du Cron
# ============================================================================
echo -e "${GREEN}[6/9] Configuration de la tâche planifiée (Cron)${NC}"
CRON_JOB="*/5 * * * * /bin/bash /root/teslacharge.sh > /dev/null 2>&1"
(crontab -l 2>/dev/null | grep -v "teslacharge.sh"; echo "$CRON_JOB") | crontab -
echo -e "${GREEN}✓ Cron root ajouté${NC}"

# ============================================================================
# ÉTAPE 7 : Configuration Docker & Nettoyage spécifique
# ============================================================================
echo -e "${GREEN}[7/9] Configuration Docker et nettoyage YAML${NC}"
DOCKER_COMPOSE_PATH=""
for path in "/opt/teslamate/docker-compose.yml" "/home/$USER/teslamate/docker-compose.yml" "./docker-compose.yml"; do
    if [ -f "$path" ]; then DOCKER_COMPOSE_PATH="$path"; break; fi
done

DB_USER="N/A"
DB_PASS="N/A"
DB_NAME="N/A"

if [ -n "$DOCKER_COMPOSE_PATH" ]; then
    # Suppression des commentaires tout en gardant l'indentation
    sed -i 's/[[:blank:]]#.*//' "$DOCKER_COMPOSE_PATH"
    
    # Extraction des informations de base de données
    DB_USER=$(grep "POSTGRES_USER=" "$DOCKER_COMPOSE_PATH" | cut -d'=' -f2 | xargs || echo "Non trouvé")
    DB_PASS=$(grep "POSTGRES_PASSWORD=" "$DOCKER_COMPOSE_PATH" | cut -d'=' -f2 | xargs || echo "Non trouvé")
    DB_NAME=$(grep "POSTGRES_DB=" "$DOCKER_COMPOSE_PATH" | cut -d'=' -f2 | xargs || echo "Non trouvé")
fi

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
echo ""

if [ -n "$DOCKER_COMPOSE_PATH" ]; then
    echo -e "${CYAN}🐳 CONFIGURATION DOCKER-COMPOSE (DB) :${NC}"
    echo -e "   Fichier          : ${YELLOW}$DOCKER_COMPOSE_PATH${NC}"
    echo -e "   Database Name    : ${GREEN}$DB_NAME${NC}"
    echo -e "   Database User    : ${GREEN}$DB_USER${NC}"
    echo -e "   Database Pass    : ${GREEN}$DB_PASS${NC}"
fi

echo ""
echo -e "${CYAN}🌐 ACCÈS :${NC}"
IP_ADDR=$(hostname -I | awk '{print $1}')
echo -e "   URL : ${GREEN}http://$IP_ADDR/tesla.php${NC}"
echo ""

rm -rf "$TEMP_EXTRACT"
