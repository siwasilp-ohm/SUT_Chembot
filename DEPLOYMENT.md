# ChemInventory AI - Deployment Guide

## System Requirements

### Minimum Requirements
- PHP 8.0+
- MySQL 8.0+ or MariaDB 10.5+
- Apache 2.4+ with mod_rewrite
- 2GB RAM
- 10GB disk space

### Recommended Requirements
- PHP 8.2+
- MySQL 8.0+
- Apache 2.4+ or Nginx 1.20+
- 4GB RAM
- SSL Certificate
- 50GB disk space (for file uploads)

## Quick Start

### Option 1: Docker Deployment (Recommended)

```bash
# Clone repository
git clone <repository-url>
cd cheminventory

# Copy environment file
cp .env.example .env

# Edit .env with your settings
nano .env

# Start with Docker Compose
docker-compose up -d

# Run database migrations
docker-compose exec app php migrate.php
```

### Option 2: Manual Installation

#### Step 1: Install Dependencies

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y apache2 mysql-server php8.1 php8.1-mysql php8.1-gd php8.1-mbstring php8.1-xml php8.1-curl composer

# Enable Apache modules
sudo a2enmod rewrite headers deflate expires
sudo systemctl restart apache2
```

#### Step 2: Configure Database

```bash
# Login to MySQL
sudo mysql -u root

# Create database and user
CREATE DATABASE chem_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'chemuser'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON chem_inventory.* TO 'chemuser'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import schema
mysql -u chemuser -p chem_inventory < sql/database_schema.sql
```

#### Step 3: Deploy Application

```bash
# Copy to web root
sudo cp -r cheminventory /var/www/html/
sudo chown -R www-data:www-data /var/www/html/cheminventory

# Set permissions
sudo chmod -R 755 /var/www/html/cheminventory
sudo chmod -R 775 /var/www/html/cheminventory/assets/uploads
sudo chmod -R 775 /var/www/html/cheminventory/assets/logs

# Install PHP dependencies
cd /var/www/html/cheminventory
sudo -u www-data composer install --no-dev --optimize-autoloader
```

#### Step 4: Configure Environment

```bash
# Copy environment file
cp .env.example .env

# Generate secure keys
JWT_SECRET=$(openssl rand -base64 32)
echo "JWT_SECRET=$JWT_SECRET" >> .env

# Edit other settings
nano .env
```

#### Step 5: Configure Apache

Create `/etc/apache2/sites-available/cheminventory.conf`:

```apache
<VirtualHost *:80>
    ServerName cheminventory.yourdomain.com
    DocumentRoot /var/www/html/cheminventory
    
    <Directory /var/www/html/cheminventory>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Enable compression
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css application/javascript application/json
    </IfModule>
    
    ErrorLog ${APACHE_LOG_DIR}/cheminventory-error.log
    CustomLog ${APACHE_LOG_DIR}/cheminventory-access.log combined
</VirtualHost>
```

Enable the site:
```bash
sudo a2ensite cheminventory
sudo systemctl reload apache2
```

#### Step 6: SSL with Let's Encrypt

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d cheminventory.yourdomain.com
```

## Post-Installation

### 1. Create Admin Account

```bash
# The default admin account is created automatically
# Username: admin
# Password: password

# Change password immediately after first login
```

### 2. Configure Cron Jobs

Add to crontab (`sudo crontab -e`):

```bash
# Check for expiring chemicals daily at 8 AM
0 8 * * * cd /var/www/html/cheminventory && php cron/check_expiry.php

# Check for low stock daily at 9 AM
0 9 * * * cd /var/www/html/cheminventory && php cron/check_low_stock.php

# Check for overdue borrows hourly
0 * * * * cd /var/www/html/cheminventory && php cron/check_overdue.php

# Send daily digest at 8:30 AM
30 8 * * * cd /var/www/html/cheminventory && php cron/send_digest.php
```

### 3. Configure Backups

Create `/opt/backup/backup-cheminventory.sh`:

