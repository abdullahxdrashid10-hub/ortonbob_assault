<?php
/**
 * shared_queries.php - Central Query Repository for Real Vaccination Management Schema
 * 
 * Interacts directly with the team's official database:
 * - users, parents, children, child_vaccinations, vaccines, vaccine_schedule,
 *   hospitals, hospital_vaccine_stock, appointments, appointment_status_history,
 *   vaccination_records, notifications
 * 
 * Every function is documented with the exact tables it touches and enforces
 * access control, ownership verification, and active-state checks server-side.
 */

require_once __DIR__ . '/../config/config.php';

// =====================================================================
// SECTION 1: CHILDREN MANAGEMENT (PARENT)
// =====================================================================

/**
 * Touches: children
 * Fetch all active children belonging to the specified parent.
 * 
 * @param int $parentId
 * @return array
 */
function shared_get_parent_children(int $parentId): array
{
    // Touches: children
    $sql = "SELECT child_id, parent_id, full_name, date_of_birth, gender,
                   blood_group, birth_weight_kg, birth_certificate_no,
                   allergies, medical_notes, is_active, created_at
            FROM children
            WHERE parent_id = ? AND is_active = 1
            ORDER BY date_of_birth DESC, child_id DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute([$parentId]);
    return $stmt->fetchAll();
}

/**
 * Touches: children
 * Fetch a single active child, verifying that it belongs to the authenticated parent.
 * 
 * @param int $childId
 * @param int $parentId
 * @return array|null
 */
function shared_get_parent_child_by_id(int $childId, int $parentId): ?array
{
    // Touches: children
    $sql = "SELECT child_id, parent_id, full_name, date_of_birth, gender,
                   blood_group, birth_weight_kg, birth_certificate_no,
                   allergies, medical_notes, is_active, created_at
            FROM children
            WHERE child_id = ? AND parent_id = ? AND is_active = 1
            LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([$childId, $parentId]);
    $child = $stmt->fetch();
    return $child ?: null;
}

/**
 * Touches: children, child_vaccinations (via trigger trg_children_after_insert)
 * Add a new child record. Wrapped in try/catch for PDOException (Task F2).
 * 
 * @param int $parentId
 * @param string $fullName
 * @param string $dob (YYYY-MM-DD)
 * @param string $gender ('male' | 'female' | 'other')
 * @param string $bloodGroup
 * @param float|null $weightKg
 * @param string|null $certNo
 * @param string|null $allergies
 * @param string|null $medicalNotes
 * @return int Inserted child_id or 0 on error
 */
