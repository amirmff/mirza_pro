# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

Mirza Pro is a Telegram bot for selling and managing VPN services. It's a PHP-based system that integrates with various VPN panel types (Marzban, X-UI, Hiddify, etc.) and payment gateways to automate VPN service sales and management.

The bot is written in Persian (Farsi) and primarily targets Iranian users selling VPN services.

## Core Architecture

### Entry Points & Flow

1. **index.php** - Main webhook handler
   - Receives Telegram updates
   - Validates Telegram IPs for security
   - Handles user registration and anti-spam
   - Routes messages/callbacks to appropriate handlers
   - Manages user state machine via `step` field

2. **admin.php** - Admin panel operations
   - Separate entry point for admin functionality
   - User management, panel configuration, reports

3. **webhooks.php** - Webhook registration

### Key Components

**Database Layer (`config.php`, `function.php`)**
- MySQL/PDO connection management
- Core functions: `select()`, `update()`, `step()` for state management
- Single database handles users, invoices, products, panels, payments

**Panel Integration (`panels.php` + individual panel files)**
- `ManagePanel` class orchestrates all panel operations
- Supports multiple panel types:
  - Marzban (`Marzban.php`) - Primary VPN panel
  - Marzneshin (`marzneshin.php`)
  - X-UI Single (`x-ui_single.php`)
  - Alireza variants (`alireza.php`, `alireza_single.php`)
  - Hiddify (`hiddify.php`)
  - WireGuard Dashboard (`WGDashboard.php`)
  - S-UI (`s_ui.php`)
  - IBSNG (`ibsng.php`)
  - Mikrotik (`mikrotik.php`)
- Each panel file implements API communication for user creation, extension, deletion

**Payment Processing (`payment/` directory)**
- Multiple payment gateway integrations:
  - `nowpayment.php` - Crypto payments (NowPayments)
  - `zarinpal.php` - Iranian payment gateway
  - `aqayepardakht.php` - Iranian payment gateway
  - `iranpay1.php` - Iranian payment gateway
  - `tronado.php` - TRON cryptocurrency
  - `card.php` - Card-to-card payments
- All payments flow through `DirectPayment()` function in `function.php`

**Cron Jobs (`cronbot/` directory)**
- **Critical scheduled tasks:**
  - `statusday.php` - Daily service status checks (runs every 15 min)
  - `NoticationsService.php` - Service expiration notifications (every 1 min)
  - `activeconfig.php` - Activate pending configs (every 1 min)
  - `disableconfig.php` - Disable expired configs (every 1 min)
  - `croncard.php` - Card payment verification (every 1 min)
  - `payment_expire.php` - Payment expiration cleanup (every 5 min)
  - `backupbot.php` - Database backups (every 5 hours)
  - `expireagent.php` - Agent license expiration (every 30 min)
  - `configtest.php` - Test configuration cleanup (every 2 min)
  - `uptime_node.php` / `uptime_panel.php` - Monitoring (every 15 min)

**Telegram Bot API (`botapi.php`)**
- Wrapper functions for Telegram Bot API
- Parses incoming updates into variables
- Functions: `telegram()`, `sendmessage()`, `Editmessagetext()`, etc.

**UI/Keyboards (`keyboard.php`)**
- Dynamic keyboard generation based on user role and settings
- Supports both reply and inline keyboards
- Customizable button text via database (`textbot` table)

**Web Admin Panel (`app/`, `panel/`)**
- Separate web interface for admin operations
- Built with HTML/CSS/JS (Bootstrap-based)
- Not the primary interface - Telegram bot is main UI

### State Management

The system uses a step-based state machine stored in the `user` table's `step` field:
- User actions trigger step changes via `step($step, $from_id)`
- Steps like "waiting_for_payment", "select_location", "enter_username", etc.
- `Processing_value` field stores temporary JSON data during multi-step flows

### Key Data Flow: Service Purchase

1. User clicks buy → `datain = "buy"` in index.php
2. System shows available locations/panels
3. User selects product → price calculated with discounts
4. Payment gateway selected and initiated
5. Payment webhook receives confirmation
6. `DirectPayment()` called with `order_id`
7. Based on `id_invoice` format:
   - `getconfigafterpay|username` → Create new service via `ManagePanel->createUser()`
   - `getextenduser` → Extend existing service
   - `getextravolumeuser` → Add data volume
   - `getextratimeuser` → Add time
8. Config generated, QR code created, sent to user
9. Affiliate commissions processed if applicable

### Critical Functions

**`function.php` contains:**
- `DirectPayment($order_id)` - Main payment processing logic (lines ~295-937)
- `generateUsername()` - Username generation with various methods
- `channel()` - Force-join channel verification
- `formatBytes()` - Data formatting
- `sendMessageService()` - Sends config to user with QR code
- Various payment gateway wrappers (nowPayments, trnado, createInvoice, etc.)
- `activecron()` - Registers all cron jobs on system

