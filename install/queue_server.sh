#!/bin/bash

sudo apt-get -y install supervisor
echo "Supervisor is installed\n\n"

sudo apt-get -y install redis-server
cp install/redis.conf /etc/redis/redis.conf
service redis-server restart
echo "Redis Server is installed\n\n"


cp install/horizon.conf /etc/supervisor/conf.d/

php artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider"


sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart horizon:*
echo "Horizon is installed\n\n"

composer dump-autoload



