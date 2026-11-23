# Vaccination Management System (`vaccination_db`) — Entity-Relationship Diagram

> **Source of Truth:** [sql/official_schema.sql](../sql/official_schema.sql)  
> **Scope:** All **14 base tables**, primary keys (`PK`), foreign keys (`FK`), unique keys (`UK`), and `ON DELETE` referential integrity rules.

---

## 1. Visual ER Diagram (Mermaid)

```mermaid
erDiagram
    users {
        int user_id PK
        varchar email UK
        varchar password_hash
        enum role
        enum status
        datetime last_login_at
        timestamp created_at
        timestamp updated_at
    }

    admins {
        int admin_id PK
        int user_id FK
        varchar full_name
        varchar phone
        timestamp created_at
    }

    parents {
        int parent_id PK
        int user_id FK
        varchar full_name
        varchar cnic UK
        varchar phone
        varchar address
        varchar city
        timestamp created_at
        timestamp updated_at
    }

    hospitals {
        int hospital_id PK
        int user_id FK
        varchar hospital_name
        varchar registration_no UK
        varchar address
        varchar city
        varchar area
        decimal latitude
        decimal longitude
        varchar phone
        varchar email
        time opening_time
        time closing_time
        smallint daily_capacity
        enum status
        int added_by FK
        timestamp created_at
        timestamp updated_at
    }

    children {
        int child_id PK
        int parent_id FK
        varchar full_name
        date date_of_birth
        enum gender
        enum blood_group
        decimal birth_weight_kg
        varchar birth_certificate_no
        varchar allergies
        text medical_notes
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }

    vaccines {
        int vaccine_id PK
        varchar vaccine_name
        varchar short_code
        varchar disease_prevented
        text description
        varchar route
        tinyint total_doses
        varchar side_effects
        tinyint is_active
        timestamp created_at
    }

    vaccine_schedule {
        int schedule_id PK
        int vaccine_id FK
        tinyint dose_number
        smallint age_in_days
        tinyint is_mandatory
    }

    child_vaccinations {
        int child_vaccination_id PK
        int child_id FK
        int vaccine_id FK
        tinyint dose_number
        date due_date
        enum status
        date completed_date
        timestamp created_at
        timestamp updated_at
    }

    hospital_vaccine_stock {
        int stock_id PK
        int hospital_id FK
        int vaccine_id FK
        enum status
        int quantity
        int updated_by FK
        timestamp updated_at
    }

    appointments {
        int appointment_id PK
        int child_vaccination_id FK
        int parent_id FK
        int child_id FK
        int hospital_id FK
        int vaccine_id FK
        date appointment_date
        time appointment_time
        enum status
        varchar parent_note
        varchar admin_remarks
        int reviewed_by FK
        datetime reviewed_at
        datetime completed_at
        timestamp created_at
        timestamp updated_at
    }

    appointment_status_history {
        int history_id PK
        int appointment_id FK
        varchar old_status
        varchar new_status
        int changed_by FK
        varchar remarks
        timestamp changed_at
    }

    vaccination_records {
        int record_id PK
        int appointment_id FK
        int child_vaccination_id FK
        int child_id FK
        int vaccine_id FK
        int hospital_id FK
        tinyint dose_number
        date vaccination_date
        time vaccination_time
        varchar batch_number
        varchar administered_by
        varchar remarks
        timestamp created_at
    }

    notifications {
        int notification_id PK
        int user_id FK
        varchar title
        varchar message
        enum type
        int related_appointment_id FK
        tinyint is_read
        timestamp created_at
    }

    activity_log {
        bigint log_id PK
        int user_id FK
        varchar action
        varchar details
        varchar ip_address
        timestamp created_at
    }

    %% Identity & Role Profiles
    users ||--o| admins : "has admin profile"
    users ||--o| parents : "has parent profile"
    users ||--o| hospitals : "manages hospital login"
    admins ||--o{ hospitals : "registers"

    %% Family & Immunization Schedule
    parents ||--o{ children : "registers"
    children ||--o{ child_vaccinations : "auto-generated via trigger"
    vaccines ||--o{ vaccine_schedule : "defines dose milestones"
    vaccines ||--o{ child_vaccinations : "scheduled for"

    %% Hospital Inventory
    hospitals ||--o{ hospital_vaccine_stock : "holds stock"
    vaccines ||--o{ hospital_vaccine_stock : "stocked as"
    users ||--o{ hospital_vaccine_stock : "updated by"

    %% Appointments & Audit Trail
    child_vaccinations ||--o{ appointments : "booked via"
    parents ||--o{ appointments : "requests"
    children ||--o{ appointments : "for child"
    hospitals ||--o{ appointments : "assigned to"
    vaccines ||--o{ appointments : "for vaccine"
    admins ||--o{ appointments : "reviews"
    appointments ||--o{ appointment_status_history : "logs transitions"
    users ||--o{ appointment_status_history : "changed by"

    %% Clinical Records & System Alerts
    appointments ||--o| vaccination_records : "produces record"
    child_vaccinations ||--o| vaccination_records : "fulfilled by"
    children ||--o{ vaccination_records : "immunization history"
    vaccines ||--o{ vaccination_records : "administered vaccine"
    hospitals ||--o{ vaccination_records : "administered at"
    users ||--o{ notifications : "receives"
    appointments ||--o{ notifications : "references"
    users ||--o{ activity_log : "audited in"
```

