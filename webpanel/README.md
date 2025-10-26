# Mirza Pro Web Admin Panel

A comprehensive web-based administration panel for managing your Mirza Pro Telegram VPN Bot.

## Features

✅ **Complete Bot Management**
- Dashboard with real-time statistics
- User management (view, edit, delete, block)
- Invoice and service management
- Payment tracking and approval
- VPN panel configuration
- Product management
- Settings control
- Reports and analytics

✅ **Security**
- Secure authentication with password hashing
- Session management with timeout
- CSRF protection
- Role-based access control (Administrator, Seller, Support)
- Activity logging

✅ **Modern UI**
- Responsive design (mobile-friendly)
- Persian (RTL) interface
- Smooth animations
- Toast notifications
- Modal dialogs

## Installation

### 1. Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- SSL certificate (for HTTPS)

### 2. Setup Steps

1. **Files are already in place** in the `webpanel/` directory

2. **Update Admin Passwords**

   By default, admins in your database have plaintext passwords. You need to hash them:

   ```php
   // Run this script ONCE to hash existing admin passwords:
   php webpanel/scripts/hash_passwords.php
   ```

   Or manually update via SQL:
   ```sql
   UPDATE admin SET password_admin = '$2y$10$YourHashedPasswordHere' WHERE id_admin = 1;
   ```

   To generate a hash:
   ```php
   echo password_hash('your_password', PASSWORD_BCRYPT);
   ```

3. **Configure Web Server**

   **For Apache (.htaccess):**
   ```apache
   <IfModule mod_rewrite.c>
       RewriteEngine On
       RewriteBase /webpanel/
       
       # Redirect to HTTPS
       RewriteCond %{HTTPS} off
       RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
       
       # Protect includes directory
       RewriteRule ^includes/ - [F,L]
   </IfModule>
   ```

   **For Nginx:**
   ```nginx
   location /webpanel {
       try_files $uri $uri/ /webpanel/index.php?$query_string;
       
       location ~ ^/webpanel/includes/ {
           deny all;
           return 403;
       }
   }
   ```

4. **Set Permissions**

   ```bash
   chmod 755 webpanel
   chmod 755 webpanel/assets
   chmod 755 webpanel/includes
   chmod 644 webpanel/*.php
   ```

5. **Access the Panel**

   Navigate to: `https://yourdomain.com/webpanel/`

   Default admin credentials (if not changed):
   - Use your existing admin username from the `admin` table
   - Password needs to be hashed (see step 2)

## Usage

### Dashboard

Access: `/webpanel/index.php`

Features:
- View total users, revenue, active services
- Quick action cards
- Recent activity feed
- Statistics overview

### User Management

Access: `/webpanel/users.php`

Features:
- Search and filter users
- View user details and history
- Edit user balance, status, verification
- Block/unblock users
- Delete users
- Send messages to users

### Payment Management

Access: `/webpanel/payments.php`

Features:
- View all payments
- Filter by status (pending, paid, failed)
- Approve manual payments
- View payment details

### Panel Management

Access: `/webpanel/panels.php`

Features:
- View all VPN panels
- Edit panel configurations
- Check panel connectivity
- View panel statistics

### Product Management

Access: `/webpanel/products.php`

Features:
- View all products
- Add/edit/delete products
- Set pricing and limits
- Configure product availability

### Settings

Access: `/webpanel/settings.php`

Features:
- Bot configuration
- Payment gateway settings
- Channel management
- System settings

## API Endpoints

The panel uses an internal API system. Main endpoints:

### Authentication
- `POST /webpanel/includes/auth.php` - Login
- `GET /webpanel/logout.php` - Logout

### Users
- `GET /webpanel/api/users.php` - List users
- `GET /webpanel/api/users.php?id={id}` - Get user details
- `PUT /webpanel/api/users.php?id={id}` - Update user
- `DELETE /webpanel/api/users.php?id={id}` - Delete user

### Statistics
- `GET /webpanel/api/stats.php` - Get dashboard statistics

### Panels
- `GET /webpanel/api/panels.php` - List panels
- `GET /webpanel/api/panels.php?name={name}` - Get panel details

### Payments
- `GET /webpanel/api/payments.php` - List payments
- `POST /webpanel/api/payments.php?id={id}&action=approve` - Approve payment

## Security Considerations

### Important Security Steps:

1. **Change Default Credentials**
   - Update all admin passwords immediately

2. **Use HTTPS**
   - Never run without SSL in production
   - Configure proper SSL certificate

3. **Restrict Access**
   - Use firewall rules to limit admin panel access
   - Consider IP whitelisting for admin access

4. **Regular Updates**
   - Keep PHP and MySQL updated
   - Monitor security logs

5. **Backup**
   - Regular database backups
   - Backup admin panel files

### Session Security:

- Sessions timeout after 30 minutes of inactivity
- CSRF tokens on all forms
- Activity logging enabled

## Role-Based Access

### Administrator
- Full access to all features
- Can manage other admins
- Access to all reports

### Seller
- View and manage users
- View invoices
- Limited access to settings

### Support
- View users
- Search and filter
- Send messages to users

## Customization

### Modifying Styles

Edit `/webpanel/assets/css/style.css`:

```css
/* Change primary color */
.btn-primary {
    background: #your-color;
}

/* Change sidebar color */
.sidebar {
    background: linear-gradient(180deg, #color1 0%, #color2 100%);
}
```

### Adding New Features

1. Create new PHP file in `/webpanel/`
2. Include authentication: `require_once __DIR__ . '/includes/auth.php';`
3. Check permissions: `$auth->requireLogin();`
4. Add navigation link in sidebar
5. Create API endpoint if needed

## Troubleshooting

### Cannot Login

- Check admin table has hashed passwords
- Verify session.save_path is writable
- Check error_log for details

### White Screen

- Enable error display: `ini_set('display_errors', 1);`
- Check PHP error log
- Verify all includes are accessible

### Statistics Not Loading

- Check database connection in config.php
- Verify table structures exist
- Check browser console for JS errors

### Session Expires Too Fast

Edit `/webpanel/includes/auth.php`:

```php
// Change timeout (in seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    // 7200 = 2 hours
}
```

## File Structure

```
webpanel/
├── includes/
│   ├── auth.php          # Authentication system
│   └── api.php           # API handler class
├── assets/
│   ├── css/
│   │   └── style.css     # Main stylesheet
│   └── js/
│       └── main.js       # JavaScript utilities
├── index.php             # Dashboard
├── login.php             # Login page
├── logout.php            # Logout handler
├── users.php             # User management
├── invoices.php          # Invoice management
├── payments.php          # Payment management
├── panels.php            # Panel management
├── products.php          # Product management
├── settings.php          # Settings
├── reports.php           # Reports & analytics
└── README.md             # This file
```

## Support

For issues or questions:
1. Check error_log file
2. Review console errors (F12 in browser)
3. Verify database connectivity
4. Check file permissions

## License

Part of Mirza Pro - Open Source VPN Bot Management System

## Credits

Developed for Mirza Pro Telegram Bot
Persian (Farsi) interface
RTL design optimized

---

**Note:** This admin panel is designed to work alongside your existing Telegram bot. All data is shared through the same database configured in `config.php`.
