FROM python:3.11-slim

# Installation de websockify et noVNC
RUN apt-get update && apt-get install -y \
    git \
    && rm -rf /var/lib/apt/lists/* \
    && pip install --no-cache-dir websockify

# Cloner noVNC (pour l'interface web uniquement)
WORKDIR /opt
RUN git clone https://github.com/novnc/noVNC.git /opt/novnc

WORKDIR /opt/novnc

# Port d'écoute
EXPOSE 8080

# Copier le script d'entrée
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
