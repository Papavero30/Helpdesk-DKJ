# On-Premise Email Setup

The application sends mail through Laravel's standard `smtp` mailer — no vendor SDK is
involved — so pointing it at an on-premise mail host is purely configuration. What is
*not* optional is the surrounding infrastructure: **two background processes must run on
the server or no email is ever delivered.**

## 1. Environment

```dotenv
APP_URL=http://123.231.143.155        # must be a real URL — it builds every link inside emails

MAIL_MAILER=smtp
MAIL_SCHEME=smtp                      # smtp = STARTTLS (port 587) | smtps = implicit TLS (port 465)
MAIL_HOST=mail.baro-q.my.id
MAIL_PORT=587
MAIL_USERNAME=helpdesk@baro-q.my.id
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=helpdesk@baro-q.my.id
MAIL_FROM_NAME="Helpdesk PT. Dunia Kimia Jaya"

MAIL_EHLO_DOMAIN=baro-q.my.id         # EHLO identity; required because APP_URL is a bare IP
MAIL_VERIFY_PEER=true                 # false only for a self-signed / mismatched TLS cert
MAIL_RATE_LIMIT_PER_SECOND=10         # ceiling for queued sends
```

After any `.env` change:

```bash
php artisan config:clear
php artisan queue:restart     # running workers hold the old config in memory
```

## 2. Queue worker (required)

Every class in `app/Notifications` implements `ShouldQueue`, and `QUEUE_CONNECTION=database`.
Mail is therefore **never** sent during the HTTP request — it is queued and picked up by a
worker. With no worker running, jobs pile up in the `jobs` table and nothing is delivered,
silently and without an error.

- **Linux:** install `deploy/helpdesk-queue.service` (instructions in the file header).
- **Windows Server:** run `php artisan queue:work --tries=25 --max-time=3600` as a service
  via [NSSM](https://nssm.cc/) or a Task Scheduler task set to "run whether user is logged
  on or not" with restart-on-failure.

Verify:

```sql
SELECT COUNT(*) FROM jobs;         -- should stay near 0; a growing number means no worker
SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 5;
```

## 3. Scheduler (required for reminders and digests)

`routes/console.php` schedules the SLA reminders, overdue alerts, comment digests and
SLA-pause auto-approval. These need a per-minute cron entry:

```cron
* * * * * cd /var/www/helpdesk && php artisan schedule:run >> /dev/null 2>&1
```

On Windows, create a Task Scheduler task repeating every 1 minute running
`php artisan schedule:run` in the project directory.

## 4. Verifying SMTP

Test the transport in isolation — `Mail::raw` sends synchronously, bypassing the queue, so
a broken SMTP connection surfaces immediately instead of failing inside a worker.

```bash
# Is the port even reachable from this server? (firewalls block 587/465 surprisingly often)
nc -zv mail.baro-q.my.id 587                             # Linux
Test-NetConnection mail.baro-q.my.id -Port 587           # Windows

php artisan tinker --execute="Mail::raw('on-prem test', fn(\$m) => \$m->to('you@example.com')->subject('SMTP test'));"
```

Then test the full path end-to-end (queue included) by triggering a real notification in
the app and watching `storage/logs/laravel.log` plus the worker log.

### Common failures

| Symptom | Cause |
| --- | --- |
| No error anywhere, `jobs` table grows | Queue worker not running (§2) |
| `Connection could not be established` / timeout | Outbound 587/465 blocked by firewall |
| `SSL: certificate verify failed` | Self-signed or mismatched cert → `MAIL_VERIFY_PEER=false` |
| `504 5.5.2 <[127.0.0.1]>: Helo command rejected` | `MAIL_EHLO_DOMAIN` unset and `APP_URL` invalid |
| `535 Authentication failed` | Wrong password, or host requires the full address as username |
| Mail sends but buttons link to `localhost` | `APP_URL` wrong (it is baked into every email link) |
