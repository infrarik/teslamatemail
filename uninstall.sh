#!/bin/bash

################################################################################
# Script de DÉSINSTALLATION TOTALE TeslaMate Mail
# Version 1.1 - Nettoyage et Purge des paquets
################################################################################

set -e

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

clear
echo -e "${RED}╔════════════════════════════════════════════════════════╗${NC}"
echo -e "${RED}║       DÉSINSTALLATION TOTALE (PURGE)                  ║${NC}"
echo -e "${RED}╚════════════════════════════════════════════════════════╝${NC}"
echo ""

# Vérifier si root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}❌ Ce script doit être exécuté en tant que root${NC}"
    exit 1
fi

echo -e "${RED}⚠ ATTENTION : Cela va supprimer Apache, PHP, Mosquitto, Postfix et tous les fichiers web !${NC}"
read -p "Confirmer la suppression totale ? (o/N) : " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Oo]$ ]]; then
    echo "Désinstallation annulée."
    exit 0
fi

# 1. Arrêt des services
echo -e "${YELLOW}→ Arrêt des services...${NC}"
systemctl stop apache2 mosquitto postfix 2>/dev/null || true

# 2. Suppression du Cron
echo -e "${YELLOW}→ Suppression de la tâche Cron...${NC}"
crontab -l 2>/dev/null | grep -v "teslacharge.sh" | crontab - || true

# 3. Suppression des fichiers de l'application
echo -e "${YELLOW}→ Suppression des scripts et fichiers web...${NC}"
rm -f /root/teslacharge.sh
rm -f /root/send_test_mail.sh
rm -rf /var/www/html/*
rm -f /var/log/teslacharge.log*
rm -f /etc/logrotate.d/teslacharge

# 4. Purge des paquets (Logiciels + Dépendances)
echo -e "${YELLOW}→ Purge des paquets (Apache, PHP, Mosquitto, Postfix, PostgreSQL client)...${NC}"
apt purge -y \
    apache2 \
    apache2-bin \
    php* \
    mosquitto \
    mosquitto-clients \
    postfix \
    postgresql-client \
    mailutils \
    libapache2-mod-php

# 5. Nettoyage des dossiers de configuration résiduels
echo -e "${YELLOW}→ Nettoyage des répertoires système...${NC}"
rm -rf /etc/apache2
rm -rf /etc/php
rm -rf /etc/mosquitto
rm -rf /etc/postfix
rm -rf /var/lib/mosquitto
rm -rf /var/lib/postfix

# 6. Autoremove pour les dépendances inutiles
echo -e "${YELLOW}→ Nettoyage final des dépendances orphelines...${NC}"
apt autoremove -y
apt autoclean

echo ""
echo -e "${GREEN}✓ Le système a été totalement nettoyé.${NC}"
echo -e "${CYAN}Note : Les données Docker de TeslaMate (base de données interne) n'ont pas été touchées.${NC}"

