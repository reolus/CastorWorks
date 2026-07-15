# CastorWorks 0.34.0 Release Summary

CastorWorks 0.34.0 introduces a unified commercial-contract management module designed for customers with multiple locations, negotiated terms, recurring service, formal SLAs, purchase-order requirements, and compliance obligations.

The existing **Agreements** route becomes the commercial-contract workspace. Existing public service-agreement review and signature behavior remains available.

## Highlights

- One customer may maintain multiple commercial contracts.
- Each contract may cover multiple existing CastorWorks properties.
- Each location may have separate contacts and purchase-order numbers.
- Recurring services generate future jobs idempotently.
- Contract billing generates invoices idempotently.
- Contract versions preserve a JSON snapshot after creation and amendments.
- Renewal monitoring moves approaching contracts into renewal review or renews eligible contracts automatically.
- SLA monitoring identifies overdue open work for active commercial customers.
- Compliance records identify expiring or missing documents.
