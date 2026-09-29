# Change plan 2 — scheduling, suggestions and document history

Follow-up to the restructure (`docs/PROCESS.md`, `docs/CHANGE_PLAN.md`, `2026_09_28_restructure.sql`).
Stage 0 only: nothing below has been changed yet. Decisions that need your answer are in **§0**.

Audited against the **live** database `sk_scholarship` on 2026-09-30. `scholar_terms`,
`term_documents`, `renewal_requests`, `budgets` and `scholarship_releases` exist, so the restructure
migration has been applied.

---

## 0. Decisions (defaults proposed — "approve with defaults" accepts all)

| # | Question | Proposed default |
|---|---|---|
| E1 | When is a claim / renewal "open"? | **Computed, not a new status.** `claim_status` stays `Waiting for Requirements`; the upload form shows only while a matching `term_schedules` row is `Open` and `opens_at ≤ now ≤ submission_deadline`. No change to the claim status map or the plain-language labels. |
| E2 | The admin sends a claim/renewal back (**Resubmit**) after the deadline has passed | The scholar **may still re-upload** while the schedule is not Cancelled (the admin asked for it). Only *first* submissions are cut off by the deadline. |
| E3 | Extending a deadline | Edit the schedule. A later deadline clears `deadline_reminder_sent_at` (a new 48 h reminder is sent), and affected scholars get a "Deadline changed" e-mail and in-app notification. |
| E4 | Do cancelled interview schedules block a suggested slot? | **No.** Only `Open` schedules count, because a cancelled one no longer uses the admin's time. |
| E5 | Suggestion window when `application_end + 1` is already in the past | Start from **tomorrow**. The window still ends 60 days after `application_end`. If that has also passed, show "No suggestions — the 60-day window after the application period has passed." |
| E6 | Application-time documents (step A2) re-uploaded while still a Draft/Returned | **Keep replacing in place**, as today, so there are no history rows for draft fixes. An A2 upload still calls `Document::supersede()` so that a *new* application's School ID becomes current over an older one. |
| E7 | Re-uploads inside one claim/renewal (after Resubmit) | **Kept as history.** Every claim/renewal upload inserts a new `term_documents` row, and no term document file is deleted any more. |
| E8 | What `is_current` means | "Newest version of this document type for this applicant", set the same way for every type. It only has a cross-table effect for **School ID** and **Enrollment Form**, the only types named the same in both tables (`Grades` ≠ `Form 138 / Grades`). |
| E9 | Blood type input | A dropdown (A+, A−, B+, B−, AB+, AB−, O+, O−, or blank). The server rejects anything else. |
| E10 | Existing scholars mid-claim when this is deployed | **No schedules are created automatically.** Until you create a release schedule for their semester, their upload form shows "not open yet". Scholars already *Submitted/Verified* are unaffected. The checklist tells you to create schedules right after upgrading. |
| E11 | Migration file name | One file, `2026_09_30_scheduling_and_docs.sql`, holds items 1, 4 and 5 (your Stage 1 wording), not a separate `…_restore_personal_fields.sql`. |

---

## 1. Restore blood type, religion, citizenship

**Live schema check:** all three columns were **dropped** from `applicants_personal_information` by
`2026_09_28_restructure.sql` (lines 347–349). The live table now has only id, applicant_id, names,
suffix, date_of_birth, complete_address, purok_id, contact_number, updated_at. `backup_2026_09_30.sql`
doesn't contain them either, so it was taken after the restructure. → **Case 2 (schema change) for
all three.**

Original definitions, from the baseline commit `45d3e30`:

| Column | Definition to re-add | Case |
|---|---|---|
| `blood_type` | `VARCHAR(5) NULL` | dropped → re-add |
| `religion` | `VARCHAR(80) NULL` | dropped → re-add |
| `citizenship` | `VARCHAR(50) DEFAULT 'Filipino'` | dropped → re-add. **Note:** the old default is spelled `'Filipino'`; the request's `'Filiplino'` is a typo. |

