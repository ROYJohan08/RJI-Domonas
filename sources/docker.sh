#!/bin/bash
set -euo pipefail

# 1. Chargement des variables
CREDENTIALS_FILE="/etc/RJIDomoNas/credentials.sh"

if [[ -f "$CREDENTIALS_FILE" ]]; then
    # shellcheck disable=SC1090
    source "$CREDENTIALS_FILE"
else
    echo "❌ Erreur : $CREDENTIALS_FILE introuvable."
    exit 1
fi

# 2. Vérification des privilèges Root
if [[ "$EUID" -ne 0 ]]; then
    echo "❌ Droits insuffisants. Exécute ce script en root."
    exit 1
fi

# 3. Fonction utilitaire : suppression propre d’un conteneur
remove_container() {
    local name="$1"
    if docker ps -a --format '{{.Names}}' | grep -q "^${name}$"; then
        sudo docker rm -f "$name"
    fi
}

# 4. Fonction utilitaire : nettoyage git
clean_git() {
    sudo rm -rf transmission-web-control/
}

# 5. Gestion des services
case "$1" in
	"lamp")
		remove_container lamp
		sudo docker pull fauria/lamp:latest
		sudo docker run -d \
			--name lamp \
			--restart=unless-stopped \
			-e TZ=CET \
			-v "$PathDkLamp:/var/www/html" \
			-v /media/Docs01/Maps/20260726_Europa.pmtiles:/var/www/html/europe.pmtiles:ro \
			-p 80:80 \
			-p 3306:3306 \
			fauria/lamp:latest
	;;
	"homeassistant")
		remove_container homeassistant
		sudo docker pull homeassistant/home-assistant:latest
		sudo docker run -d \
			--name homeassistant \
			--privileged \
			--restart=unless-stopped \
			--network=host \
			-e TZ=CET \
			-v "$PathDkHomeAssistant:/config" \
			--device /dev/dri:/dev/dri \
			-v /etc/localtime:/etc/localtime:ro \
			-p "$PortHomeAssistant:8123" \
			homeassistant/home-assistant:latest
	;;
	"jellyfin")
		remove_container jellyfin
		sudo docker pull jellyfin/jellyfin:latest
		sudo docker run -d \
			--name jellyfin \
			--restart=unless-stopped \
			-e TZ=CET \
			-v "$PathDkJellyFin1:/config" \
			-v "$PathDkJellyFin3:/media" \
			-v "$PathDkJellyFin2:/cache" \
			-p "$PortJellyFin:8096" \
			-p 8920:8920 \
			-e PUID="$USER_ID" \
			-e PGID="$GROUP_ID" \
			--device /dev/dri/renderD128:/dev/dri/renderD128 \
			jellyfin/jellyfin:latest
	;;
	"downbox")
		remove_container transmission
		remove_container downboxproxy
		sudo docker pull haugene/transmission-openvpn:latest
		sudo docker pull haugene/transmission-openvpn-proxy:latest
		sudo docker run -d \
			--name transmission \
			--privileged \
			--restart=unless-stopped \
			-e PUID="$USER_ID" \
			-e PGID="$GROUP_ID" \
			-p 9091:9091 \
			-p 51415:51414 \
			-p 51415:51414/udp \
			--cap-add=NET_ADMIN \
			-e TRANSMISSION_WEB_UI=transmission-web-control \
			-v "$PathDkDownBox2:/etc/openvpn/custom" \
			-v "$PathDkDownBox3:/data" \
			-v "$PathDkDownBox1:/config" \
			-e OPENVPN_PROVIDER=CUSTOM \
			-e OPENVPN_USERNAME="$VpnUsername" \
			-e OPENVPN_PASSWORD="$VpnPassword" \
			-e TRANSMISSION_DOWNLOAD_DIR=/data/DownBox \
			-e UFW_ALLOW_GW_NET=true \
			-e UFW_EXTRA_PORTS=9910,23561,443,83,9091 \
			-e DROP_DEFAULT_ROUTE=true \
			-e TRANSMISSION_RPC_USERNAME="$Username" \
			-e TRANSMISSION_RPC_PASSWORD="$LowPassword" \
			-e TRANSMISSION_RPC_AUTHENTICATION_REQUIRED=true \
			-e TRANSMISSION_RPC_WHITELIST_ENABLED=false \
			-e LOCAL_NETWORK=192.168.1.0/32 \
			--log-driver json-file \
			--log-opt max-size=10m \
			haugene/transmission-openvpn:latest

		sudo docker run -d \
			--name downboxproxy \
			--privileged \
			--restart=unless-stopped \
			--link transmission \
			-p "$PortDownBox:8080" \
			haugene/transmission-openvpn-proxy:latest
	;;
	"seedbox")
		remove_container seedbox
		sudo docker pull lscr.io/linuxserver/transmission:latest
		sudo docker run -d \
			--name seedbox \
			--privileged \
			--restart=unless-stopped \
			-e TZ=CET \
			-v "$PathDkSeedBox1:/config" \
			-v "$PathDkSeedBox2:/downloads" \
			-p "$PortSeedBox:9091" \
			-p 51413:51413 \
			-p 51413:51413/udp \
			-e PUID="$USER_ID" \
			-e PGID="$GROUP_ID" \
			-e USER="$Username" \
			-e PASS="$HighPassword" \
			-e TRANSMISSION_DOWNLOAD_DIR=/downloads/SeedBox \
			lscr.io/linuxserver/transmission:latest

		sudo git clone https://github.com/ronggang/transmission-web-control.git
		sudo mkdir -p "$PathDkSeedBox1/GUI/"
		sudo cp -r transmission-web-control/src/* "$PathDkSeedBox1/GUI/"
		clean_git

		sudo docker exec seedbox cp -r /config/GUI/index.html /usr/share/transmission/public_html/index.html
	;;
	"grocy")
		remove_container grocy
		sudo docker pull lscr.io/linuxserver/grocy:latest
		sudo docker run -d \
			--name grocy \
			--restart=unless-stopped \
			-e TZ=CET \
			-v "$PathDkGrocy:/config" \
			-p "$PortGrocy:80" \
			lscr.io/linuxserver/grocy:latest
	;;
	"portainer")
		remove_container portainer
		sudo docker pull portainer/portainer-ce:latest
		sudo docker run -d \
			--name portainer \
			--privileged \
			--restart=unless-stopped \
			-e TZ=CET \
			-p 8000:8000 \
			-p 9443:9443 \
			-p "$PortPortainer:9000" \
			-v /var/run/docker.sock:/var/run/docker.sock \
			-v "$PathDkPortainer2:/data" \
			portainer/portainer-ce:latest
	;;
	"filebrowser")
		remove_container filebrowser
		sudo docker pull filebrowser/filebrowser:latest
		sudo docker run -d \
			--name filebrowser \
			--restart=unless-stopped \
			-e TZ=CET \
			-v "$PathDKFileBrowser2:/srv" \
			-v "$PathDKFileBrowser3:/database" \
			-v "$PathDKFileBrowser1:/config" \
			-p "$PortFileBrowser:80" \
			filebrowser/filebrowser:latest
	;;
	"freshrss")
		remove_container freshrss
		sudo docker pull freshrss/freshrss:latest
		sudo docker run -d \
			--name freshrss \
			--restart=unless-stopped \
			-e TZ=CET \
			-e CRON_MIN="1,31" \
			-p "$PortFreshRss:80" \
			-v "$PathDkFreshRss:/var/www/FreshRSS" \
			freshrss/freshrss:latest
	;;
	"mqtt")
		remove_container mqtt
		sudo docker pull eclipse-mosquitto:latest
		sudo docker run -d \
			--name mqtt \
			--restart=unless-stopped \
			-e TZ=CET \
			-v "$PathDkMqtt1:/mosquitto/config" \
			-v "$PathDkMqtt2:/mosquitto/data" \
			-p "$PortMqtt:1883" \
			-p 9001:9001 \
			eclipse-mosquitto:latest
		sleep 2
		sudo docker exec mqtt sh -c "mosquitto_passwd -b $PathDkMqtt3 $Username $HighPassword"
	;;
	"gitea")
		remove_container gitea
		sudo docker pull gitea/gitea:latest
		sudo docker run -d \
			--name gitea \
			--restart=unless-stopped \
			-p "$PortGitea:3000" \
			-p 2222:22 \
			-v "$PathDkGitea:/data" \
			-v /etc/timezone:/etc/timezone:ro \
			-v /etc/localtime:/etc/localtime:ro \
			gitea/gitea:latest
	;;
	"siyuan")
		remove_container siyuan
		sudo docker pull b3log/siyuan:latest
		sudo docker run -d \
			--name siyuan \
			-v "$PathDkSi:/siyuan/workspace" \
			-p "$PortSiyuan:6806" \
			-e PUID="$USER_ID" \
			-e PGID="$GROUP_ID" \
			b3log/siyuan:latest \
			serve \
			--workspace=/siyuan/workspace \
			--accessAuthCode="$HighPassword"
	;;
	"med")
		remove_container myelectricdata
		sudo docker pull m4dm4rtig4n/myelectricaldata:latest
		sudo docker run -d \
			--name myelectricdata \
			--restart=unless-stopped \
			-p 5000:5000 \
			-v "$PathDkMed:/data/config.yaml" \
			m4dm4rtig4n/myelectricaldata:latest
	;;
	"kiwix")
		remove_container kiwix
		sudo docker pull ghcr.io/kiwix/kiwix-serve:latest
		sudo docker run -d \
			--name kiwix \
			--restart=unless-stopped \
			-p "$PortKiwix:8080" \
			-v "$PathDkKiwix:/data" \
			ghcr.io/kiwix/kiwix-serve:latest \
			/data/*.zim 
	;;
	"kolibri")
		remove_container kolibri
		sudo docker pull learningequality/kolibri:latest
		sudo docker run -d \
			--name kolibri \
			--restart=unless-stopped \
			-p "$PortKolibri:8080" \
			-v "$PathDkKolibri:/kolibri" \
			learningequality/kolibri:latest
	;;
	"cyberchef")
		remove_container cyberchef
		sudo docker pull mpepping/cyberchef:latest
		sudo docker run -d \
			--name cyberchef \
			--restart=unless-stopped \
			-p "$PortCyberchef:8000" \
			mpepping/cyberchef:latest
	;;
	"oolama")
		remove_container ollama
	    remove_container open-webui
	    sudo docker pull ollama/ollama:latest
	    sudo docker pull ghcr.io/open-webui/open-webui:main
	    sudo docker run -d \
	        --name ollama \
	        --restart=unless-stopped \
	        -e TZ=CET \
	        -v "$PathDkOl/ollama:/root/.ollama" \
	        -p 11434:11434 \
	        ollama/ollama:latest
	    sudo docker run -d \
	        --name open-webui \
	        --restart=unless-stopped \
	        -e TZ=CET \
	        -e OLLAMA_BASE_URL=http://127.0.0.1:11434 \
	        --network=host \
	        -v "$PathDkOl/webui:/app/backend/data" \
	        -p "${PortOl}:8080" \
	        ghcr.io/open-webui/open-webui:main
	    ;;
	"monica")
		remove_container monica
		sudo docker pull monicahq/monica:latest
		docker run -d \
  			--name monica \
  			-p 1205:80 \
  			-v /media/Runable/Docker/MO-Data:/var/www/html/storage \
  			--restart unless-stopped \
  			monicahq/monica:latest
	;;
	"all")
		for svc in lamp homeassistant jellyfin filebrowser portainer grocy mqtt downbox seedbox freshrss med kiwix gitea kolibri cyberchef; do
			"$0" "$svc"
		done
	;;
	*)
		echo "Usage: $0 {all|lamp|homeassistant|jellyfin|filebrowser|portainer|grocy|mqtt|downbox|seedbox|freshrss|med|kiwix|gitea|kolibri|cyberchef}"
	;;
esac
