# Document Control System (DCS)

A comprehensive document management system built with Laravel 12 and Filament 3.3, designed to streamline document workflow from creation to publication with QR code validation.

## 🚀 Features

### 📋 Core Features
- **Document Workflow Management**: Draft → Submit → Review → Approve → Publish
- **QR Code Generation & Validation**: Automatic QR code generation for published documents
- **Role-Based Access Control**: SuperAdmin, Admin, and User roles with granular permissions
- **Public Document Portal**: Accessible public interface for published documents
- **Real-time Notifications**: Email and in-app notifications for workflow changes
- **Comprehensive Audit Trail**: Complete history of document changes and approvals

### 🔐 Security Features
- **Authentication & Authorization**: Laravel Breeze with role-based access
- **Document Access Control**: Confidential documents restricted by department
- **File Security**: Private storage with controlled access
- **Audit Logging**: Complete tracking of user actions and document access

### 📊 Management Features
- **Document Statistics**: View counts, download tracking, and analytics
- **Advanced Search & Filtering**: Full-text search with multiple filters
- **Document Versioning**: Track document revisions and changes
- **Automated Workflows**: Email notifications and approval routing
- **File Management**: Support for PDF, DOC, DOCX formats

### 🏢 Organizational Structure
- **Departments & Sections**: Hierarchical organization structure
- **Document Numbering**: Automatic numbering with format: `{COMPANY}-{DEPT}-{SECTION}-{YEAR}-{MONTH}-{NUMBER}`
- **Department-based Access**: Admins can only manage documents in their department

## 🛠️ Technology Stack

- **Backend**: Laravel 12.x
- **Admin Panel**: Filament 3.3
- **Frontend**: Blade Templates + Livewire + Alpine.js
- **Database**: MySQL 8.0
- **File Storage**: Laravel Storage (local/cloud)
- **Queue System**: Database driver with job processing
- **PDF Viewer**: PDF.js integration
- **QR Codes**: Simple QrCode Laravel package
- **Authentication**: Laravel Breeze
- **Permissions**: Spatie Laravel Permission

## 📋 Requirements

- PHP 8.2 or higher
- Composer 2.x
- Node.js 18+ and NPM
- MySQL 8.0 or MariaDB 10.3+
- Apache/Nginx web server
- GD/ImageMagick extension for image processing

## ⚡ Quick Start

### 1. Clone and Install

```bash
# Clone the project
git clone <repository-url> document-control-system
cd document-control-system

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 2. Database Setup

```bash
# Create database
mysql -u root -p
CREATE DATABASE document_control_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit

# Configure database in .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=document_control_system
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 3. Application Configuration

Update your `.env` file with these essential settings:

```env
# Application Settings
APP_NAME="Document Control System"
APP_URL=http://localhost:8000
COMPANY_CODE=AKM
COMPANY_NAME="PT. Aneka Karya Mandiri"

# Queue Configuration
QUEUE_CONNECTION=database

# Mail Configuration (for notifications)
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@akm.com"
MAIL_FROM_NAME="${APP_NAME}"

# Document Settings
MAX_DOCUMENT_SIZE=10240
REVIEW_DEADLINE_DAYS=3
APPROVAL_DEADLINE_DAYS=7
```

### 4. Install and Setup

```bash
# Run migrations and seed data
php artisan migrate --seed

# Create storage directories and links
php artisan storage:link

# Install Filament
php artisan filament:install --panels

# Build frontend assets
npm run build

# Create queue tables
php artisan queue:table
php artisan migrate
```

### 5. Start Development Server

```bash
# Start Laravel development server
php artisan serve

# Start queue worker (in separate terminal)
php artisan queue:work

# Optional: Start file watcher for development
npm run dev
```

## 🔐 Default Login Credentials

After running the seeder, you can login with these accounts:

### Super Administrator
- **Email**: `admin@akm.com`
- **Password**: `password123`
- **Access**: Full system access, final approval authority

### IT Administrator
- **Email**: `it.admin@akm.com`
- **Password**: `password123`
- **Access**: IT department document management

### HR Administrator
- **Email**: `hr.admin@akm.com`
- **Password**: `password123`
- **Access**: HR department document management

### Regular Users
- **Email**: `john.doe@akm.com` (and others)
- **Password**: `password123`
- **Access**: Document creation and viewing

## 🌐 Application URLs

- **Public Portal**: http://localhost:8000
- **Admin Panel**: http://localhost:8000/admin
- **User Login**: http://localhost:8000/login
- **User Dashboard**: http://localhost:8000/dashboard

## 📖 User Guide

### For Regular Users

1. **Creating Documents**:
   - Login and navigate to Dashboard
   - Click "Create Document" in Filament admin panel
   - Fill in document details and upload file
   - Submit for review when ready