**`panels.php` ManagePanel class:**
- `createUser($name_panel, $code_product, $username, $Data_Config)` - Creates VPN user
- `extend()` - Extends service
- `extra_volume()` - Adds data
- `extra_time()` - Adds time
- `DataUser()` - Fetches user info from panel

## Development Commands

### Setup

Since this is a PHP project without a build system:

```bash
# Install Composer dependencies
composer install

# Configure database and bot token
# Edit config.php with your credentials:
# - Database: $dbname, $usernamedb, $passworddb
# - Bot: $APIKEY
# - Domain: $domainhosts

# Set Telegram webhook
php webhooks.php
```

### Testing

There is no formal test suite. Testing is done manually via Telegram bot interaction.

To test specific flows:
1. Use a test Telegram account
2. Interact with the bot via `/start`
3. Monitor `error_log` file for errors
4. Check `log.txt` for database update logs

### Debugging

```bash
# View error log
tail -f error_log

# View database update log
tail -f log.txt

# Check cron execution
# Manually trigger a cron job:
php cronbot/statusday.php
```

### Database

No migration system exists. Database schema is created/modified via:
- `addFieldToTable()` function adds columns dynamically
- Direct SQL in various files
- Backup via `cronbot/backupbot.php`

To inspect database:
```bash
# Connect to MySQL
mysql -u {username_db} -p {database_name}

# Key tables:
# - user: User accounts and state
# - invoice: Service purchases
# - product: Available products/plans
# - marzban_panel: Panel configurations
# - Payment_report: Payment tracking
```

### Deployment

This project requires:
- **PHP 7.4+** with extensions: curl, pdo_mysql, mbstring, gd
- **MySQL 5.7+**
- **Web server** (Apache/Nginx) with HTTPS (required for webhooks)
- **Cron daemon** to run scheduled tasks

Deployment steps:
1. Upload files to web server
2. Configure `config.php`
3. Import/create database tables
4. Set webhook: `https://yourdomain.com/webhooks.php`
5. Configure cron jobs (call `activecron()` function or manually set)
6. Test bot via Telegram

## Important Patterns

### Panel Addition
To add a new VPN panel type:
1. Create `newpanel.php` with API functions (see `Marzban.php` as template)
2. Add panel type to `panels.php` ManagePanel class
3. Add database entry in `marzban_panel` table
4. Implement: token/auth, createUser, getUser, deleteUser, extend methods

### Payment Gateway Addition
To add a new payment gateway:
1. Create `payment/newgateway.php` with API integration
2. Add gateway function in `function.php` (e.g., `createPaymentNewGateway()`)
3. Add database entries in `PaySetting` table
4. Add webhook handler in `payment/` directory
5. Add button/option in keyboards and text customization

### Localization
All user-facing text is stored in `text.json` with structure:
```json
{
  "fa": { "users": {...}, "Admin": {...} },
  "en": {...},
  "ru": {...}
}
```
Currently only Farsi is fully implemented.

## Security Considerations

- Telegram IP validation in `index.php` via `checktelegramip()`
- SQL injection protection via PDO prepared statements
- Input sanitization: `sanitizeUserName()`, `sanitize_recursive()`
- Anti-spam: Message rate limiting per user (35 msgs/min → auto-block)
- Panel credentials stored in database (should use encryption - not currently implemented)
- Payment webhooks should verify signatures (implementation varies by gateway)

## File Structure Summary

```
/
├── index.php              # Main webhook handler
├── admin.php              # Admin operations
├── config.php             # Database config
├── function.php           # Core utilities
├── panels.php             # Panel abstraction layer
├── botapi.php             # Telegram API wrapper
├── keyboard.php           # UI generation
├── Marzban.php, x-ui_single.php, etc.  # Panel implementations
├── cronbot/               # Scheduled tasks
│   ├── statusday.php
│   ├── NoticationsService.php
│   └── ...
├── payment/               # Payment gateways
│   ├── nowpayment.php
│   ├── zarinpal.php
│   └── ...
├── app/                   # Web admin panel (frontend)
├── panel/                 # Web admin panel (backend)
├── api/                   # API endpoints
├── sub/                   # Subscription link handler
├── vpnbot/                # Additional bot features
└── vendor/                # Composer dependencies
```

## Notes

- This is a production system used for commercial VPN sales
- Code is primarily in Persian/Farsi
- Uses older PHP patterns (not modern framework-based)
- Heavy use of global variables and database coupling
- No unit tests - testing done via production bot
- Active development with frequent updates (see `version` file)
- Open source as of recent release (see README.md)
