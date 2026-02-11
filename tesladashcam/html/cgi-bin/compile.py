#!/usr/bin/env python3
import os
import sys
import subprocess

def compile_tesla_videos(directory):
    if not os.path.exists(directory):
        print(f"Erreur : Le répertoire {directory} n'existe pas.")
        return

    # Chemin validé par tes soins
    ffmpeg_bin = '/usr/bin/ffmpeg'

    cameras = {
        'front': 'front.mp4',
        'back': 'back.mp4',
        'left_repeater': 'left.mp4',
        'right_repeater': 'right.mp4'
    }

    print(f"--- Début de la compilation dans : {directory} ---")

    for cam_key, output_name in cameras.items():
        # Sélection des segments
        files = [f for f in os.listdir(directory) if f.endswith(f'-{cam_key}.mp4')]
        files.sort()

        if not files:
            print(f"Aucun fichier trouvé pour : {cam_key}")
            continue

        print(f"Traitement de {cam_key} ({len(files)} segments)...")

        # Création de la liste de concaténation
        list_file_path = os.path.join(directory, f"concat_list_{cam_key}.txt")
        with open(list_file_path, 'w') as f:
            for file_name in files:
                f.write(f"file '{file_name}'\n")

        output_path = os.path.join(directory, output_name)
        
        # Commande de fusion sans ré-encodage
        cmd = [
            ffmpeg_bin, '-y', '-f', 'concat', '-safe', '0',
            '-i', list_file_path,
            '-c', 'copy',
            output_path
        ]

        try:
            # On capture la sortie pour le retour PHP
            subprocess.run(cmd, check=True, capture_output=True, text=True)
            print(f"Succès : {output_name} généré.")
        except subprocess.CalledProcessError as e:
            print(f"Erreur FFmpeg [{cam_key}]: {e.stderr}")
        finally:
            if os.path.exists(list_file_path):
                os.remove(list_file_path)

    print("--- Compilation terminée ---")

if __name__ == "__main__":
    if len(sys.argv) > 1:
        target_dir = sys.argv[1]
        compile_tesla_videos(target_dir)
    else:
        print("Usage: python3 compile.py <repertoire_des_videos>")

