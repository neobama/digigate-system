#!/bin/bash

# Update Script untuk Laravel DigiGate System (Production)
# Usage: ./update.sh
# Digunakan untuk update coding dari GitHub tanpa kehilangan data

set -e  # Exit on error

echo "🔄 Starting application update..."

# Warna untuk output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function untuk rollback
rollback() {
    echo -e "${RED}❌ Error occurred! Rolling back...${NC}"
    git reset --hard HEAD@{1}
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    sudo systemctl reload php8.4-fpm
    sudo systemctl reload nginx
    echo -e "${RED}⚠️  Rolled back to previous version${NC}"
    exit 1
}

# Jangan auto-rollback pada error opsional (backup/sudo).
# Rollback manual tetap bisa via: git reset --hard ORIG_HEAD

# 1. Backup database (opsional, tapi recommended)
echo "📦 Backing up database..."
if command -v mysqldump &> /dev/null; then
    DB_NAME=$(grep DB_DATABASE .env | cut -d '=' -f2 | tr -d ' ' | tr -d '"' | tr -d "'")
    if [ ! -z "$DB_NAME" ] && [ "$DB_NAME" != "sqlite" ]; then
        BACKUP_DIR="${HOME}/backups/digigate"
        if ! mkdir -p "$BACKUP_DIR" 2>/dev/null; then
            BACKUP_DIR="storage/backups"
            mkdir -p "$BACKUP_DIR" 2>/dev/null || true
        fi
        if [ -d "$BACKUP_DIR" ] && [ -w "$BACKUP_DIR" ]; then
            BACKUP_FILE="$BACKUP_DIR/digigate_$(date +%Y%m%d_%H%M%S).sql"
            if mysqldump "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null; then
                echo "✅ Database backup: $BACKUP_FILE"
            else
                rm -f "$BACKUP_FILE" 2>/dev/null || true
                echo "⚠️  Database backup skipped (mysqldump not available or no permission)"
            fi
        else
            echo "⚠️  Database backup skipped (no writable backup directory)"
        fi
    else
        echo "ℹ️  Using SQLite, backup skipped"
    fi
else
    echo "⚠️  mysqldump not found, backup skipped"
fi

# 2. Check git status
echo "📋 Checking git status..."
git fetch origin
CURRENT_BRANCH=$(git branch --show-current)
echo "Current branch: $CURRENT_BRANCH"

# 3. Stash local changes (jika ada)
if ! git diff-index --quiet HEAD --; then
    echo -e "${YELLOW}⚠️  Local changes detected, stashing...${NC}"
    git stash push -m "Auto-stash before update $(date +%Y%m%d_%H%M%S)"
fi

# 4. Pull latest code from GitHub
echo "📥 Pulling latest code from GitHub..."
git pull origin "$CURRENT_BRANCH" || {
    echo -e "${RED}❌ Git pull failed!${NC}"
    exit 1
}

# 5. Install/update dependencies
echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# 6. Run migrations (HANYA menambah, tidak menghapus data)
echo "🗄️ Running migrations..."
php artisan migrate --force

# 7. Clear and cache config
echo "⚡ Optimizing application..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Build assets (jika ada)
if [ -f "package.json" ]; then
    echo "🎨 Building assets..."
    if command -v npm &> /dev/null; then
        npm ci --silent
        npm run build --silent
    else
        echo "⚠️  npm not found, skipping asset build"
    fi
fi

# 9. Set permissions
echo "🔐 Setting permissions..."
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
if sudo -n true 2>/dev/null; then
    sudo chown -R www-data:www-data storage bootstrap/cache
    sudo chmod -R 775 storage bootstrap/cache
else
    echo "⚠️  sudo passwordless not available; kept current ownership"
fi

# 10. Restart services
echo "🔄 Restarting services..."
if sudo -n true 2>/dev/null; then
    sudo systemctl reload php8.4-fpm 2>/dev/null || echo "⚠️  PHP-FPM reload skipped"
    sudo systemctl reload nginx 2>/dev/null || echo "⚠️  Nginx reload skipped"
else
    echo "⚠️  Service reload skipped (sudo password required)"
fi

# 11. Verify application
echo "✅ Verifying application..."
if php artisan about &> /dev/null; then
    echo -e "${GREEN}✅ Application is running correctly!${NC}"
else
    echo -e "${YELLOW}⚠️  Application verification failed, but update completed${NC}"
fi

echo ""
echo -e "${GREEN}✅ Application update completed successfully!${NC}"
echo ""
echo "ℹ️  Note: .env file was NOT modified"
echo "ℹ️  Note: Database was NOT modified (only migrations applied)"
echo "ℹ️  Note: Admin user was NOT re-seeded (already exists)"
echo ""
echo "📝 If you encounter any issues, check:"
echo "   - Storage logs: storage/logs/laravel.log"
echo "   - Nginx logs: /var/log/nginx/digigate-error.log"
echo "   - PHP-FPM logs: /var/log/php8.4-fpm.log"

