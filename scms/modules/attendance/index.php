<?php
session_start();
require_once '../../config/db.php';

// Strict Role-Based Access Control: Admin and Faculty can manage attendance
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'faculty')) { 
    header("Location: ../../auth/login.php"); 
    exit; 
}

$success = '';
$error = '';

// --- 0. DATABASE SELF-HEALING: Add Phone Column Automatically ---
try {
    $columnCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'");
    if ($columnCheck->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD phone VARCHAR(20) DEFAULT NULL AFTER email");
    }
} catch (PDOException $e) {
    // Failsafe ignore
}

// --- 1. HANDLE ADD NEW ATTENDANCE (Manual Entry) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_attendance'])) {
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_SANITIZE_NUMBER_INT);
    $course_id = filter_input(INPUT_POST, 'course_id', FILTER_SANITIZE_NUMBER_INT);
    $attended = filter_input(INPUT_POST, 'attended_classes', FILTER_SANITIZE_NUMBER_INT);
    $total = filter_input(INPUT_POST, 'total_classes', FILTER_SANITIZE_NUMBER_INT);

    if ($attended > $total) {
        $error = "Validation Error: Present classes cannot exceed Total classes.";
    } else {
        try {
            // Prevent duplicate records for the same student/course combo
            $check = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND course_id = ?");
            $check->execute([$student_id, $course_id]);
            
            if ($check->rowCount() > 0) {
                $error = "An attendance record for this student in this course already exists. Please edit the existing record.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO attendance (student_id, course_id, attended_classes, total_classes) VALUES (?, ?, ?, ?)");
                $stmt->execute([$student_id, $course_id, $attended, $total]);
                $success = "Attendance record logged successfully. Academic Risk Engine updated.";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// --- 2. HANDLE EDIT ATTENDANCE (Manual Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_attendance'])) {
    $attendance_id = filter_input(INPUT_POST, 'attendance_id', FILTER_SANITIZE_NUMBER_INT);
    $attended = filter_input(INPUT_POST, 'attended_classes', FILTER_SANITIZE_NUMBER_INT);
    $total = filter_input(INPUT_POST, 'total_classes', FILTER_SANITIZE_NUMBER_INT);

    if ($attended > $total) {
        $error = "Validation Error: Present classes cannot exceed Total classes.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE attendance SET attended_classes = ?, total_classes = ? WHERE id = ?");
            $stmt->execute([$attended, $total, $attendance_id]);
            $success = "Attendance record successfully updated.";
        } catch (PDOException $e) {
            $error = "Failed to update attendance record.";
        }
    }
}

// --- 3. HANDLE DELETE ATTENDANCE (Purge Record) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_attendance'])) {
    $attendance_id = filter_input(INPUT_POST, 'attendance_id', FILTER_SANITIZE_NUMBER_INT);
    try {
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ?");
        $stmt->execute([$attendance_id]);
        $success = "Attendance record permanently deleted.";
    } catch (PDOException $e) {
        $error = "Failed to delete attendance record.";
    }
}

// --- 4. HANDLE DAILY ROLL CALL SUBMISSION (Direct-Click Logic) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_roll_call'])) {
    $course_id = filter_input(INPUT_POST, 'course_id', FILTER_SANITIZE_NUMBER_INT);
    $all_students = $_POST['all_students'] ?? []; 
    $attendance_data = $_POST['attendance_status'] ?? []; 

    try {
        $pdo->beginTransaction();

        foreach ($all_students as $student_id) {
            $is_present = isset($attendance_data[$student_id]) ? 1 : 0; 

            $stmt = $pdo->prepare("SELECT id, total_classes, attended_classes FROM attendance WHERE student_id = ? AND course_id = ?");
            $stmt->execute([$student_id, $course_id]);
            $record = $stmt->fetch();

            if ($record) {
                $new_total = $record['total_classes'] + 1;
                $new_attended = $record['attended_classes'] + $is_present;
                
                $update = $pdo->prepare("UPDATE attendance SET total_classes = ?, attended_classes = ? WHERE id = ?");
                $update->execute([$new_total, $new_attended, $record['id']]);
            } else {
                $insert = $pdo->prepare("INSERT INTO attendance (student_id, course_id, total_classes, attended_classes) VALUES (?, ?, 1, ?)");
                $insert->execute([$student_id, $course_id, $is_present]);
            }
        }

        $pdo->commit();
        $success = "Daily Roll Call saved successfully! Absentees correctly tracked and Risk Engine updated.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to save roll call. Database error.";
    }
}

