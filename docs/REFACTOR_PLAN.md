# Refactor plan — clean up the code without changing what the system does

**Status: completed.**
- Stages 1–8 are done: commits `d188740` … `088964e`, each listed under its stage in §8, plus `15e9fa0` for `docs/CODE_ORGANISATION.md`.
- Bugs B1 and B7 were then fixed in their own commits, `bb794a9` and `e00b7ff` (§7).
- Latest results (2026-10-13): PHPUnit **232 tests, 873 assertions, OK**; smoke test **252 requests, OK** (no HTTP 500, no PHP warnings, access map as expected).
- Before/after line counts of the split files: §9.

The Stage 0 measurements in §1–§6 were taken on branch `restructure-process` at `16f58b6`; their line numbers refer to that commit.
The rule for every stage: same pages, URLs, schema, e-mails, reports and look. Anything that would change what a
user sees or what the database stores went into **§7 Bugs found** instead of being fixed here.
The decisions in **§0** were approved with their defaults.

---

## 0. Decisions (defaults proposed — "approve with defaults" accepts all)

| # | Question | Proposed default |
|---|---|---|
| R1 | **Where PHPUnit goes.** `vendor/` is committed to git (717 files: PHPMailer and Dompdf, so XAMPP installs work without Composer), and you asked me not to touch `vendor/`. A root `composer require --dev phpunit/phpunit` writes ~25 MB of test tools into `vendor/`. It also rewrites `vendor/composer/autoload_files.php`, which would then `require` PHPUnit files that a fresh clone without dev packages doesn't have, so the site would crash with a fatal error. | **A separate Composer project for the tests:** `tests/composer.json` with `require-dev: phpunit/phpunit ^11` (PHP 8.3 ✔), installed into `tests/vendor/` (git-ignored). Root `vendor/`, `composer.json` and `composer.lock` stay untouched. Run with `composer --working-dir=tests install`, then `tests/vendor/bin/phpunit -c tests/phpunit.xml`. *Alternative:* root `require-dev` and commit the dev packages (+~25 MB in the repo, same autoload-file issue on deploy). |
| R2 | **Testing upload validation (type/size).** `Document::storeUpload()` checks `is_uploaded_file()`, so a unit test can't call it with a temp file without changing code first. | **Stage 1 tests it over HTTP**: multipart POSTs to `applicant/documents/upload` on the test server, with a wrong type, a renamed `.exe`, a file over 5 MB, a fake PDF and a real PDF. Stage 4 moves the checks into a pure `DocumentStorage::validate($path, $name)` (§1, row 10), and a unit test is added then. |
| R3 | **New class names after splitting.** Should old names keep thin "facade" methods (e.g. `Interview::suggestSlots()` forwarding to `InterviewSlots::suggest()`)? | **No facades.** Every caller is updated in the same commit, so there is one obvious place for each rule. A facade would mean two names for the same thing in front of the panel. |
| R4 | **Inline event handlers** (36 `onclick=` / `onsubmit=` / `onchange=` attributes, §3b). Not literally `<script>` blocks. | **Include them in Stage 5:** `onsubmit="return confirm('…')"` → `data-confirm="…"`, `onchange="this.form.submit()"` → `data-autosubmit`, and modal openers → `data-*` payloads. They are handled once in `ui.js` / the page script. The dialogs and their wording stay the same. |
| R5 | **`bin/send_reminders.php`** has 10 SQL statements: the "claim, then send, then release on failure" pattern that stops duplicate reminders. | **Move them into models in Stage 2**, unchanged SQL and order: `Interview::claimReminder()/releaseReminder()`, `TermSchedule::claimDeadlineReminder()/…`, `Setting::claimDigestDay()`. The script keeps only the loop and the e-mails. |
| R6 | **"Validator everywhere" (Stage 7)** must not change any message a user sees. Today's messages are hand-written per form (e.g. "Name and a valid e-mail are required."). | **Validator rules take the exact current message** (`->required('full_name', message: 'Full name required.')`). A form moves to Validator only where its messages and order of checks can stay identical. The rest are listed and left alone. |
| R7 | **`strict_types` "on every PHP file".** Every controller, model, core, config and bin file already has it (checked: 0 missing). No view has it (0 of 54). In a view, strict types would turn `e($someInt)` into a TypeError. | **Not in views.** Views stay as templates. The rule "strict_types on every class/script file" is already met. Stage 7 adds the 3 missing parameter types (§6). |

---

## 1. The 10 largest PHP/JS files (excluding `vendor/`, `node_modules/`, `public/assets/vendor/`)

