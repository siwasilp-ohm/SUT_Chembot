# ✅ VPS Deployment Files - Complete Package

**Project:** ChemInventory AI (SUT chemBot)  
**Domain:** https://ohm044.xyz/v1  
**Created:** 19 February 2026

---

## 📦 Deployment Package Contents

### 1. **Configuration Files**

| File | Purpose | Location on VPS |
|------|---------|-----------------|
| `.htaccess` | Apache URL routing & security | `/var/www/html/v1/.htaccess` |
| `.env` | Environment variables | `/var/www/html/v1/.env` |
| `ohm044.xyz.conf` | Apache virtual host config | `/etc/apache2/sites-available/` |
| `ohm044.xyz.nginx` | Nginx alternative config | `/etc/nginx/sites-available/` |

### 2. **Documentation Files**

| File | Content | Users |
|------|---------|-------|
| `VPS_DEPLOYMENT_CONFIG.md` | Complete setup guide (detailed) | Developers, DevOps |
| `DEPLOYMENT_QUICK_START.md` | Quick reference guide | Developers |
| `deploy.sh` | Automated deployment script | DevOps |

---

## 🎯 Key Features Configured

### Security ✅
- ✅ HTTPS/SSL with Let's Encrypt
- ✅ HTTP → HTTPS redirect
- ✅ SQL injection protection
- ✅ Directory traversal protection
- ✅ Upload directory protection
- ✅ Sensitive file blocking (.env, .git, vendor, etc.)
- ✅ Security headers (HSTS, X-Frame-Options, CSP, etc.)
- ✅ Hotlink protection
- ✅ Directory listing disabled

### Performance ✅
- ✅ Gzip compression for text files
- ✅ Browser caching (1 year for static assets)
- ✅ 304 Not Modified responses
- ✅ Cache headers for dynamic content
- ✅ PHP OPcache ready

### Routing ✅
- ✅ Clean URLs (no .php extensions)
- ✅ API endpoint routing
- ✅ Pages routing
- ✅ Module3D routing
- ✅ AR module routing
- ✅ Direct file/folder access preserved

### Compatibility ✅
- ✅ Apache 2.4+ support
- ✅ Nginx alternative provided
- ✅ PHP 8.0+ compatible
- ✅ MySQL/MariaDB compatible
- ✅ UTF-8/Thai language support

---

## 📝 Files Summary

### `.htaccess` (290 lines)
**Apache configuration file for URL rewriting and security**

Features:
- RewriteBase set to `/v1/` (matches your directory structure)
- Blocks sensitive files: .env, .git, vendor, composer.json, sql files
- Blocks debug files: debug_*.php, test_*.php, *_backup.php
- HTTPS redirect
- API & Page routing
- Module3D & AR routing
- Security headers
- Gzip compression
- Browser caching
- Hotlink protection
- Upload directory protection

### `.env` (45 lines)
**Environment variables template for VPS**

Pre-configured values:
```
APP_URL=https://ohm044.xyz/v1
DB_HOST=localhost
APP_TIMEZONE=Asia/Bangkok
```

Required updates:
- DB_PASS (database password)
- JWT_SECRET (generate new)
- Optional: AI_API_KEY, SMTP settings

### `ohm044.xyz.conf` (150 lines)
**Apache Virtual Host configuration**

Features:
- HTTP/HTTPS setup with Let's Encrypt
- Directory permissions
- PHP execution restrictions in uploads
- Security headers
- Caching configuration
- CORS-ready headers

### `ohm044.xyz.nginx` (200 lines)
**Nginx server configuration (alternative to Apache)**

Features:
- HTTP/HTTPS redirect
- SSL/TLS configuration
- PHP-FPM socket setup
- Routing rules
- Security location blocks
- Gzip compression
- Cache control headers

### `VPS_DEPLOYMENT_CONFIG.md` (450 lines)
**Complete deployment guide**

