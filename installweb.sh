#!/bin/bash

# Nom de l'archive
ARCHIVE="files.zip"

# Vérification de la présence de l'archive
if [ ! -f "$ARCHIVE" ]; then
    echo "Erreur : Le fichier $ARCHIVE est introuvable dans le répertoire courant."
    exit 1
fi

# Vérification des droits root (nécessaire pour écrire dans /root et /var/www)
if [ "$EUID" -ne 0 ]; then 
  echo "Veuillez exécuter ce script en tant que root ou avec sudo."
  exit 1
fi

echo "Début du déploiement..."

# 1. Extraction du contenu du dossier 'root' de l'archive vers /root
# On utilise -j (junk paths) si on veut aplatir, mais ici on extrait le contenu 
# du dossier interne 'root/' vers la racine du système /root/
unzip -o "$ARCHIVE" "root/*" -d /tmp/extraction_web/
cp -r /tmp/extraction_web/root/. /root/

# 2. Extraction du contenu du dossier 'www' de l'archive vers /var/www/html
unzip -o "$ARCHIVE" "www/*" -d /tmp/extraction_web/
cp -r /tmp/extraction_web/www/. /var/www/html/

# Nettoyage du dossier temporaire
rm -rf /tmp/extraction_web/

# Ajustement optionnel des permissions pour le web
chown -R www-data:www-data /var/www/html/

echo "Déploiement terminé avec succès."

