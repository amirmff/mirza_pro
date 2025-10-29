# Changelog

All notable changes to this project will be documented here.

## [1.1.0] - 2025-10-29
### Added
- Full web panel ↔ bot synchronization across users, invoices/services, payments.
- Notifications system with destinations per category (payments/services/system/security) + logs.
- Admin Logs and Wallet History pages.
- Bot Management (start/stop/restart, webhook, logs) and System Ops (SSL, backups, cron list).
- Deployment docs in docs/DEPLOYMENT.md and server templates (nginx/supervisor).

### Changed
- Centralized payment approve/reject via bot_core with CSRF/admin checks.
- Services actions: extend days/volume; wired to ManagePanel.

### Removed
- Refund UI in payment_detail.php (not implemented server-side).

## [1.0.0] - 2025-01-26
- Initial professional web panel release.