| # | File | Lines | What I'd split out, and where |
|---|---|---:|---|
| 1 | `app/controllers/ApplicantController.php` | 739 | **Router + dashboard** stay (`handle()` becomes a route map like `AdminController::ROUTES`; same URLs). **`journey()`** (130 lines of pure "what's next" logic) → `app/models/ApplicantJourney.php` (a pure function, so it gets unit tests). **Application form** (`application`, `autosave`, `submit`, `editableApplication`, address helpers, `lockUnflagged`) → `ApplicationFormController.php`; its 4 save methods' SQL → `Applicant::savePersonal/saveEducation/saveFamily`. **Documents** (`uploadDocument`, `viewDocument`, `invalidateDocument`) → `ApplicantDocumentController.php`. **My Scholarship** (claims, renewals, term documents) → `ScholarshipController.php`. **Slot requests, notifications, profile** → `ApplicantAccountController.php`. |
| 2 | `app/models/Interview.php` | 667 | `Interview` keeps schedules: find/list/create/update/cancel/delete, `displayStatus`, `day`, `schedulesInRange`. **`InterviewSlots.php`**: `suggestSlots`, `isCurrentSuggestion`, `holidays`, `suggestionWindowDays`, `SUGGESTION_BLOCKS`. **`InterviewAssignment.php`**: `assign`, `unassign`, `scheduleApplication(At)`, `markNoShow`, assignment lookups, + the new `missedCount()` (§4). **`InterviewScore.php`**: `evaluation`, `saveScore`, `ranking`. |
| 3 | `app/views/applicant/application_form.php` | 666 | `views/applicant/form/_header.php` (status, returned notice), `_personal.php` (83–198), `_education.php` (200–274), `_family.php` (276–378), `_documents.php` (380–462), `_review.php` (464–640; its row-building closures → a small `ApplicationReview::rows()` helper). The flagged-fields script (643–665) → `public/assets/js/application-flags.js`. |
| 4 | `app/models/Application.php` | 530 | Keeps the status machine (`TRANSITIONS`, `can`, `changeStatus`), lookups, submit and decisions. **`ApplicationEligibility.php`**: `eligibility()` (~55 lines). **`ApplicationSearch.php`**: `search`, `count`, `where` (~55 lines, shared by Applications and Screening). |
| 5 | `app/models/Analytics.php` | 527 | One class per section of the Analytics page: **`AnalyticsFunnel`** (stages, conversion rates, projected scholars, slots remaining), **`AnalyticsBudget`** (committed, projected need, remaining, surplus, extra fundable, releases), **`AnalyticsInterviews`** (attendance, no-show by attempt, scores), **`AnalyticsClaims`** (on-time rate, overdue, avg days to submit), **`AnalyticsProfiles`** (profile breakdowns, GWA). |
| 6 | `public/assets/js/application-autosave.js` | 509 | `application/shared.js` (wizard lookup, indicator, CSRF, storage keys: a small `window.SKApp` object), `application/field-autosave.js` (lines 44–233: serialize, sync, retry, offline, restore banner, normalization), `application/steps.js` (234–264: remember the step), `application/document-upload.js` (266–509). Loaded in that order through `$pageScripts`, each with its `?v=`. |
| 7 | `app/controllers/AdminController.php` | 474 | Keeps the route table, `canAccess`, `handle`, dashboard, notifications, applications, programs. **`UserController.php`** (users, save, toggle, reset), **`SettingsController.php`** (settings, save, test e-mail, holidays), **`AuditController.php`** (audit log). Route table entries point to the new classes; URLs and role lists stay the same. After Stage 2 moves its SQL out, about 180 lines remain. |
| 8 | `app/models/TermSchedule.php` | 395 | **Leave as is.** It's cohesive (one table, one concept). Only gains the reminder methods from R5. |
| 9 | `app/models/Term.php` | 369 | **`TermDocument.php`**: `documents`, `markDocuments`, `document`, `documentsReady`, `invalidateNewest`, `uploadDocument` (~95 lines). `Term` keeps the claim lifecycle. |
| 10 | `app/models/Document.php` | 327 | **`DocumentStorage.php`**: `storeUpload` (split into a pure `validate($path, $name)` + `store()`), `deleteFile`, `absolutePath`, `send` (the file-system half, ~120 lines). `Document` keeps rows, versions and supersede. |

Next in size, not split: `AuthController.php` 320 (loses its 6 SQL calls to a new `User` model), `views/admin/application_view.php` 281 (loses its inline maps), `models/Report.php` 276, `core/Notification.php` 273.

---

## 2. SQL that lives outside a model

Re-counted with `grep -nE '->(prepare|query|exec)\(|Database::conn\(\)'`. Views contain **no SQL** (0 hits in `app/views`).
In controllers: **50 call sites**. AdminController's dashboard runs one closure 8 times, so it's ~57 statements.
Your estimate was close: Admin 23 ✔, Applicant 15 ✔, Auth 6 ✔, plus 6 you didn't count (Funds 2, Interview 2, Screening 2).

