# SK Scholarship Management System

A web application for managing the Sangguniang Kabataan (SK) college scholarship program — applicant registration and onboarding, application review, document verification, interview scheduling, scholar and budget tracking, renewals, and reporting.

Built as a plain PHP MVC application (no framework) on top of PDO/MySQL, with Composer for PHP dependencies and npm for front-end libraries.

## Tech stack

- **Backend:** PHP 8+ (no framework), custom front-controller router in [public/index.php](public/index.php)
- **Database:** MySQL / MariaDB via PDO
- **PHP packages ([composer.json](composer.json)):**
  - `phpmailer/phpmailer` — outbound email (OTP codes, notifications)
  - `dompdf/dompdf` — PDF generation (reports, certificates)
- **Front-end packages ([package.json](package.json)):**
  - `chart.js` — analytics charts. The page loads a copy of `node_modules/chart.js/dist/chart.umd.min.js` from `public/assets/vendor/chart.js/`, so charts work without internet. After upgrading chart.js, copy that file again.
  - `@fullcalendar/core`, `@fullcalendar/daygrid` — interview scheduling calendar. The calendar page still loads the full FullCalendar bundle from the CDN, because it uses the week, day and list views, which these two packages don't include.
- **Server:** Apache (XAMPP) with `mod_rewrite`

## Requirements

- PHP 8.1+ with the `pdo_mysql`, `mbstring`, `fileinfo`, and `openssl` extensions (checked at boot in [config/config.php](config/config.php))
- MySQL/MariaDB
- Composer
- Node.js/npm (only needed if you want to manage the front-end packages in `node_modules`; they are already checked into the repo)
- Apache with `mod_rewrite` enabled (e.g. via XAMPP)

## Project structure

```
app/
  controllers/   AdminController, ApplicantController, AuthController
  models/        Analytics, Applicant, Application, Budget, Document, Interview, Program, Scholar
  views/         admin/, applicant/, auth/, errors/, layouts/
bin/             send_reminders.php (scheduled e-mail reminders, command line only)
config/          config.php, constants.php, database.php
core/            Auth, AuditLog, Helpers, Notification, Otp, Router, Validator
database/        sk_scholarship.sql (schema + seed data), schema.sql (empty schema), migrations/ (upgrades)
public/          index.php (front controller), assets/, uploads/ (legacy)
storage/         private uploaded documents + logs (outside the web root)
```

Requests are routed through a single front controller, [public/index.php](public/index.php), via a `?r=` query parameter (e.g. `/?r=admin/dashboard`), and dispatched to `AdminController`, `ApplicantController`, or `AuthController` based on the signed-in user's role.

## User roles

Defined in the `roles` table (see [database/sk_scholarship.sql](database/sk_scholarship.sql)):

| Role | Description |
|---|---|
| `admin` | Full administrative access |
| `staff` | Limited administrative access |
| `official` | Decision-maker / analytics viewer |
| `applicant` | College student applying for/holding a scholarship |

`admin`, `staff`, and `official` are routed to the `admin/*` area; `applicant` is routed to the `applicant/*` area.

## Setup

1. **Clone the repo into your XAMPP `htdocs` folder** (or symlink it there) so it's served as `sk-scholarship`.

2. **Install dependencies:**
   ```bash
   composer install
   npm install   # optional, node_modules is already committed
   ```
   `composer install` is **required**. Deployments should not rely on a shipped `vendor/` folder, and without it e-mail (PHPMailer: OTP codes, notifications, reminders) and PDF reports (DomPDF) do not work.

3. **Create the database and import the schema:**
   ```bash
   mysql -u root -p -e "CREATE DATABASE sk_scholarship CHARACTER SET utf8mb4"
   mysql -u root -p sk_scholarship < database/sk_scholarship.sql
   ```
   **Upgrading an existing database?** Don't re-import. Run the files in [database/migrations/](database/migrations/) that you haven't applied yet, oldest first. Each one only adds columns and is meant to be run once:
   ```bash
   mysql -u root -p sk_scholarship < database/migrations/2026_09_27_add_reminder_sent_at.sql
   ```

