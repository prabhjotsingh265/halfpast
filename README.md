# Halfpast

Booking pages for small businesses that can't double-book, even when two customers click the same slot in the same second.

Work in progress.

## Running it locally

All you need is Docker (Docker Desktop on macOS or Windows, Docker Engine with the Compose plugin on Linux) and `make`. PHP, Composer, Node and the database all run inside containers, so nothing else has to be installed.

```bash
git clone git@github.com:prabhjotsingh265/halfpast.git
cd halfpast
make setup
```

`make setup` builds the image, installs the PHP and JavaScript dependencies, creates `.env`, and runs the migrations. When it finishes:

| Address               | What's there                                    |
| --------------------- | ----------------------------------------------- |
| http://localhost:8000 | The app                                         |
| http://localhost:5173 | Vite dev server (hot reload)                    |
| http://localhost:8025 | Mailpit, which catches every email sent locally |

On Windows, run the commands from a WSL 2 terminal.

## Everyday commands

| Command                       | What it does                                  |
| ----------------------------- | --------------------------------------------- |
| `make up` / `make down`       | Start or stop every container                 |
| `make test`                   | Run the test suite                            |
| `make lint`                   | Check code style and run static analysis      |
| `make shell`                  | Open a shell in the app container             |
| `make artisan c="route:list"` | Run any artisan command                       |
| `make fresh`                  | Rebuild the database from scratch and seed it |
| `make logs`                   | Follow the logs of every container            |

`make help` lists the rest.

## Stack

PHP 8.4 · Laravel 13 · Vue 3 and TypeScript through Inertia 3 · Vite 7 · Tailwind CSS 4 · PostgreSQL 17 · Redis 8.2 · FrankenPHP · Docker

Every dependency is on a release line with a track record that's still supported: no pre-1.0 packages and no brand-new major versions.
