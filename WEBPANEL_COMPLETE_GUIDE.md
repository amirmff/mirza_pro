# Mirza Pro - Complete Web Panel Implementation Guide

## System Architecture

Mirza Pro is a comprehensive VPN reseller Telegram bot with web management panel that supports:

### Supported VPN Panels
- **Marzban** (New & Classic)
- **X-UI**
- **Hiddify**
- **WireGuard Dashboard**
- **3X-UI / s-ui**
- **Marzneshin**
- **IBSng**

### Core Components

#### 1. Database Tables (Complete)
```
✅ user - All telegram users
✅ admin - Admin users with roles (administrator/Seller/support)
✅ marzban_panel - VPN panel configurations
✅ product - Service plans (time/volume based)
✅ invoice - User subscriptions
✅ Payment_report - Payment transactions
✅ Discount - Discount codes
✅ DiscountSell - Bulk discount codes
✅ card_number - Card-to-card payment cards
✅ channels - Force join channels
✅ help - Help content with categories
✅ setting - Bot configuration
✅ textbot - Customizable bot texts
✅ topicid - Telegram forum topics for reports
✅ service_other - Extension/renewal services
✅ Giftcodeconsumed - Gift code redemptions
✅ admin_logs - Admin activity logs
```

#### 2. Bot Features

**User Features:**
- Buy VPN services (time or volume based)
- Test free accounts
- Extend/renew services
- Account wallet & balance
- Discount codes
- Agent/referral system
- Support tickets
- Lottery & wheel of luck
- Multi-language support

**Admin Features:**
- User management (block/unblock/edit)
- Service management
- Payment approval
- Panel management
- Product management
- Statistics & reports
- Bot settings
- Agent management
- Support system

**Payment Methods:**
- Card to Card (Manual)
- ZarinPal
- NowPayments (Crypto)
- Plisio (Crypto)
- Tronado
- AqayePardakht
- IranPay

#### 3. Cron Jobs
```
cronbot/statusday.php - Daily reports
cronbot/configtest.php - Service expiration check
cronbot/activeconfig.php - Auto-activate on-hold services
cronbot/disableconfig.php - Disable expired services
cronbot/NoticationsService.php - User notifications
cronbot/expireagent.php - Agent expiration
cronbot/backupbot.php - Database backups
cronbot/uptime_panel.php - Panel monitoring
cronbot/uptime_node.php - Node monitoring
cronbot/lottery.php - Lottery draw
cronbot/gift.php - Gift code processing
```

## Web Panel Implementation Status

### ✅ Completed Pages
- **login.php** - Admin authentication
- **index.php** - Dashboard with statistics
- **users.php** - User listing with filters + actions
- **user_detail.php** - Detailed user profile and actions
- **invoices.php** - Service/invoice management
- **invoice_detail.php** - Service operations (reset/toggle/revoke/extend days/GB)
- **payments.php** - Payment approval listing
- **payment_detail.php** - Payment approval/reject with CSRF
- **panels.php / panel_manager.php** - VPN panel listing/CRUD
- **products.php / product_manager.php** - Product catalog/CRUD
- **discount_manager.php** - Discount management
- **channel_manager.php** - Force-join channels
- **textbot_manager.php** - Bot texts management
- **test_quota.php** - Test quota management
- **wallet_history.php** - Wallet and payments history
- **admin_logs.php** - Admin activity logs
- **notifications.php** - Category-based notifications destinations/logs
- **settings.php** - Settings
- **reports.php** - Statistics page
- **setup.php** - Initial setup wizard

### 🚧 Remaining Enhancements

#### Critical (done)
- user_detail.php, invoice_detail.php, payment_detail.php are implemented with CSRF/admin enforcement. Refund is intentionally not implemented server-side.

4. **panel_manager.php** - VPN panel CRUD
   - Add/edit/delete panels
   - Test connection
   - View panel statistics
   - Manage inbounds/protocols

5. **product_manager.php** - Product CRUD
   - Add/edit/delete products
   - Set pricing (time/volume)
   - Configure locations
   - Enable/disable products

#### Settings Management (Priority 2)
6. **settings_bot.php** - Bot configuration
   - Enable/disable features
   - Set limits & restrictions
   - Configure channels
   - Manage keyboards

7. **settings_payments.php** - Payment gateway management
   - Configure card-to-card
   - API keys for gateways
   - Enable/disable methods

8. **settings_text.php** - Bot text customization
   - Edit all bot messages
   - Multi-language support

9. **settings_agents.php** - Agent system configuration
   - Commission percentages
   - Agent approval settings
   - Agent limits

#### Advanced Features (Priority 3)
10. **discount_manager.php** - Discount code management
    - Create/edit/delete codes
    - Set usage limits
    - View redemption history

11. **support_tickets.php** - Support ticket system
    - View all tickets
    - Reply to tickets
    - Assign to admins

12. **admin_manager.php** - Admin user management
    - Add/edit/delete admins
    - Set roles & permissions
    - View admin activity logs

