#!/bin/bash
set -e

echo "🚀 Deploying crema.s3 to Hostinger KVM1..."

# Put app into maintenance mode
php artisan down || true

# Pull latest code from main branch
git pull origin main

# Install PHP dependencies
composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=ext-sodium

# Run database migrations
php artisan migrate --force

# Install Node dependencies and build frontend assets if package.json exists
if [ -f "package.json" ]; then
    npm ci || npm install
    npm run build --if-present
fi

# Clear and rebuild caches
php artisan optimize:clear
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

# Restart supervisor / queue worker if configured
php artisan queue:restart || true

# Bring app back online
php artisan up

echo "✅ crema.s3 Deployment finished successfully!"
