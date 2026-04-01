<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../../auth/login.php"); 
    exit; 
}

$success = '';
$error = '';

// --- 1. DIRECT ACTION: BULK SEMESTER PROMOTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_promote'])) {
    $dept = filter_input(INPUT_POST, 'target_dept', FILTER_SANITIZE_STRING);
    $current_sem = filter_input(INPUT_POST, 'current_sem', FILTER_SANITIZE_NUMBER_INT);
    $next_sem = $current_sem + 1;
    try {
        $stmt = $pdo->prepare("UPDATE students SET semester = ? WHERE dept = ? AND semester = ?");
        $stmt->execute([$next_sem, $dept, $current_sem]);
        $affected = $stmt->rowCount();
        if ($affected > 0) $success = "Promoted $affected students in '$dept' to Semester $next_sem!";
        else $error = "No students found to promote.";
    } catch (PDOException $e) { $error = "Database Error."; }
}

// --- 2. MANUAL CRUD: ADD STUDENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $enrollment_no = filter_input(INPUT_POST, 'enrollment_no', FILTER_SANITIZE_STRING);
    $dept = filter_input(INPUT_POST, 'dept', FILTER_SANITIZE_STRING);
    $semester = filter_input(INPUT_POST, 'semester', FILTER_SANITIZE_NUMBER_INT);
    $default_password = password_hash('password123', PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();
        $stmt1 = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'student')");
        $stmt1->execute([$name, $email, $phone, $default_password]);
        $user_id = $pdo->lastInsertId();

        $stmt2 = $pdo->prepare("INSERT INTO students (user_id, enrollment_no, dept, semester) VALUES (?, ?, ?, ?)");
        $stmt2->execute([$user_id, $enrollment_no, $dept, $semester]);
        $pdo->commit();
        $success = "Student onboarded successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to add student. Ensure Email/Enrollment are unique.";
    }
}

// --- 3. MANUAL CRUD: EDIT STUDENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_SANITIZE_NUMBER_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $enrollment_no = filter_input(INPUT_POST, 'enrollment_no', FILTER_SANITIZE_STRING);
    $dept = filter_input(INPUT_POST, 'dept', FILTER_SANITIZE_STRING);
    $semester = filter_input(INPUT_POST, 'semester', FILTER_SANITIZE_NUMBER_INT);

    try {
        $pdo->beginTransaction();
        $stmt1 = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt1->execute([$name, $email, $phone, $user_id]);

        $stmt2 = $pdo->prepare("UPDATE students SET enrollment_no = ?, dept = ?, semester = ? WHERE id = ?");
        $stmt2->execute([$enrollment_no, $dept, $semester, $student_id]);
        $pdo->commit();
        $success = "Student profile manually updated.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to update profile.";
    }
}

// --- 4. DELETE STUDENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $success = "Student purged from the system.";
    } catch (PDOException $e) {}
}

