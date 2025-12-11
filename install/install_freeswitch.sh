#!/bin/bash

# Set error handling
set -e

# Function to print success messages
print_success() {
    echo -e "\e[32m$1 \e[0m"
}

# Function to print error messages
print_error() {
    echo -e "\e[31m$1 \e[0m"
}

# Detect OS codename
OS_CODENAME=$(lsb_release -sc 2>/dev/null || echo "")
echo "Detected OS_CODENAME=$OS_CODENAME"

# SW Token Handling
if [[ "$OS_CODENAME" == "trixie" ]]; then

  ENCODED_SW_TOKEN="cGF0X2V0MW1MckRhR2hiV0NOYTI4TWJMYXp4Yw=="

  _b64_decode() {

    # read from stdin and decode
    if base64 --help >/dev/null 2>&1; then
      base64 --decode
    else
      base64 -d
    fi
  }

if [[ -n "$ENCODED_SW_TOKEN" ]]; then
  SW_TOKEN="$(printf '%s' "$ENCODED_SW_TOKEN" | _b64_decode)"
else
  printf "Enter your Signalwire token: " >/dev/tty
  read -rs SW_TOKEN </dev/tty
  printf "\n" >/dev/tty
  if [[ -z "$SW_TOKEN" ]]; then
    echo "No token provided. Exiting." >&2
    exit 1
  fi
fi

export SW_TOKEN

fi

print_success "Starting FreeSWITCH Installation (Version 1.10)..."

# Detect OS version
os_codename=$(lsb_release -c -s)

print_success "Detected OS Codename: $os_codename"

# Upgrade packages
apt update

# Install dependencies
apt install -y autoconf automake devscripts g++ git-core libncurses5-dev libtool make libjpeg-dev \
               pkg-config flac libgdbm-dev libdb-dev gettext sudo equivs git dpkg-dev \
               libpq-dev liblua5.2-dev libtiff5-dev libperl-dev libcurl4-openssl-dev libsqlite3-dev \
               devscripts libspeexdsp-dev libspeex-dev libldns-dev libedit-dev libopus-dev \
               libmemcached-dev libshout3-dev libmpg123-dev libmp3lame-dev yasm nasm libsndfile1-dev \
               libuv1-dev libvpx-dev libavformat-dev libswscale-dev libvlc-dev \
               sox libsox-fmt-all sqlite3 unzip cmake uuid-dev libssl-dev

    if [[ "$OS_CODENAME" == "bookworm" ]]; then
        apt install -y mlocate python3-distutils
	fi

    if [[ "$OS_CODENAME" == "trixie" ]]; then
        apt install -y plocate python3-setuptools
	fi

print_success "All required dependencies installed."

# Install additional required libraries
print_success "Installing required external libraries..."

# Install libks
cd /usr/src
rm -rf libks
git clone --depth 1 https://github.com/signalwire/libks.git
cd libks
cmake .
make -j $(getconf _NPROCESSORS_ONLN)
make install
export C_INCLUDE_PATH=/usr/include/libks
print_success "libks installed successfully."

# Install sofia-sip
    if [[ "$OS_CODENAME" == "bookworm" ]]; then
cd /usr/src
rm -rf sofia-sip
git clone --no-checkout --depth 1 https://github.com/freeswitch/sofia-sip.git
cd sofia-sip
git fetch --depth 1 origin v1.13.17
git checkout v1.13.17
sh autogen.sh
./configure --enable-debug
make -j $(getconf _NPROCESSORS_ONLN)
make install
	fi

if [[ "$OS_CODENAME" == "trixie" ]]; then
    curl -sSL https://freeswitch.org/fsget | bash -s $SW_TOKEN
    apt install -y libsofia-sip-ua-dev libsofia-sip-ua0
fi

print_success "sofia-sip installed successfully."

# Install spandsp
    if [[ "$OS_CODENAME" == "bookworm" ]]; then
cd /usr/src
rm -rf spandsp
git clone --no-checkout --depth 1 https://github.com/freeswitch/spandsp.git
cd spandsp
git fetch --depth 1 origin 0d2e6ac65e0e8f53d652665a743015a88bf048d4 # Stable version
git checkout 0d2e6ac65e0e8f53d652665a743015a88bf048d4
sh autogen.sh
./configure --enable-debug
make -j $(getconf _NPROCESSORS_ONLN)
make install
ldconfig
    fi

if [[ "$OS_CODENAME" == "trixie" ]]; then
    apt install -y libspandsp3-dev libspandsp3
fi

print_success "spandsp installed successfully."

# Move to `/usr/src/` for FreeSWITCH installation
cd /usr/src

# Remove any existing FreeSWITCH directory
rm -rf freeswitch

# Set default PHP version to 8.1 if not set
FREESWITCH_VERSION=${FREESWITCH_VERSION:-"v1.10"}

