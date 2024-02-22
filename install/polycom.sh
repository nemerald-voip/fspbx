#!/bin/bash

# Define variables
firmware_version="6.4.6.2453"
phone_models=("vvx300" "vvx301" "vvx310" "vvx311" "vvx400" "vvx401" "vvx410" "vvx411" "vvx250" "vvx350" "vvx450")

# Iterate over phone models
for model in "${phone_models[@]}"; do
    directory="/var/www/freeswitchpbx/public/resources/templates/provision/polycom/${model}"
    if [ ! -d "$directory" ]; then
        mkdir -p "$directory"
    fi
    ln -s "/var/www/freeswitchpbx/public/resources/templates/provision/polycom/${firmware_version}/\${mac}-directory.xml" "/var/www/freeswitchpbx/public/resources/templates/provision/polycom/${model}/\${mac}-directory"
    ln -s "/var/www/freeswitchpbx/public/resources/templates/provision/polycom/${firmware_version}/\${mac}.cfg" "/var/www/freeswitchpbx/public/resources/templates/provision/polycom/${model}/\${mac}.cfg"
    ln -s "/var/www/freeswitchpbx/public/resources/templates/provision/polycom/${firmware_version}/custom.cfg" "/var/www/freeswitchpbx/public/resources/templates/provision/polycom/${model}/custom.cfg"
    ln -s "/var/www/freeswitchpbx/public/resources/templates/provision/polycom/${firmware_version}/phoneMAC.cfg" "/var/www/freeswitchpbx/public/resources/templates/provision/polycom/${model}/phoneMAC.cfg"
done
