# About this project

CampusFlow is a Laravel and Filament school management system for Pakistani schools. It covers admissions and enquiries, students, classes and sections, the timetable, attendance, exams and result cards, fees collected at the counter, complaints, parent teacher meetings, documents, discipline, employees, leave, payroll, recruitment, performance evaluation and account payable. Several branches are run from one installation.

# Disclaimer
This software is provided "as-is", without any warranties. Please use with caution, and report bugs if you encounter them.

# Getting started

This application runs in Docker using FrankenPHP (a modern PHP application server built on Caddy) and MySQL 8.4.

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/), or Docker Engine with the Compose plugin

Nothing else is needed on your machine: PHP, Composer and Node run inside the containers.

## Quick Start

1. Clone the repository:

```bash
git clone https://github.com/mushtaq-ali20/CampusFlow.git
cd CampusFlow
```

2. Start the containers and wait until the application is ready:

```bash
docker compose up -d --wait
```

The first run builds the image and installs the dependencies, which takes a few minutes. You can follow the progress with `docker compose logs -f app`.

This starts three services:
- **app** — the FrankenPHP application server on `http://localhost:8080`
- **assets** — builds the frontend assets, and rebuilds them when you edit `resources/css` or `resources/js`
- **mysql** — a MySQL 8.4 database server on port `3306`

On startup, the app container creates `.env` from `.env.example` if it does not exist, installs the Composer dependencies, generates the application key and runs the database migrations.

3. Create the administrator account and the reference data:

```bash
docker compose exec app php artisan db:seed
```

4. Open `http://localhost:8080` in your browser and log in with `admin@campusflow.test` / `secret`.

5. Go to **Settings > General settings** to enter your institution's name, logo, language, timezone and currency, and to enable the features you need, such as certificates. These settings are stored in the database, not in `.env`.

## Sample data

To explore the application with sample courses, students and enrollments, rebuild the database with the test seeder instead. **This deletes all existing data.**

```bash
docker compose exec app php artisan migrate:fresh --seeder=TestSeeder
```

Then log in with `admin@campusflow.test` / `secret`.

## Changing the ports

If port `8080` or `3306` is already used on your machine, set other ports when starting the containers:

```bash
APP_PORT=8081 FORWARD_DB_PORT=3307 docker compose up -d --wait
```

You can also add these two variables to your `.env` file.

## Running commands inside the container

All PHP and artisan commands should be run inside the `app` container:

```bash
# Run tests (they use an in-memory SQLite database, not the MySQL data)
docker compose exec app php artisan test

# Run the linter
docker compose exec app ./vendor/bin/pint

# Run any artisan command
docker compose exec app php artisan <command>
```

## How the development setup works

Your checkout is mounted into the containers, so code changes are visible immediately. The `vendor` and `node_modules` directories are kept in Docker volumes rather than on your machine, which avoids slow file sharing on macOS and Windows. These two directories therefore stay empty on the host.

After pulling changes that modify `composer.lock` or `package-lock.json`, restart the containers to install the new dependencies:

```bash
docker compose restart
```

Set `AUTO_MIGRATE=false` in the `app` service environment if you prefer to run the migrations yourself.

## Stopping the environment

```bash
docker compose down          # Stop containers (data is preserved in Docker volumes)
docker compose down -v       # Stop containers and delete the database and dependency volumes
```

## Production image

`docker-compose.yml` is meant for development. A production image is built from the same `Dockerfile`:

```bash
docker build -t campusflow:latest .
```

## Serving several institutions

An install serves a single institution by default. To serve several, each with its own database, files and domains, set `LANDLORD_DB_DATABASE` to a separate database that holds the list of institutions (the *tenants*) and their domains. The `DB_*` user needs the right to create databases: each institution gets its own database on the same server.

