#!/bin/bash

# Dossier web racine pour noVNC
WEB_ROOT="/opt/novnc"
TOKEN_FILE="/tokens/tokens.txt"

echo "=== Démarrage Multi-Port VNC Proxy ==="

# 1. Lancer un serveur web simple pour servir les fichiers statiques (vnc.html, etc.) sur le port 8080
echo "[-] Démarrage serveur web statique sur 8080..."
cd $WEB_ROOT
python3 -m http.server 8080 &

# 2. Boucle principale de surveillance
# Vérifie le fichier tokens toutes les 5 secondes et recharge si changé
LAST_MD5=""

while true; do
    if [ -f "$TOKEN_FILE" ]; then
        CURRENT_MD5=$(md5sum "$TOKEN_FILE" | awk '{print $1}')
        
        if [ "$CURRENT_MD5" != "$LAST_MD5" ]; then
            echo "[-] Changement détecté dans $TOKEN_FILE (ou initialisation)..."
            
            # Tuer les anciens processus websockify (sauf le serveur http)
            pkill -f "websockify"
            # Petit hack: pkill a peut-être tué le python http.server s'il contient "websockify" dans le nom ? 
            # Non, python3 -m http.server est safe.
            
            echo "[-] Rechargement des proxies..."
            
            while IFS= read -r line || [ -n "$line" ]; do
                [[ -z "$line" ]] && continue
                [[ "$line" =~ ^#.*$ ]] && continue
                
                # Format: LISTENING_PORT: TARGET_IP:TARGET_PORT
                LISTEN_PORT=$(echo "$line" | cut -d':' -f1 | tr -d ' ')
                TARGET=$(echo "$line" | cut -d':' -f2-3 | tr -d ' ')
                
                if [[ ! -z "$LISTEN_PORT" && ! -z "$TARGET" ]]; then
                    echo "[+] Lancement Proxy : :$LISTEN_PORT -> $TARGET"
                    websockify "$LISTEN_PORT" "$TARGET" &
                fi
                
            done < "$TOKEN_FILE"
            
            LAST_MD5="$CURRENT_MD5"
            echo "[=] Configuration appliquée."
        fi
    else
        echo "[!] En attente du fichier $TOKEN_FILE..."
    fi
    sleep 5
done
