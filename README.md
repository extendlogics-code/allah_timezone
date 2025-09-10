# PHP Project Starter

A minimal PHP 8 project skeleton with PSR-4 autoloading, environment configuration, and a tiny front controller.

## Quick start

1. Install dependencies

   ```bash
   composer install
   ```

2. Configure environment

   ```bash
   cp .env.example .env
   # edit .env as needed
   ```

3. Run the dev server

   ```bash
   composer start
   # open http://localhost:8000
   ```

4. Run tests

   ```bash
   composer test
   ```

## Structure

```
public/           # Web root (index.php front controller)
bootstrap/        # App bootstrap (env, error reporting, timezone)
src/              # Application source (PSR-4: App\)
tests/            # PHPUnit tests
```

## Notes

- Autoloading follows PSR-4 with namespace `App\\` mapped to `src/`.
- `.env` is optional; if `vlucas/phpdotenv` is installed and `.env` exists, variables are loaded automatically.
- Default timezone is read from `APP_TIMEZONE` (falls back to `UTC`).

