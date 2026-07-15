# Rock Bluffs Exterior Services Portal - Phase 20

A PHP, MySQL, Apache, Bootstrap, Font Awesome, and Microsoft 365-enabled field-service operations portal.

## Phase 20 highlights

- Real Microsoft Teams workflow testing with stored health results
- Unified Microsoft 365 administration page
- Consistent environment loading through `Env::get()`, `getenv()`, `$_ENV`, and `$_SERVER`
- Module administration foundation
- Automated migration ledger and CLI migration runner
- Existing Graph, calendar, SharePoint, Teams, Stripe, Twilio, QuickBooks, field-service, billing, fleet, inspection, and customer-portal features retained

## Upgrade from Phase 19

```bash
cd /var/www/rock-bluffs-exterior
composer install --no-dev --optimize-autoloader
mysql -u root -p rockbluffs_exterior < database/migrate_phase20.sql
php tests/smoke.php
php tests/phase20_regression.php
sudo systemctl restart apache2
```

Preserve the production `.env` and `storage/` directory when replacing application files. After deployment, open **System > Integrations** and click **Test Teams**. A successful Power Automate/Teams response is saved and changes the service indicator to green.

For a clean installation, configure `.env` and import `database/schema.sql` once. Do not replay historical migrations after importing the full schema.

## Phase 22

Microsoft 365 staff administration now supports Entra group role mappings, department and group import filters, manager synchronization, individual user synchronization, sync previews, scheduled-sync policy, and staff vehicle/territory assignments. Configure these features under **Team & Access → Entra Access Mapping**.
