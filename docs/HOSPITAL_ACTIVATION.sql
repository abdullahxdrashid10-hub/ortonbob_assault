-- ============================================================================
-- HOSPITAL_ACTIVATION.sql
-- Manual Hospital Account Activation Script for phpMyAdmin / MariaDB Console
-- 
-- HOW TO USE IN phpMyAdmin:
-- 1. Open phpMyAdmin in your browser (typically http://localhost/phpmyadmin/).
-- 2. Select the `vaccination_db` database from the left-hand sidebar.
-- 3. Click the "SQL" tab in the top navigation bar.
-- 4. Replace 'hospital@example.pk' below with the actual email used during
--    the hospital's registration.
-- 5. Paste the two UPDATE statements into the query box and click "Go".
-- 6. Both the user login and hospital facility will immediately become 'active'.
-- ============================================================================

-- Statement 1: Activate the facility's login account in `users`
UPDATE `users`
SET `status` = 'active',
    `updated_at` = NOW()
WHERE `email` = 'hospital@example.pk'
  AND `role` = 'hospital';

-- Statement 2: Activate the healthcare facility profile in `hospitals`
UPDATE `hospitals`
SET `status` = 'active',
    `updated_at` = NOW()
WHERE `email` = 'hospital@example.pk';

-- Verification query (optional):
-- SELECT u.user_id, u.email, u.status AS user_status,
--        h.hospital_id, h.hospital_name, h.status AS hospital_status
-- FROM users u
-- JOIN hospitals h ON h.user_id = u.user_id
-- WHERE u.email = 'hospital@example.pk';