| File:line | What it does | Moves to |
|---|---|---|
| **AdminController** (23) | | |
| 139–140 (×8 queries) | dashboard "needs your action" counters | `Analytics::queueCounts()` |
| 181, 183 | mark all notifications read; list notifications | `Notification::markAllRead()`, `Notification::forUser()` |
| 238, 246 | users list with search/role filter | `User::search()` |
| 255 | active admins except one | `User::otherActiveAdmins()` |
| 264, 274 | e-mail already used? | `User::emailTaken()` |
| 279 | current role of a user | `User::role()` |
| 287 | update staff account | `User::updateStaff()` |
| 297 | create staff account | `User::createStaff()` |
| 313–314, 322 | read + flip `is_active` | `User::find()`, `User::setActive()` |
| 335 | admin resets a password | `User::setPassword()` |
| 347 | read all settings | `Setting::all()` (new model `Setting.php`, which `setting()` will use) |
| 357 | holidays from 30 days ago | `Holiday::recent()` (new `Holiday.php`; `Interview::holidays()` moves there too) |
| 373 | upsert settings | `Setting::saveMany()` |
| 391–393 | add holiday | `Holiday::add()` |
| 409, 413 | read + delete holiday | `Holiday::find()`, `Holiday::delete()` |
| 439, 459, 464 | audit log page + count | `AuditLog::search()`, `AuditLog::count()` (core/AuditLog.php) |
| 469, 470, 471 | filter options (actions, entity types, users) | `AuditLog::filterOptions()` |
| **ApplicantController** (15) | | |
| 235, 236, 237 | puroks / schools / courses for the form | `Lookup::puroks()`, `::schools()`, `::courses()` (new `Lookup.php`) |
| 262, 265 | create Draft + 5 empty document rows | `Application::createDraft()` |
| 341 | purok name by id | `Lookup::purokName()` |
| 396 | upsert personal information | `Applicant::savePersonal()` |
| 437, 453, 457 | update / insert education | `Applicant::saveEducation()` |
| 477 | upsert family background | `Applicant::saveFamily()` |
| 586 | "is this document theirs?" | `Document::belongsToApplicant()` |
| 701, 704 | mark read / list notifications (duplicate of Admin 181/183) | `Notification::markAllRead()`, `::forUser()` |
| 718, 726, 729 | profile: password + name/contact | `User::setPassword()`, `User::updateProfile()` |
| **AuthController** (6) | | |
| 125, 298 | user name + e-mail by id | `User::find()` |
| 166 | mark e-mail verified | `User::markEmailVerified()` |
| 183 | e-mail by id (verify page) | `User::find()` |
| 214 | active user id by e-mail (forgot password) | `User::activeIdByEmail()` |
| 289–290 | set new password + clear lockout | `User::setPassword()` |
| **FundsController** (2) | | |
| 99 | release history with filters (LIMIT 300) | `Term::releaseHistory()` |
| 199 | a scholar's renewal requests | `Renewal::forScholar()` |
| **InterviewController** (2) | | |
| 111 | count of missed interviews (same query as `Interview.php:431`) | `InterviewAssignment::missedCount()` |
| 120 | last venue used by the program | `Interview::lastVenue()` |
| **ScreeningController** (2) | | |
| 25 | audit trail of one application + its documents | `AuditLog::forApplication()` |
| 51 | interview attempts with scores | `InterviewAssignment::attemptsForApplication()` |
| **bin/send_reminders.php** (10) | reminder claim/release, settings, digest day | see R5 |

`core/` (Auth, Otp, AuditLog, Notification, Helpers: 28 hits) is infrastructure that owns its own tables, so it stays.

---

## 3. Inline scripts in `app/views`

### 3a. `<script>` blocks: 18 files (your count ✔)

