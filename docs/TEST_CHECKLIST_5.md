# Manual test checklist 5 — academic-year intake (renew into the new program, fewer steps per year)

Run after `database/migrations/2026_10_18_term_program.sql` (docs/CHANGE_PLAN_5.md §9). You need an admin account, a
treasurer account, one scholar of 2026-2027 whose **2nd Semester** claim is Verified or Released (call them **R**), one
applicant account with no application yet (call them **N**), and an e-mail address you can read. Numbered steps; the
expected result follows "→".

## 1. Walkthrough: open 2027-2028 and run its first semester

1. Programs → **Add Program**: name "EduKabataan 2027", applications open 2027-06-01, close 2027-07-15, academic year
   2027-2028, **300** slots, **₱5,000** per semester, Open ticked.
   → The **Renewal for current scholars** and **Budget** sections are shown and ticked. Renewal opens = 06/01/2027
   12:00 AM, deadline = 07/15/2027 11:59 PM. The note says "Into 2027-2028 1st Semester. Goes to N Active scholars whose
   latest semester is in 2026-2027". Total budget = 3000000.00 and "Suggested: 300 slots × ₱5,000.00 × 2 semesters =
   ₱3,000,000.00". Change slots or amount → the total follows.
2. Save. → "Program created for A.Y. 2027-2028. Budget of ₱3,000,000.00 set. Renewal window into 2027-2028 1st
   Semester created; N scholar(s) were notified." Budget lists 2027-2028 ₱3,000,000.00; Renewal schedule lists the
   window (program EduKabataan 2027, renewal for 2027-2028 1st Semester). R gets "Renewal open: submit by …".
   (If today is past the dates, use dates in the future; the renewal deadline must be in the future.)
3. As **R**: My Scholarship → **Renewal for 2027-2028 1st Semester** is shown (claim Verified is enough). Upload the
   Enrollment Form and the School ID front and back, choose the year level, **Submit renewal**.
   → "Renewal submitted". Decisions (2027-2028) → "Slots 300 · Returning 0 (+1 pending renewal) · New approved 0 ·
   Free 299".
4. As **N**: apply to EduKabataan 2027 (with School ID front and back) and submit. As staff: qualify, schedule, mark
   attended and score.
5. Renewals → R → the year level and documents are shown → **Approved — open 2027-2028 1st Semester**.
   Decisions → **Approve** N.
6. Decisions (2027-2028) → **"Slots 300 · Returning 1 · New approved 1 · Free 298"**. The dashboard card (program
   2027-2028) shows "2 / 300 taken" and the same line; Programs → the 2027-2028 row shows Slots 300 · Free 298, Returning
   1, New approved 1. R's scholar page → "current program EduKabataan 2027 · origin program …".
7. Claim schedule → create the 2027-2028 1st Semester claim window. As **R**: My Scholarship → the claim asks **only for
   the college grades**; the School ID shows "On file for 2027-2028" with the files from the renewal. Upload the grades →
   **Submit claim** becomes active → submit. As **N**: the same (the application's School ID is for 2027-2028).
8. Claims → R's claim → only the grades as the claim's document, plus a read-only card "School ID on file for
   2027-2028 — From the renewal". Verify with a GWA. Releases → release R's 1st Semester.
   → "Cash released. The scholar's 2nd Semester is now open (no renewal needed); they were notified." R gets "Cash
   released — 2nd Semester open". My Scholarship → 2027-2028 2nd Semester, opened by "Automatic (after the 1st
   Semester)". No renewal section is shown.
9. Create the 2027-2028 2nd Semester claim window. As R: the 2nd Semester claim again asks **only for the grades**.

## 2. Renewal rules

1. A scholar in their 2026-2027 **2nd Semester** with the claim still **Submitted**: My Scholarship shows no renewal
   section; once the claim is **Verified**, the renewal section appears (no need to wait for the release).
2. A scholar whose **1st Semester** is Released: no renewal section; their 2nd Semester is already open.
3. Renewal schedule → New window: there is no year or semester to choose; the note says renewals go into the 1st
   Semester of the program's academic year. Choosing the 2027-2028 program makes the window "2027-2028 1st Semester".
4. Renewal schedule → "These semesters have scholars waiting but no renewal window yet": a year without a program
   says "create the 2028-2029 program first".
5. Programs → Add a second program for 2027-2028. → Refused: "A.Y. 2027-2028 already has a program …"; nothing saved.
6. Add a program whose renewal deadline is before its opening. → "Renewal window: The deadline must be after the opening
   date. Nothing was saved." The form opens again with everything you typed and the error at its top. Fix it and save.
7. Edit a program. → The Renewal and Budget sections are not shown; saving changes only the program.

## 3. Did not renew

1. Let the 2027-2028 renewal deadline pass (or set it in the past on a test copy) with one scholar who could have
   renewed and did not, and one scholar still in their 2026-2027 1st Semester.
2. Renewals → **Did not renew (1)** lists the first scholar (last semester, claim, deadline). "Couldn't renew yet (1)"
   lists the second, not included in End all.
3. Press **End all as Did not renew** → a confirmation names the count → OK. → "1 scholarship(s) ended as Did not
   renew; each scholar was notified." The scholar is Ended (reason Did not renew), gets "Scholarship ended", and the
   audit log has `scholarship_ended` and `renewal_did_not_renew`.
4. Open the Renewals page in two tabs; in one, have a listed scholar submit a renewal (extend the deadline first); in the
   other press End all. → That scholar is skipped ("1 skipped: they submitted a renewal …").
5. Missed deadline on the Renewals page: the old 2026-2027 **2nd Semester** renewal window lists nobody (that step no
   longer exists).

## 4. Seats, budget and analytics

1. Program with 2 slots, 1 returning and 1 pending renewal. → Decisions "Slots 2 · Returning 1 (+1 pending renewal) ·
   New approved 0 · Free 0", the full badge, Approve disabled; approving through a stale page → "All 2 slots are taken:
   1 returning (+1 pending renewal), 0 new approved. Nobody else can be approved unless a slot opens."
2. Let the renewal deadline pass without deciding the pending renewal. → It still holds its seat (Free stays 0).
3. Renewal page of a request when returning + new already fill the slots → a yellow warning "Approving this renewal
   makes … for … slots"; Approve still works.
4. Budget → Add budget → choose a program with no scholars → "Suggested total budget: ₱… (N slots × (₱…/semester × 2
   semesters))".
5. Analytics (2027-2028): Projected scholars "= returning … + pending renewals … + new approved … + expected
   approvals …"; Slots remaining "= slots − returning − pending renewals − new approved"; Projected need "= committed +
   (projected scholars − returning − new approved = …) × … × 2 semesters".
6. Reports → Scholar masterlist for 2027-2028 → R is listed (Current program 2027-2028, Origin program 2026-2027).
   Releases for 2026-2027 still include R's 2026-2027 releases. Program summary shows the seat line.
