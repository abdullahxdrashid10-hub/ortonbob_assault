<?php
/**
 * shared_queries.php - Centralized repository for all queries touching
 * admin-owned SHARED TABLES (hospitals, vaccines, bookings).
 * 
 * Rules:
 * - Every function here operates on or joins one of the shared tables.
 * - Every query block is commented with "SHARED TABLE".
 * - When the admin team shares their finalized database schema, update
 *   column names and table structures HERE without altering individual page files.
 */

require_once __DIR__ . '/../config/config.php';

/**
 * SHARED TABLE: hospitals
 * Fetch all hospitals matching a search term (name, location, or address).
 * 
 * @param string $search
 * @return array
 */
function shared_search_hospitals(string $search = ''): array
{
    // SHARED TABLE: hospitals
    $sql = "SELECT id, name, address, location, phone 
            FROM hospitals 
            WHERE name LIKE :kw OR location LIKE :kw OR address LIKE :kw 
            ORDER BY name ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute([':kw' => '%' . $search . '%']);
    return $stmt->fetchAll();
}

/**
 * SHARED TABLE: hospitals
 * Fetch a single hospital record by its primary key ID.
 * 
 * @param int $hospitalId
 * @return array|null
 */
function shared_get_hospital_by_id(int $hospitalId): ?array
{
    // SHARED TABLE: hospitals
    $stmt = db()->prepare("SELECT id, name, address, location, phone FROM hospitals WHERE id = ?");
    $stmt->execute([$hospitalId]);
    $res = $stmt->fetch();
    return $res ?: null;
}

/**
 * SHARED TABLE: vaccines
 * Fetch all vaccines currently marked as available in the inventory.
 * 
 * @return array
 */
function shared_get_available_vaccines(): array
{
    // SHARED TABLE: vaccines
    $stmt = db()->query("SELECT id, name, available FROM vaccines WHERE available = 1 ORDER BY name ASC");
    return $stmt->fetchAll();
}

/**
 * SHARED TABLE: vaccines
 * Fetch a single vaccine record by ID.
 * 
 * @param int $vaccineId
 * @return array|null
 */
function shared_get_vaccine_by_id(int $vaccineId): ?array
{
    // SHARED TABLE: vaccines
    $stmt = db()->prepare("SELECT id, name, available FROM vaccines WHERE id = ?");
    $stmt->execute([$vaccineId]);
    $res = $stmt->fetch();
    return $res ?: null;
}

/**
 * SHARED TABLE: vaccines, bookings
 * Get available vaccines for a specific child that can be booked:
 * - Must exist in vaccine_schedule
 * - Must be available (available = 1 in vaccines)
 * - Must NOT already have an active/completed booking ('Pending', 'Approved', 'Vaccinated')
 * 
 * @param int $childId
 * @return array
 */
function shared_get_bookable_vaccines_for_child(int $childId): array
{
    // SHARED TABLE: vaccines, bookings
    $sql = "SELECT v.id, v.name, s.due_after_days, s.description
            FROM vaccines v
            JOIN vaccine_schedule s ON (s.vaccine_id = v.id OR s.vaccine_name = v.name)
            WHERE v.available = 1
              AND NOT EXISTS (
                  SELECT 1 FROM bookings b 
                  WHERE b.child_id = :child_id 
                    AND b.vaccine_id = v.id 
                    AND b.status IN ('Pending', 'Approved', 'Vaccinated')
              )
            ORDER BY s.due_after_days ASC, v.name ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute([':child_id' => $childId]);
    return $stmt->fetchAll();
}

/**
 * SHARED TABLE: bookings
 * Check if a booking already exists for a child and vaccine that is active or completed.
 * Statuses: 'Pending', 'Approved', 'Vaccinated'
 * 
 * @param int $childId
 * @param int $vaccineId
 * @return bool
 */
function shared_has_active_or_completed_booking(int $childId, int $vaccineId): bool
{
    // SHARED TABLE: bookings
    $sql = "SELECT id FROM bookings 
            WHERE child_id = ? AND vaccine_id = ? AND status IN ('Pending', 'Approved', 'Vaccinated')
            LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([$childId, $vaccineId]);
    return (bool)$stmt->fetch();
}

/**
 * SHARED TABLE: bookings
 * Task 2: Check if a child has ANY booking records (regardless of status).
 * Used to guard against child deletion when historical records exist.
 * 
 * @param int $childId
 * @return bool
 */