Sections:
1. Pre-deployment checklist
2. Step-by-step installation
3. Database setup
4. Virtual host configuration
5. SSL certificate setup
6. Performance optimization
7. Logging & monitoring
8. Troubleshooting guide
9. Maintenance tasks
10. Support references

### `DEPLOYMENT_QUICK_START.md` (200 lines)
**Quick reference guide**

Includes:
- Quick deployment options (automated & manual)
- Configuration checklist
- Verification steps
- Troubleshooting
- Post-deployment tasks

### `deploy.sh` (350 lines)
**Automated bash deployment script**

What it does:
1. Checks prerequisites
2. Creates directories
3. Sets file permissions
4. Enables Apache modules
5. Installs PHP dependencies
6. Configures virtual host
7. Sets up SSL with Let's Encrypt
8. Creates database
9. Imports database schema
10. Restarts Apache

---

## 🚀 Getting Started

### For Developers:

1. **Review the setup:**
   ```
   Read: DEPLOYMENT_QUICK_START.md
   Reference: VPS_DEPLOYMENT_CONFIG.md
   ```

2. **Copy files to VPS:**
   - `.htaccess` → `/var/www/html/v1/`
   - `.env` → `/var/www/html/v1/`
   - `ohm044.xyz.conf` → `/etc/apache2/sites-available/`

3. **Run deployment:**
   ```bash
   bash deploy.sh
   ```

### For DevOps:

1. **Use the Apache config:**
   ```bash
   sudo cp ohm044.xyz.conf /etc/apache2/sites-available/
   sudo a2ensite ohm044.xyz
   ```

2. **Or use the Nginx config (if running Nginx):**
   ```bash
   sudo cp ohm044.xyz.nginx /etc/nginx/sites-available/ohm044.xyz
   sudo ln -s /etc/nginx/sites-available/ohm044.xyz /etc/nginx/sites-enabled/
   ```

3. **Setup SSL:**
   ```bash
   sudo certbot certonly --apache -d ohm044.xyz
   ```

4. **Test:**
   ```bash
   curl -I https://ohm044.xyz/v1/
   ```

---

## 🔍 File Checklist

Before uploading to VPS, verify all files exist:

- [ ] `.htaccess` - URL routing
- [ ] `.env` - Environment variables
- [ ] `ohm044.xyz.conf` - Apache config
- [ ] `ohm044.xyz.nginx` - Nginx config (optional)
- [ ] `deploy.sh` - Deployment script
- [ ] `VPS_DEPLOYMENT_CONFIG.md` - Detailed guide
- [ ] `DEPLOYMENT_QUICK_START.md` - Quick guide

---

## 🔧 Configuration Details

### RewriteBase Setting
```
RewriteBase /v1/
```
This tells Apache that the application is in `/v1/` directory.
Adjust if your directory structure is different.

### Domain & URLs
```
Domain:         ohm044.xyz
HTTPS:          https://ohm044.xyz/v1
Redirects:      http://ohm044.xyz → https://ohm044.xyz/v1
Alt Domains:    www.ohm044.xyz
```

### Database
```
Host:           localhost
Name:           chem_inventory_db
User:           chemuser (recommended, change from root)
Charset:        utf8mb4
Collation:      utf8mb4_unicode_ci
```

### PHP
```
Version:        8.0+
OPcache:        Recommended
Memory Limit:   256M
Execution Time: 300s
Upload Size:    50M
```

---

## ⚙️ Apache Modules Required

All configured files assume these modules are enabled:

- `mod_rewrite` - URL rewriting
- `mod_headers` - HTTP header manipulation
- `mod_deflate` - Gzip compression
- `mod_expires` - Expiry headers
- `mod_ssl` - HTTPS support
- `mod_php` - PHP execution (or PHP-FPM)

**Enable all modules:**
```bash
sudo a2enmod rewrite headers deflate expires ssl
```

---

## 📊 Performance Metrics

The configuration is optimized for:

