# Manual test checklist 4 — review fixes (notifications, scheduling, data retention, analytics, dashboards, roles)

Run after the four `2026_10_01_*.sql` migrations. Use a test program, two admin accounts (one of them inactive is a
plus), two applicant accounts and an e-mail address you can read. Numbered steps; the expected result follows "→".

## 1. Notifications: today's interviews and admins
1. Settings → both new toggles are shown: **Daily interview digest** (on) and **Also e-mail admins about new
   submissions** (off). → Saving keeps the values.
2. As applicant A, submit an application. → Every *active* admin has a new bell notification "New application
   submitted" linking to the application; an inactive admin gets none.
3. Return the application for correction; applicant A resubmits. → Admins get "Application resubmitted".
4. Qualify applicants A and B, create an interview schedule **for today** and assign both.
5. Run `php bin/send_reminders.php`. → Each applicant gets "Your interview is today" (time, venue, application code,
   "arrive 15 minutes early"); each active admin gets one "Interviews today: 1 schedule(s)" listing both names and
   "2 not yet marked". The script prints "Daily digest sent".
6. Run the script again. → "Daily digest already sent today"; no new notifications.
7. Admin dashboard → an **Interviews today** card lists the schedule's time, venue, program and both applicants (with
   links) and the "not yet marked" count.
8. Mark applicant B **No-show**. → Admins get "First no-show — reschedule needed" linking to the reschedule page.
9. Take applicant A through a claim and a renewal submission. → Admins get "Claim submitted" and "Renewal submitted".
10. Turn **Daily interview digest** off, clear `daily_digest_sent_on` on a test DB, run the script. → "Daily digest is
    turned off in Settings." Turn it back on.
11. Turn on "Also e-mail admins about new submissions" (with e-mail on). → The next submission notification is also
    e-mailed to the admins.
