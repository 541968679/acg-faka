#!/bin/bash
set -eo pipefail

: "${MYSQL_DATABASE:?MYSQL_DATABASE is required}"
: "${MYSQL_ROOT_PASSWORD:?MYSQL_ROOT_PASSWORD is required}"

db_prefix="${DB_PREFIX:-acg_}"
salt="${ADMIN_SALT:-dockerlocaldevsalt0123456789abcd}"
admin_email="${ADMIN_EMAIL:-admin@example.com}"
admin_password="${ADMIN_PASSWORD:-Admin123456!}"
admin_nickname="${ADMIN_NICKNAME:-DockerAdmin}"

sql_escape() {
  printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e "s/'/''/g"
}

sed_escape() {
  printf '%s' "$1" | sed -e 's/[\/&]/\\&/g'
}

admin_password_md5="$(printf '%s' "$admin_password" | md5sum | awk '{print $1}')"
salt_md5="$(printf '%s' "$salt" | md5sum | awk '{print $1}')"
admin_password_hash="$(printf '%s' "$(printf '%s' "${admin_password_md5}${salt_md5}" | md5sum | awk '{print $1}')" | sha1sum | awk '{print $1}')"

sed \
  -e "s/__PREFIX__/$(sed_escape "$db_prefix")/g" \
  -e "s/__MANAGE_EMAIL__/$(sed_escape "$(sql_escape "$admin_email")")/g" \
  -e "s/__MANAGE_PASSWORD__/${admin_password_hash}/g" \
  -e "s/__MANAGE_SALT__/$(sed_escape "$(sql_escape "$salt")")/g" \
  -e "s/__MANAGE_NICKNAME__/$(sed_escape "$(sql_escape "$admin_nickname")")/g" \
  /docker-entrypoint-initdb.d/Install.sql.template > /tmp/acgfaka-init.sql

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}" < /tmp/acgfaka-init.sql

if [ -n "${SITE_URL:-}" ]; then
  mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}" \
    -e "UPDATE \`${db_prefix}config\` SET value='$(sql_escape "$SITE_URL")' WHERE \`key\`='callback_domain';"
fi

if [ -n "${CNAME_DOMAIN:-}" ]; then
  mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}" \
    -e "UPDATE \`${db_prefix}config\` SET value='$(sql_escape "$CNAME_DOMAIN")' WHERE \`key\`='cname';"
fi

if [ -n "${SUBSTATION_DOMAINS:-}" ]; then
  mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}" \
    -e "UPDATE \`${db_prefix}config\` SET value='$(sql_escape "$SUBSTATION_DOMAINS")' WHERE \`key\`='domain';"
fi

rm -f /tmp/acgfaka-init.sql