---

## 2. Foreign Key & Referential Integrity Matrix (All 26 Constraints)

| Table | Constraint | Column | References | `ON DELETE` | Purpose |
|---|---|---|---|---|---|
| `admins` | `fk_admin_user` | `user_id` | `users(user_id)` | `CASCADE` | Links admin profile to login account |
| `parents` | `fk_parent_user` | `user_id` | `users(user_id)` | `CASCADE` | Links parent profile to login account |
| `hospitals` | `fk_hosp_user` | `user_id` | `users(user_id)` | `SET NULL` | Links hospital staff login (preserved if user deleted) |
| `hospitals` | `fk_hosp_admin` | `added_by` | `admins(admin_id)` | `SET NULL` | Tracks which admin added the hospital |
| `children` | `fk_child_parent` | `parent_id` | `parents(parent_id)` | `CASCADE` | Assigns child to parent (app uses soft-delete `is_active=0`) |
| `vaccine_schedule` | `fk_sched_vaccine` | `vaccine_id` | `vaccines(vaccine_id)` | `CASCADE` | Standard age offsets per dose |
| `child_vaccinations` | `fk_cv_child` | `child_id` | `children(child_id)` | `CASCADE` | Child's personalized dose rows |
| `child_vaccinations` | `fk_cv_vaccine` | `vaccine_id` | `vaccines(vaccine_id)` | `CASCADE` | Vaccine linked to each scheduled dose |
| `hospital_vaccine_stock` | `fk_stock_hospital` | `hospital_id` | `hospitals(hospital_id)` | `CASCADE` | Facility owning the stock |
| `hospital_vaccine_stock` | `fk_stock_vaccine` | `vaccine_id` | `vaccines(vaccine_id)` | `CASCADE` | Stocked vaccine |
| `hospital_vaccine_stock` | `fk_stock_user` | `updated_by` | `users(user_id)` | `SET NULL` | Staff/admin who last updated stock |
| `appointments` | `fk_ap_cv` | `child_vaccination_id` | `child_vaccinations(child_vaccination_id)` | `CASCADE` | Specific scheduled dose being booked |
| `appointments` | `fk_ap_parent` | `parent_id` | `parents(parent_id)` | `CASCADE` | Booking parent |
| `appointments` | `fk_ap_child` | `child_id` | `children(child_id)` | `CASCADE` | Child receiving the dose |
| `appointments` | `fk_ap_hospital` | `hospital_id` | `hospitals(hospital_id)` | `CASCADE` | Selected vaccination center |
| `appointments` | `fk_ap_vaccine` | `vaccine_id` | `vaccines(vaccine_id)` | `CASCADE` | Requested vaccine |
| `appointments` | `fk_ap_admin` | `reviewed_by` | `admins(admin_id)` | `SET NULL` | Admin who approved/rejected request |
| `appointment_status_history` | `fk_hist_ap` | `appointment_id` | `appointments(appointment_id)` | `CASCADE` | Appointment being audited |
| `appointment_status_history` | `fk_hist_user` | `changed_by` | `users(user_id)` | `SET NULL` | Actor who triggered transition |
| `vaccination_records` | `fk_rec_ap` | `appointment_id` | `appointments(appointment_id)` | `CASCADE` | Source appointment |
| `vaccination_records` | `fk_rec_cv` | `child_vaccination_id` | `child_vaccinations(child_vaccination_id)` | `CASCADE` | Completed schedule slot |
| `vaccination_records` | `fk_rec_child` | `child_id` | `children(child_id)` | `CASCADE` | Vaccinated child |
| `vaccination_records` | `fk_rec_vaccine` | `vaccine_id` | `vaccines(vaccine_id)` | `CASCADE` | Administered vaccine |
| `vaccination_records` | `fk_rec_hospital` | `hospital_id` | `hospitals(hospital_id)` | `CASCADE` | Administering hospital |
| `notifications` | `fk_notif_user` | `user_id` | `users(user_id)` | `CASCADE` | Recipient account |
| `notifications` | `fk_notif_ap` | `related_appointment_id` | `appointments(appointment_id)` | `SET NULL` | Optional linked appointment |
| `activity_log` | `fk_log_user` | `user_id` | `users(user_id)` | `SET NULL` | Acting user for system audit |

---

## 3. Summary of Database Automation Objects

- **Trigger (`1`):** `trg_children_after_insert` → calls `sp_generate_child_schedule(NEW.child_id)` to populate `child_vaccinations`.
- **Stored Procedures (`6`):** `sp_generate_child_schedule`, `sp_book_appointment`, `sp_review_appointment`, `sp_complete_appointment`, `sp_update_vaccine_status`, `sp_generate_due_reminders`.
- **Views (`6`):** `v_all_children`, `v_booking_details`, `v_hospital_vaccine_status`, `v_upcoming_vaccinations`, `v_vaccination_report`, `v_vaccine_availability`.
- **Scheduled Event (`1`):** `evt_daily_maintenance` (marks overdue doses `missed` and calls `sp_generate_due_reminders(7)`).
