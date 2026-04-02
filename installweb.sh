#!/bin/bash
# installweb.sh - Mise à jour TeslaMate Mail depuis /tmp/files.zip
# Ne modifie JAMAIS les valeurs existantes dans cgi-bin/setup

ZIP="/tmp/files.zip"
WEBROOT="/var/www/html"
SETUP="$WEBROOT/cgi-bin/setup"
TMPDIR="/tmp/teslamate_update"

echo "=== TeslaMate Mail Update ==="
date

# --- 1. Vérifications ---
if [ ! -f "$ZIP" ]; then
    echo "ERREUR : $ZIP introuvable"
    exit 1
fi

if ! command -v unzip &>/dev/null; then
    echo "ERREUR : unzip non installé"
    exit 1
fi

# Vérification php-curl
if ! php -r "curl_init();" 2>/dev/null; then
    echo "php-curl manquant, installation..."
    apt install -y php-curl
    echo "php-curl installé"
else
    echo "php-curl : OK"
fi

# Vérification PrivateTmp Apache (accès /tmp depuis PHP)
OVERRIDE="/etc/systemd/system/apache2.service.d/override.conf"
if [ ! -f "$OVERRIDE" ] || ! grep -q "PrivateTmp=false" "$OVERRIDE"; then
    echo "PrivateTmp non configuré, correction..."
    mkdir -p /etc/systemd/system/apache2.service.d/
    echo -e "[Service]\nPrivateTmp=false" > "$OVERRIDE"
    systemctl daemon-reload
    echo "PrivateTmp désactivé"
else
    echo "PrivateTmp=false : OK"
fi

# --- 2. Extraction du zip ---
rm -rf "$TMPDIR"
mkdir -p "$TMPDIR"
unzip -q "$ZIP" -d "$TMPDIR"
echo "ZIP extrait dans $TMPDIR"

# --- 3. Sauvegarde du setup existant ---
if [ -f "$SETUP" ]; then
    cp "$SETUP" "$SETUP.bak"
    echo "Setup sauvegardé : $SETUP.bak"
fi

# --- 4. Copie des fichiers web (SANS cgi-bin/setup) ---
echo "Copie des fichiers vers $WEBROOT..."
find "$TMPDIR" -type f | while read src; do
    rel="${src#$TMPDIR/}"
    # Ignorer les fichiers hors www/ (install.sh, README.md, etc.)
    if [[ "$rel" != www/* ]]; then
        echo "  IGNORÉ (hors www) : $rel"
        continue
    fi
    # Stripper le préfixe www/
    rel="${rel#www/}"
    # Ignorer le setup du zip
    if [[ "$rel" == "cgi-bin/setup" ]]; then
        echo "  IGNORÉ : $rel"
        continue
    fi
    dest="$WEBROOT/$rel"
    mkdir -p "$(dirname "$dest")"
    cp "$src" "$dest"
    echo "  OK : $rel"
done

# --- 5. Mise à jour du setup : ajout des clés manquantes ---
echo "Vérification des clés setup..."

# Chercher le setup de référence dans le zip
ZIP_SETUP=$(find "$TMPDIR" -name "setup" -path "*/cgi-bin/*" | head -1)

if [ -z "$ZIP_SETUP" ]; then
    echo "Pas de setup de référence dans le zip, on passe."
else
    # Pour chaque clé du setup de référence
    while IFS='=' read -r key value; do
        # Ignorer les commentaires et lignes vides
        [[ "$key" =~ ^#.*$ ]] && continue
        [[ -z "$key" ]] && continue

        key_lower=$(echo "$key" | tr '[:upper:]' '[:lower:]' | xargs)

        # Ignorer github_sha et github_size (gérés par tesla.php)
        [[ "$key_lower" == "github_sha" ]] && continue
        [[ "$key_lower" == "github_size" ]] && continue

        # Si la clé existe déjà → on n'y touche pas
        if grep -qi "^${key_lower}\s*=" "$SETUP" 2>/dev/null; then
            echo "  EXISTE déjà : $key_lower"
            continue
        fi

        # Clé manquante → on l'ajoute avec la valeur par défaut du zip
        echo "$key_lower=$value" >> "$SETUP"
        echo "  AJOUTÉ : $key_lower=$value"

    done < "$ZIP_SETUP"
fi

# --- 6. Mise à jour de la date dans le setup ---
DATE_NOW=$(date '+%Y-%m-%d %H:%M:%S')
if grep -q "^### TeslaMate Config Updated" "$SETUP"; then
    sed -i "s|^### TeslaMate Config Updated.*|### TeslaMate Config Updated - $DATE_NOW ###|" "$SETUP"
else
    sed -i "1s|^|### TeslaMate Config Updated - $DATE_NOW ###\n|" "$SETUP"
fi
echo "Date mise à jour dans setup : $DATE_NOW"

# --- 7. Redémarrage Apache si des corrections ont été appliquées ---
if ! php -r "curl_init();" 2>/dev/null || ! grep -q "PrivateTmp=false" "$OVERRIDE" 2>/dev/null; then
    echo "Redémarrage Apache..."
    systemctl restart apache2
    echo "Apache redémarré"
fi

# --- 8. Nettoyage ---
rm -rf "$TMPDIR"
rm -f "$ZIP"
rm -f "/tmp/installweb.sh"
echo "Nettoyage /tmp effectué"

echo "=== Mise à jour terminée ==="
