# Commercial Contracts Administrator Notes

The commercial-contract module uses existing customer and property records. Create the customer and every serviced property before adding a commercial contract.

Recommended setup order:

1. Create the commercial contract.
2. Add covered properties as contract locations.
3. Add recurring services and their next service dates.
4. Add SLA rules.
5. Add active purchase orders.
6. Record compliance requirements and expiration dates.
7. Activate the contract.
8. Run the job and invoice generators once manually before enabling cron.

Workers are idempotent through run keys based on the recurring template and scheduled date. Re-running a worker will skip work already generated for the same cycle.
