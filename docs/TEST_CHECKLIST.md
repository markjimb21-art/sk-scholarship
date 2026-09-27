# Manual test checklist

Walk the whole process once after deploying or upgrading. Use a test program, test accounts and real e-mail
addresses you can read. Step numbers match `docs/PROCESS.md`. Tick each box.

## 0. Setup
- [ ] Database upgraded with the migrations (or installed from `schema.sql` / `sk_scholarship.sql`), `composer install` done.
- [ ] Log in as an admin. The sidebar shows: Dashboard · Programs · Screening · Interviews · Decisions · Budget ·
      Claims · Releases · Renewals · Scholars · Applications · Reports · Analytics · Users · Settings · Audit Logs.
- [ ] **Settings → Send test e-mail** to your own address arrives.

## 1. Program and budget (B1, B6)
- [ ] Programs → Add: dates from today to +30 days, 2 slots, ₱6,000 per semester, min GWA 2.50, max income 30,000,
      max age 30, **Open** ticked. It shows "Accepting applications".
- [ ] Saving an end date before the start date, or a negative number, is refused with a message.
- [ ] Budget → Add: the program, its academic year, ₱50,000. Released ₱0, Remaining ₱50,000.

## 2. Applicant registration and application (A1–A2)
- [ ] Register a new account → a 6-digit code arrives by e-mail → enter it → log in.
- [ ] Dashboard: one tracker (Apply highlighted) and "What you need to do next → Start my application".
- [ ] Fill in Personal, Education (with a GWA), Family. Leave the page and come back: the data is still there.
- [ ] Upload 4 of the 5 documents and press Submit → refused, the missing document is named.
- [ ] Upload the 5th and submit → "Submitted — waiting for screening"; the form is now read-only.

## 3. Screening (B2, A3)
- [ ] Screening lists the application. Open it: form data, all 5 documents open, eligibility rows show Pass/Fail.
- [ ] **Return for correction** with an empty remark → refused. With a remark → the applicant gets an e-mail, and
      the dashboard + form show the remark. The applicant fixes and resubmits.
- [ ] **Qualify for interview** → the applicant is e-mailed "Qualified for interview".
- [ ] (Second test applicant) **Reject** with a reason → the applicant sees "Not approved" and the reason.

## 4. Interview (B3–B4, A4–A5)
- [ ] Interviews → New schedule for today, capacity 1. Assign 2 applicants → 1 assigned, 1 refused "schedule is full".
- [ ] The assigned applicant receives date, time and venue by e-mail; the dashboard shows them.
- [ ] Edit the schedule's venue → the applicant is e-mailed "Interview changed".
- [ ] **No-show** → applicant back in "Qualified, waiting for a schedule" with "missed once — last chance";
      assign again; **No-show** again → application Rejected, applicant e-mailed.
- [ ] Another applicant: **Attended — score** → scores above a maximum are refused; valid scores save; status
      "Interviewed".
- [ ] Reminder: set a schedule ~2 hours ahead, assign someone, run `php bin/send_reminders.php` twice → exactly
      one "Interview reminder" e-mail.

## 5. Final decision (B5, A6–A7)
- [ ] Decisions shows the ranking (score high→low, ties by lower income then better GWA) and "Slots: X / Y".
- [ ] **Approve** → scholar created; the applicant is e-mailed and My Scholarship shows Semester 1
      "Upload your School ID and Grades".
- [ ] Fill the slots, then Approve another → refused "All N slots are filled". **Waitlist** them instead.

## 6. Claim and release (A8, B7, B8)
- [ ] Scholar uploads School ID + Grades and submits the claim.
- [ ] Claims → open it → **Resubmit** only the Grades with remarks → the scholar sees the remark and only Grades
      needs re-uploading; submit again.
- [ ] **Verified** with a GWA worse than the minimum → a warning is shown; the claim still moves to Releases.
- [ ] Releases: the amount is pre-filled with ₱6,000. GCash without a reference → refused. A release larger than
      the remaining budget → refused. Delete the budget first (or pick a year without one) → "Create a budget … first".
- [ ] Release with a reference → the scholar is e-mailed the amount, date and reference; Budget shows Released
      ₱6,000 and Remaining ₱44,000.

## 7. Renewal and second release (A9, B9)
- [ ] After the release, My Scholarship offers "Renewal for <next semester>". Upload Enrollment Form + School ID, submit.
- [ ] Renewals → **Resubmit** with remarks → scholar re-uploads → **Approved** → the next semester opens.
- [ ] Repeat §6 for the new semester → second release recorded; Budget shows Released ₱12,000.

## 8. End scholarship (B10)
- [ ] Scholars → open a scholar who is still in their first semester → End (Dropped out) → the scholar is e-mailed;
      they can no longer upload claim or renewal documents.
- [ ] You are taken to Decisions with "A slot is free — promote <next waitlisted>?" → promote → approved.

## 9. Dashboard, analytics, reports
- [ ] Dashboard counters match the queues (screen, qualified without schedule, interviews today, awaiting decision,
      claims to verify, verified to release, renewals to review) and each links to its queue.
- [ ] Analytics shows the stage funnel, conversion rates with their basis ("based on current data only" when no
      program is closed yet), projected scholars, committed, projected need, surplus/shortfall, with formulas.
- [ ] Reports: generate all 8 as PDF and CSV with a program and a date range; the Program summary refuses to run
      without a program; the header says when a filter doesn't apply.

## 10. Security
- [ ] An applicant opening `admin/dashboard` gets "not found"; a logged-out visitor is sent to login.
- [ ] An applicant can't open another applicant's document (change the id in the URL → not found).
- [ ] Five wrong passwords lock the account for 15 minutes.
- [ ] Audit Logs show every status change made above.
