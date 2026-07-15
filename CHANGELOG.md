# CastorWorks 0.33.5

## Added
- Configurable AI redaction policy for email, phone, street address, and custom regular expressions.
- AI record-retention and draft-expiration controls.
- Per-role AI request and cost budgets.
- Provider connection test with latency reporting.
- AI usage CSV export.
- AI failure logging and administrator visibility.
- AI audit activity summary.
- Scheduled pruning and draft-expiration worker.

## Changed
- AI provider timeouts are now policy-controlled.
- Prompts and aggregate context are redacted before provider submission when redaction is enabled.
- Provider failures are stored in the AI usage ledger.

## Fixed
- AI governance can now enforce global, per-user, and per-role limits together.
