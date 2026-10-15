# Change plan 4 — academic years move from programs to "Releasing of budget"

Follow-up to `docs/PROCESS.md`, `docs/CHANGE_PLAN.md` (decision D3), `docs/CHANGE_PLAN_2.md` and
`docs/CHANGE_PLAN_3.md`. Stage 0 only: no code has been changed.

**The change:** a program becomes a **standing** scholarship with no academic year. Academic years get their own table
and their own page under "Releasing of budget". Every year list in the system comes from that table. New scholars start
in the **current** academic year. Decisions that need your answer are in **§0**. §0.1 lists where the code differs from
the request.

---

## 0. Decisions (defaults proposed — "approve with defaults" accepts all)

| # | Question | Proposed default |
|---|---|---|
| G1 | Where academic years live | New table `academic_years` (§1). It is the **only** source of year lists. `Budget::academicYears()` and `TermSchedule::academicYearOptions()` are deleted. |
| G2 | "Exactly one current year" | **At most one** is enforced by the database: a stored generated column `current_flag = IF(is_current, 1, NULL)` with a UNIQUE index (NULLs don't collide). **At least one, once any exists** is enforced by the code: `setCurrent()` switches in one transaction, the current year can't be deleted, and the first year created is made current. |
| G3 | Which semester opens on approval (replaces D3) | **1st Semester of the current academic year**, as requested. Note: someone approved during the 2nd semester (for example in January) starts in a 1st Semester whose classes are over. Their claim window simply needs to exist. Say so if you would rather open the *current* semester. |
| G4 | No current year at approval | **Blocked (server):** "No current academic year — an admin or treasurer must create one under Releasing of budget → Academic years." This also applies to the reviewer, who can't open that page; the message says who can. |
| G5 | Budgets when a year is created | Checkbox **ticked by default**: one budget per program **with Active scholars**, at `Budget::suggestedTotal()`. It skips programs that already have a budget for that year, and programs with no suggestion. Same transaction as the year, and every budget is audit-logged. They stay normal budgets: editable, and deletable while nothing is released. |
| G6 | Claim / renewal windows | **Never auto-created.** They need dates the admin chooses, and creating one e-mails scholars. The "needed" list and the new page show what's missing, with a pre-filled **Create window** button. When the semester's year doesn't exist yet, it shows **"Create academic year YYYY-YYYY first"** with a link instead. |
| G7 | Two programs with the same name | Your data has two "SK EduKabataan Cash Assistance". Without the "(A.Y.)" suffix they can't be told apart in selects. **Default:** `Program::options()` adds a `label` that is the name alone, or the name + application dates ("… · Oct 16 – Nov 16, 2026") **only when two programs share a name**. Every program select prints `label`. |
| G8 | Reports 8 (Program summary) and 9 (Profile & academic) | They read the program's A.Y. and will use the **current** year. The Reports form has only Program + date range (no A.Y. select), so **no new select is added**. The report header says which A.Y. was used. Say so if you want an A.Y. select on the Reports form. |
| G9 | Backfill: which year is current | The year containing today by the June rule (`ACADEMIC_YEAR_START_MONTH = 6`). If that year isn't in the table, the latest label. On your data today (2026-10-16): **2026-2027** is current, and **2027-2028** is also created (from the second program). Backfilled rows have `created_by = NULL`, because no user made them. |
| G10 | SQL files | Migration + `database/schema.sql` + `tests/seed.sql` (as requested) **+ `database/sk_scholarship.sql`**. The project convention is that every schema change also goes into the phpMyAdmin dump, so a fresh install from either file works. |
| G11 | Test seed | `academic_years` gets **one row, 2026-2027, current**. Tests that need 2027-2028 create it with `AcademicYear::create()`, so "a year not in the table is refused" has a clean case. |
| G12 | Where "+ New academic year" appears | Next to every A.Y. select: the Budget modal, the Claim/Renewal window modal, the Releases filter and the Analytics A.Y. select. It's shown only when `AdminController::canAccess('academic-years')` is true. Not on Reports (no A.Y. select, G8). |
| G13 | Commits (see §8) | The working tree already holds this session's **uncommitted** changes (front/back documents, renewal year level, program delete, Graduated status, windows edit/delete, interview Closed, sessions/CSRF, …). It also holds **edits that are not mine** (`app/views/admin/calendar.php`, `public/assets/js/pages/calendar.js`, `docs/TEST_CHECKLIST.md`). Many of these files (`Program.php`, `Budget.php`, `TermSchedule.php`, views) are touched again by this plan. **Default:** before stage 1, I commit my earlier changes as one commit "Earlier changes (session 2026-10-14…16)", **leaving your calendar and checklist edits uncommitted**. Then each stage below is its own clean commit. Say so if you'd rather commit them yourself first, or keep everything uncommitted. |

### 0.1 Where the code differs from the request (checked)
1. **`ReportController.php:54` is the Analytics page's A.Y. default**, not reports 8/9. Reports 8 and 9 read the
   program's A.Y. in `Report.php:197` (`programSummary`) and `Report.php:229` (`profileAcademic`). Both places are
   handled (§5, G8).
2. **The Reports form has no A.Y. select** (`reports.php` sends only program + dates), so "accepts `?academic_year=`"
   applies only to Analytics (G8).
3. **`Scholar.php:29` `program_year` isn't read anywhere**, so it's simply removed. `Interview.php:18` selects
   `sp.academic_year`, and no view reads it either.
4. Also needing changes, though not listed in the request:
   - `Budget.php:151`: `Budget::save` checks the year against `Budget::academicYears()`.
   - `FundsController.php:13`, `:90` and `:147`: the year lists for Budget, Releases and windows.
   - The approval message in `Application.php:443-446` builds its claim-window lookup from the program's A.Y.
5. **Renewal submission** can't reach a year that isn't in the table: a renewal needs an Open window, and windows will
   only accept existing years. The extra check in `Renewal::approve` is a second guard, as requested.
6. **Claim windows** always point at an existing year: semesters are only opened by approval (current year) or by
   renewal approval (checked). So "create year first" can only appear on the **Renewal** needed list.
7. `database/sk_scholarship.sql` isn't in the request but has to change (G10).

---

## 1. Database — `database/migrations/2026_10_16_academic_years.sql`

```sql
CREATE TABLE academic_years (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(20) NOT NULL COMMENT 'YYYY-YYYY, second year = first + 1',
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    current_flag TINYINT(1) GENERATED ALWAYS AS (IF(is_current = 1, 1, NULL)) STORED COMMENT 'at most one current year',
    created_by INT UNSIGNED NULL COMMENT 'NULL = backfilled by this migration',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_ay_label (label),
    UNIQUE KEY uniq_ay_current (current_flag),
    CONSTRAINT fk_ay_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backfill: every year already used anywhere.
INSERT IGNORE INTO academic_years (label)
SELECT academic_year FROM scholarship_programs UNION SELECT academic_year FROM scholar_terms
UNION SELECT academic_year FROM budgets UNION SELECT academic_year FROM term_schedules
UNION SELECT academic_year FROM renewal_requests;

-- Current: the year containing today (June rule), else the latest label.
SET @today_ay := IF(MONTH(CURDATE()) >= 6, CONCAT(YEAR(CURDATE()), '-', YEAR(CURDATE()) + 1),
                                           CONCAT(YEAR(CURDATE()) - 1, '-', YEAR(CURDATE())));
SET @pick := COALESCE((SELECT label FROM academic_years WHERE label = @today_ay),
                      (SELECT MAX(label) FROM academic_years));
UPDATE academic_years SET is_current = 1 WHERE label = @pick;

ALTER TABLE scholarship_programs DROP INDEX idx_year, DROP COLUMN academic_year;
```
Safe to run once. Labels that aren't `YYYY-YYYY` can't occur: every writer validated the format. `schema.sql`,
`sk_scholarship.sql` (table + backfilled rows, program rows without the column) and `tests/seed.sql` change to match.

## 2. Model — `app/models/AcademicYear.php` *new* (the only year source)

| Method | Does |
|---|---|
| `all()` | rows newest first, each with `is_current` |
| `options()` | labels newest first (every A.Y. select) |
| `current()` | the current row or null |
| `exists(string $label)` | bool |
| `labelFor(string $date)` | the June rule (moved from `Program::academicYearFor`) |
| `suggestNext()` | the year after the latest label, or `labelFor(today)` when the table is empty |
| `create(string $label, bool $makeCurrent, bool $withBudgets, int $userId)` | validates the format, `label + 1`, unique; in one transaction: insert, `setCurrent` if asked or if none is current, and budgets (G5). Returns `['ok', 'id', 'budgets' => [[program, total]…]]` |
| `setCurrent(int $id, int $userId)` | clears, then sets, in one transaction; audit `academic_year_current_changed` |
| `usage(string $label)` | counts of semesters, budgets, windows and renewal requests with that label (delete rule and page) |
| `delete(int $id, int $userId)` | refused if current or `usage` > 0, with the reason; audit `academic_year_deleted` |
| `overview(string $label)` | per program: scholars in that year (semesters with the label), budget (none / total / remaining), and the four windows (1st/2nd × claim/renewal) with `TermSchedule::state`, or not created |

Audit actions: `academic_year_created`, `academic_year_current_changed`, `academic_year_deleted`, and
`budget_created` for each auto-created budget, the same action `Budget::save` writes.

## 3. Page — Releasing of budget → Academic years

- **Routes** (`AdminController::ROUTES`, `self::FUNDS` = admin + treasurer):
  - `academic-years` (GET)
  - `academic-years-save`, `academic-years-set-current`, `academic-years-delete` (POST, CSRF through `require_post`)
- **Controller:** new `AcademicYearController`.
- **Sidebar:** the first item under "Releasing of budget", above Budget.
- **Create form:**
  - Label pre-filled with `suggestNext()`; the server validates it.
  - "Make this the current academic year", ticked when no year is current yet.
  - "Also create a budget at the suggested amount for every program with Active scholars", ticked.
  - Flash, for example: "Created 2027-2028 and 1 budget (₱12,000.00)".
- **List**, one card per year: the Current badge; "Make current"; Delete, or the reason it's refused. Then a table per
  program:
  - scholars that year
  - budget status
  - 1st Sem claim · 2nd Sem claim · renewal into 1st · renewal into 2nd
  - Each missing item has a button that opens the existing modal **pre-filled**, by linking to
    `admin/budget&new=1&program_id=…&academic_year=…` and `admin/{release|renewal}-schedule&prefill=…`. Those pages
    open their modal on load, the same way `?edit=` already works on the window pages.

## 4. Programs without an academic year

- `Program::save`: no year at all. `Program::academicYearFor()` and `Program::hasScholars()` are deleted (nothing
  else uses them).
- `programs.php` and `programs.js`: `#pYearInfo`, `fixedYear` and the `scholar_count` use for the year go.
- `Program::options()`: `id, program_name, application_start, application_end` + `label` (G7).
- Every `(academic_year)` in a program select becomes `label`: analytics, calendar, dashboard, decisions,
  interviews, reports, term_schedules, budget.
- `Program::delete` audit text drops the A.Y.
- `Application.php` (three SELECTs), `Interview.php:18`, `Scholar.php:29`: `sp.academic_year` removed.
- `admin/application_view.php:17`: "(A.Y.)" removed.
- `applicant/dashboard.php:94`: past applications show the program + "submitted Oct 14, 2026" (or "not submitted").
- Unchanged: dates, open/close, slots (`Program::slotsUsed` already doesn't count graduated scholars) and limits.

## 5. Scholars land in the year ("auto-in") and server rules

| Where | Change |
|---|---|
| `Application::approve` | Inside the approval transaction: `AcademicYear::current()`, or throw G4's message. Passes the label to `Scholar::createFromApplication($app, $year, …)`. The approval message and its window lookup use that label. |
| `Scholar::createFromApplication` | Semester 1 = `$year` 1st Semester (was `$app['academic_year']`). |
| `Budget::save` | `AcademicYear::exists($year)`, else "Academic year X doesn't exist — create it under Releasing of budget → Academic years." |
| `TermSchedule::save` | Same check on create **and** on a change of target. |
| `Renewal::approve` | Same check for the target year before opening the semester. |
| `TermSchedule::needed` | Each row gets `year_exists`. The view shows "Create academic year X first" (a link, if the role may open the page) instead of "Create window". |
| Budget, windows, Releases filter, Analytics | Year lists = `AcademicYear::options()`; Add forms default to `current()`. `budget.js` drops the program `data-year` pre-fill and defaults to the current year. |
| Dashboard budget card (`AdminController:176`, `dashboard.php:88`, `_dashboard_charts.php:25`) | Current year (no current year → "No current academic year"). |
| Analytics (`ReportController:50-54`) | List = `options()`; default = current; still accepts `?academic_year=` from the list. |
| Reports 8 and 9 (`Report.php:197`, `:229`) | Current year (G8); printed in the report header. |

No claim, release, interview or notification rule changes otherwise. No record is hard-deleted except an unused
academic year, which is a label, not anyone's record. Every POST keeps CSRF and the role check.

## 6. Files

| File | Change |
|---|---|
| `database/migrations/2026_10_16_academic_years.sql` *new*, `database/schema.sql`, `database/sk_scholarship.sql`, `tests/seed.sql` | §1 |
| `app/models/AcademicYear.php` *new* | §2 |
| `app/controllers/AcademicYearController.php` *new*, `app/views/admin/academic_years.php` *new*, `public/assets/js/pages/academic-years.js` *new* (only if needed) | §3 |
| `app/controllers/AdminController.php` | 4 routes, `require_once`, dashboard budget year |
| `app/views/layouts/sidebar.php` | menu item |
| `app/models/Program.php`, `app/views/admin/programs.php`, `public/assets/js/pages/programs.js` | §4 |
| `app/models/Application.php`, `Scholar.php`, `Interview.php`, `Budget.php`, `TermSchedule.php`, `Renewal.php`, `Report.php` | §4–§5 |
| `app/controllers/FundsController.php`, `ReportController.php` | year lists, defaults, `?new=` / `?prefill=` |
| `app/views/admin/budget.php`, `term_schedules.php`, `releases.php`, `analytics.php`, `dashboard.php`, `_dashboard_charts.php`, `decisions.php`, `interviews.php`, `reports.php`, `calendar.php` (one line: the select label), `application_view.php`, `applicant/dashboard.php` | labels, year lists, "+ New academic year" links |
| `public/assets/js/pages/budget.js`, `term-schedules.js` | default year, open a pre-filled modal from the URL |
| `config/constants.php` | `ACADEMIC_YEAR_START_MONTH` comment now points to `AcademicYear` |

`calendar.php` holds edits that aren't mine (G13). Only its program-select line changes, and that line isn't part of
those edits.

## 7. Tests (`tests/README.md`)

- `ProgramYearTest` → replaced by **`AcademicYearTest`**:
  - label validation (format, second = first + 1)
  - unique label
  - exactly one current (switching; the database refuses a second current)
  - delete refused while used or current
  - `suggestNext()`
  - create with budgets: only programs with Active scholars, at the suggested total, skipping an existing budget
- **`AcademicYearBackfillTest`:** puts the test database back in the old shape (adds `scholarship_programs.academic_year`
  with values, drops `academic_years`), runs the real migration file, and checks:
  - the rows created
  - the current year: "today's" year when present, the latest otherwise
  - the dropped column
  It rebuilds afterwards.
- **`ApplicationStatusTest`:** approval opens Semester 1 in the current year, and is blocked when there is none.
- **`BudgetTest`, `TermScheduleEditDeleteTest`:** a year not in the table is refused, and an existing one works.
  `TermScheduleEditDeleteTest` creates 2027-2028 first (G11).
- **`RenewalYearLevelTest` / renewal approval:** approval into a year that doesn't exist is refused.
- Programs: `ProgramDeleteTest`'s audit-text expectation, and the seed without `academic_year`.
- Run `tests/vendor/bin/phpunit -c tests/phpunit.xml` and `php bin/smoke_test.php`. The smoke test also covers the
  4 new routes × 3 roles.

## 8. Stages and commits

| Stage | Content | Commit |
|---|---|---|
| (pre) | My earlier uncommitted changes, without your calendar / checklist edits (G13) | "Earlier changes (session 2026-10-14…16)" |
| 1 | Migration, schema.sql, sk_scholarship.sql, seed | "Stage 1 (plan 4): academic_years table and backfill" |
| 2 | `AcademicYear` model, page, routes, sidebar | "Stage 2 (plan 4): academic years page" |
| 3 | Programs without a year | "Stage 3 (plan 4): programs are standing (no academic year)" |
| 4 | Approval in the current year, server year checks | "Stage 4 (plan 4): scholars start in the current year; year checks" |
| 5 | Year dropdowns, budget auto-create, needed-window links, pre-filled modals | "Stage 5 (plan 4): one year list everywhere" |
| 6 | Dashboard, reports, analytics | "Stage 6 (plan 4): current year on dashboard, reports, analytics" |
| 7 | `PROCESS.md` (B1, new step before B6, B6 rules, B5, §8a, D3 wording), `CODE_ORGANISATION.md` (table + model), `tests/README.md`, `docs/TEST_CHECKLIST_5.md` | "Stage 7 (plan 4): docs and checklist" |

PHPUnit + smoke run at the end of every stage. They must pass before each commit. Commit messages end with the
attribution line. Nothing is pushed.

## 9. Risks

- **Running the migration drops `scholarship_programs.academic_year`.** Back up the database first. The value isn't
  lost: every program year is backfilled into `academic_years`. It just isn't tied to a program any more, which is
  the point of the change.
- **Code still reading the old column after the migration** would fail with "Unknown column". Mitigation: the grep
  list in §4–§5, the smoke test (every page, every role), and a final grep for `sp.academic_year`,
  `program['academic_year']` and `$app['academic_year']`.
- **The generated-column UNIQUE index** needs MariaDB 10.2+ / MySQL 5.7+. XAMPP's MariaDB qualifies. This is
  checked when the migration runs on the test database in stage 1. Fallback: the "exactly one" rule enforced by code
  only.
- **Approving in the 2nd semester** opens a 1st Semester that's already over (G3). This is a behaviour choice, not a
  bug.
- **Two programs with the same name** (G7) look identical anywhere the label isn't used. Every program select is in
  the §4 list.
- **Existing approved scholars** keep their semesters exactly as they are: only *new* approvals use the current year.
