#!/bin/bash

# ==============================================================================
# CONFIGURATION
# ==============================================================================
LOGFILE="/media/Archive01/log.dat"
TARGET_DIR="/media/Archive01"
MAX_LOG_SIZE=$((500 * 1024 * 1024))   # 500 MB
DRY_RUN=0

# ==============================================================================
# COULEURS TERMINAL
# ==============================================================================
C_RESET="\e[0m"
C_GREEN="\e[32m"
C_RED="\e[31m"
C_YELLOW="\e[33m"
C_BLUE="\e[34m"

log_term() { echo -e "${1}${2}${C_RESET}"; }
log_file() { echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOGFILE"; }

# ==============================================================================
# ROTATION DES LOGS
# ==============================================================================
rotate_logs() {
    if [[ -f "$LOGFILE" ]]; then
        local size
        size=$(stat -c%s "$LOGFILE")
        if (( size > MAX_LOG_SIZE )); then
            mv "$LOGFILE" "${LOGFILE}.old"
            touch "$LOGFILE"
            log_term "$C_YELLOW" "[LOG] Rotation effectuée (log.dat.old)"
            log_file "Rotation des logs effectuée."
        fi
    fi
}
rotate_logs

# ==============================================================================
# VÉRIFICATION DU DISQUE
# ==============================================================================
if ! mountpoint -q "$TARGET_DIR"; then
    log_term "$C_RED" "[ERREUR] Le disque $TARGET_DIR n'est pas monté."
    log_file "ERREUR : disque non monté."
    exit 1
fi

# ==============================================================================
# FONCTION RSYNC
# ==============================================================================
sync_data() {
    local source="$1"
    local destination="$2"
    shift 2
    local filters=("$@")

    mkdir -p "$destination"

    log_term "$C_BLUE" "[SYNC] $source → $destination"
    log_file "Début copie : $source -> $destination"

    local rsync_args=(-av --log-file="$LOGFILE")
    [[ $DRY_RUN -eq 1 ]] && rsync_args+=("--dry-run")

    for f in "${filters[@]}"; do
        rsync_args+=(--filter="$f")
    done

    if ionice -c 3 nice -n 19 rsync "${rsync_args[@]}" "$source" "$destination"; then
        log_term "$C_GREEN" "[OK] $source"
        log_file "Succès : $source"
    else
        log_term "$C_RED" "[ERREUR] $source"
        log_file "ERREUR : copie échouée"
    fi

    echo "---------------------------------------------------" >> "$LOGFILE"
}

# ==============================================================================
# COPIES DIRECTES (sans filtre)
# ==============================================================================
sync_data "/media/Runable/Docker/"            "/media/Archive01/Runable/Docker/"
sync_data "/media/Runable/DownBox/SeedBox/"   "/media/Archive01/Runable/DownBox/"
sync_data "/media/Docs01/Photographie/"       "/media/Archive01/Docs/Photographie/"
sync_data "/media/Docs01/Musiques/"           "/media/Archive01/Docs/Musiques/"
sync_data "/media/Docs01/Jeux/"               "/media/Archive01/Docs/Jeux/"
sync_data "/media/Docs01/18/"                 "/media/Archive01/Docs/18/"
sync_data "/var/www/html/"                    "/media/Archive01/www/"

# ==============================================================================
# FILMS : uniquement fichiers contenant "-A"
# ==============================================================================
sync_data "/media/Films01/" "/media/Archive01/Films/" \
    "+ */" \
    "+ *-A*" \
    "- *"

sync_data "/media/Films02/" "/media/Archive01/Films/" \
    "+ */" \
    "+ *-A*" \
    "- *"

# ==============================================================================
# SERIES : pré‑sélection stricte des dossiers finissant par -A
# ==============================================================================
copy_series() {
    local src="$1"
    local dst="$2"

    log_term "$C_BLUE" "[SCAN] Recherche des séries -A dans $src"
    log_file "Scan séries -A dans $src"

    find "$src" -maxdepth 1 -type d -name "*-A" | while read -r serie; do
        local name
        name=$(basename "$serie")

        log_term "$C_GREEN" "[SERIE] $name"
        log_file "Copie série : $name"

        sync_data "$serie/" "$dst/$name/"
    done
}

copy_series "/media/Series01/" "/media/Archive01/Series/"
copy_series "/media/Series02/" "/media/Archive01/Series/"
copy_series "/media/Series03/" "/media/Archive01/Series/"

log_term "$C_GREEN" "[FIN] Archivage terminé."
log_file "Archivage terminé."
