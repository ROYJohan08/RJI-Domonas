#!/bin/bash

# Chargement des variables
if [ -f /etc/RJIDomoNas/credentials.sh ]; then
    source /etc/RJIDomoNas/credentials.sh
else
    echo "Erreur : /etc/RJIDomoNas/credentials.sh non trouvé."
    exit 1
fi

# Fonction pour nettoyer le dossier temporaire git
clean_git() {
    sudo rm -rf transmission-web-control/
}

case $1 in
        "lamp")
                sudo docker rm -f lamp
                sudo docker run -d \
                        --name lamp \
                        --restart=unless-stopped \
                        -e TZ=CET  \
                        -v "$PathDkLamp":/var/www/html  \
                        -p "$PortLamp":80  \
                        -p 3306:3306 \
                        fauria/lamp:latest
        ;;
        "homeassistant")
                sudo docker rm -f homeassistant
                sudo docker run -d \
                        --name homeassistant  \
                        --privileged  \
                        --restart=unless-stopped  \
                        --network=host  \
                        -e TZ=CET  \
                        -v $PathDkHomeAssistant:/config  \
                        --device /dev/dri:/dev/dri  \
                        -v /etc/localtime:/etc/localtime:ro  \
                        -p 6666:6666  \
                        -p 6667:6667  \
                        -p $PortHomeAssistant:8123  \
                        homeassistant/home-assistant:latest
        ;;
        "jellyfin")
                sudo docker rm -f jellyfin
                sudo docker run -d \
                        --name jellyfin \
                        --restart=unless-stopped \
                        -e TZ=CET \
                        -v "$PathDkJellyFin1":/config \
                        -v "$PathDkJellyFin3":/media \
                        -v "$PathDkJellyFin2":/cache \
                        -p "$PortJellyFin":8096 -p 8920:8920 \
                        -e PUID=$USER_ID \
                        -e PGID=$GROUP_ID \
                        --device /dev/dri/renderD128:/dev/dri/renderD128 \
                        jellyfin/jellyfin:latest
        ;;
        "filebrowser")
                sudo docker rm -f filebrowser
                sudo docker run -d  \
                        --name filebrowser  \
                        --privileged  \
                        --restart=unless-stopped  \
                        -e TZ=CET  \
                        -v $PathDKFileBrowser2:/srv  \
                        -v $PathDKFileBrowser3:/database  \
                        -v $PathDKFileBrowser1:/config/  \
                        -p $PortFileBrowser:80  \
                        filebrowser/filebrowser:latest
        ;;
        "portainer")
                sudo docker rm -f portainer
                sudo docker run -d  \
                        --name portainer  \
                        --privileged  \
                        --restart=unless-stopped  \
                        -e TZ=CET  \
                        -p 8000:8000  \
                        -p 9443:9443  \
                        -p $PortPortainer:9000  \
                        -v $PathDkPortainer1:/var/run/docker.sock  \
                        -v $PathDkPortainer2:/data  \
                        portainer/portainer-ce:latest
        ;;
        "grocy")
                sudo docker rm -f grocy
                sudo docker run -d  \
                        --name grocy  \
                        --restart=unless-stopped  \
                        -e TZ=CET  \
                        -v $PathDkGrocy:/config  \
                        -p $PortGrocy:80  \
                        lscr.io/linuxserver/grocy:latest
        ;;
        "mqtt")
                sudo docker rm -f mqtt
                sudo docker run -d  \
                        --name mqtt  \
                        --restart=unless-stopped \
                        -e TZ=CET  \
                        -v "$PathDkMqtt1":/mosquitto/config  \
                        -v "$PathDkMqtt2":/mosquitto/data \
                        -p "$PortMqtt":1883  \
                        -p 9001:9001 \
                        eclipse-mosquitto:latest
                sleep 2
                sudo docker exec mqtt sh -c "mosquitto_passwd -c $PathDkMqtt3 $Username"
        ;;
        "downbox")
                sudo docker rm -f transmission downboxproxy
                sudo docker run -d  \
                        --name transmission  \
                        --privileged  \
                        --restart=unless-stopped  \
                        -e PUID=$USER_ID  \
                        -e PGID=$GROUP_ID  \
                        -p 9091:9091  \
                        -p 51415:51414  \
                        -p 51415:51414/udp  \
                        --cap-add=NET_ADMIN  \
                        -e TRANSMISSION_WEB_UI=transmission-web-control  \
                        -v $PathDkDownBox2:/etc/openvpn/custom  \
                        -v $PathDkDownBox3:/data  \
                        -v $PathDkDownBox1:/config  \
                        -e OPENVPN_PROVIDER=CUSTOM  \
                        -e OPENVPN_USERNAME=$VpnUsername  \
                        -e TRANSMISSION_DOWNLOAD_DIR=/data/DownBox  \
                        -e OPENVPN_PASSWORD=$VpnPassword  \
                        -e UFW_ALLOW_GW_NET=true  \
                        -e UFW_EXTRA_PORTS=9910,23561,443,83,9091  \
                        -e DROP_DEFAULT_ROUTE=true  \
                        -e TRANSMISSION_RPC_USERNAME="$Username"  \
                        -e TRANSMISSION_RPC_PASSWORD="$LowPassword"  \
                        -e TRANSMISSION_RPC_AUTHENTICATION_REQUIRED=true  \
                        -e TRANSMISSION_RPC_WHITELIST_ENABLED=false  \
                        -e OPENVPN_PROVIDER=CUSTOM  \
                        -e LOCAL_NETWORK=192.168.1.0/32  \
                        --log-driver json-file  \
                        --log-opt max-size=10m  \
                        haugene/transmission-openvpn:latest
                sudo docker run -d  \
                        --name downboxproxy  \
                        --privileged  \
                        --restart=unless-stopped  \
                        --link transmission  \
                        -p $PortDownBox:8080  \
                        haugene/transmission-openvpn-proxy:latest
        ;;
        "seedbox")
                sudo docker rm -f seedbox
                sudo docker run -d  \
                        --name seedbox  \
                        --privileged  \
                        --restart=unless-stopped \
                        -e TZ=CET  \
                        -v "$PathDkSeedBox1":/config  \
                        -v "$PathDkSeedBox2":/downloads \
                        -p "$PortSeedBox":9091  \
                        -p 51413:51413  \
                        -p 51413:51413/udp \
                        -e PUID=$USER_ID  \
                        -e PGID=$GROUP_ID  \
                        -e USER="$Username"  \
                        -e PASS="$HighPassword" \
                        -e TRANSMISSION_DOWNLOAD_DIR=/downloads/SeedBox \
                        lscr.io/linuxserver/transmission:latest
                sudo git clone https://github.com/ronggang/transmission-web-control.git
                sudo mkdir -p "$PathDkSeedBox1/GUI/"
                sudo cp -r transmission-web-control/src/* "$PathDkSeedBox1/GUI/"
                clean_git
                sudo docker exec seedbox cp -r /config/GUI/index.html /usr/share/transmission/public_html/index.html
        ;;
        "freshrss")
                sudo docker rm -f freshrss
                sudo docker run -d \
                        --name freshrss \
                        --privileged \
                        --restart=unless-stopped \
                        -e TZ=CET \
                        -p $PortFreshRss:80 \
                        -v $PathDkFreshRss:/www/FreshRSS/data \
                        -e 'CRON_MIN=1,31' \
                        freshrss/freshrss
        ;;
        "med")
                sudo docker rm -f myelectricaldata
                sudo docker run -d  \
                        --name myelectricdata  \
                        --restart=unless-stopped  \
                        -p 5000:5000 \
                        -v $PathDkMed:/data/config.yaml  \
                        m4dm4rtig4n/myelectricaldata:latest
        ;;
        "kiwix")
                sudo docker rm -f kiwix
                sudo docker run -d \
                        --name kiwix \
                        -p $PortKiwix:8080 \
                        -v $PathDkKiwix:/data \
                        ghcr.io/kiwix/kiwix-serve:latest \
                        /data/*.zim
        ;;
        "pmtiles")
                sudo docker rm -f pmtiles
                sudo docker run -d \
                        --name pmtiles \
                        -p 1202:8080 \
                        -v /media/Docs01/Maps:/data \
                        ghcr.io/protomaps/go-pmtiles:latest \
                        serve --cors=* --public-url=http://dsm.royjohan.fr:1202 --port=8080 /data/
        ;;
        "kolibri")
                sudo docker rm -f kolibri
                sudo docker run -d \
                        --name kolibri \
                        -p $PortKolibri:8080 \
                        -v $PathDkKolibri:/kolibri \
                        learningequality/kolibri:latest
        ;;
        "cyberchef")
                sudo docker rm -f cyberchef
                sudo docker run -d \
                        --name cyberchef \
                        -p $PortCyberchef:8000 \
                        mpepping/cyberchef:latest
        ;;
        "all")
                $0 lamp
                $0 homeassistant
                $0 jellyfin
                $0 filebrowser
                $0 portainer
                $0 grocy
                $0 mqtt
                $0 downbox
                $0 seedbox
                $0 freshrss
                $0 med
                $0 kiwix
                $0 pmtiles
                $0 kolibri
                $0 cyberchef
        ;;
        *)
                echo "Usage: $0 {all|lamp|homeassistant|jellyfin|filebrowser|portainer|grocy|mqtt|downbox|seedbox|freshrss|med}"
        ;;
esac
