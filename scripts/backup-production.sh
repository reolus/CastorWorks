#!/bin/sh
set -eu
BASE=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
[ -f "$BASE/.env" ] || { echo "Missing .env" >&2; exit 1; }
set -a
. "$BASE/.env"
set +a
DEST=${BACKUP_DIRECTORY:-/var/backups/rockbluffs-exterior}
RETENTION=${BACKUP_RETENTION_DAYS:-30}
DUMP=${MYSQLDUMP_BINARY:-/usr/bin/mariadb-dump}
STAMP=$(date +%Y%m%d-%H%M%S)
mkdir -p "$DEST"
DBFILE="$DEST/rbes-db-$STAMP.sql.gz"
FILES="$DEST/rbes-files-$STAMP.tar.gz"
MYSQL_PWD="$DB_PASSWORD" "$DUMP" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" --single-transaction --routines --triggers "$DB_DATABASE" | gzip -9 > "$DBFILE"
tar -czf "$FILES" -C "$BASE" storage public/assets .env
sha256sum "$DBFILE" "$FILES" > "$DEST/rbes-$STAMP.sha256"
find "$DEST" -type f -mtime +"$RETENTION" -delete
php "$BASE/scripts/record-backup.php" full completed "$DBFILE" "Database and files backup completed"
echo "Backup completed: $STAMP"
