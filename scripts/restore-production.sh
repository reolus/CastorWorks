#!/bin/sh
set -eu
[ $# -ge 1 ] || { echo "Usage: $0 database-backup.sql.gz [files-backup.tar.gz]" >&2; exit 1; }
BASE=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
set -a; . "$BASE/.env"; set +a
printf 'This replaces production data. Type RESTORE to continue: '
read answer
[ "$answer" = RESTORE ] || exit 1
gzip -dc "$1" | MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE"
if [ $# -ge 2 ]; then tar -xzf "$2" -C "$BASE"; fi
echo "Restore completed. Validate the application immediately."
