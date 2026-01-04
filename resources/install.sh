#!/bin/bash
######################### INCLUSION LIB ##########################
BASEDIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
wget https://raw.githubusercontent.com/NebzHB/dependance.lib/master/dependance.lib -O $BASEDIR/dependance.lib &>/dev/null
PLUGIN=$(basename "$(realpath $BASEDIR/..)")
. ${BASEDIR}/dependance.lib
##################################################################
wget https://raw.githubusercontent.com/NebzHB/nodejs_install/main/install_nodejs.sh -O $BASEDIR/install_nodejs.sh &>/dev/null

installVer='16' 	#NodeJS major version to be installed

pre
step 0 "Vérifications diverses"


step 5 "Mise à jour APT et installation des packages nécessaires"
try sudo apt-get update

#install nodejs, steps 10->50
. ${BASEDIR}/install_nodejs.sh ${installVer}

step 60 "Nettoyage ancien modules"
cd ${BASEDIR};
#remove old local modules
silent sudo rm -rf node_modules

step 70 "Installation des librairies, veuillez patienter svp"
try sudo npm install --no-fund --no-package-lock --no-audit

step 80 "Mise a jours des droit"
try sudo chown -R www-data node_modules


step 90 "nettoyage final"
post
