# Database Setup & Configuration Guide

This guide describes how to import and configure the team's database (`vaccination_db`) in XAMPP (Apache + MariaDB/MySQL).

---

## 1. Database Import via phpMyAdmin

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open your browser and navigate to phpMyAdmin:
   `http://localhost/phpmyadmin/`
3. In the left sidebar, click **New** to create a database:
   - Database name: `vaccination_db`
   - Collation: `utf8mb4_general_ci` (or `utf8mb4_unicode_ci`)
   - Click **Create**.
4. Select the newly created `vaccination_db` database in the left sidebar.
5. Click on the **Import** tab at the top.
6. Under **File to import**, click **Choose File** and select:
   `sql/official_schema.sql` (located in this project repository)
7. Ensure format is set to **SQL** and click **Import** (or **Go**) at the bottom.
8. Verify that all 11 tables, 6 views, 6 stored procedures, and the `trg_children_after_insert` trigger have imported successfully.

---

## 2. Event Scheduler Activation (Important)

The database includes a recurring daily event (`ev_daily_vaccination_jobs`) that automatically marks overdue upcoming doses as missed and schedules reminder notifications.

For MySQL/MariaDB to execute scheduled events automatically, the **Event Scheduler** must be enabled:

1. In phpMyAdmin, click the **SQL** tab.
2. Run the following command:
   ```sql
   SET GLOBAL event_scheduler = ON;
   ```
3. To make this setting permanent across XAMPP restarts, open `C:\xampp\mysql\bin\my.ini` in a text editor, find the `[mysqld]` section, and add:
   ```ini
   event_scheduler=ON
   ```
4. Verify the status anytime by running:
   ```sql
   SHOW VARIABLES LIKE 'event_scheduler';
   ```
   The result should show `Value: ON`.

---

## 3. Database Credentials Configuration

Verify project database settings in `config/config.php`:
- `DB_HOST = 'localhost'`
- `DB_NAME = 'vaccination_db'`
- `DB_USER = 'root'`
- `DB_PASS = ''`
