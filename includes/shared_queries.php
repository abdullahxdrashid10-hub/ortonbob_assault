<?php
/**
 * shared_queries.php - Central Query Repository for Real Vaccination Management Schema
 * 
 * Interacts directly with the team's official database:
 * - users, parents, children, child_vaccinations, vaccines, vaccine_schedule, notifications
 * 
 * Every function is documented with the exact tables it touches and enforces
 * parent ownership and active-state checks server-side.
 */

require_once __DIR__ . '/../config/config.php';

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
 * Add a new child record. The database AFTER INSERT trigger automatically
 * calls sp_generate_child_schedule() to populate child_vaccinations rows.
 * 
 * @param int $parentId
 * @param string $fullName
 * @param string $dob (YYYY-MM-DD)
 * @param string $gender ('male' | 'female' | 'other')
 * @param string $bloodGroup ('A+' | 'A-' | 'B+' | 'B-' | 'AB+' | 'AB-' | 'O+' | 'O-' | 'unknown')
 * @param float|null $weightKg
 * @param string|null $certNo
 * @param string|null $allergies
 * @param string|null $medicalNotes
 * @return int Inserted child_id
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
}

/**
 * Touches: child_vaccinations
 * Count the generated vaccination schedule doses for a child.
 * Used after insert to verify the trigger generated schedule rows.
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
 * Update an existing active child's profile details with parent ownership enforcement.
 * Note: date_of_birth is NOT modifiable because the immunization schedule is generated
 * once upon insertion; changing DOB would leave stale doses.
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
    // Touches: children (date_of_birth removed to prevent stale schedule doses)
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
}

/**
 * Touches: children, child_vaccinations
 * Soft-delete a child record by setting is_active = 0.
 * Mandatory rule: Refuse if the child has any child_vaccinations with status 'requested' or 'booked'.
 * 
 * @param int $childId
 * @param int $parentId
 * @return string|bool Returns 'active_appointment' if active doses exist, true on success, false on failure.
 */
function shared_soft_delete_child(int $childId, int $parentId): string|bool
{
    // Touches: child_vaccinations, children
    // Guard: check if child has active appointments ('requested' or 'booked')
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

    // Touches: children (perform soft delete)
    $sql = "UPDATE children SET is_active = 0 WHERE child_id = ? AND parent_id = ? AND is_active = 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([$childId, $parentId]);
    return $stmt->rowCount() > 0 ? true : false;
}

/**
 * Touches: child_vaccinations, vaccines, children
 * Retrieve the full immunization schedule for a specific active child of this parent.
 * Joins child_vaccinations with vaccines for comprehensive dose details.
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
 * Touches: children, child_vaccinations
 * Summary KPI counts for the parent dashboard cards:
 * 1. Active children count
 * 2. Doses due within 30 days or missed/overdue
 * 3. Doses currently requested or booked
 * 4. Doses completed
 * 
 * @param int $parentId
 * @return array
 */
function shared_get_parent_dashboard_counts(int $parentId): array
{
    $pdo = db();

    // 1. Active children
    $stmtKids = $pdo->prepare("SELECT COUNT(*) FROM children WHERE parent_id = ? AND is_active = 1");
    $stmtKids->execute([$parentId]);
    $activeKids = (int)$stmtKids->fetchColumn();

    // 2. Doses due (upcoming within 30 days) or missed (overdue)
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

    // 3. Doses booked or requested
    $stmtBooked = $pdo->prepare("
        SELECT COUNT(*)
        FROM child_vaccinations cv
        JOIN children c ON c.child_id = cv.child_id
        WHERE c.parent_id = ? AND c.is_active = 1
          AND cv.status IN ('requested', 'booked')
    ");
    $stmtBooked->execute([$parentId]);
    $bookedCount = (int)$stmtBooked->fetchColumn();

    // 4. Doses completed
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
 * Retrieve all child_vaccinations with status in ('upcoming', 'missed')
 * and due_date <= today + 30 days, across all active children of the parent.
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
 * Fetch the latest notifications for the authenticated user account.
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
 * Mark a specific notification as read, enforcing user ownership.
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

/**
 * Touches: parents, users
 * Fetch profile details for the logged-in parent joining users table for email and timestamps.
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
