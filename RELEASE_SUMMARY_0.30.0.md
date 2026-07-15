# ServiceOS 0.30.0 - Unified Communications

This release adds provider-aware email, SMS, and publish/subscribe delivery. Administrators can enable multiple providers, choose their priority, and control fallback behavior without rewriting business workflows.

## Providers

### Email
- Microsoft Graph Email, enabled by default
- Azure Communication Services Email, optional
- Amazon SES, optional and disabled by default

### SMS
- AWS End User Messaging SMS
- Azure Communication Services SMS
- Twilio SMS
- Amazon SNS direct-to-phone SMS

### Publish/Subscribe
- Amazon SNS topic publishing

Lower priority numbers are attempted first. When fallback is enabled, ServiceOS proceeds to the next enabled and configured provider only after the prior provider fails.