The old data can't be recovered (it was dropped before the backup). Existing rows get NULL / NULL / 'Filipino'.

| File | Change |
|---|---|
| migration | `ALTER TABLE applicants_personal_information ADD blood_type …, ADD religion …, ADD citizenship …` (after `contact_number`) |
| `database/sk_scholarship.sql`, `database/schema.sql` | same three columns in the CREATE TABLE; add values to the sample INSERT rows |
| `app/controllers/ApplicantController.php` `savePersonal()` | save the 3 fields (`blood_type` checked against the list, `religion`/`citizenship` uppercased, citizenship defaults to FILIPINO) |
| `config/constants.php` | `BLOOD_TYPES` list |
| `app/views/applicant/application_form.php` (Step 1) | Blood Type select, Religion, Citizenship inputs after the contact row |
| `app/views/admin/application_view.php` (Personal tab) | show the 3 fields |
| `app/models/Scholar.php` `find()` + `app/views/admin/scholar_view.php` | join `applicants_personal_information`; show a small "Personal" line (birth date, contact, blood type, religion, citizenship) |

---

## 2. Interview scheduling: suggested slots

The route is now **`admin/interviews`** (it was renamed from `admin/interview-scheduling` in the
restructure). The "New schedule" card is in `app/views/admin/interviews.php`.

| File | Change |
|---|---|
| `app/models/Interview.php` | `const SUGGESTION_BLOCKS = [['08:00','10:00'],['10:00','12:00'],['13:00','15:00'],['15:00','17:00']]`; `suggestSlots(int $programId, int $limit = 5): array`. Window = max(application_end + 1 day, tomorrow) … application_end + 60 days. Mon–Sat only. Reads every **Open** `interview_schedules` row in that date range in **one** query, for all programs. Drops any block where `existing.start_time < block.end AND existing.end_time > block.start` on the same date. Returns the first 5, soonest first, as `['date','start','end','label']`. |
| `app/controllers/InterviewController.php` `index()` | pass `$suggestions` |
| `app/views/admin/interviews.php` | above the date/time fields: "Suggested slots (optional)" with up to 5 `btn-outline-primary btn-sm` chips. Clicking one fills date + start + end. Helper text: "A suggestion only — you can pick any date and time." |

`createSchedule()` is **not** changed: the admin can still type any valid future date and time.

---

## 3. Budget: suggested total

| File | Change |
|---|---|
| `app/models/Budget.php` | `suggestedTotal(int $programId): ?array` → `['total' => slots × amount × 2, 'slots', 'amount']`, or `null` when the program is missing or slots / amount ≤ 0 |
| `app/controllers/FundsController.php` `budget()` | `$suggestions = [programId => Budget::suggestedTotal(id)]` |
| `app/views/admin/budget.php` | each program `<option>` carries `data-suggested / data-slots / data-amount` (computed in PHP). Under Total budget: "Suggested total budget: ₱X (based on N slots × ₱Y/semester × 2 semesters)" + **Use this** button. Updates when the program changes and when an existing budget is opened for editing. Never fills the field by itself. |

---

## 4. Schedule-gated claim (A8) and renewal (A9)

### 4.1 New table `term_schedules`

```
id INT UNSIGNED PK AI
program_id INT UNSIGNED NOT NULL            FK scholarship_programs(id)
academic_year VARCHAR(20) NOT NULL
semester ENUM('1st Semester','2nd Semester') NOT NULL
purpose ENUM('release','renewal') NOT NULL
opens_at DATETIME NOT NULL
submission_deadline DATETIME NOT NULL
planned_date DATE NULL                      release date ('release') / semester start ('renewal')
status ENUM('Open','Closed','Cancelled') NOT NULL DEFAULT 'Open'
notes TEXT NULL
deadline_reminder_sent_at DATETIME NULL     48 h reminder, claimed before sending (idempotent)
created_by INT UNSIGNED NULL                FK users(id)
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP … ON UPDATE CURRENT_TIMESTAMP
UNIQUE uniq_term_schedule (program_id, academic_year, semester, purpose)
KEY idx_deadline (status, submission_deadline)
```