```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/opt/backup/cheminventory"
DB_NAME="chem_inventory"
DB_USER="chemuser"
DB_PASS="your_password"

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u$DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/db_$DATE.sql

# Backup uploads
tar -czf $BACKUP_DIR/uploads_$DATE.tar.gz /var/www/html/cheminventory/assets/uploads

# Keep only last 7 days
find $BACKUP_DIR -name "*.sql" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete
```

Make executable and add to cron:
```bash
chmod +x /opt/backup/backup-cheminventory.sh
# Add to crontab: 0 2 * * * /opt/backup/backup-cheminventory.sh
```

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `DB_HOST` | Database host | localhost |
| `DB_NAME` | Database name | chem_inventory |
| `DB_USER` | Database user | root |
| `DB_PASS` | Database password | - |
| `APP_URL` | Application URL | http://localhost |
| `JWT_SECRET` | Secret for JWT tokens | - |
| `AI_API_KEY` | OpenAI API key (optional) | - |
| `VISION_API_KEY` | Google Vision API key (optional) | - |
| `SMTP_HOST` | SMTP server host | - |
| `SMTP_PORT` | SMTP server port | 587 |
| `SMTP_USER` | SMTP username | - |
| `SMTP_PASS` | SMTP password | - |

## Troubleshooting

### 500 Internal Server Error

```bash
# Check Apache error logs
sudo tail -f /var/log/apache2/cheminventory-error.log

# Check PHP error logs
sudo tail -f /var/www/html/cheminventory/assets/logs/error.log

# Verify file permissions
sudo chown -R www-data:www-data /var/www/html/cheminventory
sudo chmod -R 755 /var/www/html/cheminventory
```

### Database Connection Failed

```bash
# Test database connection
mysql -u chemuser -p -e "USE chem_inventory; SHOW TABLES;"

# Check MySQL is running
sudo systemctl status mysql

# Verify credentials in .env
cat /var/www/html/cheminventory/.env | grep DB_
```

### QR Scanner Not Working

- Ensure HTTPS is enabled (required for camera access)
- Check browser console for JavaScript errors
- Verify camera permissions are granted

### AR Not Displaying

- Use a modern browser (Chrome 90+, Safari 14+, Firefox 88+)
- Enable WebGL in browser settings
- Check that 3D model files are accessible

## Security Checklist

- [ ] Change default admin password
- [ ] Use strong JWT secret
- [ ] Enable HTTPS
- [ ] Configure firewall (allow only 80, 443)
- [ ] Set up regular backups
- [ ] Keep dependencies updated
- [ ] Enable 2FA for admin accounts
- [ ] Configure rate limiting
- [ ] Set up log monitoring
- [ ] Disable PHP error display in production

## Performance Optimization

### Enable OPcache

```bash
# Edit php.ini
sudo nano /etc/php/8.1/apache2/php.ini

# Add:
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
```

### Enable MySQL Query Cache

```sql
SET GLOBAL query_cache_type = 1;
SET GLOBAL query_cache_size = 268435456;
```

### Use CDN for Static Assets

Configure in `includes/config.php`:
```php
define('CDN_URL', 'https://cdn.yourdomain.com');
```

## Monitoring

### Install Monitoring Tools

```bash
# Install Netdata
curl -Ss https://my-netdata.io/kickstart.sh | bash

# Or use Prometheus + Grafana
```

### Key Metrics to Monitor

- HTTP response times
- Database query performance
- Disk usage (uploads folder)
- Memory usage
- Error rates
- Active users

## Updates

### Update Application

```bash
cd /var/www/html/cheminventory
sudo -u www-data git pull
sudo -u www-data composer install --no-dev --optimize-autoloader

# Run migrations if needed
sudo -u www-data php migrate.php
```

### Update Database Schema

```bash
# Backup first
mysqldump -u chemuser -p chem_inventory > backup_$(date +%Y%m%d).sql

# Apply updates
mysql -u chemuser -p chem_inventory < sql/updates/update_x.x.x.sql
```

## Support

For additional support:
- Documentation: https://docs.cheminventory.local
- Email: support@cheminventory.local
- GitHub Issues: https://github.com/cheminventory/issues
