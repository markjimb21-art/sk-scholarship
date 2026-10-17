# Tests

Everything here runs against a **separate database, `sk_scholarship_test`**, rebuilt from `database/schema.sql` +
`tests/seed.sql`, and a temporary upload folder. The real `sk_scholarship` database and `storage/` are never touched.
The test tools refuse to run if `DB_NAME` is anything else.

## Run

From the project root (XAMPP's MySQL must be running):

```
composer --working-dir=tests install               # once: installs PHPUnit into tests/vendor (not committed)
tests/vendor/bin/phpunit -c tests/phpunit.xml      # unit + database + HTTP tests
php bin/smoke_test.php                             # every route, signed in as each kind of user
```

`sk_scholarship_test` is created automatically on the first run, if the MySQL user in `.env` may create databases
(XAMPP's `root` may). Otherwise create it once:
`mysql -u root -e "CREATE DATABASE sk_scholarship_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"`.

## What is covered

| Suite | File | What it proves |
|---|---|---|
| unit | `ApplicationTransitionTest` | the allowed status moves (docs/PROCESS.md §1), every action × every status |
| unit | `DocumentValidationTest` | upload checks on the file CONTENT: PDF/JPG/PNG only, not empty, at most 5 MB, fakes refused |
| unit | `FormatHelpersTest` | `peso()` and `fmt_date()`/`fmt_datetime()`/`fmt_long_date()` give exactly what the copies they replaced gave |
| unit | `RequestHelpersTest` | `get_int()`, `post_str()`, `get_id()`, `post_bool()`… and `paginate()` give what the expressions they replaced gave |
| unit | `PasswordRuleTest` | 8+ characters and 1 special character; the exact messages; Validator uses the same rule |
| integration | `ApplicationStatusTest` | refused and stale status changes; approval blocked when the slots are full |
| integration | `AnalyticsTest` | projected scholars, committed, projected need, surplus, extra fundable, attendance, no-show, scores, on-time rate, against numbers worked out by hand from the seed |
| integration | `TermProgramMigrationTest` | `2026_10_18_term_program.sql` run on the old table shape: semesters and renewals get the scholar's program, NOT NULL + foreign keys, the 2nd Semester opened only for an Active scholar stuck after a Released 1st Semester, one program per academic year |
| integration | `TermProgramTest` | a semester belongs to a program: Semester 1 in the application's program; a renewal's semester in the program it went into (not the origin) |
| integration | `BudgetTest` | suggested budget = Active scholars × (amount per semester × 2) |
| integration | `InterviewSuggestionsTest` | slot suggestions: existing schedules first, business hours, Mon–Sat, after the application period, no holidays, no other program's day, no overlaps |
| integration | `ReminderScriptTest` | `bin/send_reminders.php` run for real: interview, deadline and daily-digest reminders each go out once; a second run sends nothing |
| integration | `DocumentVersionsTest` | the "current document" across application and claim/renewal uploads; full history newest first |
| http | `LoginAndPasswordRuleTest` | an old password without a special character still signs in; registration applies the rule |
| http | `DocumentUploadTest` | upload type/size/content checks through the real upload endpoint |
| http | `AdminWritePathsTest` | staff accounts (create, edit, role change, deactivate, reset), last-admin protection, user search, settings (with their limits), holidays, audit-log filters, notifications, dashboard counters |
| http | `ApplicantWritePathsTest` | autosave of the 3 steps (cleaning and history), first save creates the Draft, Returned-with-flags lock, profile and password change, own documents only, notifications |
| http | `ScholarClaimTest` | a scholar uploads School ID + Grades in an open claim window and submits; another applicant cannot touch that semester |
| http | `InterviewWritePathsTest` | schedules (create, edit, cancel with applicants, delete), assign/unassign, no-show → one reschedule → second no-show rejects, scoring (only on/after the date, within the rubric), waitlist/reject decisions, declining a slot request |
| http | `NotificationsReadTest` | (bug B1) opening Notifications marks them read through a POST with the CSRF token to a url()-built address; a GET changes nothing; only the user's own; the bell count goes away |
| http | `ErrorAfterOutputTest` | (bug B7) an exception or fatal error after the page was printed ends as HTTP 500 with only the error page (production: no details). Its server is started with `SK_TEST_FAULTS=1`, so the test router runs `support/fault_after_output.php` after each page |
| http | `AuthFlowsTest` | register → e-mail code → verified → sign in; forgot password → code → new password (and unlock); unknown e-mail looks the same |
| smoke | `bin/smoke_test.php` | every route as admin, reviewer, treasurer and three applicants: no HTTP 500, no PHP warning, and each staff role sees exactly the pages the access map allows (404 for the rest) |

The seed's comments say which numbers the tests depend on. Change the seed, and update the arithmetic in
`tests/integration/AnalyticsTest.php`.
