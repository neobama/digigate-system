# DigiGate System

Sistem manajemen untuk PT. Gerbang Digital Indonesia yang mencakup manajemen invoice, assembly, komponen, karyawan, logbook, dan cashbon.

## 📋 Table of Contents

- [Fitur](#-fitur)
- [Tech Stack](#-tech-stack)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Usage](#-usage)
- [Deployment](#-deployment)
- [Git Workflow](#-git-workflow)
- [Contributing](#-contributing)
- [License](#-license)

## ✨ Fitur

### Admin Panel
- **Manajemen Invoice**: Buat, edit, dan kelola invoice (Proforma, Paid, Delivered)
- **Generate PDF**: Export invoice ke PDF (Proforma & Paid Invoice)
- **Manajemen Assembly**: Tracking rakitan produk dengan serial number
- **Manajemen Komponen**: Tracking stock komponen (Available, Used, Warranty)
- **Manajemen Karyawan**: Data karyawan dengan NIK, gaji, posisi, BPJS
- **Slip Gaji**: Generate slip gaji otomatis dengan potongan cashbon & BPJS
- **Logbook**: Review aktivitas harian karyawan
- **Cashbon Management**: Approve/reject request cashbon karyawan
- **Dashboard**: Statistik pendapatan bulanan dan ringkasan stock
- **Backup Data**: Export semua data ke Excel (multi-sheet)

### Employee Panel
- **Logbook**: Input aktivitas harian dengan upload foto (multiple)
- **Cashbon Request**: Request cashbon dengan alasan
- **Assembly**: Buat assembly baru dengan auto-generate serial number
- **View Assemblies**: Lihat daftar assembly yang sudah dibuat

## 🛠 Tech Stack

- **Framework**: Laravel 12
- **PHP**: 8.2+
- **Admin Panel**: Filament 3.2
- **PDF Generation**: DomPDF
- **Excel Export**: Laravel Excel (Maatwebsite)
- **Database**: MySQL / SQLite
- **Containerization**: Docker & Docker Compose

## 📦 Requirements

- PHP >= 8.2
- Composer
- Node.js & NPM (untuk assets)
- MySQL 8.0+ atau SQLite
- Docker & Docker Compose (untuk deployment)

## 🚀 Installation

### Local Development (tanpa Docker)

1. **Clone repository**
   ```bash
   git clone https://github.com/neorafaz/digigate-system.git
   cd digigate-system
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Setup environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure database di `.env`**
   ```env
   DB_CONNECTION=sqlite
   # atau
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=digigate_db
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. **Run migrations**
   ```bash
   php artisan migrate --seed
   ```

6. **Create storage link**
   ```bash
   php artisan storage:link
   ```

7. **Build assets**
   ```bash
   npm run build
   ```

8. **Start server**
   ```bash
   php artisan serve
   ```

9. **Access aplikasi**
   - Admin Panel: http://localhost:8000
   - Employee Panel: http://localhost:8000/employee
   - Default Login: `admin@digigate.id` / `password`

### Docker Development

Lihat dokumentasi lengkap di [README.DOCKER.md](README.DOCKER.md)

```bash
# Build dan start containers
docker-compose up -d --build

# Run migrations
docker-compose exec app php artisan migrate --seed

# Create storage link
docker-compose exec app php artisan storage:link
```

## ⚙️ Configuration

### Environment Variables

File `.env` berisi konfigurasi penting:

```env
APP_NAME="DigiGate System"
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=digigate_db
DB_USERNAME=root
DB_PASSWORD=

FILESYSTEM_DISK=local
```

### File Storage

- **Logbook Photos**: `storage/app/public/logbooks-photos`
- **Public Storage**: `public/storage` (symlink)

## 📖 Usage

### Admin Panel

1. Login di `/` dengan kredensial admin
2. Dashboard menampilkan:
   - Statistik pendapatan bulanan
   - Ringkasan stock komponen (tersedia)
3. Navigasi menu:
   - **Invoices**: Kelola invoice, generate PDF, export Excel
   - **Assemblies**: Tracking rakitan dengan serial number
   - **Components**: Manajemen stock komponen
   - **Employees**: Data karyawan, generate slip gaji
   - **Logbooks**: Review aktivitas karyawan
   - **Cashbons**: Approve/reject request cashbon
   - **Backup Data**: Export semua data ke Excel

### Employee Panel

1. Login di `/employee` dengan kredensial karyawan
2. Fitur yang tersedia:
   - **My Logbook**: Input aktivitas harian + upload foto
   - **My Cashbon**: Request cashbon
   - **My Assembly**: Buat dan lihat assembly

## 🚢 Deployment

### VPS Deployment dengan Docker

Lihat panduan lengkap di [DEPLOYMENT.md](DEPLOYMENT.md) (jika ada) atau [README.DOCKER.md](README.DOCKER.md)

**Quick Start:**
```bash
# Di VPS
git clone https://github.com/neorafaz/digigate-system.git
cd digigate-system

# Setup .env
cp .env.example .env
nano .env  # Edit sesuai production

# Build dan start
docker-compose -f docker-compose.prod.yml up -d --build

# Migrate
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --seed

# Optimize
docker-compose -f docker-compose.prod.yml exec app php artisan config:cache
docker-compose -f docker-compose.prod.yml exec app php artisan route:cache
docker-compose -f docker-compose.prod.yml exec app php artisan view:cache
```

## 🔄 Git Workflow

### Setup Git

1. **Clone repository**
   ```bash
   git clone https://github.com/neorafaz/digigate-system.git
   cd digigate-system
   ```

2. **Setup remote (jika belum)**
   ```bash
   git remote add origin https://github.com/neorafaz/digigate-system.git
   # atau dengan SSH
   git remote add origin git@github.com:neorafaz/digigate-system.git
   ```

### Daily Workflow

1. **Pull latest changes**
   ```bash
   git pull origin main
   ```

2. **Buat branch untuk fitur baru**
   ```bash
   git checkout -b feature/nama-fitur
   ```

3. **Commit perubahan**
   ```bash
   git add .
   git commit -m "Add: deskripsi fitur"
   ```

4. **Push ke GitHub**
   ```bash
   git push origin feature/nama-fitur
   ```

5. **Buat Pull Request di GitHub** (untuk review)

### Commit Message Convention

Gunakan format berikut untuk commit message:

- `Add:` - Menambah fitur baru
- `Fix:` - Perbaikan bug
- `Update:` - Update fitur yang sudah ada
- `Refactor:` - Refactoring code
- `Docs:` - Update dokumentasi
- `Style:` - Perubahan formatting (tidak mempengaruhi logic)
- `Test:` - Menambah atau update test

**Contoh:**
```bash
git commit -m "Add: fitur export Excel untuk invoices"
git commit -m "Fix: perbaiki bug login karyawan"
git commit -m "Update: improve PDF invoice layout"
```

### Branch Strategy

- `main` - Branch production (stable)
- `develop` - Branch development (jika ada)
- `feature/*` - Branch untuk fitur baru
- `fix/*` - Branch untuk bug fix
- `hotfix/*` - Branch untuk urgent fix di production

### Update dari Main Branch

```bash
# Switch ke main
git checkout main

# Pull latest
git pull origin main

# Merge ke branch Anda
git checkout feature/nama-fitur
git merge main

# Resolve conflict jika ada, lalu push
git push origin feature/nama-fitur
```

### Troubleshooting Git

**Error: Permission denied**
```bash
# Update remote URL dengan username
git remote set-url origin https://neorafaz@github.com/neorafaz/digigate-system.git

# Atau gunakan SSH
git remote set-url origin git@github.com:neorafaz/digigate-system.git
```

**Error: Credential tersimpan salah**
```bash
# Clear credential
git credential reject <<EOF
protocol=https
host=github.com
EOF

# Push lagi (akan diminta login)
git push origin main
```

## 📁 Project Structure

```
digigate-system/
├── app/
│   ├── Exports/              # Excel export classes
│   ├── Filament/
│   │   ├── Employee/         # Employee panel pages
│   │   ├── Pages/            # Custom admin pages
│   │   ├── Resources/        # Filament resources (CRUD)
│   │   └── Widgets/          # Dashboard widgets
│   ├── Http/
│   │   └── Controllers/      # Controllers (PDF, Salary Slip)
│   ├── Models/               # Eloquent models
│   └── Providers/            # Service providers
├── database/
│   ├── migrations/           # Database migrations
│   └── seeders/             # Database seeders
├── docker/                   # Docker configs
│   └── nginx/               # Nginx configuration
├── public/
│   └── images/               # Logo, kop, favicon
├── resources/
│   └── views/               # Blade templates
│       ├── invoices/        # PDF invoice templates
│       └── salary-slips/    # Salary slip templates
├── routes/
│   └── web.php              # Web routes
├── Dockerfile               # Dockerfile for development
├── Dockerfile.prod          # Dockerfile for production
├── docker-compose.yml       # Docker compose (dev)
└── docker-compose.prod.yml  # Docker compose (production)
```

## 🔐 Security

- **Jangan commit file `.env`** ke Git
- Gunakan password yang kuat untuk database di production
- Setup SSL/HTTPS untuk production
- Regular backup database dan storage
- Update dependencies secara berkala

## 📝 Notes

- Database menggunakan UUID untuk semua primary keys
- Serial number assembly format: `DG(YYYY)(MM)(XXX)` (contoh: DG202512001)
- Foto logbook disimpan di `storage/app/public/logbooks-photos`
- Backup data tidak termasuk foto (hanya metadata)

## 🤝 Contributing

1. Fork repository
2. Buat branch untuk fitur (`git checkout -b feature/AmazingFeature`)
3. Commit perubahan (`git commit -m 'Add: AmazingFeature'`)
4. Push ke branch (`git push origin feature/AmazingFeature`)
5. Buat Pull Request

## 📄 License

This project is proprietary software for PT. Gerbang Digital Indonesia.

## 👥 Contact

- **Repository**: https://github.com/neorafaz/digigate-system
- **Issues**: https://github.com/neorafaz/digigate-system/issues

---

**Made with ❤️ for PT. Gerbang Digital Indonesia**