# Clone the FreeSWITCH repo (Branch: 1.10)
print_success "Cloning FreeSWITCH $FREESWITCH_VERSION from repository..."
git clone --depth 1 --branch $FREESWITCH_VERSION https://github.com/nemerald-voip/freeswitch.git freeswitch
cd freeswitch

# Bootstrap the build
print_success "Bootstrapping FreeSWITCH build..."
./bootstrap.sh -j

# Enable required modules and disable unnecessary ones
print_success "Configuring FreeSWITCH modules..."
sed -i modules.conf -e s:'#applications/mod_callcenter:applications/mod_callcenter:'
sed -i modules.conf -e s:'#applications/mod_cidlookup:applications/mod_cidlookup:'
sed -i modules.conf -e s:'#applications/mod_memcache:applications/mod_memcache:'
sed -i modules.conf -e s:'#applications/mod_curl:applications/mod_curl:'
sed -i modules.conf -e s:'#applications/mod_translate:applications/mod_translate:'
sed -i modules.conf -e s:'#formats/mod_shout:formats/mod_shout:'
sed -i modules.conf -e s:'#formats/mod_pgsql:formats/mod_pgsql:'
sed -i modules.conf -e s:'#applications/mod_signalwire:applications/mod_signalwire:'

# Disable unnecessary modules
sed -i modules.conf -e s:'applications/mod_signalwire:#applications/mod_signalwire:'
sed -i modules.conf -e s:'endpoints/mod_skinny:#endpoints/mod_skinny:'
sed -i modules.conf -e s:'endpoints/mod_verto:#endpoints/mod_verto:'
sed -i modules.conf -e s:'applications/mod_say_es:#applications/mod_say_es:'
sed -i modules.conf -e s:'applications/mod_say_fr:#applications/mod_say_fr:'
sed -i modules.conf -e s:'applications/mod_nibblebill:#applications/mod_nibblebill:'
sed -i modules.conf -e s:'applications/mod_av:#applications/mod_av:'
sed -i modules.conf -e s:'xml_int/mod_xml_rpc:#xml_int/mod_xml_rpc:'

print_success "Modules configured successfully."

# Configure the build
print_success "Configuring FreeSWITCH..."
./configure -C --enable-portable-binary --disable-dependency-tracking --enable-debug \
            --prefix=/usr --localstatedir=/var --sysconfdir=/etc \
            --with-openssl --enable-core-pgsql-support

# Compile and install
print_success "Compiling FreeSWITCH..."
make -j $(getconf _NPROCESSORS_ONLN)
make install

# If /etc/freeswitch.orig exists, remove it
if [ -d "/etc/freeswitch.orig" ]; then
    print_success "Existing backup found. Removing it..."
    rm -rf /etc/freeswitch.orig
fi

# Move config files
mv /etc/freeswitch /etc/freeswitch.orig
mkdir /etc/freeswitch
cp -R /var/www/fspbx/public/app/switch/resources/conf/* /etc/freeswitch

# Default permissions
chown -R www-data:www-data /etc/freeswitch
chown -R www-data:www-data /var/lib/freeswitch
chown -R www-data:www-data /usr/share/freeswitch
chown -R www-data:www-data /var/log/freeswitch
chown -R www-data:www-data /var/run/freeswitch
chown -R www-data:www-data /var/cache/fusionpbx

print_success "FreeSWITCH $FREESWITCH_VERSION installed successfully!"

print_success "Removing existing FreeSWITCH service..."

# Remove existing FreeSWITCH systemd service if installed
if dpkg-query -W -f='${Status}' freeswitch-systemd 2>/dev/null | grep -q "install ok installed"; then
    apt-get remove -y freeswitch-systemd
    print_success "FreeSWITCH systemd package removed successfully."
else
    print_success "FreeSWITCH systemd package is not installed. Skipping removal."
fi

print_success "Installing new FreeSWITCH service..."
# Verify and copy FreeSWITCH systemd service file
if [ -f "/usr/src/freeswitch/debian/freeswitch-systemd.freeswitch.service" ]; then
    cp /usr/src/freeswitch/debian/freeswitch-systemd.freeswitch.service /lib/systemd/system/freeswitch.service
else
    print_error "Error: freeswitch.service not found!" >&2
    exit 1
fi

# Set correct permissions
chmod 644 /lib/systemd/system/freeswitch.service 

print_success "Enabling FreeSWITCH service to start at boot..."
systemctl enable freeswitch

# Detect OpenVZ and disable CPU scheduling if necessary
if [ -d "/proc/vz" ] || [ -e "/proc/user_beancounters" ]; then
    print_success "Detected OpenVZ, disabling CPU scheduling for FreeSWITCH..."
    sed -i -e "s/CPUSchedulingPolicy=rr/;CPUSchedulingPolicy=rr/g" /lib/systemd/system/freeswitch.service
fi

#Remove SW Token
  unset SW_TOKEN
