# How the code is organised

SK EduKabataan is a plain PHP 8.3 application in the **Model–View–Controller** style, with no framework:

- **One entry point** (`public/index.php`) receives every request.
- **A route table** decides which controller handles it and which roles may open it.
- **Controllers** are small and grouped by step of the scholarship process (`docs/PROCESS.md`). They read the request, call models, and pick a page.
- **Models** hold all the SQL and the business rules.
- **Views** are HTML templates and never query the database.
- **JavaScript** is in small files, one per page that needs it.

---

## 1. One request, start to finish

Example: a reviewer opens **Screening**, i.e. `/admin/screening`. Apache rewrites this to `public/index.php?r=admin/screening`.

1. **`public/index.php`** loads `config/` and `core/`, sends the security headers, installs the error handler (no stack traces outside development) and starts the session.
2. It sends `admin/…` to the staff area, but only for staff roles (`STAFF_ROLES`). `applicant/…` goes to the applicant area, and `login`, `register`, etc. go to `AuthController`.
3. **`AdminController::ROUTES`** maps `'screening' => ['ScreeningController', 'queue', REVIEW]`, i.e. controller, method and the roles allowed. A route that is missing or not allowed for this role gets the same 404 (`not_found()`), so the page doesn't reveal what exists. This is *default deny*.
4. **`ScreeningController::queue()`**:
   - reads the filter with `get_id('program_id')`;
   - asks the model for the rows (`ApplicationSearch::search()`);
   - calls `render('admin/screening', [...], 'Screening')`.
5. **`render()`** prints the layout (`layouts/header.php`), the view and `layouts/footer.php`. The footer adds the shared scripts plus the page's own script, each with a `?v=` version so browsers fetch new code after a change.

Every form that changes data is sent as a **POST** and its CSRF token is checked first (`verify_csrf()`). Most actions do both in one call, `require_post()`. Then the action calls a model, sets a flash message and redirects. The two exceptions, sign-out and "mark notifications read", are GET requests and are listed as bugs B6 and B1 in `docs/REFACTOR_PLAN.md`.

---

## 2. Folders

| Folder | What lives there |
|---|---|
| `public/` | `index.php` (the only PHP file the web server runs) and `assets/`: CSS, images, JavaScript, and local copies of Bootstrap, Chart.js and FullCalendar (they work offline) |
| `config/` | `config.php` (settings from `.env`, paths, limits such as `MAX_FILE_SIZE`); `database.php` (one PDO connection, `Database::transaction()`); `constants.php` (statuses, their labels and colours, roles, document types, rubric, every rule value) |
| `core/` | Code every page uses: `Auth` (sessions, login, lockout), `Helpers` (output escaping, URLs, CSRF, `render()`, formatting, request input), `Validator`, `AuditLog`, `Notification` (in-app + e-mail), `Otp` (e-mail codes) |
| `app/controllers/` | One controller per part of the process (§3) |
| `app/models/` | The data and the rules (§4) |
| `app/views/` | Templates: `admin/`, `applicant/` (the application form is split into `form/_personal.php` … `_review.php`), `auth/`, `layouts/`, `errors/` |
| `bin/` | `send_reminders.php` (scheduled task: interview and deadline reminders, daily digest), `smoke_test.php` (§8) |
| `database/` | `schema.sql` and migrations |
| `storage/` | Uploaded documents, **outside the web root**: they can only be reached through a controller that checks who is asking |
| `tests/` | Automated tests and their separate test database (§8) |
| `docs/` | `PROCESS.md` (the scholarship process, step by step), the change plans, test checklists |

---

## 3. Controllers: one per part of the process

**Applicant side** (route table: `ApplicantController::ROUTES`)

| Controller | Process steps |
|---|---|
| `AuthController` | A1: register, e-mail code, sign in, forgot/reset password |
| `ApplicantController` | The applicant route table and the dashboard (what to do next comes from `ApplicantJourney`) |
| `ApplicationFormController` | A2/A3: the application form, autosave of each step, submit and resubmit after a return |
| `ApplicantDocumentController` | A2: the five application documents (upload, view, flag a wrong file) |
| `ApplicantAccountController` | A4: choosing an interview slot; notifications; profile |
| `ScholarshipController` | A7–A9: semesters, the cash claim, the renewal |

**Staff side** (route table and access map: `AdminController::ROUTES`)

| Controller | Process steps |
|---|---|
| `AdminController` | Route table + access map; dashboard, programs (B1), applications list |
| `ScreeningController` | B2: screening queue, one page per application, return / qualify / reject |
| `InterviewController` | B3–B5: schedules, assignment, attendance, scores, ranking, final decision |
| `FundsController` | B6–B10: budget, claims, releases, renewals, scholars |
| `CalendarController` | Calendar of interviews and claim/renewal windows |
| `ReportController` | Reports (PDF/CSV) and Data Analytics |
| `UserController`, `SettingsController`, `AuditController` | Staff accounts, settings and holidays, audit log |

Controllers contain **no SQL**. They read input, call models, and either render a page or redirect.

---

## 4. Models: the data and the rules

