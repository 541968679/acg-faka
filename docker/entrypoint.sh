#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
    assets/cache \
    app/Pay \
    app/Plugin \
    app/View/User/Theme \
    config \
    kernel/Install/OS \
    kernel/Install/Update \
    runtime/log \
    runtime/plugin \
    runtime/request \
    runtime/tmp \
    runtime/view \
    runtime/waf

if [ ! -f assets/cache/favicon.ico ]; then
    if [ -f /usr/local/share/acg-faka/favicon.ico ]; then
        cp /usr/local/share/acg-faka/favicon.ico assets/cache/favicon.ico
    else
        : > assets/cache/favicon.ico
    fi
fi

if [ ! -L favicon.ico ]; then
    rm -f favicon.ico
    ln -s assets/cache/favicon.ico favicon.ico
fi

if [ ! -L runtime.log ]; then
    if [ -f runtime.log ] && [ ! -f runtime/runtime.log ]; then
        cp runtime.log runtime/runtime.log
    fi
    rm -f runtime.log
    ln -s runtime/runtime.log runtime.log
fi

chown -R www-data:www-data \
    assets/cache \
    app/Pay \
    app/Plugin \
    app/View/User/Theme \
    config \
    kernel/Install \
    runtime \
    runtime.log

exec docker-php-entrypoint "$@"
