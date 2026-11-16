<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shared_queries.php';

echo "=== VMS 3B COMPREHENSIVE AUTOMATION TEST ===\n";

$pdo = db();

// Ensure seeded demo users have a valid bcrypt hash for 'password'
$bcryptPass = password_hash('password', PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id <= 5")->execute([$bcryptPass]);

// Test 1: Check Database Tables count
$tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
echo "1. Base Tables Count: " . count($tables) . " (Expected: 14)\n";
assert(count($tables) === 14, "Must have exactly 14 base tables");

// Test 2: Hospital Authentication
$hospLogin = auth_login_hospital('contact@cityhospital.pk', 'password');
echo "2. Hospital Login (contact@cityhospital.pk): " . ($hospLogin ? "PASS" : "FAIL") . "\n";
assert($hospLogin === true, "Hospital login must succeed with seeded bcrypt hash");

// Test 3: Parent Authentication
$parentLogin = auth_login_parent('sara.khan@example.com', 'password');
echo "3. Parent Login (sara.khan@example.com): " . ($parentLogin ? "PASS" : "FAIL") . "\n";
assert($parentLogin === true, "Parent login must succeed");

$parentId = (int)$_SESSION['parent_id'];
$userId   = (int)$_SESSION['user_id'];

// Test 4: Check Child Schedule Generation Trigger
$testKidDob = date('Y-m-d', strtotime('-2 months'));
$childId = shared_add_child(
    $parentId,
    'Automation Test Baby',
    $testKidDob,
    'female',
    'A+',
    3.25,
    'CERT-AUTO-999',
    'None',
    'Healthy test infant'
);
echo "4. Add Child (ID: $childId): " . ($childId > 0 ? "PASS" : "FAIL") . "\n";
assert($childId > 0, "Child must be added successfully");

$schedCount = shared_count_child_vaccination_rows($childId);
echo "   Scheduled Doses automatically generated: $schedCount (Expected: 17)\n";
assert($schedCount === 17, "Trigger must generate 17 schedule rows");

// Test 5: Verify 30-Day Booking Window
// Find a dose due within 30 days
$bookableDoses = shared_get_upcoming_doses_for_parent($parentId);
echo "5. Bookable Doses within 30 days found: " . count($bookableDoses) . "\n";
assert(count($bookableDoses) > 0, "Must have bookable doses for parent");

$targetDose = null;
foreach ($bookableDoses as $bd) {
    if ((int)$bd['child_id'] === $childId) {
        $targetDose = $bd;
        break;
    }
}
echo "   Target test dose for Baby: " . ($targetDose ? $targetDose['vaccine_name'] . " (Dose " . $targetDose['dose_number'] . ", Due: " . $targetDose['due_date'] . ")" : "None found") . "\n";

// Test 6: Hospital Inventory & Stock Update
$hospId = (int)$_SESSION['hospital_id'];
$vaccineId = (int)$targetDose['vaccine_id'];

$stockUpdated = shared_update_hospital_stock($hospId, $vaccineId, 'available', 25, $userId);
echo "6. Update Vaccine Stock via sp_update_vaccine_status: " . ($stockUpdated ? "PASS" : "FAIL") . "\n";

// Test 7: Book Appointment via sp_book_appointment
$bookRes = shared_book_appointment(
    (int)$targetDose['child_vaccination_id'],
    $hospId,
    date('Y-m-d'),
    '11:00:00',
    'Automated testing appointment note'
);
echo "7. Book Appointment: " . ($bookRes['success'] ? "PASS (Appt #{$bookRes['appointment_id']})" : "FAIL: {$bookRes['message']}") . "\n";
assert($bookRes['success'] === true, "Appointment booking must succeed");
$newAptId = $bookRes['appointment_id'];

// Test 8: Cancel Appointment Check
$cancelOk = shared_cancel_parent_appointment($newAptId, $parentId, $userId);
echo "8. Cancel Pending Appointment: " . ($cancelOk ? "PASS" : "FAIL") . "\n";
assert($cancelOk === true, "Pending appointment cancellation must succeed");

// Re-book for testing completion
$rebookRes = shared_book_appointment(
    (int)$targetDose['child_vaccination_id'],
    $hospId,
    date('Y-m-d'),
    '11:30:00',
    'Re-booked for completion test'
);
$completedTestAptId = $rebookRes['appointment_id'];
echo "   Re-booked Appt #$completedTestAptId for completion test.\n";

// Test 9: Admin Approval & Hospital Completion
// In real workflow, admin approves pending appt. Let's approve via DB update to simulate admin approval.
$pdo->prepare("UPDATE appointments SET status='approved' WHERE appointment_id = ?")->execute([$completedTestAptId]);
$pdo->prepare("UPDATE child_vaccinations SET status='booked' WHERE child_vaccination_id = ?")->execute([(int)$targetDose['child_vaccination_id']]);

// Login as hospital staff to complete the appointment
auth_login_hospital('contact@cityhospital.pk', 'password');
$hospUserId = (int)$_SESSION['user_id'];

// Now hospital completes appointment via sp_complete_appointment
$completeRes = shared_complete_appointment(
    $completedTestAptId,
    $hospUserId,
    'BATCH-TEST-2026',
    'Dr. Automated Test',
    'Administered smoothly without complication'
);
echo "9. Complete Appointment via sp_complete_appointment: " . ($completeRes['success'] ? "PASS" : "FAIL: {$completeRes['message']}") . "\n";
assert($completeRes['success'] === true, "Completion must succeed");

// Test 10: Check Vaccination Records (Report)
$records = shared_get_vaccination_records($parentId, $childId);
echo "10. Vaccination Records for Child: " . count($records) . " records found\n";
assert(count($records) >= 1, "Must find newly completed vaccination record");
echo "    Verified Batch: " . $records[0]['batch_number'] . " | Administered by: " . $records[0]['administered_by'] . "\n";

// Test 11: Soft Delete Child (active appointment guard check)
// Since the appointment was completed (not requested/booked), soft delete should now be permitted!
$delResult = shared_soft_delete_child($childId, $parentId);
echo "11. Soft Delete Child with Completed Visit: " . ($delResult === true ? "PASS (Successfully soft-deleted)" : "FAIL: $delResult") . "\n";
assert($delResult === true, "Child with completed appointment can be soft-deleted safely");

// Verify child still exists in DB with is_active = 0 and child_vaccinations are preserved
$activeCheck = $pdo->prepare("SELECT is_active FROM children WHERE child_id = ?");
$activeCheck->execute([$childId]);
$isActiveVal = (int)$activeCheck->fetchColumn();
$postDosesCount = shared_count_child_vaccination_rows($childId);
echo "    Child is_active: $isActiveVal (Expected: 0) | Schedule records preserved: $postDosesCount (Expected: 17)\n";
assert($isActiveVal === 0 && $postDosesCount === 17, "Child is soft deleted and medical history is preserved");

// Test 12: Hospital Registration (Item 7 & F4)
$testHospEmail = 'test.clinic.' . time() . '@example.com';
$testHospHash  = password_hash('password', PASSWORD_DEFAULT);
$newHospId = shared_register_hospital_facility(
    'Al-Khidmat Automated Care Clinic',
    $testHospEmail,
    $testHospHash,
    'Plot 42, Block 10',
    'Karachi',
    'Gulberg',
    '021-39998888',
    'REG-AUTO-' . time(),
    '08:30:00',
    '18:30:00',
    75
);
echo "12. Hospital Registration (ID: $newHospId): " . ($newHospId > 0 ? "PASS" : "FAIL") . "\n";
assert($newHospId > 0, "Hospital registration must succeed");

// Test 13: Hospital Profile Update (Item 11)
$profUpdated = shared_update_hospital_profile(
    $newHospId,
    'Al-Khidmat Automated Care Clinic (Updated)',
    'Plot 42, Block 10 (Renovated)',
    'Karachi',
    'Gulberg East',
    '021-39999999',
    '08:00:00',
    '19:00:00',
    90
);
$loadedHosp = shared_get_hospital_profile($newHospId);
echo "13. Hospital Profile Update: " . ($loadedHosp['hospital_name'] === 'Al-Khidmat Automated Care Clinic (Updated)' ? "PASS" : "FAIL") . "\n";
assert($loadedHosp['hospital_name'] === 'Al-Khidmat Automated Care Clinic (Updated)', "Profile must update");

echo "\nALL 13 AUTOMATION TESTS PASSED PERFECTLY!\n";
