# Manual test checklist 2 — windows, suggestions, document history

Covers `docs/CHANGE_PLAN_2.md`. Run it after `database/migrations/2026_09_30_scheduling_and_docs.sql`, with a test
program, a test scholar account and an e-mail address you can read. Tick each box.

## 0. After upgrading
- [ ] The sidebar shows **Claim schedule** (above Claims) and **Renewal schedule** (above Renewals).
- [ ] **Claim schedule** lists every semester that has scholars waiting to claim but no window yet. Scholars who
      were mid-claim can't upload until you create one, so create a window for each listed semester now.

## 1. Restored personal fields
- [ ] Application Step 1 has Blood Type (dropdown), Religion and Citizenship (pre-filled FILIPINO). Fill them, leave
      the page and come back: they're kept.
- [ ] Admin → the application → Personal tab shows all three. The scholar page shows them under the name.

## 2. Budget suggestion
- [ ] Budget → Add → pick a program with slots and amount per semester set: "Suggested total budget: ₱X (based on
      N slots × ₱Y/semester × 2 semesters)" appears. Check the math.
- [ ] The Total field stays empty until you press **Use this**. Typing a different amount saves that amount.
- [ ] A program with 0 slots or ₱0 per semester shows no suggestion.

## 3. Interview suggestions
- [ ] Interviews → New schedule shows up to 5 "Suggested slots": Monday–Saturday only, 8–10, 10–12, 1–3 or 3–5,
      the first on or after the day after the application end date.
- [ ] Clicking a chip fills the date, start and end. You can still type any other date and time.
- [ ] Create a schedule on a suggested block, reload: that block is gone. A schedule of another program on the same
      day and time also removes it. A cancelled schedule doesn't.

## 4. Claim window → claim → verify → release
- [ ] As the scholar, My Scholarship shows "Not open yet — the SK office will e-mail you…" with **no upload form**.
- [ ] Admin → Claim schedule → **Create window** for that semester: opens now, deadline in 3 days, planned release
      date after that. The flash says "1 scholar(s) were notified", and the scholar gets an e-mail "Claim open:
      submit by …".
- [ ] The scholar's page now shows "Submit your Grades and School ID by …" and the two upload rows. Upload a School
      ID and Grades, then submit.
- [ ] Admin → Claims → **Resubmit** the School ID. The scholar uploads a new School ID and submits again. On the
      claim page, only the newest School ID is shown, marked "Current on file".
- [ ] **Verified** with a GWA, then Releases → release. The scholar is e-mailed.

## 5. Renewal window → renewal
- [ ] After the release, My Scholarship shows the Renewal card with "Not open yet" and no upload form.
- [ ] Admin → Renewal schedule → New window for the **next** semester (e.g. 2nd Semester of the same A.Y.). The scholar
      is e-mailed "Renewal open: submit by …".
- [ ] The scholar uploads an **updated** Enrollment Form and School ID and submits. Admin → Renewals → **Approved**:
      the next semester opens.

## 6. The new document is current everywhere; the old one is in history
- [ ] Admin → the scholar's application → Documents: School ID and Enrollment Form show **"Current version — updated
      during Renewal"** with the new file. Open it: it's the one uploaded in §5.
- [ ] **Document history** (click to expand) lists every version: the application upload, both claim uploads and the
      renewal upload. Only the newest is "Current"; the older ones show when they were replaced, and each still opens.
- [ ] The scholar page → **Current documents** shows the renewal files, with the same history below.
- [ ] Open the §4 claim again: its School ID says "Replaced by a newer upload".
- [ ] Audit Logs show "School ID superseded by a newer upload" entries.

## 7. Deadlines
- [ ] Create a claim window for the new semester, then edit it (or wait) until the deadline has passed without the
      scholar submitting. The scholar sees "The deadline (…) has passed — contact the SK office" and no upload form.
- [ ] Admin → Claims shows the scholar under **Missed deadline**. The scholarship is still Active: nothing is
      automatic.
- [ ] Press **Extend**, set a later deadline and save. The scholar is e-mailed "Claim deadline changed" and can upload again.
- [ ] Set the deadline to about 30 hours away and run `php bin/send_reminders.php` twice. Exactly one "Claim reminder"
      e-mail arrives.
- [ ] Ask for a Resubmit, then let the deadline pass: the scholar **can** still re-upload (the SK office asked for it).
- [ ] Set the window to **Cancelled**. The scholar is e-mailed, and sees "cancelled — contact the SK office" with no
      upload form, even for a Resubmit.

## 8. Security
- [ ] An applicant opening `admin/release-schedule` gets "not found".
- [ ] Uploading to `applicant/claim/upload` while no window is open (e.g. with an old browser tab) is refused with
      the window message, and no file is saved.
