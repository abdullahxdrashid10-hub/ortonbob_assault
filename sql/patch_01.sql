-- =====================================================================
-- Patch 01: Add booking time/completion and schedule foreign link
-- Database: vaccination_db
-- Import once. Seed data is not re-run safe.
-- =====================================================================
USE vaccination_db;

-- ---------------------------------------------------------------------
-- SHARED TABLE: bookings
-- Adding booking_time (for scheduled slot time) and vaccinated_on
-- (timestamp when vaccine was administered by hospital).
-- Note: These column definitions are placeholders and must be verified
-- against the teammate admin schema once provided.
-- ---------------------------------------------------------------------
ALTER TABLE bookings 
  ADD COLUMN IF NOT EXISTS booking_time TIME NULL AFTER booking_date,
  ADD COLUMN IF NOT EXISTS vaccinated_on DATETIME NULL AFTER status;

-- ---------------------------------------------------------------------
-- OUR TABLE: vaccine_schedule
-- Link schedule row to shared vaccines table by vaccine_id.
-- Note: Mapping assumes vaccines.name matches vaccine_schedule.vaccine_name.
-- Must be re-verified against real admin vaccines table.
-- ---------------------------------------------------------------------
ALTER TABLE vaccine_schedule 
  ADD COLUMN IF NOT EXISTS vaccine_id INT NULL AFTER vaccine_name;

-- Update foreign link based on exact name match
UPDATE vaccine_schedule s
JOIN vaccines v ON v.name = s.vaccine_name
SET s.vaccine_id = v.id
WHERE s.vaccine_id IS NULL;
