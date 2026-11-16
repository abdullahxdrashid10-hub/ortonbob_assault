# Vaccination Management System: System Architecture & Technical Analysis

**Project:** Child Immunization & Vaccination Management System (VMS)  
**Academic Context:** Aptech eProject  
**Standard Architecture:** Native PHP 8+, MariaDB / MySQL (XAMPP), PDO Prepared Statements, Session Authentication  
**Database Authority:** `sql/official_schema.sql` (Database: `vaccination_db`)  

---

## 1. System Overview & Problem Definition
Manual vaccination workflows (paper records, ad-hoc clinic tracking, and school camps) regularly lead to missed or delayed immunization doses, directly jeopardizing child health. The **Vaccination Management System (VMS)** is a centralized, role-based platform connecting parents, certified healthcare facilities, and system administrators to automate scheduling, slot booking, clinical dose verification, and inventory control.

---

## 2. Platform Ownership & Module Boundaries

| Role | Module Owner | Primary Responsibilities |
|---|---|---|
| **Admin** | Teammates | System-wide oversight, approving/rejecting appointment requests (`sp_review_appointment`), hospital facility verification & activation, overall reports. |
| **Parent** | **Our Team** | Account registration, child profile management, schedule monitoring, booking within 30-day window (`sp_book_appointment`), booking cancellation, printable immunization certificates (`parent/report.php`). |
| **Hospital** | **Our Team** | Facility registration, credential verification, appointment management (Approved vs. Completed), clinical vaccine administration (`sp_complete_appointment`), real-time stock inventory updates (`sp_update_vaccine_status`). |

---

## 3. Authoritative Database Schema (`vaccination_db`)

The database consists of **14 base tables**, **6 reporting views**, **6 stored procedures**, **1 trigger**, and **1 scheduled maintenance event**.

```
                           +------------------+
                           |      users       |
                           +--------+---------+
                                    |
          +-------------------------+-------------------------+
          | (role='admin')          | (role='parent')         | (role='hospital')
          v                         v                         v
   +--------------+          +--------------+          +--------------+
   |    admins    |          |   parents    |          |  hospitals   |
   +--------------+          +------+-------+          +------+-------+
                                    |                         |
                                    v                         |
                             +--------------+                 |
                             |   children   |                 |
                             +------+-------+                 |
                                    | (AFTER INSERT Trigger)  |
                                    v                         |
                        +-----------------------+             |
                        |  child_vaccinations   |             |
                        +-----------+-----------+             |
                                    |                         |
                                    v                         v
                             +--------------+      +-----------------------+
                             | appointments |<---->| hospital_vaccine_stock|
                             +------+-------+      +-----------------------+
                                    |
                                    v
                        +-----------------------+
                        |  vaccination_records  |
                        +-----------------------+
```

### 3.1 Core Entity Tables
1. **`users`**: Unified authentication registry across all roles (`admin`, `parent`, `hospital`). Stores `email`, `password_hash`, `role`, `status` (`active`, `inactive`, `blocked`), and `last_login_at`.
2. **`admins`**: Administrator personnel details linked via `user_id` FK.
3. **`parents`**: Guardian details (`user_id`, `full_name`, `cnic`, `phone`, `address`, `city`).
4. **`children`**: Child medical profiles (`parent_id`, `full_name`, `date_of_birth`, `gender`, `blood_group`, `birth_weight_kg`, `birth_certificate_no`, `allergies`, `medical_notes`, `is_active`). Soft deletes (`is_active = 0`) preserve historical medical integrity.
5. **`vaccines`**: National immunization catalog (`vaccine_name`, `short_code`, `disease_prevented`, `route`, `total_doses`, `side_effects`, `is_active`).
6. **`vaccine_schedule`**: Standard age offset milestones in days (`vaccine_id`, `dose_number`, `age_in_days`, `is_mandatory`).
7. **`child_vaccinations`**: Child dose timeline auto-generated upon child registration (`child_id`, `vaccine_id`, `dose_number`, `due_date`, `status`, `completed_date`). Status lifecycle: `upcoming`, `requested`, `booked`, `completed`, `missed`.
8. **`hospitals`**: Healthcare centers (`user_id`, `hospital_name`, `registration_no`, `address`, `city`, `area`, `phone`, `email`, `opening_time`, `closing_time`, `daily_capacity`, `status`). Newly registered hospitals default to `status='inactive'` pending admin activation.
9. **`hospital_vaccine_stock`**: Local facility inventory levels (`hospital_id`, `vaccine_id`, `status` (`available`, `unavailable`), `quantity`, `updated_by`).
10. **`appointments`**: Parent visit bookings (`child_vaccination_id`, `parent_id`, `child_id`, `hospital_id`, `vaccine_id`, `appointment_date`, `appointment_time`, `status`, `parent_note`, `admin_remarks`). Status lifecycle: `pending`, `approved`, `rejected`, `completed`, `cancelled`.
11. **`appointment_status_history`**: Audit trail of every appointment status transition (`appointment_id`, `old_status`, `new_status`, `changed_by`, `remarks`).
12. **`vaccination_records`**: Clinical proof of administered doses (`appointment_id`, `child_vaccination_id`, `child_id`, `vaccine_id`, `hospital_id`, `dose_number`, `vaccination_date`, `vaccination_time`, `batch_number`, `administered_by`, `remarks`).
13. **`notifications`**: In-app notifications dispatched across parents, hospitals, and admins.
14. **`activity_log`**: System-level administrative audit log.