function shared_child_has_bookings(int $childId): bool
{
    // SHARED TABLE: bookings
    $stmt = db()->prepare("SELECT 1 FROM bookings WHERE child_id = ? LIMIT 1");
    $stmt->execute([$childId]);
    return (bool)$stmt->fetch();
}

/**
 * SHARED TABLE: bookings
 * Create a new vaccination booking with initial status 'Pending'.
 * 
 * @param int $childId
 * @param int $vaccineId
 * @param int $hospitalId
 * @param string $bookingDate (YYYY-MM-DD)
 * @param string|null $bookingTime (HH:MM:SS)
 * @return int Inserted booking ID
 */
function shared_create_booking(int $childId, int $vaccineId, int $hospitalId, string $bookingDate, ?string $bookingTime = null): int
{
    // SHARED TABLE: bookings
    $sql = "INSERT INTO bookings (child_id, vaccine_id, hospital_id, booking_date, booking_time, status)
            VALUES (?, ?, ?, ?, ?, 'Pending')";
    $stmt = db()->prepare($sql);
    $stmt->execute([$childId, $vaccineId, $hospitalId, $bookingDate, $bookingTime]);
    return (int)db()->lastInsertId();
}

/**
 * SHARED TABLE: bookings, vaccines, hospitals
 * Fetch all bookings belonging to any child of a given parent.
 * Includes child name, vaccine name, and hospital name.
 * 
 * @param int $parentId
 * @return array
 */
function shared_get_parent_bookings(int $parentId): array
{
    // SHARED TABLE: bookings, vaccines, hospitals
    $sql = "SELECT b.id, b.child_id, b.vaccine_id, b.hospital_id, b.booking_date, b.booking_time, 
                   b.status, b.vaccinated_on, b.created_at,
                   c.full_name AS child_name,
                   v.name AS vaccine_name,
                   h.name AS hospital_name, h.location AS hospital_location
            FROM bookings b
            JOIN children c ON c.id = b.child_id
            JOIN vaccines v ON v.id = b.vaccine_id
            JOIN hospitals h ON h.id = b.hospital_id
            WHERE c.parent_id = ?
            ORDER BY b.booking_date DESC, b.id DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute([$parentId]);
    return $stmt->fetchAll();
}

/**
 * SHARED TABLE: bookings
 * Aggregate booking counts by status for dashboard statistics.
 * 
 * @param int $parentId
 * @return array [status => count]
 */
function shared_get_parent_booking_counts(int $parentId): array
{
    // SHARED TABLE: bookings
    $sql = "SELECT b.status, COUNT(*) AS count_num
            FROM bookings b
            JOIN children c ON c.id = b.child_id
            WHERE c.parent_id = ?
            GROUP BY b.status";
    $stmt = db()->prepare($sql);
    $stmt->execute([$parentId]);
    return array_column($stmt->fetchAll(), 'count_num', 'status');
}

/**
 * SHARED TABLE: bookings, vaccines
 * For a single child, compute the status of every vaccine in vaccine_schedule.
 * Status rules:
 * - 'Taken': A booking with status 'Vaccinated' exists for this child & vaccine
 * - 'Booked': A booking with status 'Pending' or 'Approved' exists
 * - 'Overdue': Due date < CURDATE() and no Taken/Booked record
 * - 'Upcoming': Due date >= CURDATE() and no Taken/Booked record
 * 
 * @param int $childId
 * @param string $childDob (YYYY-MM-DD)
 * @return array Array of schedule items with calculated status and due date
 */
