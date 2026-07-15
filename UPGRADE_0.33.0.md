# Upgrade to CastorWorks 0.33.0

1. Back up the database, `.env`, and `storage/`.
2. Copy the patch into the CastorWorks application root.
3. Run `php scripts/apply-release-0.33.0.php`.
4. Run `php scripts/migrate.php status` and `php scripts/migrate.php migrate`.
5. Run `php tests/phase33_0_regression.php`.
6. Restart Apache and force-refresh the browser.
7. Open **System > AI Assistant** and configure a provider.

## Environment variables

```env
OPENAI_API_KEY=
AZURE_OPENAI_ENDPOINT=
AZURE_OPENAI_API_KEY=
AZURE_OPENAI_DEPLOYMENT=
AZURE_OPENAI_API_VERSION=2024-10-21
OLLAMA_ENDPOINT=http://127.0.0.1:11434
```

Only configure the provider you intend to use. Provider secrets are never stored in the database.
