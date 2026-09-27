# SK Scholarship Management System

A web application for the Sangguniang Kabataan (SK) college scholarship. It covers application, screening, interview, final decision, the cash claim and release for each semester, renewals, budgets, analytics and reports.

**The process is the source of truth:** [docs/PROCESS.md](docs/PROCESS.md) maps every step to its page, route and status, and states every rule and formula. [docs/TEST_CHECKLIST.md](docs/TEST_CHECKLIST.md) walks through it once end to end.

Built as a plain PHP MVC application (no framework) on top of PDO/MySQL, with Composer for PHP dependencies and npm for front-end libraries.

## Tech stack

- **Backend:** PHP 8+ (no framework), custom front-controller router in [public/index.php](public/index.php)
- **Database:** MySQL / MariaDB via PDO
- **PHP packages ([composer.json](composer.json)):**
  - `phpmailer/phpmailer` — outbound email (OTP codes, notifications)
  - `dompdf/dompdf` — PDF generation (reports, certificates)
- **Front-end packages ([package.json](package.json)):**
  - `chart.js` — analytics charts. The page loads a copy of `node_modules/chart.js/dist/chart.umd.min.js` from `public/assets/vendor/chart.js/`, so charts work without internet. After upgrading chart.js, copy that file again.
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
  controllers/   AuthController (A1), ApplicantController (A2–A9),
                 AdminController (router + dashboard, programs, users, settings, audit),
                 ScreeningController (B2), InterviewController (B3–B5),
                 FundsController (B6–B10), ReportController (reports + analytics)
  models/        Analytics, Applicant, Application, Budget, Document, Interview, Program,
                 Renewal, Report, Scholar, Term
  views/         admin/, applicant/, auth/, errors/, layouts/
bin/             send_reminders.php (interview reminder e-mails, command line only)
config/          config.php, constants.php (statuses, rubric, document types), database.php
core/            Auth, AuditLog, Helpers, Notification, Otp, Validator
database/        sk_scholarship.sql (schema + sample data), schema.sql (schema + reference lists), migrations/
docs/            PROCESS.md, CHANGE_PLAN.md, TEST_CHECKLIST.md
public/          index.php (front controller), assets/
storage/         private documents (documents/, term-documents/) + logs, outside the web root
```

Requests go through a single front controller, [public/index.php](public/index.php), with a `?r=` parameter (e.g. `/?r=admin/dashboard`). `AdminController` holds the list of admin routes; any route not in that list returns 404.

## User roles

| Role | Description |
|---|---|
| `admin` | Runs the whole process (`admin/*`) |
| `applicant` | Registers, applies and, once approved, is a scholar (`applicant/*`) — same account |

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
   `database/schema.sql` is the same structure without sample data (it includes the purok, school and course lists).
   A fresh `schema.sql` install has no admin yet: register an account at `/register`, verify it, then promote it:
   ```sql
   UPDATE users SET role_id = (SELECT id FROM roles WHERE role_name = 'admin') WHERE email = 'you@example.com';
   DELETE FROM applicants WHERE user_id = (SELECT id FROM users WHERE email = 'you@example.com');
   ```

   **Upgrading an existing database?** Don't re-import. **Back up first**, then run the files in [database/migrations/](database/migrations/) you haven't applied yet, oldest first. Each is meant to be run once. `2026_09_28_restructure.sql` maps the old data to the new process and then drops what's no longer used — read its header before running it.
   ```bash
   mysqldump -u root sk_scholarship > backup.sql
   mysql -u root -p sk_scholarship < database/migrations/2026_09_27_add_education_general_average.sql
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

6. **Ensure `storage/` is writable** by the web server — it holds `storage/documents` and `storage/term-documents` (private uploaded documents) and `storage/logs` (application/error logs) outside the public web root.

7. Visit `http://localhost/sk-scholarship/public/` in your browser.

8. **Schedule the reminder script** (see below) so applicants get an e-mail reminder before their interview.

## Scheduled reminders

[bin/send_reminders.php](bin/send_reminders.php) sends the **interview reminder** (in-app + e-mail). It goes to every applicant still marked *Scheduled* on an interview schedule that isn't cancelled, when the interview starts within the next `interview_reminder_hours` hours. You set that number in **Settings**; the default is 24, and 0 turns reminders off.

Each reminder is sent once. The script records `reminder_sent_at` before it sends, so running it again, or two runs overlapping, won't send duplicates. If a schedule's date, time or venue changes, its reminders are reset, and the applicants get a new reminder for the new time.

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
- Uploaded documents are stored under `storage/documents` and `storage/term-documents`, outside the web root, with random file names and content-type checks. They are only reachable through the app, which checks who owns each file. Both folders are in `.gitignore`.
- Every status change is checked on the server against its transition map (`Application::TRANSITIONS`, `Term::CLAIM_TRANSITIONS`, `Renewal::TRANSITIONS`); the pages only mirror it.
- Passwords are hashed; login attempts are rate-limited and accounts lock out after repeated failures (`MAX_LOGIN_ATTEMPTS`, `LOCKOUT_MINUTES` in [config/config.php](config/config.php)).
- Email/password-reset flows use one-time-password (OTP) verification (see [core/Otp.php](core/Otp.php) and the `verify-otp` / `verify-reset-otp` routes).
- All administrative actions are recorded via [core/AuditLog.php](core/AuditLog.php).
