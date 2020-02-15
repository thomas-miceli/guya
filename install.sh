#!/bin/bash

if [ $# -lt 3 ]; then
  echo "-> ./install.sh <db_user> <db_password> <db_name>"
  exit 1
fi

if ! type git &> /dev/null ; then echo "Git not found"; exit 1;fi
if ! type php &> /dev/null ; then echo "PHP not found"; exit 1;fi
if ! type composer &> /dev/null ; then echo "Composer not found"; exit 1;fi
if ! type mysql &> /dev/null ; then echo "MySQL not found"; exit 1;fi

echo "<?php
# For an SQLite database, use: \"sqlite:///%kernel.project_dir%/var/data.db\"
# For a PostgreSQL database, use: \"postgresql://db_user:db_password@127.0.0.1:5432/db_name?serverVersion=11\"
return array (
    'APP_ENV' => 'prod',
    'APP_SECRET' => 'b4f88634d3d216d4c6dbd15a031ad106',
    'DATABASE_URL' => 'mysql://$1:$2@127.0.0.1:3306/$3',
);" > .env.local.php

composer install --no-dev -a
php bin/console doctrine:database:drop --force --if-exists
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -n

echo "

-----------

Create/Delete users with
$ php bin/console guya:au
$ php bin/console guya:ru
"