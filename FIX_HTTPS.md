# Fix HTTPS Mixed Content Issue

Jika tampilan masih berantakan setelah menggunakan trusted proxies, ikuti langkah-langkah berikut:

## 🔧 Fix di VPS

### 1. Update .env File

```bash
cd /var/www/digigate-system

# Edit .env
sudo nano .env
```

Pastikan konfigurasi berikut:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.digigate.id

# Tambahkan ini jika belum ada
ASSET_URL=https://erp.digigate.id
FORCE_HTTPS=true
```

**PENTING:** 
- `APP_URL` harus menggunakan `https://` (bukan `http://`)
- `APP_ENV` harus `production` (bukan `local`)
- `ASSET_URL` opsional tapi recommended

### 2. Clear dan Rebuild Cache

```bash
cd /var/www/digigate-system

# Clear semua cache
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan optimize:clear

# Rebuild cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### 3. Restart Services

```bash
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx
```

### 4. Verifikasi

1. Buka browser dengan **Hard Refresh** (Ctrl+Shift+R atau Cmd+Shift+R)
2. Buka Developer Tools (F12)
3. Tab **Network** - pastikan semua asset (CSS/JS) menggunakan HTTPS
4. Tab **Console** - pastikan tidak ada error mixed content

## 🐛 Troubleshooting

### Masih ada asset HTTP?

1. **Cek .env:**
   ```bash
   grep APP_URL .env
   grep APP_ENV .env
   ```

2. **Cek config cache:**
   ```bash
   php artisan config:show app.url
   php artisan config:show app.env
   ```

3. **Force clear semua:**
   ```bash
   php artisan optimize:clear
   php artisan config:cache
   ```

### Masih berantakan?

1. **Cek browser console** untuk error spesifik
2. **Cek Network tab** untuk melihat asset mana yang masih HTTP
3. **Cek Nginx Proxy Manager** - pastikan mengirim header:
   - `X-Forwarded-Proto: https`
   - `X-Forwarded-For`
   - `X-Real-IP`

### Test Trusted Proxies

```bash
# Test di tinker
php artisan tinker

# Jalankan:
request()->getScheme()
request()->isSecure()
url('/')
asset('images/logo.png')
```

Seharusnya semua return HTTPS.

## ✅ Checklist

- [ ] `.env` sudah diupdate dengan `APP_URL=https://...`
- [ ] `.env` sudah diupdate dengan `APP_ENV=production`
- [ ] Cache sudah di-clear dan rebuild
- [ ] Services sudah di-restart
- [ ] Browser sudah di-hard refresh
- [ ] Tidak ada error di browser console
- [ ] Semua asset menggunakan HTTPS di Network tab

## 📝 Quick Fix (Semua Sekaligus)

```bash
cd /var/www/digigate-system

# 1. Update .env
sudo sed -i 's|APP_URL=.*|APP_URL=https://erp.digigate.id|' .env
sudo sed -i 's|APP_ENV=.*|APP_ENV=production|' .env
grep -q "ASSET_URL" .env || echo "ASSET_URL=https://erp.digigate.id" >> .env

# 2. Clear dan rebuild cache
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# 3. Restart services
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx

echo "✅ Fixed! Hard refresh browser (Ctrl+Shift+R)"
```

