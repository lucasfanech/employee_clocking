# Clocking

Employee clocking sheet: clock in / clock out four times a day, get the balance of every day against
your weekly target, the cumulated balance of the week and the exact time you can leave.

Built with **Symfony 7.4 LTS** (PHP ≥ 8.2), **Doctrine ORM 3**, **Tailwind CSS 4** + **Stimulus**
(Webpack Encore 7) and shipped as a **Docker Compose** stack (nginx, PHP-FPM, MySQL 8.4, phpMyAdmin).

- [Quick start](#quick-start)
- [How the hours are computed](#how-the-hours-are-computed)
- [Development](#development)
- [Project layout](#project-layout)

## Quick start

Prerequisites: Docker with the Compose plugin.

```bash
git clone <repo> && cd employee_clocking
cp .env .env.local             # then set APP_SECRET (any long random string)
docker compose up -d --build
```

| Service    | URL                     | Notes                                              |
|------------|-------------------------|----------------------------------------------------|
| App        | http://localhost:8000   | nginx → php-fpm                                    |
| phpMyAdmin | http://localhost:8080   | user `clocking` / password `clocking` (see `.env`) |
| MySQL      | localhost:3306          | database `clocking` (dev override only)            |

The `php` container waits for MySQL, runs the migrations and, when `APP_ADMIN_PASSWORD` is set and no
account exists yet, creates the administrator `APP_ADMIN_EMAIL`. Otherwise open the site and use
**Create account**: the first registered account automatically gets the administrator role.

Accounts can also be created from the command line:

```bash
docker compose exec php bin/console app:user:create you@example.com --admin
```

Ports, database credentials and the bootstrap admin are configurable through `.env` / `.env.local`
(`APP_PORT`, `PHPMYADMIN_PORT`, `MYSQL_*`, `APP_ADMIN_EMAIL`, `APP_ADMIN_PASSWORD`).

`docker compose up` loads `compose.override.yaml`: source code bind-mounted, Xdebug available
(`XDEBUG_MODE=debug docker compose up`), a `node` service rebuilding the assets on change, MySQL exposed
on the host. For a production-like run use `docker compose -f compose.yaml up -d --build`
(compiled assets and dependencies are baked into the images).

## How the hours are computed

Everything is configured per user on the **Working hours** page and computed by
`App\Service\WorkTimeCalculator` (covered by unit tests):

| Rule | Formula |
|------|---------|
| Daily target | weekly hours ÷ 5 |
| Worked time of a day | (lunch out − morning in) + (evening out − afternoon in) |
| Daily balance (`+/-`) | worked − daily target, only once the four times are filled |
| Lunch-break rule | a break shorter than the required one is **not** credited: the missing minutes are removed from the balance. The required break is the standard one, or the *short* one on the selected days (e.g. 45 min on Fridays) |
| Cumulated | running sum of the daily balances, Monday → Friday |
| End of the day | morning in + daily target + required break; once lunch is clocked, the break actually taken is used (never less than the required one) |
| With the week balance | end of the day − balance cumulated on the previous days (ahead → leave earlier, behind → leave later) |
| Day off | the day is excluded from every computation |

Balances update live while typing (the browser posts the times to `/week/{year}/{week}/preview`;
the rules are never duplicated in JavaScript) and are stored with **Save**.

## Development

Without Docker you need PHP ≥ 8.2 (`intl`, `pdo_mysql`), Composer and Node ≥ 22.18.

```bash
composer install
npm install && npm run dev            # or: npm run watch
php bin/console doctrine:migrations:migrate
php bin/console app:user:create you@example.com --admin
symfony serve                         # or: php -S 127.0.0.1:8000 -t public
```

Useful commands (also available through `make`, run `make help`):

```bash
php bin/phpunit                        # unit + functional tests (SQLite, see .env.test)
php bin/console lint:container
php bin/console lint:twig templates
php bin/console make:migration         # after changing an entity
npm run build                          # production assets
```

## Project layout

```
assets/            Tailwind stylesheet and Stimulus controllers (week live preview, minesweeper…)
config/            Symfony configuration
docker/            nginx and PHP-FPM configuration, container entrypoint
migrations/        Doctrine migrations (MySQL)
src/Controller     Landing, registration, security, week grid, schedule, profile, admin
src/Entity         User, WorkSchedule (rules), WorkDay (four times of a day or a day off)
src/Service        WorkTimeCalculator (the rules), WeekSummaryBuilder, UserManager
src/Time           WeekReference (ISO week), DayTimes, Duration helpers
templates/         Twig templates (Tailwind form theme in templates/form)
tests/             PHPUnit unit and functional tests
```
