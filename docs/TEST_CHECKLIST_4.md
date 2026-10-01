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

## 2. Interview scheduling page and suggestions
1. Settings → Holidays: add a holiday on the next working day. Settings → "Interview suggestions — days ahead": enter 3
   and save. → It is stored as 7 (minimum); set it back to 60.
2. Create a second program with an interview schedule on another day. Interviews (first program) → New schedule chips.
   → No chip falls on the holiday, on Sunday, or on the other program's interview day.
3. Create a first-program schedule with capacity 2 on a later day. As a Qualified applicant, open the dashboard.
   → "Pick an interview slot" lists 3 choices; the existing schedule (with its venue) is first.
4. Pick it and press **Request this slot**. → "Slot requested"; the application is still Qualified; admins get
   "Interview slot requested"; the dashboard counter "Slot requests to confirm" shows 1 and the Interviews page lists it.
5. Press Request again (or re-send the form). → Refused: a request is already pending.
6. Admin → Review → **Confirm**. → The application is "Interview scheduled" on that schedule; the applicant gets
   "Interview scheduled"; the request shows Confirmed.
7. Second applicant picks a **new** block → admin Confirm with a venue and capacity. → A schedule is created at that
   date/time/venue and the applicant is assigned to it.
8. Third applicant picks the capacity-2 schedule; fill it before confirming (assign someone else). Confirm. → Refused
   "schedule is full"; the request is Declined and the applicant gets "Interview slot not confirmed — pick another".
