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
| unit | `PasswordRuleTest` | 8+ characters and 1 special character; the exact messages; Validator uses the same rule |
| integration | `ApplicationStatusTest` | refused and stale status changes; approval blocked when the slots are full |
| integration | `AnalyticsTest` | projected scholars, committed, projected need, surplus, extra fundable, attendance, no-show, scores, on-time rate, against numbers worked out by hand from the seed |
| integration | `BudgetTest` | suggested budget = slots × amount per semester × 2 |
| integration | `InterviewSuggestionsTest` | slot suggestions: existing schedules first, business hours, Mon–Sat, after the application period, no holidays, no other program's day, no overlaps |
| integration | `DocumentVersionsTest` | the "current document" across application and claim/renewal uploads; full history newest first |
| http | `LoginAndPasswordRuleTest` | an old password without a special character still signs in; registration applies the rule |
| http | `DocumentUploadTest` | upload type/size/content checks through the real upload endpoint |
| smoke | `bin/smoke_test.php` | every route as admin, reviewer, treasurer and three applicants: no HTTP 500, no PHP warning, and each staff role sees exactly the pages the access map allows (404 for the rest) |

The seed's comments say which numbers the tests depend on. Change the seed, and update the arithmetic in
`tests/integration/AnalyticsTest.php`.
