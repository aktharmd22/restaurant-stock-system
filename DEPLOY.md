# Putting this on Hostinger

Written for Hostinger shared hosting with SSH. Follow it top to bottom once;
after that, updating is the short list at the end.

---

## Before you start

You need:

- A Hostinger plan with **SSH access** (hPanel → Advanced → SSH Access)
- **PHP 8.2 or newer** (hPanel → Advanced → PHP Configuration)
- A **MySQL database**, user and password (hPanel → Databases → Management)
- Node and npm **on your own computer** — not on the server

Shared hosting has no Node, so assets are built locally and uploaded. That is
normal and nothing is lost by it.

---

## 1. Build the assets on your computer

```bash
npm install
npm run build
```

This writes `public/build/`. That folder must be uploaded with everything else.

---

## 2. Upload the app

The application does **not** go in `public_html`. Only the contents of
`public/` do. Everything else sits above the web root where nobody can request
it directly.

```
~/domains/yoursite.com/
├── app/                 <-- the whole project EXCEPT public/
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── artisan
│   └── .env
└── public_html/         <-- the CONTENTS of public/
    ├── build/
    ├── icons/
    ├── index.php
    ├── sw.js
    ├── favicon.svg
    └── .htaccess
```

Upload by SSH (fastest) or the hPanel File Manager.

### Point index.php at the app

Edit `public_html/index.php` and change the two `__DIR__` paths so they look one
level up and into `app`:

```php
require __DIR__.'/../app/vendor/autoload.php';

$app = require_once __DIR__.'/../app/bootstrap/app.php';
```

---

## 3. Install and set up

Over SSH:

```bash
cd ~/domains/yoursite.com/app

composer install --no-dev --optimize-autoloader

cp .env.example .env
php artisan key:generate
```

Now edit `.env`:

```ini
APP_NAME="Your Restaurant"
APP_ENV=production
APP_DEBUG=false                 # never true on a live site
APP_URL=https://yoursite.com
APP_TIMEZONE=Asia/Kolkata

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=public

BROADCAST_CONNECTION=null       # see step 6 to turn on instant alerts
```

Then create the tables and the starting data:

```bash
php artisan migrate --force
php artisan db:seed --force     # demo data - see the warning below
php artisan storage:link
```

**About the demo data.** `db:seed` creates four branches, eight people and 60
items with a month of invented history, so you can show the app to someone the
day you install it. For a real restaurant, seed only the parts you want:

```bash
php artisan db:seed --class=RoleSeeder --force        # required
php artisan db:seed --class=SettingsSeeder --force    # required
php artisan db:seed --class=BranchSeeder --force      # edit first, or skip
```

Then create your real branches, people and items in Settings.

---

## 4. Cache the config

Do this last, and again after any `.env` change:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 5. Set up the two cron jobs

hPanel → Advanced → Cron Jobs. Pick the shortest interval your plan allows;
every minute is ideal, but nothing here breaks at fifteen.

**Scheduled work** (chases requests nobody has opened, checks the ledger nightly):

```
cd ~/domains/yoursite.com/app && php artisan schedule:run >/dev/null 2>&1
```

**Queue worker** (PDFs, spreadsheets, image resizing):

```
cd ~/domains/yoursite.com/app && php artisan queue:work --stop-when-empty --max-time=55 --tries=3 >/dev/null 2>&1
```

`--stop-when-empty` matters: it exits instead of running forever, which is what
shared hosting expects.

**The ask → approve → send → receive loop does not depend on either cron.** It
all happens inside the web request. Cron only carries the slow, unimportant
work, so a fifteen-minute schedule costs you nothing that matters.

---

## 6. Instant alerts (optional but worth it)

Without this, the app checks for changes every 12 seconds. Everything works;
alerts are just a few seconds slower.

To make them instant, create a free app at [pusher.com](https://pusher.com)
(Channels → Create app, pick the region nearest you), then in `.env`:

```ini
BROADCAST_CONNECTION=pusher

PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_key
PUSHER_APP_SECRET=your_secret
PUSHER_APP_CLUSTER=ap2

VITE_BROADCAST_CONNECTION="${BROADCAST_CONNECTION}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

The `VITE_` values are read at build time, so **rebuild and re-upload
`public/build/`** after changing them, then `php artisan config:cache`.

The free tier covers a restaurant of this size comfortably.

---

## 7. Text messages (optional)

Password reset codes and the half-hour "nobody has looked at this" reminder are
written to the log until a real provider is connected.

To send them for real, write a class implementing `App\Services\Sms\SmsSender`
and bind it in `app/Providers/AppServiceProvider.php`, replacing `LogSmsSender`.
Nothing else in the app changes.

---

## 8. Check it worked

```bash
php artisan about                       # config and drivers
php artisan stock:rebuild-balances --check   # says the ledger and balances agree
```

Then in a browser:

1. Sign in and check the restaurant name is yours (Settings → Restaurant name)
2. Open the app on a phone and add it to the home screen
3. Send a request from a branch account and approve it from the admin account

**Demo sign-ins** (only if you ran the full seeder — delete these accounts before
going live):

| Who | Sign in with | Password |
|---|---|---|
| Owner | `9000000001` | `password` |
| Main store admin | `9000000002` | `password` |
| Branch manager | `9000000003` | `password` |
| Branch staff | `9000000004` | `password` |

---

## Updating later

On your computer:

```bash
npm run build
```

Upload the changed files and `public/build/`, then over SSH:

```bash
cd ~/domains/yoursite.com/app
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

## When something looks wrong

**A number looks wrong.** The ledger is the truth and the balances are only a
cache of it:

```bash
php artisan stock:rebuild-balances --check   # report differences
php artisan stock:rebuild-balances           # fix them
```

**A blank white page.** Set `APP_DEBUG=true`, reload, read the error, then set
it straight back to `false`. Also check `storage/logs/laravel.log`.

**"Permission denied" errors.** `storage/` and `bootstrap/cache/` must be
writable:

```bash
chmod -R 775 storage bootstrap/cache
```

**Alerts are slow.** That is the polling fallback doing its job. Set up Pusher
(step 6) to make them instant.

**Sound does not play.** Browsers block audio until someone taps the screen. The
app shows an amber marker in the header when that is the case — tap anywhere
once and it works for the rest of the session.