function shared_get_child_schedule_status(int $childId, string $childDob): array
{
    // SHARED TABLE: vaccines, bookings
    $sql = "SELECT s.id AS schedule_id, s.vaccine_name, s.due_after_days, s.description,
                   COALESCE(s.vaccine_id, v.id) AS vaccine_id,
                   DATE_ADD(:dob, INTERVAL s.due_after_days DAY) AS due_date,
                   latest_b.status AS booking_status,
                   latest_b.booking_date,
                   latest_b.booking_time,
                   latest_b.vaccinated_on
            FROM vaccine_schedule s
            LEFT JOIN vaccines v ON v.name = s.vaccine_name
            LEFT JOIN (
                SELECT b1.child_id, b1.vaccine_id, b1.status, b1.booking_date, b1.booking_time, b1.vaccinated_on
                FROM bookings b1
                WHERE b1.child_id = :child_id
                  AND b1.id = (
                      SELECT MAX(b2.id) 
                      FROM bookings b2 
                      WHERE b2.child_id = b1.child_id 
                        AND b2.vaccine_id = b1.vaccine_id
                  )
            ) latest_b ON latest_b.vaccine_id = COALESCE(s.vaccine_id, v.id)
            ORDER BY s.due_after_days ASC, s.id ASC";
            
    $stmt = db()->prepare($sql);
    $stmt->execute([':dob' => $childDob, ':child_id' => $childId]);
    $rows = $stmt->fetchAll();

    $today = date('Y-m-d');
    foreach ($rows as &$row) {
        $bStatus = $row['booking_status'];
        if ($bStatus === 'Vaccinated') {
            $row['computed_status'] = 'Taken';
        } elseif ($bStatus === 'Pending' || $bStatus === 'Approved') {
            $row['computed_status'] = 'Booked';
        } elseif ($row['due_date'] < $today) {
            $row['computed_status'] = 'Overdue';
        } else {
            $row['computed_status'] = 'Upcoming';
        }
    }
    unset($row);

    return $rows;
}

/**
 * SHARED TABLE: bookings, vaccines
 * Retrieve all upcoming vaccinations (due in <= 30 days) and overdue vaccinations
 * for ALL children belonging to a parent, excluding vaccines that are already
 * 'Vaccinated', 'Pending', or 'Approved'.
 * 
 * @param int $parentId
 * @return array
 */
function shared_get_upcoming_and_overdue_vaccines_all_children(int $parentId): array
{
    // SHARED TABLE: vaccines, bookings
    $sql = "SELECT c.id AS child_id, c.full_name AS child_name, c.dob AS child_dob,
                   s.vaccine_name, s.due_after_days, s.description,
                   COALESCE(s.vaccine_id, v.id) AS vaccine_id,
                   DATE_ADD(c.dob, INTERVAL s.due_after_days DAY) AS due_date,
                   DATEDIFF(DATE_ADD(c.dob, INTERVAL s.due_after_days DAY), CURDATE()) AS days_left
            FROM children c
            CROSS JOIN vaccine_schedule s
            LEFT JOIN vaccines v ON v.name = s.vaccine_name
            WHERE c.parent_id = :parent_id
              AND DATE_ADD(c.dob, INTERVAL s.due_after_days DAY) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
              AND NOT EXISTS (
                  SELECT 1 FROM bookings b
                  WHERE b.child_id = c.id
                    AND b.vaccine_id = COALESCE(s.vaccine_id, v.id)
                    AND b.status IN ('Vaccinated', 'Pending', 'Approved')
              )
            ORDER BY c.full_name ASC, due_date ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute([':parent_id' => $parentId]);
    return $stmt->fetchAll();
}

/**
 * SHARED TABLE: bookings, vaccines, hospitals
 * Fetch vaccination records with status = 'Vaccinated' for vaccination report.
 * Optionally filtered by a specific child.
 * 
 * @param int $parentId
 * @param int $childId (0 for all children)
 * @return array
 */
function shared_get_vaccination_report(int $parentId, int $childId = 0): array
{
    // SHARED TABLE: bookings, vaccines, hospitals
    $sql = "SELECT b.id, b.booking_date, b.booking_time, b.vaccinated_on,
                   c.id AS child_id, c.full_name AS child_name, c.dob AS child_dob, c.gender,
                   v.name AS vaccine_name,
                   h.name AS hospital_name, h.location AS hospital_location, h.address AS hospital_address
            FROM bookings b
            JOIN children c ON c.id = b.child_id
            JOIN vaccines v ON v.id = b.vaccine_id
            JOIN hospitals h ON h.id = b.hospital_id
            WHERE c.parent_id = :parent_id
              AND b.status = 'Vaccinated'";

    if ($childId > 0) {
        $sql .= " AND c.id = :child_id";
    }

    $sql .= " ORDER BY COALESCE(b.vaccinated_on, b.booking_date) DESC, b.id DESC";

    $stmt = db()->prepare($sql);
    $params = [':parent_id' => $parentId];
    if ($childId > 0) {
        $params[':child_id'] = $childId;
    }
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * SHARED TABLE: bookings
 * Tasks 1 & 5: Update booking status by hospital staff.
 * - If status is 'Vaccinated', sets status and vaccinated_on = NOW().
 * - If status is 'Not Vaccinated', sets status and vaccinated_on = NULL.
 * - WHERE clause strictly enforces: hospital owns booking AND current status is 'Approved'.
 * 
 * @param int $bookingId
 * @param int $hospitalId
 * @param string $newStatus ('Vaccinated' | 'Not Vaccinated')
 * @return bool True if a row was updated, false otherwise.
 */
function shared_hospital_update_booking_status(int $bookingId, int $hospitalId, string $newStatus): bool
{
    // SHARED TABLE: bookings
    if ($newStatus === ST_VACCINATED) {
        $sql = "UPDATE bookings 
                SET status = :status, vaccinated_on = NOW() 
                WHERE id = :id AND hospital_id = :hid AND status = 'Approved'";
    } else {
        $sql = "UPDATE bookings 
                SET status = :status, vaccinated_on = NULL 
                WHERE id = :id AND hospital_id = :hid AND status = 'Approved'";
    }

    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':status' => $newStatus,
        ':id'     => $bookingId,
        ':hid'    => $hospitalId
    ]);
    return $stmt->rowCount() > 0;
}

