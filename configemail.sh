#!/bin/bash

################################################################################
# Script de RECONFIGURATION EMAIL TeslaMate Mail
# Version 2.0 - Avec email par défaut personnalisé
################################################################################

set -e

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

clear
echo -e "${BLUE}╔═══════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║       RECONFIGURATION EMAIL TeslaMate Mail            ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""

# Vérifier si root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}✗ Ce script doit être exécuté en tant que root${NC}"
    exit 1
fi

# ============================================================================
# COLLECTE DES INFORMATIONS UTILISATEUR
# ============================================================================

# Hostname
read -p "Hostname du serveur [teslamate.mondomaine.fr] : " HOSTNAME
HOSTNAME=${HOSTNAME:-teslamate.mondomaine.fr}

# Serveur SMTP
read -p "Serveur SMTP [mail.mondomaine.fr] : " SMTP_HOST
SMTP_HOST=${SMTP_HOST:-mail.mondomaine.fr}

# Port SMTP
read -p "Port SMTP (465 ou 587) [465] : " SMTP_PORT
SMTP_PORT=${SMTP_PORT:-465}

# Type de sécurité
echo "Type de sécurité : 1) SMTPS (465)  2) STARTTLS (587)"
read -p "Choix [1] : " SECURITY_TYPE
SECURITY_TYPE=${SECURITY_TYPE:-1}

# Login SMTP
read -p "Login SMTP [alerte@mondomaine.fr] : " SMTP_USER
SMTP_USER=${SMTP_USER:-alerte@mondomaine.fr}

# Mot de passe SMTP
read -sp "Mot de passe SMTP : " SMTP_PASS
echo ""
if [ -z "$SMTP_PASS" ]; then
    echo -e "${RED}✗ Le mot de passe est requis${NC}"
    exit 1
fi

# Email expéditeur
read -p "Email expéditeur [noreply@mondomaine.fr] : " SMTP_FROM
SMTP_FROM=${SMTP_FROM:-noreply@mondomaine.fr}

# Email destinataire avec valeur par défaut personnalisée
read -p "Email destinataire [mon_mail@gmail.com] : " DEFAULT_EMAIL
DEFAULT_EMAIL=${DEFAULT_EMAIL:-mon_mail@gmail.com}

echo ""
echo -e "${CYAN}════════════════════════════════════════════════════════${NC}"
echo -e "${CYAN}Récapitulatif de la configuration :${NC}"
echo -e "${CYAN}════════════════════════════════════════════════════════${NC}"
echo -e "Hostname         : ${YELLOW}$HOSTNAME${NC}"
echo -e "Serveur SMTP     : ${YELLOW}$SMTP_HOST:$SMTP_PORT${NC}"
echo -e "Login SMTP       : ${YELLOW}$SMTP_USER${NC}"
echo -e "Expéditeur       : ${YELLOW}$SMTP_FROM${NC}"
echo -e "Destinataire     : ${YELLOW}$DEFAULT_EMAIL${NC}"
echo -e "Sécurité         : ${YELLOW}$([ "$SECURITY_TYPE" = "1" ] && echo "SMTPS (465)" || echo "STARTTLS (587)")${NC}"
echo -e "${CYAN}════════════════════════════════════════════════════════${NC}"
echo ""
read -p "Confirmer et appliquer cette configuration ? (O/n) : " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Nn]$ ]]; then
    echo -e "${YELLOW}Configuration annulée.${NC}"
    exit 0
fi

# ============================================================================
# CONFIGURATION POSTFIX
# ============================================================================
echo ""
echo -e "${YELLOW}→ Configuration de Postfix...${NC}"

DOMAIN=$(echo "$SMTP_FROM" | cut -d'@' -f2)

# Backup de l'ancienne configuration
if [ -f /etc/postfix/main.cf ]; then
    cp /etc/postfix/main.cf /etc/postfix/main.cf.backup.$(date +%Y%m%d_%H%M%S)
fi

# Configuration main.cf
if [ "$SECURITY_TYPE" = "1" ]; then
    # SMTPS (Port 465)
    cat > /etc/postfix/main.cf <<EOF
# Configuration TeslaMate Mail - $(date)
smtpd_banner = \$myhostname ESMTP
biff = no
append_dot_mydomain = no
readme_directory = no
compatibility_level = 2

# Serveur
myhostname = $HOSTNAME
mydomain = $DOMAIN
myorigin = \$mydomain

# Destinations
mydestination = \$myhostname, localhost.localdomain, localhost
relayhost = [$SMTP_HOST]:$SMTP_PORT

