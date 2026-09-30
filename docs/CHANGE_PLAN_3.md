# Change plan 3 — calendar and two more analytics

Follow-up to `docs/PROCESS.md`, `docs/CHANGE_PLAN.md` and `docs/CHANGE_PLAN_2.md`. Stage 0 only: no code has been
changed. Both items are **read-only additions**: no screening, interview, claim, renewal or release rule changes.
Decisions that need your answer are in **§0**.

---

## 0. Decisions (defaults proposed — "approve with defaults" accepts all)

| # | Question | Proposed default |
|---|---|---|
| F1 | Which calendar views? | **Month and Week, day-grid only.** Only `@fullcalendar/core` + `@fullcalendar/daygrid` were ever local. The old page got the time-grid/list views from a CDN, which is no longer allowed. A timed event shows its time inside the day cell, and the list under the calendar replaces a "list" view. Adding `@fullcalendar/timegrid` would need a new npm download; say so if you want it. |
| F2 | Where "Calendar" goes in the sidebar | **Directly under Dashboard, above "Application process"** (see §1.6 for why). |
| F3 | When is a claim/renewal deadline red ("overdue")? | **Exactly when the Missed deadline list shows someone for that window.** `Analytics::overdue()` wraps `TermSchedule::missedDeadline()`, the same function behind `_missed_deadlines.php`. A window is red when its deadline has passed and at least one scholar hasn't submitted. That covers **Open and Closed** windows (not Cancelled) because the Missed-deadline list does. It differs slightly from "status still Open", but the calendar, the Claims/Renewals pages and the analytics can never disagree. A past-deadline window where everyone submitted shows grey ("Done"), not red. |
| F4 | All-day vs timed deadline event | Decided by the **deadline's own time**: 00:00 → all-day on the day before (a deadline of "Oct 10 00:00" means end of Oct 9), otherwise a timed event. The request says "if opens_at is midnight", but opens_at's time says nothing about when the deadline falls — confirm if you meant opens_at literally. |
| F5 | What a click on a calendar event does | **Highlights** the event, its day cell and the matching list row (scroll + 2 s flash + `.highlighted`). It doesn't navigate. Each list row has an **Open** link to the event's page, so there's one clear way to leave. |
| F6 | "Deadline is close" threshold | `TERM_SCHEDULE_REMINDER_HOURS` (48): the constant `bin/send_reminders.php` uses for the deadline reminder, so there's no new number. Applies to claim/renewal deadlines that are still ahead; interviews don't have a deadline. |
| F7 | Which events the list shows | The events in the calendar's **visible range**, grouped **Overdue** (red, always first) · **Today** · **This week** · **Later**. Past events that aren't overdue are left out, so the list is about what's ahead. It's built in the browser from the same feed as the calendar, so both always show the same events and colors. |
| F8 | "Same status logic as interviews.php" | The Open / Full / Done / Cancelled rule is inline in the view today. It moves to `Interview::displayStatus()` + `Interview::STATUS_COLORS`, and **both** `interviews.php` and the feed call it. The list page's output is unchanged. |
| F9 | On-time rate details | Claims only (release windows); renewals have no `submitted_at` on `scholar_terms`. `submitted_at` holds the **last** submission, so a claim re-sent after a Resubmit past the deadline counts as late. Semesters with no release window (history from before plan 2) aren't counted, and the page says how many were left out. |
| F10 | Score bands | < 50 · 50–69.99 · 70–100, one small bar chart in the existing chart style. |
| F11 | Reusing Analytics constants | `STAGES` / `OPEN_STAGES` describe application stages and don't fit attendance or claim timing. New constants: `Analytics::SCORE_BANDS` and nothing else. Program / A.Y. selection reuses the page's existing selects. |

---

## 1. Calendar (`admin/calendar`)

### 1.1 Access and routes
Admin only, like every other admin page. `AdminController::ROUTES` is default-deny and only the admin role reaches
it; there is no separate staff role since the restructure. Added entries:

