-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 06, 2026 at 02:31 PM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `vaccination_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_book_appointment` (IN `p_child_vaccination_id` INT UNSIGNED, IN `p_hospital_id` INT UNSIGNED, IN `p_date` DATE, IN `p_time` TIME, IN `p_note` VARCHAR(255), OUT `p_appointment_id` INT UNSIGNED)   BEGIN
  DECLARE v_child INT UNSIGNED; DECLARE v_parent INT UNSIGNED;
  DECLARE v_vaccine INT UNSIGNED; DECLARE v_status VARCHAR(20);
  DECLARE v_booked INT; DECLARE v_capacity INT;
  DECLARE v_parent_user INT UNSIGNED;

  SELECT cv.child_id, cv.vaccine_id, cv.status, c.parent_id, p.user_id
    INTO v_child, v_vaccine, v_status, v_parent, v_parent_user
  FROM child_vaccinations cv
  JOIN children c ON c.child_id = cv.child_id
  JOIN parents  p ON p.parent_id = c.parent_id
  WHERE cv.child_vaccination_id = p_child_vaccination_id;

  IF v_status NOT IN ('upcoming','missed') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This vaccine dose is already requested, booked or completed';
  END IF;
  IF p_date < CURDATE() THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Appointment date cannot be in the past';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM hospital_vaccine_stock
                 WHERE hospital_id = p_hospital_id AND vaccine_id = v_vaccine AND status='available') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Vaccine is not available at the selected hospital';
  END IF;

  SELECT daily_capacity INTO v_capacity FROM hospitals WHERE hospital_id = p_hospital_id AND status='active';
  SELECT COUNT(*) INTO v_booked FROM appointments
   WHERE hospital_id = p_hospital_id AND appointment_date = p_date AND status IN ('pending','approved');
  IF v_booked >= v_capacity THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Hospital is fully booked on this date';
  END IF;

  INSERT INTO appointments (child_vaccination_id, parent_id, child_id, hospital_id, vaccine_id,
                            appointment_date, appointment_time, parent_note)
  VALUES (p_child_vaccination_id, v_parent, v_child, p_hospital_id, v_vaccine, p_date, p_time, p_note);
  SET p_appointment_id = LAST_INSERT_ID();

  UPDATE child_vaccinations SET status='requested' WHERE child_vaccination_id = p_child_vaccination_id;

  INSERT INTO appointment_status_history (appointment_id, old_status, new_status, changed_by, remarks)
  VALUES (p_appointment_id, NULL, 'pending', v_parent_user, 'Request created by parent');

  -- notify every admin
  INSERT INTO notifications (user_id, title, message, type, related_appointment_id)
  SELECT user_id, 'New appointment request',
         CONCAT('Appointment request #', p_appointment_id, ' is waiting for approval.'),
         'request', p_appointment_id
  FROM users WHERE role='admin' AND status='active';
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_complete_appointment` (IN `p_appointment_id` INT UNSIGNED, IN `p_hospital_user_id` INT UNSIGNED, IN `p_batch_number` VARCHAR(60), IN `p_administered_by` VARCHAR(120), IN `p_remarks` VARCHAR(255))   BEGIN
  DECLARE v_cv INT UNSIGNED; DECLARE v_child INT UNSIGNED; DECLARE v_vaccine INT UNSIGNED;
  DECLARE v_hospital INT UNSIGNED; DECLARE v_dose TINYINT UNSIGNED; DECLARE v_parent_user INT UNSIGNED;
  DECLARE v_status VARCHAR(20);

  SELECT a.status, a.child_vaccination_id, a.child_id, a.vaccine_id, a.hospital_id, cv.dose_number, p.user_id
    INTO v_status, v_cv, v_child, v_vaccine, v_hospital, v_dose, v_parent_user
  FROM appointments a
  JOIN child_vaccinations cv ON cv.child_vaccination_id = a.child_vaccination_id
  JOIN parents p ON p.parent_id = a.parent_id
  WHERE a.appointment_id = p_appointment_id;

  IF v_status <> 'approved' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only approved appointments can be completed';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM hospitals WHERE hospital_id = v_hospital AND user_id = p_hospital_user_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This appointment belongs to another hospital';
  END IF;

  UPDATE appointments SET status='completed', completed_at=NOW() WHERE appointment_id = p_appointment_id;
  UPDATE child_vaccinations SET status='completed', completed_date=CURDATE() WHERE child_vaccination_id = v_cv;

  INSERT INTO vaccination_records (appointment_id, child_vaccination_id, child_id, vaccine_id, hospital_id,
                                   dose_number, vaccination_date, vaccination_time,
                                   batch_number, administered_by, remarks)
  VALUES (p_appointment_id, v_cv, v_child, v_vaccine, v_hospital,
          v_dose, CURDATE(), CURTIME(), p_batch_number, p_administered_by, p_remarks);

  -- reduce stock; mark unavailable when it reaches zero
  UPDATE hospital_vaccine_stock
     SET quantity = IF(quantity > 0, quantity - 1, 0),
         status   = IF(quantity <= 0, 'unavailable', status)   -- quantity already reduced above
   WHERE hospital_id = v_hospital AND vaccine_id = v_vaccine;

  INSERT INTO appointment_status_history (appointment_id, old_status, new_status, changed_by, remarks)
  VALUES (p_appointment_id, 'approved', 'completed', p_hospital_user_id, p_remarks);

  INSERT INTO notifications (user_id, title, message, type, related_appointment_id)
  VALUES (v_parent_user, 'Vaccination completed',
          CONCAT('Vaccination for appointment #', p_appointment_id, ' is completed. Report is available.'),
          'completion', p_appointment_id);

  INSERT INTO notifications (user_id, title, message, type, related_appointment_id)
  SELECT user_id, 'Vaccination completed',
         CONCAT('Appointment #', p_appointment_id, ' was completed by the hospital.'),
         'completion', p_appointment_id
  FROM users WHERE role='admin' AND status='active';
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_child_schedule` (IN `p_child_id` INT UNSIGNED)   BEGIN
  INSERT IGNORE INTO child_vaccinations (child_id, vaccine_id, dose_number, due_date, status)
  SELECT c.child_id, vs.vaccine_id, vs.dose_number,
         DATE_ADD(c.date_of_birth, INTERVAL vs.age_in_days DAY),
         CASE WHEN DATE_ADD(c.date_of_birth, INTERVAL vs.age_in_days DAY) < CURDATE()
              THEN 'missed' ELSE 'upcoming' END
  FROM children c
  JOIN vaccine_schedule vs
  JOIN vaccines v ON v.vaccine_id = vs.vaccine_id AND v.is_active = 1
  WHERE c.child_id = p_child_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_due_reminders` (IN `p_days_ahead` INT)   BEGIN
  INSERT INTO notifications (user_id, title, message, type)
  SELECT p.user_id, 'Upcoming vaccination',
         CONCAT(c.full_name, ' is due for ', v.vaccine_name, ' (dose ', cv.dose_number,
                ') on ', DATE_FORMAT(cv.due_date, '%d-%b-%Y'), '.'),
         'reminder'
  FROM child_vaccinations cv
  JOIN children c ON c.child_id = cv.child_id
  JOIN parents  p ON p.parent_id = c.parent_id
  JOIN vaccines v ON v.vaccine_id = cv.vaccine_id
  WHERE cv.status = 'upcoming'
    AND cv.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL p_days_ahead DAY)
    AND NOT EXISTS (SELECT 1 FROM notifications n
                    WHERE n.user_id = p.user_id AND n.type = 'reminder'
                      AND n.message LIKE CONCAT(c.full_name, ' is due for ', v.vaccine_name, ' (dose ', cv.dose_number, ')%')
                      AND DATE(n.created_at) = CURDATE());
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_review_appointment` (IN `p_appointment_id` INT UNSIGNED, IN `p_admin_id` INT UNSIGNED, IN `p_decision` ENUM('approved','rejected'), IN `p_remarks` VARCHAR(255))   BEGIN
  DECLARE v_cv INT UNSIGNED; DECLARE v_parent_user INT UNSIGNED;
  DECLARE v_hosp_user INT UNSIGNED; DECLARE v_admin_user INT UNSIGNED;

  IF (SELECT status FROM appointments WHERE appointment_id = p_appointment_id) <> 'pending' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only pending requests can be reviewed';
  END IF;

  SELECT a.child_vaccination_id, p.user_id, h.user_id
    INTO v_cv, v_parent_user, v_hosp_user
  FROM appointments a
  JOIN parents p ON p.parent_id = a.parent_id
  JOIN hospitals h ON h.hospital_id = a.hospital_id
  WHERE a.appointment_id = p_appointment_id;

  SELECT user_id INTO v_admin_user FROM admins WHERE admin_id = p_admin_id;

  UPDATE appointments
     SET status = p_decision, admin_remarks = p_remarks,
         reviewed_by = p_admin_id, reviewed_at = NOW()
   WHERE appointment_id = p_appointment_id;

  UPDATE child_vaccinations
     SET status = IF(p_decision='approved','booked','upcoming')
   WHERE child_vaccination_id = v_cv;

  INSERT INTO appointment_status_history (appointment_id, old_status, new_status, changed_by, remarks)
  VALUES (p_appointment_id, 'pending', p_decision, v_admin_user, p_remarks);

  INSERT INTO notifications (user_id, title, message, type, related_appointment_id)
  VALUES (v_parent_user,
          IF(p_decision='approved','Appointment approved','Appointment rejected'),
          CONCAT('Your appointment #', p_appointment_id, ' was ', p_decision,
                 IF(p_remarks IS NULL,'', CONCAT('. Remarks: ', p_remarks))),
          IF(p_decision='approved','approval','rejection'), p_appointment_id);

  IF p_decision = 'approved' AND v_hosp_user IS NOT NULL THEN
    INSERT INTO notifications (user_id, title, message, type, related_appointment_id)
    VALUES (v_hosp_user, 'New appointment received',
            CONCAT('Appointment #', p_appointment_id, ' has been approved for your hospital.'),
            'approval', p_appointment_id);
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_vaccine_status` (IN `p_hospital_id` INT UNSIGNED, IN `p_vaccine_id` INT UNSIGNED, IN `p_status` ENUM('available','unavailable'), IN `p_quantity` INT UNSIGNED, IN `p_user_id` INT UNSIGNED)   BEGIN
  INSERT INTO hospital_vaccine_stock (hospital_id, vaccine_id, status, quantity, updated_by)
  VALUES (p_hospital_id, p_vaccine_id, p_status, p_quantity, p_user_id)
  ON DUPLICATE KEY UPDATE status = VALUES(status), quantity = VALUES(quantity), updated_by = VALUES(updated_by);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `log_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `user_id`, `full_name`, `phone`, `created_at`) VALUES