For `purpose = 'release'`, the academic year and semester are those of the **claim**. For
`purpose = 'renewal'`, they are the **target** semester, i.e. `Term::nextSemester()` of the semester
the scholar renews from.

### 4.2 Rules (server)

- Create: program exists; A.Y. like `2026-2027`; `opens_at < submission_deadline`; deadline in the
  future; `planned_date`, if given, on or after the deadline's date; one per
  program + A.Y. + semester + purpose (duplicate → "A … schedule already exists — edit it instead").
- Edit: same checks except the deadline may stay in the past when closing. Status can be set to
  Open / Closed / Cancelled.
- **Window open** = status `Open` AND `opens_at ≤ now ≤ submission_deadline`.
- `Term::claimEditable()` also needs the release window open for the scholar's program + A.Y. + semester.
  Exception (E2): claim_status `Resubmit` needs only a non-Cancelled schedule.
- `Renewal::editable()` also needs the renewal window open for the next semester. Exception (E2):
  renewal status `Resubmit` needs only a non-Cancelled schedule.
- These checks sit inside `Term::uploadDocument`, `Term::submitClaim` and `Renewal::submit`, not only
  in the view.

### 4.3 Affected scholars (notifications + missed-deadline lists)

- **release:** Active scholars of the program with a `scholar_terms` row for that A.Y. + semester.
  - notified: `claim_status` is Waiting for Requirements / Resubmit.
  - "missed deadline" (after the deadline): still Waiting for Requirements / Resubmit.
- **renewal:** Active scholars of the program whose **current** semester is the one before the target.
  - notified: all of them.
  - "missed deadline": no renewal request, or one in Resubmit.
- Messages:
  - on create: "Submit your Grades and School ID by <deadline>" / "Submit your Enrollment Form and
    School ID by <deadline>", plus the opening date and planned date.
  - 48 h before the deadline: the same text as a reminder.
  - on a deadline change (E3): "Deadline changed".
  - All three go out in-app + by e-mail via `Notification::send(…, sendEmail: true)` and are
    audit-logged.
- Missed deadlines are **never** acted on automatically. The admin extends the deadline (edits the
  schedule), follows up, or ends the scholarship with the existing End Scholarship action.

### 4.4 Files

| File | Change |
|---|---|
| `app/models/TermSchedule.php` *new* | `find`, `forTerm(programId, ay, sem, purpose)`, `all(filters)`, `isWindowOpen(row)`, `save(data, id, adminId)`, `setStatus(id, status, adminId)`, `affectedScholars(row)`, `missedDeadline(purpose)`, `notifyAffected(row, kind)`, `dueForReminder(now)` |
| `app/controllers/FundsController.php` | `releaseSchedules()`, `renewalSchedules()` (one list + create/edit modal per purpose), `termScheduleSave()`, `termScheduleStatus()` |
| `app/controllers/AdminController.php` ROUTES | **add** `release-schedule`, `renewal-schedule`, `term-schedule-save` (no existing route renamed). *Built without a separate `term-schedule-status` route: the edit form sets Open / Closed / Cancelled through `term-schedule-save`.* |
| `app/views/admin/term_schedules.php` *new* | shared list + form for both purposes (deadline, opens at, planned date, notes, status) with the affected / submitted / missed counts per row |
| `app/views/admin/claims.php`, `renewals.php` | link to the schedule page + a **"Missed deadline"** table (scholar, semester, deadline, status, links to Scholar page / edit schedule) |
| `app/views/layouts/…` sidebar | "Release schedule" under Claims, "Renewal schedule" under Renewals |
| `app/models/Term.php` | `claimEditable()` gains the schedule rule; `claimWindow(term)` returns the schedule + state (`not_created`, `not_open_yet`, `open`, `deadline_passed`, `cancelled`, `closed`) for the view |
| `app/models/Renewal.php` | `editable()` gains the rule; `window(term)` likewise |
| `app/controllers/ApplicantController.php` | `scholarship()` passes the two windows. The dashboard `journey()` shows "Claim opens on … / deadline …" or "Not open yet — we'll e-mail you". |
| `app/views/applicant/scholarship.php` | no upload form unless the window allows it. Otherwise one of: "Not open yet — the SK office will e-mail you when you can submit (opens <date>)" / "The deadline (<date>) has passed — contact the SK office" / "Submit by <deadline>" when open |
| `app/models/Application.php` `approve()`, `Renewal::approve()` | wording: "…we'll e-mail you when the claim window opens" (or "submit by <deadline>" if a schedule is already open) |
| `bin/send_reminders.php` | second section: `TermSchedule::dueForReminder()` = Open schedules whose deadline is within 48 h, still in the future, `deadline_reminder_sent_at IS NULL`. Claim the row (UPDATE … WHERE … IS NULL) → send to scholars who haven't submitted yet. If the send throws, the claim is released so the next run retries. |
| `config/constants.php` | `TERM_SCHEDULE_PURPOSES`, `TERM_SCHEDULE_STATUSES`, `TERM_SCHEDULE_REMINDER_HOURS = 48` |
| `docs/PROCESS.md`, `README.md` | A8/A9/B7/B9 rows, the notifications table, the reminders section |