| Route | Controller | Returns |
|---|---|---|
| `calendar` | `CalendarController::index` | the page |
| `calendar-feed` | `CalendarController::feed` | JSON array of events (GET; `start`, `end` from FullCalendar; optional `program_id`) |

Read-only: no POST, so no CSRF token needed. Both go through the same `Auth::requireRole('admin')` as the rest.

### 1.2 FullCalendar served locally
- Restore `node_modules/@fullcalendar/core`, `node_modules/@fullcalendar/daygrid` and `node_modules/preact`
  (6.1.21 / 10.12.1) from commit `d99840d^`, the exact files deleted in the restructure. Put their three entries back
  in `package.json` / `package-lock.json`.
- Copy `core/index.global.min.js` and `daygrid/index.global.min.js` to `public/assets/vendor/fullcalendar/`, the same
  pattern as `public/assets/vendor/chart.js/`. The v6 global bundle injects its own CSS, so no stylesheet is needed.
- Loaded only on the calendar page through the existing `$pageScripts` hook in `layouts/footer.php`. No CDN tag.
- README: "after upgrading FullCalendar, copy the two files again" (same note as Chart.js).

### 1.3 Feed (`CalendarController::feed`, queries in the models)

| Source | Event | Color |
|---|---|---|
| `interview_schedules` in range (new `Interview::schedulesInRange(start, end, ?programId)`: the same counts as `schedulesByProgram`) | "Interview — <program>", start/end = date + times, url `admin/interview-schedule&id=X` | from `Interview::displayStatus()` (F8): Open green · Full amber · Done grey · Cancelled red + struck-through (same Bootstrap colors as the list badges) |
| `term_schedules` with status ≠ Cancelled, deadline in range (`TermSchedule::inRange(start, end, ?programId)`) | "<Program> — Claim deadline" / "— Renewal deadline" (+ semester in the list), F4 all-day/timed, url `admin/release-schedule&edit=ID` / `admin/renewal-schedule&edit=ID` (opens that window) | claim blue · renewal purple; **due within 48 h** (F6) amber with a pulsing dot; **overdue** (F3) red; past with nobody missing grey |
| same row, if `planned_date` is set and in range | "<Program> — planned release" / "— planned semester start", all-day | outlined: white fill, border and text in the purpose color |

Each event carries `extendedProps` for the list: kind, state label, program, semester, when-text, overdue, dueSoon.
Colors live in one PHP map, sent with each event, so the list uses the very same values.

### 1.4 Page (`app/views/admin/calendar.php`)
- Header with a program select (`All programs` + each program), the same pattern as `admin/interviews.php`. Changing
  it refetches the feed (`?program_id=`) and remembers the choice in the URL.
- Legend: Interview open / full / done / cancelled · Claim deadline · Renewal deadline · Due within 48 h · Overdue ·
  Planned date.
- Month and Week buttons (F1); the **today** cell keeps FullCalendar's highlight. An explicit
  `.fc .fc-day-today { background: … }` rule on the page guarantees it. Nothing in `style.css` targets table
  cells today, and this protects against future changes.
- List under the calendar (F7): rows with a colored dot, title, when, state, and an Open link.
  - Click an event → the list scrolls to its row and flashes it.
  - Click a row → the calendar moves to that date and flashes the event and its day cell.
  - `.highlighted` stays on the last-selected pair.

### 1.5 Overdue: one function (F3)
`Analytics::overdue(?int $programId = null, ?string $academicYear = null, ?string $purpose = null): array` returns
`TermSchedule::missedDeadline()` rows (both purposes, or one) filtered by program / A.Y. Used by:
- the calendar feed, to mark a window red;
- the analytics page, for the "Currently overdue" count (§2.4).

`_missed_deadlines.php` keeps calling `TermSchedule::missedDeadline()` directly, and `Analytics::overdue()` is only
a filter on top of it, so all three read one query.

### 1.6 Sidebar
"Calendar" goes directly under **Dashboard**, before the "Application process" section. It shows interviews
(application process) *and* claim/renewal deadlines (releasing of budget). Under "Interviews" it would suggest it's
only about interviews and hide the deadlines. Next to the dashboard it reads as another "overview" page, which is
what it is.

