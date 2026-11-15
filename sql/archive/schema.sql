-- =====================================================================
-- Vaccination Management System - Parent & Hospital panels schema
-- Database: vaccination_db
-- ---------------------------------------------------------------------
-- OURS (we own these):     parents, children, hospital_users, vaccine_schedule
-- ADMIN-OWNED (SHARED):    hospitals, vaccines, bookings
--   !! The three shared tables below are PLACEHOLDERS. Once the admin team
--   !! shares the real schema, DROP these definitions and adapt the queries
--   !! in /parent and /hospital (all SQL on shared tables is marked
--   !! "SHARED TABLE" in comments so it is easy to find).
-- =====================================================================
CREATE DATABASE IF NOT EXISTS vaccination_db CHARACTER SET utf8mb4;
USE vaccination_db;

-- ---------- OURS ----------
CREATE TABLE IF NOT EXISTS parents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  phone VARCHAR(20) NOT NULL,
  address VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS children (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parent_id INT NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  dob DATE NOT NULL,
  gender ENUM('Male','Female','Other') NOT NULL,
  blood_group VARCHAR(5) DEFAULT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE
);

-- Age-based lookup: which vaccine is due how many days after birth.
CREATE TABLE IF NOT EXISTS vaccine_schedule (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vaccine_name VARCHAR(100) NOT NULL,
  due_after_days INT NOT NULL,
  description VARCHAR(255) DEFAULT NULL
);

-- ---------- SHARED / ADMIN-OWNED (PLACEHOLDERS) ----------
CREATE TABLE IF NOT EXISTS hospitals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  address VARCHAR(255) NOT NULL,
  location VARCHAR(100) NOT NULL,
  phone VARCHAR(20) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS vaccines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  available TINYINT(1) NOT NULL DEFAULT 1
);

-- status lifecycle: Pending -> Approved | Rejected -> Vaccinated | Not Vaccinated
CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  child_id INT NOT NULL,
  vaccine_id INT NOT NULL,
  hospital_id INT NOT NULL,
  booking_date DATE NOT NULL,
  status ENUM('Pending','Approved','Rejected','Vaccinated','Not Vaccinated') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------- OURS (references shared hospitals.id) ----------
-- Hospital login credentials live here so we never alter the admin table.
CREATE TABLE IF NOT EXISTS hospital_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------- SEED DATA (demo only) ----------
INSERT INTO vaccine_schedule (vaccine_name, due_after_days, description) VALUES
 ('BCG', 0, 'Tuberculosis - at birth'),
 ('Hepatitis B (Birth dose)', 0, 'At birth'),
 ('OPV 0', 0, 'Oral polio - at birth'),
 ('Pentavalent 1', 42, '6 weeks'),
 ('OPV 1', 42, '6 weeks'),
 ('Pentavalent 2', 70, '10 weeks'),
 ('OPV 2', 70, '10 weeks'),
 ('Pentavalent 3', 98, '14 weeks'),
 ('OPV 3', 98, '14 weeks'),
 ('Measles / MR 1', 270, '9 months'),
 ('MMR', 450, '15 months'),
 ('DPT Booster 1', 540, '16-24 months'),
 ('OPV Booster', 540, '16-24 months');

-- Placeholder shared data so the panels can be demoed before admin integration.
INSERT INTO vaccines (name, available)
  SELECT vaccine_name, 1 FROM vaccine_schedule;
INSERT INTO hospitals (name, address, location, phone) VALUES
 ('City General Hospital', '12 Main Boulevard', 'Lahore', '042-1111111'),
 ('Sunrise Children Clinic', '45 Garden Road', 'Karachi', '021-2222222');