4. **Create your `.env` file** in the project root by copying [.env.example](.env.example) (`cp .env.example .env`) and filling in real values. This file is read by [config/config.php](config/config.php) and is already in `.gitignore`. Never commit it:
   ```env
   APP_ENV=development
   BASE_URL=http://localhost/sk-scholarship/public

   DB_HOST=127.0.0.1
   DB_NAME=sk_scholarship
   DB_USER=root
   DB_PASS=

   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=465
   MAIL_USER=your-email@gmail.com
   MAIL_PASS=your-app-password
   MAIL_FROM=your-email@gmail.com
   MAIL_FROM_NAME="SK Scholarship"
   MAIL_ENCRYPTION=ssl
   ```
   `APP_ENV` must be exactly `development` to enable debug output and error display; anything else (including unset) is treated as production. **On a deployed server, set `APP_ENV=production`.** Development mode shows stack traces, SQL errors and file paths to visitors.

5. **Point Apache/XAMPP at the project** and make sure `mod_rewrite` is enabled — [.htaccess](.htaccess) routes all requests through `public/`, blocks direct access to `app/`, `bin/`, `core/`, `config/`, `database/`, `vendor/`, and `storage/`, and denies hidden files.

6. **Ensure `storage/` is writable** by the web server — it holds `storage/documents` (private uploaded documents) and `storage/logs` (application/error logs) outside the public web root.

7. Visit `http://localhost/sk-scholarship/public/` in your browser.

8. **Schedule the reminder script** (see below) so applicants and scholars get e-mail reminders.

## Scheduled reminders

[bin/send_reminders.php](bin/send_reminders.php) sends two kinds of reminders, both as an in-app notification and an e-mail:

- **Interview reminders** go to applicants whose interview assignment is *Scheduled* or *Confirmed*, on a schedule that is not cancelled, when the interview starts within the next `interview_reminder_hours` hours. You set this in **System Settings**; the default is 24, and 0 turns interview reminders off.
- **Renewal reminders** go to *Active* scholars whose renewal record is still *For Review*.

Each reminder is sent once. The script records `reminder_sent_at` before it sends, so running it again, or two runs overlapping, won't send duplicates. If an interview is moved to a new date or start time, its reminder is reset, and the applicant gets a new reminder for the new time.

The script only runs from the command line. Run it **every hour**:

**Windows (XAMPP), Task Scheduler:**
1. Open *Task Scheduler*, then choose *Create Task…*
2. *General* tab: name it `SK Scholarship reminders` and choose *Run whether user is logged on or not*.
3. *Triggers* tab: *New…*, *Daily*, then tick *Repeat task every: 1 hour* for a duration of *Indefinitely*.
4. *Actions* tab: *New…*, *Start a program*:
   - Program/script: `C:\xampp\php\php.exe`
   - Add arguments: `C:\xampp\htdocs\sk-scholarship\bin\send_reminders.php`
   - Start in: `C:\xampp\htdocs\sk-scholarship`
5. Save. Then right-click the task and choose *Run* to test it. MySQL must be running.

You can also run it by hand: `C:\xampp\php\php.exe C:\xampp\htdocs\sk-scholarship\bin\send_reminders.php`

**Linux, cron** (`crontab -e` as the web server user or a user who can read `.env`):
```cron
0 * * * * /usr/bin/php /var/www/sk-scholarship/bin/send_reminders.php >> /var/www/sk-scholarship/storage/logs/reminders.log 2>&1
```

## Security notes

- `.env` contains live database and email credentials. It is listed in `.gitignore`; commit only [.env.example](.env.example), which has placeholder values, and never commit real secrets.
- Deployed servers must run with `APP_ENV=production`.
- Uploaded applicant documents are stored under `storage/documents`, outside the web root, and are only reachable through application code — not `public/uploads`, which is a legacy read-only fallback.
- Passwords are hashed; login attempts are rate-limited and accounts lock out after repeated failures (`MAX_LOGIN_ATTEMPTS`, `LOCKOUT_MINUTES` in [config/config.php](config/config.php)).
- Email/password-reset flows use one-time-password (OTP) verification (see [core/Otp.php](core/Otp.php) and the `verify-otp` / `verify-reset-otp` routes).
- All administrative actions are recorded via [core/AuditLog.php](core/AuditLog.php).