2. **Document Workflow**:
   - **Draft**: Edit and prepare your document
   - **Submit**: Send to admin for review
   - **Revision**: Make requested changes if needed
   - **Published**: Document is live and accessible

### For Administrators

1. **Review Process**:
   - Access "Pending Reviews" from admin panel
   - Review submitted documents
   - Request revisions or verify documents
   - Add comments for creators

2. **Department Management**:
   - Manage users in your department
   - Monitor document statistics
   - Configure department settings

### For Super Administrators

1. **System Management**:
   - Manage all users and departments
   - Final approval for documents
   - System configuration and settings
   - Analytics and reporting

2. **Document Publishing**:
   - Approve verified documents
   - Publish approved documents
   - Generate and manage QR codes

## 🔧 Advanced Configuration

### Queue Workers

For production, set up dedicated queue workers:

```bash
# High priority (notifications)
php artisan queue:work --queue=high --timeout=60 --tries=3

# Normal priority (general tasks)
php artisan queue:work --queue=normal --timeout=90 --tries=3

# Email queue
php artisan queue:work --queue=emails --timeout=60 --tries=5

# Maintenance tasks
php artisan queue:work --queue=maintenance --timeout=600 --tries=2
```

### Scheduled Tasks

Add to your server's crontab:

```bash
# Laravel scheduler
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1

# Daily cleanup at 2 AM
0 2 * * * cd /path/to/project && php artisan queue:dispatch "App\\Jobs\\CleanupExpiredDocumentsJob"
```

### File Storage

Configure cloud storage for production:

```env
# AWS S3
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket

# Or use local storage
FILESYSTEM_DISK=local
```

## 🔒 Security Considerations

### Production Security

1. **Environment Security**:
   ```bash
   # Secure file permissions
   chmod 644 .env
   chmod -R 755 storage bootstrap/cache
   
   # Clear caches
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

2. **Database Security**:
   - Use strong database passwords
   - Limit database user permissions
   - Enable SSL connections

3. **File Security**:
   - Store documents outside web root
   - Implement virus scanning for uploads
   - Use HTTPS for all connections

### Backup Strategy

```bash
# Database backup
mysqldump -u username -p document_control_system > backup_$(date +%Y%m%d).sql

# File backup
tar -czf documents_backup_$(date +%Y%m%d).tar.gz storage/app/documents/

# Automated backup script
php artisan backup:run
```

## 🐛 Troubleshooting

### Common Issues

1. **Storage Permission Errors**:
   ```bash
   sudo chown -R www-data:www-data storage bootstrap/cache
   chmod -R 775 storage bootstrap/cache
   ```

2. **Queue Not Processing**:
   ```bash
   # Check queue status
   php artisan queue:failed
   
   # Restart workers
   php artisan queue:restart
   
   # Clear failed jobs
   php artisan queue:flush
   ```

3. **File Upload Issues**:
   ```bash
   # Check PHP upload limits
   php -i | grep -E 'upload_max_filesize|post_max_size|max_execution_time'
   
   # Increase limits in php.ini
   upload_max_filesize = 10M
   post_max_size = 10M
   max_execution_time = 300
   ```

4. **QR Code Generation Issues**:
   ```bash
   # Install required extensions
   sudo apt-get install php-gd php-imagick
   
   # Clear config cache
   php artisan config:clear
   ```

### Debug Mode

For development, enable debug mode:

```env
APP_DEBUG=true
LOG_LEVEL=debug
```

## 📊 Monitoring & Analytics

### Built-in Analytics

- Document view and download statistics
- User activity tracking
- Department performance metrics
- Workflow efficiency reports

### Log Files

Monitor these log files:

- `storage/logs/laravel.log` - Application logs
- `storage/logs/queue.log` - Queue processing logs
- `storage/logs/access.log` - Document access logs

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/new-feature`
3. Commit changes: `git commit -am 'Add new feature'`
4. Push to branch: `git push origin feature/new-feature`
5. Submit a Pull Request

### Development Standards

- Follow PSR-12 coding standards
- Write tests for new features
- Update documentation
- Use meaningful commit messages

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

For support and questions:

- **Email**: admin@akm.com
- **Documentation**: Check this README and inline code comments
- **Issues**: Create an issue in the repository

## 🎯 Future Enhancements

### Planned Features

- [ ] Advanced search with Elasticsearch
- [ ] Document templates and forms
- [ ] Digital signatures integration
- [ ] Mobile application
- [ ] API documentation with Swagger
- [ ] Advanced reporting dashboard
- [ ] Multi-language support
- [ ] Integration with external systems

### Performance Optimizations

- [ ] Redis caching implementation
- [ ] CDN integration for file delivery
- [ ] Database query optimization
- [ ] Lazy loading improvements
- [ ] Image optimization for QR codes

---

**Built with ❤️ using Laravel 12 and Filament 3.3**

*Last updated: August 2025*