// --- 5. FETCH DATA FOR UI (Now includes Phone Numbers) ---
$coursesList = $pdo->query("SELECT id, name FROM courses ORDER BY name")->fetchAll();
$studentsList = $pdo->query("SELECT s.id, u.name, u.phone, s.enrollment_no FROM students s JOIN users u ON s.user_id = u.id ORDER BY u.name")->fetchAll();

$attendance_list = $pdo->query("
    SELECT a.id as attendance_id, a.student_id, a.course_id, u.name as student_name, u.phone, s.enrollment_no, c.name as course_name, a.total_classes, a.attended_classes 
    FROM attendance a 
    JOIN students s ON a.student_id = s.id 
    JOIN users u ON s.user_id = u.id 
    JOIN courses c ON a.course_id = c.id
    ORDER BY c.name ASC, u.name ASC
")->fetchAll();

// Calculate KPIs
$totalRecords = count($attendance_list);
$atRiskCount = 0;
$totalAttended = 0;
$totalPossible = 0;

foreach ($attendance_list as $rec) {
    if ($rec['total_classes'] > 0) {
        $pct = ($rec['attended_classes'] / $rec['total_classes']) * 100;
        if ($pct < 75) $atRiskCount++;
        $totalAttended += $rec['attended_classes'];
        $totalPossible += $rec['total_classes'];
    }
}
$avgSystemAttendance = $totalPossible > 0 ? round(($totalAttended / $totalPossible) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enterprise Attendance Management | SCMS ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/style.css" rel="stylesheet">
    <style>
        .attendance-toggle .form-check-input { width: 3em; height: 1.5em; cursor: pointer; }
        .attendance-toggle .form-check-input:checked { background-color: #10b981; border-color: #10b981; }
        .attendance-toggle .form-check-input:not(:checked) { background-color: #ef4444; border-color: #ef4444; }
        
        .search-wrapper .form-control:focus { box-shadow: none; border-color: #dee2e6; }
        .search-wrapper .input-group-text { background-color: #fff; border-right: none; }
        .search-wrapper .form-control { border-left: none; padding-left: 0; }
        
        /* Sortable Header Styling */
        th.sortable { cursor: pointer; transition: background-color 0.2s; user-select: none; }
        th.sortable:hover { background-color: #2c3034 !important; }
        .sort-icon { font-size: 0.8em; opacity: 0.5; transition: opacity 0.2s; }
        th.sortable:hover .sort-icon { opacity: 1; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark px-4 shadow-sm">
        <a class="navbar-brand fw-bold" href="../../dashboard/index.php">
            <i class="fa-solid fa-arrow-left me-2"></i> SCMS | Attendance Tracking
        </a>
    </nav>

    <div class="container-fluid px-4 mt-4 pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-dark"><i class="fa-solid fa-clipboard-user text-primary me-2"></i> Academic Attendance Master</h3>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary shadow-sm fw-bold px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#addAttendanceModal">
                    <i class="fa-solid fa-plus me-2"></i> Log Manual Entry
                </button>
                <button class="btn btn-success shadow-sm fw-bold px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#rollCallModal">
                    <i class="fa-solid fa-microphone-lines me-2"></i> Start Daily Roll Call
                </button>
            </div>
        </div>

        <?php if($success) echo "<div class='alert alert-success shadow-sm rounded-3 fw-bold'><i class='fa-solid fa-check-circle me-2'></i>$success</div>"; ?>
        <?php if($error) echo "<div class='alert alert-danger shadow-sm rounded-3 fw-bold'><i class='fa-solid fa-triangle-exclamation me-2'></i>$error</div>"; ?>

        <!-- Academic KPIs -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-primary border-4 h-100 rounded-4">
                    <div class="card-body">
                        <div class="text-muted small fw-bold text-uppercase mb-1">System-Wide Avg Attendance</div>
                        <h3 class="mb-0 fw-bold text-dark"><?= $avgSystemAttendance ?>%</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-danger border-4 h-100 rounded-4">
                    <div class="card-body">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Students At Risk (< 75%)</div>
                        <h3 class="mb-0 fw-bold text-danger"><?= $atRiskCount ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-success border-4 h-100 rounded-4">
                    <div class="card-body">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Total System Records</div>
                        <h3 class="mb-0 fw-bold text-dark"><?= $totalRecords ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aggregated View (Full CRUD Table + Search/Filter/Sort) -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-0 pt-4 pb-3">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-chart-line text-info me-2"></i> Cumulative Attendance Overview</h5>
                
                <!-- 🔥 REAL-TIME SEARCH & FILTER ENGINE 🔥 -->
                <div class="row g-3 bg-light p-3 rounded-3 border">
                    <div class="col-md-5">
                        <div class="input-group search-wrapper">
                            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="searchStudent" class="form-control" placeholder="Search by Student Name, Phone or ID...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-book text-muted"></i></span>
                            <select id="filterSubject" class="form-select border-start-0 ps-0">
                                <option value="all">All Subjects/Courses</option>
                                <?php foreach($coursesList as $cr): ?>
                                    <option value="<?= htmlspecialchars($cr['name']) ?>"><?= htmlspecialchars($cr['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-filter text-muted"></i></span>
                            <select id="filterStatus" class="form-select border-start-0 ps-0">
                                <option value="all">All Statuses</option>
                                <option value="safe">Safe Only (>= 75%)</option>
                                <option value="at risk">At Risk Only (< 75%)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="attendanceTable">
                        <thead class="table-dark">
                            <tr>
                                <!-- Clickable Sort Headers -->
                                <th class="ps-4 sortable" onclick="sortTable(0)">Student Details <i class="fa-solid fa-sort ms-1 sort-icon" id="sort-icon-0"></i></th>
                                <th class="sortable" onclick="sortTable(1)">Subject/Course <i class="fa-solid fa-sort ms-1 sort-icon" id="sort-icon-1"></i></th>
                                <th class="sortable" onclick="sortTable(2)">Detailed Metrics <i class="fa-solid fa-sort ms-1 sort-icon" id="sort-icon-2"></i></th>
                                <th class="sortable" onclick="sortTable(3)">Status <i class="fa-solid fa-sort ms-1 sort-icon" id="sort-icon-3"></i></th>
                                <th class="text-end pe-4">Manual Actions</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTableBody">
                            <?php foreach($attendance_list as $rec): 
                                $percent = ($rec['total_classes'] > 0) ? ($rec['attended_classes'] / $rec['total_classes']) * 100 : 0;
                                $absent_classes = $rec['total_classes'] - $rec['attended_classes'];
                                $isRisk = $percent < 75;
                            ?>
                            <tr class="attendance-row">
                                <td class="ps-4 student-info">
                                    <div class="fw-bold text-dark student-name"><?= htmlspecialchars($rec['student_name']) ?></div>
                                    <div class="small text-muted student-enrollment">
                                        <i class="fa-solid fa-id-badge me-1"></i><?= htmlspecialchars($rec['enrollment_no']) ?> | 
                                        <i class="fa-solid fa-phone me-1"></i><?= htmlspecialchars($rec['phone'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                <td class="fw-semibold text-secondary course-name"><?= htmlspecialchars($rec['course_name']) ?></td>
                                <td style="width: 320px;" class="metrics-cell" data-sort-val="<?= $percent ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="fw-bold text-success"><i class="fa-solid fa-check me-1"></i>Present: <?= $rec['attended_classes'] ?></small>
                                        <small class="fw-bold text-danger"><i class="fa-solid fa-xmark me-1"></i>Absent: <?= $absent_classes ?></small>
                                        <small class="fw-bold text-dark border-start ps-2 border-2">Total: <?= $rec['total_classes'] ?></small>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-1 mt-2">
                                        <small class="text-muted" style="font-size: 0.75rem;">Attendance Percentage</small>
                                        <small class="fw-bold <?= $isRisk ? 'text-danger' : 'text-success' ?>"><?= number_format($percent, 1) ?>%</small>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar <?= $isRisk ? 'bg-danger' : 'bg-success' ?>" role="progressbar" style="width: <?= $percent ?>%;"></div>
                                    </div>
                                </td>
                                <td class="status-cell" data-sort-val="<?= $isRisk ? '0' : '1' ?>">
                                    <?php if($isRisk): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger rounded-pill px-3 status-badge">At Risk</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success border border-success rounded-pill px-3 status-badge">Safe</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editAttendanceModal<?= $rec['attendance_id'] ?>" title="Edit Record">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this attendance record?');">
                                        <input type="hidden" name="attendance_id" value="<?= $rec['attendance_id'] ?>">
                                        <button type="submit" name="delete_attendance" class="btn btn-sm btn-outline-danger" title="Delete Record">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Attendance Modal (Manual Update with Auto-Calculation) -->
                            <div class="modal fade" id="editAttendanceModal<?= $rec['attendance_id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                        <div class="modal-header bg-primary text-white border-0 py-3">
                                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Attendance Record</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body p-4 bg-light">
                                                <input type="hidden" name="attendance_id" value="<?= $rec['attendance_id'] ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold text-muted">Student & Course</label>
                                                    <input type="text" class="form-control bg-white" value="<?= htmlspecialchars($rec['student_name']) ?> - <?= htmlspecialchars($rec['course_name']) ?>" readonly>
                                                </div>

                                                <!-- AUTO CALCULATING INPUTS -->
                                                <div class="row mb-3 align-items-end">
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-bold text-success"><i class="fa-solid fa-check me-1"></i> Present Classes</label>
                                                        <input type="number" name="attended_classes" id="edit_present_<?= $rec['attendance_id'] ?>" class="form-control border-success fs-5 fw-bold text-center" value="<?= $rec['attended_classes'] ?>" min="0" required oninput="calculateAbsent('<?= $rec['attendance_id'] ?>')">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-bold text-danger"><i class="fa-solid fa-xmark me-1"></i> Absent Classes</label>
                                                        <input type="text" id="edit_absent_<?= $rec['attendance_id'] ?>" class="form-control border-danger bg-danger-subtle text-danger fs-5 fw-bold text-center" value="<?= $absent_classes ?>" readonly title="Auto-Calculated">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-bold text-dark"><i class="fa-solid fa-list me-1"></i> Total Classes</label>
                                                        <input type="number" name="total_classes" id="edit_total_<?= $rec['attendance_id'] ?>" class="form-control border-dark fs-5 fw-bold text-center" value="<?= $rec['total_classes'] ?>" min="1" required oninput="calculateAbsent('<?= $rec['attendance_id'] ?>')">
                                                    </div>
                                                </div>
                                                <div class="alert alert-info py-2 mb-0 small border-0">
                                                    <i class="fa-solid fa-lightbulb me-1"></i> The <strong>Absent Classes</strong> count is automatically calculated to prevent human error.
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 p-4 pt-0 bg-light">
                                                <button type="button" class="btn btn-secondary px-4 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="edit_attendance" class="btn btn-primary px-4 rounded-pill fw-bold"><i class="fa-solid fa-floppy-disk me-2"></i>Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <tr id="noRecordsRow" style="display: <?= empty($attendance_list) ? 'table-row' : 'none' ?>;">
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-clipboard-user fa-3x mb-3 opacity-50"></i>
                                    <p class="mb-0 fw-bold" id="noRecordsText">No attendance records found matching your search.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 🔥 1-CLICK DAILY ROLL CALL MODAL 🔥 -->
    <div class="modal fade" id="rollCallModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-success text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-clipboard-list me-2"></i>Take Daily Roll Call</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4 bg-light">
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark">1. Select Course / Subject</label>
                            <select name="course_id" class="form-select form-select-lg border-success" required>
                                <option value="" disabled selected>-- Choose the class you are taking --</option>
                                <?php foreach($coursesList as $cr): ?>
                                    <option value="<?= $cr['id'] ?>"><?= htmlspecialchars($cr['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <label class="form-label fw-bold text-dark">2. Mark Students</label>
                        <p class="small text-muted mb-2">Switch to the Left (Red) for <span class="text-danger fw-bold">Absent</span>. Keep it on the Right (Green) for <span class="text-success fw-bold">Present</span>.</p>
                        <div class="card border-0 shadow-sm">
                            <ul class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                                <?php foreach($studentsList as $st): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                    <div>
                                        <span class="fw-bold text-dark d-block"><?= htmlspecialchars($st['name']) ?></span>
                                        <div class="small text-muted mt-1">
                                            <i class="fa-solid fa-id-badge me-1"></i><?= htmlspecialchars($st['enrollment_no']) ?> | 
                                            <i class="fa-solid fa-phone me-1"></i><?= htmlspecialchars($st['phone'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                    
                                    <!-- DIRECT ACTION TOGGLE -->
                                    <div class="form-check form-switch attendance-toggle fs-5">
                                        <!-- THIS HIDDEN FIELD FIXES THE ABSENTEE TRACKING -->
                                        <input type="hidden" name="all_students[]" value="<?= $st['id'] ?>"> 
                                        <input class="form-check-input" type="checkbox" name="attendance_status[<?= $st['id'] ?>]" value="present" checked title="Toggle Present/Absent">
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="alert alert-warning mt-3 small py-2 mb-0 border-0 fw-bold">
                            <i class="fa-solid fa-circle-info me-2"></i> Saving this will automatically add +1 to Total Classes. Absentees will correctly receive 0 Present marks.
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0 bg-light">
                        <button type="button" class="btn btn-secondary px-4 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_roll_call" class="btn btn-success px-5 rounded-pill fw-bold"><i class="fa-solid fa-floppy-disk me-2"></i>Save Daily Roll Call</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add New Attendance Modal (Manual Entry) -->
    <div class="modal fade" id="addAttendanceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-plus me-2"></i>Log Manual Attendance</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4 bg-light">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Select Student</label>
                            <select name="student_id" class="form-select border-primary" required>
                                <option value="" disabled selected>-- Search & Select Student --</option>
                                <?php foreach($studentsList as $st): ?>
                                    <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?> (<?= htmlspecialchars($st['enrollment_no']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Select Course/Subject</label>
                            <select name="course_id" class="form-select border-primary" required>
                                <option value="" disabled selected>-- Select Course --</option>
                                <?php foreach($coursesList as $cr): ?>
                                    <option value="<?= $cr['id'] ?>"><?= htmlspecialchars($cr['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- AUTO CALCULATING INPUTS -->
                        <div class="row mb-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-success"><i class="fa-solid fa-check me-1"></i> Present Classes</label>
                                <input type="number" name="attended_classes" id="add_present" class="form-control border-success fs-5 fw-bold text-center" placeholder="0" min="0" required oninput="calculateAbsent('add')">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-danger"><i class="fa-solid fa-xmark me-1"></i> Absent Classes</label>
                                <input type="text" id="add_absent" class="form-control border-danger bg-danger-subtle text-danger fs-5 fw-bold text-center" placeholder="0" readonly title="Auto-Calculated">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark"><i class="fa-solid fa-list me-1"></i> Total Classes</label>
                                <input type="number" name="total_classes" id="add_total" class="form-control border-dark fs-5 fw-bold text-center" placeholder="0" min="1" required oninput="calculateAbsent('add')">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0 bg-light">
                        <button type="button" class="btn btn-secondary px-4 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_attendance" class="btn btn-primary px-4 rounded-pill fw-bold"><i class="fa-solid fa-check me-2"></i>Submit Manual Log</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // 1. Smart Auto-Calculation Logic for Modals
        function calculateAbsent(prefix) {
            let presentInput = document.getElementById(prefix === 'add' ? 'add_present' : 'edit_present_' + prefix);
            let totalInput = document.getElementById(prefix === 'add' ? 'add_total' : 'edit_total_' + prefix);
            let absentInput = document.getElementById(prefix === 'add' ? 'add_absent' : 'edit_absent_' + prefix);
            
            let present = parseInt(presentInput.value) || 0;
            let total = parseInt(totalInput.value) || 0;
            
            if (present > total && total > 0) {
                absentInput.value = "Error: P > T";
                absentInput.classList.remove('bg-danger-subtle', 'text-danger');
                absentInput.classList.add('bg-warning', 'text-dark');
            } else if (total > 0) {
                absentInput.value = total - present;
                absentInput.classList.add('bg-danger-subtle', 'text-danger');
                absentInput.classList.remove('bg-warning', 'text-dark');
            } else {
                absentInput.value = 0;
            }
        }

        // 2. Real-Time Smart Filter Engine
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchStudent');
            const filterSubject = document.getElementById('filterSubject');
            const filterStatus = document.getElementById('filterStatus');
            const tableRows = document.querySelectorAll('#attendanceTableBody .attendance-row');
            const noRecordsRow = document.getElementById('noRecordsRow');

            function filterTable() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const subjectTerm = filterSubject.value.toLowerCase();
                const statusTerm = filterStatus.value.toLowerCase();
                let visibleCount = 0;

                tableRows.forEach(row => {
                    const studentText = row.querySelector('.student-info').textContent.toLowerCase();
                    const courseText = row.querySelector('.course-name').textContent.toLowerCase();
                    const statusText = row.querySelector('.status-badge').textContent.toLowerCase();

                    const matchesSearch = studentText.includes(searchTerm);
                    const matchesSubject = subjectTerm === 'all' || courseText.includes(subjectTerm);
                    const matchesStatus = statusTerm === 'all' || statusText.includes(statusTerm);

                    if (matchesSearch && matchesSubject && matchesStatus) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                noRecordsRow.style.display = (visibleCount === 0) ? 'table-row' : 'none';
            }

            searchInput.addEventListener('input', filterTable);
            filterSubject.addEventListener('change', filterTable);
            filterStatus.addEventListener('change', filterTable);
        });

        // 3. Click-to-Sort Engine
        let currentSortCol = -1;
        let currentSortAsc = true;

        function sortTable(columnIndex) {
            const tableBody = document.getElementById("attendanceTableBody");
            const rows = Array.from(tableBody.querySelectorAll("tr.attendance-row"));
            
            // Toggle Direction
            if (currentSortCol === columnIndex) {
                currentSortAsc = !currentSortAsc;
            } else {
                currentSortAsc = true;
                currentSortCol = columnIndex;
            }

            // Reset all icons visually
            document.querySelectorAll('.sort-icon').forEach(icon => {
                icon.className = 'fa-solid fa-sort ms-1 sort-icon text-muted';
            });
            
            // Highlight active sort icon
            const currentIcon = document.getElementById('sort-icon-' + columnIndex);
            if(currentIcon) {
                currentIcon.className = currentSortAsc ? 'fa-solid fa-sort-up ms-1 sort-icon text-white' : 'fa-solid fa-sort-down ms-1 sort-icon text-white';
            }

            // Perform Sorting
            rows.sort((rowA, rowB) => {
                let cellA = rowA.querySelectorAll("td")[columnIndex];
                let cellB = rowB.querySelectorAll("td")[columnIndex];
                
                let valA = cellA.getAttribute('data-sort-val') || cellA.textContent.trim().toLowerCase();
                let valB = cellB.getAttribute('data-sort-val') || cellB.textContent.trim().toLowerCase();

                // Numeric sort fallback
                let numA = parseFloat(valA);
                let numB = parseFloat(valB);
                if (!isNaN(numA) && !isNaN(numB)) {
                    valA = numA;
                    valB = numB;
                }

                if (valA < valB) return currentSortAsc ? -1 : 1;
                if (valA > valB) return currentSortAsc ? 1 : -1;
                return 0;
            });

            // Re-append sorted rows to the DOM
            rows.forEach(row => tableBody.appendChild(row));
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>