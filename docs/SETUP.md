# Setup

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+
- npm
- SQLite extension enabled in PHP

## Install

```bash
composer install
npm install
```

Create the environment file:

```bash
cp .env.example .env
php artisan key:generate
```

On Windows PowerShell:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Create the local SQLite database:

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
```

Optional demo seed:

```bash
php artisan migrate:fresh --seed
```

Remove demo data while keeping real imports:

```bash
php artisan futia:cleanup-demo-data
```

## Run

Start Laravel:

```bash
php artisan serve
```

Start Vite:

```bash
npm run dev
```

Open:

```text
http://127.0.0.1:8000
```

## Validate

```bash
php artisan test
npm run typecheck
npm run build
```