| File (lines) | Size | PHP inside the JS today | Moves to |
|---|---:|---|---|
| `admin/calendar.php` 72–193 | 122 | `TODAY`, `WEEK_END`, feed URL | `js/pages/calendar.js`; values as `data-*` on `#calendar` |
| `admin/budget.php` 92–118 | 27 | — | `js/pages/budget.js` |
| `admin/analytics.php` 182–207 | 26 | 3 chart datasets | `js/pages/analytics.js`; data in `<script type="application/json">` via `js_json()` |
| `admin/programs.php` 131–156 | 26 | — | `js/pages/programs.js` |
| `admin/term_schedules.php` 122–147 | 26 | `$editId` | `js/pages/term-schedules.js`; `data-edit-id` on the table |
| `admin/_analytics_profiles.php` 142–163 | 22 | 7 datasets | `js/pages/analytics.js` (same JSON element) |
| `applicant/application_form.php` 644–665 | 22 | flags + field map | `js/application/flags.js`; `data-flags` / `data-flag-fields` |
| `admin/audit_Logs.php` 126–143 | 18 | — | `js/pages/audit-log.js` |
| `auth/verify_otp.php` 30–45 | 16 | — | `js/pages/otp-countdown.js` (shared, see next row) |
| `auth/verify_reset_otp.php` 30–45 | 16 | — (identical to the row above) | same file |
| `admin/_dashboard_charts.php` 63–75 | 13 | 6 datasets | `js/pages/dashboard.js` + JSON element |
| `admin/interview_schedule.php` 106–117 | 12 | 1 | `js/pages/interview-schedule.js` |
| `admin/reports.php` 68–78 | 11 | — | `js/pages/reports.js` |
| `admin/users.php` 111–121 | 11 | — | `js/pages/users.js` |
| `admin/interviews.php` 113–122 | 10 | — | `js/pages/interviews.js` |
| `admin/interview_reschedule.php` 103–112 | 10 | — | `js/pages/interview-reschedule.js` |
| `admin/claim_view.php` 70–76 | 7 | `$minGwa` via plain `json_encode` | `js/pages/claim-view.js`; `data-min-gwa` |
| `admin/interview_score.php` 42–48 | 7 | — | `js/pages/interview-score.js` |

**Stays inline:** `layouts/head_assets.php` (2 lines) applies the A/A+/A++ text size **before first paint**. Moving it to a file would make the page jump on every load.

### 3b. Inline event handlers (R4): 36 attributes in 17 views

19 × `onsubmit="return confirm('…')"`, 6 × `onchange="this.form.submit()"`, 9 × modal openers (`openScheduleModal`, `openUserModal`, `openProgramModal`, `openBudgetModal` with `js_json` payloads), 2 × `document.getElementById(...)`.
Most are in application_form 4, interview_schedule 4, decisions 4, users 3, term_schedules 3, programs 3 and budget 3.

---

## 4. Duplicated logic

| Duplicate | Where it appears | One shared place |
|---|---|---|
| **Peso formatting**: 7 closures, each handling a missing amount differently | `views/admin/budget.php:1`, `releases.php:1`, `_dashboard_charts.php:4` (null → ₱0.00); `analytics.php:2`, `application_view.php:8`, `applicant/application_form.php:491`, `models/Report.php:233` (null → "—"); `Report.php:196` (null → "No budget"); plus 14 inline `'₱' . number_format(` in `ApplicantController:192`, `Application:445`, `Budget`, `Term:323,342`, `dashboard.php`, `programs.php`, `scholars.php`, `scholar_view.php`, `scholarship.php` | `peso(?float $v, string $ifEmpty = '—'): string` in `core/Helpers.php`; each caller passes its own empty text so output stays identical |
| **Date display**: `date('M d, Y', …)` ×26, `date('M d, Y g:i A', …)` ×15, `date('F j, Y', …)` ×11 | 30+ views and models | `fmt_date()`, `fmt_datetime()`, `fmt_long_date()` (same format strings). The 10 rarer formats stay where they are. |
| **Pagination**: page, limit, offset, `ceil()` | `AdminController::applications` 196–200, `::auditLogs` 457–467 | `paginate(int $total, int $perPage): array` (page from `?page`) |
| **Selected program from `?program_id`**: 6 variants | `AdminController:158`, `ReportController:43` (`Program::selected(!empty($_GET…))`); `InterviewController::programFromRequest` (GET **or** POST); `ScreeningController:10`; `CalendarController::programId` (validates it exists); `FundsController:86,187` (kept as string for filters) | `Program::fromRequest()` for the 3 identical ones; the 3 that behave differently on purpose keep their own code, with a comment saying why |
| **Notifications page**: identical SQL in two controllers | `AdminController::notifications` 177–186, `ApplicantController::notifications` 697–711 | `Notification::forUser()` / `markAllRead()`; one shared action |
| **POST + CSRF guard**: written out 9 times | `ApplicantController` 309–310, 526–527 (JSON variant), 563–564, 624–625, 637–638, 653–654, 675–676, 687–688, 716–717 | the existing `require_post()` (the JSON variants keep their JSON answer) |
| **"Not found" page**: `http_response_code(404); require …/404.php; return;` ×11 | Admin 125, Applicant 42, Funds 48/128/197, Interview 31/109/176, Screening 20/95, `public/index.php:106` | `not_found(): never` |
| **Request input reading**: 148 `$_GET`/`$_POST` reads | all 8 controllers (Admin 37, Interview 29, Funds 26, Applicant 20, Screening 13, Auth 12, Report 8, Calendar 3) | `input_str($key)`, `input_int($key)`, `input_bool($key)` (GET or POST explicitly), same casts as today |
| **Staff role list** | `public/index.php:100` hard-codes `'admin', 'reviewer', 'treasurer'`; `AdminController::STAFF_ROLES` exists for exactly this and is unused | `index.php` uses `AdminController::STAFF_ROLES` |
| **Missed-interview count**: same query twice | `InterviewController:111`, `Interview.php:431` | `InterviewAssignment::missedCount()` |
| **Attendance badge colour** | `admin/application_view.php:206`, `admin/interview_schedule.php:46`, `admin/dashboard.php:43` | `ATTENDANCE_COLORS` in `config/constants.php` |
| **Term-document status colour** | `admin/_term_documents.php:11`, `applicant/scholarship.php:12` | `TERM_DOC_STATUS_COLORS` |
| **Eligibility result colour** | `admin/application_view.php:7` (only place, but an inline map) | `ELIGIBILITY_COLORS` |
| **OTP countdown script**: two identical copies | `auth/verify_otp.php:30–45`, `auth/verify_reset_otp.php:30–45` | one `otp-countdown.js` |
| **Upload limit "5MB"** written as text in 4 places, separately from `MAX_FILE_SIZE` | `Document.php:216`, `application_form.php:387`, `scholarship.php:82`, `application-autosave.js:395` (+ its own `5 * 1024 * 1024`) | text built from `MAX_FILE_SIZE`; the JS reads it from `data-max-bytes` |
| **Suggestion window 7–180 days** clamped twice | `AdminController:371`, `Interview.php:196` | `INTERVIEW_WINDOW_MIN/MAX_DAYS` constants |
| **Status labels/colours** | Already central: `APP_STATUS_LABELS/COLORS`, `CLAIM_STATUS_*`, `RENEWAL_STATUS_LABELS` in `config/constants.php`, `Interview::STATUS_COLORS`, `TermSchedule::STATE_LABELS` (checked: no copies) | (only the 3 inline maps above remain) |
| **Permission checks** | Already central: `AdminController::canAccess()` (route table) is used by `handle()`, the sidebar and pages (checked: no copies) | — |

