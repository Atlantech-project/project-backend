# F01 · Runtime and platform

Task origin: P1-A1 (Sprint 1), plus later config from P1-A2, P1-C1 and P1-B6. Facts checked against `develop` at `106b40e`. Paths are inside `project-backend/ATLANTECH/` unless stated.

## 1. What it does

This feature is the **ground the application stands on**: how the code runs on a developer's machine, how it runs "the way the port server will", how a request reaches PHP, and how the Laravel app is assembled from 15 modules.

It gives you:

- **Daily development:** PHP runs in **Laravel Herd** on your machine; Docker runs only MySQL and Redis.
- **Server shape:** the same API inside Docker (PHP-FPM behind NGINX). CI, the shared dev server and deployment use this shape.
- **A module-based layout** where each module registers its own middleware aliases, gates and console commands through its own service provider.
- **Configuration** for the database (strict MySQL, UTC), sessions, Sanctum, CORS, queues, cache and seeding.

It does **not** give you: TLS (the port server's NGINX will add TLS 1.3 in P6-R6), the Reverb realtime server (P5-N2), AI worker supervision, or a deployment. Those are later tasks.

## 2. How a request reaches the code

Server shape (`docker compose --profile server up -d`):

1. A client calls `http://localhost:8000/api/v1/...`. Port 8000 is published on `127.0.0.1` only, so nothing outside the machine can reach it.
2. The **nginx** container (`docker/nginx/default.conf`) receives it. Static files come from `./public` (mounted read-only). Everything else falls through `try_files` to `/index.php`.
3. nginx hands PHP requests to the **app** container with `fastcgi_pass app:9000` (PHP-FPM). `SCRIPT_FILENAME` is `$realpath_root$fastcgi_script_name`, which works because both containers see the same path `/var/www/html/public`.
4. `public/index.php` boots Laravel with `bootstrap/app.php`.
5. `bootstrap/app.php` builds the application: registers routing (`web`, `api` under the prefix `api/v1`, console, and the health route `/up`), then middleware, then the exception renderer.
6. Laravel reads `bootstrap/providers.php` and boots each **service provider**. The module providers register their middleware aliases, gates and commands (section 3, table P).
7. The router matches `routes/api.php`, runs the middleware stack (details in F11), and a controller answers.

Herd shape (daily work): Herd's own NGINX and PHP 8.4 serve the app at `http://atlantech.test`. The only containers running are `mysql` and `redis`.

## 3. Every piece of code and config

### 3.1 `docker-compose.yml`

Compose **profiles** decide which services start. A service with no profile always starts; a service with a profile starts only when that profile is requested.

| Service | Profile | What it is | Notes |
| --- | --- | --- | --- |
| `mysql` | none (always) | `mysql:8.4` | Published on `127.0.0.1:3306`. `MYSQL_ALLOW_EMPTY_PASSWORD=yes` (root, no password, local use only). Creates the database `atlantech`. Data lives in the named volume `mysql-data`. Health check: `mysqladmin ping`. |
| `redis` | none (always) | `redis:7.4-alpine` | Published on `127.0.0.1:6379`, no password. Command `redis-server --appendonly yes --appendfsync everysec` (AOF persistence). Data in volume `redis-data`. Health check: `redis-cli ping`. |
| `app` | `server` | Built from `Dockerfile` | PHP-FPM. `env_file: .env` with `required: false`. Its `environment:` forces `DB_HOST=mysql` and `REDIS_HOST=redis`, so inside Docker the app talks to the other containers even though `.env` says `127.0.0.1`. Starts only after MySQL and Redis are healthy. Health check opens port 9000. |
| `nginx` | `server` | `nginx:1.27-alpine` | Published on `127.0.0.1:8000`. Mounts `default.conf` and `public/` read-only. Starts after `app` is healthy. Health check requests `/up`. |
| `phpmyadmin` | `tools` | `phpmyadmin:5.2` | Published on `127.0.0.1:8080`, user `root`. Only when you ask for it. |

All services have `restart: unless-stopped` (NFR-REL-1: the port server must come back after a reboot).

The three ways to run it:

| Command | Starts | Used for |
| --- | --- | --- |
| `docker compose up -d` | mysql, redis | Daily development with Herd |
| `docker compose --profile server up -d --build` | + app, nginx | Frontend, Mobile and QA (no PHP needed), CI-like runs, the future dev server |
| `docker compose --profile tools up -d` | + phpmyadmin | Browsing the database |

### 3.2 `Dockerfile` (the `app` image)

1. `FROM php:8.4-fpm-bookworm`: PHP 8.4 with FPM.
2. `install-php-extensions pdo_mysql redis intl bcmath pcntl zip opcache`: the extensions the app and CI need (CI installs the same list).
3. Copies Composer, and `docker/php/uploads.ini`.
4. `WORKDIR /var/www/html`, then copies **only** `composer.json` and `composer.lock` and runs `composer install --no-scripts --no-autoloader`. This makes Docker reuse the dependency layer until those two files change. `ARG INSTALL_DEV=true` keeps dev tools; a deployment image can build with `INSTALL_DEV=false` (`--no-dev`).
5. `COPY . .`, then `composer dump-autoload --optimize`, `touch .env` (an empty file so Laravel does not warn; real settings come from environment variables) and `chown` of `storage` and `bootstrap/cache` to `www-data`.
6. `EXPOSE 9000`.

`.dockerignore` keeps real `.env` files, `.git`, `vendor`, `node_modules`, logs and caches out of the image.

### 3.3 `docker/nginx/default.conf`

| Directive | Why |
| --- | --- |
| `listen 80; server_name _;` | Plain HTTP for local, CI and dev-server use. TLS comes in P6-R6. |
| `root /var/www/html/public; index index.php;` | Laravel's public folder. |
| `client_max_body_size 20m;` | BAPLIE file uploads (P3-H1 will set the real per-file rule inside this limit). |
| `add_header X-Frame-Options "SAMEORIGIN"; add_header X-Content-Type-Options "nosniff";` | Basic security headers. |
| `location / { try_files $uri $uri/ /index.php?$query_string; }` | Real files are served directly, everything else goes to Laravel. |
| `error_page 404 /index.php;` | A missing route is answered by Laravel, so the 404 is the project's JSON problem response, not nginx's HTML page. |
| `location ~ ^/index\.php(/|$) { fastcgi_pass app:9000; ... fastcgi_hide_header X-Powered-By; }` | Hands PHP to the `app` container and hides the PHP version header. |
| `location ~ /\.(?!well-known).* { deny all; }` | Blocks dotfiles such as `.env` and `.git`. |

### 3.4 `docker/php/uploads.ini`

`upload_max_filesize = 20M` and `post_max_size = 21M`. They match nginx's 20 MB limit (a request slightly larger than the file is allowed for the form fields).

### 3.5 `.env.example` (copy to `.env`)

| Group | Keys | Notes |
| --- | --- | --- |
| App | `APP_NAME`, `APP_ENV=local`, `APP_KEY` (empty), `APP_DEBUG=true`, `APP_URL=http://atlantech.test`, `APP_LOCALE=en` | `APP_KEY` must be generated (`php artisan key:generate`). A 500 never shows a stack trace even with `APP_DEBUG=true` (F03). |
| Database | `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=atlantech`, `DB_USERNAME=root`, `DB_PASSWORD=` | Docker's `app` service overrides `DB_HOST`. The tests use a different database (F02). |
| AI workers | `WORKER_PART1_PASSWORD`, `…PART2…`, `…YARD…`, `…EDI…`, `…VALIDATE…`, `…PART3…` | Passwords for the six MySQL accounts the AI workers use (`atlantech:worker-accounts`, F05). Left empty in the repo. Never commit a value. |
| Session | `SESSION_DRIVER=database`, `SESSION_LIFETIME=120`, `SESSION_COOKIE=atlantech_session`, `SESSION_PATH=/`, `SESSION_DOMAIN=null`, `SESSION_ENCRYPT=false` | Web sessions live in the `sessions` table, idle timeout 120 minutes. |
| SPA | `SANCTUM_STATEFUL_DOMAINS=localhost:5173,atlantech.test`, `CORS_ALLOWED_ORIGINS=http://localhost:5173,http://atlantech.test` | Which front-end origins may use cookie sign-in (F07). |
| Redis, queue, cache | `REDIS_CLIENT=phpredis`, `REDIS_HOST=127.0.0.1`, `REDIS_PORT=6379`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis` | The `phpredis` extension (not the `predis` package). |
| Other | `BROADCAST_CONNECTION=log`, `FILESYSTEM_DISK=local`, mail and AWS keys (framework defaults) | Broadcasting is logged only until Reverb (P5-N2). |
| Seeding | `DEMO_USER_PASSWORD=` | Shared password of the demo users (F13). Empty means nobody can sign in as a demo user. |

### 3.6 `composer.json` and scripts

- **Requires:** PHP `^8.3`, `laravel/framework ^13.17`, `laravel/sanctum ^4.3`, `laravel/tinker`. **Dev:** Pest 5, Pint, Boost, Pail, Faker, Mockery, `symfony/yaml` (used by the OpenAPI drift test). PSR-4: `App\` to `app/`, `Database\Factories\`, `Database\Seeders\`, `Tests\`.
- **Scripts:**

| Script | Runs | Used for |
| --- | --- | --- |
| `composer setup` | install, copy `.env`, key, migrate, npm install and build | First-time setup |
| `composer dev` | `php artisan dev` | Laravel 13's built-in dev runner: starts `artisan serve`, `queue:listen --tries=1 --timeout=0` and `npm run dev` (Vite) |
| `composer test` | `config:clear`, then `php artisan test` | Full suite |
| `composer lint` | `pint --test` | Style check, same as CI |
| `composer contracts:schema` / `:status-vocabulary` / `:openapi` / `:role-matrix` | the four `atlantech:contracts-*` commands | Publish contract files to PortHub (F14) |
| `post-update-cmd` | `vendor:publish laravel-assets`, `boost:update` | Keeps Laravel Boost guidelines current; it may rewrite `CLAUDE.md`, `AGENTS.md` and `boost.json`: check `git status` after `composer update`. |

### 3.7 Configuration that is not the Laravel default

| File and key | Value | Why |
| --- | --- | --- |
| `config/app.php` `timezone` | `UTC` | All times are UTC (F03, CT-01). |
| `config/database.php` `mysql` | `strict => true`, `charset utf8mb4`, `collation utf8mb4_0900_ai_ci`, `engine InnoDB`, `timezone '+00:00'` | Strict mode makes MySQL refuse a value outside an ENUM list or a bad cast, so the database rules (F05) really bite. The session time zone is UTC. |
| `config/session.php` | driver `database`, lifetime 120, cookie `atlantech_session`, `same_site lax`, `http_only true` | Web sign-in uses a server session (F07). |
| `config/sanctum.php` | `stateful` from `SANCTUM_STATEFUL_DOMAINS`, `guard ['web']`, `expiration null` | SPA cookie authentication. A mobile token guard comes with P1-C3. |
| `config/cors.php` | `paths ['api/*', 'sanctum/csrf-cookie']`, origins from `CORS_ALLOWED_ORIGINS`, `supports_credentials true` | Lets the Vue app send cookies cross-origin. |
| `config/seeding.php` | `demo_port` (env `SEED_DEMO_PORT`, default on outside production) and `demo_user_password` | The production guard of the demo data (F13). |
| `config/queue.php`, `config/cache.php` | defaults are `database`, but `.env` sets `redis` | If `.env` is missing, cache and queue fall back to the database tables. |

Laravel defaults left untouched: `config/auth.php`, `cache.php` (apart from the `.env` driver), `filesystems.php`, `logging.php`, `mail.php`, `queue.php` (apart from the `.env` driver) and `services.php`. The default log channel is `stack` with the `single` file (`LOG_CHANNEL`, `LOG_STACK` in `.env.example`); the default filesystem disk is `local`.

### 3.8 `bootstrap/app.php` and `bootstrap/providers.php`

`bootstrap/app.php` is the Laravel 13 application builder:

- `withRouting(web, api, commands, health: '/up', apiPrefix: 'api/v1')`: the API prefix is set here, not in `routes/api.php`.
- `withMiddleware`: `statefulApi()` (Sanctum's SPA support), the `port.api` group and the `priority` list (the write chain, F11).
- `withExceptions`: `shouldRenderJsonWhen(api/* or expects JSON)` and the custom `ProblemRenderer` (F03).

`bootstrap/providers.php` lists the providers in order: `AppServiceProvider`, then `Shared`, `Access`, `License`, `MasterData`, `Environment`, `Berthing`, `Baplie`, `AiBridge`, `Recommendation`, `Plan`, `Yard`, `Dispatch`, `Execution`, `Learning`, `Realtime`. Registration is explicit (not auto-discovered).

### 3.9 The service providers, and what each does **today**

A provider's class comment states the module's intended job. Only some providers do anything yet; the rest are empty on purpose, ready for later tasks.

| Provider | Does today | Intended later (from its comment) |
| --- | --- | --- |
| `AppServiceProvider` | `JsonResource::withoutWrapping()`: Eloquent resources are returned as-is, not inside an automatic `{"data": ...}` wrapper. Lists build their own `{data, meta}` (F03). | none |
| `Shared` | Aliases middleware `version.token` (`EnsureRecordVersion`); registers `PublishOpenApi` and `PublishStatusVocabulary` commands. | Audit, transitions, `ResourceChanged` event, terminal-scope filters |
| `Access` | Aliases `ability`, `terminal.scope`, `app.version`; defines one **Gate per ability**; registers `PublishRoleMatrix`. | Users, devices, tokens (P1-C3, P2-E1…) |
| `License` | Aliases `license.write` (`EnsureLicenseAllowsWrite`, a pass-through today). | SETUP, FULL, READ_ONLY modes, check-in, plan limits |
| `AiBridge` | Registers `CreateWorkerAccounts` and `GenerateContractsSchema` commands. | Job publisher, run rows, completion consumer, mock workers |
| `MasterDataServiceProvider`, `EnvironmentServiceProvider`, `BerthingServiceProvider`, `BaplieServiceProvider`, `RecommendationServiceProvider`, `PlanServiceProvider`, `YardServiceProvider`, `DispatchServiceProvider`, `ExecutionServiceProvider`, `LearningServiceProvider`, `RealtimeServiceProvider` | Nothing (empty classes). | Their module's routes and services in later phases |

### 3.10 Leftovers from the Laravel skeleton (safe to ignore)

`routes/web.php` (`/` returns the welcome view), `routes/console.php` (`inspire`), `resources/{css,js,views}`, `package.json`, `vite.config.js` (Vite 8, Tailwind 4) and `public/build`. The product is an API only; nothing shipped uses them. `composer dev` still starts Vite because that is Laravel's default dev runner.

### 3.11 Agent tooling files

`CLAUDE.md` and `AGENTS.md` (instructions agents read automatically: Laravel Boost rules plus the ATLANTECH workflow section), `boost.json` (Boost settings for `claude_code`, `junie`, `opencode`), `opencode.json` (the Boost MCP server). They affect how AI assistants work in the repo, not the running app.

## 4. Why it is built this way

| Choice | Reason | Source |
| --- | --- | --- |
| Plain `app/Modules/<Module>` folders, not a modules package | No new dependency; the layer rule is enforced by Pest architecture tests instead. | P1-A1 answer 1 (Marwan, 2026-10-26) |
| Herd for PHP, Docker for MySQL and Redis | Fast daily work; the `server` profile gives the port-server shape without running it every day. | P1-A1 answers 2 and 5 |
| Cache and queue on Redis, sessions on the database | The AI jobs use Redis queues; sessions in the database survive a Redis restart. | P1-A1 answer 3 |
| Redis with AOF `everysec` | Closed decision DEC-11: AOF plus a sweeper as the safety net. | DEC-11 (Closed 2026-10-31) |
| Ports bound to `127.0.0.1`, empty MySQL root password | Local use only. A shared server must set real passwords before exposing anything. | `docker-compose.yml` header |
| `app` and `nginx` as opt-in profile | CI, the dev server (DEC-47) and deployment all want the same shape. | P1-A1; DEC-47 (Closed 2026-10-29) |
| Code baked into the image (no bind mount) | The image is what CI and the server run. After pulling new code you rebuild. | `Dockerfile`; README |
| `AiBridge` and `Realtime` as integration modules | Berthing must be able to enqueue an AI job; a mid-chain AiBridge would forbid it. | P1-A1 plan section 7, step 2 change |
| Strict MySQL and UTC | The database rules and time rules (F03, F05) depend on them. | CT-01, NFR-UX-4 |

## 5. How it connects

- **Everything** sits on this feature. F03 uses `bootstrap/app.php`'s exception renderer; F07 uses the session and Sanctum config; F11 uses the middleware group and priority list in the same file; F13 uses `config/seeding.php`.
- **Module layer rule.** `tests/Unit/Modules/ModuleArchitectureTest.php` encodes the layers listed in `A_orientation.md` section A4. It is the safety net for the whole code base.
- **CI** (F02) builds the same shape: MySQL 8.4 and Redis 7.4 service containers, PHP 8.4 with the Dockerfile's extensions.
- **Frontend and Mobile** can run the API with only Docker (`--profile server`), which is why the `app` image ships with dev tools (`INSTALL_DEV=true`) and seeds the demo port (F13).

## 6. Data

No tables of its own. It relies on the framework tables created by the first three migrations: `password_reset_tokens` (kept as the framework creates it; the app has no password-reset feature yet), `sessions` (web sessions), `cache` and `cache_locks`, `jobs`, `job_batches` and `failed_jobs`. MySQL's database `atlantech` is created by Compose; the test database `atlantech_testing` is created by the test helper (F02).

## 7. Tests

| Test | Proves | Run |
| --- | --- | --- |
| `tests/Feature/HealthCheckTest.php` | `GET /up` returns 200 (the route nginx and the container health checks use) | `vendor/bin/pest tests/Feature/HealthCheckTest.php` |
| `tests/Feature/InfrastructureTest.php` (group `infra`) | MySQL answers `select 1` and Redis stores and returns a value, against the real Compose services | `vendor/bin/pest --group=infra` (needs `docker compose up -d`) |
| `tests/Unit/Modules/ModuleArchitectureTest.php` | Each module uses only earlier layers; integration modules use only Shared; every module folder has a place in the rules | `vendor/bin/pest tests/Unit/Modules/ModuleArchitectureTest.php` |

`InfrastructureTest` hard-codes the database name `atlantech` (the one Compose creates) because `phpunit.xml` points tests at `atlantech_testing`. If Compose's database name changes, the test fails with a connection error, not a clear mismatch.

## 8. Gotchas and invariants

- **Rebuild after pulling.** The code is inside the image. `docker compose --profile server up -d --build` is needed after new code, and `php artisan migrate --seed` inside the container (`docker compose exec app php artisan migrate --seed`).
- **`APP_KEY` first.** Without it the app answers 500. Generate it once (`php artisan key:generate`; for Docker use `docker compose --profile server run --rm app php artisan key:generate --show` and paste it into `.env`).
- **Docker must be running for tests.** With MySQL down, the suite hangs instead of failing.
- **`DB_HOST` differs by shape.** `127.0.0.1` for Herd, `mysql` inside Docker (the compose `environment:` forces it). Do not "fix" `.env`.
- **A missing `.env` changes behaviour.** Defaults in `config/cache.php` and `config/queue.php` are `database`; only `.env` makes them `redis`.
- **Never use `env()` outside config files.** Laravel caches config; `env()` calls in application code stop working in production. Add a key to `.env.example`, read it in a `config/*.php`, then use `config('...')`.
- **Redis is not wiped by tests, MySQL's `atlantech_testing` is rebuilt by tests.** Do not point test settings at a database you care about (F02).
- **Empty module providers are intentional.** Do not delete them: `ModuleArchitectureTest` and `providers.php` expect all 15.
- **Compose comment is stale.** `docker-compose.yml` still says AOF is "a development setting until DEC-11 closes", but DEC-11 is Closed with AOF `everysec`; only the comment is old.
- **Ports are localhost-only on purpose.** A shared dev server (P6-R6) must set real MySQL and Redis passwords first.

## 9. How to extend it

**Add a module.** (1) Create `app/Modules/<Name>/` with `<Name>ServiceProvider.php` (extends `ServiceProvider`). (2) Add it to `bootstrap/providers.php`. (3) Place it in the `$layers` or `$integrationModules` lists in `tests/Unit/Modules/ModuleArchitectureTest.php`; the last test fails if you forget. (4) Respect the direction: it may use only earlier layers.

**Register a middleware alias, gate or command.** In the module's provider `boot(Router $router)`: `$router->aliasMiddleware('name', Class::class)`, `Gate::define(...)`, and `$this->commands([...])` guarded by `$this->app->runningInConsole()`.

**Add an environment setting.** Add it to `.env.example` and read it with `env()` in a `config/*.php` file; use `config()` everywhere else. If Docker must override it, put it under `app.environment` in `docker-compose.yml`.

**Add a PHP extension.** Add it to the `install-php-extensions` line in `Dockerfile` **and** to the `extensions:` list of the `tests` job in `.github/workflows/ci.yml`, so CI matches the image.

## 10. Try it

From `project-backend/ATLANTECH` (PowerShell or bash; `php` comes from Herd):

```bash
docker compose up -d
docker compose ps
```
Expect `mysql` and `redis` both `healthy`.

```bash
vendor/bin/pest tests/Feature/HealthCheckTest.php tests/Unit/Modules/ModuleArchitectureTest.php
vendor/bin/pest --group=infra
```
Expect all green; `--group=infra` talks to the real MySQL and Redis.

```bash
docker compose --profile server up -d --build
curl -i http://localhost:8000/up
curl -i http://localhost:8000/api/v1/nothing-here
```
Expect `200` for `/up` with the `X-Frame-Options` and `X-Content-Type-Options` headers, and a `404` problem response in JSON for the second call (`Content-Type: application/problem+json`).

```bash
php artisan list | grep -E "dev|atlantech:"
php artisan dev:list
php artisan route:list --except-vendor
```
Expect the five `atlantech:*` commands, three dev processes (`server`, `queue`, `vite`), and the routes `GET /` (welcome page), `GET api/v1/audit-logs`, `POST api/v1/auth/login`, `POST api/v1/auth/logout`, `GET api/v1/auth/me`, `POST api/v1/auth/change-password`. (`/up` and Sanctum's `/sanctum/csrf-cookie` come from the framework and are not listed under `--except-vendor`.)

## 11. Open points

- **Comment cleanup:** the AOF comment in `docker-compose.yml` (see gotchas).
- **TLS 1.3, real passwords and the shared dev server:** P6-R6 (DEC-47 target: a shared dev server updated on every merge to `develop`, a demo server on every release).
- **Reverb and the SSE endpoint:** `BROADCAST_CONNECTION=log` until P5-N2; the `Realtime` module is an empty shell.
- **PHP constraint:** `composer.json` allows `^8.3`, but CI, the image and the docs use 8.4, and the README badge says 8.3. Treat 8.4 as the version; the badge is stale.
- **Skeleton leftovers** (`welcome` route, Vite) can be removed in a cleanup task; nothing depends on them.
