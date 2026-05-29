# 🚀 VPS Deployment Quick Start

**Project:** ChemInventory AI (SUT chemBot)  
**Domain:** https://ohm044.xyz/v1  
**Environment:** XAMPP on VPS  
**Created:** 19 February 2026

---

## 📋 Generated Files

| File | Purpose |
|------|---------|
| `.htaccess` | URL routing, security, caching rules |
| `.env` | Environment variables |
| `VPS_DEPLOYMENT_CONFIG.md` | Complete deployment guide |
| `ohm044.xyz.conf` | Apache virtual host configuration |
| `deploy.sh` | Automated deployment script |
| `DEPLOYMENT.md` | Original deployment guide (reference) |

---

## ⚡ Quick Deployment (5 minutes)

### Option A: Automated Deployment (Recommended)

```bash
# SSH to your VPS
ssh root@ohm044.xyz

# Upload and run deployment script
cd /home/user
bash deploy.sh

# That's it! Follow the prompts
```

### Option B: Manual Deployment

#### 1️⃣ SSH to VPS
```bash
ssh root@ohm044.xyz
```

#### 2️⃣ Create directories
```bash
mkdir -p /var/www/html/v1/{assets/uploads,assets/logs,logs}
```

#### 3️⃣ Upload files (from your local machine)
```bash
rsync -avz c:/xampp/htdocs/v1/ root@ohm044.xyz:/var/www/html/v1/
```

#### 4️⃣ Set permissions
```bash
cd /var/www/html/v1
chown -R www-data:www-data .
find . -type d -exec chmod 755 {} \;
chmod -R 775 assets/uploads assets/logs logs
```

#### 5️⃣ Install dependencies
```bash
cd /var/www/html/v1
composer install --no-dev --optimize-autoloader
```

#### 6️⃣ Setup SSL
```bash
sudo certbot certonly --apache -d ohm044.xyz -d www.ohm044.xyz
```

#### 7️⃣ Configure Apache
```bash
# Copy and enable virtual host
sudo cp ohm044.xyz.conf /etc/apache2/sites-available/
sudo a2ensite ohm044.xyz
sudo a2enmod rewrite headers deflate expires ssl
sudo systemctl restart apache2
```

#### 8️⃣ Update .env
```bash
sudo nano /var/www/html/v1/.env
```

**Set:**
```env
DB_HOST=localhost
DB_NAME=chem_inventory_db
DB_USER=chemuser
DB_PASS=your_password
APP_URL=https://ohm044.xyz/v1
JWT_SECRET=<generate-new>
```

**Generate JWT_SECRET:**
```bash
php -r 'echo bin2hex(random_bytes(32));'
```

#### 9️⃣ Setup database
```bash
mysql -u root << EOF
CREATE DATABASE chem_inventory_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'chemuser'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON chem_inventory_db.* TO 'chemuser'@'localhost';
FLUSH PRIVILEGES;
EOF

# Import schema
mysql -u chemuser -p chem_inventory_db < /var/www/html/v1/sql/database_schema.sql
```

#### 🔟 Test
```bash
curl -I https://ohm044.xyz/v1/
# Should return 200 OK
```

---

## ✅ Verification

### Check Application
```bash
# Visit in browser
https://ohm044.xyz/v1

# Should see login page
```

### Check Logs
```bash
# Error log
tail -f /var/www/html/v1/logs/error.log

# Apache error
tail -f /var/log/apache2/ohm044.xyz-error.log

# Apache access
tail -f /var/log/apache2/ohm044.xyz-access.log
```

### Test Database
```bash
mysql -u chemuser -p chem_inventory_db -e "SELECT COUNT(*) FROM users;"
```

### Test API
```bash
curl https://ohm044.xyz/v1/api/auth/
```

---

## 🔧 Configuration Checklist

- [ ] `.htaccess` copied to `/var/www/html/v1/`
- [ ] `.env` updated with production values
- [ ] Database created and imported
- [ ] SSL certificate installed
- [ ] Apache modules enabled
- [ ] File permissions set correctly
- [ ] Composer dependencies installed
- [ ] Application accessible at domain
- [ ] Database connections working
- [ ] Logs are writable

---

