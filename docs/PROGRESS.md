# VaxCare: Build Progress Tracker

> One place to see what has been built, where it lives, what is verified, and what is still open.
> Last updated: 18-Nov-2026 (Oat White & Slate Blue re-theme + local illustrations).

---

## 1. Project at a glance

| Item | Value |
|---|---|
| Project | Vaccination Management System ("VaxCare"), Aptech eProject |
| Stack | Plain PHP 8, MariaDB (XAMPP), PDO prepared statements, sessions. No framework, no three.js |
| Database | `vaccination_db`, from `sql/official_schema.sql` (team dump, authoritative, never modified by us except one typo fix, see §7) |
| Our scope | **Parent panel** and **Hospital panel**. Admin panel belongs to teammates |
| Repo | `github.com/abdullahxdrashid10-hub/ortonbob_assault` (branch `main`) |
| Delivery | Push to `main` + fresh `E project.zip` after every change |

---

## 2. Timeline of steps

| # | Step | Commit | Status |
|---|---|---|---|
| 0 | Analysis doc (stack, ownership, decisions) | `2367b9a`, `808008f` | Done (rewritten in 3C) |
| 1 | First parent + hospital panels on **placeholder** schema | `cd9e75b` | Superseded |
| 2 | Full parent panel, `shared_queries.php` introduced, `patch_01.sql` | `d7fcb9b` | Superseded |
| – | Bug-fix pass (tasks 1–8: vaccinated date, delete guard, dynamic `BASE_URL`, booking checks, POST+CSRF logout, cookie flags) | `da5f97f` | Done |
| 3A | Re-platform parent panel onto the **real** schema (`users` + `parents`, trigger-built schedule, soft delete) | `c05a625` | Done |
| Part 0 | Small fixes: read-only DOB, no double escaping, `checkdate()` + 13-year limit, active-appointment delete guard, 30-day Book rule | `1d0832c` | Done |
| F1–F6 + 3B | Error logging + friendly error page, try/catch, column-length validation, `book.php` rebuilt, full hospital panel | `76f9897` | Done |
| 3B (v2) | Hospital approval flow, "awaiting approval" login message, `stock.php`, Not-Vaccinated stub | `ebfb823` | Done (merged into 3C commit) |
| 3C | Security cleanup, inactive registration, opening-hours check, timezone, docs | `ebfb823` | Done (one item partial, see §8) |
| Bugfix + UX | CSS variables/badges/textarea fix + strict SQLSTATE `45000` check | `3654517` | Done |
| UX #1 | Vertical immunization timeline + pure CSS progress ring on `schedule.php` | `d518a6e` | Done |
| UX #2 | Framed official certificate layout, ref #, signature lines, print CSS on `report.php` | `f9ef67c` | Done |
| UX #3 | Booking form: hospital hours bounds, slot availability hint, booking summary | `96a77c0` | Done |
| UX #6 | Active nav highlight, success toast auto-fade, Complete confirm dialog, empty hints | `1074203` | Done |
| Days 1–2 | `:focus-visible`, ARIA attrs, `:active`/`:disabled` buttons, mobile `flex-wrap`, 2D `#bg-canvas` | `42ea118` | Done |
| Theme + Assets | Oat White (`#f7f5f0`) & Slate Blue (`#2c5282`) re-theme + local hero & certificate crest images (`assets/img/`) | *latest* | Done |

---

## 3. Feature status

### Parent panel (`/parent`)

| Page | What it does | Status |
|---|---|---|
| `register.php` | Transaction: `users` (role parent) + `parents`. Confirm password, length limits, friendly duplicate email/CNIC errors | ✅ |
| `login.php` / `logout.php` | `auth_login_parent()`, session regenerate, `last_login_at`. Logout is POST + CSRF | ✅ |
| `dashboard.php` | 4 KPI cards, doses due ≤ 30 days grouped by child, 5 latest notifications with mark-as-read | ✅ |
| `children.php` | List/add active children, soft delete (`is_active=0`), blocked if a dose is `requested`/`booked`. Checks the trigger created schedule rows | ✅ |
| `child_edit.php` | Edit profile, DOB read-only | ✅ |
| `schedule.php` | Progress ring (`X/Y doses · %`) + colour-coded vertical timeline from `child_vaccinations`, Book button only for upcoming/missed AND due ≤ 30 days | ✅ |
| `hospitals.php` | Active hospitals only, LIKE search on name/city/area/address, hours, phone, vaccines in stock | ✅ |
| `book.php` | Ownership + eligibility check, hospitals stocking that vaccine, date ≥ today, time bounded to hospital hours, live slot hint & summary, `CALL sp_book_appointment` | ✅ |
| `bookings.php` | All my appointments with status badges, notes/admin remarks, cancel for `pending` | ✅ |
| `report.php` | `vaccination_records` per child, deterministic ref #, framed certificate layout, signature lines + seal, print stylesheet | ✅ |
| `profile.php` | Edit parent details, change password (current password verified) | ✅ |

