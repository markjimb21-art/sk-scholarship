# SK Scholarship — The Process

This is the source of truth for the system. Every page is one step below, every status has exactly one
meaning, and every rule marked **(server)** is enforced in PHP; the page only mirrors it.

Roles: **Admin** and **Applicant**. An applicant becomes a **Scholar** when approved (same account).

Routes use the existing front controller: `index.php?r=<route>` (pretty URL `/<route>`).
Routes marked *new* don't exist yet; *renamed* ones replace an old route (see CHANGE_PLAN.md).

---

## 1. Statuses (one meaning each)

### Application (`applications.status`)

| Status | Plain-language label (shown to users) | Meaning | Who moves it next |
|---|---|---|---|
| Draft | Draft — not submitted | Applicant is still filling in the form | Applicant (Submit) |
| Submitted | Submitted — waiting for screening | Complete application waiting for B2 | Admin (B2) |
| Returned | Returned for correction | Admin listed items to fix (remarks shown) | Applicant (fix + resubmit) |
| Qualified | Qualified — waiting for interview schedule | Passed screening, no interview slot yet (also after a first no-show) | Admin (B3) |
| Interview Scheduled | Interview scheduled | Assigned to a schedule; date, time, venue sent | Admin (B4) |
| Interviewed | Interviewed — waiting for final result | Attended and scored | Admin (B5) |
| Approved | Approved — you are a scholar | Scholar record + Semester 1 created | final |
| Waitlisted | Waitlisted | Ranked below the slots; can be approved later if a slot opens | Admin (B5) |
| Rejected | Not approved | Reason shown | final |

Allowed transitions (one map in `Application::TRANSITIONS`, used by the server **and** by the views
to decide which buttons appear):

| Action | From | To | Required input |
|---|---|---|---|
| submit (applicant) | Draft, Returned | Submitted | all sections + all 5 documents; program open and inside its dates (a Returned resubmission is allowed after the end date — see decision D2) |
| qualify | Submitted | Qualified | — |
| return | Submitted | Returned | remarks |
| reject (screening) | Submitted | Rejected | reason |
| assign | Qualified | Interview Scheduled | schedule with free capacity |
| unassign / schedule cancelled | Interview Scheduled | Qualified | — |
| mark attended + score | Interview Scheduled | Interviewed | rubric scores |
| mark no-show (1st) | Interview Scheduled | Qualified | — (reschedule_count becomes 1) |
| mark no-show (2nd) | Interview Scheduled | Rejected | automatic reason "Did not attend the rescheduled interview" |
| approve | Interviewed, Waitlisted | Approved | slots not full |
| waitlist | Interviewed | Waitlisted | — |
| reject (decision) | Interviewed, Waitlisted | Rejected | reason |

### Scholar (`scholars.status`)
- **Active** — can claim and renew.
- **Ended** — with `end_reason` = Graduated / Dropped out / Failed grade requirement / Did not renew. Can't claim or renew **(server)**.

### Semester claim (`scholar_terms.claim_status`)
| Status | Label | Meaning |
|---|---|---|
| Waiting for Requirements | Upload your School ID and Grades | Semester is open; nothing uploaded yet |
| Submitted | Submitted — waiting for verification | Both documents uploaded |
| Resubmit | Please re-upload (remarks shown) | Admin asked for new documents |
| Verified | Verified — waiting for release | GWA recorded; ready for B8 |
| Released | Released | Cash released (amount, date, reference shown) |

### Renewal request (`renewal_requests.status`)
- **Submitted** — Enrollment Form + School ID uploaded for the next semester.
- **Resubmit** — admin asked for new documents (remarks shown).
- **Approved** — the next semester (`scholar_terms` row, `opened_via = renewal`) was created.

Semester order: 1st Semester → 2nd Semester (same A.Y.) → 1st Semester of the next A.Y. (no Summer).

### Interview attendance (`interview_assignments.attendance`)
Scheduled | Attended | No-show.

---

## 2. Applicant steps

