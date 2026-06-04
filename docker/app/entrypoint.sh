#!/bin/sh
set -eu

cd /var/www/html

mkdir -p runtime/view/cache runtime/view/compile runtime/waf/PACKET assets/cache
chown -R www-data:www-data runtime assets/cache

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

exec "$@"

