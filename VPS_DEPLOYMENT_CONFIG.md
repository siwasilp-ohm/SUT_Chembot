# 🚀 VPS Deployment Configuration Guide
# ChemInventory AI for https://ohm044.xyz/v1

**สร้างเมื่อ:** 19 กุมภาพันธ์ 2026  
**Domain:** https://ohm044.xyz/v1  
**Environment:** XAMPP on VPS  
**Status:** Ready for Deployment  

---

## 📋 Pre-Deployment Checklist

### ✅ Files Generated:
- [x] `.htaccess` - URL routing & security configuration
- [x] `.env` - Environment variables
- [x] This guide

### ⚠️ VPS Prerequisites:
- [ ] Apache 2.4+ with `mod_rewrite` enabled
- [ ] PHP 8.0+ with `mod_php` or PHP-FPM
- [ ] MySQL 8.0+ or MariaDB 10.5+
- [ ] SSL Certificate (Let's Encrypt recommended)
- [ ] XAMPP installed and running
- [ ] Domain DNS pointing to VPS IP

---

## 📁 Directory Structure on VPS

```
/var/www/html/
├── v1/                          ← Application root
│   ├── .htaccess               ← URL routing rules
│   ├── .env                    ← Environment variables
│   ├── .env.example            ← Template
│   ├── index.php               ← Main entry point
│   ├── composer.json
│   ├── composer.lock
│   ├── 📂 includes/            ← Core classes
│   ├── 📂 api/                 ← API endpoints
│   ├── 📂 pages/               ← UI Pages
│   ├── 📂 assets/
│   │   ├── 📂 uploads/         ← User uploads (writable)
│   │   ├── 📂 logs/            ← Asset logs (writable)
│   │   └── 📂 js/
│   ├── 📂 sql/                 ← Database scripts
│   ├── 📂 data/                ← CSV data
│   ├── 📂 lang/                ← Translations
│   ├── 📂 logs/                ← App logs (writable)
│   ├── 📂 vendor/              ← Composer dependencies
│   └── 📂 module3d/            ← 3D module
```

---

## 🔧 Step-by-Step Deployment

### Step 1: Connect to VPS

```bash
ssh user@ohm044.xyz
# or ssh root@ohm044.xyz (if using root)
```

### Step 2: Create Web Root Directory

```bash
# Create directory structure
sudo mkdir -p /var/www/html/v1
sudo mkdir -p /var/www/html/v1/assets/uploads
sudo mkdir -p /var/www/html/v1/assets/logs
sudo mkdir -p /var/www/html/v1/logs

# Set proper ownership (replace www-data with your web server user)
sudo chown -R www-data:www-data /var/www/html/v1
```

### Step 3: Upload Application Files

```bash
# From your local machine:
scp -r c:/xampp/htdocs/v1/* user@ohm044.xyz:/var/www/html/v1/

# Or using rsync for better performance:
rsync -avz --delete c:/xampp/htdocs/v1/ user@ohm044.xyz:/var/www/html/v1/
```

### Step 4: Set File Permissions

```bash
# SSH into VPS
ssh user@ohm044.xyz

# Navigate to project root
cd /var/www/html/v1

# Set directory permissions
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# Make writable directories
chmod -R 775 assets/uploads
chmod -R 775 assets/logs
chmod -R 775 logs

# Make PHP files executable by web server
chmod -R 755 api
chmod -R 755 pages
```

### Step 5: Install PHP Dependencies

```bash
cd /var/www/html/v1

# Install Composer if not already installed
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# Install dependencies
composer install --no-dev --optimize-autoloader

# If permissions issue, run as web server user:
sudo -u www-data composer install --no-dev --optimize-autoloader
```

### Step 6: Configure Database

```bash
# Connect to MySQL
mysql -u root -p

# Create database
CREATE DATABASE chem_inventory_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Create user (change password!)
CREATE USER 'chemuser'@'localhost' IDENTIFIED BY 'strong_password_here';

# Grant privileges
GRANT ALL PRIVILEGES ON chem_inventory_db.* TO 'chemuser'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import schema
mysql -u chemuser -p chem_inventory_db < /var/www/html/v1/sql/database_schema.sql
```

### Step 7: Update .env File

```bash
cd /var/www/html/v1

# Edit .env with your VPS settings
sudo nano .env
```

**Update these values:**

```env
# Database
DB_HOST=localhost
DB_NAME=chem_inventory_db
DB_USER=chemuser
DB_PASS=your_strong_password_here

# Application
APP_URL=https://ohm044.xyz/v1
APP_ENV=production

# Security (generate new)
JWT_SECRET=<generate-new-secure-key>

# Optional: Email
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
```

**Generate JWT_SECRET:**
```bash
php -r 'echo "JWT_SECRET=" . bin2hex(random_bytes(32)) . "\n";'
```

### Step 8: Enable Apache Modules

```bash
# Check if modules are enabled
sudo apache2ctl -M | grep rewrite

# If not enabled, enable them:
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod deflate
sudo a2enmod expires
sudo a2enmod ssl

# Restart Apache
sudo systemctl restart apache2
```

### Step 9: Configure Virtual Host (if needed)

If your domain needs a separate virtual host, create one:

```bash
sudo nano /etc/apache2/sites-available/ohm044.xyz.conf
```

**Add:**
```apache
<VirtualHost *:80>
    ServerName ohm044.xyz
    ServerAlias www.ohm044.xyz
    DocumentRoot /var/www/html

    <Directory /var/www/html/v1>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/ohm044.xyz-error.log
    CustomLog ${APACHE_LOG_DIR}/ohm044.xyz-access.log combined
</VirtualHost>

<VirtualHost *:443>
    ServerName ohm044.xyz
    ServerAlias www.ohm044.xyz
    DocumentRoot /var/www/html

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/ohm044.xyz/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/ohm044.xyz/privkey.pem

    <Directory /var/www/html/v1>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/ohm044.xyz-error.log
    CustomLog ${APACHE_LOG_DIR}/ohm044.xyz-access.log combined
</VirtualHost>
```

**Enable the site:**
```bash
sudo a2ensite ohm044.xyz.conf
sudo systemctl reload apache2
```

### Step 10: Set Up SSL Certificate (Let's Encrypt)

```bash
# Install certbot
sudo apt-get install certbot python3-certbot-apache

# Generate certificate
sudo certbot certonly --apache -d ohm044.xyz -d www.ohm044.xyz

# Certificates will be at:
# /etc/letsencrypt/live/ohm044.xyz/
```

### Step 11: Test Application

```bash
# Check if .htaccess is working
curl -I https://ohm044.xyz/v1/

# Should return 200 OK
# Test API
curl https://ohm044.xyz/v1/api/auth/

# Check logs
tail -f /var/www/html/v1/logs/error.log
```

---

## 🔐 Security Configuration

### Update .htaccess for Your Domain

The `.htaccess` file already includes:
- ✅ HTTPS redirect
- ✅ Protection of sensitive files
- ✅ Directory listing disabled
- ✅ Security headers
- ✅ Hotlinking prevention

**Verify RewriteBase:**
```
RewriteBase /v1/
```
This should match your directory structure. If you're using a subdomain, adjust accordingly.

### Firewall Rules

```bash
# Allow HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow SSH (adjust port if needed)
sudo ufw allow 22/tcp

# Enable firewall
sudo ufw enable
```

---

## 📊 Performance Optimization

### Enable Caching

The `.htaccess` includes browser caching headers. To enable further optimization:

```bash
# Enable PHP OPcache
sudo nano /etc/php/8.1/apache2/php.ini
```

**Set:**
```ini
[opcache]
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

### Database Optimization

```bash
mysql -u root -p chem_inventory_db

# Add indexes for frequently queried columns
ALTER TABLE users ADD INDEX idx_email (email);
ALTER TABLE chemicals ADD INDEX idx_cas_number (cas_number);
ALTER TABLE containers ADD INDEX idx_chemical_id (chemical_id);
ALTER TABLE locations ADD INDEX idx_location_code (location_code);
```

---

## 📝 Logging & Monitoring

### Application Logs

- **Error Log:** `/var/www/html/v1/logs/error.log`
- **Apache Access:** `/var/log/apache2/ohm044.xyz-access.log`
- **Apache Error:** `/var/log/apache2/ohm044.xyz-error.log`

### View Logs

```bash
# Real-time error log
tail -f /var/www/html/v1/logs/error.log

# Apache access log
tail -f /var/log/apache2/ohm044.xyz-access.log

# Last 100 lines
tail -n 100 /var/www/html/v1/logs/error.log
```

---

## 🆘 Troubleshooting

### Issue: 404 Not Found on all pages

**Solution:**
- Verify `mod_rewrite` is enabled: `sudo a2enmod rewrite`
- Check `.htaccess` location (should be in `/var/www/html/v1/`)
- Verify `RewriteBase /v1/` matches your directory
- Check Apache error log: `tail -f /var/log/apache2/error.log`

### Issue: Database connection failed

**Solution:**
```bash
# Check MySQL is running
sudo systemctl status mysql

# Start if stopped
sudo systemctl start mysql

# Verify credentials in .env
# Test connection:
mysql -u chemuser -p -h localhost chem_inventory_db -e "SELECT 1;"
```

### Issue: HTTPS/SSL certificate issues

**Solution:**
```bash
# Renew certificate
sudo certbot renew

# Set auto-renewal
sudo certbot renew --dry-run

# Or for manual renewal
sudo systemctl restart certbot.timer
```

### Issue: Upload permissions denied

**Solution:**
```bash
sudo chown www-data:www-data /var/www/html/v1/assets/uploads
sudo chmod 775 /var/www/html/v1/assets/uploads
```

### Issue: PHP memory/timeout errors

**Edit `/etc/php/8.1/apache2/php.ini`:**
```ini
memory_limit=256M
max_execution_time=300
upload_max_filesize=50M
post_max_size=50M
```

---

## 🔄 Maintenance Tasks

### Weekly
- [ ] Check application logs
- [ ] Monitor disk space: `df -h`
- [ ] Monitor memory: `free -h`

### Monthly
- [ ] Update SSL certificate: `sudo certbot renew`
- [ ] Update PHP packages: `sudo apt update && sudo apt upgrade`
- [ ] Database backup

### Quarterly
- [ ] Security audit
- [ ] Performance review
- [ ] Update dependencies: `composer update`

---

## 📞 Support & References

- **PHP Documentation:** https://www.php.net/
- **Apache Rewrite Guide:** https://httpd.apache.org/docs/current/mod/mod_rewrite.html
- **MySQL Documentation:** https://dev.mysql.com/doc/
- **Let's Encrypt:** https://letsencrypt.org/
- **Project README:** See `README.md`

---

## ✅ Deployment Verification Checklist

After deployment, verify:

- [ ] Application accessible at https://ohm044.xyz/v1
- [ ] Login page loads without errors
- [ ] Can create test user account
- [ ] Dashboard displays correctly
- [ ] API endpoints responding
- [ ] Database queries working
- [ ] File uploads working
- [ ] SSL certificate valid
- [ ] No console errors
- [ ] Performance is acceptable
- [ ] Logs are being written correctly
- [ ] Backups scheduled

---

## 🎯 Next Steps

1. **Finalize Configuration:**
   - Update all `.env` values for production
   - Generate strong JWT_SECRET
   - Configure email settings

2. **Load Initial Data:**
   - Import CSV data from `/data/` directory
   - Create admin user account
   - Test all modules

3. **Setup Monitoring:**
   - Configure log rotation
   - Setup uptime monitoring
   - Enable error notifications

4. **Security Hardening:**
   - Enable 2FA if supported
   - Regular security updates
   - Implement backup strategy

---

**Deployment Configuration Complete! ✨**  
Your ChemInventory AI system is ready for production deployment on https://ohm044.xyz/v1

