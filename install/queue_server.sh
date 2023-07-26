#!/bin/bash

sudo apt-get install php7.4-mbstring
sudo apt install imagemagick php7.4-imagick
sudo apt-get install php7.4-zip
service  php7.4-fpm restart
echo "PHP dependencies are installed\n\n"

sudo apt-get -y install supervisor
echo "Supervisor is installed\n\n"

sudo apt-get -y install redis-server
cp install/redis.conf /etc/redis/redis.conf
service redis-server restart
echo "Redis Server is installed\n\n"


cp install/horizon.conf /etc/supervisor/conf.d/

composer require laravel/horizon:*

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart horizon:*
echo "Horizon is installed\n\n"

composer dump-autoload