---

## 5. Dead code (proven with grep)

| Item | Proof | Plan |
|---|---|---|
| `AdminController::STAFF_ROLES` (line 109) | `grep -rn "STAFF_ROLES" app core public bin` → only its definition | **Used** (Stage 6c, `cb104d4`): moved to `config/constants.php` as `STAFF_ROLES`, used by `AdminController`, `UserController` and `public/index.php` |
| `Validator::min()`, `numeric()`, `date()`, `in()` | no `->min(`, `->numeric(`, `->date(`, `->in(` call anywhere (the last `->min` went when the password rule moved to `strongPassword()`) | **Done: deleted in Stage 8** (`088964e`). Stage 7 didn't need them (its new rule is `newPassword()`). |
| CSS `.auth-hero` (in the focus rule), `.brand-logos-lg`, `.brand-rule` | `grep -rlw` over `app core public/assets/js` → 0 files each | **Done: deleted in Stage 8** (`088964e`); none of 585 rendered pages used them |
| `public/assets/js/app.js` 116–120 (notifications "mark read" fetch) | its selector `[data-notifications]` appears in no view (`grep -rn "data-notifications" app` → 0) | left in the refactor (the broken half of bug **B1**); **removed by the B1 fix** (`bb794a9`), replaced by `pages/notifications.js` |
| `?mark_read=1` branch in both notifications actions | only caller is the dead fetch above | left in the refactor; **removed by the B1 fix** (`bb794a9`), replaced by POST routes |
| Bootstrap override classes never used in our markup (`text-bg-*`, `btn-info`, `btn-outline-info/success`, `alert-primary`, `alert-link`, `valid-feedback`, `was-validated`, `text-secondary`, `text-info`, `link-danger`, `list-group-item-action`, `input-group-text`, `bg-dark`) | 0 hits in views/JS | **keep**: they make standard Bootstrap classes meet the contrast rules if a page ever uses one |
| Routes, views, model methods, helper functions | Script over every class: a method counts as used if `Class::name(`, `self::/static::/$this->name(` or `$obj->name(` appears anywhere (route strings for controllers). **0 unused** in models/controllers/core. Every one of the 54 views is required somewhere. Every admin and applicant route is linked (`claim/upload`, `renewal/upload` through `applicant/$purpose/upload`). | nothing to delete. Earlier change plans already removed the rest. |
| JS functions | `checkForNewerLocalCopy` looked unused but is a named IIFE that runs on load | not dead |

Out of scope (noted only): `node_modules/` (11 MB) is committed. It was only the source the local Chart.js/FullCalendar files were copied from. You asked me not to touch it.

---

## 6. Magic numbers → named constants (Stage 7)

