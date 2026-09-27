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
| A2 | Multi-step form: Personal, Education, Family, Documents (Certificate of Residency, Form 138 / Grades, Enrollment Form, School ID, 2x2 Photo), Review & Submit. Autosaves a draft. **(server)** Only while a program is open and inside its dates; Submit only when complete. | `applicant/application`, `applicant/application/autosave`, `applicant/documents/upload`, `applicant/documents/view`, `applicant/application/submit` | Draft → Submitted |
| A3 | Waits for screening. If Returned: the remarks are shown on the dashboard and at the top of the form; the form becomes editable; resubmit. | `applicant/dashboard`, `applicant/application` | Submitted / Returned / Rejected / Qualified |
| A4 | Waits for a schedule; then sees date, time and venue (in-app + e-mail) and gets a reminder e-mail 24 h before. | `applicant/dashboard` ("What you need to do next" box) | Qualified → Interview Scheduled |
| A5 | Attends. A first no-show goes back to "waiting for a new schedule" (one reschedule); a second no-show is Rejected. | `applicant/dashboard` | Interview Scheduled → Interviewed / Qualified / Rejected |
| A6 | Waits for the final result. | `applicant/dashboard` | Interviewed → Approved / Waitlisted / Rejected |
| A7 | Approved → Scholar; Semester 1 opens automatically. | `applicant/scholarship` *(rebuilt)* | Approved; term = Waiting for Requirements |
| A8 | Claims cash for the current semester: uploads School ID + Grades → waits → re-uploads if Resubmit → sees Released (amount, date, reference). | `applicant/scholarship`, `applicant/claim/upload` *new*, `applicant/claim/submit` *new*, `applicant/term-documents/view` *new* | claim_status |
| A9 | After the current semester is Released: uploads Enrollment Form + School ID for the next semester → waits → re-uploads if Resubmit → Approved opens the next semester (back to A8). | `applicant/scholarship`, `applicant/renewal/upload` *new*, `applicant/renewal/submit` *new* | renewal status |

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
| B2 | `admin/screening` *new* (queue: Submitted), `admin/application&id=` (one page per application, any status), `admin/screening/decide` *new*, `admin/documents/view` | One page per application: form data, every document viewable, eligibility check as pass/fail rows. Buttons: **Qualify for interview**, **Return for correction** (remarks required), **Reject** (reason required). | transition map; remarks/reason non-empty |
| B3 | `admin/interviews` *renamed from admin/interview-scheduling*, `admin/interview-schedule-save`, `-update`, `-cancel`, `-delete`, `admin/interview-schedule&id=` *renamed from interview-schedule-detail*, `admin/interview-assign` (bulk), `admin/interview-unassign` | Create a schedule (date, start/end, venue, capacity); bulk-assign Qualified applicants. Assign, edit (date/time/venue) and cancel notify the applicants. | capacity; only Qualified; delete only when empty; cancel sends everyone back to Qualified |
| B4 | `admin/interview-schedule&id=` (attendance column), `admin/interview-no-show` *new*, `admin/interview-score&id=` (saving a score marks Attended) *renamed from interview-evaluate*, `admin/interview-score-save` *renamed* | Mark Attended / No-show; score attendees with the rubric + remarks. | rubric bounds from the one constant; one reschedule |
| B5 | `admin/decisions&program_id=` *new*, `admin/decisions/decide` *new* | Ranking of Interviewed (and Waitlisted) applicants: score, GWA, family income, eligibility result; "Slots: X approved / Y total". Approve / Waitlist / Reject (reason). "Promote next waitlisted?" prompt when a first-semester scholar ends. | approve blocked when X ≥ Y |

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

Charts (Chart.js, served locally): stage funnel (bar); budget Total vs Released vs Committed vs Projected need (bar).

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

Every status change is also written to the audit log (`AuditLog::write`).

---

## 9. Kept supporting pages

`admin/applications` (read-only search of all applications — the "database of applications"),
`admin/users` (admin accounts), `admin/settings` (e-mail on/off, reminder hours, Send Test Email),
`admin/audit-logs`, `admin/notifications`, `admin/documents/view`, `admin/term-documents/view` *new*.