13. **reports_advanced.php** - Detailed reports
    - Sales reports (daily/monthly)
    - Popular products
    - Agent performance
    - Payment method analytics

14. **channel_manager.php** - Force join channels
    - Add/remove channels
    - Set button text
    - Test channel status

15. **help_manager.php** - Help content management
    - Add/edit help articles
    - Categories
    - Media attachments

16. **lottery_manager.php** - Lottery configuration
    - Set prizes
    - Draw settings
    - Winner history

#### Bot Control (done)
- bot_management.php (start/stop/restart, webhook, logs) and system.php (SSL/backups/cron list)

#### Cron Manager (enhancement)
- Optional future page for cron edit/enable/disable; current system lists crons and supports backups.

## API Endpoints Needed

### Already Implemented in includes/api.php
```php
✅ getUsers() - Get user list with filters
✅ getUserDetails() - Get user details
✅ updateUser() - Update user info
✅ deleteUser() - Delete user
✅ getPanels() - Get panel list
✅ getPanelDetails() - Get panel details
✅ getProducts() - Get product list
✅ getStatistics() - Get dashboard stats
```

### Additional API Methods Needed
```php
// Invoices
getInvoices($filters)
getInvoiceDetails($invoice_id)
updateInvoice($invoice_id, $data)
renewInvoice($invoice_id, $data)
resetConfig($invoice_id)

// Payments
getPayments($filters)
getPaymentDetails($payment_id)
approvePayment($payment_id, $notes)
rejectPayment($payment_id, $reason)

// Panels
addPanel($data)
updatePanel($panel_name, $data)
deletePanel($panel_name)
testPanel($panel_name)

// Products
addProduct($data)
updateProduct($product_id, $data)
deleteProduct($product_id)

// Settings
getSettings($category)
updateSettings($category, $data)

// Discounts
getDiscounts()
addDiscount($data)
updateDiscount($code, $data)
deleteDiscount($code)

// Bot Control
getBotStatus()
startBot()
stopBot()
setWebhook($url)
testWebhook()

// Cron Jobs
getCronStatus()
toggleCron($cron_name, $status)
runCron($cron_name)
```

## Implementation Roadmap

### Phase 1: Core Management (Week 1)
- [ ] User detail page with full editing
- [ ] Invoice detail page with management
- [ ] Payment approval workflow
- [ ] Panel CRUD operations
- [ ] Product CRUD operations

### Phase 2: Settings & Configuration (Week 2)
- [ ] Complete settings pages
- [ ] Bot text customization
- [ ] Payment gateway configuration
- [ ] Agent system settings

### Phase 3: Advanced Features (Week 3)
- [ ] Discount management
- [ ] Support ticket system
- [ ] Admin management
- [ ] Advanced reports

### Phase 4: Bot Control & Automation (Week 4)
- [ ] Bot control panel
- [ ] Cron job management
- [ ] Channel management
- [ ] Help content management
- [ ] Lottery system

## Current Installation Status

### ✅ Completed Setup
1. Database configured
2. All tables created
3. Bot token updated: `8265630669:AAHxSQ0t47788Lzw5CNMs5bT6wgFWVASIew`
4. PHP 8.2 configured
5. Nginx configured
6. Basic webpanel pages created
7. API backend functional
8. Authentication system working

### ⚠️ Pending Setup
1. **SSL Certificate** - Required for Telegram webhook
   ```bash
   sudo apt install certbot python3-certbot-nginx
   sudo certbot --nginx -d yourdomain.com
   ```

2. **Set Webhook** (after SSL)
   ```bash
   curl "https://api.telegram.org/bot8265630669:AAHxSQ0t47788Lzw5CNMs5bT6wgFWVASIew/setWebhook?url=https://yourdomain.com/webhooks.php"
   ```

3. **Configure Cron Jobs**
   ```bash
   crontab -e
   # Add these lines:
   */5 * * * * php /var/www/mirza_pro/cronbot/configtest.php
   */15 * * * * php /var/www/mirza_pro/cronbot/NoticationsService.php
   0 0 * * * php /var/www/mirza_pro/cronbot/statusday.php
   0 2 * * * php /var/www/mirza_pro/cronbot/backupbot.php
   ```

## Development Guidelines

### Code Standards
- PHP 8.2+ compatibility
- PSR-12 coding standard
- Use prepared statements for all queries
- Implement CSRF protection
- Log all admin actions
- Validate and sanitize all inputs

### Security Best Practices
- Never expose API keys in client-side code
- Use password_hash() for passwords
- Implement rate limiting
- Use HTTPS only
- Validate file uploads
- Sanitize output to prevent XSS

### Database Interaction
- Always use PDO with prepared statements
- Use transactions for multi-table operations
- Index frequently queried fields
- Log important changes

## Next Steps

1. Create complete user detail page
2. Implement invoice management
3. Add panel CRUD operations
4. Complete settings pages
5. Setup SSL for webhook
6. Configure cron jobs
7. Test all functionality
8. Deploy to production

---

**Version**: 1.0.0  
**Last Updated**: 2025-10-27  
**Status**: In Development
