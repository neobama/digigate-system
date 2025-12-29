# DigiGate System

**Enterprise Resource Planning (ERP) System** untuk PT. Gerbang Digital Indonesia

Sistem manajemen terintegrasi yang mencakup manajemen invoice, inventory, assembly, human resources, financial tracking, dan document management dengan dukungan cloud storage (S3).

---

## 📋 Table of Contents

- [Overview](#-overview)
- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [S3 Storage Setup](#-s3-storage-setup)
- [Usage](#-usage)
- [Deployment](#-deployment)
- [Project Structure](#-project-structure)
- [Security](#-security)
- [Troubleshooting](#-troubleshooting)
- [Contributing](#-contributing)
- [License](#-license)

---

## 🎯 Overview

DigiGate System adalah sistem ERP berbasis web yang dirancang khusus untuk mengelola operasional PT. Gerbang Digital Indonesia. Sistem ini menyediakan dua panel terpisah (Admin dan Employee) dengan fitur-fitur lengkap untuk manajemen bisnis, mulai dari invoice, inventory, assembly, HR, hingga financial tracking.

### Key Highlights

- ✅ **Dual Panel System**: Admin Panel dan Employee Panel dengan akses terpisah
- ✅ **Cloud Storage Integration**: Wajib menggunakan S3-compatible storage untuk semua file
- ✅ **Real-time Dashboard**: Statistik dan ringkasan data real-time
- ✅ **Financial Management**: Tracking pemasukan dan pengeluaran dengan export laporan akuntansi
- ✅ **Document Management**: Centralized document storage dengan preview dan download
- ✅ **AI-Powered Features**: Experimental AI untuk auto-parse invoice (Google Gemini)
- ✅ **UUID-based**: Semua primary keys menggunakan UUID untuk keamanan dan skalabilitas

---

## ✨ Features

### 🎛️ Admin Panel

#### Operational Management
- **Invoice Management**
  - Create, edit, dan kelola invoice (Proforma, Paid, Delivered, Cancelled)
  - View invoice documents (uploaded via Document Management)
  - Quick upload dokumen invoice
  - Export invoice ke Excel (bulanan)
  - Auto-calculate total dari items, discount, dan shipping cost
  
- **Assembly Management**
  - Tracking rakitan produk dengan auto-generate serial number (format: `DG(YYYY)(MM)(XXX)`)
  - View detail assembly dengan SN komponen lengkap
  - Link assembly ke invoice
  - Auto-update status komponen menjadi "used" saat assembly dibuat

- **Component Management**
  - Tracking stock komponen (Available, Used, Warranty Claim)
  - Breakdown stock per komponen di dashboard
  - Bulk add komponen dengan Experimental AI (auto-parse dari invoice)

#### Human Resources
- **Employee Management**
  - Data karyawan lengkap (NIK, nama, tanggal lahir, gaji, posisi, BPJS)
  - Manage login credentials (email & password) untuk karyawan
  - Generate slip gaji otomatis dengan potongan cashbon & BPJS
  
- **Logbook Management**
  - Review aktivitas harian karyawan
  - View multiple foto bukti kerja per entry
  - Filter berdasarkan tanggal dan karyawan
  
- **Cashbon Management**
  - Approve/reject request cashbon karyawan
  - Track status cashbon (Pending, Approved, Rejected, Paid)
  - Auto-deduct dari slip gaji bulanan
  
- **Reimbursement Management**
  - Approve/reject reimbursement request
  - View proof of payment
  - Track status (Pending, Approved, Rejected, Paid)

#### Financial Management
- **Income Tracking**
  - Manual income input
  - Auto-track dari invoice paid
  - Real-time dashboard widget
  
- **Expense Tracking**
  - Manual expense input (dengan vendor invoice number, account code, fund source)
  - Auto-track dari reimbursement paid dan cashbon paid
  - Real-time dashboard widget
  
- **Financial Reports**
  - Monthly financial overview (Income, Expense, Profit/Loss)
  - Export laporan keuangan ke Excel (format akuntansi: Debit/Kredit/Saldo)
  - Breakdown per kategori (Invoice, Manual, Reimbursement, Cashbon)

#### Document Management
- **Centralized Document Storage**
  - Upload dokumen ke S3 (PDF, Word, Excel, Images, ZIP, RAR)
  - Kategori dokumen (Invoice, Contract, Certificate, License, Legal, Financial, HR, Technical)
  - Preview dokumen langsung di browser (PDF, Images)
  - Download dokumen
  - Link dokumen ke invoice
  - Access control (Public, Private, Restricted)

#### Settings & Utilities
- **Backup Data**
  - Export semua data ke Excel (multi-sheet)
  - Backup: Invoices, Assemblies, Employees, Logbooks, Cashbons, Components
  
- **Dashboard Analytics**
  - Statistik pendapatan bulanan
  - Financial overview (Income, Expense, Profit/Loss)
  - Stock summary breakdown per komponen
  - Recent documents widget

### 👤 Employee Panel

- **My Logbook**
  - Input aktivitas harian dengan deskripsi
  - Upload multiple foto bukti kerja (disimpan ke S3)
  - View history logbook
  
- **My Cashbon**
  - Request cashbon dengan alasan
  - Track status request
  - View history cashbon
  
- **My Reimbursement**
  - Request reimbursement dengan proof of payment
  - Experimental AI: Auto-fill dari invoice image (Google Gemini)
  - Track status request
  
- **My Assembly**
  - Buat assembly baru dengan auto-generate serial number
  - View daftar assembly yang sudah dibuat
  - View detail assembly dengan SN komponen
  
- **My Component**
  - View stock komponen tersedia
  - Bulk add komponen dengan Experimental AI (auto-parse invoice)
  - Manual add komponen

### 🤖 Experimental AI Features

- **Reimbursement Auto-Fill**
  - Upload invoice/bon image
  - AI auto-parse: Purpose, Expense Date, Amount, Description
  - User dapat review dan edit sebelum submit
  
- **Component Bulk Add**
  - Upload invoice image
  - AI auto-detect multiple items (Name, Supplier, Purchase Date)
  - User hanya perlu input Serial Number (SN)
  - Auto-map component name ke dropdown options

---

## 🛠 Tech Stack

### Backend
- **Framework**: Laravel 12
- **PHP**: 8.4+
- **Database**: MySQL 8.0+ / SQLite 3
- **Storage**: AWS S3-compatible (Wajib untuk Production)

### Frontend & Admin Panel
- **Admin Framework**: Filament 3.2
- **UI Components**: Livewire 3.x
- **Icons**: Heroicons

### Third-Party Services
- **PDF Generation**: DomPDF
- **Excel Export**: Laravel Excel (Maatwebsite)
- **AI Service**: Google Gemini API (Experimental)
- **Cloud Storage**: AWS S3 / S3-compatible storage

### Development Tools
- **Package Manager**: Composer
- **Asset Bundler**: Vite
- **Version Control**: Git

---

## 📦 Requirements

### Server Requirements
- **PHP**: >= 8.4 dengan extensions:
  - `mysql` / `pdo_mysql`
  - `sqlite3` / `pdo_sqlite`
  - `xml`
  - `mbstring`
  - `curl`
  - `zip`
  - `bcmath`
  - `gd` / `imagick` (untuk image processing)
  - `fileinfo` (untuk MIME type detection)
- **Composer**: >= 2.0
- **Node.js**: >= 18.x (untuk build assets)
- **Database**: MySQL 8.0+ atau SQLite 3
- **Web Server**: Nginx atau Apache dengan PHP-FPM

### Storage Requirements
- **S3-compatible Storage** (Wajib untuk Production)
  - AWS S3, DigitalOcean Spaces, Cloudflare R2, atau S3-compatible storage lainnya
  - Bucket dengan public read access untuk file uploads
  - CORS configuration untuk browser uploads

### Development Requirements
- **Git**: Untuk version control
- **Text Editor/IDE**: VS Code, PhpStorm, atau editor lainnya

---

## 🚀 Installation

### 1. Clone Repository

```bash
git clone https://github.com/neobama/digigate-system.git
cd digigate-system
```

### 2. Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies (jika ada frontend assets)
npm install
```

### 3. Environment Setup

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4. Configure Database

Edit `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=digigate_db
DB_USERNAME=root
DB_PASSWORD=your_password
```

Atau untuk SQLite (development):

```env
DB_CONNECTION=sqlite
# Database file akan dibuat otomatis di database/database.sqlite
```

### 5. Configure S3 Storage (Wajib)

Edit `.env` file dan tambahkan konfigurasi S3:

```env
# Filesystem - WAJIB set ke 's3' untuk production
FILESYSTEM_DISK=s3

# AWS S3 Configuration
AWS_ACCESS_KEY_ID=your_access_key_id
AWS_SECRET_ACCESS_KEY=your_secret_access_key
AWS_DEFAULT_REGION=jkt01
AWS_BUCKET=s3-digigate
AWS_ENDPOINT=https://is3.cloudhost.id
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_URL=
```

**Catatan**: Untuk development lokal, bisa menggunakan `FILESYSTEM_DISK=local`, tapi **production wajib menggunakan S3**.

### 6. Run Migrations

```bash
# Run migrations dan seed default admin user
php artisan migrate --seed
```

Default admin credentials:
- **Email**: `admin@digigate.id`
- **Password**: `password`

**⚠️ PENTING**: Ganti password default setelah first login!

### 7. Create Storage Link

```bash
php artisan storage:link
```

### 8. Build Assets

```bash
npm run build
```

### 9. Start Development Server

```bash
php artisan serve
```

### 10. Access Application

- **Admin Panel**: http://localhost:8000
- **Employee Panel**: http://localhost:8000/employee
- **Default Login**: `admin@digigate.id` / `password`

---

## ⚙️ Configuration

### Environment Variables

File `.env` berisi semua konfigurasi penting:

#### Application Configuration
```env
APP_NAME="DigiGate System"
APP_ENV=production
APP_KEY=base64:...  # Generate dengan: php artisan key:generate
APP_DEBUG=false
APP_URL=https://erp.digigate.id
```

#### Database Configuration
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=digigate_db
DB_USERNAME=root
DB_PASSWORD=secure_password
```

#### S3 Storage Configuration (Wajib)
```env
FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=jkt01
AWS_BUCKET=s3-digigate
AWS_ENDPOINT=https://is3.cloudhost.id
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_URL=
```

#### Experimental AI Configuration (Optional)
```env
GEMINI_API_KEY=your_gemini_api_key
GEMINI_MODEL=gemini-3-pro
GEMINI_TIMEOUT=30
```

### File Storage Structure

Dengan S3, semua file disimpan di bucket dengan struktur:

```
s3-digigate/
├── assets/                    # Logo, favicon (upload manual)
│   ├── digigate-logo.png
│   ├── digigate-dark.png
│   └── favicon.png
├── documents/                 # Document management files
│   ├── invoice-2025-12-25.pdf
│   └── contract-abc-123.pdf
├── logbooks-photos/           # Employee logbook photos
│   ├── 2025/12/photo1.jpg
│   └── 2025/12/photo2.jpg
├── reimbursements/            # Reimbursement proof of payment
│   └── reimbursement-123.jpg
└── expenses/                  # Manual expense proof
    └── expense-456.jpg
```

---

## ☁️ S3 Storage Setup

### Prerequisites

1. **S3 Bucket** sudah dibuat
2. **Access Key** dan **Secret Key** sudah didapatkan
3. **CORS Configuration** sudah di-set (untuk browser uploads)

### Step-by-Step Setup

#### 1. Install S3 Package

```bash
composer require league/flysystem-aws-s3-v3
```

#### 2. Configure `.env`

```env
FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=your_access_key_id
AWS_SECRET_ACCESS_KEY=your_secret_access_key
AWS_DEFAULT_REGION=jkt01
AWS_BUCKET=s3-digigate
AWS_ENDPOINT=https://is3.cloudhost.id
AWS_USE_PATH_STYLE_ENDPOINT=true
```

#### 3. Configure CORS

Upload file `CORS_CONFIGURATION.json` ke bucket S3 atau set via S3 dashboard:

```json
[
    {
        "AllowedHeaders": ["*"],
        "AllowedMethods": ["GET", "PUT", "POST", "DELETE", "HEAD", "OPTIONS"],
        "AllowedOrigins": [
            "http://127.0.0.1:8000",
            "http://localhost:8000",
            "https://erp.digigate.id"
        ],
        "ExposeHeaders": [
            "ETag",
            "x-amz-server-side-encryption",
            "x-amz-request-id",
            "x-amz-id-2",
            "x-amz-version-id",
            "Content-Length",
            "Content-Type",
            "x-amz-acl"
        ],
        "MaxAgeSeconds": 3000
    }
]
```

#### 4. Upload Assets

Upload logo dan favicon ke S3 bucket:

```bash
# Upload via S3 client atau web interface
# Path: assets/digigate-logo.png
# Path: assets/digigate-dark.png
# Path: assets/favicon.png
```

#### 5. Clear Config Cache

```bash
php artisan config:clear
php artisan config:cache
```

### Testing S3 Connection

```bash
php artisan tinker
```

```php
// Test S3 connection
Storage::disk('s3_public')->put('test.txt', 'Hello S3');
Storage::disk('s3_public')->exists('test.txt'); // Should return true
Storage::disk('s3_public')->url('test.txt'); // Get public URL
```

### Troubleshooting S3

Lihat dokumentasi lengkap di:
- [S3_SETUP.md](S3_SETUP.md)
- [CORS_SETUP_GUIDE.md](CORS_SETUP_GUIDE.md)
- [TROUBLESHOOTING_S3.md](TROUBLESHOOTING_S3.md)

---

## 📖 Usage

### Admin Panel

#### Dashboard
- **Financial Overview**: Total pemasukan, pengeluaran, dan laba/rugi bulan ini
- **Invoice Stats**: Statistik invoice per status
- **Stock Summary**: Breakdown stock komponen yang tersedia
- **Recent Documents**: 5 dokumen terbaru

#### Navigation Menu

**Operational**
- **Invoices**: Kelola invoice, view dokumen, export Excel
- **Assemblies**: Tracking rakitan, view detail dengan SN komponen
- **Components**: Manajemen stock komponen

**HR**
- **Employees**: Data karyawan, manage login, generate slip gaji
- **Logbooks**: Review aktivitas karyawan dengan foto
- **Cashbons**: Approve/reject cashbon request
- **Reimbursements**: Approve/reject reimbursement dengan proof

**Financial**
- **Pemasukan**: Input manual income
- **Pengeluaran**: Input manual expense
- **Laporan Keuangan**: Export laporan bulanan (format akuntansi)

**Settings**
- **Dokumen**: Document management (upload, preview, download)
- **Backup Data**: Export semua data ke Excel

### Employee Panel

1. Login di `/employee` dengan kredensial karyawan
2. Fitur yang tersedia:
   - **My Logbook**: Input aktivitas + upload foto (multiple)
   - **My Cashbon**: Request cashbon
   - **My Reimbursement**: Request reimbursement (dengan Experimental AI)
   - **My Assembly**: Buat dan view assembly
   - **My Component**: View stock dan bulk add komponen (dengan Experimental AI)

---

## 🚢 Deployment

### Production Deployment (Ubuntu 24)

Lihat panduan lengkap di [DEPLOYMENT.md](DEPLOYMENT.md)

#### Quick Start

```bash
# 1. Clone repository
git clone https://github.com/neobama/digigate-system.git /var/www/digigate-system

# 2. Setup .env
cd /var/www/digigate-system
cp .env.example .env
nano .env  # Edit sesuai production

# 3. Install dependencies
composer install --no-dev --optimize-autoloader

# 4. Generate key dan migrate
php artisan key:generate
php artisan migrate --seed

# 5. Set permissions
sudo chown -R www-data:www-data /var/www/digigate-system
sudo chmod -R 755 /var/www/digigate-system
sudo chmod -R 775 storage bootstrap/cache

# 6. Create storage link
php artisan storage:link

# 7. Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Configure Nginx (lihat DEPLOYMENT.md)
```

#### Auto Update Script

Gunakan script `update.sh` untuk update aplikasi:

```bash
cd /var/www/digigate-system
chmod +x update.sh
./update.sh
```

Script ini akan:
- ✅ Pull latest code dari GitHub
- ✅ Install dependencies
- ✅ Run migrations (safe, tidak menghapus data)
- ✅ Clear dan rebuild cache
- ✅ Set permissions
- ✅ Restart services

---

## 📁 Project Structure

```
digigate-system/
├── app/
│   ├── Exports/                  # Excel export classes
│   │   ├── BackupAllDataExport.php
│   │   └── FinancialReportExport.php
│   ├── Filament/
│   │   ├── Employee/             # Employee panel pages
│   │   │   ├── Pages/
│   │   │   └── Widgets/
│   │   ├── Pages/                 # Custom admin pages
│   │   │   ├── Dashboard.php
│   │   │   ├── FinancialReport.php
│   │   │   └── BackupData.php
│   │   ├── Resources/            # Filament resources (CRUD)
│   │   │   ├── InvoiceResource.php
│   │   │   ├── AssemblyResource.php
│   │   │   ├── ComponentResource.php
│   │   │   ├── EmployeeResource.php
│   │   │   ├── DocumentResource.php
│   │   │   ├── ExpenseResource.php
│   │   │   └── IncomeResource.php
│   │   └── Widgets/              # Dashboard widgets
│   │       ├── InvoiceStatsWidget.php
│   │       ├── FinancialOverviewWidget.php
│   │       └── StockSummaryWidget.php
│   ├── Http/
│   │   ├── Controllers/           # Controllers (PDF, Salary Slip)
│   │   └── Middleware/            # Custom middleware
│   ├── Models/                    # Eloquent models (UUID-based)
│   ├── Providers/                  # Service providers
│   └── Services/                  # Business logic services
│       └── GeminiService.php      # AI service
├── config/
│   ├── filesystems.php            # S3 configuration
│   └── gemini.php                 # AI configuration
├── database/
│   ├── migrations/                # Database migrations
│   └── seeders/                   # Database seeders
├── resources/
│   └── views/
│       ├── filament/              # Filament views
│       ├── invoices/              # PDF invoice templates
│       └── salary-slips/          # Salary slip templates
├── routes/
│   └── web.php                    # Web routes
├── update.sh                      # Auto-update script
└── README.md                      # This file
```

---

## 🔐 Security

### Best Practices

1. **Environment Variables**
   - ❌ Jangan commit `.env` ke Git
   - ✅ Gunakan `.env.example` sebagai template
   - ✅ Rotate credentials secara berkala

2. **S3 Credentials**
   - ❌ Jangan hardcode credentials di code
   - ✅ Gunakan environment variables
   - ✅ Gunakan IAM roles jika memungkinkan (AWS)

3. **Database**
   - ✅ Gunakan password yang kuat
   - ✅ Limit database user permissions
   - ✅ Regular backup database

4. **Application**
   - ✅ Gunakan HTTPS di production
   - ✅ Set `APP_DEBUG=false` di production
   - ✅ Update dependencies secara berkala
   - ✅ Gunakan UUID untuk primary keys (security through obscurity)

5. **File Permissions**
   - ✅ Set correct ownership (`www-data:www-data`)
   - ✅ Set correct permissions (755 untuk files, 775 untuk storage)

---

## 🐛 Troubleshooting

### Common Issues

#### S3 Upload Issues
- **CORS Error**: Pastikan CORS configuration sudah di-set di bucket
- **Permission Denied**: Check bucket policy dan IAM permissions
- **File Not Found**: Pastikan `AWS_ENDPOINT` dan `AWS_BUCKET` benar

#### Database Issues
- **Migration Failed**: Check migration status dengan `php artisan migrate:status`
- **UUID Error**: Pastikan semua migrations sudah di-run

#### Application Errors
- **500 Error**: Check `storage/logs/laravel.log`
- **Config Cache**: Clear dengan `php artisan config:clear`
- **Permission Error**: Set ownership dan permissions untuk `storage` dan `bootstrap/cache`

Lihat dokumentasi lengkap:
- [DEPLOYMENT.md](DEPLOYMENT.md) - Deployment guide
- [TROUBLESHOOTING_S3.md](TROUBLESHOOTING_S3.md) - S3 troubleshooting
- [FIX_HTTPS.md](FIX_HTTPS.md) - HTTPS configuration

---

## 📝 Notes

### Database
- Semua primary keys menggunakan **UUID** (bukan auto-increment integer)
- Format: `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`
- Foreign keys juga menggunakan UUID

### Serial Numbers
- **Assembly Serial Number**: Format `DG(YYYY)(MM)(XXX)`
  - Contoh: `DG202512001` (Assembly ke-1 di Desember 2025)
  - Auto-increment per bulan

### File Storage
- **Wajib menggunakan S3** untuk production
- Semua file uploads (logbook photos, reimbursement proof, documents) disimpan di S3
- Local storage hanya untuk development

### Experimental AI
- Fitur AI menggunakan Google Gemini API
- **Optional**: Bisa digunakan atau tidak
- Jika tidak digunakan, fitur tetap berfungsi secara manual
- API key disimpan di `.env` (tidak di-commit ke Git)

---

## 🤝 Contributing

1. Fork repository
2. Buat branch untuk fitur (`git checkout -b feature/AmazingFeature`)
3. Commit perubahan (`git commit -m 'Add: AmazingFeature'`)
4. Push ke branch (`git push origin feature/AmazingFeature`)
5. Buat Pull Request

### Commit Message Convention

- `Add:` - Menambah fitur baru
- `Fix:` - Perbaikan bug
- `Update:` - Update fitur yang sudah ada
- `Refactor:` - Refactoring code
- `Docs:` - Update dokumentasi

---

## 📄 License

This project is proprietary software for **PT. Gerbang Digital Indonesia**.

---

## 📞 Support & Documentation

- **Repository**: https://github.com/neobama/digigate-system
- **Issues**: https://github.com/neobama/digigate-system/issues
- **Deployment Guide**: [DEPLOYMENT.md](DEPLOYMENT.md)
- **S3 Setup**: [S3_SETUP.md](S3_SETUP.md)
- **Experimental AI**: [EXPERIMENTAL_AI_SETUP.md](EXPERIMENTAL_AI_SETUP.md)

---

**Made with ❤️ for PT. Gerbang Digital Indonesia**

*Last Updated: December 2025*
