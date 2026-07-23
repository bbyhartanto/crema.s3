# Deploying `crema.s3` to Hostinger KVM1

This guide provides the exact SSH CLI commands and setup instructions to host **`crema.s3`** (Laravel 13 API) on Hostinger KVM1 and set up automatic deployment on `git push main`.

---

## 📍 Server Architecture Overview

- **Hostinger Server SSH**: `ssh -p 65002 u876211904@145.79.14.146`
- **Application Core Location**: `~/domains/roaster.crema.supply/core3`
- **Web Docroot (Public Traffic)**: `~/domains/roaster.crema.supply/public_html` (or subdomain web root)

---

## ⚡ The `public_html` Obstacle & How to Handle It

### Why the obstacle exists
Hostinger expects web traffic to be served directly from `public_html` (or a domain's designated docroot folder). However, Laravel applications store source code at the project root (`core3`) and serve assets/index.php via `public/`.

### Solution: Delegate via Entrypoint & Symlinks
Do **NOT** copy or move Laravel source code into `public_html`. Instead:
1. **Laravel application code** remains inside `~/domains/roaster.crema.supply/core3`.
2. **`public_html/index.php`** delegates request execution to `../core3`.
3. **Symlinks** are created in `public_html` for storage uploads and compiled Vite assets.

---

## 🛠️ Step-by-Step SSH CLI Guide (Spell-out Guide)

### 1. SSH into Hostinger KVM1
```bash
ssh -p 65002 u876211904@145.79.14.146
```

---

### 2. Initial Repository Clone & Setup inside `core3`
```bash
# Navigate to the domain root
cd ~/domains/roaster.crema.supply

# Clone crema.s3 repository into core3
git clone git@github.com:YOUR_GITHUB_USERNAME/crema.s3.git core3

# Navigate into core3
cd core3

# Copy and configure environment variables
cp .env.example .env
nano .env   # Update APP_URL, DB_DATABASE, DB_USERNAME, DB_PASSWORD, etc.

# Install production dependencies
composer install --no-dev --optimize-autoloader

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate --force

# Link storage directory inside core3
php artisan storage:link

# Give write permissions to storage and bootstrap/cache if needed
chmod -R 775 storage bootstrap/cache
```

---

### 3. Wire Up `public_html` (Hostinger Docroot)

Navigate to the `public_html` folder (or dedicated subdomain public folder):
```bash
cd ~/domains/roaster.crema.supply/public_html
```

#### A. Update `index.php` in `public_html`
Replace the contents of `public_html/index.php` with:
```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Maintenance mode check
if (file_exists($maintenance = __DIR__.'/../core3/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register Composer autoloader
require __DIR__.'/../core3/vendor/autoload.php';

// Bootstrap Laravel from core3
/** @var Application $app */
$app = require_once __DIR__.'/../core3/bootstrap/app.php';

$app->handleRequest(Request::capture());
```

#### B. Create Asset & Storage Symlinks in `public_html`
```bash
# Link Vite build directory
rm -rf build
ln -s ../core3/public/build build

# Link public storage directory for media uploads
rm -rf storage
ln -sfn ../core3/storage/app/public storage
```

---

## 🤖 Automatic Deployment via GitHub Actions (`git push main`)

Every `git push` to `main` branch will automatically trigger `.github/workflows/deploy.yml` which SSHs into Hostinger and executes `deploy.sh`.

### Required GitHub Repository Secrets
Go to your GitHub Repository **Settings → Secrets and variables → Actions** and add:

| Secret Name | Value Example | Description |
|---|---|---|
| `HOSTINGER_HOST` | `145.79.14.146` | Hostinger Server IP address |
| `HOSTINGER_USER` | `u876211904` | SSH username |
| `HOSTINGER_SSH_KEY` | `-----BEGIN OPENSSH PRIVATE KEY-----...` | Private SSH Key corresponding to server `~/.ssh/authorized_keys` |
| `HOSTINGER_PORT` | `65002` | Hostinger SSH Port |

---

## ⏱️ Hostinger Cron Setup (Laravel Scheduler)

In **Hostinger hPanel → Advanced → Cron Jobs**, create a cron job running every minute:

```bash
* * * * * cd /home/u876211904/domains/roaster.crema.supply/core3 && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

---

## 🔍 Manual Deployment / Verification Commands

To trigger deployment manually over SSH:
```bash
cd ~/domains/roaster.crema.supply/core3
./deploy.sh
```

To test application status:
```bash
curl -i https://roaster.crema.supply/up
```
