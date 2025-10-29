# Fork Notice

This repository is a maintained fork of the original Mirza Pro project.

- Upstream: https://github.com/mahdiMGF2/mirza_pro (original bot core)
- This fork: adds a production-grade web admin panel, deployment automation, bot/system controls, and a category-based notifications system.

Major differences vs upstream:
- Full web admin panel (RTL) with users, invoices/services, payments, panels, products, discounts, channels, text management, test quotas
- Secure admin auth (session/CSRF/role checks), admin activity logs, wallet history
- Bot control (start/stop/restart via Supervisor), webhook tools, log viewer
- System operations (SSL via certbot, backups, cron listing)
- Notifications: destinations per category (payments/services/system/security) with forum topic support + logs
- Deployment docs and server configs (nginx/supervisor)

Compatibility
- Database schema remains compatible with upstream tables; no destructive changes
- New tables (created automatically): admin_logs, notifications_channels, notifications_log

Contributing
- Please open issues/PRs on this fork for panel or integration topics; for bot-core logic changes, consider also opening upstream issues