# Konfigurasi CORS untuk S3 di Filament FileUpload

## Masalah: Upload muter-muter terus tidak selesai

Masalah ini terjadi karena Filament FileUpload mencoba upload langsung dari browser ke S3, tapi terblokir oleh CORS policy.

## Solusi: Konfigurasi CORS di S3 Bucket

Anda perlu mengkonfigurasi CORS di S3 bucket `s3-digigate`. Berikut langkah-langkahnya:

### 1. Masuk ke Panel S3 (is3.cloudhost.id)

### 2. Pilih bucket `s3-digigate`

### 3. Buka menu CORS Configuration

### 4. Tambahkan CORS Configuration berikut:

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
            "http://127.0.0.1:8000",
            "https://yourdomain.com"
        ],
        "ExposeHeaders": [
            "ETag",
            "x-amz-server-side-encryption",
            "x-amz-request-id",
            "x-amz-id-2"
        ],
        "MaxAgeSeconds": 3000
    }
]
```

**PENTING:** Ganti `https://yourdomain.com` dengan domain aplikasi Anda yang sebenarnya (misalnya `https://digigate.yourdomain.com`).

### 5. Simpan CORS Configuration

### 6. Test Upload

Setelah CORS dikonfigurasi, test upload dari aplikasi. Upload seharusnya tidak lagi muter-muter.

## Alternatif: Gunakan Local Storage

Jika tidak bisa mengkonfigurasi CORS, gunakan local storage sementara:

1. Ubah `.env`:
   ```env
   FILESYSTEM_DISK=local
   ```

2. Clear cache:
   ```bash
   php artisan config:clear
   ```

3. Upload akan tersimpan di `storage/app/public/logbooks-photos/`

## Troubleshooting

### Cek Browser Console
Buka browser console (F12) dan cek apakah ada error CORS:
- `Access to XMLHttpRequest at '...' from origin '...' has been blocked by CORS policy`

### Cek S3 Bucket Policy
Pastikan bucket policy mengizinkan public read:
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "PublicReadGetObject",
            "Effect": "Allow",
            "Principal": "*",
            "Action": "s3:GetObject",
            "Resource": "arn:aws:s3:::s3-digigate/*"
        }
    ]
}
```

### Test S3 Connection
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

Jika test ini berhasil, masalahnya adalah CORS configuration.

