# Halden v2: first release to economics.flexee.org

This is a one-time manual release. The automatic deploy workflow (`deploy-production`) is switched off
on GitHub, and its last five runs failed, so merging to `main` does **not** deploy anything right now.
After this first release works by hand, the workflow can be fixed and switched back on.

Everything below runs over SSH on the GoDaddy server. Copy each block, run it, and check the result before
moving on. If anything looks different from what's described, stop and send the output to Claude.

## 0. Before you start (Vikram)

1. **Merge PR #6** (`v2` into `main`). Mark it ready for review first, then merge. Nothing deploys.
2. **Make sure nobody needs the data on the current site.** Step 2 backs it up, and step 5 wipes it.

## 1. Look at what's there now

```bash
cd ~/public_html/economics.flexee.org
pwd
git status -sb | head -3
git remote -v
grep -E '^(APP_ENV|APP_DEBUG|APP_URL|DB_CONNECTION|DB_DATABASE|SESSION_DRIVER)=' .env
mysql -e "SELECT VERSION();" 2>/dev/null || true
```

What we want to see: a git checkout of `OneSmarterInc/flexee-economics`, and a `.env` with database
settings. If `git status` says "not a git repository", stop and send the output.

## 2. Back up the database

```bash
cd ~/public_html/economics.flexee.org
mkdir -p ~/backups
set -a; . ./.env; set +a
STAMP=$(date +%Y%m%d-%H%M)
mysqldump --single-transaction --routines -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  | gzip > ~/backups/flexee-economics-before-v2-$STAMP.sql.gz
gzip -t ~/backups/flexee-economics-before-v2-$STAMP.sql.gz && ls -lh ~/backups/flexee-economics-before-v2-$STAMP.sql.gz
```

The last line should print a file that's more than a few KB. Keep it.

## 3. Get the new code

```bash
cd ~/public_html/economics.flexee.org
php artisan down || true
git fetch origin main
git checkout --force main
git reset --hard origin/main
git log --oneline -1
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build
```

`git log` should show the merge of PR #6. The build should end with "built in".

## 4. Update `.env`

Open `.env` (`nano .env`) and make sure these lines say exactly this. Keep the existing `APP_KEY`,
`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`.

```
APP_NAME="Halden Energy"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://economics.flexee.org
DB_CONNECTION=mariadb
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
```

Delete any `SEED_DEMO_PASSWORD` line for now.

**Outgoing mail** is off until the `MAIL_*` lines point at a real mailer (`MAIL_MAILER=smtp` with the host, port,
username, password and `MAIL_FROM_ADDRESS` from GoDaddy or whichever service you use). Once they do, Halden emails
students their login when an instructor adds them (if the instructor ticks the box), sends deadline reminders a day,
four hours and an hour before each close (the scheduler line below runs `halden:remind`), lets instructors send a
reminder from the board, and password resets work from the login page. `HALDEN_MAIL=false` holds all of that while
you test the mailer with `php artisan tinker` first. Then `php artisan config:clear`.

The advisors stay switched off until an Anthropic API key is added. To switch them on, add
`ANTHROPIC_API_KEY=` with a key made for this server (not one shared in chat), then run
`php artisan config:cache`.

## 5. Rebuild the database

This deletes every table and builds the new ones. The backup from step 2 is the only copy of the old data.

The app refuses to wipe a database while it thinks it's in production (a safety catch), so the wipe line
says `APP_ENV=local` for that one command only. Everything after it runs as production.

```bash
cd ~/public_html/economics.flexee.org
php artisan config:clear
APP_ENV=local php artisan db:wipe --force
php artisan migrate --force
php artisan migrate:status | tail -5
```

## 6. Create your login and a class to play

Your own account, as admin (an admin can see every class):

```bash
php artisan halden:user vikram.sethi@onesmarter.com "Vikram Sethi" --role=admin
```

Copy the password it prints. It's shown only once.

A demo class so you can play as a team (Alpha, Bravo, Charlie, five students each, Quarter 1 open).
Pick a long password of your own, at least 16 characters:

```bash
SEED_DEMO_PASSWORD='choose-a-long-password-here' php artisan db:seed --force
```

The demo logins are `alpha1@example.test` to `alpha5@example.test` (and `bravo…`, `charlie…`), all with that
password. `alpha1` is the EVP, who marks the team ready. The faculty login is `faculty@example.test`.

A real class, with no demo logins: an admin creates the instructor's login and the class at `/admin` (or from the
command line: `halden:user` for the login, then `halden:class "MBA 7250 Spring 2027" --faculty=instructor@example.edu
--weeks=14 --teams=6`, with `--first-deadline="2027-01-14 17:00"` for a start other than next Thursday 5 pm Eastern
and `--weeks=7` for the 7-week class). The instructor then adds students on the faculty board's "Students and teams"
page: paste emails or upload a CSV (new logins get a one-time password shown once, downloadable as a list), or hand
out the class's join link so students make their own logins. "Form teams" shuffles everyone into teams of five with
seats; students can be moved, paused, removed or given a new password from the same page.

## 7. Finish

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
curl -sS -o /dev/null -w "%{http_code}\n" https://economics.flexee.org/login
```

The last line should print `200`.

## 8. Check in a browser

1. Open https://economics.flexee.org and log in as `alpha1@example.test`. You should see the first opening screen
   ("Welcome aboard").
2. Go through the opening, then save something on the oil fields page.
3. Log in as yourself in a private window. You should land on the faculty board for "Demo section".

**If the site shows a directory listing or a 404 for every page,** the subdomain's document root is wrong.
In cPanel → Domains, the document root for economics.flexee.org must end in
`public_html/economics.flexee.org/public`.

## If something goes wrong

To put the old data back (old code would also need `git checkout` of its last commit):

```bash
gunzip < ~/backups/flexee-economics-before-v2-STAMP.sql.gz | mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"
```