| Value | Where | Constant |
|---|---|---|
| `× 2` (semesters per academic year) | `Budget.php:71`, `Analytics.php:156, 288` | `SEMESTERS_PER_YEAR` (= `count(SEMESTERS)`) |
| `48` hours | already `TERM_SCHEDULE_REMINDER_HOURS` | — |
| `5 * 1024 * 1024` | `config.php` `MAX_FILE_SIZE` ✔; JS copy at `application-autosave.js` | JS reads `data-max-bytes` |
| `7`, `180`, `60` (suggestion window) | `AdminController:371`, `Interview.php:196` | `INTERVIEW_WINDOW_MIN_DAYS`, `_MAX_DAYS`, `_DEFAULT_DAYS` |
| `168`, `24` (reminder hours) | `AdminController:368`, `settings.php:15` | `INTERVIEW_REMINDER_MAX_HOURS`, `_DEFAULT_HOURS` |
| `25`, `50` (page sizes) | `AdminController:197, 458` | `APPLICATIONS_PER_PAGE`, `AUDIT_LOG_PER_PAGE` |
| `100`, `200`, `300` (list caps) | notifications, users, screening queue, release history | named per list |
| `10 * 60` (OTP countdown) | both OTP views | from `Otp::TTL_MINUTES` (made public), passed as `data-ttl` |

Types: 3 untyped parameters remain: `ApplicantController::measurement($value)`, `old(…, $default)` (also no return type), `js_json($value)`. They become `mixed` / `string|int|float|null`. Every other model/core method is already fully typed.

---

## 7. Bugs found (NOT fixed in the refactor; each fixed separately, one commit each)

Still open: **B2, B3, B4, B5, B6, B8**. Fixed: B1 and B7 (high priority).

| # | Bug | Evidence | Effect | Priority | Status / fix plan |
|---|---|---|---|---|---|
| **B1** | **Notifications are never marked as read.** The bell's unread count only grows. | `app.js:117–119` waits for `[data-notifications]`, which no view has; its URL is hard-coded to `/sk-scholarship/public/…` (wrong on any other install path); the only "mark read" is a GET (`?mark_read=1`) that changes data without a CSRF check. | Every user's badge shows all notifications ever received | **High** | **Fixed** in `bb794a9`: POST routes `admin/` and `applicant/notifications/mark-read` (CSRF), the page hook built with `url()`, `pages/notifications.js` posts it and clears the bell. Test: `NotificationsReadTest`. |
| **B2** | **"Forgot password → Resend code" reveals whether an e-mail is registered.** | `AuthController::forgotPassword` unsets `otp_user_id` for unknown e-mails; `resendOtp` then redirects to Sign In, while a real account stays on the code page. | Account enumeration (low severity) | Low | Open. Fix: for an unknown e-mail, keep the same session state and page as for a real account, so Resend looks identical (and sends nothing). |
| **B3** | **The OTP countdown restarts at 10:00 on every page load**, including the reload after a wrong code. | `pages/otp-countdown.js` always starts from the full lifetime (`data-ttl` = `Otp::TTL_MINUTES`), not from the code's real expiry | The page can say "9:58 left" for a code that is already expired | Low | Open. Fix: write the code's real expiry (`expires_at` of the newest `email_otp_codes` row) into the page and count down from that. |
| **B4** | **Admin "Reset password" reports success for a user that doesn't exist.** | `UserController::userReset` runs the UPDATE and audit-logs without checking the id (0 rows changed → still "Password reset.") | Misleading message and a bogus audit row (crafted request only) | Low | Open. Fix: look the account up first and answer "Account not found." with no UPDATE and no audit row when it doesn't exist. |
| **B5** | **The OTP e-mail still prints the code in the old navy** `#0d3b66`. | `AuthController.php:134` inline style | Off-brand (the rest of the e-mail uses the new tokens) | Low | Open. Fix: use the brand colour of the rest of the e-mail for the code instead of `#0d3b66`. |
| **B6** | **Sign out is a GET link** (`?r=logout`). | `public/index.php` `case 'logout'` | Another site could sign a user out (low severity) | Low | Open. Fix: make Sign out a small POST form with the CSRF token in the account menu; a GET to `logout` only redirects. |
| **B7** | **An error after a page has started returns HTTP 200**, not 500. *(Found in Stage 1, while proving the smoke test catches errors.)* | `public/index.php` exception handler: `http_response_code(500)` only when `!headers_sent()`; by the time a view throws, the layout has already been sent | In production the user sees half a page with the error page appended; monitoring sees a "successful" request | **High** | **Fixed** in `e00b7ff`: every page is built in an output buffer; the exception handler and a shutdown check for fatal errors discard it and answer 500 with the error page. Test: `ErrorAfterOutputTest`. |
| **B8** | **Registration accepts the e-mail `0`.** *(Found in Stage 7.)* | `Validator::email()` only checks a value that is `!empty()`, and `"0"` is "empty" in PHP; `required()` passes it (not blank), so a crafted POST with `email=0` creates an account. The browser's `type="email"` field blocks it in normal use. | An account with an unusable e-mail (crafted request only) | Low | Open. Fix: in the Validator, treat only a missing or blank value as empty (not `"0"`), so `email()` checks `"0"`; add a test. |

