# Tests

Everything here runs against a **separate database, `sk_scholarship_test`**, rebuilt from `database/schema.sql` +
`tests/seed.sql`, and a temporary upload folder. The real `sk_scholarship` database and `storage/` are never touched.
The test tools refuse to run if `DB_NAME` is anything else.

## Run

From the project root (XAMPP's MySQL must be running):

```
composer -d tests install                          # once: installs PHPUnit into tests/vendor (not committed)
tests/vendor/bin/phpunit -c tests/phpunit.xml      # unit + database + HTTP tests
php bin/smoke_test.php                             # every route, signed in as each kind of user
```

## What is covered

| Suite | File | What it proves |
|---|---|---|
| unit | `ApplicationTransitionTest` | the allowed status moves (docs/PROCESS.md §1), every action × every status |
| unit | `DocumentValidationTest` | upload checks on the file CONTENT: PDF/JPG/PNG only, not empty, at most 5 MB, fakes refused |
| unit | `PasswordRuleTest` | 8+ characters and 1 special character; the exact messages; Validator uses the same rule |
| integration | `ApplicationStatusTest` | refused and stale status changes; approval blocked when the slots are full |
| integration | `AnalyticsTest` | projected scholars, committed, projected need, surplus, extra fundable, attendance, no-show, scores, on-time rate, against numbers worked out by hand from the seed |
| integration | `BudgetTest` | suggested budget = slots × amount per semester × 2 |
| integration | `InterviewSuggestionsTest` | slot suggestions: existing schedules first, business hours, Mon–Sat, after the application period, no holidays, no other program's day, no overlaps |
| integration | `ReminderScriptTest` | `bin/send_reminders.php` run for real: interview, deadline and daily-digest reminders each go out once; a second run sends nothing |
| integration | `DocumentVersionsTest` | the "current document" across application and claim/renewal uploads; full history newest first |
| http | `LoginAndPasswordRuleTest` | an old password without a special character still signs in; registration applies the rule |
| http | `DocumentUploadTest` | upload type/size/content checks through the real upload endpoint |
| http | `AdminWritePathsTest` | staff accounts (create, edit, role change, deactivate, reset), last-admin protection, user search, settings (with their limits), holidays, audit-log filters, notifications, dashboard counters |
| http | `ApplicantWritePathsTest` | autosave of the 3 steps (cleaning and history), first save creates the Draft, Returned-with-flags lock, profile and password change, own documents only, notifications |
| http | `AuthFlowsTest` | register → e-mail code → verified → sign in; forgot password → code → new password (and unlock); unknown e-mail looks the same |
| smoke | `bin/smoke_test.php` | every route as admin, reviewer, treasurer and three applicants: no HTTP 500, no PHP warning, and each staff role sees exactly the pages the access map allows (404 for the rest) |

The seed's comments say which numbers the tests depend on. Change the seed, and update the arithmetic in
`tests/integration/AnalyticsTest.php`.