// --- FETCH DATA ---
$students = $pdo->query("
    SELECT s.id as student_id, u.id as user_id, u.name, u.email, u.phone, s.enrollment_no, s.dept, s.semester 
    FROM students s JOIN users u ON s.user_id = u.id ORDER BY s.semester ASC, u.name ASC
")->fetchAll();

$distinctDepts = $pdo->query("SELECT DISTINCT dept FROM students")->fetchAll(PDO::FETCH_COLUMN);

// Calculate KPIs
$totalStudents = count($students);
$distinctDeptsCount = count($distinctDepts);
$avgSemester = $pdo->query("SELECT ROUND(AVG(semester), 1) FROM students")->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enterprise Student Management | SCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/style.css" rel="stylesheet">
    <style>
        .search-wrapper .form-control:focus { box-shadow: none; border-color: #dee2e6; }
        th.sortable { cursor: pointer; user-select: none; transition: background-color 0.2s; }
        th.sortable:hover { background-color: #2c3034 !important; }
        .sort-icon { font-size: 0.8em; opacity: 0.5; transition: opacity 0.2s; }
        th.sortable:hover .sort-icon { opacity: 1; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark px-4 shadow-sm">
        <a class="navbar-brand fw-bold" href="../../dashboard/index.php"><i class="fa-solid fa-arrow-left me-2"></i> SCMS | Student Directory</a>
    </nav>

    <div class="container-fluid px-4 mt-4 pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-dark"><i class="fa-solid fa-users text-primary me-2"></i> Student Enrollment Master</h3>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-success fw-bold rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#bulkPromoteModal"><i class="fa-solid fa-forward-step me-2"></i> Bulk Promote</button>
                <button class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal"><i class="fa-solid fa-user-plus me-2"></i> Onboard Student</button>
            </div>
        </div>

        <?php if($success) echo "<div class='alert alert-success shadow-sm fw-bold rounded-3'><i class='fa-solid fa-check-circle me-2'></i>$success</div>"; ?>
        <?php if($error) echo "<div class='alert alert-danger shadow-sm fw-bold rounded-3'><i class='fa-solid fa-triangle-exclamation me-2'></i>$error</div>"; ?>

        <!-- Demographics KPIs -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-primary border-4 h-100 rounded-4">
                    <div class="card-body">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Total Active Enrollments</div>
                        <h3 class="mb-0 fw-bold text-dark"><?= $totalStudents ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-info border-4 h-100 rounded-4">
                    <div class="card-body">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Academic Departments</div>
                        <h3 class="mb-0 fw-bold text-dark"><?= $distinctDeptsCount ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-success border-4 h-100 rounded-4">
                    <div class="card-body">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Average Progress (Semester)</div>
                        <h3 class="mb-0 fw-bold text-dark">Sem <?= $avgSemester ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-0 pt-4 pb-3">
                <div class="row g-3 bg-light p-3 rounded-3 border">
                    <div class="col-md-5">
                        <div class="input-group search-wrapper">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="searchData" class="form-control border-start-0 ps-0" placeholder="Search Name, Phone, or ID...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-building text-muted"></i></span>
                            <select id="filterDept" class="form-select border-start-0 ps-0">
                                <option value="all">All Departments</option>
                                <?php foreach($distinctDepts as $d): ?><option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-layer-group text-muted"></i></span>
                            <select id="filterSem" class="form-select border-start-0 ps-0">
                                <option value="all">All Semesters</option>
                                <?php for($i=1; $i<=8; $i++): ?><option value="sem <?= $i ?>">Semester <?= $i ?></option><?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-4 sortable" onclick="sortTable(0)">Enrollment No <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-0"></i></th>
                                <th class="sortable" onclick="sortTable(1)">Student Info <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-1"></i></th>
                                <th class="sortable" onclick="sortTable(2)">Department <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-2"></i></th>
                                <th class="sortable" onclick="sortTable(3)">Semester <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-3"></i></th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dataTableBody">
                            <?php foreach($students as $st): ?>
                            <tr class="data-row">
                                <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($st['enrollment_no']) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($st['name']) ?></div>
                                    <div class="small text-muted"><i class="fa-solid fa-phone me-1"></i><?= htmlspecialchars($st['phone'] ?? 'N/A') ?> | <i class="fa-solid fa-envelope ms-1 me-1"></i><?= htmlspecialchars($st['email']) ?></div>
                                </td>
                                <td class="search-dept"><span class="badge bg-secondary-subtle text-secondary px-3 py-2 border shadow-sm"><?= htmlspecialchars($st['dept']) ?></span></td>
                                <td class="fw-bold search-sem">Sem <?= htmlspecialchars($st['semester']) ?></td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-primary rounded-circle me-1" style="width: 32px; height: 32px; padding: 0;" data-bs-toggle="modal" data-bs-target="#editStudentModal<?= $st['student_id'] ?>" title="Manual Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('WARNING: Are you sure you want to permanently delete this student? This wipes ALL marks, attendance, and fee records!');">
                                        <input type="hidden" name="user_id" value="<?= $st['user_id'] ?>">
                                        <button type="submit" name="delete_student" class="btn btn-sm btn-outline-danger border-0 rounded-circle" style="width: 32px; height: 32px; padding: 0;" title="Purge Record">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editStudentModal<?= $st['student_id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                        <div class="modal-header bg-dark text-white border-0 py-3">
                                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-pen me-2"></i>Manual Profile Override</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body p-4 bg-light">
                                                <input type="hidden" name="user_id" value="<?= $st['user_id'] ?>"><input type="hidden" name="student_id" value="<?= $st['student_id'] ?>">
                                                <div class="row mb-3">
                                                    <div class="col-md-4"><label class="form-label fw-bold">Full Name</label><input type="text" name="name" class="form-control border-primary" value="<?= htmlspecialchars($st['name']) ?>" required></div>
                                                    <div class="col-md-4"><label class="form-label fw-bold">Email Address</label><input type="email" name="email" class="form-control border-primary" value="<?= htmlspecialchars($st['email']) ?>" required></div>
                                                    <div class="col-md-4"><label class="form-label fw-bold">Phone Number</label><input type="text" name="phone" class="form-control border-primary" value="<?= htmlspecialchars($st['phone'] ?? '') ?>"></div>
                                                </div>
                                                <div class="row mb-3">
                                                    <div class="col-md-4"><label class="form-label fw-bold">Enrollment No</label><input type="text" name="enrollment_no" class="form-control border-primary" value="<?= htmlspecialchars($st['enrollment_no']) ?>" required></div>
                                                    <div class="col-md-4"><label class="form-label fw-bold">Department</label><input type="text" name="dept" class="form-control border-primary" value="<?= htmlspecialchars($st['dept']) ?>" required></div>
                                                    <div class="col-md-4"><label class="form-label fw-bold">Semester</label><input type="number" name="semester" class="form-control border-primary" min="1" max="8" value="<?= $st['semester'] ?>" required></div>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light border-0 p-4 pt-0">
                                                <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="edit_student" class="btn btn-primary rounded-pill px-5 fw-bold"><i class="fa-solid fa-floppy-disk me-2"></i> Save Override</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <tr id="noRecordsRow" style="display: <?= empty($students) ? 'table-row' : 'none' ?>;">
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-users-slash fa-3x mb-3 opacity-50"></i>
                                    <p class="mb-0 fw-bold" id="noRecordsText">No students found matching your search criteria.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bulkPromoteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-success text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-forward-step me-2"></i>Bulk Semester Promotion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" onsubmit="return confirm('This will instantly promote all selected students to the next semester. Are you absolutely sure?');">
                    <div class="modal-body p-4 bg-light">
                        <p class="text-muted small mb-4">Select a target demographic. The system will automatically upgrade all students matching this criteria to their next semester level.</p>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Target Department</label>
                            <select name="target_dept" class="form-select border-success" required>
                                <option value="" disabled selected>-- Select --</option>
                                <?php foreach($distinctDepts as $d): ?><option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Current Semester</label>
                            <input type="number" name="current_sem" class="form-control border-success" min="1" max="7" placeholder="e.g. 1" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light p-4 pt-0">
                        <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="bulk_promote" class="btn btn-success rounded-pill px-5 fw-bold"><i class="fa-solid fa-bolt me-2"></i> Execute Promotion</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus me-2"></i>Manual Student Onboarding</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4 bg-light">
                        <div class="row mb-3">
                            <div class="col-md-4"><label class="form-label fw-bold text-dark">Full Name</label><input type="text" name="name" class="form-control border-primary" placeholder="John Doe" required></div>
                            <div class="col-md-4"><label class="form-label fw-bold text-dark">Email</label><input type="email" name="email" class="form-control border-primary" placeholder="john@scms.edu" required></div>
                            <div class="col-md-4"><label class="form-label fw-bold text-dark">Phone Number</label><input type="text" name="phone" class="form-control border-primary" placeholder="+91..."></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4"><label class="form-label fw-bold text-dark">Enrollment No</label><input type="text" name="enrollment_no" class="form-control border-primary" placeholder="CS2026..." required></div>
                            <div class="col-md-4"><label class="form-label fw-bold text-dark">Department</label><input type="text" name="dept" class="form-control border-primary" placeholder="Computer Science" required></div>
                            <div class="col-md-4"><label class="form-label fw-bold text-dark">Semester</label><input type="number" name="semester" class="form-control border-primary" min="1" max="8" value="1" required></div>
                        </div>
                        <div class="alert alert-info py-2 mb-0 mt-3 small rounded-3 border-0">
                            <i class="fa-solid fa-circle-info me-2"></i> Initial login password will automatically be set to: <strong>password123</strong>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light p-4 pt-0">
                        <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_student" class="btn btn-primary rounded-pill px-5 fw-bold"><i class="fa-solid fa-user-check me-2"></i> Complete Registration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Real-Time Search & Filter Engine
        document.getElementById('searchData').addEventListener('input', filterData);
        document.getElementById('filterDept').addEventListener('change', filterData);
        document.getElementById('filterSem').addEventListener('change', filterData);

        function filterData() {
            const term = document.getElementById('searchData').value.toLowerCase();
            const dept = document.getElementById('filterDept').value.toLowerCase();
            const sem = document.getElementById('filterSem').value.toLowerCase();
            let visibleCount = 0;
            
            document.querySelectorAll('.data-row').forEach(row => {
                const text = row.innerText.toLowerCase();
                const rowDept = row.querySelector('.search-dept').innerText.toLowerCase();
                const rowSem = row.querySelector('.search-sem').innerText.toLowerCase();
                
                if(text.includes(term) && (dept === 'all' || rowDept.includes(dept)) && (sem === 'all' || rowSem === sem)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Show "No Records" if empty
            document.getElementById('noRecordsRow').style.display = (visibleCount === 0) ? 'table-row' : 'none';
        }

        // Universal Click-to-Sort Engine
        let sortAsc = true;
        let currentSortCol = -1;
        function sortTable(col) {
            const tbody = document.getElementById("dataTableBody");
            const rows = Array.from(tbody.querySelectorAll("tr.data-row"));
            
            // Toggle sort direction
            if (currentSortCol === col) {
                sortAsc = !sortAsc;
            } else {
                sortAsc = true;
                currentSortCol = col;
            }

            // Reset visual icons
            document.querySelectorAll('.sort-icon').forEach(icon => {
                icon.className = 'fa-solid fa-sort ms-1 sort-icon text-muted';
            });
            
            // Set active visual icon
            const activeIcon = document.getElementById('sort-icon-' + col);
            if(activeIcon) {
                activeIcon.className = sortAsc ? 'fa-solid fa-sort-up ms-1 sort-icon text-dark' : 'fa-solid fa-sort-down ms-1 sort-icon text-dark';
            }

            rows.sort((a, b) => {
                let vA = a.querySelectorAll("td")[col].innerText.trim().toLowerCase();
                let vB = b.querySelectorAll("td")[col].innerText.trim().toLowerCase();
                
                // Smart Fallback for numeric values like "Sem 5"
                let numA = parseFloat(vA.replace(/[^0-9.-]+/g,""));
                let numB = parseFloat(vB.replace(/[^0-9.-]+/g,""));
                if (!isNaN(numA) && !isNaN(numB) && vA.includes("sem")) {
                    vA = numA;
                    vB = numB;
                }

                return vA < vB ? (sortAsc ? -1 : 1) : (vA > vB ? (sortAsc ? 1 : -1) : 0);
            });
            rows.forEach(r => tbody.appendChild(r));
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>