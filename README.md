# WorkBoard

WorkBoard is a project-management web application built with **Laravel** and **Blade**. It supports collaborative projects, tasks, comments, tags, file attachments, in-app notifications (with queued delivery), Redis-backed caching, and a JSON **REST API** authenticated with **Laravel Sanctum**.

## Features

- **Authentication** — register, log in, log out (session-based web UI)
- **Projects** — CRUD, search, sort, pagination; owned and shared via membership
- **Members** — project owners can add/remove members; roles (owner / member) and policies enforce access
- **Tasks** — project-scoped CRUD, assignees, status, priority, due dates, search/filters/sort/pagination on the project page
- **Comments** — on tasks, with author and timestamps
- **Tags** — project-scoped; attach to tasks
- **Attachments** — upload, download, image preview, delete (stored on the `local` disk)
- **Notifications** — database notifications for task/comment/member/attachment events; queued listeners; safe “open” navigation with stale-item handling
- **Cache** — Redis project summary statistics on project show, with explicit invalidation
- **REST API** — `/api` routes mirroring core resources; Bearer token via Sanctum
- **Demo dataset** — optional rich local seed (two configurable login users plus sample team data); see [Demo seeding](#demo-seeding) below

## Tech stack

- PHP 8.3+ / Laravel 13
- MySQL (Docker Compose)
- Redis — queues and cache (`QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`)
- Blade, Tailwind CSS, Vite
- PHPUnit

## Requirements

- [Docker](https://docs.docker.com/get-docker/) and Docker Compose (recommended)
- Or: PHP 8.3+, Composer, Node.js/npm, and MySQL locally

## Local setup (Docker)

1. Clone the repository and enter the project directory.

2. Copy the environment file and install dependencies:

   ```bash
   cp .env.example .env
   docker compose run --rm composer install
   docker compose exec php php artisan key:generate
   ```

3. Configure demo login users in `.env` (see [Demo seeding](#demo-seeding)) if you plan to seed sample data.

4. Start services:

   ```bash
   docker compose up -d
   ```

   The PHP entrypoint aligns container permissions with your bind mount. Prefer `docker compose exec -u www-data php php artisan …` over running Artisan as root.

5. Run migrations:

   ```bash
   docker compose exec php php artisan migrate
   ```

6. Build frontend assets:

   ```bash
   npm install
   npm run build
   ```

   For development with hot reload: `npm run dev`

7. Open [http://localhost:8081](http://localhost:8081).

Default Docker MySQL credentials in `.env.example` (`laravel` / `laravel`) are for **local development only**.

## Demo seeding

Local demo accounts and a rich sample dataset are configured via environment variables (never commit real passwords to Git):

```env
DEMO_USER_ONE_EMAIL=
DEMO_USER_ONE_PASSWORD=
DEMO_USER_TWO_EMAIL=
DEMO_USER_TWO_PASSWORD=
```

Values are read through `config/demo.php`. To reset the database and load the demo dataset (destructive):

```bash
docker compose exec -T php php artisan migrate:fresh --seed
```

Additional seeded team users (`@workboard.demo`) appear as project members but are not login accounts unless you create credentials separately.

## Running tests

PHPUnit uses in-memory SQLite (`phpunit.xml`) and does not require MySQL:

```bash
docker compose exec php php artisan test
```

## Redis (queues and cache)

Inside Docker, PHP connects to Redis at **`redis:6379`**. The PHP image includes **phpredis** (`REDIS_CLIENT=phpredis`).

Verify connectivity:

```bash
docker compose exec php php artisan tinker --execute="dump(Illuminate\Support\Facades\Redis::connection()->ping());"
```

### Queue worker

Queued listeners and notifications require a worker during local development:

```bash
docker compose exec php php artisan queue:work -v
```

Failed jobs use Laravel’s default `failed_jobs` table.

### Project summary cache

Project show displays cached task/member summary statistics in Redis, invalidated when tasks or members change. Tests use the **array** cache driver.

## REST API (Sanctum)

JSON API under `/api` with personal access tokens. Web session login is unchanged.

### Obtain a token

```bash
curl -s -X POST http://localhost:8081/api/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"you@example.com","password":"your-password"}'
```

Use the returned `token` as `Authorization: Bearer …` on subsequent requests.

### Representative endpoints

| Method | Path |
|--------|------|
| `GET` | `/api/user` |
| `POST` | `/api/logout` |
| `GET` | `/api/projects` |
| `POST` | `/api/projects` |
| `GET` | `/api/projects/{project}/tasks` |
| `POST` | `/api/projects/{project}/tasks` |
| `GET` | `/api/projects/{project}/tasks/{task}/comments` |
| `GET` | `/api/projects/{project}/tags` |

Task list query parameters: `search`, `status`, `priority`, `tag`, `sort`, `page`, `per_page` (max 100).

## Postman

If present in your checkout, API collections and example environments live under `postman/`. Use a **local** environment file (gitignored) for real credentials; keep committed example files free of secrets.

## Project structure (high level)

- `app/` — HTTP layer, models, policies, events, listeners, API resources
- `resources/views/` — Blade UI
- `routes/web.php`, `routes/api.php` — Web and API routes
- `database/migrations/`, `database/seeders/` — Schema and demo seeders
- `tests/` — PHPUnit
- `docker-compose.yml` — Nginx, PHP, MySQL, Redis

## Security note

Never commit `.env`, API tokens, or real passwords. Use `.env.example` placeholders only. Demo user passwords belong in local `.env` only.

## License

No license file is included unless you add one later.
