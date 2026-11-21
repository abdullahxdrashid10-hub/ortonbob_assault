# VaxCare — System Functionalities & Aptech eProject Deliverables Overview

> **Project:** Vaccination Management System (`VaxCare`) — Aptech eProject  
> **Stack:** Plain PHP 8, MariaDB / MySQL (XAMPP), PDO Prepared Statements, Native Sessions, Vanilla JS + Local Three.js r128  
> **Database:** `vaccination_db` ([sql/official_schema.sql](../sql/official_schema.sql))  
> **Scope Owned:** Public Landing, Parent Panel (`/parent`), Hospital Panel (`/hospital`), Shared Logic (`/includes`, `/config`), UI/3D Assets (`/assets`)

---

## 1. Aptech Problem Statement Deliverables -> Code Mapping

### A. Parent Module (`/parent`)

| Deliverable (PDF 2) | File | What It Does |
|---|---|---|
| **Landing & Entry** | [index.php](../index.php) | Public home page with split hero, 4 role/feature SVG cards, and the embedded 8-vaccine Parent Guide ([includes/vaccine_guide.php](../includes/vaccine_guide.php)). |
| **Register & Login** | [parent/register.php](../parent/register.php) | Transactional signup inserting into `users` (`role='parent'`) and `parents` (`full_name`, `cnic`, `phone`, `address`, `city`). Enforces column length limits, password confirmation, CSRF, and friendly duplicate email/CNIC errors. |
| | [parent/login.php](../parent/login.php) | Authenticates via `auth_login_parent()` in [includes/auth.php](../includes/auth.php), regenerates session ID, updates `users.last_login_at`, and sets `user_id`, `role`, `parent_id`, `parent_name`. |
| | [parent/logout.php](../parent/logout.php) | POST + CSRF-protected logout calling `auth_logout()`. |
| **Vaccination Dates & Alerts** | [parent/dashboard.php](../parent/dashboard.php) | 4 animated KPI cards (Active Children, Doses Due/Missed, Requested/Booked, Completed), 30-day upcoming/overdue doses grouped by child with **Book** links, and the 5 latest notifications with CSRF-protected Mark-as-Read. |
| | [parent/schedule.php](../parent/schedule.php) | Child selector with ownership check, pure CSS `conic-gradient` progress ring (`X / Y doses · %`), status legend, and colour-coded vertical timeline from `child_vaccinations`. **Book** links appear only for `upcoming`/`missed` doses due within 30 days. |
| **Details of Child** | [parent/children.php](../parent/children.php) | Lists active children (`is_active=1`), adds new child profiles (validating `checkdate()`, <=13 years, enum gender/blood group, weight `0.30`–`9.99` kg, column lengths; preserves form values on validation error), verifies the DB trigger generated schedule rows, and performs soft delete (`is_active=0`) blocked if any dose is `requested`/`booked`. |
| | [parent/child_edit.php](../parent/child_edit.php) | Edits medical/contact fields for an owned active child while keeping `date_of_birth` read-only so trigger-generated schedules never go stale. |
| **Book Hospital & Request** | [parent/hospitals.php](../parent/hospitals.php) | Lists `status='active'` hospitals with `LIKE` search across name, city, area, and address; shows hours, daily capacity, phone, live in-stock vaccine pills, and the 8-vaccine Parent Guide. |
| | [parent/book.php](../parent/book.php) | Verifies dose ownership and 30-day eligibility, lists active hospitals stocking that vaccine, enforces `opening_time`–`closing_time` bounds (client + server), shows live remaining slots and booking summary, and calls `CALL sp_book_appointment(...)`. |
| | [parent/bookings.php](../parent/bookings.php) | Lists all appointments for the parent's children with status badges, notes/admin remarks, chronological `appointment_status_history` trail, and cancellation for `pending` requests. |
| **Report of Vaccination Taken** | [parent/report.php](../parent/report.php) | Queries `vaccination_records` joined with `children`, `vaccines`, and `hospitals`; supports child filtering, deterministic certificate reference numbers, signature lines, verification seal, and a black-on-white `@media print` certificate layout. |
| **My Profile** | [parent/profile.php](../parent/profile.php) | Updates `parents` contact fields (syncing session `parent_name`) and changes password in `users` after verifying the current password. |