9. Scheduling page (Interviews → waiting list → **Schedule**, or a schedule's row → **Reschedule**): suggestions are
   ranked (existing schedules with places left first) and the applicant's own current schedule is not offered.
10. On a scheduled applicant press **Assign here** on another schedule. → The applicant is moved (still one attempt,
    no no-show counted) and gets one "Interview changed". Moving onto a full schedule is refused and nothing changes.
11. Use the date/time picker with your own date, times, venue and capacity. → The applicant is scheduled there.
12. Edit a schedule's venue. → Every applicant on it gets "Interview changed".
13. Mark an applicant No-show (first time). → They are Qualified again, can pick a slot on their dashboard, and the
    admins' "First no-show" notification links to the scheduling page. A second no-show still rejects the application.
14. Security: a crafted request for a slot that isn't one of the applicant's 3 suggestions, or one without the CSRF
    token, is refused; an applicant opening `admin/interview-reschedule` gets "not found".

## 3. Retain correct data, remove only invalid data
1. As a Draft applicant, upload a School ID, then upload a different one. → Two versions exist; the first file is still
   on disk (`storage/documents/<application>/`); the audit log says "new version; the previous file is kept".
2. Press **This is the wrong file — remove it** under a document. → The document shows "Removed: Wrong file uploaded";
   it counts as missing; the record is kept as Invalid (admin → Document history shows it with "(file removed)").
3. Submit an application. Admin → Return for correction: tick **Contact number**, **GWA**, **School ID — re-upload** and
   **2x2 Photo — invalid**, with a remark. → The applicant's e-mail lists exactly those items; the photo is marked
   Invalid with the remark as reason and its file removed.
4. As the applicant open the form. → The flagged fields are highlighted, everything else is greyed out; the Certificate,
   Form 138 and Enrollment Form cards say "Accepted as submitted".
5. Try to change your name (e.g. with the browser's developer tools re-enable the field) and save. → The name stays as
   submitted; only the contact number changes. Changing the school in the Education step is ignored; the GWA changes.
6. Try to upload the Certificate of Residency. → Refused ("accepted as submitted").
7. Resubmit before re-uploading the School ID and photo. → Refused, naming the documents. Upload both and resubmit. → Submitted.
8. Admin → application → History tab → **Changes to key information** shows "GWA 1.60 → 1.80" with who and when.
   The scholar page shows the same panel.
9. Admin → a document card → **Mark this file invalid…** with a reason. → The card shows "Marked invalid …", the record
   stays in Document history, and the audit log has "document_invalidated".
10. Claim (or renewal) → Resubmit with **"The file is invalid — remove it"** ticked. → That document shows Invalid with the
    remark; the scholar can't submit until they upload a new one.
11. Confirm in the database that no applicant, application, scholar, release or document row was deleted by any of the
    above (row counts only grow).

## 4. Analytics: profiles and academic performance
1. Application Step 1 has an optional **Sex** field ("Prefer not to say" by default). → Saved and shown on the
   application page; blank shows "Not given".
2. With a few submitted applications (different puroks, schools, courses, year levels, ages, incomes; some Approved),
   open Analytics → section 5. → Each table lists applicants, approved and approval rate per group; the counts add up to
   the number of submitted applications (drafts are not counted); ages are grouped as on the submission date.
3. Section 6 → average GWA of applicants vs approved scholars matches the GWAs on their forms.
4. Verify two claims for the same scholar with GWAs 2.00 then 2.75 (program minimum 2.50). → The per-scholar table
   shows both semesters, the 2.75 in red, trend "Declining"; the scholar is in **At-risk scholars** and the count is 1.
   A scholar going 2.00 → 1.50 shows "Improving".
5. Section 7 → after two ₱5,000 releases with a ₱40,000 budget: released ₱10,000, release rate 25.0%, average per
   scholar matches released ÷ scholars paid; "Released per month" lists each release month.
6. Every number has its formula written under it; changing the A.Y. select changes sections 6 (per-scholar table) and 7.

## 5. Dashboards and charts
1. Admin dashboard → pick a program. → Four charts appear below the program cards: **Applications by status**,
   **Budget** (total / released / remaining), **Releases per month**, **Active scholars by GWA**; each has its numbers in a
   table under it and labelled axes. A program with no data shows a short "No … yet" message instead of an empty chart.
2. Analytics → sections 5–7 each show charts with the same numbers in tables: profile bars (Applicants blue, Approved
   orange, with a legend), **Average GWA per semester** (line; the axis is reversed so a better GWA is higher, as the
   axis title says), the **At-risk scholars** table, and **Released per month / per semester** bars.
3. The browser's developer tools → Network: Chart.js and `js/sk-charts.js` load from the site (`assets/…`), nothing from a CDN.
4. Reports → **Profile and academic summary** → choose a program → PDF and CSV. → The file lists every profile group
   (applicants, approved, approval rate), the GWA figures, at-risk scholars, GWA trends and fund utilization. Without a
   program it is refused; a date range shows "Not applicable to this report" in the header.

## 6. Role-based access
1. Users → **Add staff**: create a Reviewer and a Treasurer. → Both appear with their role badge.
2. Edit a staff account and change its role. → Saved; Audit Logs show "Role of … changed from … to …".
   Try to change your own role → refused. With only one active admin, try to demote or deactivate them → refused.
3. Log in as the **Reviewer**. → Sidebar: Dashboard, Calendar, Screening, Interviews, Decisions, Applications only;
   dashboard shows the screening/interview counters and today's interviews, no budget card or claims counters; the
   calendar shows interviews only.
4. As the Reviewer open `admin/budget`, `admin/claims`, `admin/users`, `admin/settings`, `admin/reports` by URL. → "not found".
   A forged POST to `admin/budget-save` (with a valid token) creates nothing.
5. Log in as the **Treasurer**. → Sidebar: Dashboard, Calendar, Budget, Claim schedule, Claims, Releases, Renewal
   schedule, Renewals, Scholars; no screening, interviews or applications; the calendar shows claim/renewal deadlines only.
   `admin/screening`, `admin/application&id=…`, `admin/users` → "not found".
6. As the Treasurer end a first-semester scholarship. → Ended; no redirect to the (reviewer-only) Decisions page.
7. Deactivate a staff account. → They can no longer log in.
8. Admin can still open every page.

## 7. Security cleanup
1. `git status` / `git log --all --name-only` → no `.env` and no `*.sql` dump outside `database/` is or ever was committed.
2. Create a file `backup_test.sql` in the project root → `git status` does not list it (ignored); delete it.
3. The project folder contains no SQL dumps; backups are in `C:\xampp\sk-scholarship-backups\`.
4. README → Security notes tells you to keep backups outside the project and to rotate any credential that was ever
   committed or shared (MySQL password, Gmail App Password).

## 8. Everything
1. `php -l` on every PHP file → no errors.
2. On a copy of `database/sk_scholarship.sql` as it was before these changes, run the five `2026_10_01_*.sql`
   migrations → no errors; the result matches `database/sk_scholarship.sql` and `database/schema.sql`.
3. Every new POST (slot request, slot decision, reschedule, holidays, document invalidation, user role change) without
   a CSRF token is refused.
