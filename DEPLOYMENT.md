# Deployment Guide - DigiGate System

Panduan untuk deploy dan update aplikasi DigiGate System di production server.

## 📋 Prasyarat

1. Server sudah terinstall:
   - PHP 8.4 dengan extensions: mysql, xml, mbstring, curl, zip, bcmath
   - Composer
   - Git
   - Nginx
   - MySQL atau SQLite
   - Node.js & npm (jika ada frontend assets)

2. Repository sudah di-clone di `/var/www/digigate-system`
3. File `.env` sudah dikonfigurasi dengan benar
4. Database sudah dibuat dan dikonfigurasi

## 🚀 Initial Deployment (Setup Awal)

Jika ini pertama kali deploy:

```bash
cd /var/www/digigate-system

# 1. Clone repository (jika belum)
# git clone https://github.com/neobama/digigate-system.git /var/www/digigate-system

# 2. Setup .env
cp .env.example .env
nano .env  # Edit sesuai kebutuhan

# 3. Install dependencies
composer install --no-dev --optimize-autoloader

# 4. Generate APP_KEY
php artisan key:generate

# 5. Run migrations dan seed
php artisan migrate:fresh --seed

# 6. Set permissions
sudo chown -R www-data:www-data /var/www/digigate-system
sudo chmod -R 755 /var/www/digigate-system
sudo chmod -R 775 storage bootstrap/cache

# 7. Create storage link
php artisan storage:link

# 8. Cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 9. Restart services
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx
```

## 🔄 Update Application (Best Practice)

### Opsi 1: Menggunakan Script Update (Recommended)

```bash
cd /var/www/digigate-system
./update.sh
```

Script ini akan:
- ✅ Backup database (jika MySQL)
- ✅ Check git status
- ✅ Stash local changes (jika ada)
- ✅ Pull latest code
- ✅ Install dependencies
- ✅ Run migrations
- ✅ Clear dan rebuild cache
- ✅ Build assets (jika ada)
- ✅ Set permissions
- ✅ Restart services
- ✅ Auto-rollback jika error

### Opsi 2: Manual Update (Step by Step)

#### 1. Backup Database (PENTING!)

```bash
# Untuk MySQL
mysqldump -u root digigate_db > /var/backups/digigate_$(date +%Y%m%d_%H%M%S).sql

# Atau untuk SQLite
cp database/database.sqlite /var/backups/digigate_$(date +%Y%m%d_%H%M%S).sqlite
```

#### 2. Check Git Status

```bash
cd /var/www/digigate-system
git fetch origin
git status
```

#### 3. Stash Local Changes (jika ada)

```bash
# Jika ada perubahan lokal yang belum di-commit
git stash push -m "Backup before update $(date +%Y%m%d_%H%M%S)"
```

#### 4. Pull Latest Code

```bash
# Pull dari branch main/master
git pull origin main

# Atau branch lain
git pull origin production
```

#### 5. Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

#### 6. Run Migrations

```bash
# Hanya menambah tabel/kolom baru, TIDAK menghapus data
php artisan migrate --force
```

**⚠️ PERINGATAN:** Jangan gunakan `migrate:fresh` di production! Itu akan menghapus semua data.

#### 7. Clear dan Rebuild Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Rebuild cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### 8. Build Assets (jika ada)

```bash
npm ci
npm run build
```

#### 9. Set Permissions

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

#### 10. Restart Services

```bash
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx
```

#### 11. Verify

```bash
# Test aplikasi
php artisan about

# Check logs jika ada error
tail -f storage/logs/laravel.log
```

## 🔙 Rollback (Jika Ada Masalah)

Jika update menyebabkan masalah, rollback ke versi sebelumnya:

```bash
cd /var/www/digigate-system

# 1. Rollback git
git reset --hard HEAD@{1}  # Kembali ke commit sebelumnya
# Atau
git checkout <commit-hash>  # Kembali ke commit tertentu

# 2. Restore database (jika perlu)
mysql -u root digigate_db < /var/backups/digigate_YYYYMMDD_HHMMSS.sql

# 3. Rebuild cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Restart services
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx
```

## 📝 Checklist Sebelum Update

- [ ] Backup database
- [ ] Check git status (pastikan tidak ada uncommitted changes)
- [ ] Test di staging environment (jika ada)
- [ ] Baca changelog/commit messages
- [ ] Pastikan `.env` tidak akan di-overwrite
- [ ] Pastikan tidak ada migration yang menghapus data
- [ ] Siapkan waktu maintenance (jika perlu)

## ⚠️ Hal yang TIDAK BOLEH Dilakukan

1. ❌ Jangan gunakan `migrate:fresh` di production
2. ❌ Jangan commit file `.env` ke git
3. ❌ Jangan pull saat ada user aktif (jika memungkinkan)
4. ❌ Jangan skip backup database
5. ❌ Jangan langsung pull tanpa cek git status
6. ❌ Jangan hapus folder `storage` atau `vendor`

## 🐛 Troubleshooting

### Error: "Class not found"
```bash
composer dump-autoload
php artisan config:clear
php artisan config:cache
```

### Error: "Migration failed"
```bash
# Check migration status
php artisan migrate:status

# Rollback migration terakhir
php artisan migrate:rollback --step=1

# Fix migration, lalu jalankan lagi
php artisan migrate --force
```

### Error: "Permission denied"
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Error: "APP_KEY not set"
```bash
php artisan key:generate
php artisan config:cache
```

### Error: "Mixed content" (HTTP/HTTPS)
```bash
# Pastikan APP_URL di .env menggunakan HTTPS
nano .env  # APP_URL=https://erp.digigate.id

php artisan config:clear
php artisan config:cache
```

## 📞 Support

Jika ada masalah, cek:
- Log Laravel: `storage/logs/laravel.log`
- Log Nginx: `/var/log/nginx/digigate-error.log`
- Log PHP-FPM: `/var/log/php8.4-fpm.log`

## 🔐 Security Notes

1. Pastikan file `.env` tidak bisa diakses dari web
2. Pastikan folder `storage` dan `bootstrap/cache` memiliki permission yang benar
3. Jangan expose file `.env` atau `composer.json` ke public
4. Gunakan HTTPS di production
5. Update dependencies secara berkala untuk security patches