---

### B. Hospital Module (`/hospital`)

| Deliverable (PDF 2) | File | What It Does |
|---|---|---|
| **Register & Login** | [hospital/register.php](../hospital/register.php) | Transactional signup inserting `users` (`role='hospital'`, `status='inactive'`) and `hospitals` (`status='inactive'`) via `shared_register_hospital_facility()`, with no auto-seeded stock. Displays admin approval notice; manual activation SQL provided in [docs/HOSPITAL_ACTIVATION.sql](HOSPITAL_ACTIVATION.sql). |
| | [hospital/login.php](../hospital/login.php) | Calls `auth_login_hospital()`. Checks password hash **first** (preventing email enumeration), then shows `"Your registration is awaiting admin approval."` if inactive. |
| | [hospital/logout.php](../hospital/logout.php) | POST + CSRF-protected logout destroying the hospital session. |
| **Appointments & Vaccine Status** | [hospital/appointments.php](../hospital/appointments.php) | Displays the **Today** KPI strip (Today's Approved Visits, Completed Today, Low/Out-of-Stock `<10` alerts) and **Approved (To Do)** / **Completed** tabs. Approved rows support: **(1) Complete** (pre-checks ownership/status, calls `sp_complete_appointment` with batch number, clinician, remarks, auto-decrementing stock & creating `vaccination_records`) and **(2) Not Vaccinated** (transactional update via `shared_hospital_mark_not_vaccinated()`, re-opening the dose for re-booking, logging history, notifying parent, and leaving stock untouched). |
| **Stock Management** | [hospital/stock.php](../hospital/stock.php) | Lists all active vaccines (`LEFT JOIN hospital_vaccine_stock`), rejects unknown/inactive vaccines, forces `unavailable` when quantity is `0`, and updates stock via `CALL sp_update_vaccine_status(...)`. ([hospital/inventory.php](../hospital/inventory.php) redirects here.) |
| **Facility Profile** | [hospital/profile.php](../hospital/profile.php) | Edits hospital metadata (`hospital_name`, `phone`, `address`, `city`, `area`, `opening_time`, `closing_time`, `daily_capacity`) and updates staff password in `users`. |

---

### C. Admin Module (Teammates' Scope — Database Support Ready)

- Owned by teammates (not part of `/parent` or `/hospital` PHP files).
- Supported in [sql/official_schema.sql](../sql/official_schema.sql) via views (`v_all_children`, `v_booking_details`, `v_upcoming_vaccinations`, `v_vaccination_report`, `v_hospital_vaccine_status`, `v_vaccine_availability`), `sp_review_appointment()`, and [docs/HOSPITAL_ACTIVATION.sql](HOSPITAL_ACTIVATION.sql).

---

## 2. Shared Backend, Security & Frontend Architecture

| Layer | File | Responsibility |
|---|---|---|
| **Config & Security** | [config/config.php](../config/config.php) | Sets `Asia/Karachi` timezone, `FEATURE_NOT_VACCINATED = true`, error logging to `logs/php-error.log`, friendly exception handler, dynamic `BASE_URL`, DB constants, status constants (`APT_*`, `CV_*`), `httponly`/`SameSite=Lax` session cookies, `db()`, `csrf_token()`, `csrf_field()`, `csrf_check()`, `e()`, `flash()`, `age_text()`, `badge()`. |
| **Auth & Guards** | [includes/auth.php](../includes/auth.php) | Centralizes `auth_login_parent()`, `auth_login_hospital()`, `auth_is_parent_logged_in()`, `auth_is_hospital_logged_in()`, `require_parent()`, `require_hospital()`, `auth_logout()`. |
| **Data Access Layer** | [includes/shared_queries.php](../includes/shared_queries.php) | Holds **all SQL** across the app (zero inline SQL in pages), tagged by tables touched, using PDO prepared statements, transactions, and strict `SQLSTATE 45000` error extraction (`shared_extract_sp_error()`). |
| **Layout & Shared UI** | [includes/header.php](../includes/header.php), [includes/footer.php](../includes/footer.php) | Role-aware navigation with active link highlighting, CSRF logout forms, flash message renderer, and local script loading. |
| | [includes/vaccine_guide.php](../includes/vaccine_guide.php) | Renders the 8 essential vaccines from `vaccines` with importance, immune role, components, route, and mild side effects. |
| **Styling & Motion** | [assets/css/style.css](../assets/css/style.css) | Onyx Black (`#0f111a`), Electric Indigo (`#6366f1`), and Tropical Mint (`#2ee59d`) theme; frosted glass cards, timeline/progress-ring CSS, responsive grids, `:focus-visible` accessibility rings, `prefers-reduced-motion` rules, and `@media print` certificate styles. |
| | [assets/js/app.js](../assets/js/app.js) | Mobile nav `aria-expanded` toggle, `IntersectionObserver` scroll-reveal, KPI count-up animation, 4s success toast auto-fade, and `Escape` key popup closing. |
| | [assets/js/three.min.js](../assets/js/three.min.js), [assets/js/bg-3d.js](../assets/js/bg-3d.js) | Local Three.js r128 WebGL 3D backdrop (no CDN): rotating DNA double-helix, wireframe immunity cells, and parallax particle field. |

---

## 3. Frontend Image Assets (`assets/img/`)

### Hero & Certificate Artwork
![hero-immunization.jpg](../assets/img/hero-immunization.jpg)
![cert-crest.jpg](../assets/img/cert-crest.jpg)

### Vaccine Guide Artwork
![vac-bcg.jpg](../assets/img/vac-bcg.jpg)
![vac-opv.jpg](../assets/img/vac-opv.jpg)
![vac-penta.jpg](../assets/img/vac-penta.jpg)
![vac-pcv.jpg](../assets/img/vac-pcv.jpg)
![vac-rota.jpg](../assets/img/vac-rota.jpg)
![vac-ipv.jpg](../assets/img/vac-ipv.jpg)
![vac-mr.jpg](../assets/img/vac-mr.jpg)
![vac-tcv.jpg](../assets/img/vac-tcv.jpg)

---

## 4. Aptech Project Report Deliverables Mapping (PDF 1)

| # | Required Report Section | Where Source Material Lives |
|---|---|---|
| 0 | Project Synopsis / Overview | [docs/ANALYSIS.md](ANALYSIS.md), Section 1–2 above |
| 1 | Certificate of Completion | Template to format in final Word/PDF report |
| 2 | Table of Contents | Auto-generated in final Word/PDF report |
| 3 | Problem Definition | [docs/ANALYSIS.md](ANALYSIS.md) §1 |
| 4 | Customer Requirement Specification (CRS) | [docs/ANALYSIS.md](ANALYSIS.md) §2–6 & Section 1 above |
| 5 | Project Plan | [docs/PROGRESS.md](PROGRESS.md) §2 & §9 |
| 6 | E-R Diagrams | [docs/ANALYSIS.md](ANALYSIS.md) §3 (14 tables) + [sql/official_schema.sql](../sql/official_schema.sql) |
| 7 | Algorithms | [docs/ANALYSIS.md](ANALYSIS.md) §4–5 (trigger schedule, 30-day rule, booking, completion, auth) |
| 8 | GUI Standards Document | [assets/css/style.css](../assets/css/style.css) (Onyx/Indigo/Mint tokens, typography, badges, print rules) |
| 9 | Interface Design Document | Frontend pages in Section 1 + browser screenshots |
| 10 | Task Sheet | [docs/DELIVERABLES_CHECKLIST.txt](DELIVERABLES_CHECKLIST.txt) (Parent/Hospital vs. Admin split) |
| 11 | Project Review and Monitoring Report | [docs/PROGRESS.md](PROGRESS.md) |
| 12 | Unit Testing Check List | [docs/PROGRESS.md](PROGRESS.md) §6 & §8 |
| 13 | Final Check List | [docs/DELIVERABLES_CHECKLIST.txt](DELIVERABLES_CHECKLIST.txt) |
