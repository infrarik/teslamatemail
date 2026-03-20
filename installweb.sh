#!/bin/bash

################################################################################
# Script de déploiement WEB uniquement — TeslaMate Mail
# Version 3.5
#
# Déploie les fichiers PHP sans reconfigurer Postfix ni les dépendances.
# Utile pour une mise à jour ou un redéploiement rapide.
#
# - Déploiement www -> /var/www/html et root -> /root
# - Préservation de cgi-bin/setup existant (ajout des clés manquantes seulement)
# - Création du fichier de log /var/log/tesla_rapport.log
# - Installation du cron hebdomadaire fixe (activé via setup)
################################################################################

set -e

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

ARCHIVE="files.zip"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ZIP_FILE="$SCRIPT_DIR/$ARCHIVE"

echo -e "${CYAN}╔═══════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║     Déploiement Web TeslaMate Mail v3.6               ║${NC}"
echo -e "${CYAN}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""

# Vérification root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}✗ Ce script doit être exécuté en tant que root${NC}"
    exit 1
fi

# Vérification archive
if [ ! -f "$ZIP_FILE" ]; then
    echo -e "${RED}✗ Fichier introuvable : $ZIP_FILE${NC}"
    exit 1
fi

# ============================================================================
# ÉTAPE 1 : Extraction et déploiement
# ============================================================================
echo -e "${GREEN}[1/4] Extraction et déploiement des fichiers${NC}"
TEMP_EXTRACT="/tmp/teslamate_web_extract_$$"
mkdir -p "$TEMP_EXTRACT"
unzip -q "$ZIP_FILE" -d "$TEMP_EXTRACT"

# Déploiement WWW — on exclut cgi-bin/setup pour ne pas l'écraser
if [ -d "$TEMP_EXTRACT/www" ]; then
    rsync -a --exclude='cgi-bin/setup' "$TEMP_EXTRACT/www/" /var/www/html/ 2>/dev/null || \
        { cp -r "$TEMP_EXTRACT/www"/. /var/www/html/ && echo -e "   ${YELLOW}⚠ rsync absent, copie complète (setup peut avoir été écrasé)${NC}"; }
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
# ÉTAPE 2 : Complétion de cgi-bin/setup (clés manquantes seulement)
# ============================================================================
echo -e "${GREEN}[2/4] Vérification de cgi-bin/setup${NC}"
SETUP_FILE="/var/www/html/cgi-bin/setup"
mkdir -p /var/www/html/cgi-bin
touch "$SETUP_FILE"

# Ajoute une clé=valeur si la clé est absente (insensible à la casse)
add_if_missing() {
    local key="$1"
    local default="$2"
    if ! grep -qi "^${key}=" "$SETUP_FILE"; then
        echo "${key}=${default}" >> "$SETUP_FILE"
        echo -e "   ${CYAN}→ Clé ajoutée : ${key}=${default}${NC}"
    fi
}

add_if_missing "NOTIFICATION_EMAIL"  "notif@monmail.com"
add_if_missing "KWH_PRICE"           "0.0000"
add_if_missing "DOCKER_PATH"         "/opt/teslamate/docker-compose.yml"
add_if_missing "LANGUAGE"            "fr"
add_if_missing "CURRENCY"            "EUR"
add_if_missing "RAPPORT_HEBDO"       "False"
add_if_missing "email_enabled"       "False"
add_if_missing "telegram_enabled"    "False"
add_if_missing "mqtt_enabled"        "False"

chown www-data:www-data "$SETUP_FILE"
chmod 640 "$SETUP_FILE"
echo -e "   ${CYAN}→ cgi-bin/setup OK${NC}"

# ============================================================================
# ÉTAPE 3 : Création du fichier de log
# ============================================================================
echo -e "${GREEN}[3/4] Création du fichier de log${NC}"
touch /var/log/tesla_rapport.log
chmod 666 /var/log/tesla_rapport.log
echo -e "   ${CYAN}Log : /var/log/tesla_rapport.log${NC}"

# ============================================================================
# ÉTAPE 4 : Cron hebdomadaire + redémarrage Apache
# ============================================================================
echo -e "${GREEN}[4/4] Cron hebdomadaire + redémarrage Apache${NC}"
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

systemctl restart apache2 2>/dev/null || service apache2 restart 2>/dev/null || true
echo -e "   ${CYAN}→ Apache redémarré${NC}"

# ============================================================================
# RÉSUMÉ
# ============================================================================
echo ""
echo -e "${CYAN}╔═══════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║      DÉPLOIEMENT TERMINÉ                              ║${NC}"
echo -e "${CYAN}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${CYAN}⏰ Cron hebdomadaire :${NC}"
echo -e "   ${GREEN}$CRON_LINE${NC}"
echo -e "   Log    : ${YELLOW}$CRON_LOG${NC}"
echo -e "   Active : via le toggle dans teslacalcul.php (RAPPORT_HEBDO dans cgi-bin/setup)"
echo ""
echo -e "${CYAN}🌐 Accès :${NC}"
IP_ADDR=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "localhost")
echo -e "   URL : ${GREEN}http://$IP_ADDR/tesla.php${NC}"
echo ""
