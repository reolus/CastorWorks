# CastorWorks 0.33.4
## AI Operations Integration and Monitoring

CastorWorks 0.33.4 connects the governed AI foundation to daily operational records while retaining explicit human control.

### Operational integration

Estimate pages can create an AI estimate-narrative draft using the customer, property, price, and existing estimate notes as grounding data. Conversation pages can create an AI customer-reply draft from the recent thread history. Neither workflow sends content automatically.

Each draft records its intended source target. Once approved, the application form defaults to the correct estimate or conversation record. Applying a draft requires the user to certify that the content was reviewed by a human.

### Governance

Administrators can set per-user daily request, monthly request, and monthly estimated-cost limits. These limits supplement the global provider limits and are enforced before a provider request is made.

Saved prompt versions are visible in the AI administration page and can be restored through a governed rollback action.

### Reporting and health

The AI administration page now summarizes monthly requests, successful requests, estimated cost, and average latency. It includes provider-level and user-level usage tables.

System Health now reports whether the AI provider is enabled and configured, the latest request time and latency, monthly failure count, and estimated monthly cost.

### Security and privacy

Provider secrets remain in `.env`. Prompts continue to be represented by hashes in usage logs. AI-generated customer-facing text remains reviewable and is never sent automatically by this release.
