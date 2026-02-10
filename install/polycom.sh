#!/bin/bash

# Define variables
firmware_version="6.3"
phone_models=("vvx300" "vvx301" "vvx310" "vvx311" "vvx400" "vvx401" "vvx410" "vvx411" "vvx500" "vvx250" "vvx350" "vvx450")

# Iterate over phone models
for model in "${phone_models[@]}"; do
    directory="/var/www/fspbx/public/resources/templates/provision/polycom/${model}"
    if [ ! -d "$directory" ]; then
        mkdir -p "$directory"
    fi
    cd "$directory" || exit
    ln -s "../${firmware_version}/{\$mac}-directory.xml" "{\$mac}-directory.xml"
    ln -s "../${firmware_version}/{\$mac}.cfg" "{\$mac}.cfg"
    ln -s "../${firmware_version}/custom.cfg" "custom.cfg"
    ln -s "../${firmware_version}/phoneMAC.cfg" "phoneMAC.cfg"
done
