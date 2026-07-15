# Phase 22 Summary

Phase 22 expands Microsoft Entra identity administration and formalizes staff access synchronization.

## Added

- Microsoft group-to-portal-role mappings with ordered priority.
- Entra synchronization policy page with department, group, enabled-account, manager, role, schedule, and disable-missing controls.
- Microsoft group discovery and search.
- Manager and reporting-line synchronization.
- Individual user synchronization.
- Synchronization preview before applying a full sync.
- Staff vehicle and service-territory assignments.
- Scheduled-sync policy enforcement in the CLI worker.
- Application version and migration-history improvements on the System Upgrade page.
- Phase 22 migration and regression tests.

## Permissions

Directory user synchronization requires Microsoft Graph application permission `User.Read.All` or `Directory.Read.All`.
Group discovery and role mapping require `Group.Read.All` or `Directory.Read.All` with admin consent.
