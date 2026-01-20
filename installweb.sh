#!/bin/bash

################################################################################
# Script de mise à jour - TeslaMate Mail Web
# Version 1.1 - Déploiement sécurisé (Exclusion de la config)
################################################################################

# Nom de l'archive
ARCHIVE="files.zip"

# Couleurs
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Vérification des droits root
if [ "$EUID" -ne 0 ]; then 
  echo -e "${RED}✗ Veuillez exécuter ce script en tant que root ou avec sudo.${NC}"
  exit 1
fi

# Vérification de la présence de l'archive
if [ ! -f "$ARCHIVE" ]; then
    echo -e "${RED}✗ Erreur : Le fichier $ARCHIVE est introuvable dans le répertoire courant.$(pwd)${NC}"
    exit 1
fi

echo -e "${YELLOW}🚀 Début du déploiement des mises à jour...${NC}"

# Création du dossier temporaire propre
rm -rf /tmp/extraction_web/
mkdir -p /tmp/extraction_web/

# 1. Extraction du contenu du dossier 'root' vers /root
echo -e "→ Mise à jour des scripts système (/root)..."
unzip -o -q "$ARCHIVE" "root/*" -d /tmp/extraction_web/
cp -r /tmp/extraction_web/root/. /root/
chmod +x /root/*.sh 2>/dev/null || true

# 2. Extraction du contenu du dossier 'www' vers /var/www/html
# EXCLUSION CRITIQUE : on ne touche pas à setup et lastchargeid
echo -e "→ Mise à jour de l'interface web (/var/www/html)..."
unzip -o -q "$ARCHIVE" "www/*" -x "www/cgi-bin/setup" "www/cgi-bin/lastchargeid" -d /tmp/extraction_web/
cp -r /tmp/extraction_web/www/. /var/www/html/

# Nettoyage
rm -rf /tmp/extraction_web/

# Ajustement des permissions pour le serveur web
chown -R www-data:www-data /var/www/html/
# On s'assure que les fichiers existants de config restent modifiables par l'interface
[ -f /var/www/html/cgi-bin/setup ] && chmod 666 /var/www/html/cgi-bin/setup
[ -f /var/www/html/cgi-bin/lastchargeid ] && chmod 666 /var/www/html/cgi-bin/lastchargeid

echo -e "${GREEN}✅ Mise à jour terminée avec succès sans écraser votre configuration.${NC}"
