# Mirza Pro - Deployment Guide

Complete deployment guide for Mirza Pro Telegram Bot with Web Panel.

## 📋 Prerequisites

- Ubuntu 20.04 or 22.04 LTS
- Root or sudo access
- Domain name (optional, but recommended for SSL)
- Telegram Bot Token from [@BotFather](https://t.me/BotFather)
- Your Telegram User ID from [@userinfobot](https://t.me/userinfobot)

## 🚀 Quick Installation

### 1. Download and Run Installer

```bash
cd /var/www
git clone <your-repo-url> mirza_pro
cd mirza_pro
chmod +x install.sh
sudo ./install.sh
```

The installer will:
- Install PHP 8.1, MySQL, Nginx, Supervisor
- Configure firewall (UFW)
- Set up database and user
- Configure Nginx for web panel
- Set up Supervisor for bot process management
- Create necessary directories and permissions

### 2. Complete Web Setup

After installation, visit: `http://YOUR_SERVER_IP/webpanel/setup.php`

Follow the 3-step wizard:

**Step 1: Database Configuration**
- Database Name: `mirza_pro` (pre-filled)
- Username: `mirza_user` (pre-filled)
- Password: (auto-generated, pre-filled)

**Step 2: Bot Configuration**
- Bot Token: From @BotFather
- Admin Telegram ID: Your numeric ID
- Admin Panel Username: `admin` (default)
- Admin Panel Password: Choose a strong password
- Domain: Your domain (optional)

**Step 3: Automatic Setup**
- Creates config.php
- Imports database schema
- Creates admin user
- Sets webhook
- Completes installation

### 3. Login to Web Panel

Visit: `http://YOUR_SERVER_IP/webpanel/login.php`
- Username: `admin` (or what you set)
- Password: Your chosen password

## 🔒 SSL/HTTPS Setup (Recommended)

### Prerequisites
- A domain pointing to your server IP
- Port 80 and 443 open in firewall

### Method 1: Via Web Panel (Easiest)

1. Login to web panel
2. Go to "مدیریت سیستم" (System Management)
3. Click "📥 نصب SSL با Let's Encrypt"
4. Wait for completion (2-3 minutes)
5. Your site will be accessible via HTTPS

### Method 2: Manual Installation

```bash
sudo apt-get install certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com
```

Follow the prompts. Certbot will automatically:
- Obtain certificate
- Configure Nginx
- Set up auto-renewal

## 🔐 Security Hardening

### 1. Firewall Configuration

The installer sets up UFW automatically. Verify:

```bash
sudo ufw status
```

Should show:
```
22/tcp    ALLOW
80/tcp    ALLOW
443/tcp   ALLOW
```

### 2. Secure MySQL

Change root password (if not done):
```bash
sudo mysql_secure_installation
```

### 3. File Permissions

Verify correct permissions:
```bash
sudo chown -R www-data:www-data /var/www/mirza_pro
sudo chmod -R 755 /var/www/mirza_pro
sudo chmod 600 /var/www/mirza_pro/config.php
```

### 4. Disable Direct Config Access

Already configured in Nginx, but verify:
```bash
sudo nano /etc/nginx/sites-available/mirza_pro
```

Should contain:
```nginx
location ~ /(config\.php|\.git) {
    deny all;
}
```

### 5. Install Fail2Ban (Rate Limiting)

```bash
sudo apt-get install fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

Create jail for web panel:
```bash
sudo nano /etc/fail2ban/jail.local
```

Add:
```ini
[nginx-login]
enabled = true
port = http,https
filter = nginx-login
logpath = /var/log/nginx/access.log
maxretry = 5
bantime = 3600
```

Create filter:
```bash
sudo nano /etc/fail2ban/filter.d/nginx-login.conf
```

Add:
```ini
[Definition]
failregex = ^<HOST>.*POST /webpanel/login.php.*401
ignoreregex =
```

Restart:
```bash
sudo systemctl restart fail2ban
```

## 🤖 Bot Management

### Start/Stop/Restart Bot

#### Via Web Panel
1. Go to "مدیریت ربات" (Bot Management)
2. Use control buttons: Start, Stop, Restart

#### Via Command Line

```bash
# Start bot
sudo supervisorctl start mirza_bot

# Stop bot
sudo supervisorctl stop mirza_bot

# Restart bot
sudo supervisorctl restart mirza_bot

# Check status
sudo supervisorctl status mirza_bot

# View logs
sudo supervisorctl tail -f mirza_bot
```

### View Logs

```bash
# Real-time logs
tail -f /var/log/mirza_bot.log

# Last 100 lines
tail -n 100 /var/log/mirza_bot.log

# Search for errors
grep -i error /var/log/mirza_bot.log
```

### Webhook Management

#### Via Web Panel
"مدیریت ربات" → "🔗 تنظیم مجدد Webhook"

#### Via Command Line

```bash
# Set webhook
curl -X POST "https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook" \
  -d "url=https://yourdomain.com/webhooks.php"

# Check webhook
curl "https://api.telegram.org/bot<YOUR_TOKEN>/getWebhookInfo"

# Delete webhook (for polling mode)
curl -X POST "https://api.telegram.org/bot<YOUR_TOKEN>/deleteWebhook"
```

## 💾 Backup & Restore

### Automated Backups (via Web Panel)

1. Go to "مدیریت سیستم" (System Management)
2. Choose backup type:
   - Database only
   - Files only
   - Full backup (recommended)

### Manual Database Backup

```bash
# Create backup
mysqldump -u mirza_user -p mirza_pro > backup_$(date +%Y%m%d_%H%M%S).sql

# Compress
gzip backup_*.sql

# Download via SCP
scp user@server:/path/to/backup_*.sql.gz ./
```

### Restore Database

```bash
# Decompress if needed
gunzip backup_*.sql.gz

# Restore
mysql -u mirza_user -p mirza_pro < backup_*.sql
```

### Full System Backup

```bash
# Backup everything
sudo tar -czf mirza_pro_backup_$(date +%Y%m%d).tar.gz \
  -C /var/www mirza_pro \
  --exclude='mirza_pro/logs' \
  --exclude='mirza_pro/backups'

# Backup database separately
mysqldump -u mirza_user -p mirza_pro | gzip > db_backup_$(date +%Y%m%d).sql.gz
```

### Scheduled Automated Backups

Set up via web panel or crontab:

```bash
sudo crontab -e
```

Add daily backup at 2 AM:
```bash
0 2 * * * /usr/bin/mysqldump -u mirza_user -p'PASSWORD' mirza_pro | gzip > /backups/db_$(date +\%Y\%m\%d).sql.gz
0 2 * * * tar -czf /backups/files_$(date +\%Y\%m\%d).tar.gz -C /var/www mirza_pro
```

## 🔄 Updates

### Update Bot Code

```bash
cd /var/www/mirza_pro
git pull origin main
sudo supervisorctl restart mirza_bot
```

### Update Web Panel

```bash
cd /var/www/mirza_pro/webpanel
git pull origin main
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx
```

### Update System Packages

```bash
sudo apt-get update
sudo apt-get upgrade
sudo systemctl restart php8.1-fpm nginx
sudo supervisorctl restart mirza_bot
```

## 🐛 Troubleshooting

### Bot Not Responding

1. Check bot status:
```bash
sudo supervisorctl status mirza_bot
```

2. Check logs:
```bash
tail -f /var/log/mirza_bot.log
```

3. Verify webhook:
```bash
curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"
```

4. Test bot token:
```bash
curl "https://api.telegram.org/bot<TOKEN>/getMe"
```

### Database Connection Errors

1. Check MySQL status:
```bash
sudo systemctl status mysql
```

2. Test connection:
```bash
mysql -u mirza_user -p mirza_pro -e "SELECT 1"
```

3. Check credentials in `config.php`:
```bash
sudo nano /var/www/mirza_pro/config.php
```

### Web Panel Not Loading

1. Check Nginx status:
```bash
sudo systemctl status nginx
```

2. Check PHP-FPM:
```bash
sudo systemctl status php8.1-fpm
```

3. Check error logs:
```bash
sudo tail -f /var/log/nginx/error.log
```

4. Check permissions:
```bash
ls -la /var/www/mirza_pro/webpanel
```

### 502 Bad Gateway

Usually PHP-FPM issue:
```bash
sudo systemctl restart php8.1-fpm
```

Check PHP-FPM logs:
```bash
sudo tail -f /var/log/php8.1-fpm.log
```

### Permission Denied Errors

Fix ownership:
```bash
sudo chown -R www-data:www-data /var/www/mirza_pro
sudo chmod -R 755 /var/www/mirza_pro
```

## 📊 Monitoring

### System Resources

Via web panel: "مدیریت سیستم" shows live stats

Via command line:
```bash
# CPU and memory
htop

# Disk usage
df -h

# Network
netstat -tuln | grep LISTEN
```

### Bot Performance

```bash
# Check process
ps aux | grep php | grep bot

# Memory usage
ps -p $(pgrep -f "bot.php") -o %mem,%cpu,etime

# Open files
lsof -p $(pgrep -f "bot.php")
```

### Database Performance

```bash
# Login to MySQL
mysql -u root -p

# Check connections
SHOW PROCESSLIST;

# Check table sizes
SELECT table_name, 
       ROUND(((data_length + index_length) / 1024 / 1024), 2) AS "Size (MB)"
FROM information_schema.TABLES 
WHERE table_schema = "mirza_pro"
ORDER BY (data_length + index_length) DESC;
```

## 🔗 Useful Commands

### Supervisor
```bash
sudo supervisorctl status        # All processes
sudo supervisorctl restart all   # Restart all
sudo supervisorctl update        # Reload config
```

### Nginx
```bash
sudo nginx -t                    # Test config
sudo systemctl reload nginx      # Reload without downtime
sudo tail -f /var/log/nginx/access.log  # Access logs
```

### MySQL
```bash
sudo systemctl status mysql      # Status
sudo mysqladmin -u root -p processlist  # Active queries
sudo mysqladmin -u root -p status      # Server status
```

## 📞 Support

- Check logs in `/var/log/mirza_bot.log`
- Check web panel activity logs in database `admin_activity`
- Review Nginx logs: `/var/log/nginx/`
- Check PHP logs: `/var/log/php8.1-fpm.log`

## 🔄 Migration to New Server

1. **Backup current server:**
```bash
# Database
mysqldump -u mirza_user -p mirza_pro > mirza_backup.sql

# Files
tar -czf mirza_files.tar.gz /var/www/mirza_pro
```

2. **Transfer to new server:**
```bash
scp mirza_backup.sql newserver:/tmp/
scp mirza_files.tar.gz newserver:/tmp/
```

3. **Install on new server:**
```bash
# Run installer
sudo ./install.sh

# Restore database
mysql -u mirza_user -p mirza_pro < /tmp/mirza_backup.sql

# Restore files (selective)
tar -xzf /tmp/mirza_files.tar.gz -C /var/www/
```

4. **Update webhook:**
```bash
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
  -d "url=https://newdomain.com/webhooks.php"
```

## ✅ Post-Installation Checklist

- [ ] Bot responds to messages
- [ ] Web panel accessible
- [ ] SSL certificate installed (if using domain)
- [ ] Firewall configured
- [ ] Backups scheduled
- [ ] Supervisor managing bot process
- [ ] Webhook set and working
- [ ] Admin can login to panel
- [ ] Logs rotating properly
- [ ] File permissions correct

---

**Last Updated:** 2025-01-26  
**Version:** 1.0.0
