#!/bin/bash
# installweb.sh - Mise à jour TeslaMate Mail depuis /tmp/files.zip
# Ne modifie JAMAIS les valeurs existantes dans cgi-bin/setup
# téléchargez files.zip dans /tmp et lancez bash /tmp/installwebtmp.sh

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
    # Ignorer le setup du zip
    if [[ "$rel" == "cgi-bin/setup" ]] || [[ "$rel" == */cgi-bin/setup ]]; then
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
    # Lire les options activées dans le setup EXISTANT
    mqtt_enabled=$(grep -i "^mqtt_enabled=True" "$SETUP" 2>/dev/null && echo "yes" || echo "no")
    telegram_enabled=$(grep -i "^telegram_enabled=True" "$SETUP" 2>/dev/null && echo "yes" || echo "no")
    email_enabled=$(grep -i "^email_enabled=True" "$SETUP" 2>/dev/null && echo "yes" || echo "no")

    echo "  mqtt_enabled=$mqtt_enabled / telegram_enabled=$telegram_enabled / email_enabled=$email_enabled"

    # Pour chaque clé du setup de référence
    while IFS='=' read -r key value; do
        # Ignorer les commentaires et lignes vides
        [[ "$key" =~ ^#.*$ ]] && continue
        [[ -z "$key" ]] && continue

        key_lower=$(echo "$key" | tr '[:upper:]' '[:lower:]' | xargs)

        # Ignorer github_sha et github_size (gérés par tesla.php)
        [[ "$key_lower" == "github_sha" ]] && continue
        [[ "$key_lower" == "github_size" ]] && continue

        # Vérifier si la clé existe déjà dans le setup existant
        if grep -qi "^${key_lower}\s*=" "$SETUP" 2>/dev/null; then
            echo "  EXISTE déjà : $key_lower"
            continue
        fi

        # Clés conditionnelles selon les options activées
        if [[ "$key_lower" == mqtt_* ]] && [[ "$mqtt_enabled" == "no" ]]; then
            echo "  IGNORÉ (mqtt désactivé) : $key_lower"
            continue
        fi
        if [[ "$key_lower" == "telegram_bot_token" ]] && [[ "$telegram_enabled" == "no" ]]; then
            echo "  IGNORÉ (telegram désactivé) : $key_lower"
            continue
        fi
        if [[ "$key_lower" == "notification_email" ]] && [[ "$email_enabled" == "no" ]]; then
            echo "  IGNORÉ (email désactivé) : $key_lower"
            continue
        fi

        # Ajouter la clé manquante
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

# --- 7. Nettoyage ---
rm -rf "$TMPDIR"
rm -f "$ZIP"
rm -f "/tmp/installweb.sh"
echo "Nettoyage /tmp effectué"

echo "=== Mise à jour terminée ==="
