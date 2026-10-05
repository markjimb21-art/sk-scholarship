# Change plan UI — rebrand, accessible refresh, login improvements

Follow-up to `docs/PROCESS.md` and the earlier change plans. Stage 0 only: no code has been changed.
This is a **visual and login change**. No workflow, status, route, database rule or feature changes, and every page
shows the same information and does the same things afterwards. Decisions that need your answer are in **§0**.

---

## 0. Decisions (defaults proposed — "approve with defaults" accepts all)

| # | Question | Proposed default |
|---|---|---|
| U1 | Where the system name lives | **There is no `site_name` setting any more.** It was removed in `CHANGE_PLAN.md` ("Settings that do nothing") because nothing read it. The row is in neither `schema.sql` nor `sk_scholarship.sql`, and isn't in the live database. Re-adding it would be a setting that nothing reads. Proposed: **two constants in `config/constants.php`** (`APP_NAME` = "SK EduKabataan: Educational Assistance for College Students", `APP_NAME_SHORT` = "SK EduKabataan") plus `APP_SUBTITLE`, used by every title, the sidebar, e-mails and PDFs. **No migration, no SQL to run.** If you want it editable from Settings, say so: I'd then add a migration that inserts `site_name`, show it on the Settings page and read it through a helper. |
| U2 | Local Bootstrap | **Every page loads Bootstrap from the CDN today**: the main layout (`header.php`, `footer.php`) as well as the auth pages. Proposed: download Bootstrap 5.3.3 (CSS + bundle JS) and Bootstrap Icons 1.11.3 (CSS + woff/woff2) into `public/assets/vendor/`, and load them on **all** pages, so the site works with no internet like Chart.js and FullCalendar already do. |
| U3 | A 5th password entry point | Besides the four you listed, applicants can **change their own password on My Profile** (`ApplicantController::profile`, `change_password`). It currently checks only length (and says "Password too short."). Proposed: use the shared rule there too, with the eye toggle and live checklist. |
| U4 | Which characters count as "special" | `PASSWORD_SPECIAL_CHARS` = ``!@#$%^&*()_+-=[]{};:'",.<>/?\|`~`` (all printable ASCII punctuation). The message lists examples ("e.g. ! @ # $ %"). Spaces and letters with accents don't count. |
| U5 | Font size on admin tables | The base becomes 18px everywhere. Dense admin tables (Applications, Scholars, Releases, Audit log) use **16px** cells so they stay usable, which is above the 15px floor. They scroll sideways inside their card on small screens. Pages get somewhat longer; that is the price of readability. |
| U6 | Calendar and chart colors | **Unchanged.** The calendar event colors (`CalendarController::COLORS`) and the Chart.js series colors identify data, and you asked last time to keep the calendar color-coding as it is. Only the calendar's "today" cell changes: its yellow tint behind the day number becomes a pale blue tint with a yellow top bar, because yellow may not sit behind text. |
| U7 | Seal resolution | Image 2 is only ~228 px wide. That's fine at the sizes it appears (≤ 96 px on screen, ~60 px in the PDF), but it would look soft if enlarged. A larger original would help if you have one. |
| U8 | Old notifications in the sample dump | `sk_scholarship.sql` has 2 sample notification rows ("Your SK Scholarship account has been created…"). They are records of messages already sent, so they stay as they are, like migration history. The dump's header comment is updated. |

---

## 1. Every place the old name appears