### Hospital panel (`/hospital`)

| Page | What it does | Status |
|---|---|---|
| `register.php` | Transaction: `users` + `hospitals`, both `inactive`. No default stock. Message: admin must approve | ✅ |
| `login.php` / `logout.php` | Password checked **first**, then "Your registration is awaiting admin approval" if inactive. Logout POST + CSRF | ✅ |
| `appointments.php` | Tabs Approved / Completed. Child, age, allergies, vaccine, dose, parent, note. Complete form → PHP ownership pre-check → `CALL sp_complete_appointment` | ✅ |
| `stock.php` | All active vaccines LEFT JOIN stock. Rejects unknown/inactive vaccines, forces `unavailable` at quantity 0, `CALL sp_update_vaccine_status` | ✅ |
| `profile.php` | Edit hospital details + change password | ✅ built, **not in nav** (3B v2 nav spec = Appointments, Vaccine Stock, Logout) |
| `inventory.php` | Redirect to `stock.php` (kept for old links) | Legacy stub |

### Shared

| File | Role |
|---|---|
| `config/config.php` | Timezone `Asia/Karachi`, `FEATURE_NOT_VACCINATED=false`, error logging to `logs/php-error.log`, friendly exception page, DB constants, dynamic `BASE_URL`, status constants, secure session cookies, `db()`, CSRF helpers, `e()`, `flash()`, `badge()` |
| `includes/auth.php` | All login/guard logic: `auth_login_parent()`, `auth_login_hospital()`, `require_parent()`, `require_hospital()`, `auth_logout()` |
| `includes/shared_queries.php` | **All SQL** (pages contain none). Each function tagged with the tables it touches |
| `includes/header.php` / `footer.php` | Layout, role-based nav, flash messages |

---

## 4. Database usage (how our code talks to `vaccination_db`)

| We use | For |
|---|---|
| `trg_children_after_insert` → `sp_generate_child_schedule` | Builds a child's doses on insert. We **never** insert into `child_vaccinations` |
| `sp_book_appointment` | Booking (status, past date, stock, daily capacity, history, admin notification) |
| `sp_complete_appointment` | Completion (records, stock −1, history, notifications) |
| `sp_update_vaccine_status` | Stock upsert |
| `sp_review_appointment` | Admin approve/reject. **Admin side, we don't call it**; used in manual testing |
| Direct writes we still do | `children` (add/edit/soft delete), `parents`, `users` (register, password, last login), `hospitals` (register, profile), `notifications.is_read`, and the parent cancel (see §8) |

