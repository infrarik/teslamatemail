#!/bin/bash
# installweb.sh - Mise à jour TeslaMate Mail depuis /tmp/files.zip
# Ne modifie JAMAIS les valeurs existantes dans cgi-bin/setup

ZIP="/tmp/files.zip"
WEBROOT="/var/www/html"
SETUP="$WEBROOT/cgi-bin/setup"
VERSION_DEST="$WEBROOT/cgi-bin/version"
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

# --- 3. Copie directe du fichier version ---
VERSION_IN_ZIP=$(unzip -l "$ZIP" | grep "cgi-bin/version" | awk '{print $NF}')
if [ -n "$VERSION_IN_ZIP" ]; then
    unzip -p "$ZIP" "$VERSION_IN_ZIP" > "$VERSION_DEST"
    echo "Version installée : $(cat $VERSION_DEST)"
else
    echo "Pas de fichier version dans le zip"
fi

# --- 4. Sauvegarde du setup existant ---
if [ -f "$SETUP" ]; then
    cp "$SETUP" "$SETUP.bak"
    echo "Setup sauvegardé : $SETUP.bak"
fi

# --- 5. Copie des fichiers web (SANS cgi-bin/setup et cgi-bin/version) ---
echo "Copie des fichiers vers $WEBROOT..."
find "$TMPDIR" -type f | while read src; do
    rel="${src#$TMPDIR/}"
    # Ignorer les fichiers hors www/
    if [[ "$rel" != www/* ]]; then
        echo "  IGNORÉ (hors www) : $rel"
        continue
    fi
    # Stripper le préfixe www/
    rel="${rel#www/}"
    # Ignorer setup et version (traités séparément)
    if [[ "$rel" == "cgi-bin/setup" ]] || [[ "$rel" == "cgi-bin/version" ]]; then
        echo "  IGNORÉ : $rel"
        continue
    fi
    dest="$WEBROOT/$rel"
    mkdir -p "$(dirname "$dest")"
    cp "$src" "$dest"
    echo "  OK : $rel"
done

# --- 6. Mise à jour du setup : ajout des clés manquantes ---
echo "Vérification des clés setup..."
ZIP_SETUP=$(find "$TMPDIR" -name "setup" -path "*/cgi-bin/*" | head -1)

if [ -z "$ZIP_SETUP" ]; then
    echo "Pas de setup de référence dans le zip, on passe."
else
    while IFS='=' read -r key value; do
        [[ "$key" =~ ^#.*$ ]] && continue
        [[ -z "$key" ]] && continue
        key_lower=$(echo "$key" | tr '[:upper:]' '[:lower:]' | xargs)
        [[ "$key_lower" == "github_sha" ]] && continue
        [[ "$key_lower" == "github_size" ]] && continue
        if grep -qi "^${key_lower}\s*=" "$SETUP" 2>/dev/null; then
            echo "  EXISTE déjà : $key_lower"
            continue
        fi
        echo "$key_lower=$value" >> "$SETUP"
        echo "  AJOUTÉ : $key_lower=$value"
    done < "$ZIP_SETUP"
fi

# --- 7. Mise à jour de la date dans le setup ---
DATE_NOW=$(date '+%Y-%m-%d %H:%M:%S')
if grep -q "^### TeslaMate Config Updated" "$SETUP"; then
    sed -i "s|^### TeslaMate Config Updated.*|### TeslaMate Config Updated - $DATE_NOW ###|" "$SETUP"
else
    sed -i "1s|^|### TeslaMate Config Updated - $DATE_NOW ###\n|" "$SETUP"
fi
echo "Date mise à jour dans setup : $DATE_NOW"

# --- 8. Redémarrage Apache si nécessaire ---
if ! php -r "curl_init();" 2>/dev/null || ! grep -q "PrivateTmp=false" "$OVERRIDE" 2>/dev/null; then
    echo "Redémarrage Apache..."
    systemctl restart apache2
    echo "Apache redémarré"
fi

# --- 9. Nettoyage (répertoire temporaire uniquement) ---
rm -rf "$TMPDIR"
echo "Répertoire temporaire supprimé"
echo "files.zip et installweb.sh conservés dans /tmp"

echo "=== Mise à jour terminée ==="