---

## 5. Document auto-update + version history

### 5.1 Schema

| Table | Change | Why |
|---|---|---|
| `documents` | `ADD is_current TINYINT(1) NOT NULL DEFAULT 1`, `ADD superseded_at DATETIME NULL`, `KEY idx_doc_current (document_type, is_current)` | `uniq_doc_per_app` is **kept** (E6: A2 replaces in place) |
| `term_documents` | same two columns + `KEY idx_term_doc (term_id, purpose, type)` **then** `DROP INDEX uniq_term_doc` | history needs several rows per term + purpose + type. The FK on `term_id` currently relies on `uniq_term_doc` as its index, so the plain index is added first. |

Existing rows: the migration marks older duplicates across both tables as `is_current = 0`
(`superseded_at` = the newer row's upload time), so every applicant starts with exactly one current
School ID and one current Enrollment Form.

### 5.2 Model

- `Document::supersede(int $applicantId, string $type, ?string $keepTable, ?int $keepId): int`
  - Sets `is_current = 0, superseded_at = NOW()` on every current row of that type for that applicant
    in **both** tables (documents via `applications.applicant_id`, term_documents via
    `scholar_terms → scholars.applicant_id`), except the row being kept.
  - Writes one `document_superseded` audit entry per row changed.
- `Document::current(int $applicantId, string $type): ?array` — one UNION query. Returns the newest
  `is_current = 1` row normalised to: `source_table`, `id`, `type`, `original_filename`, `mime_type`,
  `uploaded_at`, `status`, `uploaded_during` (Application / Claim / Renewal), `semester`, `view_route`
  (`admin/documents/view` or `admin/term-documents/view`).
- `Document::history(int $applicantId, string $type): array` — the same UNION without the filter,
  newest first.
- `Term::uploadDocument()` — **inserts** a new row every time (no more `deleteFile`), after
  `Document::supersede()`, inside one transaction.
- `Term::documents(termId, purpose)` — returns the **newest row per type** for that term + purpose,
  not `is_current`. This keeps a claim showing what was submitted for that claim even after a later
  renewal supersedes its School ID.
- Status updates in `Term::verifyClaim/resubmitClaim` and `Renewal::approve/resubmit` touch only those
  newest rows (by id), so old versions keep their status.
- `ApplicantController::uploadDocument()` (A2) calls `Document::supersede()` for the uploaded type,
  keeping its own row, and sets that row `is_current = 1`.

### 5.3 Every place that reads "the applicant's School ID / Enrollment Form"

| # | Where | Today | Change |
|---|---|---|---|
| 1 | `Application::withDetails()` → `$app['documents']` (`Document::byApplication`) → `app/views/admin/application_view.php` Documents tab | the application-time row only | The School ID and Enrollment Form cards use `Document::current()`, with a badge "Updated during Claim/Renewal — <semester>" when newer than the application upload, plus a **Document history** collapse (date, uploaded during, semester, status, open link). Other types unchanged. |
| 2 | `Application::eligibility()` (uses `$app['documents']`) | reads only Certificate of Residency | no change (not School ID / Enrollment Form) |
| 3 | `FundsController::scholar()` → `app/views/admin/scholar_view.php` | shows no documents | new "Current documents" card (School ID, Enrollment Form via `Document::current()`) + Document history collapse |
| 4 | `FundsController::claimView()` → `Term::documents(…,'claim')` → `app/views/admin/_term_documents.php` (claims verification queue) | the claim's row | shows the newest row of that claim (what's being verified) + a "Current on file" badge, or a link to the current version when a newer one exists |
| 5 | `FundsController::renewalView()` → `Term::documents(…,'renewal')` → `_term_documents.php` (renewals queue) | same | same as #4 |
| 6 | `ApplicantController::scholarship()` → `Term::documents()` → `app/views/applicant/scholarship.php` | the term's row | newest row per type (same function, fixed in §5.2) |
| 7 | `ApplicantController::application()` → `Document::byApplication()` → application form Documents step | the applicant's own upload for this application | no change (that application's own document) |
| 8 | `ApplicantController::uploadDocument()`, `Term::uploadDocument()` | writers that overwrite + delete the file | call `Document::supersede()`; term uploads no longer delete files (§5.2) |
| 9 | `ScreeningController::document()`, `FundsController::termDocument()`, `ApplicantController::viewDocument/viewTermDocument()` | stream by id, with an ownership check on the applicant side | no change, so old versions stay openable |
| 10 | `Report.php` (8 reports) | none read documents | no change |