(1, 1, 'System Administrator', '021-11122233', '2026-11-06 13:31:27');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(10) UNSIGNED NOT NULL,
  `child_vaccination_id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED NOT NULL,
  `child_id` int(10) UNSIGNED NOT NULL,
  `hospital_id` int(10) UNSIGNED NOT NULL,
  `vaccine_id` int(10) UNSIGNED NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('pending','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
  `parent_note` varchar(255) DEFAULT NULL,
  `admin_remarks` varchar(255) DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `child_vaccination_id`, `parent_id`, `child_id`, `hospital_id`, `vaccine_id`, `appointment_date`, `appointment_time`, `status`, `parent_note`, `admin_remarks`, `reviewed_by`, `reviewed_at`, `completed_at`, `created_at`, `updated_at`) VALUES
(1, 6, 1, 1, 1, 3, '2026-11-07', '10:00:00', 'approved', 'Morning slot please', 'Approved', 1, '2026-11-06 18:31:28', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(2, 74, 2, 3, 2, 5, '2026-11-08', '11:30:00', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(3, 40, 1, 2, 1, 4, '2026-11-09', '09:30:00', 'rejected', NULL, 'Slot not available, choose another date', 1, '2026-11-06 18:31:28', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_status_history`
--

CREATE TABLE `appointment_status_history` (
  `history_id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(10) UNSIGNED NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) NOT NULL,
  `changed_by` int(10) UNSIGNED DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_status_history`
--

INSERT INTO `appointment_status_history` (`history_id`, `appointment_id`, `old_status`, `new_status`, `changed_by`, `remarks`, `changed_at`) VALUES
(1, 1, NULL, 'pending', 2, 'Request created by parent', '2026-11-06 13:31:28'),
(2, 1, 'pending', 'approved', 1, 'Approved', '2026-11-06 13:31:28'),
(3, 2, NULL, 'pending', 3, 'Request created by parent', '2026-11-06 13:31:28'),
(4, 3, NULL, 'pending', 2, 'Request created by parent', '2026-11-06 13:31:28'),
(5, 3, 'pending', 'rejected', 1, 'Slot not available, choose another date', '2026-11-06 13:31:28');

-- --------------------------------------------------------

--
-- Table structure for table `children`
--

CREATE TABLE `children` (
  `child_id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-','unknown') NOT NULL DEFAULT 'unknown',
  `birth_weight_kg` decimal(4,2) DEFAULT NULL,
  `birth_certificate_no` varchar(60) DEFAULT NULL,
  `allergies` varchar(255) DEFAULT NULL,
  `medical_notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `children`
--

INSERT INTO `children` (`child_id`, `parent_id`, `full_name`, `date_of_birth`, `gender`, `blood_group`, `birth_weight_kg`, `birth_certificate_no`, `allergies`, `medical_notes`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Ayaan Khan', '2026-09-05', 'male', 'B+', '3.10', NULL, NULL, NULL, 1, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(2, 1, 'Zara Khan', '2025-12-01', 'female', 'B+', '2.90', NULL, NULL, NULL, 1, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(3, 2, 'Hamza Ali', '2026-10-01', 'male', 'O+', '3.40', NULL, 'None known', NULL, 1, '2026-11-06 13:31:28', '2026-11-06 13:31:28');

--
-- Triggers `children`
--
DELIMITER $$
CREATE TRIGGER `trg_children_after_insert` AFTER INSERT ON `children` FOR EACH ROW BEGIN
  CALL sp_generate_child_schedule(NEW.child_id);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `child_vaccinations`
--

CREATE TABLE `child_vaccinations` (
  `child_vaccination_id` int(10) UNSIGNED NOT NULL,
  `child_id` int(10) UNSIGNED NOT NULL,
  `vaccine_id` int(10) UNSIGNED NOT NULL,
  `dose_number` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `due_date` date NOT NULL,
  `status` enum('upcoming','requested','booked','completed','missed') NOT NULL DEFAULT 'upcoming',
  `completed_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `child_vaccinations`
--

INSERT INTO `child_vaccinations` (`child_vaccination_id`, `child_id`, `vaccine_id`, `dose_number`, `due_date`, `status`, `completed_date`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, '2026-09-05', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(2, 1, 2, 1, '2026-09-05', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(3, 1, 2, 2, '2026-10-17', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(4, 1, 2, 3, '2026-11-14', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(5, 1, 2, 4, '2026-12-12', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(6, 1, 3, 1, '2026-10-17', 'booked', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(7, 1, 3, 2, '2026-11-14', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(8, 1, 3, 3, '2026-12-12', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(9, 1, 4, 1, '2026-10-17', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(10, 1, 4, 2, '2026-11-14', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(11, 1, 4, 3, '2026-12-12', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(12, 1, 5, 1, '2026-10-17', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(13, 1, 5, 2, '2026-11-14', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(14, 1, 6, 1, '2026-12-12', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(15, 1, 7, 1, '2027-06-02', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(16, 1, 7, 2, '2027-11-29', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(17, 1, 8, 1, '2027-06-02', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(32, 2, 1, 1, '2025-12-01', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(33, 2, 2, 1, '2025-12-01', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(34, 2, 2, 2, '2026-01-12', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(35, 2, 2, 3, '2026-02-09', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(36, 2, 2, 4, '2026-03-09', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(37, 2, 3, 1, '2026-01-12', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(38, 2, 3, 2, '2026-02-09', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(39, 2, 3, 3, '2026-03-09', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(40, 2, 4, 1, '2026-01-12', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(41, 2, 4, 2, '2026-02-09', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(42, 2, 4, 3, '2026-03-09', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(43, 2, 5, 1, '2026-01-12', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(44, 2, 5, 2, '2026-02-09', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(45, 2, 6, 1, '2026-03-09', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(46, 2, 7, 1, '2026-08-28', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(47, 2, 7, 2, '2027-02-24', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(48, 2, 8, 1, '2026-08-28', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(63, 3, 1, 1, '2026-10-01', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(64, 3, 2, 1, '2026-10-01', 'missed', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(65, 3, 2, 2, '2026-11-12', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(66, 3, 2, 3, '2026-12-10', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(67, 3, 2, 4, '2027-01-07', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(68, 3, 3, 1, '2026-11-12', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(69, 3, 3, 2, '2026-12-10', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(70, 3, 3, 3, '2027-01-07', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(71, 3, 4, 1, '2026-11-12', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(72, 3, 4, 2, '2026-12-10', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(73, 3, 4, 3, '2027-01-07', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(74, 3, 5, 1, '2026-11-12', 'requested', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(75, 3, 5, 2, '2026-12-10', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(76, 3, 6, 1, '2027-01-07', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(77, 3, 7, 1, '2027-06-28', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(78, 3, 7, 2, '2027-12-25', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28'),
(79, 3, 8, 1, '2027-06-28', 'upcoming', NULL, '2026-11-06 13:31:28', '2026-11-06 13:31:28');

-- --------------------------------------------------------

--
-- Table structure for table `hospitals`
--

CREATE TABLE `hospitals` (
  `hospital_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `hospital_name` varchar(150) NOT NULL,
  `registration_no` varchar(60) DEFAULT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(80) NOT NULL,
  `area` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `opening_time` time NOT NULL DEFAULT '09:00:00',
  `closing_time` time NOT NULL DEFAULT '17:00:00',
  `daily_capacity` smallint(5) UNSIGNED NOT NULL DEFAULT 50,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `added_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hospitals`
--

INSERT INTO `hospitals` (`hospital_id`, `user_id`, `hospital_name`, `registration_no`, `address`, `city`, `area`, `latitude`, `longitude`, `phone`, `email`, `opening_time`, `closing_time`, `daily_capacity`, `status`, `added_by`, `created_at`, `updated_at`) VALUES
(1, 4, 'City Children Hospital', 'REG-KHI-001', 'Main Shahrah-e-Faisal', 'Karachi', 'Faisal Cantt', '24.8607343', '67.0011364', '021-34567890', 'contact@cityhospital.pk', '08:00:00', '16:00:00', 60, 'active', 1, '2026-11-06 13:31:27', '2026-11-06 13:31:27'),
(2, 5, 'LifeCare Medical Centre', 'REG-KHI-002', 'Tariq Road, PECHS', 'Karachi', 'PECHS', '24.8700000', '67.0600000', '021-34561122', 'info@lifecarekhi.pk', '09:00:00', '18:00:00', 40, 'active', 1, '2026-11-06 13:31:27', '2026-11-06 13:31:27');

-- --------------------------------------------------------

--
-- Table structure for table `hospital_vaccine_stock`
--

CREATE TABLE `hospital_vaccine_stock` (
  `stock_id` int(10) UNSIGNED NOT NULL,
  `hospital_id` int(10) UNSIGNED NOT NULL,
  `vaccine_id` int(10) UNSIGNED NOT NULL,
  `status` enum('available','unavailable') NOT NULL DEFAULT 'unavailable',
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hospital_vaccine_stock`
--

INSERT INTO `hospital_vaccine_stock` (`stock_id`, `hospital_id`, `vaccine_id`, `status`, `quantity`, `updated_by`, `updated_at`) VALUES
(1, 1, 1, 'available', 100, 4, '2026-11-06 13:31:28'),
(2, 1, 2, 'available', 200, 4, '2026-11-06 13:31:28'),
(3, 1, 3, 'available', 80, 4, '2026-11-06 13:31:28'),
(4, 1, 4, 'available', 60, 4, '2026-11-06 13:31:28'),
(5, 1, 5, 'unavailable', 0, 4, '2026-11-06 13:31:28'),
(6, 1, 6, 'available', 50, 4, '2026-11-06 13:31:28'),
(7, 1, 7, 'available', 70, 4, '2026-11-06 13:31:28'),
(8, 1, 8, 'available', 40, 4, '2026-11-06 13:31:28'),
(9, 2, 1, 'available', 40, 5, '2026-11-06 13:31:28'),
(10, 2, 2, 'available', 90, 5, '2026-11-06 13:31:28'),
(11, 2, 3, 'available', 30, 5, '2026-11-06 13:31:28'),
(12, 2, 4, 'unavailable', 0, 5, '2026-11-06 13:31:28'),
(13, 2, 5, 'available', 25, 5, '2026-11-06 13:31:28'),
(14, 2, 6, 'unavailable', 0, 5, '2026-11-06 13:31:28'),
(15, 2, 7, 'available', 35, 5, '2026-11-06 13:31:28'),
(16, 2, 8, 'available', 20, 5, '2026-11-06 13:31:28');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` varchar(500) NOT NULL,
  `type` enum('reminder','request','approval','rejection','completion','system') NOT NULL DEFAULT 'system',
  `related_appointment_id` int(10) UNSIGNED DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `related_appointment_id`, `is_read`, `created_at`) VALUES
(1, 1, 'New appointment request', 'Appointment request #1 is waiting for approval.', 'request', 1, 0, '2026-11-06 13:31:28'),
(2, 2, 'Appointment approved', 'Your appointment #1 was approved. Remarks: Approved', 'approval', 1, 0, '2026-11-06 13:31:28'),
(3, 4, 'New appointment received', 'Appointment #1 has been approved for your hospital.', 'approval', 1, 0, '2026-11-06 13:31:28'),
(4, 1, 'New appointment request', 'Appointment request #2 is waiting for approval.', 'request', 2, 0, '2026-11-06 13:31:28'),
(5, 1, 'New appointment request', 'Appointment request #3 is waiting for approval.', 'request', 3, 0, '2026-11-06 13:31:28'),
(6, 2, 'Appointment rejected', 'Your appointment #3 was rejected. Remarks: Slot not available, choose another date', 'rejection', 3, 0, '2026-11-06 13:31:28');

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--

CREATE TABLE `parents` (
  `parent_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `cnic` varchar(20) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parents`
--

INSERT INTO `parents` (`parent_id`, `user_id`, `full_name`, `cnic`, `phone`, `address`, `city`, `created_at`, `updated_at`) VALUES
(1, 2, 'Sara Khan', '42101-1234567-8', '0300-1234567', 'Block 5, Gulshan-e-Iqbal', 'Karachi', '2026-11-06 13:31:27', '2026-11-06 13:31:27'),
(2, 3, 'Imran Ali', '42201-7654321-0', '0321-7654321', 'Phase 2, DHA', 'Karachi', '2026-11-06 13:31:27', '2026-11-06 13:31:27');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','parent','hospital') NOT NULL,
  `status` enum('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password_hash`, `role`, `status`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'admin@vaccinecare.pk', '$2y$10$REPLACE_WITH_REAL_BCRYPT_HASH_ADMIN', 'admin', 'active', NULL, '2026-11-06 13:31:27', '2026-11-06 13:31:27'),
(2, 'sara.khan@example.com', '$2y$10$REPLACE_WITH_REAL_BCRYPT_HASH_PARENT', 'parent', 'active', NULL, '2026-11-06 13:31:27', '2026-11-06 13:31:27'),
(3, 'imran.ali@example.com', '$2y$10$REPLACE_WITH_REAL_BCRYPT_HASH_PARENT', 'parent', 'active', NULL, '2026-11-06 13:31:27', '2026-11-06 13:31:27'),
(4, 'contact@cityhospital.pk', '$2y$10$REPLACE_WITH_REAL_BCRYPT_HASH_HOSP', 'hospital', 'active', NULL, '2026-11-06 13:31:27', '2026-11-06 13:31:27'),
(5, 'info@lifecarekhi.pk', '$2y$10$REPLACE_WITH_REAL_BCRYPT_HASH_HOSP', 'hospital', 'active', NULL, '2026-11-06 13:31:27', '2026-11-06 13:31:27');

-- --------------------------------------------------------

--
-- Table structure for table `vaccination_records`
--

CREATE TABLE `vaccination_records` (
  `record_id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(10) UNSIGNED NOT NULL,
  `child_vaccination_id` int(10) UNSIGNED NOT NULL,
  `child_id` int(10) UNSIGNED NOT NULL,
  `vaccine_id` int(10) UNSIGNED NOT NULL,
  `hospital_id` int(10) UNSIGNED NOT NULL,
  `dose_number` tinyint(3) UNSIGNED NOT NULL,
  `vaccination_date` date NOT NULL,
  `vaccination_time` time DEFAULT NULL,
  `batch_number` varchar(60) DEFAULT NULL,
  `administered_by` varchar(120) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vaccines`
--

CREATE TABLE `vaccines` (
  `vaccine_id` int(10) UNSIGNED NOT NULL,
  `vaccine_name` varchar(120) NOT NULL,
  `short_code` varchar(20) DEFAULT NULL,
  `disease_prevented` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `route` varchar(40) DEFAULT NULL,
  `total_doses` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `side_effects` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vaccines`
--

INSERT INTO `vaccines` (`vaccine_id`, `vaccine_name`, `short_code`, `disease_prevented`, `description`, `route`, `total_doses`, `side_effects`, `is_active`, `created_at`) VALUES
(1, 'BCG', 'BCG', 'Tuberculosis', 'Given at birth', 'Intradermal', 1, 'Small sore at injection site', 1, '2026-11-06 13:31:27'),
(2, 'OPV (Oral Polio Vaccine)', 'OPV', 'Poliomyelitis', 'Oral drops - birth, 6, 10, 14 weeks', 'Oral', 4, 'Rare', 1, '2026-11-06 13:31:27'),
(3, 'Pentavalent', 'PENTA', 'Diphtheria, Tetanus, Pertussis, Hep B, Hib', '5-in-1 vaccine', 'Intramuscular', 3, 'Fever, swelling', 1, '2026-11-06 13:31:27'),
(4, 'PCV10 (Pneumococcal)', 'PCV', 'Pneumonia, Meningitis', 'Pneumococcal conjugate vaccine', 'Intramuscular', 3, 'Fever, irritability', 1, '2026-11-06 13:31:27'),
(5, 'Rotavirus', 'ROTA', 'Severe diarrhoea', 'Oral vaccine', 'Oral', 2, 'Mild diarrhoea', 1, '2026-11-06 13:31:27'),
(6, 'IPV (Inactivated Polio)', 'IPV', 'Poliomyelitis', 'Injectable polio vaccine', 'Intramuscular', 1, 'Redness at site', 1, '2026-11-06 13:31:27'),
(7, 'Measles / MR', 'MR', 'Measles, Rubella', 'Two doses', 'Subcutaneous', 2, 'Mild fever, rash', 1, '2026-11-06 13:31:27'),
(8, 'Typhoid Conjugate (TCV)', 'TCV', 'Typhoid fever', 'Single dose', 'Intramuscular', 1, 'Mild fever', 1, '2026-11-06 13:31:27');

-- --------------------------------------------------------

--
-- Table structure for table `vaccine_schedule`
--

CREATE TABLE `vaccine_schedule` (
  `schedule_id` int(10) UNSIGNED NOT NULL,
  `vaccine_id` int(10) UNSIGNED NOT NULL,
  `dose_number` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `age_in_days` int(10) UNSIGNED NOT NULL,
  `age_label` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vaccine_schedule`
--

INSERT INTO `vaccine_schedule` (`schedule_id`, `vaccine_id`, `dose_number`, `age_in_days`, `age_label`) VALUES
(1, 1, 1, 0, 'Birth'),
(2, 2, 1, 0, 'Birth'),
(3, 2, 2, 42, '6 Weeks'),
(4, 2, 3, 70, '10 Weeks'),
(5, 2, 4, 98, '14 Weeks'),
(6, 3, 1, 42, '6 Weeks'),
(7, 3, 2, 70, '10 Weeks'),
(8, 3, 3, 98, '14 Weeks'),
(9, 4, 1, 42, '6 Weeks'),
(10, 4, 2, 70, '10 Weeks'),
(11, 4, 3, 98, '14 Weeks'),
(12, 5, 1, 42, '6 Weeks'),
(13, 5, 2, 70, '10 Weeks'),
(14, 6, 1, 98, '14 Weeks'),
(15, 7, 1, 270, '9 Months'),
(16, 7, 2, 450, '15 Months'),
(17, 8, 1, 270, '9 Months');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_all_children`
-- (See below for the actual view)
--
CREATE TABLE `v_all_children` (
`child_id` int(10) unsigned
,`child_name` varchar(120)
,`date_of_birth` date
,`age_in_months` bigint(21)
,`gender` enum('male','female','other')
,`blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-','unknown')
,`birth_weight_kg` decimal(4,2)
,`allergies` varchar(255)
,`parent_id` int(10) unsigned
,`parent_name` varchar(120)
,`parent_phone` varchar(20)
,`city` varchar(80)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_booking_details`
-- (See below for the actual view)
--
CREATE TABLE `v_booking_details` (
`appointment_id` int(10) unsigned
,`status` enum('pending','approved','rejected','completed','cancelled')
,`appointment_date` date
,`appointment_time` time
,`parent_note` varchar(255)
,`admin_remarks` varchar(255)
,`requested_at` timestamp
,`parent_id` int(10) unsigned
,`parent_name` varchar(120)
,`parent_phone` varchar(20)
,`child_id` int(10) unsigned
,`child_name` varchar(120)
,`vaccine_name` varchar(120)
,`dose_number` tinyint(3) unsigned
,`hospital_id` int(10) unsigned
,`hospital_name` varchar(150)
,`hospital_address` varchar(255)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_hospital_vaccine_status`
-- (See below for the actual view)
--
CREATE TABLE `v_hospital_vaccine_status` (
`hospital_id` int(10) unsigned
,`hospital_name` varchar(150)
,`vaccine_id` int(10) unsigned
,`vaccine_name` varchar(120)
,`status` varchar(11)
,`quantity` decimal(10,0)
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_upcoming_vaccinations`
-- (See below for the actual view)
--
CREATE TABLE `v_upcoming_vaccinations` (
`child_vaccination_id` int(10) unsigned
,`child_id` int(10) unsigned
,`child_name` varchar(120)
,`parent_id` int(10) unsigned
,`parent_name` varchar(120)
,`parent_phone` varchar(20)
,`vaccine_id` int(10) unsigned
,`vaccine_name` varchar(120)
,`dose_number` tinyint(3) unsigned
,`due_date` date
,`status` enum('upcoming','requested','booked','completed','missed')
,`days_remaining` int(7)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_vaccination_report`
-- (See below for the actual view)
--
CREATE TABLE `v_vaccination_report` (
`record_id` int(10) unsigned
,`vaccination_date` date
,`vaccination_time` time
,`child_id` int(10) unsigned
,`child_name` varchar(120)
,`vaccine_name` varchar(120)
,`dose_number` tinyint(3) unsigned
,`batch_number` varchar(60)
,`hospital_name` varchar(150)
,`administered_by` varchar(120)
,`remarks` varchar(255)
,`parent_id` int(10) unsigned
,`parent_name` varchar(120)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_vaccine_availability`
-- (See below for the actual view)
--
CREATE TABLE `v_vaccine_availability` (
`vaccine_id` int(10) unsigned
,`vaccine_name` varchar(120)
,`disease_prevented` varchar(200)
,`hospitals_available` decimal(23,0)
,`total_quantity` decimal(32,0)
,`overall_status` varchar(11)
);

-- --------------------------------------------------------

--
-- Structure for view `v_all_children`
--
DROP TABLE IF EXISTS `v_all_children`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_all_children`  AS SELECT `c`.`child_id` AS `child_id`, `c`.`full_name` AS `child_name`, `c`.`date_of_birth` AS `date_of_birth`, timestampdiff(MONTH,`c`.`date_of_birth`,curdate()) AS `age_in_months`, `c`.`gender` AS `gender`, `c`.`blood_group` AS `blood_group`, `c`.`birth_weight_kg` AS `birth_weight_kg`, `c`.`allergies` AS `allergies`, `p`.`parent_id` AS `parent_id`, `p`.`full_name` AS `parent_name`, `p`.`phone` AS `parent_phone`, `p`.`city` AS `city` FROM (`children` `c` join `parents` `p` on(`p`.`parent_id` = `c`.`parent_id`))  ;

-- --------------------------------------------------------

--
-- Structure for view `v_booking_details`
--
DROP TABLE IF EXISTS `v_booking_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_booking_details`  AS SELECT `a`.`appointment_id` AS `appointment_id`, `a`.`status` AS `status`, `a`.`appointment_date` AS `appointment_date`, `a`.`appointment_time` AS `appointment_time`, `a`.`parent_note` AS `parent_note`, `a`.`admin_remarks` AS `admin_remarks`, `a`.`created_at` AS `requested_at`, `p`.`parent_id` AS `parent_id`, `p`.`full_name` AS `parent_name`, `p`.`phone` AS `parent_phone`, `c`.`child_id` AS `child_id`, `c`.`full_name` AS `child_name`, `v`.`vaccine_name` AS `vaccine_name`, `cv`.`dose_number` AS `dose_number`, `h`.`hospital_id` AS `hospital_id`, `h`.`hospital_name` AS `hospital_name`, `h`.`address` AS `hospital_address` FROM (((((`appointments` `a` join `parents` `p` on(`p`.`parent_id` = `a`.`parent_id`)) join `children` `c` on(`c`.`child_id` = `a`.`child_id`)) join `vaccines` `v` on(`v`.`vaccine_id` = `a`.`vaccine_id`)) join `hospitals` `h` on(`h`.`hospital_id` = `a`.`hospital_id`)) join `child_vaccinations` `cv` on(`cv`.`child_vaccination_id` = `a`.`child_vaccination_id`))  ;

-- --------------------------------------------------------

--
-- Structure for view `v_hospital_vaccine_status`
--
DROP TABLE IF EXISTS `v_hospital_vaccine_status`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_hospital_vaccine_status`  AS SELECT `h`.`hospital_id` AS `hospital_id`, `h`.`hospital_name` AS `hospital_name`, `v`.`vaccine_id` AS `vaccine_id`, `v`.`vaccine_name` AS `vaccine_name`, coalesce(`s`.`status`,'unavailable') AS `status`, coalesce(`s`.`quantity`,0) AS `quantity`, `s`.`updated_at` AS `updated_at` FROM ((`hospitals` `h` join `vaccines` `v`) left join `hospital_vaccine_stock` `s` on(`s`.`hospital_id` = `h`.`hospital_id` and `s`.`vaccine_id` = `v`.`vaccine_id`)) WHERE `h`.`status` = 'active' AND `v`.`is_active` = 11  ;

-- --------------------------------------------------------

--
-- Structure for view `v_upcoming_vaccinations`
--
DROP TABLE IF EXISTS `v_upcoming_vaccinations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_upcoming_vaccinations`  AS SELECT `cv`.`child_vaccination_id` AS `child_vaccination_id`, `c`.`child_id` AS `child_id`, `c`.`full_name` AS `child_name`, `p`.`parent_id` AS `parent_id`, `p`.`full_name` AS `parent_name`, `p`.`phone` AS `parent_phone`, `v`.`vaccine_id` AS `vaccine_id`, `v`.`vaccine_name` AS `vaccine_name`, `cv`.`dose_number` AS `dose_number`, `cv`.`due_date` AS `due_date`, `cv`.`status` AS `status`, to_days(`cv`.`due_date`) - to_days(curdate()) AS `days_remaining` FROM (((`child_vaccinations` `cv` join `children` `c` on(`c`.`child_id` = `cv`.`child_id`)) join `parents` `p` on(`p`.`parent_id` = `c`.`parent_id`)) join `vaccines` `v` on(`v`.`vaccine_id` = `cv`.`vaccine_id`)) WHERE `cv`.`status` in ('upcoming','requested','booked') AND `cv`.`due_date` >= curdate()  ;

-- --------------------------------------------------------

--
-- Structure for view `v_vaccination_report`
--
DROP TABLE IF EXISTS `v_vaccination_report`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_vaccination_report`  AS SELECT `r`.`record_id` AS `record_id`, `r`.`vaccination_date` AS `vaccination_date`, `r`.`vaccination_time` AS `vaccination_time`, `c`.`child_id` AS `child_id`, `c`.`full_name` AS `child_name`, `v`.`vaccine_name` AS `vaccine_name`, `r`.`dose_number` AS `dose_number`, `r`.`batch_number` AS `batch_number`, `h`.`hospital_name` AS `hospital_name`, `r`.`administered_by` AS `administered_by`, `r`.`remarks` AS `remarks`, `p`.`parent_id` AS `parent_id`, `p`.`full_name` AS `parent_name` FROM ((((`vaccination_records` `r` join `children` `c` on(`c`.`child_id` = `r`.`child_id`)) join `parents` `p` on(`p`.`parent_id` = `c`.`parent_id`)) join `vaccines` `v` on(`v`.`vaccine_id` = `r`.`vaccine_id`)) join `hospitals` `h` on(`h`.`hospital_id` = `r`.`hospital_id`))  ;

-- --------------------------------------------------------

--
-- Structure for view `v_vaccine_availability`
--
DROP TABLE IF EXISTS `v_vaccine_availability`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_vaccine_availability`  AS SELECT `v`.`vaccine_id` AS `vaccine_id`, `v`.`vaccine_name` AS `vaccine_name`, `v`.`disease_prevented` AS `disease_prevented`, coalesce(sum(`s`.`status` = 'available'),0) AS `hospitals_available`, coalesce(sum(`s`.`quantity`),0) AS `total_quantity`, CASE WHEN coalesce(sum(`s`.`status` = 'available'),0) > 0 THEN 'available' ELSE 'unavailable' END AS `overall_status` FROM (`vaccines` `v` left join `hospital_vaccine_stock` `s` on(`s`.`vaccine_id` = `v`.`vaccine_id`)) WHERE `v`.`is_active` = 1 GROUP BY `v`.`vaccine_id`, `v`.`vaccine_name`, `v`.`disease_prevented``disease_prevented`  ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_log_user` (`user_id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `fk_ap_cv` (`child_vaccination_id`),
  ADD KEY `fk_ap_child` (`child_id`),
  ADD KEY `fk_ap_vaccine` (`vaccine_id`),
  ADD KEY `fk_ap_admin` (`reviewed_by`),
  ADD KEY `idx_ap_status` (`status`),
  ADD KEY `idx_ap_hospital_date` (`hospital_id`,`appointment_date`),
  ADD KEY `idx_ap_parent` (`parent_id`);

--
-- Indexes for table `appointment_status_history`
--
ALTER TABLE `appointment_status_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `fk_hist_ap` (`appointment_id`),
  ADD KEY `fk_hist_user` (`changed_by`);

--
-- Indexes for table `children`
--
ALTER TABLE `children`
  ADD PRIMARY KEY (`child_id`),
  ADD KEY `idx_child_parent` (`parent_id`),
  ADD KEY `idx_child_dob` (`date_of_birth`);

--
-- Indexes for table `child_vaccinations`
--
ALTER TABLE `child_vaccinations`
  ADD PRIMARY KEY (`child_vaccination_id`),
  ADD UNIQUE KEY `uq_child_vaccine_dose` (`child_id`,`vaccine_id`,`dose_number`),
  ADD KEY `fk_cv_vaccine` (`vaccine_id`),
  ADD KEY `idx_cv_due` (`due_date`,`status`);

--
-- Indexes for table `hospitals`
--
ALTER TABLE `hospitals`
  ADD PRIMARY KEY (`hospital_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `fk_hosp_admin` (`added_by`),
  ADD KEY `idx_hosp_city` (`city`),
  ADD KEY `idx_hosp_status` (`status`);

--
-- Indexes for table `hospital_vaccine_stock`
--
ALTER TABLE `hospital_vaccine_stock`
  ADD PRIMARY KEY (`stock_id`),
  ADD UNIQUE KEY `uq_hosp_vaccine` (`hospital_id`,`vaccine_id`),
  ADD KEY `fk_stock_vaccine` (`vaccine_id`),
  ADD KEY `fk_stock_user` (`updated_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `fk_notif_ap` (`related_appointment_id`),
  ADD KEY `idx_notif_user` (`user_id`,`is_read`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`parent_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `cnic` (`cnic`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`);

--
-- Indexes for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  ADD PRIMARY KEY (`record_id`),
  ADD UNIQUE KEY `appointment_id` (`appointment_id`),
  ADD KEY `fk_rec_cv` (`child_vaccination_id`),
  ADD KEY `fk_rec_vaccine` (`vaccine_id`),
  ADD KEY `fk_rec_hospital` (`hospital_id`),
  ADD KEY `idx_rec_date` (`vaccination_date`),
  ADD KEY `idx_rec_child` (`child_id`);

--
-- Indexes for table `vaccines`
--
ALTER TABLE `vaccines`
  ADD PRIMARY KEY (`vaccine_id`),
  ADD UNIQUE KEY `vaccine_name` (`vaccine_name`);

--
-- Indexes for table `vaccine_schedule`
--
ALTER TABLE `vaccine_schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD UNIQUE KEY `uq_vaccine_dose` (`vaccine_id`,`dose_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `log_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `appointment_status_history`
--
ALTER TABLE `appointment_status_history`
  MODIFY `history_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `children`
--
ALTER TABLE `children`
  MODIFY `child_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `child_vaccinations`
--
ALTER TABLE `child_vaccinations`
  MODIFY `child_vaccination_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `hospitals`
--
ALTER TABLE `hospitals`
  MODIFY `hospital_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `hospital_vaccine_stock`
--
ALTER TABLE `hospital_vaccine_stock`
  MODIFY `stock_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `parent_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  MODIFY `record_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vaccines`
--
ALTER TABLE `vaccines`
  MODIFY `vaccine_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `vaccine_schedule`
--
ALTER TABLE `vaccine_schedule`
  MODIFY `schedule_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `fk_admin_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_ap_admin` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ap_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ap_cv` FOREIGN KEY (`child_vaccination_id`) REFERENCES `child_vaccinations` (`child_vaccination_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ap_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`hospital_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ap_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`parent_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ap_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`vaccine_id`) ON DELETE CASCADE;

--
-- Constraints for table `appointment_status_history`
--
ALTER TABLE `appointment_status_history`
  ADD CONSTRAINT `fk_hist_ap` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hist_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `children`
--
ALTER TABLE `children`
  ADD CONSTRAINT `fk_child_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`parent_id`) ON DELETE CASCADE;

--
-- Constraints for table `child_vaccinations`
--
ALTER TABLE `child_vaccinations`
  ADD CONSTRAINT `fk_cv_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cv_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`vaccine_id`) ON DELETE CASCADE;

--
-- Constraints for table `hospitals`
--
ALTER TABLE `hospitals`
  ADD CONSTRAINT `fk_hosp_admin` FOREIGN KEY (`added_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hosp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `hospital_vaccine_stock`
--
ALTER TABLE `hospital_vaccine_stock`
  ADD CONSTRAINT `fk_stock_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`hospital_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stock_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_stock_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`vaccine_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_ap` FOREIGN KEY (`related_appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `parents`
--
ALTER TABLE `parents`
  ADD CONSTRAINT `fk_parent_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  ADD CONSTRAINT `fk_rec_ap` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rec_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rec_cv` FOREIGN KEY (`child_vaccination_id`) REFERENCES `child_vaccinations` (`child_vaccination_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rec_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`hospital_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rec_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`vaccine_id`) ON DELETE CASCADE;

--
-- Constraints for table `vaccine_schedule`
--
ALTER TABLE `vaccine_schedule`
  ADD CONSTRAINT `fk_sched_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`vaccine_id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `ev_daily_vaccination_jobs` ON SCHEDULE EVERY 1 DAY STARTS '2026-11-07 06:00:00' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
  UPDATE child_vaccinations SET status='missed'
   WHERE status='upcoming' AND due_date < CURDATE();
  CALL sp_generate_due_reminders(7);
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
