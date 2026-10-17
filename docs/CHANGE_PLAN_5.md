# Change plan 5 — academic-year intake: returning scholars renew into the new program

**Stage 0 only: no code has been changed.** This plan needs your approval before Stage 1.

## 0. Starting point (Stage 0, step 1)

- **The plan-4 undo is already committed.** It is commit `8c078ed` "Original system V1" (59 files: `academic_years`
  removed, programs have `academic_year` again, `database/migrations/2026_10_17_undo_academic_years.sql`). The working
  tree was clean, so there was nothing to commit. I did not rename your commit; say so if you want the message changed
  to "Undo plan 4: programs have their own academic year again".
- **The base is green:**
  - `php -l` on every PHP file: no errors;
  - PHPUnit: **269 tests, 1009 assertions, OK**;
  - `bin/smoke_test.php`: **258 requests, OK** (no HTTP 500, no PHP warnings, access map as expected).
- **Leftovers of plan 4:** `grep -rn "academic_years|AcademicYear|CHANGE_PLAN_4|academic-years|academic_year_id"`
  over code, docs and tests finds only the undo migration itself (it must name the table it drops) and
  `storage/logs/app.log` (left alone). Nothing to remove.

---

## 1. The process (your source of truth)

One program = one academic-year intake, e.g. **"SK EduKabataan 2027-2028"**, A.Y. 2027-2028, 300 slots. Its slots are
shared by:
- **(a) returning scholars**, whose renewal into the 1st Semester of 2027-2028 was approved;
- **(b) new applicants**, approved through screening → interview → decision (unchanged).

Active scholars don't apply again. They renew: School ID (front + back) + Enrollment Form + year level. An approved
renewal puts their new semester in the new program. There is no screening and no interview for renewals.

Within a year, the 2nd Semester opens by itself when the 1st Semester's cash is released (D3), so a renewal happens
**once a year**.

---

## 2. Decisions

### D1–D6 (yours; your default applies)

| # | Decision | Default |
|---|---|---|
| D1 | At most **one program per academic year**, so a renewal always knows which program it goes into | **Yes.** Server rule in `Program::save` and a `UNIQUE` key on `scholarship_programs.academic_year` (see D12) |
| D2 | Renewal into the new A.Y. is allowed when the last semester's claim is **Verified or Released** (not "Released only") | **Yes** |
| D3 | **No renewal between the 1st and 2nd Semester.** Releasing the 1st Semester opens the 2nd Semester of the same program (`opened_via = 'continuation'`) and tells the scholar to claim it. The 2nd-Semester renewal window and queue items go away. | **Yes** |
| D4 | **School ID once per A.Y.** A claim asks only for the Grades when a current School ID (front + back) from the same A.Y. is on file; that ID is shown read-only on the claim page and the admin's claim view. Without one, the claim asks for it as today. | **Yes** |
| D5 | **Returning scholars have priority.** Pending renewals (Submitted / Resubmit) reserve a seat. Seats free for new applicants = slots − approved returning − pending renewals − approved new. Renewal approval is never blocked by seats, only warned about. | **Yes** |
| D6 | Conflicts with PROCESS.md, and which rule wins | See §2.3: the new process wins in every case; the rules you listed under "Keep" don't change |

### 2.2 Extra decisions I found (proposed defaults, please confirm)

