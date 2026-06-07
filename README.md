# WSV Admin

Admin panel for [Watersportvereniging Almere Centraal](https://local.leden.almerecentraal.nl), built with [Laravel](https://laravel.com) 13 and [Filament](https://filamentphp.com) 5. It manages members, their objects, rentals, contributions, and other day-to-day operations of the club.

The application is a member-facing administrative tool: member administration, invoicing and other bookkeeping for the windsurfing association.

## Table of contents

- [Requirements](#requirements)
- [Setup](#setup)
- [Common tasks](#common-tasks)
- [Project layout](#project-layout)
- [Quality assurance](#quality-assurance)
- [Using AI](#using-ai)
- [License](#license)

## Requirements

All commands in this project are run through the `Taskfile`. To use it you need:

- **Bash 5+** — the `Taskfile` checks the version on startup. On macOS the system `bash` is too old, install Bash 5 via Homebrew (`brew install bash`) and make sure your shell uses it.
- **Docker** with **Docker Compose** — the application and its Postgres 18 database run as containers.
- **curl** — used by the development proxy installer.
- **mkcert** — used to create a locally trusted SSL certificate for `local.leden.almerecentraal.nl`. Install with `brew install mkcert` and run `mkcert -install` once.
- A working `/etc/hosts` entry pointing `local.leden.almerecentraal.nl` to `127.0.0.1` (and `::1`). The `Taskfile` will tell you what to add on first run.
- _(Optional)_ the [Development Proxy](https://github.com/Enrise/DevelopmentProxy) — the `Taskfile` will fetch and start it for you the first time.

## Setup

Clone the repository and run the bootstrap task once. It builds the containers, copies `.env.example` to `.env`, installs Composer dependencies, runs migrations, and seeds the database.

```bash
git clone <repository-url> wsv-admin
cd wsv-admin
./Taskfile init
```

The first run downloads the development proxy and generates SSL certificates. After it finishes the application is reachable at <https://local.leden.almerecentraal.nl>.

To see the full list of available tasks at any time:

```bash
./Taskfile help
```

If you prefer a short `task` command instead of `./Taskfile`, you can install a wrapper script in `/usr/local/bin` (requires `sudo`):

```bash
./Taskfile shorthand
```

## Common tasks

All commands are invoked through the `Taskfile`. Run `./Taskfile help` for the canonical list.

### Project lifecycle

| Command | What it does |
| --- | --- |
| `./Taskfile init` | First-time setup: builds containers, installs dependencies, migrates and seeds the database. |
| `./Taskfile start` | Starts the Docker containers and the development proxy. |
| `./Taskfile stop` | Stops the Docker containers and disconnects the proxy. |
| `./Taskfile restart` | Restarts containers and the development proxy. |
| `./Taskfile update` | Re-runs `composer install` and migrations after pulling new changes. |
| `./Taskfile build` | Rebuilds the Docker images. |

### Database

| Command | What it does |
| --- | --- |
| `./Taskfile migrate` | Runs `php artisan migrate`. |
| `./Taskfile seed` | Runs `php artisan db:seed`. |
| `./Taskfile artisan migrate:fresh --seed` | Any `artisan` command can be passed through the task runner. |

### Inspecting the application

| Command | What it does |
| --- | --- |
| `./Taskfile shell` | Opens a shell inside the `app` container. |
| `./Taskfile logs` | Tails logs from all services (add a service name to filter). |
| `./Taskfile logs:app` | Tails logs from the `app` service only. |
| `./Taskfile exec "<command>"` | Runs a shell command inside the `app` container, e.g. `./Taskfile exec "ls -la"`. |
| `./Taskfile artisan <args>` | Runs an Artisan command, e.g. `./Taskfile artisan tinker --execute 'User::count();'`. |
| `./Taskfile composer <args>` | Runs Composer, e.g. `./Taskfile composer require vendor/package`. |


## Project layout

- `app/` — application code (HTTP layer, Filament resources, domain, infrastructure).
- `config/` — Laravel configuration files.
- `database/` — migrations, seeders, factories.
- `lang/nl/` — Dutch translations (the UI is Dutch-first).
- `public/` — web entry point.
- `routes/` — route definitions.
- `tests/` — PHPUnit feature and unit tests.
- `dev/` — local-only helpers (Traefik config, git hooks).
- `docker-compose.yml` / `Dockerfile` — container definitions.
- `Taskfile` — task runner (Bash, Enrise/Taskfile style).

## Quality assurance

The project enforces automated checks at multiple levels. Run them all with:

```bash
./Taskfile lint
```

This chains the tasks below. Fixers and formatters are wired in too.

| Task | Tool | Purpose |
| --- | --- | --- |
| `./Taskfile psr` | Composer | Verifies PSR-4 autoload compliance. |
| `./Taskfile rector` | Rector | Automated refactoring and code-modernisation suggestions. Pass `--dry-run` to inspect only. |
| `./Taskfile ecs` | EasyCodingStandard | Code-style checks. `./Taskfile fix` applies the fixes. |
| `./Taskfile stan` | PHPStan (Larastan) | Static analysis on the domain and infrastructure code. `./Taskfile stan:clear` clears the cache. |
| `./Taskfile test` | PHPUnit | Runs the test suite in parallel; pass filters as extra arguments, e.g. `./Taskfile test --filter=UserTest`. |
| `./Taskfile test:coverage` | PHPUnit + Xdebug | Runs tests with HTML and text coverage (report in `./coverage/phpunit/`). |

Every change is expected to ship with a test. Add or update tests in `tests/Feature` or `tests/Unit` and run `./Taskfile test --filter=...` to confirm.

## Using AI

I use AI tools to plan and generate code, but every line that lands in this repository is **fully reviewed and refactored by a human** before it is committed.

In practice that means:

- AI may propose a design, draft code, or suggest a refactor, but nothing is merged on autopilot.
- A human reads, understands, and rewrites the code until it matches the project's conventions, idioms, and quality bar (see [Quality assurance](#quality-assurance)).
- Tests are written or updated alongside the change, and the full lint + test suite is run before commit.
- AI suggestions that conflict with the established architecture, naming, or testing conventions of the codebase are rejected, regardless of how plausible they look.

If you contribute AI-assisted code, treat it as a draft, not a deliverable.

## License

This project is private and proprietary to Watersportvereniging Almere Centraal. Contact the maintainers before redistributing or building upon it.
