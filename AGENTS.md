# AGENTS.md — crema.s3

## Project overview

Laravel 13 API + Identity Provider (PHP ^8.3). Serves as the auth layer, product search API, catalog webhook receiver, and click tracker for the Crema ecosystem (S1/S2/S3). Deployed to Hostinger KVM1.

## Key commands

| Purpose | Command |
|---|---|
| First-time setup | `composer setup` |
| Dev (server + queue + logs + vite) | `composer dev` |
| Run all tests | `composer test` |
| Run a single test | `vendor/bin/phpunit --filter=TestClassName` |
| Run a single test file | `vendor/bin/phpunit tests/Feature/SomeTest.php` |
| Format / lint | `vendor/bin/pint` |
| Run artisan | `php artisan <command>` |

## Architecture

- **Entry points**: `routes/api.php` (JSON API), `routes/web.php` (root redirect, JWKS, outbound links)
- **Models**: `User`, `Customer`, `CustomerAddress`, `CatalogItem`, `MarketplaceClick`
- **Auth**: Laravel Passport (OAuth2). JWKS exposed at `/.well-known/jwks.json` and `/oauth/jwks`
- **API endpoints**: `/v1/search`, `/v1/products/{store}/{slug}`, `/v1/webhooks/catalog`, `/v1/auth/*`, `/outbound`, `/v1/clicks/track`
- **Middleware**: `CheckTokenBlacklist` guards protected routes

## Testing

- PHPUnit with in-memory SQLite (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`)
- No external services needed for tests
- Two suites: `Unit` and `Feature` under `tests/`
- Base test class: `tests/TestCase.php` (extends Laravel `BaseTestCase`)

## Tooling quirks

- **`.npmrc` sets `ignore-scripts=true`** — npm post-install scripts are skipped by default
- Default DB is **SQLite** (single `database/database.sqlite` file); sessions/queue/cache also use database driver
- Uses **Predis** client for Redis (not phpredis extension)
- Frontend: Vite + Tailwind CSS v4, builds to `public/build` (gitignored)

## Deployment

- **Trigger**: push to `main` → `.github/workflows/deploy.yml` → SSH to Hostinger → runs `deploy.sh`
- **Deploy sequence**: `artisan down` → `git pull` → `composer install` → `migrate --force` → `npm ci/build` → cache rebuild (`config`, `event`, `route`, `view`) → `queue:restart` → `artisan up`
- **Server path**: `~/domains/s3.crema.supply/public_html/core3`
- **Cron**: Laravel scheduler runs every minute via Hostinger cron job
- GitHub secrets required: `HOSTINGER_HOST`, `HOSTINGER_USER`, `HOSTINGER_SSH_KEY`, `HOSTINGER_PORT`

## Code style

- **Pint** (Laravel Pint) for formatting — run `vendor/bin/pint` before committing