| Metric | Setting | Benefits |
|--------|---------|----------|
| **Cache Lifetime (JS/CSS/Images)** | 1 year | Reduced bandwidth, faster loads |
| **Cache Lifetime (HTML)** | 2 hours | Balance between freshness and performance |
| **Gzip Compression** | Enabled | ~70% reduction in text file sizes |
| **SSL/TLS Version** | 1.2 & 1.3 | Modern security standard |
| **Database Charset** | utf8mb4 | Full Unicode + Thai support |

---

## 🔐 Security Features Included

| Feature | Configured | Details |
|---------|-----------|---------|
| **HTTPS** | ✅ | SSL/TLS 1.2+ |
| **HSTS** | ✅ | 1-year directive with preload |
| **Security Headers** | ✅ | X-Frame-Options, X-Content-Type-Options, etc. |
| **Input Validation** | ✅ | SQL whitelist in database.php |
| **File Upload Protection** | ✅ | PHP execution blocked in uploads |
| **Directory Traversal** | ✅ | Blocked by .htaccess |
| **Sensitive File Blocking** | ✅ | .env, .git, vendor, sql files blocked |
| **Rate Limiting** | ⚠️ | Not configured (add separately if needed) |
| **CSRF Protection** | ⚠️ | Application level (verify in code) |

---

## 📞 Support & Troubleshooting

### Common Issues & Solutions

#### 404 errors on all pages
**Cause:** mod_rewrite not enabled  
**Fix:** `sudo a2enmod rewrite && sudo systemctl restart apache2`

#### Database connection failed
**Cause:** Wrong credentials or database not created  
**Fix:** Check `.env` and verify database exists

#### SSL certificate not found
**Cause:** Certbot not run yet  
**Fix:** `sudo certbot certonly --apache -d ohm044.xyz`

#### Upload failed
**Cause:** Wrong directory permissions  
**Fix:** `sudo chmod 775 /var/www/html/v1/assets/uploads`

See `VPS_DEPLOYMENT_CONFIG.md` for more troubleshooting.

---

## 📋 Post-Deployment Tasks

After successful deployment:

1. **Test Application**
   - [ ] Login page loads
   - [ ] Can create user account
   - [ ] Dashboard displays
   - [ ] Can upload files

2. **Load Data**
   - [ ] Import CSV data from `/data/` directory
   - [ ] Create admin account
   - [ ] Add chemicals to inventory

3. **Configure Features**
   - [ ] Set up email (optional)
   - [ ] Configure AI API (optional)
   - [ ] Enable AR features
   - [ ] Setup SSL auto-renewal

4. **Monitor & Maintain**
   - [ ] Setup log rotation
   - [ ] Schedule daily backups
   - [ ] Monitor performance
   - [ ] Plan security updates

---

## 🎓 Learning Resources

**Included Documentation:**
- `README.md` - Project features
- `DEPLOYMENT.md` - Original deployment guide
- `VPS_DEPLOYMENT_CONFIG.md` - Detailed setup
- `PROJECT_STRUCTURE_ANALYSIS.md` - Code analysis

**External References:**
- Apache Rewrite Guide: https://httpd.apache.org/docs/current/mod/mod_rewrite.html
- Nginx Documentation: https://nginx.org/en/docs/
- Let's Encrypt: https://letsencrypt.org/
- MySQL Documentation: https://dev.mysql.com/doc/
- PHP Documentation: https://www.php.net/docs.php

---

## ✨ Summary

This deployment package provides:

✅ **Complete Apache Configuration** - Ready-to-use .htaccess and virtual host  
✅ **Security Hardening** - HTTPS, security headers, file protection  
✅ **Performance Optimization** - Caching, compression, optimization rules  
✅ **Automated Deployment** - One-command setup script  
✅ **Detailed Documentation** - Step-by-step guides and troubleshooting  
✅ **Nginx Alternative** - For servers using Nginx instead of Apache  

**Status:** ✅ Ready for production deployment

Your ChemInventory AI application is configured and ready to go live at:

🌐 **https://ohm044.xyz/v1**

---

*Deployment Configuration Package*  
*Created: 19 February 2026*  
*Version: 1.0*