| # | Question | Proposed default |
|---|---|---|
| D7 | **Who does "End all as Did not renew" end?** A scholar who was notified but whose latest semester is still the **1st** Semester of the previous A.Y. (e.g. their claim was still being processed) could not have renewed. | Only scholars whose latest semester is the **2nd Semester of the previous A.Y.** with no renewal request. The others are listed separately ("couldn't renew yet") and the button skips them. |
| D8 | **Renewal windows made the old way** (a window on the *old* program for the next A.Y.): your database may have some, possibly with scholars mid-renewal. | `Renewal::window()` looks for the window on the program of the target A.Y. first (D1). If that A.Y. has no program yet, it falls back to a window on the scholar's current program, so in-flight renewals keep working. **New** renewal windows can only be made on the program of their own A.Y. |
| D9 | Does the program form's "Renewal" and "Budget" sections also appear when **editing** a program? | **No: only when creating.** Editing a program never creates or changes its window or budget; those keep their own pages, linked from the form. |
| D10 | **Report 4 (Scholar masterlist)**: its program filter reads `scholars.program_id` (origin). The 2027-2028 masterlist would then leave out every returning scholar. | The filter becomes "has a semester in the program", and the report shows two columns, **Origin program** and **Current program**. (You listed reports 5–8; this adds 4.) |
| D11 | **Scholars stuck by D3:** after the change, an Active scholar whose 1st Semester was *already* Released with nothing after it can't renew into the 2nd Semester any more. | The migration opens their 2nd Semester (`continuation`, Waiting for Requirements). No message is sent by the migration; they see it on My Scholarship and get the claim-window e-mail when that window is created. |
| D12 | **How D1 is enforced on your existing data:** a `UNIQUE` key fails if two programs already share an A.Y. | The `UNIQUE` key is the migration's **last** statement. Run the check query in §9 first; if it returns a row, change one program's A.Y. (or tell me) before migrating. |
| D13 | **Test seed:** programs 1 and 2 are both 2026-2027, which D1 forbids. | Program 2 moves to **2027-2028**, which also makes it the "next year" program the renewal tests need. Tests that depend on program 2's year are updated in the same commit, with the reason. |
| D14 | **"A School ID from the same A.Y."** (D4): which uploads count? | A School ID uploaded **in the application** counts for that program's A.Y. One uploaded **in a renewal** counts for the A.Y. renewed into. One uploaded **in a claim** counts for that semester's A.Y. Both sides are needed (one file with the whole ID also counts, as `Document::covers` already does). If the admin marks it Invalid, the claim asks for a School ID again. |

### 2.3 D6: conflicts with PROCESS.md (the new process wins each time)

| PROCESS.md today | After this plan |
|---|---|
| §1 Renewal request: "Enrollment Form + School ID uploaded for the **next semester**"; §1 semester order 1st → 2nd via renewal | Renewal only into the **1st Semester of the next A.Y.**; the 2nd Semester opens by `continuation` (D3) |
| §2 A9: renew "after the current semester is **Released**" | From the **2nd Semester** only, claim **Verified or Released** (D2), in the **new program's** window |
| §2 A8: claim = School ID (front + back) + Grades | Grades only when a current School ID of the same A.Y. is on file (D4) |
| §3 B1: program fields; delete only when nobody applied | **+ one program per A.Y.** (D1); delete also refused when semesters or renewals are in it; new-program form opens the renewal window and budget (§7) |
| §3 B5: "Slots: X approved / Y total", approve blocked when X ≥ Y; "Slots used = scholars created from this program …" | Seat line and rule of §6 (returning + pending + new) |
| §4 B6 suggestion: Active scholars × amount × 2, none without scholars | **slots × amount per semester × 2** |
| §4 B9: approve opens the next semester | Approve opens the 1st Semester **in the renewal's program**; warning when seats are over |
| §4a: renewal windows for any semester; "Nothing happens automatically" after a missed deadline | Renewal windows only into the 1st Semester of the program's own A.Y. Still nothing automatic: "End all as Did not renew" is a button the treasurer or admin presses, with a confirmation. |
| §4b: "a claim review always shows what was submitted for it" | A Grades-only claim also shows the School ID **on file** (read-only, labelled where it came from) |
| §5: report program filters on `scholars.program_id` (4, 5, 7) | Filter on the semester's program (`scholar_terms.program_id`); renewals on `renewal_requests.program_id` |
| §6 (1)–(2), (4), (7): "of the program" = by the scholar's program | By the semester's program; projected scholars include returning and pending scholars |

Nothing in "Keep / don't change" conflicts:
- data retention: no deletes; "Did not renew" ends the scholarship, keeping the record;
- the audit log; prepared statements, CSRF and the access map;
- roles: renewal decisions and the bulk end stay Treasurer + Admin;
- one release per semester, and a release blocked without a budget or beyond Remaining.