---

## 4. Authoritative Business Logic: Stored Procedures & Triggers

To prevent business logic divergence across disparate panels, all core state transitions, capacity validations, and inventory adjustments reside strictly inside MariaDB stored procedures:

### 4.1 `trg_children_after_insert`
- Triggered automatically upon `INSERT` on `children`.
- Invokes `sp_generate_child_schedule(NEW.child_id)` to calculate all due dates from `date_of_birth + age_in_days` and populates `child_vaccinations`.

### 4.2 `sp_book_appointment(cv_id, hospital_id, date, time, note, @out_appt_id)`
- Enforces dose eligibility: status must be `upcoming` or `missed`.
- Enforces calendar rules: date cannot be in the past (`date >= CURDATE()`).
- Validates stock: hospital must have `status='available'` in `hospital_vaccine_stock`.
- Validates facility daily limit: rejects booking if `booked >= daily_capacity`.
- Atomic state update: creates appointment with status `pending`, sets `child_vaccinations.status = 'requested'`, records status history, and dispatches admin notification.

### 4.3 `sp_review_appointment(appointment_id, admin_id, decision, remarks)`
- Executed by administrators: decision is either `approved` or `rejected`.
- If approved: transitions appointment to `approved`, updates `child_vaccinations.status = 'booked'`, and dispatches notification to the hospital.
- If rejected: transitions appointment to `rejected`, reverts `child_vaccinations.status` to `upcoming` or `missed`, records remarks, and notifies parent.

### 4.4 `sp_complete_appointment(appointment_id, hospital_user_id, batch, admin_by, remarks)`
- Enforces appointment readiness: status must currently be `approved`.
- Validates ownership: appointment must be assigned to the logged-in hospital user.
- Transitions appointment to `completed` (`completed_at = NOW()`).
- Transitions child vaccination dose to `completed` (`completed_date = CURDATE()`).
- Creates permanent clinical record in `vaccination_records` with batch number and administering doctor/nurse.
- Decrements stock in `hospital_vaccine_stock` by 1; if stock reaches 0, automatically flips availability status to `'unavailable'`.
- Notifies parent and system administrators.

### 4.5 `sp_update_vaccine_status(hospital_id, vaccine_id, status, quantity, user_id)`
- Upserts hospital stock levels into `hospital_vaccine_stock`.

### 4.6 `evt_daily_maintenance`
- Scheduled event running daily at 00:05.
- Flags overdue upcoming doses as `missed` when `due_date < CURDATE()`.
- Dispatches 7-day advance reminders via `sp_generate_due_reminders(7)`.

---

## 5. End-to-End Operational Lifecycle

```mermaid
sequenceDiagram
    autonumber
    actor Parent
    participant App as VMS Application
    participant DB as MariaDB (vaccination_db)
    actor Admin
    actor Hospital

    Parent->>App: Register Child Profile
    App->>DB: INSERT INTO children
    DB-->>DB: trg_children_after_insert -> sp_generate_child_schedule()
    Note over DB: Generates 17 schedule doses in child_vaccinations

    Parent->>App: Book Due Dose (within 30-day window)
    App->>DB: CALL sp_book_appointment(cv_id, hosp_id, date, time, note, @id)
    Note over DB: Validates stock, capacity, date; sets status='pending'

    Admin->>DB: CALL sp_review_appointment(id, admin_id, 'approved', 'OK')
    Note over DB: Sets appt status='approved', dose status='booked'

    Hospital->>App: Open Approved Visit & Administer Vaccine
    App->>DB: CALL sp_complete_appointment(id, hosp_user_id, batch, staff, remarks)
    Note over DB: Sets status='completed', decrements stock, inserts vaccination_records

    Parent->>App: View Immunization Report & Certificate (report.php)
    App-->>Parent: Display Verified Clinical Record & Print Certificate
```

---

## 6. Security & Architectural Standards
- **Zero Raw Password Exposure:** Passwords securely hashed with bcrypt (`PASSWORD_DEFAULT`).
- **Timing & Enumeration Resistance:** Email enumeration prevented during hospital login by validating password hashes before evaluating account activation flags.
- **CSRF Token Guards:** Every state-modifying `POST` request (registration, child edit, booking, cancellation, stock update, administration, logout) protected by strict cryptographic token validation (`csrf_check()`).
- **XSS Sanitization:** All dynamic output escaped through `e()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`).
- **Database Encapsulation:** Zero inline SQL in presentation pages; all database queries isolated in `includes/shared_queries.php`.
