# Manual test checklist 5 — academic years under "Releasing of budget" (CHANGE_PLAN_4)

Run after `database/migrations/2026_10_16_academic_years.sql` (back up the database first: it drops
`scholarship_programs.academic_year`). Use an admin, a treasurer and a reviewer account, one program with at least one
Active scholar, and one applicant who is Interviewed or Waitlisted. Numbered steps; the expected result follows "→".

## 1. After the migration
1. Releasing of budget → **Academic years** (as admin or treasurer).
   → It is the first item of that section, above Budget.
   → Every year your data already used is listed. The one containing today is marked **Current** (Oct 2026 →
   2026-2027).
2. As the reviewer, look at the sidebar and open `admin/academic-years` directly.
   → No menu item, and the page answers "not found".
3. Programs → open Add Program and Edit.
   → There is no academic-year field or line. The list shows no "A.Y." under the names.
   → The form says new scholars start in the current academic year.
4. Open the program pickers on Dashboard, Interviews, Decisions, Analytics, Reports, Calendar and the window pages.
   → No "(2026-2027)" after the names. If two programs have the same name, each also shows its application dates.

## 2. Create an academic year: it appears everywhere
1. Academic years → the label field already holds the next year (for example **2027-2028**).
   → "Also create a budget …" is ticked; "Make this the current academic year" is unticked (one is current already).
2. Create it.
   → The flash says "Created 2027-2028 and N budget(s) (₱…)". N = the programs with Active scholars.
3. Budget.
   → A budget for **2027-2028** exists for each of those programs. Its total = Active scholars × amount per semester × 2
   (₱3,000 × 2 per scholar).
   → A program with no Active scholars got none.
4. Open Budget → Add budget, Claim schedule → New window, Renewal schedule → New window.
   → **2027-2028** is in each A.Y. list. Add/New default to the **current** year. "+ New academic year" sits under each
   year list.
5. Create 2027-2028 again.
   → "Academic year 2027-2028 already exists." Labels such as `2027`, `2027-2029` or `1999-2000` are refused with
   the format message.
6. Edit the auto-created budget, and delete it.
   → Both work like any other budget: delete only while nothing was released.

## 3. Windows: never automatic, one click away
1. Academic years → the 2027-2028 card.
   → Each program row shows its scholars that year, its budget, and four window cells. Each cell shows a state or a
   **Create** button.
2. Click **Create** under "Claim · 1st Sem".
   → Claim schedule opens with **New window** already open: that program, 2027-2028, 1st Semester.
   → Nothing was e-mailed yet. Cancel the modal.
3. Click **Add budget** for a program without one.
   → Budget opens with Add budget open: that program and year.

## 4. Renewal into a year that doesn't exist yet
1. Use a scholar whose current semester is **2nd Semester** of the latest year (for example 2027-2028) and is
   **Released**, with that next year (2028-2029) **not** created.
2. Renewal schedule.
   → The yellow "scholars waiting but no renewal window yet" list shows that program — 2028-2029 1st Semester with
   **Create academic year 2028-2029 first** (a link) instead of Create window.
3. Follow the link and create 2028-2029, then go back.
   → The same line now has **Create window**.
4. (Server) Try to save a window for a year that isn't in the list by editing the form in the browser's developer
   tools.
   → "Academic year … doesn't exist yet — create it under Releasing of budget → Academic years."

## 5. Approval uses the current year
1. Academic years → **Make current** on 2027-2028 → confirm.
   → It gets the Current badge; the previous year loses it.
2. Decisions → approve the Interviewed / Waitlisted applicant.
   → The scholar's first semester is **2027-2028 1st Semester** (scholar page, and the applicant's My Scholarship).
   → The approval e-mail names that semester.
3. On a test database, make no year current (`UPDATE academic_years SET is_current = 0`), then approve another
   applicant.
   → Refused: "No current academic year — an admin or treasurer must create one under Releasing of budget → Academic
   years." Nothing changed. Restore the current year.

## 6. Delete rules
1. Delete a year that has no semester, budget, window or renewal request, and isn't current.
   → Deleted. The audit log shows `academic_year_deleted`.
2. A year that is current, or used by anything.
   → No delete button; the card says why ("It is the current academic year …" / "It is used by 2 semester(s), 1
   budget(s) …").

## 7. Dashboard, analytics, reports
1. Dashboard with a program selected.
   → "Budget remaining — <current year>" and the budget chart use the **current** academic year.
2. Analytics.
   → The A.Y. list is the academic years, defaulting to the current one. Choosing another reloads with it.
3. Reports → 8 (Program summary) and 9 (Profile and academic summary).
   → They show "Academic year (current)" / "Funds (<current year>)".
4. Audit Logs.
   → `academic_year_created`, `academic_year_current_changed`, and one `budget_created` per auto-created budget.