---

## 8. Stages (each: `php -l` on changed files → PHPUnit → smoke test → commit)

**Stage 1 — safety net (before any code moves)**
- `tests/composer.json` + `tests/phpunit.xml` (R1). `phpunit.xml` forces `DB_NAME=sk_scholarship_test`, `APP_ENV=testing`, and a dead mail host (`MAIL_HOST=127.0.0.1`, port 9).
- `tests/bootstrap.php`:
  - refuses to run unless `getenv('DB_NAME') === 'sk_scholarship_test'` **and** `SELECT DATABASE()` returns it;
  - rebuilds the test database from `database/schema.sql` + `tests/seed.sql` (no triggers or `DELIMITER` in the schema, so plain statements work).
- `tests/seed.sql`:
  - 1 open program, 1 admin, 1 reviewer, 1 treasurer;
  - applicants at Draft / Submitted / Returned / Qualified / Interview Scheduled / Interviewed / Waitlisted / Approved / Rejected;
  - 1 scholar with a Released term and a claim window;
  - dates relative to `CURDATE()`.
- Tests:
  - transition map (allowed and blocked, all 11 actions × 9 statuses);
  - approve beyond slots (DB);
  - analytics formulas against hand-computed values from the seed: projected scholars, committed, projected need, surplus, extra fundable, attendance rate, on-time rate;
  - `Budget::suggestedTotal`;
  - `Interview::suggestSlots`: Mon–Sat business blocks, no overlap with Open schedules, none on holidays or other programs' days, none before the day after `application_end`;
  - password rule, and that login with an old password still works (`Auth::login`);
  - `Document::current()` returns the newest current version;
  - upload validation over HTTP (R2).
- `bin/smoke_test.php` (CLI only; refuses unless `DB_NAME` is the test database):
  1. rebuilds the test database;
  2. starts `php -S` with `-d display_errors=1 -d error_reporting=E_ALL` and a dev router;
  3. signs in as admin and as an applicant (and reviewer and treasurer, to exercise the 404 rule);
  4. requests every route: public routes, every key of `AdminController::ROUTES` (read by reflection), and every applicant route, with seed ids where a page needs `&id=`;
  5. fails on HTTP 500 or on "Warning", "Notice", "Deprecated", "Fatal error" or "Uncaught" in the output. POST-only routes requested by GET must redirect, not error.

*Outcome:* test database, PHPUnit suites (unit / integration / http) and `bin/smoke_test.php` built and green before anything moved; HTTP tests added for every write path whose SQL moved in Stage 2 — `d188740`, `0c42af6`, `5319f07`.

**Stage 2 — SQL out of controllers (§2, R5)**
- New models: `User`, `Setting`, `Holiday`, `Lookup`. Methods are added to the existing models.
- Each statement moves word for word, keeping prepared statements and parameter order. `verify_csrf()` and `require_post()` stay in the controllers.

*Outcome:* no SQL left in controllers (was 50 call sites) or in `bin/send_reminders.php` (was 10); new `User`, `Setting`, `Holiday`, `Lookup`, query methods on `AuditLog`/`Notification`, `Database::transaction()` — `d20e433`.

**Stage 3 — `render()` in ApplicantController**
- 5 manual header/footer blocks become `render(view, compact(...) + ['pageScripts' => …], title)`. `footer.php` reads `$pageScripts` from the same scope, so the scripts still load.

*Outcome:* every applicant page renders through `render()` — `935c1d2`.

**Stage 4 — split the large files (§1)**
- Every caller is updated in the same commit (R3).
- `DocumentStorage::validate()` gets its unit test.

*Outcome:* 9 large files split (counts in §9):
- 4a `6536cd5`: `DocumentStorage`;
- 4b `7b6feae`: `TermDocument`;
- 4c `843de56`: `ApplicationEligibility`, `ApplicationSearch`;
- 4d `7e63621`: `Interview` → `InterviewSlots`, `InterviewAssignment`, `InterviewScore`;
- 4e `071529e`: `Analytics` → 5 `Analytics*` classes + `WorkQueues`;
- 4f `07a24b4`: `UserController`, `SettingsController`, `AuditController`;
- 4g `5e01f63`: `ApplicantController` → 4 controllers + `ApplicantJourney`;
- 4h `618d73b`: form partials;
- 4i `3f7d5c9`: wizard JS.

**Stage 5 — inline scripts out (§3, R4)**
- Data goes through `data-*` attributes or `<script type="application/json">` produced by `js_json()`.
- Every script tag goes through `asset()`, so it keeps its `?v=`.

