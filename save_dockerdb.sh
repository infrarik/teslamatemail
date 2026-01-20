#!/bin/bash
#
# Sauvegarde de la base de données de docker et teslamate
# Le script va chercher où se trouve le fichier avant de lancer
# la sauvegarde.
#

# Couleurs pour l'affichage
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}[7/8] Configuration Docker${NC}"

# --- 1. Recherche de l'emplacement du fichier ---
DOCKER_COMPOSE_PATH=""
# Liste des chemins à tester
for path in "/opt/teslamate/docker-compose.yml" "/home/$USER/teslamate/docker-compose.yml" "./docker-compose.yml"; do
    if [ -f "$path" ]; then 
        DOCKER_COMPOSE_PATH="$path"
        break
    fi
done

# Vérification si le fichier a été trouvé
if [ -z "$DOCKER_COMPOSE_PATH" ]; then
    echo -e "${RED}Erreur : Impossible de trouver docker-compose.yml dans les emplacements standard.${NC}"
    exit 1
fi

# Définition du répertoire de sauvegarde basé sur l'emplacement du fichier trouvé
BASE_DIR=$(dirname "$DOCKER_COMPOSE_PATH")
BACKUP_DIR="$BASE_DIR/backups"

echo -e "Fichier trouvé dans : ${GREEN}$BASE_DIR${NC}"

# --- 2. Extraction des informations de connexion ---
# On extrait les valeurs depuis le fichier YAML
DB_USER=$(grep "POSTGRES_USER=" "$DOCKER_COMPOSE_PATH" | cut -d'=' -f2 | tr -d '[:space:]')
DB_PASS=$(grep "POSTGRES_PASSWORD=" "$DOCKER_COMPOSE_PATH" | cut -d'=' -f2 | tr -d '[:space:]')
DB_NAME=$(grep "POSTGRES_DB=" "$DOCKER_COMPOSE_PATH" | cut -d'=' -f2 | tr -d '[:space:]')

# Vérification de l'extraction
if [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_NAME" ]; then
    echo -e "${RED}Erreur : Impossible d'extraire les identifiants du fichier YAML.${NC}"
    exit 1
fi

# --- 3. Configuration de la sauvegarde ---
mkdir -p "$BACKUP_DIR"
DATE=$(date +"%Y-%m-%d_%H-%M-%S")
BACKUP_FILE="$BACKUP_DIR/teslamate_db_$DATE.sql"
SERVICE_NAME="database"

echo -e "Lancement de la sauvegarde dans : ${GREEN}$BACKUP_DIR${NC}"

# --- 4. Exécution du dump ---
# Utilisation de PGPASSWORD pour éviter la demande interactive de mot de passe
export PGPASSWORD=$DB_PASS

# Commande de sauvegarde via Docker Compose
# -T retire le terminal interactif pour la redirection de flux
docker compose -f "$DOCKER_COMPOSE_PATH" exec -T "$SERVICE_NAME" pg_dump -U "$DB_USER" "$DB_NAME" > "$BACKUP_FILE"

# --- 5. Vérification finale ---
if [ $? -eq 0 ] && [ -s "$BACKUP_FILE" ]; then
    echo -e "${GREEN}Sauvegarde réussie !${NC}"
    echo -e "Fichier : ${GREEN}$(basename "$BACKUP_FILE")${NC}"
    echo -e "Taille : ${GREEN}$(du -h "$BACKUP_FILE" | cut -f1)${NC}"
else
    echo -e "${RED}Erreur lors de la génération du fichier SQL.${NC}"
    # On supprime le fichier s'il est vide ou s'il y a eu une erreur
    [ -f "$BACKUP_FILE" ] && rm "$BACKUP_FILE"
    exit 1
fi

