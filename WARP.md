# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

Mirza Pro is a PHP 8.1+ Telegram bot and web admin panel for selling and managing VPN services. It integrates with multiple VPN panels (Marzban, X-UI, Hiddify, etc.) and several payment gateways.

## Big-Picture Architecture

- Entry points
  - `index.php`: Telegram webhook handler (validates Telegram IPs, routes updates, manages user step machine).
  - `webhooks.php`: Utility to set the Telegram webhook URL.
  - `webpanel/`: Primary web admin UI (modern RTL Persian interface) backed by `webpanel/includes/` and `webpanel/api/`.
- Core application layer
  - `config.php`: PDO MySQL connection and global config.
  - `function.php`: Core business logic (payments via `DirectPayment()`, username generation, channel enforcement, QR/config delivery, cron registration, helpers).
  - `botapi.php`: Thin wrapper over Telegram Bot API helpers.
  - State is tracked in DB via `user.step` and `user.Processing_value`.
- Panel abstraction
  - `panels.php`: `ManagePanel` class orchestrates operations.
  - Panel adapters: `Marzban.php`, `x-ui_single.php`, `hiddify.php`, `WGDashboard.php`, `marzneshin.php`, `ibsng.php`, `mikrotik.php`, etc.
- Payments
  - `payment/`: `nowpayment.php`, `zarinpal.php`, `aqayepardakht.php`, `iranpay1.php`, `tronado.php`, `card.php` feed into `DirectPayment()` in `function.php`.
- Scheduled jobs
  - `cronbot/`: operational tasks (status checks, notifications, activation/disable, backups, uptime monitors, lottery/gift).
- Data and localization
  - Schema files in `database/` (e.g., `schema.sql`).
  - Text templates in `text.json` (also under `vpnbot/Default/` and `vpnbot/update/`).

High-level purchase flow
- User chooses a product → initiates payment → gateway callback → `DirectPayment($order_id)` → branch by invoice intent (create/extend/extra volume/time) → panel adapter action → send config/QR to user.

## Commands you’ll use often

- Run the web admin locally
  - Windows: run the bundled script
    ```powershell path=null start=null
    .\webpanel\START_SERVER.bat
    ```
  - Cross-platform (requires PHP CLI): serve repo root and open `/webpanel/`
    ```bash path=null start=null
    php -S localhost:8000 -t .
    # then open http://localhost:8000/webpanel/
    ```
- Set/refresh Telegram webhook (after configuring `config.php`)
  ```bash path=null start=null
  php webhooks.php
  ```
- Trigger a single cron task (useful for debugging specific flows)
  ```bash path=null start=null
  php cronbot/statusday.php
  # or: php cronbot/activeconfig.php
  ```
- Import initial database schema
  ```bash path=null start=null
  mysql -u <db_user> -p <db_name> < database/schema.sql
  ```
- PHP syntax lint
  - Single file
    ```bash path=null start=null
    php -l index.php
    ```
  - Recursive (PowerShell)
    ```powershell path=null start=null
    Get-ChildItem -Recurse -Filter *.php | %% { php -l $_.FullName } | sls -NotMatch "No syntax errors"
    ```
  - Recursive (bash)
    ```bash path=null start=null
    find . -name "*.php" -print0 | xargs -0 -n1 php -l | grep -v "No syntax errors"
    ```

Notes
- No Composer manifest at repo root; `vendor/` is committed, so `composer install` is typically not required.
- Webhook requires HTTPS in production (use the deployment guide for Nginx + Certbot or the panel’s SSL helper).

## Development and debugging

- Logs frequently referenced by code: `error_log`, `log.txt` (created at runtime). Tail them while interacting with the bot.
- Manual testing is standard (no unit test suite): interact with the bot via Telegram and/or run individual `cronbot/*.php` scripts.

## File/Directory map (selective)

- Core: `index.php`, `config.php`, `function.php`, `botapi.php`, `panels.php`, `keyboard.php`
- Panels: top-level `*panel*.php` (see above) implement remote API calls
- Payments: `payment/*.php`
- Cron: `cronbot/*.php`
- Web admin: `webpanel/` (UI, includes, api)
- API for bot/web: root `api/*.php` and `webpanel/api/*.php`
- Data: `database/schema.sql`, `text.json`
- Third-party: `vendor/` (do not edit)

## Important docs to consult

- `README.md`: features, quick install, requirements, backup/restore, troubleshooting.
- `DEPLOYMENT.md`: full Ubuntu automation (PHP 8.1, MySQL, Nginx, Supervisor), SSL options, firewall, webhook, bot management.
- `webpanel/README.md`: web admin specifics, security notes, server config snippets.
- `WEBPANEL_COMPLETE_GUIDE.md`, `WEBPANEL_BOT_INTEGRATION_GUIDE.md`: deeper integration and roadmap.