# Réseaux autorisés
mynetworks = 127.0.0.0/8 [::ffff:127.0.0.0]/104 [::1]/128 192.168.1.0/24
mailbox_size_limit = 0
recipient_delimiter = +
inet_interfaces = all
inet_protocols = ipv4

# Authentification SASL
smtp_sasl_auth_enable = yes
smtp_sasl_password_maps = hash:/etc/postfix/sasl_passwd
smtp_sasl_security_options = noanonymous
smtp_sasl_mechanism_filter = plain, login

# TLS/SSL pour port 465 (SMTPS)
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
# Configuration TeslaMate Mail - $(date)
smtpd_banner = \$myhostname ESMTP
biff = no
append_dot_mydomain = no
readme_directory = no
compatibility_level = 2

# Serveur
myhostname = $HOSTNAME
mydomain = $DOMAIN
myorigin = \$mydomain

# Destinations
mydestination = \$myhostname, localhost.localdomain, localhost
relayhost = [$SMTP_HOST]:$SMTP_PORT

# Réseaux autorisés
mynetworks = 127.0.0.0/8 [::ffff:127.0.0.0]/104 [::1]/128 192.168.1.0/24
mailbox_size_limit = 0
recipient_delimiter = +
inet_interfaces = all
inet_protocols = ipv4

# Authentification SASL
smtp_sasl_auth_enable = yes
smtp_sasl_password_maps = hash:/etc/postfix/sasl_passwd
smtp_sasl_security_options = noanonymous
smtp_sasl_mechanism_filter = plain, login

# TLS pour port 587 (STARTTLS)
smtp_use_tls = yes
smtp_tls_security_level = encrypt
smtp_tls_CAfile = /etc/ssl/certs/ca-certificates.crt
smtp_tls_session_cache_database = btree:\${data_directory}/smtp_scache

# Réécriture d'adresse
smtp_generic_maps = hash:/etc/postfix/generic
sender_canonical_maps = hash:/etc/postfix/sender_canonical
EOF
fi

# Authentification
echo "[$SMTP_HOST]:$SMTP_PORT $SMTP_USER:$SMTP_PASS" > /etc/postfix/sasl_passwd
chmod 600 /etc/postfix/sasl_passwd
postmap /etc/postfix/sasl_passwd

# Réécriture d'adresse
cat > /etc/postfix/generic <<EOF
root@$HOSTNAME $SMTP_FROM
@$HOSTNAME $SMTP_FROM
root $SMTP_FROM
EOF
chmod 600 /etc/postfix/generic
postmap /etc/postfix/generic

cat > /etc/postfix/sender_canonical <<EOF
root $SMTP_FROM
@$HOSTNAME $SMTP_FROM
EOF
chmod 600 /etc/postfix/sender_canonical
postmap /etc/postfix/sender_canonical

# Aliases
sed -i "/^root:/d" /etc/aliases 2>/dev/null || true
echo "root: $DEFAULT_EMAIL" >> /etc/aliases
newaliases

# Redémarrage
systemctl restart postfix
echo -e "${GREEN}✓ Postfix reconfiguré avec succès.${NC}"

# ============================================================================
# TEST FINAL
# ============================================================================
echo ""
read -p "Envoyer un email de test à $DEFAULT_EMAIL ? (O/n) : " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Nn]$ ]]; then
    echo -e "${YELLOW}→ Envoi de l'email de test...${NC}"
    
    # Envoi du test via la commande mail
    echo "Test de reconfiguration TeslaMate Mail - $(date)" | mail -s "Test Config Email TeslaMate" -r "$SMTP_FROM" "$DEFAULT_EMAIL"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Email de test envoyé !${NC}"
        echo -e "${CYAN}Vérifiez votre boîte de réception (et vos spams si besoin).${NC}"
    else
        echo -e "${RED}✗ Erreur lors de l'envoi.${NC}"
        echo -e "${YELLOW}Consultez les logs : tail -f /var/log/mail.log${NC}"
    fi
fi

echo ""
echo -e "${GREEN}════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}Configuration terminée !${NC}"
echo -e "${GREEN}════════════════════════════════════════════════════════${NC}"
echo ""
echo "Commandes utiles :"
echo -e "  ${CYAN}Vérifier les logs :${NC} tail -f /var/log/mail.log"
echo -e "  ${CYAN}File d'attente :${NC} mailq"
echo -e "  ${CYAN}Test manuel :${NC} echo 'Test' | mail -s 'Sujet' $DEFAULT_EMAIL"
echo ""