## 🔐 Security Checklist

- [ ] HTTPS enabled (HTTP redirects to HTTPS)
- [ ] `.env` file not accessible
- [ ] Debug files blocked
- [ ] SQL files blocked
- [ ] Upload directory protected
- [ ] Directory listing disabled
- [ ] Security headers enabled
- [ ] JWT secret changed
- [ ] Database user password changed
- [ ] Firewall configured

---

## 📊 Project Settings

**Configuration Details:**

```
Domain:         https://ohm044.xyz/v1
Web Root:       /var/www/html
App Dir:        /var/www/html/v1
RewriteBase:    /v1/
Web Server:     Apache 2.4+
PHP Version:    8.0+
Database:       MySQL 8.0+ / MariaDB 10.5+
SSL Provider:   Let's Encrypt
Character Set:  utf8mb4
Timezone:       Asia/Bangkok
```

**Environment Variables:**

```env
APP_URL=https://ohm044.xyz/v1
DB_HOST=localhost
DB_NAME=chem_inventory_db
ENABLE_AR_FEATURES=true
ENABLE_AI_FEATURES=true
```

---

## 🎯 What's Included

### .htaccess Features
✅ HTTPS redirect  
✅ Clean URL routing  
✅ Security headers  
✅ Gzip compression  
✅ Browser caching  
✅ Hotlink protection  
✅ Upload protection  
✅ Sensitive file blocking  

### Apache Configuration
✅ HTTP → HTTPS redirect  
✅ SSL/TLS setup  
✅ Virtual host setup  
✅ Directory restrictions  
✅ Security headers  
✅ Performance optimization  

### Deployment Script
✅ Automated setup  
✅ Permission configuration  
✅ Apache module setup  
✅ SSL certificate  
✅ Database creation  
✅ Dependency installation  

---

## 📞 Troubleshooting

### 404 Error on all pages
```bash
# Check mod_rewrite is enabled
sudo apache2ctl -M | grep rewrite

# If not, enable it
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Database connection error
```bash
# Check MySQL is running
sudo systemctl status mysql

# Test connection
mysql -u chemuser -p -h localhost chem_inventory_db -e "SELECT 1;"
```

### SSL certificate not found
```bash
# Check certificate
ls -la /etc/letsencrypt/live/ohm044.xyz/

# If missing, run certbot
sudo certbot certonly --apache -d ohm044.xyz -d www.ohm044.xyz
```

### Upload permission denied
```bash
# Fix permissions
sudo chown www-data:www-data /var/www/html/v1/assets/uploads
sudo chmod 775 /var/www/html/v1/assets/uploads
```

### PHP memory/timeout errors
```bash
# Edit PHP config
sudo nano /etc/php/8.1/apache2/php.ini

# Set values
memory_limit=256M
max_execution_time=300
upload_max_filesize=50M
```

---

## 📚 Documentation Files

| File | Content |
|------|---------|
| `VPS_DEPLOYMENT_CONFIG.md` | Complete setup guide (detailed) |
| `README.md` | Project features & requirements |
| `DEPLOYMENT.md` | General deployment info |
| `PROJECT_STRUCTURE_ANALYSIS.md` | Codebase analysis |
| `deploy.sh` | Automated deployment script |

---

## 🔄 After Deployment

### Day 1
- [ ] Test all pages load correctly
- [ ] Create admin account
- [ ] Import sample data

### Week 1
- [ ] Test borrow system
- [ ] Test QR scanner
- [ ] Test API endpoints
- [ ] Load production data

### Month 1
- [ ] Monitor performance
- [ ] Check error logs
- [ ] Setup backups
- [ ] Train users

---

## 📞 Support

**Need help?**

1. Check `VPS_DEPLOYMENT_CONFIG.md` for detailed instructions
2. Review Apache logs: `/var/log/apache2/`
3. Check app logs: `/var/www/html/v1/logs/`
4. Run: `php test_system.php` for diagnostics

---

## ✨ You're All Set!

Your ChemInventory AI application is now ready for production at:

🌐 **https://ohm044.xyz/v1**

**Enjoy managing your chemical inventory! 🧪**

---

*Deployment configuration created: 19 February 2026*
