# Manual test checklist 3 — calendar and analytics

Covers `docs/CHANGE_PLAN_3.md`. Use a test program with at least one scholar whose claim is still "Waiting for
Requirements". Tick each box.

## 1. Calendar basics
- [ ] The sidebar shows **Calendar** right under Dashboard. As an applicant, opening `admin/calendar` gives "not found".
- [ ] Calendar opens on this month with **today's cell tinted yellow** and the day number in bold.
- [ ] Month / Week buttons switch views; the legend lists all colours.
- [ ] Nothing is loaded from the internet for the calendar: in the browser's developer tools → Network, the calendar
      files come from `assets/vendor/fullcalendar/` (works with the internet off).

## 2. Interviews on the calendar match the Interviews list
- [ ] Interviews → create a schedule for **today** and one for a later date with capacity 1, then assign one applicant
      to the later one.
- [ ] Calendar: today's schedule is **green (Open)**, the later one **amber (Full)**, older ones **grey (Done)**, a
      cancelled one **red with the title struck through**.
- [ ] Open the Interviews list for the same program in a second tab: every schedule's badge (Open / Full / Done /
      Cancelled) matches its calendar colour.

## 3. Claim / renewal deadlines
- [ ] Claim schedule → New window: deadline **about 30 hours from now**, planned release date in 5 days.
- [ ] Calendar: the deadline is **orange with a pulsing dot** ("Due within 48 hours"); the planned release shows as a
      **dashed outline** on its date. The list below shows the deadline under **This week** (or Today) with the same
      orange pulsing dot.
- [ ] Renewal schedule → New window with a deadline 2 weeks away at **12:00 AM**: it appears as an all-day **purple**
      event on the day **before** (a midnight deadline means the end of the previous day).
- [ ] Program filter: choose one program → only its interviews and windows remain (calendar and list); the URL keeps
      `program_id`, so reloading keeps the filter.

## 4. Click-to-highlight both ways
- [ ] Click the orange deadline on the calendar → you stay on the page; the list scrolls to its row, which flashes and
      stays highlighted (light blue); the event gets a blue outline.
- [ ] Click a row in the list (e.g. the planned release) → the calendar jumps to that month, the event gets the blue
      outline and its day cell a blue border. Only one row is highlighted at a time.
- [ ] The row's **Open** button goes to the interview schedule / opens the window's edit form.

## 5. Overdue: calendar, Claims and Analytics agree
- [ ] Let the claim window's deadline pass without the scholar submitting (or, on a **test** database only:
      `UPDATE term_schedules SET submission_deadline = NOW() - INTERVAL 10 MINUTE WHERE id = <window id>;`).
- [ ] Calendar: the deadline turns **red** ("Overdue — 1 scholar(s) missed it") and appears first in the list under
      **Overdue**, in red.
- [ ] Claims → **Missed deadline** lists the same scholar.
- [ ] Analytics (same program and A.Y.) → section 4 **Currently overdue** shows the same number, in red.
- [ ] A window whose deadline passed but where everyone submitted is **grey**, not red.

## 6. Analytics sections 3 and 4
- [ ] Sections 1 and 2 look and calculate exactly as before.
- [ ] Section 3: record one Attended (score it) and one No-show on the program's interviews → Attendance rate "1 of 2"
      = 50%; No-show first attempt counts the no-show; after the applicant's reschedule, mark it No-show again → "No-show
      after the reschedule" = 1 of 1. The score chart has one bar per band and the average matches the scores entered.
- [ ] Section 4: a scholar who submits before the deadline → On-time "1 of 1"; average time to submit ≈ days between
      the window opening and the submission. A re-send after a Resubmit past the deadline makes that claim late.
- [ ] Every number has its formula written under it.
