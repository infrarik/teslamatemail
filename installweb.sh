#!/bin/bash

################################################################################
# Script de déploiement WEB uniquement — TeslaMate Mail
# Version 3.5
#
# Déploie les fichiers PHP sans reconfigurer Postfix ni les dépendances.
# Utile pour une mise à jour ou un redéploiement rapide.
#
# - Déploiement www -> /var/www/html et root -> /root
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
echo -e "${CYAN}║     Déploiement Web TeslaMate Mail v3.5               ║${NC}"
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
# ÉTAPE 2 : Création du fichier de log
# ============================================================================
echo -e "${GREEN}[2/4] Création du fichier de log${NC}"
touch /var/log/tesla_rapport.log
chmod 666 /var/log/tesla_rapport.log
echo -e "   ${CYAN}Log : /var/log/tesla_rapport.log${NC}"

# ============================================================================
# ÉTAPE 3 : Installation du cron hebdomadaire (fixe, activé via setup)
# ============================================================================
echo -e "${GREEN}[3/4] Installation du cron hebdomadaire (lundi 4h)${NC}"
CRON_SCRIPT="/var/www/html/tesla_rapport_hebdo.php"
CRON_LOG="/var/log/tesla_rapport.log"
CRON_LINE="0 4 * * 1 php $CRON_SCRIPT >> $CRON_LOG 2>&1"
CRON_MARKER="tesla_rapport_hebdo"

if crontab -l 2>/dev/null | grep -q "$CRON_MARKER"; then
    echo -e "   ${YELLOW}⚠ Cron déjà présent, non modifié${NC}"
    crontab -l | grep "$CRON_MARKER"
else
    (crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -
    echo -e "   ${CYAN}Cron installé : $CRON_LINE${NC}"
fi

# ============================================================================
# ÉTAPE 4 : Redémarrage Apache
# ============================================================================
echo -e "${GREEN}[4/4] Redémarrage Apache${NC}"
systemctl restart apache2 2>/dev/null || service apache2 restart 2>/dev/null || true

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

#
# Déploie les fichiers PHP sans reconfigurer Postfix ni les dépendances.
# Utile pour une mise à jour ou un redéploiement rapide.
#
# Ajoute également :
# - Création du fichier de log /var/log/tesla_rapport.log
# - Installation du cron hebdomadaire (lundi 4h)
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
echo -e "${CYAN}║     Déploiement Web TeslaMate Mail v3.3               ║${NC}"
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

# Installation du service systemd cron_watcher
if [ -f /root/cron_watcher.py ]; then
    cp /root/cron_watcher.service /etc/systemd/system/cron_watcher.service
    systemctl daemon-reload
    systemctl enable cron_watcher
    systemctl restart cron_watcher
    echo -e "   ${CYAN}→ cron_watcher : service systemd installé et démarré${NC}"
fi

rm -rf "$TEMP_EXTRACT"

# ============================================================================
# ÉTAPE 2 : Création du fichier de log
# ============================================================================
echo -e "${GREEN}[2/4] Création du fichier de log${NC}"
touch /var/log/tesla_rapport.log
chmod 666 /var/log/tesla_rapport.log
echo -e "   ${CYAN}Log créé : /var/log/tesla_rapport.log${NC}"

# ============================================================================
# ÉTAPE 3 : Installation du cron hebdomadaire
# ============================================================================
echo -e "${GREEN}[3/4] Installation du cron hebdomadaire (lundi 4h)${NC}"
CRON_SCRIPT="/var/www/html/tesla_rapport_hebdo.php"
CRON_LOG="/var/log/tesla_rapport.log"
CRON_LINE="0 4 * * 1 php $CRON_SCRIPT >> $CRON_LOG 2>&1"
CRON_MARKER="tesla_rapport_hebdo"

if crontab -l 2>/dev/null | grep -q "$CRON_MARKER"; then
    echo -e "   ${YELLOW}⚠ Cron déjà présent, non modifié${NC}"
    crontab -l | grep "$CRON_MARKER"
else
    (crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -
    echo -e "   ${CYAN}Cron installé : $CRON_LINE${NC}"
fi

# ============================================================================
# ÉTAPE 4 : Redémarrage Apache
# ============================================================================
echo -e "${GREEN}[4/4] Redémarrage Apache${NC}"
systemctl restart apache2 2>/dev/null || service apache2 restart 2>/dev/null || true

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
echo -e "   Log : ${YELLOW}$CRON_LOG${NC}"
echo ""
echo -e "${CYAN}🌐 Accès :${NC}"
IP_ADDR=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "localhost")
echo -e "   URL : ${GREEN}http://$IP_ADDR/tesla.php${NC}"
echo ""
