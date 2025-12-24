# Troubleshooting S3 Upload Issue

## Masalah: Upload muter-muter terus tidak selesai

Masalah ini biasanya terjadi karena:

1. **CORS Configuration** - S3 bucket perlu dikonfigurasi untuk mengizinkan upload dari domain aplikasi
2. **Filament FileUpload** - Filament FileUpload untuk S3 memerlukan konfigurasi khusus

## Solusi Sementara: Gunakan Local Storage

Untuk sementara, gunakan local storage dengan mengubah `.env`:

```env
FILESYSTEM_DISK=local
```

Kemudian clear cache:
```bash
php artisan config:clear
php artisan view:clear
```

## Solusi Permanen: Konfigurasi CORS di S3

Jika ingin tetap menggunakan S3, pastikan S3 bucket memiliki CORS configuration:

```json
[
    {
        "AllowedHeaders": [
            "*"
        ],
        "AllowedMethods": [
            "GET",
            "PUT",
            "POST",
            "DELETE",
            "HEAD"
        ],
        "AllowedOrigins": [
            "http://localhost:8000",
            "https://yourdomain.com"
        ],
        "ExposeHeaders": [
            "ETag"
        ],
        "MaxAgeSeconds": 3000
    }
]
```

Ganti `http://localhost:8000` dan `https://yourdomain.com` dengan domain aplikasi Anda.

## Cek Browser Console

Buka browser console (F12) dan cek apakah ada error CORS atau error lainnya saat upload.

## Test S3 Connection

Test koneksi S3 dengan:
```bash
php artisan tinker
```

Kemudian:
```php
Storage::disk('s3_public')->put('test.txt', 'test');
Storage::disk('s3_public')->exists('test.txt'); // Should return true
Storage::disk('s3_public')->delete('test.txt');
```

Jika test ini berhasil, masalahnya kemungkinan besar adalah CORS configuration.

