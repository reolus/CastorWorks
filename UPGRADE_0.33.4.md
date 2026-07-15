# Upgrade to CastorWorks 0.33.4

## Supported baseline

CastorWorks 0.33.3

## Backup

```bash
cd /var/www/rock-bluffs-exterior

mysqldump -u root -p rockbluffs_exterior \
  > /root/castorworks-before-0.33.4-$(date +%F-%H%M%S).sql

sudo tar -czf \
  /root/castorworks-files-before-0.33.4-$(date +%F-%H%M%S).tar.gz \
  .env storage
```

## Apply files

```bash
cd /var/www/rock-bluffs-exterior
sudo unzip -o /path/to/castorworks-0.33.4-patch.zip
sudo cp -a castorworks-0.33.4-patch/. .
sudo rm -rf castorworks-0.33.4-patch
```

No `apply-release` script is used.

## Migrate

```bash
php scripts/migrate.php status
php scripts/migrate.php migrate
```

## Validate

```bash
php tests/phase33_4_regression.php
sha256sum -c PATCH_MANIFEST.sha256
find app public scripts tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Restart

```bash
sudo systemctl restart apache2
```

## Functional checks

1. Open an estimate and create an AI estimate-narrative draft.
2. Open a customer conversation and create an AI reply draft.
3. Approve a draft, certify human review, and apply it to the intended record.
4. Restore a saved prompt version.
5. Configure a user AI budget and verify it appears on the AI page.
6. Open System Health and verify the AI Assistant monitor appears.
