#!/bin/bash
# ============================================================================
# ChemInventory AI - VPS Deployment Script
# Usage: sudo bash deploy.sh
# Domain: https://ohm044.xyz/v1
# ============================================================================

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
DOMAIN="ohm044.xyz"
APP_DIR="/var/www/html/v1"
WEB_USER="www-data"
WEB_GROUP="www-data"
DB_NAME="chem_inventory_db"
PHP_VERSION="8.1"

# Functions
print_header() {
    echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

# Check if running as root
if [[ $EUID -ne 0 ]]; then
    print_error "This script must be run as root"
    exit 1
fi

# Step 1: Check prerequisites
print_header "Step 1: Checking Prerequisites"

if ! command -v apache2ctl &> /dev/null; then
    print_error "Apache2 is not installed"
    exit 1
fi
print_success "Apache2 found"

if ! command -v php &> /dev/null; then
    print_error "PHP is not installed"
    exit 1
fi
print_success "PHP found: $(php -v | head -n 1)"

if ! command -v mysql &> /dev/null; then
    print_error "MySQL is not installed"
    exit 1
fi
print_success "MySQL found"

if ! command -v composer &> /dev/null; then
    print_warning "Composer not found, installing..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    print_success "Composer installed"
fi

# Step 2: Create directories
print_header "Step 2: Creating Directory Structure"

mkdir -p "$APP_DIR"
mkdir -p "$APP_DIR/assets/uploads"
mkdir -p "$APP_DIR/assets/logs"
mkdir -p "$APP_DIR/logs"

print_success "Directories created"

# Step 3: Set permissions
print_header "Step 3: Setting File Permissions"

chown -R $WEB_USER:$WEB_GROUP "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;
chmod 755 "$APP_DIR/api"
chmod 755 "$APP_DIR/pages"
chmod 775 "$APP_DIR/assets/uploads"
chmod 775 "$APP_DIR/assets/logs"
chmod 775 "$APP_DIR/logs"

print_success "Permissions set correctly"

# Step 4: Enable Apache modules
print_header "Step 4: Enabling Apache Modules"

modules=("rewrite" "headers" "deflate" "expires" "ssl")
for module in "${modules[@]}"; do
    if a2enmod "$module" 2>/dev/null; then
        print_success "Module $module enabled"
    else
        print_warning "Module $module might already be enabled"
    fi
done

# Step 5: Install PHP dependencies
print_header "Step 5: Installing PHP Dependencies"

cd "$APP_DIR"
if [ -f "composer.json" ]; then
    sudo -u $WEB_USER composer install --no-dev --optimize-autoloader
    print_success "Composer dependencies installed"
else
    print_warning "composer.json not found, skipping dependency installation"
fi

# Step 6: Configure Apache virtual host
print_header "Step 6: Configuring Apache Virtual Host"

VHOST_FILE="/etc/apache2/sites-available/$DOMAIN.conf"
if [ ! -f "$VHOST_FILE" ]; then
    print_info "Creating virtual host configuration..."
    
    # Create basic vhost if full config doesn't exist
    cat > "$VHOST_FILE" << EOF
<VirtualHost *:80>
    ServerName $DOMAIN
    ServerAlias www.$DOMAIN
    DocumentRoot /var/www/html
    
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
    
    <Directory /var/www/html/v1>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/$DOMAIN-error.log
    CustomLog \${APACHE_LOG_DIR}/$DOMAIN-access.log combined
</VirtualHost>

<VirtualHost *:443>
    ServerName $DOMAIN
    ServerAlias www.$DOMAIN
    DocumentRoot /var/www/html
    
    SSLEngine on
    
    <Directory /var/www/html/v1>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/$DOMAIN-error.log
    CustomLog \${APACHE_LOG_DIR}/$DOMAIN-access.log combined
</VirtualHost>
EOF
    
    a2ensite "$DOMAIN"
    print_success "Virtual host created and enabled"
else
    print_success "Virtual host already exists"
fi

# Step 7: Configure SSL with Let's Encrypt
print_header "Step 7: Setting Up SSL Certificate"

if ! command -v certbot &> /dev/null; then
    print_warning "Certbot not found, installing..."
    apt-get update -qq
    apt-get install -y certbot python3-certbot-apache
fi

if [ ! -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]; then
    print_info "Requesting SSL certificate from Let's Encrypt..."
    certbot certonly --apache -d "$DOMAIN" -d "www.$DOMAIN" --non-interactive --agree-tos --email admin@$DOMAIN
    print_success "SSL certificate obtained"
else
    print_success "SSL certificate already exists"
fi

# Step 8: Setup database
print_header "Step 8: Verifying Database"

if mysql -u root -e "USE $DB_NAME;" 2>/dev/null; then
    print_success "Database $DB_NAME already exists"
else
    print_info "Creating database..."
    mysql -u root << EOF
CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'chemuser'@'localhost' IDENTIFIED BY 'changeme';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO 'chemuser'@'localhost';
FLUSH PRIVILEGES;
EOF
    print_success "Database created"
fi

# Step 9: Import database schema
print_header "Step 9: Importing Database Schema"

if [ -f "$APP_DIR/sql/database_schema.sql" ]; then
    if mysql -u root "$DB_NAME" -e "SHOW TABLES;" 2>/dev/null | grep -q users; then
        print_warning "Database tables already exist, skipping import"
    else
        print_info "Importing schema..."
        mysql -u root "$DB_NAME" < "$APP_DIR/sql/database_schema.sql"
        print_success "Database schema imported"
    fi
fi

# Step 10: Restart Apache
print_header "Step 10: Restarting Apache"

systemctl reload apache2
print_success "Apache reloaded"

# Summary
print_header "Deployment Summary"

print_success "Deployment completed!"
echo ""
print_info "Application URL: https://$DOMAIN/v1"
print_info "Application Directory: $APP_DIR"
print_info "Database Name: $DB_NAME"
print_info "Web User: $WEB_USER"
echo ""
echo -e "${YELLOW}Next Steps:${NC}"
echo "1. Update .env file with your settings:"
echo "   sudo nano $APP_DIR/.env"
echo ""
echo "2. Test the application:"
echo "   curl -I https://$DOMAIN/v1/"
echo ""
echo "3. Check logs if there are issues:"
echo "   tail -f /var/log/apache2/$DOMAIN-error.log"
echo ""
echo "4. Generate JWT_SECRET:"
echo "   php -r 'echo bin2hex(random_bytes(32));'"
echo ""
echo "5. View deployment config:"
echo "   cat $APP_DIR/VPS_DEPLOYMENT_CONFIG.md"
echo ""

print_success "Deployment script finished!"
