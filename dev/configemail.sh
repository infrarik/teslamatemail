#!/bin/bash

################################################################################
# Script de RECONFIGURATION EMAIL TeslaMate Mail
# Version 1.0 - Uniquement pour corriger les soucis SMTP
################################################################################

set -e

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
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
read -p "Hostname du serveur (ex: teslamate.monserveur.fr) : " HOSTNAME
HOSTNAME=${HOSTNAME:-teslamate.local}

read -p "Serveur SMTP (ex: mail.monserveur.fr) : " SMTP_HOST
if [ -z "$SMTP_HOST" ]; then echo -e "${RED}✗ Requis${NC}"; exit 1; fi

read -p "Port SMTP (465 ou 587) [465] : " SMTP_PORT
SMTP_PORT=${SMTP_PORT:-465}

echo "Type de sécurité : 1) SMTPS (465)  2) STARTTLS (587)"
read -p "Choix [1] : " SECURITY_TYPE
SECURITY_TYPE=${SECURITY_TYPE:-1}

read -p "Login SMTP : " SMTP_USER
read -sp "Mot de passe SMTP : " SMTP_PASS
echo ""
read -p "Email expéditeur : " SMTP_FROM
read -p "Email destinataire par défaut : " DEFAULT_EMAIL

# ============================================================================
# CONFIGURATION POSTFIX
# ============================================================================
echo -e "${YELLOW}→ Configuration de Postfix...${NC}"

DOMAIN=$(echo "$SMTP_FROM" | cut -d'@' -f2)

# Configuration main.cf
if [ "$SECURITY_TYPE" = "1" ]; then
    # SMTPS (Port 465)
    cat > /etc/postfix/main.cf <<EOF
myhostname = $HOSTNAME
mydomain = $DOMAIN
myorigin = \$mydomain
relayhost = [$SMTP_HOST]:$SMTP_PORT
smtp_sasl_auth_enable = yes
smtp_sasl_password_maps = hash:/etc/postfix/sasl_passwd
smtp_sasl_security_options = noanonymous
smtp_sasl_mechanism_filter = plain, login
smtp_tls_security_level = encrypt
smtp_tls_wrappermode = yes
smtp_tls_CAfile = /etc/ssl/certs/ca-certificates.crt
smtp_generic_maps = hash:/etc/postfix/generic
sender_canonical_maps = hash:/etc/postfix/sender_canonical
EOF
else
    # STARTTLS (Port 587)
    cat > /etc/postfix/main.cf <<EOF
myhostname = $HOSTNAME
mydomain = $DOMAIN
myorigin = \$mydomain
relayhost = [$SMTP_HOST]:$SMTP_PORT
smtp_sasl_auth_enable = yes
smtp_sasl_password_maps = hash:/etc/postfix/sasl_passwd
smtp_sasl_security_options = noanonymous
smtp_sasl_mechanism_filter = plain, login
smtp_use_tls = yes
smtp_tls_security_level = encrypt
smtp_tls_CAfile = /etc/ssl/certs/ca-certificates.crt
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
postmap /etc/postfix/generic

cat > /etc/postfix/sender_canonical <<EOF
root $SMTP_FROM
@$HOSTNAME $SMTP_FROM
EOF
postmap /etc/postfix/sender_canonical

# Aliases
sed -i "/^root:/d" /etc/aliases
echo "root: $DEFAULT_EMAIL" >> /etc/aliases
newaliases

# Redémarrage
systemctl restart postfix
echo -e "${GREEN}✓ Postfix reconfiguré.${NC}"

# ============================================================================
# TEST FINAL
# ============================================================================
read -p "Envoyer un email de test à $DEFAULT_EMAIL ? (O/n) : " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Nn]$ ]]; then
    echo "Test de reconfiguration TeslaMate Mail" | mail -s "Test Reconfig Email" -r "$SMTP_FROM" "$DEFAULT_EMAIL"
    echo -e "${GREEN}✓ Email envoyé (vérifiez vos spams ou /var/log/mail.log).${NC}"
fi
