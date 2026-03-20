#!/bin/bash

################################################################################
# Script de création du package files.zip pour TeslaMate Mail
# Version 3.6 - PDF pur PHP sans dépendance externe
################################################################################

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configuration des chemins
PACKAGE_DIR="/root/teslamate_package"
DEST_ZIP="/root/files.zip"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Chemins sources
SRC_ROOT="/root"
SRC_WWW="/var/www/html"

clear
echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}     Création du package TeslaMate Mail v3.6${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
echo ""

# ============================================================================
# ÉTAPE 0 : Vérifications et Pré-requis
# ============================================================================
echo -e "${GREEN}[0/6] Vérification des pré-requis${NC}"

if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}❌ Erreur : Ce script doit être exécuté en root (sudo)${NC}"
    exit 1
fi

if ! command -v zip &> /dev/null; then
    echo -e "${YELLOW}→ 'zip' n'est pas installé. Installation en cours...${NC}"
    apt update -qq && apt install zip -y -qq
    echo -e "${GREEN}✓ 'zip' installé avec succès.${NC}"
else
    echo -e "${CYAN}    ✓ 'zip' est déjà installé.${NC}"
fi

[ -d "$PACKAGE_DIR" ] && rm -rf "$PACKAGE_DIR"
[ -f "$DEST_ZIP" ] && rm -f "$DEST_ZIP"

# ============================================================================
# ÉTAPE 1 : Création de la structure
# ============================================================================
echo -e "${GREEN}[1/6] Création de la structure temporaire${NC}"
mkdir -p "$PACKAGE_DIR/root"
mkdir -p "$PACKAGE_DIR/www/cgi-bin"

# ============================================================================
# ÉTAPE 2 : Copie des fichiers racine (Installation & Docs)
# ============================================================================
echo -e "${GREEN}[2/6] Recherche des fichiers d'installation${NC}"

FILES_BASE=("install.sh" "installweb.sh" "LICENSE" "README.md")
for file in "${FILES_BASE[@]}"; do
    if [ -f "$SCRIPT_DIR/$file" ]; then
        cp "$SCRIPT_DIR/$file" "$PACKAGE_DIR/"
        echo -e "${CYAN}    ✓ $file trouvé${NC}"
    else
        echo -e "${YELLOW}    ⚠ $file introuvable, création d'un vide${NC}"
        touch "$PACKAGE_DIR/$file"
    fi
done

# ============================================================================
# ÉTAPE 3 : Copie des scripts depuis /root
# ============================================================================
echo -e "${GREEN}[3/6] Copie des scripts depuis $SRC_ROOT${NC}"

if [ -f "$SRC_ROOT/teslacharge.sh" ]; then
    cp "$SRC_ROOT/teslacharge.sh" "$PACKAGE_DIR/root/"
    chmod +x "$PACKAGE_DIR/root/teslacharge.sh"
    echo -e "${CYAN}    ✓ teslacharge.sh copié${NC}"
else
    echo -e "${RED}    ✗ teslacharge.sh manquant dans $SRC_ROOT${NC}"
fi

# ============================================================================
# ÉTAPE 4 : Copie des fichiers web depuis /var/www/html
# ============================================================================
echo -e "${GREEN}[4/6] Copie des fichiers web (y compris PWA)${NC}"

WWW_FILES=(
    "index.php" "tesla.php" "teslamate_api.php" "teslaconf.php"
    "teslaconfig_handler.php" "teslanotif.php" "teslamap.php"
    "teslacalcul.php" "tesla_rapport_hebdo.php" "tesla_rapport_hebdo_body.php" "fn_mail_rapport.php"
    "credits.php" "parrain.php"
    "telegram_helper.php" "telegramtest.php" "test_docker.php"
    "test_email.php" "test_mqtt.php" "test_telegram.php"
    "notification_charging.php" "logoteslamatemail.png" "logoparrain.png"
    "icon-192.png" "icon-512.png" "manifest.json" "sw.js" "dashcam_logosmall.jpg"
)

for file in "${WWW_FILES[@]}"; do
    if [ -f "$SRC_WWW/$file" ]; then
        cp "$SRC_WWW/$file" "$PACKAGE_DIR/www/"
    else
        echo -e "${RED}    ✗ Fichier manquant : $file${NC}"
    fi
done
echo -e "${CYAN}    ✓ Fichiers web copiés${NC}"

# ============================================================================
# ÉTAPE 5 : Génération des fichiers cgi-bin & Sécurité
# ============================================================================
echo -e "${GREEN}[5/6] Génération de la configuration et du .htaccess${NC}"

# Création du fichier setup par défaut
cat > "$PACKAGE_DIR/www/cgi-bin/setup" <<EOF
mqtt_host=127.0.0.1
mqtt_port=1883
mqtt_user=mqtt_user
mqtt_pass=pass
mqtt_topic=teslamate/tmy
notification_email=notif@monmail.com
docker_path=/opt/teslamate/docker-compose.yml
telegram_bot_token=xxxx
email_enabled=False
telegram_enabled=False
mqtt_enabled=False
KWH_PRICE=0.0000
LANGUAGE=fr
CURRENCY=EUR
RAPPORT_HEBDO=False
EOF

# Création du fichier .htaccess de sécurité
cat > "$PACKAGE_DIR/www/cgi-bin/.htaccess" <<EOF
# Empêche l'affichage de la liste des fichiers
Options -Indexes

# Bloque l'accès direct aux fichiers sensibles
<FilesMatch "\.(json|log|ini|setup)$">
    Require all denied
</FilesMatch>
EOF

echo "001" > "$PACKAGE_DIR/www/cgi-bin/lastchargeid"
echo -e "${CYAN}    ✓ Fichiers cgi-bin et sécurité générés${NC}"

# ============================================================================
# ÉTAPE 6 : Création de l'archive
# ============================================================================
echo -e "${GREEN}[6/6] Compression vers $DEST_ZIP${NC}"

cd "$PACKAGE_DIR"
zip -r "$DEST_ZIP" . > /dev/null

if [ -f "$DEST_ZIP" ]; then
    echo -e "${GREEN}✓ Archive créée avec succès dans /root/files.zip${NC}"
else
    echo -e "${RED}❌ Erreur : L'archive n'a pas pu être générée.${NC}"
    exit 1
fi

# ============================================================================
# RÉSUMÉ
# ============================================================================
echo ""
SIZE=$(du -h "$DEST_ZIP" | cut -f1)
echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
echo -e "${CYAN}📊 Taille    :${NC} ${YELLOW}$SIZE${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
echo ""

cd /root
read -p "Supprimer le répertoire temporaire teslamate_package ? (O/n) : " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Nn]$ ]]; then
    rm -rf "$PACKAGE_DIR"
    echo -e "${GREEN}✓ Répertoire temporaire supprimé${NC}"
fi

echo -e "\n${YELLOW}Le fichier ${CYAN}$DEST_ZIP${YELLOW} est disponible !${NC}\n"