| Area | Models |
|---|---|
| Applicants and applications | `Applicant` (profile sections), `Application` (status changes go through one transition map), `ApplicationEligibility` (pass/fail per program rule), `ApplicationSearch` (lists), `ApplicantJourney` (dashboard tracker), `RecordHistory` (old/new value of every changed field), `Document` + `DocumentStorage` (versioned uploads, private files), `Lookup` (puroks, schools, courses) |
| Interviews | `Interview` (schedules), `InterviewSlots` (slot suggestions), `InterviewAssignment` (assign, no-show, reschedule, reminders), `InterviewScore` (rubric, ranking), `SlotRequest`, `Holiday` |
| Scholars and money | `Program`, `Budget`, `Scholar`, `Term` (semesters, claims, releases), `TermDocument`, `TermSchedule` (claim/renewal windows), `Renewal` |
| Reporting | `Report` (every PDF/CSV report), `WorkQueues` (dashboard counters), and one class per analytics section: `AnalyticsFunnel`, `AnalyticsBudget`, `AnalyticsInterviews`, `AnalyticsClaims`, `AnalyticsProfiles` |
| Accounts and settings | `User`, `Setting` |

**Where to find a rule:**
- Which status can follow which: `Application`'s transition map (and `Term::CLAIM_TRANSITIONS` for claims).
- Budget numbers: `Budget` and `AnalyticsBudget`.
- Interview suggestions: `InterviewSlots`.

Each number on the analytics page comes from one small function, and its formula is printed under the number.

---

## 5. Views and JavaScript

- **Views are templates only.** They print every value through `e()` (HTML escaping) and never run SQL.
- **No inline JavaScript.** There are no `<script>` blocks with code in the views and no `onclick=`/`onsubmit=` attributes. The one exception is the two-line text-size setting in the page head, which must run before the page is drawn.
  - `ui.js` (every page): text size (A / A+ / A++), show/hide password, live password checklist, sign-in pop-up, "Are you sure?" dialogs (`data-confirm`), filters that submit on change (`data-autosubmit`), wide tables.
  - `app.js`: sidebar, age and income calculations, wizard steps.
  - `sk-charts.js`: one chart style for every chart.
  - `pages/*.js`: one file per page that needs behaviour, named after the page (`budget.js`, `calendar.js`, …).
  - `application/*.js`: the application wizard. `shared.js` holds the settings; then `field-autosave.js`, `steps.js`, `document-upload.js`, `review.js` and `flags.js`.
- **Server data reaches JavaScript only as data**: `data-*` attributes, or a `<script type="application/json">` block written with `js_json()`. PHP is never echoed inside JavaScript code.

---

## 6. Shared building blocks (each defined once)

| What | Where |
|---|---|
| Statuses, labels, badge colours, roles, document types, limits | `config/constants.php`, `config/config.php` |
| Money and dates as shown on pages | `peso()`, `fmt_date()`, `fmt_datetime()`, `fmt_long_date()` |
| Reading request input (same casts everywhere) | `get_int()`, `post_str()`, `get_id()`, `post_bool()`, … |
| POST + CSRF guard, 404, paging | `require_post()`, `not_found()`, `paginate()` |
| Page layout | `render()` |
| New-password rule (8+ characters and 1 special character; never applied at sign-in) | `password_problems()`; forms use `Validator::newPassword()` |
| All-or-nothing database work | `Database::transaction()` |

---

## 7. Security, and where it lives

| Risk | Protection | Where |
|---|---|---|
| SQL injection | Prepared statements only (PDO with real server-side prepares); all SQL is in models/core | `config/database.php`, `app/models/` |
| Cross-site scripting | `e()` on every printed value; `js_json()` escapes `<`, `>`, `&` and quotes in page data | `core/Helpers.php` |
| Forged form posts (CSRF) | A session token on every form; `verify_csrf()` on every POST (HTTP 419 if wrong) | `core/Helpers.php`, `require_post()` |
| Opening pages you shouldn't | Default-deny route table with roles per page; applicants only see their own records (ownership checks) | `AdminController::ROUTES`, `Applicant::current()`, `Application::isOwnedBy()` |
| Account takeover | Hashed passwords; session id renewed at sign-in; HTTP-only cookie; lockout after 5 failed sign-ins for 15 minutes | `core/Auth.php`, `config/config.php` |
| E-mail codes | Stored hashed, with expiry, attempt limit and resend cooldown | `core/Otp.php` |
| Uploads | Content checked (real PDF/JPG/PNG, at most 5MB), random file names, stored outside the web root, sent only after an ownership/role check | `DocumentStorage` |
| Accountability | Every important action is written to the audit log | `core/AuditLog.php` |

---

## 8. Tests: the safety net

- **PHPUnit** (`tests/`), in three groups:
  - **unit**: the rules on their own (status transitions, password rule, upload checks, formatting and input helpers);
  - **integration**: formulas against a known database (analytics, budget suggestion, interview suggestions, document versions, approving beyond the slots, the reminder script);
  - **http**: real requests to a test web server (sign-in, uploads, every kind of form that changes data).
- **A separate test database**, `sk_scholarship_test`, is rebuilt from `schema.sql` + `tests/seed.sql` on every run. The tools refuse to run against the real database.
- **`bin/smoke_test.php`** signs in as every staff role and three applicants, and opens every route. It fails on any HTTP 500, PHP warning or error, and checks that each role gets exactly the pages the access map allows.

```
tests/vendor/bin/phpunit -c tests/phpunit.xml      # 221 tests
php bin/smoke_test.php                             # 246 requests
```

---

## 9. Conventions

- `declare(strict_types=1)` in every class and script file. Every parameter and return value has a type.
- One class per file, named after what it holds.
- Comments explain *why*, not *what*.
- Behaviour changes and clean-ups are separate commits. The clean-up history and the list of known bugs are in `docs/REFACTOR_PLAN.md`.
