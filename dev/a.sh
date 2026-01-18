#!/bin/bash

# --- CONFIGURATION ---
CONFIGFILE="/var/www/html/cgi-bin/setup"
STATEFILE="/var/www/html/cgi-bin/lastchargeid"
USERSFILE="/var/www/html/cgi-bin/telegram_users.json"

# --- CHARGEMENT DE LA CONFIGURATION SETUP ---
if [ -f "$CONFIGFILE" ]; then
    EMAIL=$(grep -i "^notification_email=" "$CONFIGFILE" | cut -d'=' -f2 | xargs)
    EMAIL_ENABLED=$(grep -i "^email_enabled=" "$CONFIGFILE" | cut -d'=' -f2 | xargs)
    MQTT_ENABLED=$(grep -i "^mqtt_enabled=" "$CONFIGFILE" | cut -d'=' -f2 | xargs)
    TELEGRAM_ENABLED=$(grep -i "^telegram_enabled=" "$CONFIGFILE" | cut -d'=' -f2 | xargs)
    TELEGRAM_TOKEN=$(grep -i "^telegram_bot_token=" "$CONFIGFILE" | cut -d'=' -f2 | xargs)
    
    MQTT_SERVER=$(grep -i "^mqtt_host=" "$CONFIGFILE" | cut -d'=' -f2 | xargs)
    MQTT_PORT=$(grep -i "^mqtt_port=" "$CONFIGFILE" | cut -d'=' -f2 | xargs)
    MQTT_TOPIC=$(grep -i "^mqtt_topic=" "$CONFIGFILE" | cut -d'=' -f2 | xargs)
    MQTT_USER=$(grep -i "^mqtt_user=" "$CONFIGFILE" | cut -d'=' -f2 | xargs)
    MQTT_PASS=$(grep -i "^mqtt_pass=" "$CONFIGFILE" | cut -d'=' -f2 | xargs)
    
    DOCKER_PATH=$(grep -i "^docker_path=" "$CONFIGFILE" | cut -d'=' -f2 | xargs)
    
    [ -z "$MQTT_PORT" ] && MQTT_PORT="1883"
else
    echo "ERREUR: Fichier de config $CONFIGFILE introuvable"
    exit 1
fi

# --- EXTRACTION DES INFOS DB ---
if [ -f "$DOCKER_PATH" ]; then
    # Extraction améliorée pour gérer les formats : VARIABLE=valeur ou VARIABLE: valeur
    DB_USER=$(grep -E "DATABASE_USER|POSTGRES_USER" "$DOCKER_PATH" | head -1 | awk -F'[:=]' '{print $2}' | tr -d '"' | tr -d "'" | xargs)
    DB_PASS=$(grep -E "DATABASE_PASS|POSTGRES_PASSWORD" "$DOCKER_PATH" | head -1 | awk -F'[:=]' '{print $2}' | tr -d '"' | tr -d "'" | xargs)
    DB_NAME=$(grep -E "DATABASE_NAME|POSTGRES_DB" "$DOCKER_PATH" | head -1 | awk -F'[:=]' '{print $2}' | tr -d '"' | tr -d "'" | xargs)
    DB_HOST="127.0.0.1"  

    # Valeurs par défaut uniquement si les variables sont vides après extraction
    [ -z "$DB_USER" ] && DB_USER="teslamate"
    [ -z "$DB_PASS" ] && DB_PASS="secret"
    [ -z "$DB_NAME" ] && DB_NAME="teslamate"

    echo "Tentative de connexion avec : User=$DB_USER | DB=$DB_NAME | Pass=$DB_PASS"
else
    echo "ERREUR: Le fichier spécifié dans DOCKER_PATH ($DOCKER_PATH) est introuvable."
    exit 1
fi

# --- RÉCUPÉRATION DE LA CHARGE ---
# On utilise l'option -w pour ne jamais demander de mot de passe en interactif
NEWID=$(PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -w --no-psqlrc --quiet -t -c "SELECT id FROM public.charging_processes WHERE end_date IS NOT NULL ORDER BY end_date DESC LIMIT 1" | tr -d ' ')

if [ -z "$NEWID" ]; then 
    echo "Aucune charge trouvée ou erreur de connexion."
    exit 0
fi

OLDID=$(cat "$STATEFILE" 2>/dev/null || echo "0")

if [[ "$NEWID" =~ ^[0-9]+$ ]] && [ "$NEWID" -gt "$OLDID" ]; then
    
    # Récupérer les détails
    IFS='|' read -r STARTDATE ENDDATE DURATIONMIN ENERGY STARTSOC ENDSOC <<<"$(PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -w --no-psqlrc --quiet -t -A -F'|' -c "SELECT TO_CHAR(start_date, 'DD/MM/YYYY HH24:MI'), TO_CHAR(end_date, 'DD/MM/YYYY HH24:MI'), ROUND(EXTRACT(EPOCH FROM (end_date - start_date))/60), charge_energy_added, start_battery_level, end_battery_level FROM public.charging_processes WHERE id=$NEWID")"

    # --- ENVOI EMAIL ---
    if [ "$EMAIL_ENABLED" == "True" ] && [ -n "$EMAIL" ]; then
        SUBJECT="TeslaMate: Nouvelle Charge - ${ENERGY}kWh"
        mail -s "$SUBJECT" -r "noreply@teslamate.local" "$EMAIL" <<EOF
DÉTAILS DE LA CHARGE ($NEWID):
Energie: ${ENERGY}kWh (${STARTSOC}% -> ${ENDSOC}%)
Duree: ${DURATIONMIN} min
Fin: ${ENDDATE}
EOF
        echo "=> Email envoyé."
    fi

    # --- ENVOI TELEGRAM ---
    if [ "$TELEGRAM_ENABLED" == "True" ] && [ -n "$TELEGRAM_TOKEN" ] && [ -f "$USERSFILE" ]; then
        CHAT_ID=$(tr -d '[:space:]' < "$USERSFILE" | grep -oP '"chat_id":"\K[0-9]+')
        
        if [ -n "$CHAT_ID" ]; then
            T_MSG="NOUVELLE CHARGE ${ENERGY} kWh
DÉTAILS DE LA CHARGE ($NEWID):
Energie: ${ENERGY}kWh (${STARTSOC}% -> ${ENDSOC}%)
Duree: ${DURATIONMIN} min
Fin: ${ENDDATE}"

            curl -s -X POST "https://api.telegram.org/bot$TELEGRAM_TOKEN/sendMessage" \
                --data-urlencode "chat_id=$CHAT_ID" \
                --data-urlencode "text=$T_MSG" > /dev/null
            echo "=> Notification Telegram envoyée."
        fi
    fi

    # --- PUBLICATION MQTT ---
    if [ "$MQTT_ENABLED" == "True" ] && [ -n "$MQTT_SERVER" ]; then
        AUTH_ARGS=""
        [ -n "$MQTT_USER" ] && AUTH_ARGS="-u $MQTT_USER -P $MQTT_PASS"
        MESSAGE="{\"id\":$NEWID,\"kwh\":$ENERGY,\"soc\":$ENDSOC,\"duration\":$DURATIONMIN}"
        mosquitto_pub -h "$MQTT_SERVER" -p "$MQTT_PORT" $AUTH_ARGS -t "$MQTT_TOPIC" -m "$MESSAGE" -r
        echo "=> Message MQTT publié."
    fi
    
    echo "$NEWID" > "$STATEFILE"
fi
