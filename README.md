# WorkBoard

WorkBoard is a learning project: a project-management and task-tracking web application built with Laravel. It is developed in stages to practice authentication, Eloquent, policies, APIs, queues, testing, and related Laravel topics.

**Status:** Active development. Stages completed so far include the WorkBoard foundation (UI/layout), user authentication, and authenticated **Projects** CRUD with per-user ownership.

## Tech stack

- PHP 8.3+ / Laravel 13
- MySQL (Docker Compose)
- Redis (available in Docker; used in later stages)
- Blade, Tailwind CSS, Vite
- PHPUnit

## Requirements

- [Docker](https://docs.docker.com/get-docker/) and Docker Compose (recommended for this repo)
- Or: PHP 8.3+, Composer, Node.js/npm, and MySQL locally

## Local setup (Docker)

1. Clone the repository and enter the project directory.

2. Copy the environment file and generate an application key:

   ```bash
   cp .env.example .env
   docker compose run --rm composer install
   docker compose exec php php artisan key:generate
   ```

3. Start services (PHP-FPM, Nginx, MySQL, Redis):

   ```bash
   docker compose up -d
   ```

   The PHP entrypoint creates Laravel cache/log directories and maps the container `www-data` user to the same numeric UID/GID as the bind-mounted project (auto-detected from `/var/www`, or set `APP_USER_ID` / `APP_GROUP_ID` in `.env`). That lets PHP-FPM write under `storage/` and `bootstrap/cache/` without changing ownership of your WSL files. Prefer `docker compose exec -u www-data php php artisan …` over running Artisan as root. After changing `Dockerfile` or `docker/php/entrypoint.sh`, rebuild with `docker compose build php && docker compose up -d php`.

4. Run migrations:

   ```bash
   docker compose exec php php artisan migrate
   ```

5. Install frontend dependencies and build assets (or run the dev server):

   ```bash
   npm install
   npm run build
   ```

   For development with hot reload:

   ```bash
   npm run dev
   ```

6. Open the app at [http://localhost:8081](http://localhost:8081).

Default Docker MySQL credentials match `.env.example` (`laravel` / `laravel`). These are **local development placeholders only**—do not use them in production.

## Running tests

Tests use an in-memory SQLite database (see `phpunit.xml`) and do not require MySQL:

```bash
docker compose exec php php artisan test
```

## Redis (queues and cache)

WorkBoard uses the **Redis** service from Docker Compose for queues and application cache (`QUEUE_CONNECTION=redis`, `CACHE_STORE=redis` in `.env.example`).

Inside Docker, PHP connects to Redis at **`redis:6379`** (the Compose service hostname). Port **6380** on your host maps to Redis only for tools outside the PHP container (for example `redis-cli` from the host).

The PHP image includes the **phpredis** extension (`REDIS_CLIENT=phpredis`). After changing the `Dockerfile`, rebuild:

```bash
docker compose build php && docker compose up -d
```

Start Redis (if not already running):

```bash
docker compose up -d redis
```

Verify connectivity (from the PHP container):

```bash
docker compose exec php php artisan tinker --execute="dump(Illuminate\Support\Facades\Redis::connection()->ping());"
```

You should see `"PONG"`.

Inspect Redis from the Redis container:

```bash
docker compose exec redis redis-cli PING
```

### Queue worker

Queued listeners and notifications (Stage 11) are processed from the **Redis** queue. Start a worker during local development:

```bash
docker compose exec php php artisan queue:work -v
```

Keep this process running in a separate terminal while you exercise task, comment, member, and attachment flows. Primary actions complete immediately; notification delivery runs asynchronously.

Failed jobs are stored in the `failed_jobs` table per Laravel’s default configuration.

### Project summary cache

The project show page caches derived statistics (task counts by status, member count) in Redis with explicit invalidation when tasks or members change. PHPUnit uses the **array** cache driver and does not require a running Redis instance.

## Project structure (high level)

- `app/` — HTTP layer, models, policies, form requests
- `resources/views/` — Blade templates (WorkBoard UI)
- `routes/web.php` — Web routes
- `database/migrations/` — Schema
- `tests/` — PHPUnit feature and unit tests
- `docker-compose.yml` — Local stack (Nginx, PHP, MySQL, Redis)

## Security note

Never commit `.env` or real API keys, passwords, or `APP_KEY` values. Use `.env.example` as a template with safe placeholders only.

## License

No license file is included in this repository yet. All rights reserved unless you add a license later.
