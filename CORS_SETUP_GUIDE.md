# Panduan Konfigurasi CORS untuk S3 Bucket

## Masalah
Error CORS terjadi karena browser mencoba upload langsung ke S3, tapi S3 bucket belum dikonfigurasi untuk mengizinkan request dari domain aplikasi.

## Solusi: Konfigurasi CORS di S3 Bucket

### Langkah-langkah:

1. **Login ke Panel S3 (is3.cloudhost.id)**

2. **Pilih bucket `s3-digigate`**

3. **Buka menu CORS Configuration**
   - Biasanya ada di menu "Permissions" atau "Settings"
   - Atau cari menu "CORS" atau "Cross-Origin Resource Sharing"

4. **Copy dan paste konfigurasi CORS berikut:**

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
            "HEAD",
            "OPTIONS"
        ],
        "AllowedOrigins": [
            "http://127.0.0.1:8000",
            "http://localhost:8000",
            "https://yourdomain.com"
        ],
        "ExposeHeaders": [
            "ETag",
            "x-amz-server-side-encryption",
            "x-amz-request-id",
            "x-amz-id-2",
            "Content-Length",
            "Content-Type"
        ],
        "MaxAgeSeconds": 3000
    }
]
```

**PENTING:** 
- Ganti `https://yourdomain.com` dengan domain aplikasi Anda yang sebenarnya (misalnya `https://digigate.yourdomain.com`)
- Jika aplikasi masih di localhost, biarkan `http://127.0.0.1:8000` dan `http://localhost:8000`

5. **Simpan konfigurasi CORS**

6. **Test upload lagi dari aplikasi**

### Catatan:
- Setelah mengubah CORS, mungkin perlu beberapa menit untuk perubahan diterapkan
- Jika masih error, pastikan domain aplikasi Anda sudah ada di `AllowedOrigins`
- Untuk production, pastikan hanya domain yang valid yang ada di `AllowedOrigins` (jangan gunakan `*` untuk security)

### Alternatif: Jika tidak bisa set CORS
Jika panel S3 Anda tidak memiliki opsi untuk set CORS, atau CORS tidak bisa dikonfigurasi, maka kita perlu menggunakan pendekatan upload via server Laravel dulu, baru kemudian pindahkan ke S3.