---

## 3. Data model and migration

### 3.1 Columns

| Column | Meaning | Set by |
|---|---|---|
| `scholar_terms.program_id` **new**, NOT NULL, FK, index `(program_id, academic_year)` | The program this semester belongs to: its budget, its windows, its seat | approval → the application's program; renewal → `renewal_requests.program_id`; continuation → same as the 1st Semester |
| `scholar_terms.opened_via` | + `'continuation'` | D3 |
| `renewal_requests.program_id` **new**, NOT NULL, FK | The program the renewal goes **into** (owner of the renewal window) | `Renewal::submit` |
| `scholars.program_id` (unchanged) | Now documented as the **origin** program (first approval). Current program = program of the latest semester. | column comment |
| `scholarship_programs.academic_year` | `UNIQUE` (D1, D12) | — |

### 3.2 `database/migrations/2026_10_18_term_program.sql` (draft)

The header names what it does, "back up first", "safe to run once", and the check query of §9.

```sql
-- 1. A semester belongs to a program (backfill: the scholar's program, which is what every query used until now).
ALTER TABLE `scholar_terms` ADD COLUMN `program_id` INT UNSIGNED NULL AFTER `scholar_id`;
UPDATE `scholar_terms` t JOIN `scholars` s ON s.id = t.scholar_id SET t.program_id = s.program_id;
ALTER TABLE `scholar_terms`
  MODIFY `program_id` INT UNSIGNED NOT NULL COMMENT 'Program this semester belongs to: its budget, windows and seat',
  MODIFY `opened_via` ENUM('approval','renewal','continuation') NOT NULL,
  ADD INDEX `idx_terms_program_year` (`program_id`, `academic_year`),
  ADD CONSTRAINT `fk_terms_program` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`);