```bash
# Create the landlord tables (and, later, run every migration after an upgrade)
php artisan campusflow:migrate --force

# Create an institution: its database, files folder, domain and first administrator, who receives a link to choose a password
php artisan campusflow:tenant:create "Example School" school.example.org --admin-email=admin@example.org --admin-firstname=Jane --admin-lastname=Doe

# Add another domain, make it the primary one (used in links and emails), or remove one
php artisan campusflow:tenant:domain "Example School" www.example-school.org --primary
php artisan campusflow:tenant:domain "Example School" school.example.org --remove

# List the institutions
php artisan campusflow:tenant:list

# Run a command for one institution, or for all of them without --tenant
php artisan tenants:artisan "campusflow:resync-coursetimes 12" --tenant=1
```

- Each domain must point to the server. Requests on a host name that matches no institution get a 404, unless `TENANCY_DEFAULT_TENANT` names an institution to use instead.
- One scheduler (`php artisan schedule:run` every minute) and one queue worker serve all institutions. Scheduled tasks run for each institution at its time, in its timezone. The queue and the cache are stored in the landlord database.
- The files of each institution are in `storage/tenants/<ID>`.
- Set the database with `DB_HOST`, `DB_PORT`, `DB_USERNAME` and `DB_PASSWORD`: `DB_URL` is refused, because a database named in it would win over the database of each institution. The file cache store is refused as well, because it would share cached data between institutions.
- `campusflow:migrate` goes on with the other institutions when the migrations of one fail, and then exits with an error.

A single-institution install needs none of this: its database also holds the landlord tables, and its one institution answers on any host name.

### Behind Traefik

One web container serves all institutions, so its routes are best kept in a [file provider](https://doc.traefik.io/traefik/providers/file/) configuration rather than in Docker labels: Traefik reloads the file when it changes, without recreating the container. When you add an institution, add its domains to the file; Traefik then requests their certificates.

```yaml
# traefik/dynamic/campusflow.yml, loaded with
#   --providers.file.directory=/dynamic
#   --providers.file.watch=true
http:
  routers:
    campusflow-example-school:
      rule: Host(`school.example.org`) || Host(`www.example-school.org`)
      entryPoints: [websecure]
      service: campusflow
      tls:
        certResolver: letsencrypt
  services:
    campusflow:
      loadBalancer:
        servers:
          - url: http://campusflow:80
```

Redirect HTTP to HTTPS on the `web` entry point (`--entrypoints.web.http.redirections.entrypoint.to=websecure`), or add an HTTP router with a `redirectScheme` middleware.

```yaml
services:
  campusflow:
    image: campusflow:latest
    # The server starts even when the migrations of one institution fail: the others stay online
    command: sh -c 'php artisan campusflow:migrate --force; php artisan optimize && frankenphp run --config /etc/caddy/Caddyfile'
    env_file: .env
    environment:
      SERVER_NAME: ":80"    # Traefik handles HTTPS
    volumes:
      - ./storage:/app/storage

  campusflow-scheduler:
    image: campusflow:latest
    command: sh -c 'while true; do php artisan schedule:run --no-interaction; sleep 60; done'
    env_file: .env
    volumes:
      - ./storage:/app/storage

  campusflow-worker:
    image: campusflow:latest
    command: php artisan queue:work --tries=3 --timeout=60
    env_file: .env
    volumes:
      - ./storage:/app/storage
```

- One web container, one scheduler and one worker serve all institutions. The storage folder must be writable by `www-data` (UID 33).
- The web container runs the migrations of every institution when it starts. If those of one institution fail, the error is in the container logs (`docker compose logs campusflow`); fix the cause, then run `php artisan campusflow:migrate --force` again.
- Set `TRUSTED_PROXIES=*` when the application container publishes no port and can only be reached through Traefik, so that the client IP addresses and HTTPS links are right.
- The database user creates one database per institution, named after `TENANCY_DATABASE_PREFIX` (`campusflow_` by default) and the institution. With MySQL, this grant lets it create and use the databases whose name starts with the prefix. Choose a prefix that no other database on the server uses, since the grant covers all of them, and name the landlord database with it, for example `campusflow_landlord`.

```sql
GRANT ALL PRIVILEGES ON `campusflow\_%`.* TO 'campusflow'@'%';
```

# Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for how to set up a development environment and submit changes.

To report a security problem, follow the [security policy](SECURITY.md) instead of opening a public issue.

# License

CampusFlow is open-source software licensed under the [MIT License](LICENSE).