function shared_add_child(
    int $parentId,
    string $fullName,
    string $dob,
    string $gender,
    string $bloodGroup,
    ?float $weightKg = null,
    ?string $certNo = null,
    ?string $allergies = null,
    ?string $medicalNotes = null
): int {
    try {
        // Touches: children
        $sql = "INSERT INTO children (
                    parent_id, full_name, date_of_birth, gender, blood_group,
                    birth_weight_kg, birth_certificate_no, allergies, medical_notes, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

        $stmt = db()->prepare($sql);
        $stmt->execute([
            $parentId,
            $fullName,
            $dob,
            $gender,
            $bloodGroup,
            $weightKg,
            $certNo,
            $allergies,
            $medicalNotes
        ]);

        return (int)db()->lastInsertId();
    } catch (PDOException $e) {
        error_log('shared_add_child error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Touches: child_vaccinations
 * Count the generated vaccination schedule doses for a child.
 * 
 * @param int $childId
 * @return int
 */
function shared_count_child_vaccination_rows(int $childId): int
{
    // Touches: child_vaccinations
    $stmt = db()->prepare("SELECT COUNT(*) FROM child_vaccinations WHERE child_id = ?");
    $stmt->execute([$childId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Touches: children
 * Update an existing active child profile. Wrapped in try/catch for PDOException (Task F2).
 * Note: date_of_birth is permanent and not updated to prevent stale schedule records.
 * 
 * @param int $childId
 * @param int $parentId
 * @param string $fullName
 * @param string $gender
 * @param string $bloodGroup
 * @param float|null $weightKg
 * @param string|null $certNo
 * @param string|null $allergies
 * @param string|null $medicalNotes
 * @return bool
 */
function shared_update_child(
    int $childId,
    int $parentId,
    string $fullName,
    string $gender,
    string $bloodGroup,
    ?float $weightKg = null,
    ?string $certNo = null,
    ?string $allergies = null,
    ?string $medicalNotes = null
): bool {
    try {
        // Touches: children
        $sql = "UPDATE children
                SET full_name = ?, gender = ?, blood_group = ?,
                    birth_weight_kg = ?, birth_certificate_no = ?, allergies = ?, medical_notes = ?
                WHERE child_id = ? AND parent_id = ? AND is_active = 1";

        $stmt = db()->prepare($sql);
        $stmt->execute([
            $fullName,
            $gender,
            $bloodGroup,
            $weightKg,
            $certNo,
            $allergies,
            $medicalNotes,
            $childId,
            $parentId
        ]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('shared_update_child error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Touches: children, child_vaccinations
 * Soft-delete a child record by setting is_active = 0.
 * Refuses if the child has any child_vaccinations with status 'requested' or 'booked'.
 * 
 * @param int $childId
 * @param int $parentId
 * @return string|bool Returns 'active_appointment' if active doses exist, true on success, false on failure.
 */
function shared_soft_delete_child(int $childId, int $parentId): string|bool
{
    // Touches: child_vaccinations, children
    $chk = db()->prepare("
        SELECT 1 FROM child_vaccinations cv
        JOIN children c ON c.child_id = cv.child_id
        WHERE cv.child_id = ? AND c.parent_id = ? AND cv.status IN ('requested', 'booked')
        LIMIT 1
    ");
    $chk->execute([$childId, $parentId]);
    if ($chk->fetch()) {
        return 'active_appointment';
    }

    // Touches: children
    $sql = "UPDATE children SET is_active = 0 WHERE child_id = ? AND parent_id = ? AND is_active = 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([$childId, $parentId]);
    return $stmt->rowCount() > 0 ? true : false;
}

/**
 * Touches: child_vaccinations, vaccines, children
 * Retrieve the full immunization schedule for a specific active child of this parent.
 * 
 * @param int $childId
 * @param int $parentId
 * @return array
 */
function shared_get_child_vaccination_schedule(int $childId, int $parentId): array
{
    // Touches: child_vaccinations, vaccines, children
    $sql = "SELECT cv.child_vaccination_id, cv.child_id, cv.vaccine_id, cv.dose_number,
                   cv.due_date, cv.status, cv.completed_date,
                   v.vaccine_name, v.short_code, v.disease_prevented, v.route, v.description
            FROM child_vaccinations cv
            JOIN vaccines v ON v.vaccine_id = cv.vaccine_id
            JOIN children c ON c.child_id = cv.child_id
            WHERE cv.child_id = ? AND c.parent_id = ? AND c.is_active = 1
            ORDER BY cv.due_date ASC, cv.dose_number ASC, cv.child_vaccination_id ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute([$childId, $parentId]);
    return $stmt->fetchAll();
}

/**
 * Touches: child_vaccinations, children, vaccines
 * Retrieve a specific single dose record verifying parent ownership and child active state.
 * 
 * @param int $childVaccinationId
 * @param int $parentId
 * @return array|null
 */
function shared_get_child_vaccination_dose(int $childVaccinationId, int $parentId): ?array
{
    // Touches: child_vaccinations, children, vaccines
    $sql = "SELECT cv.child_vaccination_id, cv.child_id, cv.vaccine_id, cv.dose_number,
                   cv.due_date, cv.status, cv.completed_date,
                   c.full_name AS child_name, c.date_of_birth, c.gender,
                   v.vaccine_name, v.short_code, v.disease_prevented
            FROM child_vaccinations cv
            JOIN children c ON c.child_id = cv.child_id
            JOIN vaccines v ON v.vaccine_id = cv.vaccine_id
            WHERE cv.child_vaccination_id = ? AND c.parent_id = ? AND c.is_active = 1
            LIMIT 1";

    $stmt = db()->prepare($sql);
    $stmt->execute([$childVaccinationId, $parentId]);
    $res = $stmt->fetch();
    return $res ?: null;
}

/**
 * Touches: children, child_vaccinations
 * Summary KPI counts for the parent dashboard cards.
 * 
 * @param int $parentId
 * @return array
 */
function shared_get_parent_dashboard_counts(int $parentId): array
{
    $pdo = db();

    $stmtKids = $pdo->prepare("SELECT COUNT(*) FROM children WHERE parent_id = ? AND is_active = 1");
    $stmtKids->execute([$parentId]);
    $activeKids = (int)$stmtKids->fetchColumn();

    $stmtDue = $pdo->prepare("
        SELECT COUNT(*)
        FROM child_vaccinations cv
        JOIN children c ON c.child_id = cv.child_id
        WHERE c.parent_id = ? AND c.is_active = 1
          AND cv.status IN ('upcoming', 'missed')
          AND cv.due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmtDue->execute([$parentId]);
    $dueCount = (int)$stmtDue->fetchColumn();

    $stmtBooked = $pdo->prepare("
        SELECT COUNT(*)
        FROM child_vaccinations cv
        JOIN children c ON c.child_id = cv.child_id
        WHERE c.parent_id = ? AND c.is_active = 1
          AND cv.status IN ('requested', 'booked')
    ");
    $stmtBooked->execute([$parentId]);
    $bookedCount = (int)$stmtBooked->fetchColumn();

    $stmtCompleted = $pdo->prepare("
        SELECT COUNT(*)
        FROM child_vaccinations cv
        JOIN children c ON c.child_id = cv.child_id
        WHERE c.parent_id = ? AND c.is_active = 1
          AND cv.status = 'completed'
    ");
    $stmtCompleted->execute([$parentId]);
    $completedCount = (int)$stmtCompleted->fetchColumn();

    return [
        'active_children' => $activeKids,
        'due_or_missed'   => $dueCount,
        'active_bookings' => $bookedCount,
        'completed_doses' => $completedCount
    ];
}

/**
 * Touches: child_vaccinations, children, vaccines
 * Retrieve doses with status in ('upcoming', 'missed') and due_date <= today + 30 days.
 * 
 * @param int $parentId
 * @return array
 */
function shared_get_upcoming_doses_for_parent(int $parentId): array
{
    // Touches: child_vaccinations, children, vaccines
    $sql = "SELECT cv.child_vaccination_id, cv.child_id, cv.vaccine_id, cv.dose_number,
                   cv.due_date, cv.status,
                   c.full_name AS child_name, c.date_of_birth, c.gender,
                   v.vaccine_name, v.short_code, v.disease_prevented,
                   DATEDIFF(cv.due_date, CURDATE()) AS days_remaining
            FROM child_vaccinations cv
            JOIN children c ON c.child_id = cv.child_id
            JOIN vaccines v ON v.vaccine_id = cv.vaccine_id
            WHERE c.parent_id = ? AND c.is_active = 1
              AND cv.status IN ('upcoming', 'missed')
              AND cv.due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY c.full_name ASC, cv.due_date ASC, cv.dose_number ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute([$parentId]);
    return $stmt->fetchAll();
}

/**
 * Touches: notifications
 * Fetch the latest notifications for an account.
 * 
 * @param int $userId
 * @param int $limit
 * @return array
 */
function shared_get_parent_notifications(int $userId, int $limit = 5): array
{
    // Touches: notifications
    $sql = "SELECT notification_id, user_id, title, message, type,
                   related_appointment_id, is_read, created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC, notification_id DESC
            LIMIT " . (int)$limit;

    $stmt = db()->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Touches: notifications
 * Mark a notification as read.
 * 
 * @param int $notificationId
 * @param int $userId
 * @return bool
 */
function shared_mark_notification_read(int $notificationId, int $userId): bool
{
    // Touches: notifications
    $sql = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?";
    $stmt = db()->prepare($sql);
    $stmt->execute([$notificationId, $userId]);
    return $stmt->rowCount() > 0;
}

// =====================================================================
// SECTION 2: HOSPITALS & STOCK (PARENT & HOSPITAL)
// =====================================================================

/**
 * Touches: hospitals
 * Fetch active hospitals matching keyword search across name, city, address, or area.
 * 
 * @param string $search
 * @return array
 */
function shared_search_hospitals(string $search = ''): array
{
    // Touches: hospitals
    $sql = "SELECT hospital_id, hospital_name, registration_no, address, city, area,
                   phone, email, opening_time, closing_time, daily_capacity, status
            FROM hospitals
            WHERE status = 'active'";

    $params = [];
    if ($search !== '') {
        $sql .= " AND (hospital_name LIKE :kw OR city LIKE :kw OR area LIKE :kw OR address LIKE :kw)";
        $params[':kw'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY city ASC, hospital_name ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Touches: hospitals
 * Fetch a single active hospital record by primary key ID.
 * 
 * @param int $hospitalId
 * @return array|null
 */
function shared_get_hospital_by_id(int $hospitalId): ?array
{
    // Touches: hospitals
    $stmt = db()->prepare("SELECT * FROM hospitals WHERE hospital_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$hospitalId]);
    $res = $stmt->fetch();
    return $res ?: null;
}

/**
 * Touches: hospitals, hospital_vaccine_stock
 * Fetch active hospitals that have the specified vaccine in stock ('available').
 * 
 * @param int $vaccineId
 * @return array
 */
function shared_get_hospitals_stocking_vaccine(int $vaccineId): array
{
    // Touches: hospitals, hospital_vaccine_stock
    $sql = "SELECT h.hospital_id, h.hospital_name, h.address, h.city, h.area,
                   h.phone, h.opening_time, h.closing_time, h.daily_capacity,
                   s.quantity AS stock_quantity
            FROM hospitals h
            JOIN hospital_vaccine_stock s ON s.hospital_id = h.hospital_id
            WHERE h.status = 'active'
              AND s.vaccine_id = ?
              AND s.status = 'available'
              AND s.quantity > 0
            ORDER BY h.city ASC, h.hospital_name ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute([$vaccineId]);
    return $stmt->fetchAll();
}

/**
 * Touches: hospital_vaccine_stock, vaccines
 * Fetch available vaccines for a specific hospital.
 * 
 * @param int $hospitalId
 * @return array
 */
function shared_get_hospital_available_vaccines(int $hospitalId): array
{
    // Touches: hospital_vaccine_stock, vaccines
    $sql = "SELECT v.vaccine_id, v.vaccine_name, v.short_code, v.disease_prevented,
                   s.status, s.quantity, s.updated_at
            FROM hospital_vaccine_stock s
            JOIN vaccines v ON v.vaccine_id = s.vaccine_id
            WHERE s.hospital_id = ?
              AND s.status = 'available'
              AND s.quantity > 0
              AND v.is_active = 1
            ORDER BY v.vaccine_name ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute([$hospitalId]);
    return $stmt->fetchAll();
}

// =====================================================================
// SECTION 3: APPOINTMENT BOOKING & TRACKING (PARENT)
// =====================================================================

/**
 * Touches: appointments, child_vaccinations, appointment_status_history, notifications
 * Calls official stored procedure sp_book_appointment to schedule a visit:
 * - Checks dose status in ('upcoming', 'missed')
 * - Checks appointment date >= CURDATE()
 * - Verifies hospital_vaccine_stock availability
 * - Verifies hospital daily capacity
 * - Inserts into appointments (status='pending')
 * - Updates child_vaccinations status to 'requested'
 * - Records appointment_status_history
 * - Dispatches admin notification
 * 
 * @param int $childVaccinationId
 * @param int $hospitalId
 * @param string $appointmentDate (YYYY-MM-DD)
 * @param string $appointmentTime (HH:MM:SS)
 * @param string|null $parentNote
 * @return array ['success' => bool, 'appointment_id' => int, 'message' => string]
 */
function shared_book_appointment(
    int $childVaccinationId,
    int $hospitalId,
    string $appointmentDate,
    string $appointmentTime,
    ?string $parentNote = null
): array {
    $pdo = db();
    try {
        $sql = "CALL sp_book_appointment(:cv_id, :h_id, :p_date, :p_time, :p_note, @p_appointment_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cv_id'  => $childVaccinationId,
            ':h_id'   => $hospitalId,
            ':p_date' => $appointmentDate,
            ':p_time' => $appointmentTime,
            ':p_note' => $parentNote
        ]);

        $res = $pdo->query("SELECT @p_appointment_id AS new_id")->fetch();
        $newId = (int)($res['new_id'] ?? 0);

        return [
            'success'        => true,
            'appointment_id' => $newId,
            'message'        => "Appointment request #$newId submitted successfully! Your booking is currently Pending admin approval."
        ];
    } catch (PDOException $e) {
        error_log('sp_book_appointment error: ' . $e->getMessage());
        $raw = $e->getMessage();
        // Extract custom SIGNAL MESSAGE_TEXT if raised by stored procedure
        if (preg_match('/1644\s+(.+?)(?:\s*\(|$)/', $raw, $m)) {
            $userMsg = trim($m[1]);
        } elseif (preg_match('/SQLSTATE\[45000\]:.*?:\s*\d+\s*(.+)$/', $raw, $m)) {
            $userMsg = trim($m[1]);
        } else {
            $userMsg = 'Booking request could not be completed. Please verify slot availability and try again.';
        }
        return [
            'success'        => false,
            'appointment_id' => 0,
            'message'        => $userMsg
        ];
    }
}

/**
 * Touches: appointments, children, vaccines, hospitals, child_vaccinations
 * Fetch all appointments belonging to children of a parent.
 * 
 * @param int $parentId
 * @return array
 */
function shared_get_parent_appointments(int $parentId): array
{
    // Touches: appointments, children, vaccines, hospitals, child_vaccinations
    $sql = "SELECT a.appointment_id, a.child_vaccination_id, a.parent_id, a.child_id,
                   a.hospital_id, a.vaccine_id, a.appointment_date, a.appointment_time,
                   a.status, a.parent_note, a.admin_remarks, a.reviewed_at, a.completed_at, a.created_at,
                   c.full_name AS child_name, c.date_of_birth, c.gender,
                   v.vaccine_name, v.short_code,
                   cv.dose_number,
                   h.hospital_name, h.address AS hospital_address, h.city AS hospital_city
            FROM appointments a
            JOIN children c ON c.child_id = a.child_id
            JOIN vaccines v ON v.vaccine_id = a.vaccine_id
            JOIN hospitals h ON h.hospital_id = a.hospital_id
            JOIN child_vaccinations cv ON cv.child_vaccination_id = a.child_vaccination_id
            WHERE a.parent_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.appointment_id DESC";

    $stmt = db()->prepare($sql);
    $stmt->execute([$parentId]);
    return $stmt->fetchAll();
}

/**
 * WRITES TO ADMIN-OWNED TABLES: confirm with the admin team
 * Touches: appointments, child_vaccinations, appointment_status_history
 * Cancel an appointment if it is currently 'pending'.
 * Resets child_vaccinations status back to 'upcoming' or 'missed' based on due_date.
 * 
 * @param int $appointmentId
 * @param int $parentId
 * @param int $userId
 * @return bool
 */
function shared_cancel_parent_appointment(int $appointmentId, int $parentId, int $userId): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Fetch appointment details enforcing parent ownership and pending status
        $stmt = $pdo->prepare("SELECT appointment_id, child_vaccination_id, status FROM appointments WHERE appointment_id = ? AND parent_id = ? AND status = 'pending' FOR UPDATE");
        $stmt->execute([$appointmentId, $parentId]);
        $apt = $stmt->fetch();

        if (!$apt) {
            $pdo->rollBack();
            return false;
        }

        $cvId = (int)$apt['child_vaccination_id'];

        // 1. Update appointment status to cancelled
        $upApt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?");
        $upApt->execute([$appointmentId]);

        // 2. Determine target child_vaccinations status: if due_date < CURDATE() then 'missed', else 'upcoming'
        $stmtCv = $pdo->prepare("SELECT due_date FROM child_vaccinations WHERE child_vaccination_id = ?");
        $stmtCv->execute([$cvId]);
        $dueDate = $stmtCv->fetchColumn();
        $newCvStatus = ($dueDate && $dueDate < date('Y-m-d')) ? 'missed' : 'upcoming';

        $upCv = $pdo->prepare("UPDATE child_vaccinations SET status = ? WHERE child_vaccination_id = ?");
        $upCv->execute([$newCvStatus, $cvId]);

        // 3. Record status history
        $insHist = $pdo->prepare("INSERT INTO appointment_status_history (appointment_id, old_status, new_status, changed_by, remarks) VALUES (?, 'pending', 'cancelled', ?, 'Cancelled by parent')");
        $insHist->execute([$appointmentId, $userId]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('shared_cancel_parent_appointment error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Touches: vaccination_records, children, vaccines, hospitals
 * Fetch completed vaccination records for parent reports.
 * 
 * @param int $parentId
 * @param int $childId (0 for all children)
 * @return array
 */
function shared_get_vaccination_records(int $parentId, int $childId = 0): array
{
    // Touches: vaccination_records, children, vaccines, hospitals
    $sql = "SELECT r.record_id, r.appointment_id, r.child_vaccination_id, r.child_id,
                   r.vaccine_id, r.hospital_id, r.dose_number, r.vaccination_date, r.vaccination_time,
                   r.batch_number, r.administered_by, r.remarks, r.created_at,
                   c.full_name AS child_name, c.date_of_birth, c.gender,
                   v.vaccine_name, v.disease_prevented,
                   h.hospital_name, h.city AS hospital_city, h.address AS hospital_address
            FROM vaccination_records r
            JOIN children c ON c.child_id = r.child_id
            JOIN vaccines v ON v.vaccine_id = r.vaccine_id
            JOIN hospitals h ON h.hospital_id = r.hospital_id
            WHERE c.parent_id = :parent_id";

    if ($childId > 0) {
        $sql .= " AND c.child_id = :child_id";
    }

    $sql .= " ORDER BY r.vaccination_date DESC, r.vaccination_time DESC, r.record_id DESC";

    $stmt = db()->prepare($sql);
    $params = [':parent_id' => $parentId];
    if ($childId > 0) {
        $params[':child_id'] = $childId;
    }
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// =====================================================================
// SECTION 4: PARENT PROFILE & ACCOUNT
// =====================================================================

/**
 * Touches: parents, users
 * Fetch profile details for the logged-in parent joining users table.
 * 
 * @param int $parentId
 * @return array|null
 */
function shared_get_parent_profile(int $parentId): ?array
{
    // Touches: parents, users
    $sql = "SELECT p.parent_id, p.user_id, p.full_name, p.cnic, p.phone,
                   p.address, p.city, p.created_at AS parent_created_at,
                   u.email, u.password_hash, u.created_at AS user_created_at
            FROM parents p
            JOIN users u ON u.user_id = p.user_id
            WHERE p.parent_id = ?
            LIMIT 1";

    $stmt = db()->prepare($sql);
    $stmt->execute([$parentId]);
    $res = $stmt->fetch();
    return $res ?: null;
}

/**
 * Touches: parents
 * Update parent profile contact information.
 * 
 * @param int $parentId
 * @param string $fullName
 * @param string $phone
 * @param string|null $address
 * @param string|null $city
 * @param string|null $cnic
 * @return bool
 */
function shared_update_parent_profile(
    int $parentId,
    string $fullName,
    string $phone,
    ?string $address = null,
    ?string $city = null,
    ?string $cnic = null
): bool {
    // Touches: parents
    $sql = "UPDATE parents
            SET full_name = ?, phone = ?, address = ?, city = ?, cnic = ?
            WHERE parent_id = ?";

    $stmt = db()->prepare($sql);
    $stmt->execute([$fullName, $phone, $address, $city, $cnic, $parentId]);
    return $stmt->rowCount() > 0;
}

/**
 * Touches: users
 * Update the password hash for a user account.
 * 
 * @param int $userId
 * @param string $newPasswordHash
 * @return bool
 */
function shared_update_user_password(int $userId, string $newPasswordHash): bool
{
    // Touches: users
    $sql = "UPDATE users SET password_hash = ? WHERE user_id = ?";
    $stmt = db()->prepare($sql);
    $stmt->execute([$newPasswordHash, $userId]);
    return $stmt->rowCount() > 0;
}

// =====================================================================
// SECTION 5: HOSPITAL OPERATIONS (HOSPITAL PANEL)
// =====================================================================

/**
 * Touches: users, hospitals
 * Register a new hospital facility and staff login credentials in a transaction.
 * 
 * @param string $name
 * @param string $email
 * @param string $passwordHash
 * @param string $address
 * @param string $city
 * @param string|null $area
 * @param string|null $phone
 * @param string|null $regNo
 * @param string $openingTime
 * @param string $closingTime
 * @param int $dailyCapacity
 * @return int Inserted hospital_id
 */
function shared_register_hospital_facility(
    string $name,
    string $email,
    string $passwordHash,
    string $address,
    string $city,
    ?string $area = null,
    ?string $phone = null,
    ?string $regNo = null,
    string $openingTime = '09:00:00',
    string $closingTime = '17:00:00',
    int $dailyCapacity = 50
): int {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // 1. Insert into users table with status='inactive' awaiting admin approval
        $stmtUser = $pdo->prepare("INSERT INTO users (email, password_hash, role, status) VALUES (?, ?, 'hospital', 'inactive')");
        $stmtUser->execute([$email, $passwordHash]);
        $userId = (int)$pdo->lastInsertId();

        // 2. Insert into hospitals table with status='inactive'
        $stmtHosp = $pdo->prepare("
            INSERT INTO hospitals (
                user_id, hospital_name, registration_no, address, city, area,
                phone, email, opening_time, closing_time, daily_capacity, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'inactive')
        ");
        $stmtHosp->execute([
            $userId,
            $name,
            $regNo,
            $address,
            $city,
            $area,
            $phone,
            $email,
            $openingTime,
            $closingTime,
            $dailyCapacity
        ]);
        $hospitalId = (int)$pdo->lastInsertId();

        $pdo->commit();
        return $hospitalId;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('shared_register_hospital_facility error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Touches: appointments, children, parents, vaccines, child_vaccinations
 * Fetch appointments assigned to a hospital filtered by status.
 * 
 * @param int $hospitalId
 * @param string $statusFilter ('approved' | 'completed' | 'all' | 'pending' | 'rejected')
 * @return array
 */
function shared_get_hospital_appointments_list(int $hospitalId, string $statusFilter = 'approved'): array
{
    // Touches: appointments, children, parents, vaccines, child_vaccinations
    $sql = "SELECT a.appointment_id, a.child_vaccination_id, a.parent_id, a.child_id,
                   a.hospital_id, a.vaccine_id, a.appointment_date, a.appointment_time,
                   a.status, a.parent_note, a.admin_remarks, a.reviewed_at, a.completed_at, a.created_at,
                   c.full_name AS child_name, c.date_of_birth, c.gender, c.blood_group, c.allergies,
                   p.full_name AS parent_name, p.phone AS parent_phone,
                   v.vaccine_name, v.short_code, v.disease_prevented,
                   cv.dose_number
            FROM appointments a
            JOIN children c ON c.child_id = a.child_id
            JOIN parents p ON p.parent_id = a.parent_id
            JOIN vaccines v ON v.vaccine_id = a.vaccine_id
            JOIN child_vaccinations cv ON cv.child_vaccination_id = a.child_vaccination_id
            WHERE a.hospital_id = ?";

    $params = [$hospitalId];

    if ($statusFilter !== 'all') {
        $sql .= " AND a.status = ?";
        $params[] = $statusFilter;
    }

    $sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC, a.appointment_id ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Touches: appointments, children, vaccines, parents, child_vaccinations
 * Fetch single appointment for hospital action.
 * 
 * @param int $appointmentId
 * @param int $hospitalId
 * @return array|null
 */
function shared_get_hospital_appointment_by_id(int $appointmentId, int $hospitalId): ?array
{
    $sql = "SELECT a.*, c.full_name AS child_name, c.date_of_birth, c.gender, c.allergies,
                   p.full_name AS parent_name, p.phone AS parent_phone,
                   v.vaccine_name, cv.dose_number
            FROM appointments a
            JOIN children c ON c.child_id = a.child_id
            JOIN parents p ON p.parent_id = a.parent_id
            JOIN vaccines v ON v.vaccine_id = a.vaccine_id
            JOIN child_vaccinations cv ON cv.child_vaccination_id = a.child_vaccination_id
            WHERE a.appointment_id = ? AND a.hospital_id = ?
            LIMIT 1";

    $stmt = db()->prepare($sql);
    $stmt->execute([$appointmentId, $hospitalId]);
    $res = $stmt->fetch();
    return $res ?: null;
}

/**
 * Touches: appointments, child_vaccinations, vaccination_records, hospital_vaccine_stock, notifications
 * Calls official stored procedure sp_complete_appointment:
 * - Verifies appointment status = 'approved'
 * - Verifies hospital user ownership
 * - Updates appointment status to 'completed'
 * - Updates child_vaccinations status to 'completed'
 * - Inserts vaccination_records row
 * - Decrements stock in hospital_vaccine_stock
 * - Logs status history and dispatches notifications
 * 
 * @param int $appointmentId
 * @param int $hospitalUserId
 * @param string $batchNumber
 * @param string $administeredBy
 * @param string|null $remarks
 * @return array ['success' => bool, 'message' => string]
 */
function shared_complete_appointment(
    int $appointmentId,
    int $hospitalUserId,
    string $batchNumber,
    string $administeredBy,
    ?string $remarks = null
): array {
    $pdo = db();
    try {
        $stmt = $pdo->prepare("CALL sp_complete_appointment(:ap_id, :h_user_id, :batch, :admin_by, :remarks)");
        $stmt->execute([
            ':ap_id'     => $appointmentId,
            ':h_user_id' => $hospitalUserId,
            ':batch'     => $batchNumber,
            ':admin_by'  => $administeredBy,
            ':remarks'   => $remarks ?: null
        ]);

        return [
            'success' => true,
            'message' => "Appointment #$appointmentId successfully completed! Vaccination record saved."
        ];
    } catch (PDOException $e) {
        error_log('sp_complete_appointment error: ' . $e->getMessage());
        $raw = $e->getMessage();
        if (preg_match('/1644\s+(.+?)(?:\s*\(|$)/', $raw, $m)) {
            $userMsg = trim($m[1]);
        } elseif (preg_match('/SQLSTATE\[45000\]:.*?:\s*\d+\s*(.+)$/', $raw, $m)) {
            $userMsg = trim($m[1]);
        } else {
            $userMsg = 'Appointment completion failed. Please verify that this booking belongs to your hospital and is approved.';
        }
        return [
            'success' => false,
            'message' => $userMsg
        ];
    }
}

/**
 * Touches: appointments (STUB ONLY - DO NOT WRITE TO APPOINTMENTS DIRECTLY)
 * Task 9: The official database currently has no stored procedure for marking an appointment
 * as 'Not Vaccinated'. To maintain data integrity, activity history, and notification triggers,
 * business logic must not write directly to appointments.
 * 
 * @param int $appointmentId
 * @param int $hospitalId
 * @throws BadMethodCallException
 */
function shared_hospital_mark_not_vaccinated(int $appointmentId, int $hospitalId): void
{
    // The DB has no stored procedure for 'Not Vaccinated' yet.
    throw new BadMethodCallException('Marking as Not Vaccinated is not available yet (awaiting database stored procedure).');
}

/**
 * Touches: hospital_vaccine_stock, vaccines
 * Fetch inventory list of all vaccines and the hospital's stock level.
 * 
 * @param int $hospitalId
 * @return array
 */
function shared_get_hospital_inventory(int $hospitalId): array
{
    // Touches: hospital_vaccine_stock, vaccines
    $sql = "SELECT v.vaccine_id, v.vaccine_name, v.short_code, v.disease_prevented, v.total_doses,
                   COALESCE(s.stock_id, 0) AS stock_id,
                   COALESCE(s.status, 'unavailable') AS stock_status,
                   COALESCE(s.quantity, 0) AS quantity,
                   s.updated_at
            FROM vaccines v
            LEFT JOIN hospital_vaccine_stock s ON s.vaccine_id = v.vaccine_id AND s.hospital_id = ?
            WHERE v.is_active = 1
            ORDER BY v.vaccine_name ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute([$hospitalId]);
    return $stmt->fetchAll();
}

/**
 * Touches: vaccines
 * Check if a vaccine ID exists and is currently active.
 * 
 * @param int $vaccineId
 * @return bool
 */
function shared_is_vaccine_active(int $vaccineId): bool
{
    // Touches: vaccines
    $stmt = db()->prepare("SELECT 1 FROM vaccines WHERE vaccine_id = ? AND is_active = 1");
    $stmt->execute([$vaccineId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Touches: hospital_vaccine_stock
 * Calls official stored procedure sp_update_vaccine_status to update vaccine stock.
 * 
 * @param int $hospitalId
 * @param int $vaccineId
 * @param string $status ('available' | 'unavailable')
 * @param int $quantity
 * @param int $userId
 * @return bool
 */
function shared_update_hospital_stock(
    int $hospitalId,
    int $vaccineId,
    string $status,
    int $quantity,
    int $userId
): bool {
    try {
        $stmt = db()->prepare("CALL sp_update_vaccine_status(:h_id, :v_id, :status, :qty, :u_id)");
        $stmt->execute([
            ':h_id'   => $hospitalId,
            ':v_id'   => $vaccineId,
            ':status' => $status,
            ':qty'    => $quantity,
            ':u_id'   => $userId
        ]);
        return true;
    } catch (PDOException $e) {
        error_log('sp_update_vaccine_status error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Touches: hospitals, users
 * Fetch profile details for a hospital.
 * 
 * @param int $hospitalId
 * @return array|null
 */
function shared_get_hospital_profile(int $hospitalId): ?array
{
    // Touches: hospitals, users
    $sql = "SELECT h.*, u.email, u.password_hash, u.created_at AS user_created_at
            FROM hospitals h
            JOIN users u ON u.user_id = h.user_id
            WHERE h.hospital_id = ?
            LIMIT 1";

    $stmt = db()->prepare($sql);
    $stmt->execute([$hospitalId]);
    $res = $stmt->fetch();
    return $res ?: null;
}

/**
 * Touches: hospitals
 * Update hospital profile information.
 * 
 * @param int $hospitalId
 * @param string $name
 * @param string $address
 * @param string $city
 * @param string|null $area
 * @param string|null $phone
 * @param string $openingTime
 * @param string $closingTime
 * @return bool
 */
function shared_update_hospital_profile(
    int $hospitalId,
    string $name,
    string $address,
    string $city,
    ?string $area = null,
    ?string $phone = null,
    string $openingTime = '09:00:00',
    string $closingTime = '17:00:00',
    int $dailyCapacity = 50
): bool {
    // Touches: hospitals
    $sql = "UPDATE hospitals
            SET hospital_name = ?, address = ?, city = ?, area = ?, phone = ?,
                opening_time = ?, closing_time = ?, daily_capacity = ?
            WHERE hospital_id = ?";

    $stmt = db()->prepare($sql);
    $stmt->execute([
        $name,
        $address,
        $city,
        $area,
        $phone,
        $openingTime,
        $closingTime,
        $dailyCapacity,
        $hospitalId
    ]);
    return true;
}
