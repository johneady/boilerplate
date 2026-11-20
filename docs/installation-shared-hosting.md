# Installing on shared hosting (cPanel)

Boilerplate runs on ordinary PHP hosting. You don't need SSH, Composer, Node or
a terminal. The download comes with everything already built, and a browser
installer does the setup.

## What you need

- **PHP 8.5 or newer.** In cPanel, choose it under *Select PHP Version* or
  *MultiPHP Manager*.
- **PHP extensions:** pdo_mysql, mbstring, openssl, tokenizer, xml, ctype,
  json, bcmath, fileinfo, zip, gd, curl, iconv, intl and sodium. Most hosts
  turn these on by default. The installer checks each one and tells you which
  are missing.
- **A MySQL or MariaDB database.** MariaDB 10.6+ or MySQL 8.0+ is recommended.
  The application is tested on MariaDB 11.8.
- **Apache with mod_rewrite**, or another web server that sends every request
  to `public/index.php`.
- **One cron job.** It sends email and runs every other background task. See
  [The cron job](#the-cron-job).
- **HTTPS** on your domain. Payments, webhooks and passkeys need it.

## What's in the download

| File | What it is |
| --- | --- |
| `boilerplate-vX.Y.Z-full.zip` | Unzip this on your computer. It contains the next three files. |
| `install.php` | The installer. |
| `readme.html` | A short summary of the installation steps. |
| the application archive | The application itself. The installer unpacks it, so don't extract it yourself. |
| `boilerplate-vX.Y.Z.update` | You need this only to update an existing installation. See [Updating](#updating). |

## Installing

1. **Create a database.** In cPanel, open *MySQL Database Wizard*. Create a
   database and a user with a strong password, then give the user *All
   privileges* on the database. Write down all three.
2. **Upload the files.** In *File Manager*, open your domain's document root
   (usually `public_html`). Upload `install.php` and the application archive
   from inside the full zip.
3. **Run the installer.** Visit `https://your-domain/install.php`. It takes you
   through these steps:
   - **Licence:** read and accept it.
   - **Requirements:** checks the PHP version, extensions and writable
     folders. Fix anything marked as failed, then press *Re-Test*.
   - **Database:** enter the details from step 1. The installer tests the
     connection before it goes on.
   - **Settings:** the site name, address and timezone. The address must be
     the `https://` address visitors will use.
   - **Email:** how the site sends mail. Your host gives you the SMTP details
     (in cPanel: *Email Accounts → Connect Devices*). You can change them later
     under Admin → Settings → Email.
   - **Administrator:** your name, email and password. You sign in to `/admin`
     with this account.
   - **Install:** unpacks the application, writes its configuration, and
     creates the database tables and your administrator account.
   - **Cron job:** shows the exact line to add. See below.
4. **Sign in.** Go to `https://your-domain/login` and sign in as the
   administrator you created. You land in the admin panel at `/admin`.

The installer tries to delete itself when it finishes. If `install.php` or the
archive is still in your document root afterwards, delete them yourself.

## The cron job

In cPanel, add this under *Advanced → Cron Jobs*, set to run **once per
minute**:

```text
* * * * * /usr/local/bin/php /home/USER/APP/artisan schedule:run >> /dev/null 2>&1
```

Copy the line from the installer's cron step rather than typing it. The
installer fills in your host's PHP path and the application's folder.

The cron job does two things:

- **It sends queued email.** Receipts, password resets, contact form alerts and
  similar messages are queued, then sent within about a minute. Shared hosts
  don't allow a queue worker that runs all the time, so every minute the cron
  job sends whatever is waiting, for up to 50 seconds.
- **It runs scheduled tasks.** It ends expired subscriptions, sends trial
  reminders, and cleans up old files and sessions.

If the cron job isn't running, no email is sent. You can check it at
`https://your-domain/health`: the `queue` line shows whether jobs are being
processed.

> **Running a real queue worker?** On a VPS where Supervisor (or similar) runs
> `php artisan queue:work`, add `QUEUE_MODE=worker` to the `.env` file. The
> cron job then leaves queued jobs to your worker. Keep the cron job either
> way, because it still runs the scheduled tasks.

## After installing

Go through the tabs under **Admin → Settings**:

1. **Business and Brand:** your name, contact details and logo, plus the title
   and description search engines show.
2. **Locale:** the site's timezone and how dates are shown.
3. **Email:** send a test message from the Email tab.
4. **Registration:** choose whether visitors may create accounts.
5. **Payments:** switch on Stripe or PayPal if you take payments. The Payments
   menu appears once a gateway is on.
6. **Diagnostics:** lists anything in the configuration that needs attention.

## Updating

Each release comes with a `boilerplate-vX.Y.Z.update` file. The file is
signed, so the updater refuses any file that was tampered with or didn't come
from us.

1. **Back up your database** from cPanel (*phpMyAdmin → Export*, or *Backup*).
   The updater makes its own backup first, but that backup includes the
   database only if the server has the `mysqldump` program. Many shared hosts
   don't, and then the updater's backup holds your files only.
2. In the admin panel, open **System → System update** and press **Launch
   updater**.
3. Upload the `.update` file, check what will change, and start the update.
   The site shows a maintenance page while the update runs. **Keep the tab
   open until it says the update is complete.**
4. Afterwards, the System update page lists every update applied, with its
   result.

If something fails partway through, the updater offers to restore its backup.

> **If the update was interrupted** (the tab was closed, or the connection
> dropped), the site may stay in maintenance mode. The updater won't start
> again for 30 minutes after an interrupted run. To retry sooner, delete
> `storage/app/updater/update.lock` in File Manager. Then launch the updater
> and apply the same file again. That finishes the update and brings the site
> back.

Updates keep your `.env` file, your uploaded files and your database.

## Troubleshooting

| Problem | What to check |
| --- | --- |
| A blank page or a "500" error right after installing | The PHP version (8.5+) and the extensions above, and that mod_rewrite is on in Apache. |
| No emails arrive | The cron job (see `/health`), then send a test message from Admin → Settings → Email. |
| Images or uploads fail | `storage/` and `bootstrap/cache/` must be writable (755 or 775). |
| Payments or webhooks fail | The site must be on `https://`, and its address in the settings must match exactly. |
| Anything else | Admin → Settings → Diagnostics, and the log files in `storage/logs/`. |
