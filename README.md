# ChemInventory AI - Advanced Chemical Inventory Management System

A comprehensive, AI-driven chemical inventory management system with Augmented Reality (AR) capabilities, built with PHP 8.x and MySQL.

## Features

### Core System
- **Multi-level User Management**: 5 roles (Admin, CEO, Lab Manager, User, Visitor) with hierarchical permissions
- **Role-based Dashboards**: Customized analytics and views for each user role
- **Theme Customization**: Dark/Light mode with customizable primary colors

### Chemical Management
- **CAS Number Support**: Full CAS registry integration
- **GHS Classification**: Automatic hazard pictograms and safety statements
- **SDS Management**: Safety Data Sheet storage and retrieval
- **3D Model Support**: Container visualization with .glb/.usdz files

### QR Code & AR
- **QR Code Generation**: Unique codes for every container
- **AR Visualization**: View containers in augmented reality
- **Mobile-friendly AR**: Works on iOS (ARKit) and Android (ARCore)
- **Interactive Overlays**: Fluid levels, hazard labels, and location highlights

### Location Management
- **Hierarchical Structure**: Building > Room > Cabinet > Shelf > Slot
- **Visual Floor Plans**: SVG-based interactive maps
- **Animated Navigation**: Highlight and zoom to locations

### Borrow/Loan System
- **Request Workflow**: Request → Approval → Fulfillment → Return
- **Quantity Tracking**: mL, L, g, kg, mg unit support
- **Overdue Alerts**: Automatic notifications for late returns

### AI Assistant
- **Natural Language Queries**: "Where is HCl?", "Show expiring chemicals"
- **Smart Suggestions**: Proactive recommendations based on usage
- **Usage Predictions**: Forecast chemical consumption
- **Visual Search**: Image-based chemical identification (requires API)

### Safety & Compliance
- **GHS Integration**: Automatic hazard classification
- **Storage Compatibility**: Prevent incompatible chemical storage
- **Expiry Alerts**: Proactive notifications for expiring chemicals
- **Audit Logging**: Complete change tracking

## Requirements

- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache/Nginx web server
- Composer
- SSL certificate (for AR features)

## Installation

### 1. Clone/Download the repository
```bash
cd /var/www/html
git clone <repository-url> cheminventory
cd cheminventory
```

### 2. Install dependencies
```bash
composer install
```

### 3. Configure environment
```bash
cp .env.example .env
# Edit .env with your database credentials and settings
```

### 4. Create database
```bash
mysql -u root -p < sql/database_schema.sql
```

### 5. Set permissions
```bash
chmod -R 755 assets/uploads
chmod -R 755 assets/logs
chown -R www-data:www-data assets/
```

### 6. Configure web server

#### Apache
Enable required modules:
```bash
a2enmod rewrite
a2enmod headers
a2enmod deflate
a2enmod expires
systemctl restart apache2
```

#### Nginx
Add to your server block:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
}
```

### 7. Access the application
Open your browser and navigate to `http://your-domain/cheminventory/`

Default login:
- Username: `admin`
- Password: `password` (change immediately after first login)

## Configuration

### AI Features (Optional)
To enable AI chat and predictions, add your OpenAI API key to `.env`:
```
AI_API_KEY=sk-your-api-key
```

### Visual Search (Optional)
To enable image-based chemical search, add Google Vision API key:
```
VISION_API_KEY=your-vision-api-key
```

### Email Notifications (Optional)
Configure SMTP settings in `.env` for email alerts:
```
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
```

## API Documentation

### Authentication
```http
POST /api/auth.php?action=login
Content-Type: application/json

{
  "username": "your_username",
  "password": "your_password",
  "remember": true
}
```

### Chemicals
```http
GET /api/chemicals.php?page=1&per_page=20&search=ethanol
GET /api/chemicals.php?id=123
POST /api/chemicals.php
PUT /api/chemicals.php?id=123
DELETE /api/chemicals.php?id=123
```

### Containers
```http
GET /api/containers.php?qr=CHEM-123456
GET /api/containers.php?id=456
POST /api/containers.php
```

### Locations
```http
GET /api/locations.php?hierarchy=1&lab_id=1
GET /api/locations.php?type=cabinets&parent_id=5
```

### Borrow Requests
```http
GET /api/borrow.php?status=pending
POST /api/borrow.php
{
  "action": "create",
  "chemical_id": 123,
  "requested_quantity": 100,
  "quantity_unit": "mL",
  "purpose": "Experiment XYZ"
}
```

### AI Assistant
```http
POST /api/ai_assistant.php
{
  "action": "chat",
  "message": "Where is HCl?"
}

GET /api/ai_assistant.php?suggest=1
```

## AR Usage

### Viewing AR
1. Scan a container's QR code
2. Click "AR View" button
3. On mobile: Tap AR button to enter immersive mode
4. On desktop: Use mouse to rotate and zoom the 3D model

### Supported Devices
- iOS 12+ with Safari (AR Quick Look)
- Android 8.0+ with ARCore support
- Modern desktop browsers (WebGL fallback)

## File Structure

```
chem_inventory/
├── api/                    # API endpoints
│   ├── auth.php
│   ├── chemicals.php
│   ├── containers.php
│   ├── locations.php
│   ├── borrow.php
│   ├── dashboard.php
│   └── ai_assistant.php
├── ar/                     # AR views
│   └── view_ar.php
├── assets/                 # Static assets
│   ├── css/
│   ├── js/
│   ├── images/
│   └── uploads/
├── includes/               # Core PHP classes
│   ├── config.php
│   ├── database.php
│   ├── auth.php
│   └── qr_generator.php
├── pages/                  # Frontend pages
│   ├── login.php
│   ├── dashboard.php
│   ├── chemicals.php
│   ├── containers.php
│   ├── locations.php
│   ├── borrow.php
│   ├── qr-scanner.php
│   └── ai-assistant.php
├── sql/                    # Database schema
│   └── database_schema.sql
├── composer.json
├── .env.example
├── .htaccess
└── README.md
```

## Security Considerations

1. **Change default passwords** immediately after installation
2. **Use HTTPS** in production for AR features
3. **Regular backups** of the database
4. **Keep dependencies updated** with `composer update`
5. **Set proper file permissions** on upload directories
6. **Use strong JWT secret** in production

## Troubleshooting

### QR Scanner not working
- Ensure camera permissions are granted
- Use HTTPS in production (required for camera access)
- Check browser console for errors

### AR not displaying
- Verify WebGL is enabled in browser
- Check that 3D model files are accessible
- Use supported browsers (Chrome, Safari, Firefox)

### Database connection errors
- Verify credentials in `.env`
- Check MySQL is running
- Ensure database exists: `SHOW DATABASES;`

## License

MIT License - See LICENSE file for details

## Support

For support and feature requests, please contact:
- Email: support@cheminventory.local
- Documentation: https://docs.cheminventory.local

## Credits

Built with:
- [Tailwind CSS](https://tailwindcss.com)
- [Chart.js](https://chartjs.org)
- [AR.js](https://ar-js-org.github.io/AR.js-Docs/)
- [Model Viewer](https://modelviewer.dev)
- [html5-qrcode](https://github.com/mebjas/html5-qrcode)