-- 2. A renewal goes into a program (backfill: until now every renewal stayed in the scholar's program).
ALTER TABLE `renewal_requests` ADD COLUMN `program_id` INT UNSIGNED NULL AFTER `scholar_id`;
UPDATE `renewal_requests` rr JOIN `scholars` s ON s.id = rr.scholar_id SET rr.program_id = s.program_id;
ALTER TABLE `renewal_requests`
  MODIFY `program_id` INT UNSIGNED NOT NULL COMMENT 'Program the renewal goes INTO (owner of the renewal window)',
  ADD INDEX `idx_renewals_program` (`program_id`, `status`),
  ADD CONSTRAINT `fk_renewals_program` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`);

-- 3. scholars.program_id is the origin program.
ALTER TABLE `scholars` MODIFY `program_id` INT UNSIGNED NOT NULL
  COMMENT 'Origin program: the one that first approved the scholar. Current program = program of the latest semester';

-- 4. D11: open the 2nd Semester for Active scholars whose 1st Semester is already Released with nothing after it.
INSERT INTO `scholar_terms` (scholar_id, program_id, academic_year, semester, opened_via, claim_status)
SELECT t.scholar_id, t.program_id, t.academic_year, '2nd Semester', 'continuation', 'Waiting for Requirements'
FROM `scholar_terms` t JOIN `scholars` s ON s.id = t.scholar_id
WHERE s.status = 'Active' AND t.semester = '1st Semester' AND t.claim_status = 'Released'
  AND NOT EXISTS (SELECT 1 FROM `scholar_terms` l WHERE l.scholar_id = t.scholar_id
                  AND (l.academic_year > t.academic_year OR (l.academic_year = t.academic_year AND l.semester > t.semester)))
  AND NOT EXISTS (SELECT 1 FROM `renewal_requests` rr WHERE rr.from_term_id = t.id);

-- 5. D1: one program per academic year (last, see the check query).
ALTER TABLE `scholarship_programs` ADD UNIQUE KEY `uniq_program_year` (`academic_year`);
```

The same changes go into `database/schema.sql` (CREATE TABLE) and `database/sk_scholarship.sql`. The dump has one
program and no scholars, so only its table definitions change. In `tests/seed.sql`, every `scholar_terms` and
`renewal_requests` row gets its `program_id`, and program 2 moves to 2027-2028 (D13).

**Migration test** (Stage 1, like the earlier backfill test):
1. Put the test database in the old shape: no `program_id` on terms or renewals, the old `opened_via` enum, no unique key, and one Active scholar whose 1st Semester is Released with nothing after it.
2. Run the file statement by statement.
3. Check:
   - every term's and renewal's `program_id` equals `scholars.program_id`;
   - the columns are NOT NULL with their FKs and indexes;
   - the stuck scholar now has a `continuation` 2nd Semester, and nobody else got one;
   - a second program in the same A.Y. is refused.

---

## 4. Every query that changes (Stage 2: `s.program_id` → the semester's program)

"P" = a program; "the semester's program" = `t.program_id`. Afterwards,
`grep -rnE "\bs\.program_id\b|scholars s ON s\.program_id|FROM scholars WHERE program_id"` lists only the queries
under "stays origin". The commit message pastes that grep.

| # | File :: function | Today | After |
|---|---|---|---|
| 1 | `Budget::actualReleased` (also the Released re-sum `syncReleased`, `releaseBlocker`, `delete`) | releases of scholars **of P** in the A.Y. | releases of **semesters of P** in the A.Y. |
| 2 | `Budget::committed` | Active scholars **of P** × unreleased semesters of the A.Y. | Active scholars **with a semester of P** in the A.Y. × that A.Y.'s semesters of P not yet released |
| 3 | `Budget::suggestedTotal` | Active scholars of P × amount × 2 (none without scholars) | **slots × amount × 2** (§6.3) |
| 4 | `AnalyticsBudget::releasedBySemester`, `releasedByMonth`, `avgReleasedPerScholar` | `s.program_id = P` | `t.program_id = P` |
| 5 | `AnalyticsBudget::projectedNeed` | committed + expected new × amount × 2 | same formula; "expected new" now includes pending renewals (§6.4) |
| 6 | `AnalyticsClaims` submitted-with-window SQL (`onTimeRate`, `avgDaysToSubmit`, "without window") | `sc.program_id` | `t.program_id` |
| 7 | `TermSchedule::affectedScholars` (who a window is for: notifications, pending/submitted counts, reminders, missed deadlines, calendar red, edit/delete lock) | release: scholars **of P** with that semester; renewal: scholars **of P** with the semester before | release: **semesters of P** of that A.Y. + semester. Renewal (into P's 1st Semester): Active scholars with a renewal into P for that target, plus those with none whose **latest semester is in the previous A.Y.** Pending = no request, or Resubmit. |
| 8 | `TermSchedule::needed` ("needs a window") | release: grouped by `s.program_id`; renewal: next semester after any Released semester | release: grouped by `t.program_id`. Renewal: only 2nd Semesters (Verified / Released, no request) → the 1st Semester of the next A.Y., on the program of that A.Y.; "create the 2027-2028 program first" when it has none. |
| 9 | `TermSchedule::missedDeadline` → `AnalyticsClaims::overdue` → calendar feed, Claims/Renewals "Missed deadline" | via #7 | via #7 (no own change) |
| 10 | `bin/send_reminders.php` deadline reminders | via #7 | via #7 (no own change) |
| 11 | `Term::claimWindow` | `forSemester($term['program_id'] …)` where `program_id` came from `s.program_id` | same call; `program_id` now comes from `t.program_id` (#12) |
| 12 | `Term::find`, `Term::byClaimStatus` (claims queue, Releases "ready", claim view, release) | `s.program_id`, program name, amount per semester and budget remaining from the scholar's program | from the **semester's** program (`t.program_id`); `s.program_id AS origin_program_id` kept for display |
| 13 | `Term::releaseHistory` filter | `s.program_id` | `t.program_id` (program column = the semester's program) |
| 14 | `Renewal::find`, `Renewal::byStatus` | program = scholar's program | program = **`rr.program_id`** (the program renewed into); origin shown too |
| 15 | `Renewal::window` | window on the term's program for the next semester | window on the program of the **target A.Y.** (D1; fallback D8) |
| 16 | `Program::slotsUsed` | scholars **whose origin is P** (minus ended in 1st semester) | seats of §6.1 (returning + new, both via semesters of P) |
| 17 | `Program::all` (`scholar_count`), `Program::hasScholars` (A.Y. fixed) | scholars whose origin is P | scholars **with a semester in P** |
| 18 | `Program::delete` | refused when anyone applied | also refused when any semester or renewal is in P |
| 19 | `Scholar::search` program filter (Scholars page) | origin program | **has a semester in P**; list shows origin and current program |
| 20 | `Scholar::end` → "slot freed" + program in the flash | origin, all semesters ≤ 1 | **current** program; freed when it ended before a 2nd semester **in that program** |
| 21 | `AnalyticsProfiles::termGwas` (GWA per semester, trend, at risk) | scholars of P | **semesters of P** |
| 22 | `AnalyticsProfiles::scholarsByGwaBand` (dashboard chart) | Active scholars whose origin is P | Active scholars whose **current** program is P |
| 23 | `AnalyticsFunnel::projectedScholars`, `slotsRemaining` | slots used (origin) | §6.4 / seats free |
| 24 | Report 4 Scholar masterlist | `scholars.program_id` | has a semester in P; columns Origin + Current program (D10) |
| 25 | Report 5 Releases | `s.program_id` | `t.program_id` |
| 26 | Report 6 Budget utilization, "Released in range" | `s.program_id = b.program_id` | `t.program_id = b.program_id` |
| 27 | Report 7 Claims & renewals | claims and renewals filtered by `s.program_id` | claims by `t.program_id`, renewals by `rr.program_id` |
| 28 | Report 8 Program summary | released and Active/Ended counts by `s.program_id`; "Slots used" | by semesters of P; the seat line of §6.2 |
| 29 | Dashboard budget card, B6 Budget page Committed | via #1–#2 | via #1–#2 (no own change) |

**Stays origin on purpose** (each will carry a short comment):
- `Scholar::createFromApplication` writes `scholars.program_id`;
- `Scholar::find` shows it as **Origin program** (next to Current program);
- Report 4's Origin column.

**Not scholars at all** (alias `s` = `interview_schedules`): `Interview.php`, `AnalyticsInterviews.php`,
`InterviewSlots.php`. These stay as they are.

---

## 5. Renewal (Stage 3)

**When a scholar may renew (server, `Renewal::editable`):**
- the scholar is Active;
- the term is their current (latest) semester **and a 2nd Semester**;
- its claim is **Verified or Released** (D2);
- no request is pending or approved;
- the window (`Renewal::window`) allows uploads.

The target is the 1st Semester of the next A.Y. on the program of that A.Y. (D1), so `renewal_requests.program_id` is
set at submit.

**Approve (B9):** opens the 1st Semester with `program_id = rr.program_id`; the "Renewal approved" message describes
that program's claim window. Before approving, the renewal page warns when returning + new (+ this one) would exceed
the program's slots. It never blocks.

**D3, the 2nd Semester by itself:** in `Term::release`, inside the same transaction, when the released semester is a
1st Semester, it's the scholar's latest and the scholar is Active, insert the 2nd Semester:
- same program and A.Y., `opened_via = 'continuation'`, Waiting for Requirements;
- audit `term_opened`;
- after the commit, the notification "2nd Semester open — claim it" with the claim-window sentence.

The release message no longer says "you can now renew". Existing 2nd-Semester renewal requests (history, or one still
pending) keep working, but no new ones can be made:
- `TermSchedule::save` refuses a renewal window into a 2nd Semester;
- the renewal window form fixes the semester to 1st and the A.Y. to the program's;
- the renewal queue no longer asks for 2nd-Semester windows.

**D4, School ID once per A.Y.:**
- `Document::versions()` returns the A.Y. each version belongs to (D14).
- New `Document::currentForYear($applicantId, 'School ID', $ay)` returns the current set if it covers both sides and belongs to that A.Y.
- New `Term::claimDocumentTypes($term)` returns `['Grades']` or `['School ID', 'Grades']`.
- `TermDocument::ready()` takes the required types (callers: `Term::submitClaim`, `Renewal::submit`, the upload JSON in `ScholarshipController`, `scholarship.php`).
- The claim upload refuses a type that isn't required, and claim Resubmit only offers the required types.
- The claim page and the admin claim view show the School ID on file read-only, with where it came from (application / renewal, A.Y.) and a view link.

(There is no `Term::documentsReady`; the check you meant is `TermDocument::ready()`, used with `Document::covers()`.)

**"Did not renew" (Renewals page, after a renewal window's deadline):**
- "Did not renew (N)" lists the Active scholars of that window who were notified and have no request, within D7's scope.
- **End all as Did not renew** (POST, CSRF, confirmation, Treasurer + Admin) re-checks each scholar at that moment: still Active, still no request, latest semester unchanged. It then ends them through `Scholar::end(…, 'Did not renew')`, with the same notification and audit entry as one by one, and skips anyone who submitted in the meantime.
- The flash says how many were ended and how many skipped.

---

## 6. Seats, decisions and budget (Stage 4)

### 6.1 Seats of program P (`Program::seats(P)`)

A scholar holds a seat in P when they have a semester in P and their **first semester in P is a 1st Semester**:
- opened via **approval** = new;
- opened via **renewal** = returning.

As today, a scholar **Ended with only one semester in P** frees the seat; a graduated scholar keeps it.

| Number | Formula |
|---|---|
| returning | seat holders whose first semester in P was opened by renewal |
| new | seat holders whose first semester in P was opened by approval |
| pending | renewal requests into P with status Submitted / Resubmit, of Active scholars. They hold a seat until decided, also after the deadline; scholars who never submitted hold none. |
| **free** | slots − returning − pending − new (never below 0) |

`Program::slotsUsed(P)` = returning + new. It stays the floor for lowering a program's slots.

### 6.2 Where it shows

- **Decisions:** "**Slots 300 · Returning 120 (+15 pending renewal) · New approved 140 · Free 25**".
  - `Application::approve` (and the "approve blocked" button state) uses **free**, locking the program row as today. The message: "All 300 slots are taken: 120 returning (+15 pending renewal), 165 new approved."
  - "Promote next waitlisted?" appears when free > 0.
- **Dashboard** (program card) and the **Programs** list: the same line.
- **Renewal page:** the warning of §5.

### 6.3 Budget suggestion

`Budget::suggestedTotal(P)` = slots × amount per semester × 2 (`SEMESTERS_PER_YEAR`). It works for a new program with
no scholars, and the formula is printed under it, as today.

### 6.4 Analytics (formulas on the page updated)

- **Projected scholars** = returning (approved + pending) + approved new + Σ open stages × conversion rate, capped at the slots.
- **Slots remaining** = free.
- **Projected need** = committed (semesters of P) + (projected − returning − new) × amount × 2.
- **Committed / released / on-time / fund use**: by the semester's program (§4).

---

## 7. One form opens the new academic year (Stage 5)

**Programs → New program** gets two sections, **both ticked** for a new program (D9: not shown when editing):

1. **Renewal for current scholars.** It makes the renewal window on this program, into the 1st Semester of its A.Y. It opens at the application start (00:00) and closes at the application end (23:59); both can be edited. The form shows: "goes to N Active scholars whose latest semester is in 2026-2027".
2. **Budget.** It makes the budget for this program and A.Y., with total = the suggestion, slots × amount × 2, which `pages/programs.js` updates live as slots or amount change. The formula is shown.

**Server (`ProgramSetup::create`):** one `Database::transaction`.
1. `Program::save`, with D1 checked.
2. `Budget::save`.
3. `TermSchedule::save` with notifications held back.

If any part fails, nothing is saved, and the form reopens with the error naming the part ("Renewal window: …"). The
scholars are told "Renewal open: submit by …" only after the commit, so a failed save never e-mails anyone. Every part
writes its own audit entry, as today.

---

## 8. Stages and tests

After every stage: `php -l` on changed files → PHPUnit → smoke test → commit.

| Stage | What | Tests added |
|---|---|---|
| 1 | Migration file + `schema.sql` + `sk_scholarship.sql` + seed (D13); `Scholar::createFromApplication` and `Renewal::approve` write `t.program_id` | migration on the old shape (§3.2) |
| 2 | Every query of §4 → the semester's program; grep proof in the commit | a 2026-2027 scholar renews into 2027-2028: the new semester's program is 2027-2028, their old releases still count in the 2026-2027 budget, and they take one seat of 2027-2028 |
| 3 | Renewal rules D2, D3, D4 (+ D7, D8, D14) and the bulk "Did not renew" | 1st Semester released → 2nd opens by itself; renewal allowed with the last claim Verified; claim needs only Grades with a current School ID of the same A.Y. and needs the ID without one; bulk "Did not renew" ends only the right scholars and skips one who submitted |
| 4 | Seats (D5), decisions/dashboard/programs line, budget suggestion, analytics formulas | seats: 300 slots, 120 returning, 15 pending, 165 new → one more new approval is blocked; after the deadline the 15 pending still hold seats, unsubmitted scholars none; budget suggestion = slots × amount × 2 with no scholars |
| 5 | One-form new academic year | program + renewal window + budget all created, or none when one part is invalid |
| 6 | Docs: PROCESS.md (statuses, A7–A9, B1, B5, B6, B9, §4a, analytics), CODE_ORGANISATION.md (tables), TEST_CHECKLIST_5.md (walkthrough below) | — |

Existing tests that pin today's behaviour, e.g. the renewal "Released only" rule or the old budget suggestion, are
changed in the stage that changes that behaviour, and the commit message names each one.

**TEST_CHECKLIST_5 walkthrough:**
1. Create 2027-2028 (300 slots) in one form.
2. A 2026-2027 scholar renews.
3. A new applicant applies.
4. Approve both.
5. Decisions shows Returning 1 / New 1 / Free 298.
6. Release the 1st Semester; the 2nd Semester opens by itself.
7. Claim it with Grades only.

---

## 9. The SQL to run on your database, in order

1. **Back up** (`mysqldump`, outside the project folder).
2. **Check D1 first.** Every row it returns is an A.Y. with more than one program; fix those before step 3:
   ```sql
   SELECT academic_year, GROUP_CONCAT(program_name SEPARATOR ' | ') AS programs
   FROM scholarship_programs GROUP BY academic_year HAVING COUNT(*) > 1;
   ```
   Optional, for D8: renewal windows made the old way (window A.Y. ≠ its program's A.Y.):
   ```sql
   SELECT ts.id, sp.program_name, sp.academic_year AS program_year, ts.academic_year, ts.semester, ts.status
   FROM term_schedules ts JOIN scholarship_programs sp ON sp.id = ts.program_id
   WHERE ts.purpose = 'renewal' AND ts.academic_year <> sp.academic_year;
   ```
3. `database/migrations/2026_10_18_term_program.sql`.

---

## 10. Opening a new academic year: admin steps

**Before:**
1. **Programs** → Add program (name, A.Y., dates, slots, amount, limits).
2. **Renewal schedule** → New window, **for every old program**, into the 1st Semester of the new A.Y. (and again
   mid-year for each 2nd Semester).
3. **Budget** → Add budget for each program + A.Y. (no suggestion while the new program has no scholars).
4. Approve renewals; returning scholars stay counted in their **old** program's slots and budget.

**After:**
1. **Programs** → New program: fill in the program, leave "Renewal for current scholars" and "Budget" ticked, check
   the pre-filled dates and total → **Save**. All three are created together, and the scholars are notified.
2. Approve renewals and new applicants. Both count in the new program's seats and budget. The 2nd Semester opens by
   itself after each 1st-Semester release.
