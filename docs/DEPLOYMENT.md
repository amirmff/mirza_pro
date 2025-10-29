# Deployment and Operations Guide

This guide ensures the bot and web panel are fully installed, integrated, monitored, and operable via the panel.

1) Prerequisites
- OS: Linux server with Nginx + PHP-FPM + MySQL/MariaDB
- PHP extensions: pdo_mysql, curl, mbstring, json, openssl
- Tools: git, curl, supervisor (or systemd), certbot (optional), tar, gzip, mysqldump

2) Database
- Import existing schema/data.
- Tables created at runtime if missing: admin_logs, notifications_channels, notifications_log.

3) Configure project
- Edit config.php: DB creds, $APIKEY, $domainhosts.
- Web server root should serve project with /webpanel accessible.

4) Supervisor (process control)
- Install supervisor and place the provided configs/supervisor/mirza_bot.conf.
- Commands used by panel: `supervisorctl start|stop|restart mirza_bot`.

5) Nginx
- Use configs/nginx/mirza_pro.conf as a template; set server_name to your domain.
- Ensure PHP-FPM socket/path matches your environment.

6) SSL (optional)
- From webpanel → System → SSL: install/renew via certbot buttons (needs root permissions and certbot installed).

7) Webhook
- From webpanel → Bot Management: set webhook after domain and SSL are working.

8) Notifications (channels/groups with or without topics)
- Create channel/group in Telegram and add bot as admin.
- In webpanel → اعلان‌ها: add destinations per category (payments, services, system, security) with Chat ID and optional Topic ID.
- Use “ارسال آزمایشی” to verify delivery.

9) Logs & Monitoring
- Bot logs: webpanel → مدیریت ربات → نمایش لاگ‌ها (expects /var/log/mirza_bot.log or logs/bot.log).
- Admin actions: webpanel → فعالیت ادمین‌ها.
- Notification attempts: webpanel → اعلان‌ها → گزارش ارسال اعلان‌ها.
- Payments: webpanel → پرداخت‌ها و تاریخچه کیف پول.

10) Backups & Crons
- Backups: webpanel → سیستم → پشتیبان‌گیری (DB/files/full).
- Cron tasks: webpanel → سیستم (list/add as needed).

11) Final E2E checklist
- Approve & reject a pending payment; verify user balance change/DM + admin/notification logs.
- Service operations: reset usage, toggle, revoke sub, extend days/GB; verify on panel (ManagePanel) and DB.
- Texts, discounts, channels, quotas: update and verify in bot flows.
- Notifications: test each category and confirm logs.

12) Troubleshooting
- Supervisor status: webpanel → مدیریت ربات or SSH: `supervisorctl status`.
- Webhook errors: view in Bot Management (getWebhookInfo) and adjust domain/SSL.
- PHP/Nginx errors: check error logs and permissions on project directories.
