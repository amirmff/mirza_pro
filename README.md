<div align="center">

# 🚀 Mirza Pro

### Advanced Telegram VPN Bot with Professional Web Admin Panel

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://www.php.net/)
[![Telegram Bot API](https://img.shields.io/badge/Telegram%20Bot%20API-Latest-blue.svg)](https://core.telegram.org/bots/api)
[![Ubuntu](https://img.shields.io/badge/Ubuntu-20.04%20%7C%2022.04-orange.svg)](https://ubuntu.com/)
[![Status](https://img.shields.io/badge/Status-Production%20Ready-success.svg)](https://github.com/amirmff/mirza_pro)

**Complete VPN service management solution** with automated deployment, modern web interface, and comprehensive bot features.

[Features](#-features) • [Installation](#-quick-start) • [Documentation](#-documentation) • [Screenshots](#-screenshots) • [Support](#-support)

---

</div>

## ✨ Overview

**Mirza Pro** is a production-ready Telegram bot system for managing VPN services, featuring:

- 🤖 **Full-featured Telegram Bot** - User registration, service management, payment processing
- 🎛️ **Professional Web Admin Panel** - Modern cyberpunk-themed UI with dark mode
- 🔄 **Multi-Panel Support** - Marzban, X-UI, Hiddify, WireGuard Dashboard, and more
- 💳 **Multiple Payment Gateways** - ZarinPal, NowPayments, Crypto, Card-to-Card
- 🚀 **One-Command Installation** - Automated setup with SSL and bot configuration
- 🔒 **Enterprise Security** - CSRF protection, session management, activity logging

<div align="center">

### 🎯 Perfect For

**VPN Resellers** • **Service Providers** • **Telegram Bot Developers** • **System Administrators**

[⬇️ Quick Start](#-quick-start) • [📖 Full Documentation](docs/DEPLOYMENT.md) • [🐛 Report Bug](https://github.com/amirmff/mirza_pro/issues)

</div>

---

## 🌟 Key Features

### 🤖 Telegram Bot Features

| Feature | Description |
|---------|-------------|
| 👥 **User Management** | Registration, authentication, profile management, agent system |
| 💰 **Payment Processing** | Multiple gateways, invoice generation, transaction tracking |
| 📊 **Service Plans** | Flexible pricing, time/volume-based plans, auto-renewal |
| 🔄 **VPN Integration** | Support for 7+ VPN panel types (Marzban, X-UI, Hiddify, etc.) |
| 📢 **Notifications** | Service alerts, payment confirmations, system notifications |
| 🎫 **Support System** | Ticket management, help categories, FAQ system |
| 🎁 **Promotions** | Discount codes, gift codes, lottery system, referral rewards |
| 📈 **Analytics** | Revenue tracking, user statistics, service reports |

### 🎛️ Web Admin Panel Features

| Feature | Description |
|---------|-------------|
| 🎨 **Modern UI** | Cyberpunk-themed design with dark/light mode toggle |
| 👥 **User Management** | View, edit, suspend, delete users with advanced filtering |
| 💳 **Payment Management** | Approve/reject payments, transaction history, revenue reports |
| ⚙️ **Panel Configuration** | Manage multiple VPN panels, test connections, monitor status |
| 📦 **Product Management** | Create/edit service plans, pricing, location-based products |
| 🤖 **Bot Control** | Start/stop/restart bot, view live logs, manage webhook |
| 🔒 **SSL Management** | Automated Let's Encrypt certificates, HTTPS configuration |
| 💾 **Backup System** | Database and file backups, scheduled backups, restore |
| ⏰ **Cron Management** | View and manage scheduled tasks, system automation |
| 📊 **System Monitor** | Real-time CPU, RAM, disk usage, service status |
| 🔐 **Security** | Session management, CSRF protection, admin activity logs |

### 🛠️ System Features

- ✅ **Automated Installation** - One-command setup script
- ✅ **SSL Automation** - Automatic Let's Encrypt certificate installation
- ✅ **Process Management** - Supervisor integration for bot reliability
- ✅ **CLI Tools** - Beautiful command-line interface for management
- ✅ **Database Management** - Automated schema creation, migrations
- ✅ **Logging System** - Comprehensive activity and error logging
- ✅ **Multi-language** - Persian (RTL) interface with extensible text system

---

## 🚀 Quick Start

### Prerequisites

- **OS**: Ubuntu 20.04 or 22.04 LTS
- **RAM**: 1GB minimum (2GB recommended)
- **Disk**: 10GB minimum free space
- **Access**: Root or sudo privileges
- **Optional**: Domain name (for SSL/HTTPS)

### One-Command Installation

```bash
curl -sSL https://raw.githubusercontent.com/amirmff/mirza_pro/main/install.sh | sudo bash
```

### Manual Installation

```bash
# Clone repository
cd /var/www
git clone https://github.com/amirmff/mirza_pro.git
cd mirza_pro

# Run installer
chmod +x install.sh
sudo ./install.sh
```

### Post-Installation Setup

1. **Access Setup Wizard**
   ```
   http://YOUR_SERVER_IP/webpanel/setup.php
   ```

2. **Configure System**
   - Enter Telegram Bot Token (from [@BotFather](https://t.me/BotFather))
   - Enter Admin User ID (from [@userinfobot](https://t.me/userinfobot))
   - Enter domain name (optional - for SSL)
   - Set admin username and password

3. **Complete Setup**
   - Bot will start automatically
   - SSL certificate will be installed (if domain provided)
   - Webhook will be configured

4. **Access Admin Panel**
   ```
   http://YOUR_SERVER_IP/webpanel/login.php
   ```

📖 **Detailed Guide**: [Complete Installation Documentation](docs/DEPLOYMENT.md)

---

## 📸 Screenshots

<div align="center">

### Web Admin Panel

| Dashboard | Bot Management | User Management |
|-----------|---------------|-----------------|
| ![Dashboard](https://via.placeholder.com/400x250/0a0a0f/00f3ff?text=Dashboard) | ![Bot Control](https://via.placeholder.com/400x250/0a0a0f/00f3ff?text=Bot+Management) | ![Users](https://via.placeholder.com/400x250/0a0a0f/00f3ff?text=User+Management) |

### Telegram Bot Interface

| Main Menu | Service Purchase | Payment |
|-----------|-----------------|---------|
| ![Bot Menu](https://via.placeholder.com/400x250/0a0a0f/00f3ff?text=Telegram+Bot) | ![Purchase](https://via.placeholder.com/400x250/0a0a0f/00f3ff?text=Service+Purchase) | ![Payment](https://via.placeholder.com/400x250/0a0a0f/00f3ff?text=Payment) |

> 💡 *Screenshots coming soon - Add your own screenshots to showcase the interface!*

</div>

---

## 🏗️ Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                    Telegram Bot (index.php)                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │ User Handler │  │ Payment API  │  │ Panel Manager │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│              Shared Database (MySQL)                         │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│  │  Users   │  │ Invoices │  │ Payments │  │  Panels  │   │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘   │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│            Web Admin Panel (webpanel/)                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │  Dashboard   │  │ Bot Control  │  │  Management   │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│              VPN Panels (Marzban, X-UI, etc.)               │
└─────────────────────────────────────────────────────────────┘
```

### Supported VPN Panels

- ✅ **Marzban** (New & Classic)
- ✅ **X-UI** (Single & Multi-user)
- ✅ **Hiddify**
- ✅ **WireGuard Dashboard**
- ✅ **3X-UI / s-ui**
- ✅ **Marzneshin**
- ✅ **IBSng**

### Payment Gateways

- ✅ **ZarinPal** (Iranian payment gateway)
- ✅ **NowPayments** (Cryptocurrency)
- ✅ **Plisio** (Cryptocurrency)
- ✅ **Tronado**
- ✅ **AqayePardakht**
- ✅ **IranPay**
- ✅ **Card-to-Card** (Manual processing)

---

## 📂 Project Structure

```
mirza_pro/
├── 📄 index.php                 # Telegram webhook handler
├── 📄 webhooks.php              # Webhook configuration utility
├── 📄 config.php                # Main configuration file
├── 📄 function.php              # Core business logic
├── 📄 botapi.php                # Telegram API wrapper
├── 📄 panels.php                # VPN panel management
├── 📄 install.sh                # Automated installer
├── 📄 mirza-cli.sh              # CLI management tool
│
├── 📁 webpanel/                 # Web Admin Panel
│   ├── 📄 index.php             # Dashboard
│   ├── 📄 setup.php             # Setup wizard
│   ├── 📄 login.php             # Authentication
│   ├── 📄 bot_management.php   # Bot control
│   ├── 📄 system.php            # System management
│   │
│   ├── 📁 includes/             # Backend logic
│   │   ├── auth.php             # Authentication system
│   │   ├── bot_core.php         # Bot integration bridge
│   │   ├── api.php              # API endpoints
│   │   ├── bot_control.php     # Bot operations
│   │   └── system_control.php   # System operations
│   │
│   └── 📁 assets/               # Frontend assets
│       ├── css/style.css        # Cyberpunk theme
│       └── js/main.js           # JavaScript utilities
│
├── 📁 payment/                  # Payment gateways
│   ├── zarinpal.php
│   ├── nowpayment.php
│   └── ...
│
├── 📁 cronbot/                  # Scheduled tasks
│   ├── statusday.php            # Daily reports
│   ├── configtest.php           # Service expiration
│   └── ...
│
├── 📁 database/                 # Database schemas
│   └── schema.sql
│
├── 📁 configs/                  # Server configurations
│   ├── nginx/mirza_pro.conf
│   └── supervisor/mirza_bot.conf
│
└── 📁 docs/                     # Documentation
    └── DEPLOYMENT.md
```

---

## 🛠️ Management Tools

### CLI Management Tool

After installation, use the beautiful CLI tool:

```bash
# Open interactive menu
sudo mirza

# Direct commands
sudo mirza status              # Check bot status
sudo mirza start               # Start bot
sudo mirza restart             # Restart bot
sudo mirza logs                # View live logs
sudo mirza reset-admin         # Change admin credentials
sudo mirza change-db-password  # Change database password
sudo mirza view-creds          # View all credentials
```

### Web Panel Management

- **Bot Control**: Start/stop/restart, view logs, manage webhook
- **User Management**: View, edit, suspend users
- **Payment Processing**: Approve/reject payments
- **System Operations**: SSL, backups, cron jobs
- **Panel Configuration**: Manage VPN panels

---

## 🔐 Security Features

<div align="center">

| Feature | Status |
|---------|--------|
| 🔒 Password Hashing (bcrypt) | ✅ |
| 🛡️ Session Management | ✅ |
| 🔐 CSRF Protection | ✅ |
| 🚫 SQL Injection Prevention (PDO) | ✅ |
| 🛡️ XSS Protection | ✅ |
| 📝 Activity Logging | ✅ |
| 🔒 File Permission Hardening | ✅ |
| 🚦 Rate Limiting Support | ✅ |
| 🔐 HTTPS/SSL Support | ✅ |

</div>

---

## 📚 Documentation

### 📖 Main Documentation

- **[Complete Deployment Guide](docs/DEPLOYMENT.md)** - Full installation, SSL setup, troubleshooting
- **[Web Panel Guide](webpanel/README.md)** - Admin panel features and usage
- **[Integration Guide](WEBPANEL_BOT_INTEGRATION_GUIDE.md)** - Bot ↔ Panel integration details
- **[Installation Guide](webpanel/INSTALLATION_GUIDE.md)** - Step-by-step setup instructions

### 📄 Additional Resources

- **[Changelog](CHANGELOG.md)** - Version history and updates
- **[Bug Fixes](BUGFIXES.md)** - Known issues and fixes
- **[Contributing](CONTRIBUTING.md)** - How to contribute
- **[Fork Notice](FORK_NOTICE.md)** - What's different in this fork

---

## 🔄 Updates & Maintenance

### Update Installation

```bash
cd /var/www/mirza_pro
git pull origin main
sudo supervisorctl restart mirza_bot
sudo systemctl restart php8.2-fpm nginx
```

### Backup & Restore

**Via Web Panel:**
1. Login → System Management
2. Choose backup type (Database / Files / Full)
3. Download when ready

**Via CLI:**
```bash
# Database backup
mysqldump -u mirza_user -p mirza_pro > backup.sql

# Full backup
tar -czf mirza_backup.tar.gz /var/www/mirza_pro
```

---

## 🐛 Troubleshooting

### Bot Not Responding?

```bash
# Check bot status
sudo supervisorctl status mirza_bot

# View logs
sudo tail -f /var/log/mirza_bot.log

# Restart bot
sudo supervisorctl restart mirza_bot
```

### Web Panel Not Loading?

```bash
# Check services
sudo systemctl status nginx php8.2-fpm

# View error logs
sudo tail -f /var/log/nginx/error.log
```

### SSL Issues?

```bash
# Check certificate
sudo certbot certificates

# Renew certificate
sudo certbot renew --nginx
```

📖 **Full troubleshooting guide**: [DEPLOYMENT.md#troubleshooting](docs/DEPLOYMENT.md#troubleshooting)

---

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. **Fork the repository**
2. **Create feature branch** (`git checkout -b feature/AmazingFeature`)
3. **Commit changes** (`git commit -m 'Add AmazingFeature'`)
4. **Push to branch** (`git push origin feature/AmazingFeature`)
5. **Open Pull Request**

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for detailed guidelines.

---

## 📊 Changelog

### Version 2.0.0 - Latest (2025-01-XX)

#### ✨ New Features
- 🎨 **Cyberpunk UI Redesign** - Modern dark theme with light mode toggle
- 🚀 **Enhanced CLI Tool** - Beautiful interactive menu with password management
- 🔒 **SSL Automation** - Automatic certificate installation on domain setup
- ⚙️ **Improved Setup Wizard** - Auto-configures bot, starts services, sets webhook
- 📝 **Credential Display** - Installation script shows all credentials clearly

#### 🔧 Improvements
- ✅ Fixed bot startup after installation
- ✅ Improved database integration
- ✅ Enhanced payment function integration
- ✅ Better error handling and logging
- ✅ Optimized installation process

#### 🐛 Bug Fixes
- Fixed duplicate payment functions
- Fixed config.php credential loading
- Fixed SSL automation issues
- Fixed bot management in web panel

### Version 1.1.0

- ✅ Full web panel ↔ bot synchronization
- ✅ Category-based notifications system
- ✅ Bot management from panel
- ✅ System operations (SSL, backups, cron)

### Version 1.0.0

- ✅ Initial professional web panel release
- ✅ Automated installer
- ✅ SSL automation
- ✅ Backup system

📄 **Full Changelog**: [CHANGELOG.md](CHANGELOG.md)

---

## 💖 Support & Credits

### Original Project

This fork is based on the excellent work by **[mahdiMGF2](https://github.com/mahdiMGF2/mirza_pro)**.

If you find the original project helpful, consider supporting:
👉 [Support on NowPayments](https://nowpayments.io/donation/permiumbotmirza)

### This Fork

If you appreciate the web panel and automation features:

- ⭐ **Star this repository** to help others discover it!
- 🐛 **Report bugs** via [GitHub Issues](https://github.com/amirmff/mirza_pro/issues)
- 💡 **Suggest features** or improvements
- 📢 **Share** with others who might find it useful

---

## 📝 License

This project is licensed under the **MIT License** - see the [LICENSE](LICENSE) file for details.

---

## ⚠️ Disclaimer

This software is provided for **educational and legitimate business purposes**. Users are responsible for compliance with local laws and regulations regarding VPN services.

---

## 🌟 Star History

<div align="center">

If you find this project useful, please give it a star ⭐

[![Star History Chart](https://api.star-history.com/svg?repos=amirmff/mirza_pro&type=Date)](https://star-history.com/#amirmff/mirza_pro&Date)

</div>

---

<div align="center">

### Made with ❤️ for the VPN community

**Forked from**: [mahdiMGF2/mirza_pro](https://github.com/mahdiMGF2/mirza_pro)  
**Maintained by**: [amirmff](https://github.com/amirmff)  
**Version**: 2.0.0  
**Last Updated**: 2025-01-XX

[⬆ Back to Top](#-mirza-pro)

</div>
