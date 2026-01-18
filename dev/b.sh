#!/bin/bash

# --- CONFIGURATION ---
# On reprend tes variables extraites
DB_USER="teslamate"
DB_PASS="1nIFcUVUHtMKr"
DB_NAME="teslamate"
DB_HOST="127.0.0.1"

echo "--- TEST DE CONNEXION POSTGRESQL ---"
echo "Utilisateur : $DB_USER"
echo "Base        : $DB_NAME"
echo "Hôte        : $DB_HOST"
echo "------------------------------------"

# Test 1: Simple tentative de connexion (SELECT 1)
echo -n "[1/2] Test d'authentification... "
PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -w -c "SELECT 1" > /dev/null 2>&1

if [ $? -eq 0 ]; then
    echo "SUCCÈS ✅"
else
    echo "ÉCHEC ❌"
    echo "Erreur : Impossible de se connecter. Vérifie le mot de passe ou les droits de l'utilisateur."
    exit 1
fi

# Test 2: Vérification de la table TeslaMate
echo -n "[2/2] Test d'accès aux données (charging_processes)... "
COUNT=$(PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -w -t -c "SELECT count(*) FROM public.charging_processes" | xargs)

if [ $? -eq 0 ]; then
    echo "SUCCÈS ✅ ($COUNT charges trouvées)"
else
    echo "ÉCHEC ❌"
    echo "Erreur : La connexion a réussi mais la table est inaccessible."
fi

echo "------------------------------------"