*Outcome:* the 18 inline scripts are now `public/assets/js/pages/*.js` and `application/flags.js`; the 36 inline handlers became `data-confirm` / `data-autosubmit` / `data-no-submit` (handled once in `ui.js`) and `data-*` payloads — `53d24ac`.

**Stage 6 — duplication (§4)**
- `peso()`, `fmt_date()`, `paginate()`, `input_*()`, `not_found()`, `Program::fromRequest()`, the new colour constants, `STAFF_ROLES`.

*Outcome:*
- 6a `30ade45`: `peso()` + `fmt_date/fmt_datetime/fmt_long_date` replace 21 money and 52 date copies;
- 6b `602831b`: `not_found()`, `require_post()`, `paginate()`, `Program::fromRequest()`, and `get_int/post_str/…` (103 reads);
- 6c `cb104d4`: `STAFF_ROLES`, the 3 colour maps, `missedCount()` reuse, `MAX_FILE_SIZE_TEXT`, the suggestion-window constants.

**Stage 7 — consistency (§6, R6, R7)**
- Validator where the messages can stay identical;
- named constants;
- the 3 parameter types;
- comments only where the reason isn't obvious.

*Outcome:* typed parameters everywhere; named constants (semesters per year, reminder hours, page sizes, list caps, OTP lifetime); `Validator::newPassword()`; stale comments fixed — `9955923`.

*Outcome (R6):* the Validator now checks registration (as before) and all four "new password + confirm" forms
(reset password, admin create user, admin reset password, My Profile) through one rule, `newPassword()`, with each
form's own wording. The remaining hand-written checks stay as they are, because moving them would change behaviour:
- they sit between database checks (e.g. "e-mail already used") whose order decides which message is shown;
- `Validator::email()` skips "empty" values with `!empty()`, so the input `"0"` would pass where the hand check refuses it (see B8).

**Stage 8 — delete proven dead code (§5)**
- The 3 CSS classes;
- whatever Validator rules are still unused.

*Outcome:* the 3 CSS rules and `Validator::min/numeric/date/in` deleted — `088964e`.

**Extra checks beyond PHPUnit and the smoke test** (done):
- the application-form browser walkthrough (47 checks) after Stages 4i, 5 and 6c, the ones that changed the wizard's JavaScript or markup;
- a browser behaviour recording of 46 pages (modals, confirm dialogs, filters, charts, calendar, locked fields, OTP countdown), identical before and after Stages 5 and 7;
- a full-site HTML snapshot of 585 pages and reports, byte-identical through Stages 6–8 except one intended data attribute.

The line counts are in §9. How the code is organised now is in `docs/CODE_ORGANISATION.md`.

---

## 9. Before / after: the split files

"Before" is the line count at the Stage 0 commit `58f9dce` (from `git show 58f9dce:<file> | wc -l`); "after" is the
current file. The new files' counts include later changes (stages 5–8, the B1 fix).

| Before (lines at `58f9dce`) | After (lines now) |
|---|---|
| `app/controllers/ApplicantController.php` **739** | `ApplicantController` 89 (route table + dashboard), `ApplicationFormController` 110, `ApplicantDocumentController` 80, `ScholarshipController` 75, `ApplicantAccountController` 65, model `ApplicantJourney` 145 |
| `app/models/Interview.php` **667** | `Interview` 254 (schedules), `InterviewSlots` 125, `InterviewAssignment` 281, `InterviewScore` 98 |
| `app/views/applicant/application_form.php` **666** | `application_form.php` 94, `form/_personal.php` 118, `form/_education.php` 77, `form/_family.php` 105, `form/_documents.php` 85, `form/_review.php` 176; its inline Returned-flags script became `application/flags.js` 31 (Stage 5) |
| `app/models/Application.php` **530** | `Application` 451, `ApplicationEligibility` 64, `ApplicationSearch` 61 |
| `app/models/Analytics.php` **527** | *(removed)* `AnalyticsFunnel` 121, `AnalyticsBudget` 89, `AnalyticsInterviews` 62, `AnalyticsClaims` 74, `AnalyticsProfiles` 190, `WorkQueues` 24 |
| `public/assets/js/application-autosave.js` **509** | *(removed)* `application/shared.js` 46, `application/field-autosave.js` 202, `application/steps.js` 40, `application/document-upload.js` 254 |
| `public/assets/js/application-review.js` **150** | renamed, unchanged (100% similarity), to `application/review.js` 150 |
| `app/controllers/AdminController.php` **474** | `AdminController` 245 (route table + dashboard, programs, applications, notifications), `UserController` 82, `SettingsController` 84, `AuditController` 27 |
| `app/models/Term.php` **369** | `Term` 304, `TermDocument` 96 |
| `app/models/Document.php` **327** | `Document` 209, `DocumentStorage` 143 |
