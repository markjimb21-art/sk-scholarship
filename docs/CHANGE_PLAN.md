# Change Plan — restructure around docs/PROCESS.md

Status: **Implemented (Stages 1–7).** Approved with the default for every decision in §0.

### Changes made during implementation (beyond the plan below)
- `interview_assignments.application_id` is **no longer unique**: one row per scheduling attempt, so a missed
  interview stays on record after the reschedule (the application's latest row is its current one).
- `renewal_requests`, `scholar_terms` and `term_documents` gained a `submitted_at` (queue order + reports).
- Staff/official accounts (D1) were deactivated **and moved to the admin role** (required to delete the two
  roles); an admin must explicitly re-activate them.
- Admin account **deletion** (`user-delete`) was removed — deactivation covers it and deleting accounts with
  records attached always failed.
- The **renewal reminder** e-mail in `bin/send_reminders.php` was removed (not in the process; its table is gone).
- An **Active scholar can't start another application**; after the scholarship Ended they can apply again.
- Release rules: a reference number is required for GCash, Bank and Check; a budget can only be deleted while
  nothing has been released against it.

Audit basis: every file under `app/`, `core/`, `config/`, `public/`, `bin/`, both SQL files and the
migrations, plus read-only queries against the local `sk_scholarship` database (27 Sep 2026).

---

## 0. Decisions needed before Stage 1

| # | Question | Proposed default |
|---|---|---|
| D1 | **Staff and official accounts** (see §6). Convert to admin, deactivate, or delete? | Deactivate both, then drop the `staff`/`official` roles; I won't delete accounts unless you say so |
| D2 | May a **Returned** applicant resubmit after the program's end date? | Yes — the original submission was on time and the admin asked for the fix |
| D3 | **Which semester opens on approval?** B1 has no semester field. | Always the 1st Semester of the program's academic year; drop `scholarship_programs.semester`. **Changed by CHANGE_PLAN_4:** programs have no academic year; it is the 1st Semester of the *current* academic year. |
| D4 | Live data: scholars #1 and #2 exist, but their applications are "For Interview" and "Interview Completed", not Approved. | Set any application that has a scholar record to **Approved** |
| D5 | Extra deletions beyond your list (marked ★ in §3): civil status, citizenship, the stored `age` and duplicate `email` in personal info, `combined_income`, the schedule title / interviewer / remarks, assignment instructions, `applicants.application_code`, the legacy password-reset **link**, release "recipient confirmed" | Delete all ★ items |
| D6 | `schools` and `courses` tables are used — only as autocomplete suggestions in Step 2. | Keep them (they keep school and course names consistent in reports) |
| D7 | The working tree has **44 uncommitted changes** made before this restructure (including the 27 Sep fixes). | Commit them first as a baseline on a new branch `restructure-process`, then one commit per stage |

---

## 1. KEEP (unchanged in purpose)

| Item | Where | Why |
|---|---|---|
| Front controller, `.env` loading, error handling, security headers | `public/index.php`, `config/config.php`, `config/database.php` | infrastructure |
| OTP registration + password reset, login lockout, sessions, CSRF | `core/Auth.php`, `core/Otp.php`, `core/Helpers.php`, `app/controllers/AuthController.php`, `app/views/auth/*`, table `email_otp_codes` | A1, objective 4 |
| Private document storage (random names, content-type check, streaming with ownership check) | `Document::absolutePath/send`, `ApplicantController::uploadDocument`, `storage/documents/` | A2 and reused for claim/renewal files |
| Audit log + viewer | `core/AuditLog.php`, table `audit_logs`, `admin/audit-logs` | every status change is logged |
| Notifications inbox + branded e-mail | `core/Notification.php`, table `notifications`, `applicant/notifications`, `admin/notifications` | objective 5 |
| Application wizard + autosave | `applicant/application*`, `public/assets/js/application-autosave.js`, `app.js` | A2 |
| Puroks + address composition | table `puroks`, `ApplicantController::composeAddress` | address is contact data; Certificate of Residency is checked in B2 |
| Budget model + sync-from-releases | `app/models/Budget.php` | B6 |
| Program management | `app/models/Program.php`, `admin/programs` | B1 |
| Reminder script | `bin/send_reminders.php` | 24 h reminder |
| PDF/CSV streaming | `AdminController::streamCsv/streamPdf` (DomPDF) | objective 7 |
| Admin user management (admin accounts only) | `admin/users`, `user-save`, `user-toggle`, `user-reset`, `user-delete` | objective 4 (someone has to create admins) |
| Chart.js served locally | `public/assets/vendor/chart.js/` | analytics + dashboard |

---

## 2. CHANGE

### 2.1 Code

| Item | Change |
|---|---|
| `Application::TRANSITIONS` | Replace with the 9-status map in PROCESS.md §1; views render buttons from it. |
| `ApplicantController` | Keep the wizard; the Documents step lists the 5 new types; `applicant/dashboard` becomes the single tracker + "What you need to do next" box; `applicant/scholarship` becomes the Semesters page (A7–A9) with claim/renewal uploads. |
| `AdminController` (2,199 lines) | Split into `AdminController` (dashboard, users, settings, audit, notifications, reports, analytics), `ScreeningController` (B2), `InterviewController` (B3–B5 incl. decisions), `FundsController` (B6–B10). Same `ACCESS` default-deny pattern, all `ROLES_ADMIN`. |
| `app/models/Interview.php` | Attendance (Scheduled / Attended / No-show), reschedule rule, rubric from `INTERVIEW_RUBRIC`, notify on assign/edit/cancel, reset `reminder_sent_at` on reschedule. |
| `app/models/Scholar.php` | Active/Ended + end reason; terms, claims, releases, renewals move to new `app/models/Term.php` and `app/models/Renewal.php`. |
| `app/models/Analytics.php` | Rewrite: only the functions in PROCESS.md §6 + dashboard counters. |
| `app/models/Budget.php` | Drop `allocated`; add `committed()`; releases join terms. |
| `app/models/Program.php` | Fields per B1; add `amount_per_semester`; drop `budget_amount`, `citizenship_required`, `required_residency`, `description`, `semester` (D3). |
| Reports | Replace the 19-report catalog with the 8 in PROCESS.md §5; keep the bind-only-used-placeholders fix. |
| `app/views/admin/settings.php` + `settingsSave` | Only e-mail on/off, reminder hours, Send Test Email (`settings/test-email` is in `ACCESS` since 27 Sep; keep it). |
| `app/views/layouts/sidebar.php` | Admin: Dashboard · Programs · Screening · Interviews · Decisions · Claims · Releases · Renewals · Scholars · Budget · Applications · Reports · Analytics · Users · Settings · Audit Logs. Applicant: Dashboard · My Application · My Scholarship · Notifications · Profile. |
| `app/views/layouts/footer.php`, `header.php` | Remove the FullCalendar script + CSS. |
| `config/constants.php` | New `APP_STATUS`, `DOCUMENT_TYPES` (5), `SCHOLAR_END_REASONS`, `CLAIM_STATUS`, `RENEWAL_STATUS`, `INTERVIEW_RUBRIC`, `PAYMENT_METHODS`; delete `DOC_STATUS` extras, `INTERVIEW_STATUS`, `SCHOLAR_STATUS`. |
| `package.json` | Remove `composer`, `@fullcalendar/core`, `@fullcalendar/daygrid`; keep `chart.js`. |
| `README.md` | Roles, process link, migration order. |

### 2.2 Database (one migration `database/migrations/2026_09_28_restructure.sql`, mirrored into `sk_scholarship.sql` and `schema.sql`)

Run the three `2026_09_27_*` migrations first (your local database hasn't had them yet).

| Table | Change |
|---|---|
| `applications` | `status` ENUM → Draft, Submitted, Returned, Qualified, Interview Scheduled, Interviewed, Approved, Waitlisted, Rejected. Add `qualified_at`, `waitlisted_at` (for the "reached stage" analytics). The existing `review_remarks*` become the "returned" remarks (`review_remarks_at` = returned_at). |
| `documents` | `document_type` ENUM → Certificate of Residency, Form 138 / Grades, Enrollment Form, School ID, 2x2 Photo. `status` ENUM → Not Submitted, Submitted, Verified. |
| `scholarship_programs` | Add `amount_per_semester DECIMAL(12,2)`. `is_active` keeps meaning "open". |
| `scholars` | `status` ENUM → Active, Ended; add `end_reason` ENUM(Graduated, Dropped out, Failed grade requirement, Did not renew), `ended_at`. |
| `interview_assignments` | Add `attendance` ENUM(Scheduled, Attended, No-show), `reschedule_count TINYINT`; keep `reminder_sent_at`. |
| `interview_evaluations` | Keep `financial_need_score` (0–40), `academic_score` (0–30), `motivation_score` → **renamed** `communication_score` (0–20), `community_score` (0–10), `total_score`, `interview_remarks` → **renamed** `remarks`, `interviewer_id`, `evaluated_at`. |
| `interview_schedules` | `status` ENUM → Open, Cancelled (Full and Completed are derived from count and date). |
| `scholarship_releases` | Add `term_id` UNIQUE (FK `scholar_terms`); `payment_method` ENUM → Cash, GCash, Bank, Check. |
| `budgets` | Keep `total_budget`, `released`, `remaining`. |
| **new** `scholar_terms` | id, scholar_id, academic_year, semester, opened_via (approval, renewal), claim_status (Waiting for Requirements, Submitted, Resubmit, Verified, Released), gwa, remarks, reviewed_by, reviewed_at, created_at; UNIQUE (scholar_id, academic_year, semester). |
| **new** `term_documents` | id, term_id, purpose (claim, renewal), type (School ID, Grades, Enrollment Form), file_path, original_filename, file_size, mime_type, status (Submitted, Resubmit, Verified), remarks, uploaded_at; UNIQUE (term_id, purpose, type). Files in `storage/term-documents/<term_id>/`. |
| **new** `renewal_requests` | id, scholar_id, from_term_id, academic_year, semester (target), status (Submitted, Resubmit, Approved), remarks, reviewed_by, reviewed_at, created_at; UNIQUE (from_term_id). |
| `system_settings` | Keep rows `email_notifications`, `interview_reminder_hours`. |

---

## 3. DELETE (verified by searching the code; ★ = beyond your list, see D5)

### 3.1 Routes, pages, code

| What | Where | Why |
|---|---|---|
| Admin override | `applications/update` action `admin_override`, `Application::canBypassTransitions`, override modal + badges in `application_view.php` | no override step in the process |
| Separate document verification | routes `documents` (workbench), `documents/review`, `documents/verify-all`; action `verify_documents`; `app/views/admin/documents.php`; `Document::verifiedCount/allVerified/pendingCount/updateStatus` | merged into B2 screening |
| Old intermediate steps + buttons | actions `start_review`, `for_interview`, `for_final`, `mark_incomplete` (→ `return`); status badges for Under Initial Review, Documents Under Review, Documents Verified, For Final Evaluation | replaced by the 9-status map |
| Interview calendar | routes `interview-calendar`, `interview-calendar-feed`; `app/views/admin/interview_calendar.php`; FullCalendar CSS (`header.php`) + JS (`footer.php`); SweetAlert2 (only used there); links in `dashboard.php`, `interview_scheduling.php`, `sidebar.php` | the schedule list is enough |
| Interview evaluation list + old result field | route `interview-evaluation`, `app/views/admin/interview_evaluation_list.php`; "result" select in `interview_evaluate.php` | scoring happens on the schedule page (B4); the decision is B5 |
| Staff-created renewals + renewal detail | routes `renewal-save`, `renewal-update`, `renewal-delete`, `renewal-view`; `app/views/admin/renewal_view.php`; renewal modal in `scholar_view.php` | renewals are applicant-initiated (A9 / B9) |
| Academic Records page | routes `academic-records`, `academic-record-save`, `academic-record-delete`; `app/views/admin/academic_records.php`; modal + tab in `scholar_view.php`; `Scholar::academicRecords/saveAcademicRecord/deleteAcademicRecord/standingFromGpa/atRiskScholars` | grades come from claims (B7) |
| Scholar status change | route `scholar-status` | only Active → Ended (B10) |
| Applicants registry | route `applicants`, `app/views/admin/applicants.php`, `Applicant::search/count` | duplicate of the applications list |
| Standalone "My Documents" + "My Interview" pages | routes `applicant/documents` (page only; upload/view stay), `applicant/interview`; `app/views/applicant/documents.php`, `interview.php` | one page per step: documents are A2's Step 4, interview info is the dashboard's next-step box |
| Release delete | route `release-delete` | a release is final; mistakes are fixed with the audit trail, not deletion |
| Analytics beyond PROCESS §6 | `Analytics::applicantsBySchool/ByCourse/ByYearLevel/ByCivilStatus/ByPurok/ByIncomeRange/avgParentalIncome/interviewResults/interviewAttendanceByMonth/interviewScoresByCriteria/gpaDistribution/gpaTrendByYear/gpaBySchool/atRiskCount/releaseByYear/releaseByMonth/scholarsByStatus/renewalRate/budgetUtilization/applicationsTrend/kpis`; all their charts in `analytics.php` and `dashboard.php` (status pie, 12-month trend, at-risk list, KPI cards) | not in the process |
| 11 report types | `interview_schedule`, `interview_attendance` (merged into #2), `approved`, `rejected`, `waitlisted` (→ #1 with status), `documents`, `noshow`, `scholars_school`, `scholars_course`, `scholars_year`, `academic`, `graduation`, `annual` (→ #8) | only the 8 in PROCESS §5 |
| Settings that do nothing | `site_name`, `barangay_name`, `max_file_size_mb`, `allowed_doc_types`, `application_open` (the program's open/close + dates replace it) — rows, form fields and `settingsSave` keys | never read, or replaced |
| ★ Legacy password-reset link | `AuthController::resetPasswordViaToken` and the `?token=` branch of `resetPassword` | OTP reset replaced it; old links expired after 60 min |
| Legacy uploads fallback | `UPLOAD_PATH` in `config.php`, `uploads/documents/` branch of `Document::absolutePath`, folder `public/uploads/` (tracked in git) | 5 files (application #1) are moved into `storage/documents/1/` with random names first, and their `file_path` rows updated by the migration |
| Staff / official roles | `ROLES_STAFF`, `ROLES_ALL` in `AdminController`; role checks in `index.php`, `sidebar.php`, `application_view.php`, `scholar_view.php`, `users.php`, `documents.php` | Admin + Applicant only (accounts per D1) |
| npm packages | `composer`, `@fullcalendar/core`, `@fullcalendar/daygrid` in `package.json` and `node_modules/` | unused |

### 3.2 Tables and columns

| Table / column | Why |
|---|---|
| table `academic_records` | data moved into `scholar_terms.gwa` |
| table `scholarship_renewals` | data moved into `renewal_requests` |
| table `barangays` (+ `puroks.barangay_id` FK) | no code reads it; the barangay is the constant `ADDRESS_BARANGAY` |
| `applicants.is_resident`, `applicants.residency_verified` | never read or written |
| ★ `applicants.application_code` | a second "application code" next to each application's own code; confusing on the dashboard |
| `applicants_personal_information.height_cm, weight_kg, blood_type, religion` | not eligibility or contact |
| ★ `…civil_status`, `…citizenship` | same rule; not in the B2 eligibility check |
| ★ `…age` | stale stored copy; eligibility computes age from `date_of_birth` |
| ★ `…email` | always a copy of `users.email` |
| ★ `education_records.school_id, course_id` | always NULL; names are stored |
| ★ `family_background.combined_income` | always equal to `total_family_income` |
| `applications.admin_override, override_reason` | override deleted |
| `applications.eligibility_score, eligibility_notes` | eligibility is computed on the page from the (locked) submitted data, so a stored copy can't disagree |
| `documents.reviewed_at, reviewed_by` | screening decides the whole application; recorded on `applications.reviewed_*` and in the audit log |
| `scholarship_programs.budget_amount` | the budgets table is the source of truth |
| `scholarship_programs.citizenship_required, required_residency, description, semester` | not in B1 (D3) |
| `scholars` statuses On Probation, Suspended, Graduated, Withdrawn, Disqualified | Active / Ended + reason |
| ★ `interview_schedules.title, interviewer, remarks` | B3 is date, time, venue, capacity |
| `interview_assignments.assignment_status, confirmed_at` | replaced by `attendance` |
| ★ `interview_assignments.instructions, assigned_by` | not in B3 (the audit log records who assigned) |
| `interview_evaluations.purpose_score, overall_score, max_possible_score, rating, result, recommendation, additional_notes` | fixed 4-part rubric only |
| ★ `scholarship_releases.recipient_confirmed, confirmed_at`; `academic_year, semester` (now from the term) | not in B8; avoids two copies of the semester |
| `budgets.allocated`, ★ `budgets.notes` | replaced by computed Committed |
| ★ `users.profile_picture, verify_token, reset_token, reset_expires` | never read (photo is a document; OTP replaced tokens) |
| roles `staff`, `official` | after D1 |
| settings rows listed in §3.1 | do nothing |

---

## 4. Status and data migration mapping (runs before any DROP)

**Applications**

| Old | New |
|---|---|
| Draft | Draft |
| Submitted, Under Initial Review, Documents Under Review | Submitted |
| Incomplete | Returned |
| Documents Verified, For Interview | Qualified (`qualified_at` = `updated_at`) |
| Interview Scheduled | Interview Scheduled |
| Interview Completed, For Final Evaluation | Interviewed |
| Approved / Waitlisted / Rejected | same (`waitlisted_at` = `decided_at` for Waitlisted) |
| any status, but a scholar record exists (D4) | Approved |

**Documents:** type Profile Picture → 2x2 Photo, Form 138 → Form 138 / Grades. Status: Under Review, Rejected, Resubmission Required → Submitted.

**Interview assignments:** For Interview, Scheduled, Confirmed, Rescheduled → Scheduled · Completed → Attended · No Show → No-show (`reschedule_count` = 0) · Cancelled → row deleted, application back to Qualified.

**Interview evaluations** (old criteria were 0–20 each): financial × 2 → 0–40, academic × 1.5 → 0–30, motivation × 1 → communication 0–20, community × 0.5 → 0–10; total recomputed; old `purpose`/`overall` are dropped. This is an approximation — only 1 row exists (application #13, old total 100).

**Scholars:** Active, On Probation → Active · Graduated → Ended/Graduated · Withdrawn → Ended/Dropped out · Disqualified, Suspended → Ended/Failed grade requirement (no rows today). `ended_at` = `updated_at`.

**Terms, from existing data:**
1. Every release → a `scholar_terms` row (scholar, A.Y., semester, `claim_status` Released, `opened_via` approval for the scholar's first term, renewal otherwise); `scholarship_releases.term_id` set.
2. Every `academic_records` row → its term's `gwa` (creating the term as Verified if no release exists).
3. Every `scholarship_renewals` row → `renewal_requests`: Renewed, Eligible for Renewal → Approved (the target term is created, `opened_via` renewal, Waiting for Requirements); For Review → Submitted; Not Eligible, Renewal Denied → Resubmit with the old remarks. `from_term_id` = the scholar's latest term before the target.
4. Every Active scholar without any term → 1st Semester of the program's A.Y., `opened_via` approval, Waiting for Requirements.

Applied to today's local data: scholar #1 gets 2025-2026 1st Sem (Released ₱3,000, GWA 1.00) and 2026-2027 2nd Sem (from the approved renewal, Waiting for Requirements); scholar #2 gets 2025-2026 1st Sem (Waiting for Requirements); applications #1 and #13 become Approved (D4); #22 stays Draft.

**Programs:** `amount_per_semester` = 0 until you set it in B1 (the release form refuses 0).
**Budgets:** `allocated`, `notes` dropped; `released` re-synced from releases per program + A.Y.

---

## 5. Stages after approval

1. Database migration + both SQL files. 2. Applicant side. 3. B1–B5. 4. B6–B10. 5. Reports, analytics,
dashboard, notifications, reminders. 6. Deletions + reference sweep. 7. `php -l` on every file, grep for
deleted routes/columns/statuses, full walkthrough against a scratch copy of the database, and
`docs/TEST_CHECKLIST.md`. One commit per stage on `restructure-process` (D7).

---

## 6. Existing staff / official users (decision D1)

| id | Name | E-mail | Role | Active | Last login |
|---|---|---|---|---|---|
| 10 | staff | staff@gmail.com | staff | yes | 2026-09-25 17:57 |
| 11 | Official | official@gmail.com | official | yes | 2026-09-25 18:00 |

For reference, the admin accounts are #2 Admin (admin@gmail.com) and #29 Admin Mark (admin1@gmail.com, never logged in).