/**
 * SHARED TABLE: bookings, children, parents, vaccines
 * Task 5: Fetch appointments belonging to a specific hospital filtered by status.
 * 
 * @param int $hospitalId
 * @param string $statusFilter ('Approved' | 'Vaccinated' | 'Not Vaccinated')
 * @return array
 */
function shared_get_hospital_appointments(int $hospitalId, string $statusFilter): array
{
    // SHARED TABLE: bookings, vaccines
    $sql = "SELECT b.id, b.booking_date, b.booking_time, b.status, b.vaccinated_on,
                   c.full_name AS child, c.dob, c.gender,
                   v.name AS vaccine,
                   p.full_name AS parent, p.phone
            FROM bookings b
            JOIN children c ON c.id = b.child_id
            JOIN parents p ON p.id = c.parent_id
            JOIN vaccines v ON v.id = b.vaccine_id
            WHERE b.hospital_id = ? AND b.status = ?
            ORDER BY b.booking_date ASC, b.booking_time ASC, b.id ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute([$hospitalId, $statusFilter]);
    return $stmt->fetchAll();
}

/**
 * SHARED TABLE: hospitals
 * Task 5: Fetch hospital user credentials and hospital display name for login.
 * 
 * @param string $email
 * @return array|null
 */
function shared_get_hospital_user_by_email(string $email): ?array
{
    // SHARED TABLE: hospitals
    $sql = "SELECT u.id, u.hospital_id, u.email, u.password_hash, h.name 
            FROM hospital_users u 
            JOIN hospitals h ON h.id = u.hospital_id 
            WHERE u.email = ?";
    $stmt = db()->prepare($sql);
    $stmt->execute([$email]);
    $res = $stmt->fetch();
    return $res ?: null;
}

/**
 * SHARED TABLE: hospitals
 * Task 5: Check if hospital login email is already registered.
 * 
 * @param string $email
 * @return bool
 */
function shared_hospital_email_exists(string $email): bool
{
    $stmt = db()->prepare("SELECT id FROM hospital_users WHERE email = ?");
    $stmt->execute([$email]);
    return (bool)$stmt->fetch();
}

/**
 * SHARED TABLE: hospitals
 * Task 5: Register a new hospital and create its associated login credentials
 * within a single database transaction.
 * 
 * @param string $name
 * @param string $address
 * @param string $location
 * @param string $phone
 * @param string $email
 * @param string $passwordHash
 * @return int Inserted hospital ID
 */
function shared_register_hospital(string $name, string $address, string $location, string $phone, string $email, string $passwordHash): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // SHARED TABLE: hospitals
        $stmtHosp = $pdo->prepare("INSERT INTO hospitals (name, address, location, phone) VALUES (?, ?, ?, ?)");
        $stmtHosp->execute([$name, $address, $location, $phone]);
        $hospitalId = (int)$pdo->lastInsertId();

        // Create hospital login credentials linked to hospital_id
        $stmtUser = $pdo->prepare("INSERT INTO hospital_users (hospital_id, email, password_hash) VALUES (?, ?, ?)");
        $stmtUser->execute([$hospitalId, $email, $passwordHash]);

        $pdo->commit();
        return $hospitalId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
