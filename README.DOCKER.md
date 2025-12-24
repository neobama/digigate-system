# Docker Setup untuk DigiGate System

## Prerequisites

- Docker
- Docker Compose

## Development Setup

1. **Copy environment file:**
   ```bash
   cp .env.docker .env
   ```

2. **Generate application key:**
   ```bash
   docker-compose run --rm app php artisan key:generate
   ```

3. **Build and start containers:**
   ```bash
   docker-compose up -d --build
   ```

4. **Run migrations:**
   ```bash
   docker-compose exec app php artisan migrate --seed
   ```

5. **Create storage link:**
   ```bash
   docker-compose exec app php artisan storage:link
   ```

6. **Set permissions:**
   ```bash
   docker-compose exec app chmod -R 775 storage bootstrap/cache
   ```

7. **Access application:**
   - Admin Panel: http://localhost:8000
   - Employee Panel: http://localhost:8000/employee

## Production Setup

1. **Copy environment file:**
   ```bash
   cp .env.docker .env
   ```

2. **Update .env untuk production:**
   - Set `APP_ENV=production`
   - Set `APP_DEBUG=false`
   - Update database credentials
   - Set `APP_URL` sesuai domain Anda

3. **Generate application key:**
   ```bash
   docker-compose -f docker-compose.prod.yml run --rm app php artisan key:generate
   ```

4. **Build and start containers:**
   ```bash
   docker-compose -f docker-compose.prod.yml up -d --build
   ```

5. **Run migrations:**
   ```bash
   docker-compose -f docker-compose.prod.yml exec app php artisan migrate --seed
   ```

6. **Optimize for production:**
   ```bash
   docker-compose -f docker-compose.prod.yml exec app php artisan config:cache
   docker-compose -f docker-compose.prod.yml exec app php artisan route:cache
   docker-compose -f docker-compose.prod.yml exec app php artisan view:cache
   ```

## Useful Commands

### View logs
```bash
docker-compose logs -f app
docker-compose logs -f nginx
docker-compose logs -f db
```

### Access container shell
```bash
docker-compose exec app bash
```

### Run artisan commands
```bash
docker-compose exec app php artisan [command]
```

### Stop containers
```bash
docker-compose down
```

### Stop and remove volumes (WARNING: deletes database and storage)
```bash
docker-compose down -v
```

## Volume Management

Aplikasi menggunakan **3 volume terpisah** untuk data persistence:

1. **`db_data`** - Database MySQL (data aplikasi)
2. **`app_storage`** - Storage files (foto logbook, uploads)
3. **`app_public`** - Public storage (production only)

### Backup Volume

#### Backup Database
```bash
# Backup database
docker-compose exec db mysqldump -u digigate_user -ppassword digigate_db > backup_$(date +%Y%m%d_%H%M%S).sql

# Atau backup volume langsung
docker run --rm -v digigate-system_db_data:/data -v $(pwd):/backup alpine tar czf /backup/db_backup_$(date +%Y%m%d_%H%M%S).tar.gz /data
```

#### Backup Storage
```bash
# Backup storage volume
docker run --rm -v digigate-system_app_storage:/data -v $(pwd):/backup alpine tar czf /backup/storage_backup_$(date +%Y%m%d_%H%M%S).tar.gz /data
```

#### Restore Database
```bash
# Restore dari SQL dump
docker-compose exec -T db mysql -u digigate_user -ppassword digigate_db < backup_20251223_120000.sql

# Atau restore dari volume backup
docker run --rm -v digigate-system_db_data:/data -v $(pwd):/backup alpine tar xzf /backup/db_backup_20251223_120000.tar.gz -C /
```

#### List Volumes
```bash
docker volume ls | grep digigate-system
```

#### Inspect Volume Location
```bash
docker volume inspect digigate-system_db_data
docker volume inspect digigate-system_app_storage
```

## Database Access

- **Host:** localhost
- **Port:** 3306
- **Database:** digigate_db
- **Username:** digigate_user
- **Password:** password

## Architecture

### Development (`docker-compose.yml`)
- **App Container**: Bind mount seluruh folder untuk development
- **Storage**: Named volume `app_storage` (terpisah)
- **Database**: Named volume `db_data` (terpisah)

### Production (`docker-compose.prod.yml`)
- **App Container**: Code di-build ke dalam image (tidak bind mount)
- **Storage**: Named volume `app_storage` (terpisah, persistent)
- **Public**: Named volume `app_public` (terpisah, untuk public files)
- **Database**: Named volume `db_data` (terpisah, persistent)

## Notes

- **Volume Separation**: Data aplikasi (database) dan storage (files) sudah dipisah menjadi volume terpisah, sehingga mudah untuk backup dan restore
- Untuk production, pastikan untuk:
  - Menggunakan SSL certificate (HTTPS)
  - Mengubah password database yang kuat
  - Mengatur backup database dan storage secara berkala
  - Menggunakan environment variables yang aman
  - Backup volume secara rutin (disarankan harian)