Business rules enforced in **PHP on top of** the procedures: dose ownership (procedures don't check it), 30-day booking window, opening hours, active hospital, column lengths, enum values.

---

## 5. Security checklist

| Control | Status |
|---|---|
| Login required on every panel page (`require_parent` / `require_hospital`) | ✅ |
| Ownership check on every id from the request | ✅ |
| CSRF token on every POST, including logout | ✅ |
| Output escaped with `e()` | ✅ |
| PDO prepared statements only | ✅ |
| Session cookie `httponly`, `samesite=Lax`, `session_regenerate_id` on login | ✅ |
| Errors hidden from users, logged to file | ✅ |
| Hospital login can't be used to discover emails (password checked first) | ✅ |
| Procedure business errors shown, other DB errors generic | ✅ (strict SQLSTATE `45000` check) |
| No test scripts under web root | ✅ (`/scratch` deleted and git-ignored) |

---

## 6. How to run and test locally

1. Import `sql/official_schema.sql` into a database named `vaccination_db` (see `docs/SETUP.md`).
2. Turn the event scheduler on: `SET GLOBAL event_scheduler = ON;`
3. Open `http://localhost/E%20project/`.
4. Seed accounts in the dump have **placeholder** hashes, so they can't log in. Register new accounts, or set a real bcrypt hash yourself. (Our local DB has seed users 1–5 set to `password` for testing only. That change is not in the dump.)
5. To activate a newly registered hospital: run `docs/HOSPITAL_ACTIVATION.sql` in phpMyAdmin.
6. To approve a booking as admin: `CALL sp_review_appointment(<appointment_id>, 1, 'approved', 'ok');`

**End-to-end path:** register parent → add child born ~2 months ago (expect 17 doses) → book a due dose → approve via SQL → hospital completes it → check `vaccination_records` and the parent report.

---

## 7. Changes outside our own code

| Change | Why |
|---|---|
| `sql/official_schema.sql` line ~842: fixed `disease_prevented``disease_prevented` typo in view `v_vaccine_availability` | The dump failed to import otherwise. **Tell the team** so their copy matches |
| Old `sql/schema.sql`, `sql/patch_01.sql` moved to `sql/archive/` | Only one schema in `/sql` |

---

## 8. Known issues and open items (honest list)

*(Resolved since Step 3C: missing CSS classes/variables/textarea in `3654517`, strict SQLSTATE `45000` check in `3654517`, and empty `#bg-canvas` in `42ea118`.)*

| # | Issue | Where | Severity |
|---|---|---|---|
| 1 | CALLs use named placeholders and `@p_appointment_id`, not the exact `(?,?,?,?,?,@appt_id)` pattern from the spec. Works the same | `shared_book_appointment()` | Low |
| 2 | Parent cancel writes to admin-owned tables (`appointments`, `child_vaccinations`, `appointment_status_history`). Tagged "WRITES TO ADMIN-OWNED TABLES: confirm with the admin team" | `shared_cancel_parent_appointment()` | Needs team decision |
| 3 | "Not Vaccinated" has no DB procedure. Stub throws "not available yet", button hidden by `FEATURE_NOT_VACCINATED=false` | `shared_hospital_mark_not_vaccinated()` | Waiting on team |
| 4 | `/scratch` removed from the repo, but the old test script is **still in git history** (commit `76f9897`). Not rewritten because that needs a force-push. It contains only local test logic and the test password `password`, no real secrets | git history | Low, decide if a force-push is OK |
| 5 | Session keys may need to match the admin panel's. All login logic is in `includes/auth.php` so it can be swapped in one place | `includes/auth.php` | Waiting on admin code |
| 6 | After 3C, the new inactive-registration + approval + stock-before-booking flow was syntax-checked but **not re-run end to end** (the test script was deleted for security) | `hospital/register.php`, `auth_login_hospital()`, `hospital/stock.php` | Medium, retest manually |
| 7 | `hospital/profile.php` works but has no nav link | `includes/header.php` | Low |

---

## 9. Next steps (remaining 8-day plan)

1. **Days 3–4 — Standout Viva Features:**
   - Hospital "Today" summary strip on `hospital/appointments.php` (Today's Approved, Completed Today, Low-Stock `< 10` warnings).
   - Status history trail (`pending → approved → completed` with timestamps from `appointment_status_history`) on `parent/bookings.php`.
2. **Days 5–6 — Manual End-to-End Testing:**
   - Walk through the full parent + hospital + admin-SQL lifecycle in Chrome/Edge (including mobile `375px` width and Print Preview on `report.php`), verifying the new hospital approval path (issue #6).
   - Confirm with the admin team on issues #2, #3, #5, and the schema typo in §7.
3. **Days 7–9 — Aptech eProject Documentation Set:**
   - CRS, ER diagram (14 tables), algorithms/flowcharts, GUI standards, unit-test checklist, review report.
4. **Day 10 — Freeze & Demo Rehearsal:**
   - Final push, zip, and 5-minute live walkthrough.

---

## 10. File map

```
E project/
├── config/config.php          settings, helpers, constants
├── includes/
│   ├── auth.php               login + guards
│   ├── shared_queries.php     ALL SQL
│   ├── header.php, footer.php layout + nav
├── parent/                    11 pages (see §3)
├── hospital/                  register, login, logout, appointments, stock, profile, inventory (redirect)
├── assets/
│   ├── css/style.css          Oat White & Slate Blue theme
│   ├── js/app.js              menu, scroll reveal, count-up, toast, 2D canvas
│   └── img/                   hero-immunization.jpg, cert-crest.jpg
├── sql/official_schema.sql    the real DB (authoritative)
├── sql/archive/               old placeholder schemas (reference only)
├── docs/
│   ├── ANALYSIS.md            architecture + schema + procedures
│   ├── SETUP.md               import steps, event scheduler
│   ├── HOSPITAL_ACTIVATION.sql manual hospital approval
│   └── PROGRESS.md            this file
└── logs/                      php-error.log (git-ignored)
```