`grep -rniE "SK Scholarship|Scholarship Management|Scholarship MIS"` (repo, excluding `vendor/`, `storage/`).
The folder/URL slug `sk-scholarship` (`BASE_URL`, README paths, `app.js`, the dump's file paths) is **not** a display
name and stays, because renaming it would change every URL.

| File | Line | Text today | Becomes |
|---|---|---|---|
| `app/views/layouts/header.php` | 12 | `<title>… · SK Scholarship MIS` | `… · SK EduKabataan` |
| `app/views/layouts/sidebar.php` | 15 | `SK Scholarship` (brand) | SK logo + `SK EduKabataan` / `Barangay Estefania` |
| `app/views/auth/login.php` | 6, 19 | title + heading `SK Scholarship MIS` | `Sign In · SK EduKabataan`; both logos + full name + subtitle |
| `app/views/auth/register.php` | 6 | title | `Register · SK EduKabataan` |
| `app/views/auth/landing.php` | 6, 17 | title + `Sangguniang Kabataan Scholarship Management System` | both logos, full name as the heading |
| `app/views/errors/500.php` | 6 | title | `Something went wrong · SK EduKabataan` |
| `app/views/auth/forgot_password.php`, `reset_password.php`, `verify_otp.php`, `verify_reset_otp.php`, `errors/404.php` | — | **no `<title>` at all** today | get one (`… · SK EduKabataan`) and the SK logo |
| `core/Notification.php` | 107 | `SK Scholarship MIS — Test Email` | `SK EduKabataan — Test Email` |
| `core/Notification.php` | 127, 145, 154 | e-mail shell comment, header `Scholarship Management System`, footer `SK Scholarship Management Information System` | full name in the header (text + colors, no image), short name in the footer |
| `core/Notification.php` | 208 | default sender name `SK Scholarship` (used only when `MAIL_FROM_NAME` is unset) | `SK EduKabataan`. **`.env` is not touched**: if your `.env` sets `MAIL_FROM_NAME="SK Scholarship"`, change it there yourself. |
| `app/controllers/AuthController.php` | 77, 115, 117, 118, 154, 286, 287 | "SK Scholarship account/password" in notification and OTP e-mail text | "SK EduKabataan account/password" |
| `app/controllers/ReportController.php` | 146, 182 | PDF header `SK Scholarship Management Information System`, footer `Generated by SK Scholarship MIS` | both logos + full name; footer `Generated by SK EduKabataan` |
| `app/models/Scholar.php` | 95 | "Your SK scholarship (…) has ended … SK scholar" | **kept**: here "SK scholarship" is the plain noun (the grant), not the system name |
| `database/schema.sql` | 2 | header comment `SK SCHOLARSHIP MANAGEMENT INFORMATION SYSTEM` | `SK EDUKABATAAN — EDUCATIONAL ASSISTANCE FOR COLLEGE STUDENTS` |
| `database/sk_scholarship.sql` | 1 | header comment | same new name (data rows: see U8) |
| `README.md` | 1, 106, 133 | title, `MAIL_FROM_NAME="SK Scholarship"` example, Task Scheduler task name | new name / `"SK EduKabataan"` / `SK EduKabataan reminders` |
| `docs/PROCESS.md` | 1 | `# SK Scholarship — The Process` | `# SK EduKabataan — The Process` |

---

## 2. Design tokens (in `public/assets/css/style.css` `:root`)

Bootstrap's own variables (`--bs-primary`, `--bs-body-color`, `--bs-link-color`, `--bs-danger`, …) are pointed at
these tokens, so the existing `btn-primary`, `text-muted`, `alert-*` and `badge bg-*` classes in ~60 views restyle
**without rewriting the views**. No new hard-coded colors go into views; the few inline colors on the auth pages
(their gradient background) move into the CSS.

| Token | Value | Used for |
|---|---|---|
| `--brand-blue` | `#0B4DB3` | primary buttons, headings accent, links, active states |
| `--brand-blue-dark` | `#083A87` | hover/active, focus ring on light surfaces, dark text-blue |
| `--brand-navy` | `#082B66` | sidebar background, e-mail header |
| `--brand-red` | `#CE1F2A` | destructive buttons, error borders/icons **only** |
| `--brand-red-dark` | `#A3141D` | error **text** (higher contrast than the logo red) |
| `--brand-yellow` | `#F6C90E` | small accents only: sidebar active bar, focus ring on dark surfaces, logo-strip underline. **Never text and never behind text.** |
| `--surface-page` | `#F5F7FB` | page background |
| `--surface-card` | `#FFFFFF` | cards, fields |
| `--text` | `#1A1F2B` | all body text |
| `--text-muted` | `#454E5F` | secondary text (replaces Bootstrap's light gray `text-muted`) |
| `--border-field` | `#6B7486` | form field borders (visible edge) |
| `--focus-ring` | `3px solid var(--brand-blue-dark)`, 2px offset (yellow on the sidebar) | every interactive element |
| Badge pairs | success `#14532D`/`#DCF2E4`, warning `#6B4300`/`#FFF1C7`, danger `#9B111B`/`#FDE4E5`, info `#083A87`/`#E1ECFB`, neutral `#2F3645`/`#E7EAF0`, primary `#FFF`/`#0B4DB3` | status badges (the word is always shown: `APP_STATUS_LABELS` already does that) |
| Type | `"Atkinson Hyperlegible", system-ui, sans-serif`; root 18px (1.125rem), line-height 1.6; `.small` = 0.875rem → 15.75px; h1–h6 bold, 2.0/1.6/1.35/1.2/1.1/1rem | everywhere |
| Text-size scale | `html[data-text-size]` = 100% / 115% / 130% | A / A+ / A++ control |
| Measure | `--measure: 70ch` on paragraphs and alerts in long-text areas | readability |
| Target | `--target-min: 44px` (min-height/min-width) | buttons, sidebar links, table action links, checkboxes (via label padding), eye toggle |

### Contrast ratios (WCAG 2.x, calculated, not estimated)

| Pair | Ratio | Requirement |
|---|---|---|
| Body text `#1A1F2B` on white | 16.47:1 | ≥ 7 ✔ |
| Body text on page `#F5F7FB` | 15.36:1 | ≥ 7 ✔ |
| Muted text `#454E5F` on white | 8.37:1 | ≥ 7 ✔ |
| Muted text on page `#F5F7FB` | 7.81:1 | ≥ 7 ✔ |
| Link / blue `#0B4DB3` on white | 7.70:1 | ≥ 4.5 ✔ (7 ✔) |
| Link / blue on page | 7.18:1 | ✔ |
| White on blue (primary button) | 7.70:1 | ✔ |
| White on blue-dark (hover) | 10.68:1 | ✔ |
| Logo red `#CE1F2A` on white (error border/icon) | 5.45:1 | ≥ 3 (non-text) ✔ |
| Error text `#A3141D` on white | 7.85:1 | ≥ 4.5 ✔ |
| White on red (danger button) | 5.45:1 | ≥ 4.5 ✔ |
| Sidebar white on navy `#082B66` | 13.57:1 | ✔ |
| Sidebar inactive `#D6E2F5` on navy | 10.37:1 | ✔ |
| Yellow on navy (sidebar active bar / focus) | 8.58:1 | ≥ 3 ✔ |
| Yellow on blue (accent) | 4.87:1 | ≥ 3 ✔ |
| Yellow on white | 1.58:1 | ✘ — **so yellow is never used on white** |
| Focus ring blue-dark on white / on page | 10.68 / 9.96:1 | ≥ 3 ✔ |
| Field border `#6B7486` on white | 4.70:1 | ≥ 3 (non-text) ✔ |
| Badge success / warning / danger / info / neutral / primary | 7.75 / 7.68 / 6.99 / 8.95 / 10.05 / 7.70:1 | ≥ 4.5 ✔ |

---

## 3. Every password create/change entry point

All five switch to **one** shared rule, `password_problems(string $pw): array`, in `core/Helpers.php`. It returns the
failed parts as plain sentences: `[]` means OK, otherwise e.g. `['at least 1 special character (e.g. ! @ # $ %)']`.
It uses `PASSWORD_MIN_LENGTH` (8, existing) and the new `PASSWORD_SPECIAL_CHARS` constant. The server message
names exactly what failed: "Your password needs at least 1 special character (e.g. ! @ # $ %)."

| # | Where | Today | After |
|---|---|---|---|
| 1 | Register — `AuthController::register` (Validator `->min('password', PASSWORD_MIN_LENGTH)`) | length only | shared rule (as a `Validator::strongPassword()` method that calls the helper) + confirm match (already there) |
| 2 | Reset password (after OTP) — `AuthController::resetPassword` (`mb_strlen < PASSWORD_MIN_LENGTH`) | length only | shared rule; confirm field already there |
| 3 | Admin create user — `AdminController::userSave` (`strlen < PASSWORD_MIN_LENGTH`) | length only | shared rule; **+ confirm field** (the form has none) |
| 4 | Admin reset a user's password — `AdminController::userResetPassword` | length only | shared rule; **+ confirm field** |
| 5 | Applicant changes own password — `ApplicantController::profile` (`change_password`) (U3) | length only, "Password too short." | shared rule; **+ confirm field** |
| — | **Login** — `Auth::login` | `password_verify` only | **unchanged**: no strength rule. `AuthController::login` gains a friendly empty-field check ("Please enter your email address and password.") before calling `Auth::login`; old passwords keep working. |

Live checklist ("At least 8 characters", "At least 1 special character (e.g. ! @ # $ %)", "Passwords match", each
with a ✓/✗ icon **and** the words "done" / "not yet" for screen readers) on register, reset, profile change and
both admin modals. The browser check mirrors the server rule. Its special-character set is printed from the same PHP
constant, so the two can't drift.

---

## 4. Files that change

**New**
- `public/assets/img/sk-logo.png` (512×512, from Image 1, white background kept, since it is the seal's own background), `barangay-seal.png` (Image 2), `favicon-32.png`, `apple-touch-icon.png` (180×180). Resized with PHP GD.
- `public/assets/fonts/atkinson-hyperlegible-{400,700}-{normal,italic}.woff2` + `OFL.txt` (SIL Open Font License, from the `@fontsource/atkinson-hyperlegible` package).
- `public/assets/vendor/bootstrap/` (css + js + LICENSE), `public/assets/vendor/bootstrap-icons/` (css + fonts + LICENSE) (U2).
- `public/assets/js/ui.js`: shared, loaded on every page:
  - `skPasswordToggle()`, the one eye-toggle function;
  - the password checklist;
  - the text-size control (A / A+ / A++; `localStorage` in try/catch, applied before first paint by a 3-line inline script in `<head>` so there's no flash);
  - the login empty-field modal.
- `app/views/layouts/auth_head.php` + `auth_brand.php`: a shared `<head>` and logo block for the 7 auth pages and 2 error pages, so the asset list, favicon and text-size control are written once.
- `docs/TEST_CHECKLIST_UI.md` (Stage 5).

**Changed**
- `config/constants.php`: `APP_NAME`, `APP_NAME_SHORT`, `APP_SUBTITLE`, `PASSWORD_SPECIAL_CHARS`.
- `core/Helpers.php`:
  - `asset()` appends `?v=<filemtime>` for cache-busting on every local CSS/JS file;
  - `password_problems()`;
  - `field_error()`: inline error markup with `aria-describedby` and `role="alert"`.
- `core/Validator.php`: `strongPassword()`.
- `core/Notification.php`: e-mail shell restyled (navy header with the name as text, blue button, larger body text) and renamed.
- `app/controllers/AuthController.php`: rename strings, empty-field check at login, shared rule on register/reset.
- `app/controllers/AdminController.php`, `ApplicantController.php`: shared rule + confirm on the 3 other entry points (the password lines only).
- `app/controllers/ReportController.php`: PDF header shows both logos (embedded as data URIs, since Dompdf has remote loading off) + new name.
- `app/views/layouts/header.php`, `sidebar.php`, `footer.php`:
  - local assets, favicon;
  - logo in the brand;
  - text-size control in the top bar;
  - `aria-label` on the menu toggle and bell;
  - skip-to-content link.
- `app/views/auth/*.php` (7 files), `app/views/errors/*.php` (2):
  - shared head;
  - logos and the new name;
  - labels above fields;
  - eye toggles;
  - checklist;
  - login modal.
- `public/assets/css/style.css`:
  - the tokens, Bootstrap variable overrides and base type;
  - focus, 44px targets and `prefers-reduced-motion`;
  - badges, tables that scroll inside their card, auth-page layout, 360px rules.
- `public/assets/js/app.js`: the menu button reports open/closed (`aria-expanded`). *(As built: the hard-coded `/sk-scholarship/public/` notification URL was left alone. No view has `data-notifications`, so that code never runs, and changing it would alter dead code only.)*
- Applicant + admin views: **restyle only**. Mostly no edits at all, because the shared CSS does the work. Edits only where a view has its own inline color/size (`calendar.php` "today" cell, `analytics.php` one inline color), an icon-only button without `aria-label`, or a placeholder used as the only label.
- `database/schema.sql`, `database/sk_scholarship.sql`: header comment only. `README.md`, `docs/PROCESS.md`: name.

**Not changed:** routes, controllers' business logic, models, statuses, the database (no migration, see U1), CSRF,
prepared statements, `.env`.

---

## 5. Stages and commits

1. **Assets:** logos, favicons, font, local Bootstrap, CSS tokens and base styles. *(commit)*
2. **Rebrand text:** constants and every row of §1. *(commit)*
3. **Layouts + auth pages:** shared auth head, logos, text-size control, login modal, eye toggle, password rule on all 5 entry points + live checklist. *(commit)*
4. **Applicant pages, then admin pages:** restyle via shared CSS, plus the few per-view fixes. *(commit)*
5. **Verify:**
   - `php -l` on every changed PHP file;
   - the grep in §1 returns only the kept items (U8, `Scholar.php` noun, slug);
   - a headless-Chrome pass: login empty-field modal + focus, eye toggle by keyboard, `password1` rejected / `password1!` accepted, old-password login still works, 130% text, 200% zoom, 360 px width with no sideways scroll, focus outline visible while tabbing;
   - write `docs/TEST_CHECKLIST_UI.md`. *(commit)*

SQL to run when done: **none** (with the U1 default).
