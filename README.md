# 🤖 Mirza Pro - Advanced Telegram VPN Bot
### Professional VPN Service Management with Web Admin Panel

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://www.php.com/)
[![Telegram Bot API](https://img.shields.io/badge/Telegram%20Bot%20API-Latest-blue)](https://core.telegram.org/bots/api)

> **Complete Telegram bot solution for VPN service management** with professional web admin panel, automated deployment, and comprehensive system management tools.

---

## 🌟 What's New in This Fork

This fork adds a **complete professional web administration system** with:

✨ **Web-based Setup Wizard** - 3-step installation process  
🎛️ **Modern Admin Panel** - RTL Persian interface with full bot control  
🤖 **Bot Management** - Start/stop/restart, live logs, webhook control  
🔒 **SSL Automation** - One-click Let's Encrypt integration  
💾 **Backup System** - Automated database and file backups  
⚙️ **System Monitor** - Real-time CPU, RAM, disk usage  
📦 **Automated Installer** - Ubuntu deployment in minutes  
📚 **Complete Documentation** - Deployment and troubleshooting guides

---

## 📋 Features

### 🤖 Telegram Bot
- 👥 **User Management** - Registration, authentication, service assignment
- 💰 **Payment Integration** - Multiple gateways, invoicing, transaction tracking
- 📊 **Service Plans** - Flexible pricing, data limits, expiration management
- 🔄 **VPN Panel Integration** - Marzban/X-UI support
- 📢 **Notifications** - Service alerts, payment confirmations
- 🎫 **Support System** - Ticket management
- 📈 **Analytics** - Revenue tracking, user statistics

### 🎛️ Web Admin Panel
- 📱 **Modern RTL UI** - Clean, responsive Persian interface
- 👥 **User Management** - View, edit, suspend, delete users
- 💳 **Payment Tracking** - Transaction history, revenue reports
- ⚙️ **Panel Config** - Manage multiple Marzban/X-UI panels
- 📦 **Product Management** - Service plans and pricing
- 🤖 **Bot Control** - Start/stop/restart from browser
- 📋 **Log Viewer** - Real-time bot logs with filtering
- 🔒 **SSL Management** - Automated certificate installation
- 💾 **Backup System** - Database and file backups
- ⏰ **Cron Jobs** - Schedule automated tasks
- 📊 **System Monitor** - Live resource usage
- 🔐 **Security** - Session management, CSRF protection, activity logging

---

## 🚀 Quick Installation

### One-Command Install

```bash
curl -sSL https://raw.githubusercontent.com/amirmff/mirza_pro/main/install.sh | sudo bash
```

### Manual Installation

```bash
cd /var/www
git clone https://github.com/amirmff/mirza_pro.git
cd mirza_pro
chmod +x install.sh
sudo ./install.sh
```

### Complete Setup

1. **Run Installer** (installs PHP, MySQL, Nginx, Supervisor)
2. **Visit Setup Wizard**: `http://YOUR_SERVER_IP/webpanel/setup.php`
3. **Configure Bot**: Enter token, admin ID, credentials
4. **Login to Panel**: `http://YOUR_SERVER_IP/webpanel/login.php`

📖 **Full Guide**: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)

---

## 📋 Requirements

- **OS**: Ubuntu 20.04 or 22.04 LTS
- **RAM**: 1GB minimum (2GB recommended)
- **Disk**: 10GB minimum
- **Access**: Root or sudo privileges
- **Optional**: Domain name (for SSL/HTTPS)

---

## 📸 Screenshots

### Web Admin Panel
- ✅ Dashboard with live statistics
- ✅ Bot management with process control
- ✅ System monitoring and SSL setup
- ✅ User and payment management
- ✅ Real-time log viewer

### Telegram Bot
- ✅ User registration and service purchase
- ✅ Panel connection and service management
- ✅ Payment processing
- ✅ Support ticket system

---

## 🛠️ Tech Stack

- **Backend**: PHP 8.1
- **Database**: MySQL 8.0
- **Web Server**: Nginx
- **Process Manager**: Supervisor
- **Bot Framework**: PHP + Telegram Bot API
- **Frontend**: Vanilla JS + Modern CSS

---

## 📂 Project Structure

```
mirza_pro/
├── bot.php                    # Main bot logic
├── webhooks.php               # Telegram webhook handler
├── config.php                 # Configuration
├── install.sh                 # Automated installer
├── DEPLOYMENT.md              # Complete deployment guide
├── webpanel/                  # Admin panel
│   ├── login.php              # Authentication
│   ├── index.php              # Dashboard
│   ├── bot_management.php     # Bot control
│   ├── system.php             # System management
│   ├── setup.php              # Installation wizard
│   ├── includes/              # Backend logic
│   │   ├── auth.php           # Authentication
│   │   ├── api.php            # API endpoints
│   │   ├── bot_control.php    # Bot operations
│   │   └── system_control.php # System operations
│   └── assets/                # CSS, JS
├── database/                  # Schema
├── backups/                   # Automated backups
└── logs/                      # Application logs
```

---

## 🔐 Security Features

✅ Password hashing (bcrypt)  
✅ Session management with timeout  
✅ CSRF protection  
✅ SQL injection prevention (PDO)  
✅ XSS protection  
✅ Activity logging  
✅ File permission hardening  
✅ Rate limiting support (Fail2Ban)  
✅ HTTPS/SSL support

---

## 📚 Documentation

- 📖 [Complete Deployment Guide](DEPLOYMENT.md) - Installation, SSL, backups, troubleshooting
- 🐛 [Bug Fixes Log](BUGFIXES.md) - Fixed issues and improvements
- 🔧 [Web Panel Guide](webpanel/README.md) - Admin panel features

---

## 🔄 Update Your Installation

```bash
cd /var/www/mirza_pro
git pull origin main
sudo supervisorctl restart mirza_bot
sudo systemctl restart php8.1-fpm nginx
```

---

## 💾 Backup & Restore

### Via Web Panel
1. Login → System Management
2. Choose backup type (Database / Files / Full)
3. Download when ready

### Via CLI
```bash
# Backup
mysqldump -u mirza_user -p mirza_pro > backup.sql
tar -czf mirza_backup.tar.gz /var/www/mirza_pro

# Restore
mysql -u mirza_user -p mirza_pro < backup.sql
```

---

## 🐛 Troubleshooting

**Bot not responding?**
```bash
sudo supervisorctl status mirza_bot
tail -f /var/log/mirza_bot.log
```

**Web panel not loading?**
```bash
sudo systemctl status nginx php8.1-fpm
sudo tail -f /var/log/nginx/error.log
```

📖 **Full troubleshooting guide**: [DEPLOYMENT.md#troubleshooting](DEPLOYMENT.md#troubleshooting)

---

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

---

## 💖 Support the Project

### Original Project
This fork is based on the excellent work by [mahdiMGF2](https://github.com/mahdiMGF2/mirza_pro).

If you find the original project helpful:  
👉 [Support on NowPayments](https://nowpayments.io/donation/permiumbotmirza)

### This Fork
If you appreciate the web panel and automation features:  
⭐ **Star this repository** to help others discover it!  
🐛 Report bugs via [GitHub Issues](https://github.com/amirmff/mirza_pro/issues)  
💡 Suggest features or improvements

---

## 📝 License

This project is licensed under the MIT License - see [LICENSE](LICENSE) file for details.

---

## ⚠️ Disclaimer

This software is provided for educational and legitimate business purposes. Users are responsible for compliance with local laws and regulations regarding VPN services.

---

## 🌟 Star History

If you find this project useful, please give it a star ⭐

---

## 📈 Changelog

### v1.1.0 - Full Sync + Notifications + Ops
- ✅ Web panel fully synced with bot core (users, invoices/services, payments)
- ✅ Admin wallet history, admin activity logs pages
- ✅ Category-based notifications (payments/services/system/security) with forum topics and logs
- ✅ Bot management from panel (start/stop/restart, webhook, logs)
- ✅ System operations (SSL, backups, cron listing)
- ✅ CSRF + admin-only enforcement across mutating endpoints
- ✅ Deployment assets (configs/nginx, configs/supervisor) and docs/DEPLOYMENT.md

### v1.0.0 - Professional Web Panel Release
- ✅ Complete web admin panel with RTL UI
- ✅ Automated Ubuntu installer
- ✅ Bot management (start/stop/restart/logs)
- ✅ SSL automation with Let's Encrypt
- ✅ Backup and restore system
- ✅ System monitoring dashboard
- ✅ Cron job management
- ✅ Security improvements
- ✅ Comprehensive documentation

---

**Forked from**: [mahdiMGF2/mirza_pro](https://github.com/mahdiMGF2/mirza_pro)  
**Maintained by**: [amirmff](https://github.com/amirmff)  
**Version**: 1.0.0  
**Last Updated**: 2025-01-26

---

<div align="center">

### Made with ❤️ for the VPN community

[⬆ Back to Top](#-mirza-pro---advanced-telegram-vpn-bot)

</div>
