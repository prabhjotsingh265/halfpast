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

`make setup` builds the image, installs the PHP and JavaScript dependencies, creates `.env`, runs the migrations and adds a demo salon. When it finishes:

| Address                                   | What's there                                    |
| ----------------------------------------- | ----------------------------------------------- |
| http://localhost:8000                     | The app                                         |
| http://localhost:8000/kesar-beauty-lounge | The demo salon's booking page                   |
| http://localhost:5173                     | Vite dev server (hot reload)                    |
| http://localhost:8025                     | Mailpit, which catches every email sent locally |

Log in as `demo@halfpast.test` with the password `password` to see the salon from the owner's side. `make fresh` resets it.

On Windows, run the commands from a WSL 2 terminal.

## Payments in test mode

Deposits can be taken through Stripe and Razorpay (PayPal is on the way). Without any keys, a built-in test gateway is used, so the whole flow works out of the box.

To use the real gateways in test mode, add their **test** keys to `.env`. Never commit them:

| Setting                                  | Where it comes from                                            |
| ---------------------------------------- | -------------------------------------------------------------- |
| `STRIPE_KEY`, `STRIPE_SECRET`            | Stripe Dashboard → Developers → API keys (test mode)           |
| `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET` | Razorpay Dashboard → Account & Settings → API keys (test mode) |

Then switch them on under **Business settings → Payment methods**.

- **Stripe webhooks, locally:** `make up` starts Stripe's CLI by itself when the Stripe keys are in `.env`. It saves the signing secret and forwards test webhooks to the app. `make stripe-webhooks` does the same on its own.
- **Razorpay webhooks** need a public address, so locally the app settles Razorpay payments when the customer returns, and a check runs every minute for any it hasn't heard back about.

Test details:

- **Stripe:** card `4242 4242 4242 4242`, any future date, any CVC.
- **Razorpay:** Netbanking, any bank, then **Success** on the test bank page.

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
