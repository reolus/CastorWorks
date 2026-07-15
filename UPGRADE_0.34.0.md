# Upgrade to CastorWorks 0.34.0

## Supported baseline

- CastorWorks v0.33.5

## Backup

```bash
cd /var/www/rock-bluffs-exterior
mysqldump -u root -p rockbluffs_exterior > /root/castorworks-before-0.34.0-$(date +%F-%H%M%S).sql
sudo tar -czf /root/castorworks-files-before-0.34.0-$(date +%F-%H%M%S).tar.gz .env storage
```

## Create the milestone branch

```bash
git switch main
git pull --ff-only
git switch -c release/0.34-commercial-contracts
```

## Apply files

Copy the contents of this package into the project root, preserving `.env`, `storage/`, and `vendor/`.

```bash
sudo cp -a castorworks-0.34.0-commercial-contracts/. /var/www/rock-bluffs-exterior/
```

## Migrate

```bash
php scripts/migrate.php status
php scripts/migrate.php migrate
```

## Validate

```bash
php -l app/controllers/AgreementController.php
php -l app/services/CommercialContractService.php
php tests/phase34_0_regression.php
sha256sum -c PATCH_MANIFEST.sha256
```

## Restart

```bash
sudo systemctl restart apache2
```

## Cron

Add to `/etc/cron.d/castorworks`:

```cron
10 0 * * * www-data flock -n /run/lock/castorworks-contract-jobs.lock php /var/www/rock-bluffs-exterior/scripts/generate-contract-jobs.php >> /var/log/castorworks/generate-contract-jobs.log 2>&1
20 0 * * * www-data flock -n /run/lock/castorworks-contract-invoices.lock php /var/www/rock-bluffs-exterior/scripts/generate-contract-invoices.php >> /var/log/castorworks/generate-contract-invoices.log 2>&1
30 6 * * * www-data flock -n /run/lock/castorworks-contract-renewals.lock php /var/www/rock-bluffs-exterior/scripts/monitor-contract-renewals.php >> /var/log/castorworks/monitor-contract-renewals.log 2>&1
*/15 * * * * www-data flock -n /run/lock/castorworks-contract-sla.lock php /var/www/rock-bluffs-exterior/scripts/monitor-contract-slas.php >> /var/log/castorworks/monitor-contract-slas.log 2>&1
```

Restart cron after editing.

## Git commit

```bash
git add app database scripts tests VERSION release.json CHANGELOG.md README_PATCH.md RELEASE_SUMMARY_0.34.0.md UPGRADE_0.34.0.md PATCH_MANIFEST.sha256
git commit -m "Release CastorWorks v0.34.0 commercial contracts"
git push -u origin release/0.34-commercial-contracts
```
