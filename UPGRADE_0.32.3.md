# Upgrade to CastorWorks 0.32.3

1. Back up the database and persistent storage.
2. Extract this patch over CastorWorks 0.32.2 while preserving `.env` and `storage/`.
3. Run `php scripts/migrate.php status`.
4. Run `php scripts/migrate.php migrate`.
5. Run `php tests/phase32_3_regression.php`.
6. Run `php scripts/schema-check.php`.
7. Run `php scripts/validate-upgrade.php`.
8. Restart Apache.

The optimizer now creates a proposal. Live job order changes only after an authorized user accepts the proposal.