| Step | What the applicant does / sees | Page (route) | Status |
|---|---|---|---|
| A1 | Register → 6-digit e-mail code → login. Forgot password uses the same OTP. | `register`, `verify-otp`, `resend-otp`, `login`, `forgot-password`, `verify-reset-otp`, `reset-password` | — |
| A2 | Multi-step form: Personal (incl. blood type, religion, citizenship), Education, Family, Documents (Certificate of Residency, Form 138 / Grades, Enrollment Form, School ID, 2x2 Photo), Review & Submit. Autosaves a draft. **(server)** Only while a program is open and inside its dates; Submit only when complete. | `applicant/application`, `applicant/application/autosave`, `applicant/documents/upload`, `applicant/documents/view`, `applicant/application/submit` | Draft → Submitted |
| A3 | Waits for screening. If Returned: the remarks are shown on the dashboard and at the top of the form; the form becomes editable; resubmit. | `applicant/dashboard`, `applicant/application` | Submitted / Returned / Rejected / Qualified |
| A4 | Waits for a schedule — and may **pick one of 3 suggested slots** (§3a), which the SK office confirms; then sees date, time and venue (in-app + e-mail), a reminder before, and "Your interview is today" on the day. | `applicant/dashboard` ("Pick an interview slot" box), `applicant/slot-request`, `applicant/slot-request/cancel` | Qualified → Interview Scheduled (only by the admin's confirm/assign) |
| A5 | Attends. A first no-show goes back to "waiting for a new schedule" (one reschedule); a second no-show is Rejected. | `applicant/dashboard` | Interview Scheduled → Interviewed / Qualified / Rejected |
| A6 | Waits for the final result. | `applicant/dashboard` | Interviewed → Approved / Waitlisted / Rejected |
| A7 | Approved → Scholar; Semester 1 opens automatically. | `applicant/scholarship` *(rebuilt)* | Approved; term = Waiting for Requirements |
| A8 | Claims cash for the current semester: uploads School ID + Grades → waits → re-uploads if Resubmit → sees Released (amount, date, reference). **(server)** Only while the admin's **claim window** for that program + semester is open (§4a). | `applicant/scholarship`, `applicant/claim/upload` *new*, `applicant/claim/submit` *new*, `applicant/term-documents/view` *new* | claim_status |
| A9 | After the current semester is Released: uploads Enrollment Form + School ID for the next semester → waits → re-uploads if Resubmit → Approved opens the next semester (back to A8). **(server)** Only while the admin's **renewal window** for the next semester is open (§4a). | `applicant/scholarship`, `applicant/renewal/upload` *new*, `applicant/renewal/submit` *new* | renewal status |

**One application at a time (server):** while an applicant holds an **Active** scholarship, their
"current" application is the one that made them a scholar and they can't start another; once the
scholarship has Ended they can apply to a later program. Between programs, the latest application is shown.

**Applicant dashboard** (`applicant/dashboard`): one progress tracker
(Apply → Screening → Interview → Result → Scholar: Claim → Renewal) with the current step highlighted,
the status in plain words, and **one** "What you need to do next" box with the single action button, or
"Nothing to do — we will e-mail you when …". Past applications and past semesters are read-only tables.

Other applicant pages kept: `applicant/notifications` (inbox), `applicant/profile` (name, contact, password).

---

## 3. Admin: application process

| Step | Page (route) | What it does | Rules (server) |
|---|---|---|---|
| B1 | `admin/programs`, `admin/program-save`, `admin/program-toggle` | Create/edit a program: name, academic year, application start/end, slots, amount per scholar per semester, minimum GWA, maximum family income, maximum age. Open / close. | end ≥ start; numbers ≥ 0; applicants can apply only when open **and** today is inside the dates |
| B2 | `admin/screening` *new* (queue: Submitted), `admin/application&id=` (one page per application, any status), `admin/screening/decide` *new*, `admin/documents/view` | One page per application: form data, every document viewable, eligibility check as pass/fail rows. Buttons: **Qualify for interview**, **Return for correction** (remarks required; tick the wrong fields/documents — see Data retention), **Reject** (reason required). Any document can be **marked invalid** (`admin/documents/invalidate`). | transition map; remarks/reason non-empty; on a flagged return only the flagged items can change and flagged documents must be re-uploaded before resubmitting |
| B3 | `admin/interviews` *renamed from admin/interview-scheduling*, `admin/interview-schedule-save`, `-update`, `-cancel`, `-delete`, `admin/interview-schedule&id=` *renamed from interview-schedule-detail*, `admin/interview-assign` (bulk), `admin/interview-unassign` | Create a schedule (date, start/end, venue, capacity); bulk-assign Qualified applicants. Assign, edit (date/time/venue) and cancel notify the applicants. | capacity; only Qualified; delete only when empty; cancel sends everyone back to Qualified |
| B4 | `admin/interview-schedule&id=` (attendance column), `admin/interview-no-show` *new*, `admin/interview-score&id=` (saving a score marks Attended) *renamed from interview-evaluate*, `admin/interview-score-save` *renamed* | Mark Attended / No-show; score attendees with the rubric + remarks. | rubric bounds from the one constant; one reschedule |
| B5 | `admin/decisions&program_id=` *new*, `admin/decisions/decide` *new* | Ranking of Interviewed (and Waitlisted) applicants: score, GWA, family income, eligibility result; "Slots: X approved / Y total". Approve / Waitlist / Reject (reason). "Promote next waitlisted?" prompt when a first-semester scholar ends. | approve blocked when X ≥ Y |

### 3a. Interview scheduling page and suggestions

`admin/interview-reschedule&application_id=` (also `&id=` an interview assignment) — reached from "Schedule" on the
Interviews page's waiting list, "Reschedule" on a schedule's applicant row, a slot-request notification, and the
first-no-show notification. It shows the applicant's pending slot request (Confirm / Decline), a ranked list of
suggestions, and a free date/time picker (`admin/interview-reschedule-save`, `admin/slot-request-decide`).

**Suggestions — `Interview::suggestSlots()`, one function for the New-schedule form, this page and the applicant:**
1. Existing **Open** schedules of the program, from tomorrow, that still have free capacity (soonest first) — not on the
   New-schedule form, and not the schedule the applicant is already on.
2. New free **2-hour blocks** (8–10, 10–12, 1–3, 3–5), Monday–Saturday, from the day after the application end date (or
   tomorrow), skipping **holidays** (Settings → Holidays), dates on which **another program** already has an interview
   day, and blocks overlapping any Open schedule.
3. Window end = the later of the application end date and today + `interview_suggestion_window_days` (Settings, 7–180,
   default 60).

**Rules (server):**
- Applicant slot requests (`slot_requests`): only while the application is Qualified (also after a first no-show); only
  one Pending request; the pick must be one of the applicant's **current** top 3 suggestions; CSRF on every POST.
- Only the admin's **Confirm** schedules the applicant — through the existing `assign` transition, which enforces
  capacity and the one-reschedule rule. A free block becomes a schedule at the venue/capacity the admin confirms (or the
  program's Open schedule at that exact time). If it can't be used any more, the request is **Declined** with the reason
  and the applicant is asked to pick again. Any assignment closes a pending request.
- Moving an upcoming interview to another schedule removes the waiting attempt and creates the new one (no no-show is
  counted) and sends one **"Interview changed"**; editing a schedule's date/time/venue also sends "Interview changed".

**Eligibility check (B2, also shown in B5)** — computed on the server from the application; each row is Pass / Fail / Not provided:
1. Age on the submission date ≤ program maximum age.
2. Total family income ≤ program maximum family income.
3. GWA ≤ program minimum GWA (Philippine scale: 1.00 highest, 5.00 failing).
4. Certificate of Residency uploaded.

A program limit left blank is shown as "No limit" and always passes. Nothing is rejected automatically.

**Interview rubric** (one constant, `INTERVIEW_RUBRIC` in `config/constants.php`):
Financial need 0–40 · Academic performance 0–30 · Communication / motivation 0–20 · Community involvement 0–10 · **Total 100**.

**Ranking order (B5):** interview total (high → low), then total family income (low → high), then GWA (better = lower number first).

**Slots used (B5 counter):** scholars created from this program, not counting scholars who were ended
before any second semester was opened (their slot is free again — that's when "Promote next waitlisted?" appears).

---

## 4. Admin: releasing of budget

| Step | Page (route) | What it does | Rules (server) |
|---|---|---|---|
| B6 | `admin/budget`, `admin/budget-save`, `admin/budget-delete` | One budget per program + academic year (total). Shows Total, Released, Remaining, Committed. | Remaining = Total − Released, where Released is always re-summed from `scholarship_releases` |
| B7 | `admin/claims` *new* (queue: Submitted), `admin/claims/view&id=` *new*, `admin/claims/decide` *new* | View School ID + Grades, type the GWA (stored on the semester), then **Verified** or **Resubmit** (remarks required). Warning if GWA is worse than the program minimum. | only Submitted claims; remarks required for Resubmit; GWA 1.00–5.00 |
| B8 | `admin/releases` *(rebuilt: "Ready to release" + history)*, `admin/release-save` | Amount pre-filled with the program's amount per semester; date, method (Cash / GCash / Bank / Check), reference no. Scholar is notified. | only Verified claims; one release per semester (`term_id` unique); blocked if no budget for program + A.Y., or if it would exceed Remaining |
| B9 | `admin/renewals` *(rebuilt queue: Submitted)*, `admin/renewals/view&id=` *new*, `admin/renewals/decide` *new* | Enrollment Form + School ID → **Approved** (opens the next semester) or **Resubmit** (remarks). | only Submitted; scholar Active |
| B10 | `admin/scholars`, `admin/scholar&id=` *renamed from scholar-view*, `admin/scholar-end` *new* | End the scholarship with a reason. Scholar is notified. | reason from the fixed list; Ended can't claim or renew |

### 4a. Claim and renewal windows (`term_schedules`)

| Page (route) | What it does | Rules (server) |
|---|---|---|
| `admin/release-schedule`, `admin/renewal-schedule`, `admin/term-schedule-save` | One window per program + academic year + semester + purpose: **release** = the cash claim of that semester (Grades + School ID); **renewal** = the renewal *into* that semester (Enrollment Form + School ID). Opens at, submission deadline, planned release date / semester start, notes, status Open / Closed / Cancelled. Lists semesters that have waiting scholars but no window yet, and **Missed deadline**. | opens < deadline; deadline in the future when created or changed; planned date ≥ deadline date; one per program + A.Y. + semester + purpose |

- **Window open** = status Open and opens_at ≤ now ≤ deadline. Before that, the scholar sees "Not open yet" and no upload form; after it, "The deadline has passed — contact the SK office".
- A re-upload the admin asked for (**Resubmit**) is still allowed after the deadline, unless the window is Cancelled.
- Creating a window, changing its deadline and cancelling it notify every scholar who hasn't submitted (in-app + e-mail). A reminder goes out 48 h before the deadline (`bin/send_reminders.php`, once per window via `deadline_reminder_sent_at`, reset when the deadline changes).
- Missed deadlines are listed on Claims, Renewals and the window pages. Nothing happens automatically: extend the deadline, follow up, or end the scholarship (B10).

### 4b. Document versions

A School ID or Enrollment Form uploaded during a claim or renewal becomes the applicant's **current** version (`is_current`). Older versions — in `documents` or `term_documents` — are marked superseded (`superseded_at`), never deleted, and audit-logged (`Document::supersede`). The application view and scholar view show the current version (`Document::current`) and a **Document history** list (`Document::history`). A claim or renewal review always shows what was submitted for it.

**Suggestions (never filled in without a click):** B3 "New schedule" offers up to 5 free 2-hour slots, and the scheduling page and applicant dashboard rank existing schedules first — rules in §3a (`Interview::suggestSlots`). B6 offers a total budget = slots × amount per semester × 2 — `Budget::suggestedTotal`.

---

## 5. Reports (`admin/reports`, `admin/reports/generate`) — PDF and CSV

Every report takes Program and Date range; each filter is applied to the column named here, and if a
filter doesn't apply the report header says so.

| # | Report | Program filter | Date filter |
|---|---|---|---|
| 1 | Applicant list by status | applications.program_id | applications.submitted_at |
| 2 | Interview schedule & attendance | interview_schedules.program_id | interview_schedules.interview_date |
| 3 | Interview results / ranking | applications.program_id | interview_evaluations.evaluated_at |
| 4 | Scholar masterlist | scholars.program_id | scholars.approval_date |
| 5 | Releases (disbursement) | scholars.program_id | scholarship_releases.release_date |
| 6 | Budget utilization | budgets.program_id | releases counted by release_date |
| 7 | Claims & renewals status | scholars.program_id | scholar_terms.created_at / renewal_requests.created_at |
| 8 | Program summary (one page) | required (one program) | submissions and releases inside the range |

---

## 6. Data analytics (`admin/analytics`) — formulas shown under every number

All functions live in `app/models/Analytics.php`, one small function per number.

### (1) Possible scholars — per program
- **Stage counts:** number of the program's applications currently in Submitted, Returned, Qualified, Interview Scheduled, Interviewed, Approved, Waitlisted, Rejected.
- **"Reached a stage"** (needed for conversion rates): Submitted = `submitted_at` set · Returned = `returned_at` set · Qualified = `qualified_at` set · Interview Scheduled = has an interview assignment · Interviewed = has an interview score · Waitlisted = `waitlisted_at` set.
- **Conversion rate of stage S** = applications in **closed** programs that reached S and ended Approved ÷ applications in closed programs that reached S.
  A program is *closed* when the admin has closed it and its end date has passed.
  If there are no closed programs, the same formula is applied to this program's applications that already have a final result, labelled **"based on current data only"**.
- **Projected scholars** = Approved + Σ over open stages (Submitted, Returned, Qualified, Interview Scheduled, Interviewed, Waitlisted) of (count in stage × conversion rate of stage), capped at the program's slots.
- **Slots remaining** = slots − slots used. **Waitlisted available** = count of Waitlisted.

### (2) Possible budget — per program + academic year
- **Committed** = Σ over Active scholars of the program (amount per semester × semesters of this A.Y. not yet released to them), where a scholar has 2 semesters per A.Y.
- **Projected need** = Committed + (Projected scholars − current Active scholars) × amount per semester × 2.
- **Remaining** = Total budget − Released.
- **Surplus / Shortfall** = Remaining − Projected need (positive = surplus, negative = shortfall).
- **Extra scholars fundable** = floor(Surplus ÷ (amount per semester × 2)), 0 when there is a shortfall.

### (3) Interview effectiveness — per program
Base: interview attempts on the program's schedules that aren't cancelled and that happened (Attended or No-show).
- **Attendance rate** = Attended ÷ (Attended + No-show).
- **No-show rate** on the first attempt (`reschedule_count` = 0) vs after the one reschedule (`reschedule_count` = 1), each = No-show ÷ attempts.
- **Average score** = average `total_score` of scored interviews; count per band < 50 · 50–69.99 · 70–100 (`Analytics::SCORE_BANDS`).

### (4) On-time claim submissions — per program + academic year
Base: the program's semesters of that A.Y. whose claim was submitted, joined to their release window (`term_schedules`).
- **On-time rate** = submitted (last submission) on or before the window's deadline ÷ submitted claims with a window. Claims from before windows existed are not counted.
- **Currently overdue** = scholars in `Analytics::overdue(program, A.Y., 'release')` — the same list as Claims → Missed deadline and the red deadlines on the calendar.
- **Average time to submit** = average (submitted_at − window opens_at), in days.

Charts (Chart.js, served locally): stage funnel (bar); budget Total vs Released vs Committed vs Projected need (bar); interview score bands (bar).

---

## 7. Admin dashboard (`admin/dashboard`)

"Needs your action" counters, each linking to its queue:

| Counter | Formula | Links to |
|---|---|---|
| Applications to screen | applications with status Submitted | `admin/screening` |
| Qualified, no schedule | applications with status Qualified | `admin/interviews` |
| Interviews today | assignments with attendance Scheduled on a non-cancelled schedule dated today | `admin/interview-schedule&id=` / `admin/interviews` |
| Awaiting a decision | applications Interviewed | `admin/decisions` |
| Claims to verify | scholar_terms with claim_status Submitted | `admin/claims` |
| Verified, to release | scholar_terms with claim_status Verified | `admin/releases` |
| Renewals to review | renewal_requests with status Submitted | `admin/renewals` |

Below them, for the selected program: slots used / total, and budget remaining for its current A.Y.

---

## 8. Notifications (in-app + e-mail via `Notification::send(..., sendEmail: true)`)

| Event | Trigger (server) |
|---|---|
| Returned for correction (with remarks) | B2 return |
| Not approved (with reason) | B2 reject, 2nd no-show, B5 reject |
| Qualified for interview | B2 qualify |
| Interview scheduled (date, time, venue) | B3 assign |
| Interview changed | B3 schedule edit that changes date, time or venue |
| Interview cancelled | B3 cancel / unassign |
| Interview reminder (24 h before; hours = setting) | `bin/send_reminders.php` (CLI, idempotent via `interview_assignments.reminder_sent_at`) |
| Missed interview — you'll get a new schedule | 1st no-show |
| Approved | B5 approve (also a promoted waitlisted applicant) |
| Waitlisted | B5 waitlist |
| Claim: please re-upload (remarks) | B7 resubmit |
| Claim verified | B7 verify |
| Cash released (amount, date, reference) | B8 release |
| Renewal: please re-upload (remarks) | B9 resubmit |
| Renewal approved — next semester open | B9 approve |
| Scholarship ended (reason) | B10 |
| Claim / Renewal open: submit by <deadline> | window created (§4a) |
| Claim / Renewal deadline changed | window deadline changed or reopened |
| Claim / Renewal window cancelled | window cancelled |
| Claim / Renewal reminder (48 h before the deadline) | `bin/send_reminders.php` (idempotent via `term_schedules.deadline_reminder_sent_at`) |
| Your interview is today (time, venue, application code, arrive 15 minutes early) | daily digest in `bin/send_reminders.php`, first run of the day (Settings → Daily interview digest) |

**Notifications to admins** — `Notification::sendToAdmins()` writes to every *active* user with the admin role.
In-app always; e-mailed only when Settings → "Also e-mail admins about new submissions" is on (the digest is e-mailed
whenever e-mail is on). Each links to the item in its queue.

| Event | Trigger (server) |
|---|---|
| New application submitted / Application resubmitted | A2 submit / A3 resubmit (`Application::submit`) |
| Claim submitted | A8 (`Term::submitClaim`) |
| Renewal submitted / resubmitted | A9 (`Renewal::submit`) |
| First no-show — reschedule needed | B4 first no-show (`Interview::markNoShow`) |
| Interview slot requested | applicant picks a suggested slot (§3a) |
| Interviews today: N schedule(s) — time, venue, applicants, how many not yet marked | daily digest, first run of the day; idempotent via `system_settings.daily_digest_sent_on` (claimed before sending, given back on failure) |

The admin dashboard also lists today's interviews (time, venue, program, applicants, how many not yet marked).

Every status change is also written to the audit log (`AuditLog::write`).

---

## 8a. Data retention

Correct data is kept; only data explicitly flagged as invalid is removed — and even then the record stays.

**Never hard-deleted (no code path deletes them):** applicants, applications, scholars, semesters, renewal requests, releases,
application documents and claim/renewal documents, record history, audit logs.

- **Re-uploads are versions.** A new application, claim or renewal upload adds a row; the older one is superseded
  (`is_current = 0`, `superseded_at`) and its file kept (`Document::storeApplicationDocument`, `Term::uploadDocument`,
  `Document::supersede`), with an audit entry. Every version is listed under **Document history** on the application and
  scholar pages.
- **Invalid documents.** The only way a document file is deleted: an admin (any document, or ticking "invalid — remove
  file" on Return / claim / renewal Resubmit) or the applicant ("This is the wrong file", own documents that may still
  change) flags it invalid with a reason (`Document::markInvalid`). The row is kept with status `Invalid`, the reason,
  who and when, and `file_removed_at`; it no longer counts as uploaded. Audit-logged.
- **Field history.** Education is updated in place (no delete + re-insert); changes to GWA, school, course, year level
  and family income are kept in `record_history` (old → new, who, when) and shown as **Changes to key information**.
- **Flagged returns.** On Return the admin ticks the wrong fields/documents (`applications.flagged_fields`). The
  applicant sees them highlighted; every other field and document stays exactly as submitted (the server resets
  unflagged fields on save and refuses unflagged uploads). Resubmitting needs a new upload of each flagged document.
  Ticking nothing reopens the whole form (as before).

**What can be removed, and by whom (admin only):**

| Item | When | Where |
|---|---|---|
| Interview schedule | only while nobody is assigned (otherwise cancel it) | schedule page → Delete |
| Budget | only while nothing was released against it | Budget → delete |
| Document **file** | only when flagged invalid (row kept) | as above |
| Holiday | any time (a setting, not a record) | Settings → Holidays |

A waiting interview attempt that is moved or unassigned is removed (it never happened); attempts with attendance
recorded are kept.

---

## 9. Kept supporting pages

`admin/calendar` + `admin/calendar-feed` (read-only calendar of interview schedules, claim/renewal deadlines and
planned dates; interview colours from `Interview::displayStatus()` like the Interviews list, red deadlines from
`Analytics::overdue()` like Missed deadline, amber within 48 h like the deadline reminder; a list below the calendar is
linked both ways),
`admin/applications` (read-only search of all applications — the "database of applications"),
`admin/users` (admin accounts), `admin/settings` (e-mail on/off, reminder hours, Send Test Email),
`admin/audit-logs`, `admin/notifications`, `admin/documents/view`, `admin/term-documents/view` *new*.
