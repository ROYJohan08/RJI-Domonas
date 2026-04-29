#!bin/sh
VpnUsername=""
VpnPassword=""
Username=""
LowPassword=""
HighPassword=""
TmdbApiToken=""

PathDocs01=""
PathDocs02=""
PathDocs03=""
PathSeries01=""
PathSeries02=""
PathSeries03=""
PathFilms01=""
PathFilms02=""
PathFilms03=""
PathRunable=""
PathArchive=""

UidDocs01=""
UidDocs02=""
UidDocs03=""
UidSeries01=""
UidSeries02=""
UidSeries03=""
UidFilms01=""
UidFilms02=""
UidFilms03=""
UidRunable=""
UidArchive=""


PortLamp=
PortHomeAssistant=
PortJellyFin=
PortDownBox=
PortSeedBox=
PortGrocy=
PortPortainer=
PortFileBrowser=
PortFreshRss=
PortMqtt=
PathDkLamp="${PathRunable}/Docker/LM-Data"
PathDkHomeAssistant="${PathRunable}/Docker/HA-Config"
PathDkJellyFin1="${PathRunable}/Docker/JF-Config"
PathDkJellyFin2="${PathRunable}/Docker/JF-Cache"
PathDkJellyFin3="/media/"
PathDKFileBrowser1="${PathRunable}/Docker/FB-Config"
PathDKFileBrowser2="/media/"
PathDKFileBrowser3="${PathRunable}/Docker/FB-Database"
PathDkPortainer1="/var/run/docker.sock"
PathDkPortainer2="${PathRunable}/Docker/PO-Config"
PathDkGrocy="${PathRunable}/Docker/GO-Config"
PathDkMqtt1="${PathRunable}/Docker/MQ-Config"
PathDkMqtt2="${PathRunable}/Docker/MQ-Data"
PathDkMqtt3="/mosquitto/config/password.txt"
PathDkDownBox1="${PathRunable}/Docker/DB-Config"
PathDkDownBox2="${PathRunable}/Docker/DB-Config/custom"
PathDkDownBox3="${PathRunable}/DownBox"
PathDkSeedBox1="${PathRunable}/Docker/SB-Config"
PathDkSeedBox2="${PathRunable}/DownBox"
PathDkFreshRss="${PathRunable}/Docker/RS-Data"
PathDkMed="${PathRunable}/Docker/ME-Data/config.yaml"

USER_ID=1000
GROUP_ID=1000

# Vérifier si la variable est déjà présente dans .bashrc
if grep -q "export Username=" ~/.bashrc; then
    echo "La variable Username existe déjà dans ~/.bashrc."
else
    # Ajouter la variable à la fin du fichier
    echo "export Username=\"$Username\"" >> ~/.bashrc
    export "Username"="$Username"
fi
if grep -q "export HighPassword=" ~/.bashrc; then
    echo "La variable HighPassword existe déjà dans ~/.bashrc."
else
    echo "export HighPassword=\"$HighPassword\"" >> ~/.bashrc
    export "HighPassword"="$HighPassword"
fi