---

## 6. Migration `database/migrations/2026_09_30_scheduling_and_docs.sql` (Stage 1)

In order, each safe to run once:
1. `ALTER TABLE applicants_personal_information ADD COLUMN blood_type …, ADD COLUMN religion …, ADD COLUMN citizenship … DEFAULT 'Filipino'`
2. `CREATE TABLE term_schedules …`
3. `ALTER TABLE documents ADD is_current …, ADD superseded_at …, ADD KEY idx_doc_current …`
4. `ALTER TABLE term_documents ADD is_current …, ADD superseded_at …, ADD KEY idx_term_doc …` then `ALTER TABLE term_documents DROP INDEX uniq_term_doc`
5. `UPDATE`s that mark older School ID / Enrollment Form duplicates per applicant as not current

The same changes go into `database/sk_scholarship.sql` and `database/schema.sql`.

---

## 7. Stages and commits (branch `restructure-process`)

| Stage | Content | Commit |
|---|---|---|
| 1 | migration + both SQL files | "Stage 1 (plan 2): schema for schedules, document history, personal fields" |
| 2 | items 1 and 3 | "Stage 2 (plan 2): restore personal fields, budget suggestion" |
| 3 | item 2 | "Stage 3 (plan 2): interview slot suggestions" |
| 4 | item 4 | "Stage 4 (plan 2): schedule-gated claims and renewals" |
| 5 | item 5 | "Stage 5 (plan 2): document versions and current-document lookup" |
| 6 | `php -l` on every changed file; a scratch-DB walkthrough of the checklist; `docs/TEST_CHECKLIST_2.md`; PROCESS/README updates | "Stage 6 (plan 2): verification and docs" |

All testing uses a scratch copy of the database; the live `sk_scholarship` is never written to.
`.env` is not touched.

**Not in scope** (say if you want them): a dashboard counter for missed deadlines; a missed-deadline
report; history for the other three application document types; blocking a release before its
planned date (the planned date is shown for information only).