### 1.7 Files
| File | Change |
|---|---|
| `app/controllers/CalendarController.php` *new* | `index()`, `feed()` |
| `app/controllers/AdminController.php` | 2 routes; `require_once` Analytics |
| `app/views/admin/calendar.php` *new* | page, legend, list, JS (FullCalendar + list sync) |
| `app/models/Interview.php` | `STATUS_COLORS`, `displayStatus(array $schedule)`, `schedulesInRange()` (read-only) |
| `app/models/TermSchedule.php` | `inRange()` (read-only) |
| `app/models/Analytics.php` | `overdue()` |
| `app/views/admin/interviews.php` | use `Interview::displayStatus()` instead of the inline rule (same output) |
| `app/views/layouts/sidebar.php` | Calendar link |
| `public/assets/vendor/fullcalendar/*` *new*, `node_modules/@fullcalendar/*`, `node_modules/preact`, `package.json`, `package-lock.json` | local library |

---

## 2. Analytics (extend `admin/analytics`)

Sections 1 and 2 stay exactly as they are. Sections 3 and 4 are added below them in the same card layout, with a
one-line formula under every number. They use the page's existing program and A.Y. selects.

### 2.3 Interview effectiveness — per program
Base: `interview_assignments` on the program's `interview_schedules` (not Cancelled schedules; still-Scheduled
attempts are excluded because they haven't happened yet).

| Function | Number | Formula shown |
|---|---|---|
| `Analytics::attendanceRate(int $programId)` | Attendance rate + "X of Y" | = Attended ÷ (Attended + No-show) |
| `Analytics::noShowByAttempt(int $programId)` | No-show rate on the 1st attempt and after 1 reschedule, each with "X of Y" | = No-show ÷ (Attended + No-show), `reschedule_count` = 0 vs 1 |
| `Analytics::scoreSummary(int $programId)` | Average score + count per band (small bar chart, F10) | = average `total_score` of scored (Attended) interviews; bands < 50 · 50–69.99 · 70–100 |

The chart answers one question: does the rubric spread people out or put everyone in the same band?

### 2.4 On-time submissions — per program + A.Y. (claims)
Base: the program's `scholar_terms` of that A.Y. whose claim was submitted (`claim_status` ≠ Waiting for Requirements,
`submitted_at` set), joined to their release window (`term_schedules` purpose `release`, same program + A.Y. + semester).

| Function | Number | Formula shown |
|---|---|---|
| `Analytics::onTimeRate(int $programId, string $academicYear)` | On-time % + "X of Y" (+ "N without a window not counted") | = submitted on or before the window's deadline ÷ submitted claims with a window |
| `Analytics::overdueCount(int $programId, string $academicYear)` | Currently overdue | = scholars in `Analytics::overdue(program, A.Y., 'release')`, the same list the calendar colors red and Claims → Missed deadline shows |
| `Analytics::avgDaysToSubmit(int $programId, string $academicYear)` | Average days | = average (submitted_at − window opens_at) in days, same base as on-time |

### 2.5 Files
| File | Change |
|---|---|
| `app/models/Analytics.php` | `SCORE_BANDS`, the six functions above, `overdue()` (§1.5) |
| `app/controllers/ReportController.php` `analytics()` | add their results to `$data` |
| `app/views/admin/analytics.php` | sections 3 and 4 (cards + 1 chart), same style |

---

## 3. Stages and commits

| Stage | Content | Commit |
|---|---|---|
| 1 | FullCalendar local files, routes, feed, page, sidebar, `Interview::displayStatus` refactor, `Analytics::overdue` | "Stage 1 (plan 3): admin calendar" |
| 2 | Analytics sections 3 and 4 | "Stage 2 (plan 3): interview and on-time analytics" |
| 3 | `php -l`; a scratch-DB check that the feed and the list pages give the same status/overdue for the same rows; `docs/TEST_CHECKLIST_3.md`; PROCESS/README | "Stage 3 (plan 3): verification and docs" |

Testing uses scratch databases only; `.env` and the live database are not touched.
