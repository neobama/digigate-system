#!/bin/bash

# Script untuk melengkapi file .env

ENV_FILE=".env"

# Backup .env jika sudah ada
if [ -f "$ENV_FILE" ]; then
    cp "$ENV_FILE" "${ENV_FILE}.backup.$(date +%Y%m%d_%H%M%S)"
    echo "✅ Backup .env dibuat"
fi

# Buat .env lengkap
cat > "$ENV_FILE" << 'EOF'
APP_NAME="DigiGate System"
APP_ENV=local
APP_KEY=base64:ej2NXVmiAt5J9tHakY/QdYX45751fp03G+h7RhnNXHY=
APP_DEBUG=true
APP_URL=http://localhost:8000

APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
# APP_MAINTENANCE_STORE=database

# PHP_CLI_SERVER_WORKERS=4

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Database Configuration
# Untuk development, gunakan SQLite (default)
DB_CONNECTION=sqlite
# DB_DATABASE=database/database.sqlite (auto-created)

# Untuk production, gunakan MySQL (uncomment dan isi)
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=digigate_db
# DB_USERNAME=root
# DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log

# Filesystem Configuration
# Set ke 's3' untuk menggunakan S3 storage, atau 'local' untuk local storage
FILESYSTEM_DISK=s3

# AWS S3 Configuration
AWS_ACCESS_KEY_ID=ZS9KPA7WQ6NRT0RFGEWZ
AWS_SECRET_ACCESS_KEY=1Ob6HEQ31nm8OlVe7vlG7ZGZm6vcPBLmkb8hux2e
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=s3-digigate
AWS_ENDPOINT=https://is3.cloudhost.id
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_URL=

QUEUE_CONNECTION=database

CACHE_STORE=database
# CACHE_PREFIX=

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail Configuration
MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="noreply@digigate.id"
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"
EOF

echo "✅ File .env telah dilengkapi dengan semua konfigurasi!"
echo "📝 Silakan edit .env jika perlu menyesuaikan konfigurasi database atau lainnya"

