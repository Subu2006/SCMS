<?php
session_start();
require_once '../../config/db.php';

// Strict Role-Based Access Control: Only Admins can manipulate faculty records
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../../auth/login.php"); 
    exit; 
}

$success = '';
$error = '';

// --- 1. HANDLE ADD NEW FACULTY ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_faculty'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
    
    // Secure Hash for default password
    $default_password = password_hash('password123', PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();
        
        // Step 1: Insert into core users table
        $stmt1 = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'faculty')");
        $stmt1->execute([$name, $email, $phone, $default_password]);
        $user_id = $pdo->lastInsertId();

        // Step 2: Insert into faculty profile table
        $stmt2 = $pdo->prepare("INSERT INTO faculty (user_id, department) VALUES (?, ?)");
        $stmt2->execute([$user_id, $department]);
        
        $pdo->commit();
        $success = "Faculty onboarded successfully. Default password: password123";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to add faculty. Email might already exist in the system.";
    }
}

// --- 2. HANDLE EDIT FACULTY ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_faculty'])) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    $faculty_id = filter_input(INPUT_POST, 'faculty_id', FILTER_SANITIZE_NUMBER_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);

    try {
        $pdo->beginTransaction();
        
        // Update users table
        $stmt1 = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ? AND role = 'faculty'");
        $stmt1->execute([$name, $email, $phone, $user_id]);

        // Update faculty table
        $stmt2 = $pdo->prepare("UPDATE faculty SET department = ? WHERE id = ?");
        $stmt2->execute([$department, $faculty_id]);
        
        $pdo->commit();
        $success = "Faculty profile updated successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to update profile. Email might be in use by another account.";
    }
}

// --- 3. HANDLE DELETE FACULTY ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_faculty'])) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    try {
        // Cascading delete triggered via 3NF schema constraints
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'faculty'");
        $stmt->execute([$user_id]);
        $success = "Faculty record securely terminated and wiped from the directory.";
    } catch (PDOException $e) {
        $error = "Failed to delete faculty record. They may be linked to active courses.";
    }
}

// --- 4. FETCH DATA FOR UI ---
$faculty_list = $pdo->query("
    SELECT f.id as faculty_id, u.id as user_id, u.name, u.email, u.phone, f.department 
    FROM faculty f 
    JOIN users u ON f.user_id = u.id
    ORDER BY f.department ASC, u.name ASC
")->fetchAll();

$distinctDepts = $pdo->query("SELECT DISTINCT department FROM faculty")->fetchAll(PDO::FETCH_COLUMN);

// Calculate HR KPIs
$totalFaculty = count($faculty_list);
$distinctDeptsCount = count($distinctDepts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enterprise HR & Faculty Management - SCMS</title>
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
    <!-- Top Navigation -->
    <nav class="navbar navbar-dark bg-dark px-4 shadow-sm">
        <a class="navbar-brand fw-bold" href="../../dashboard/index.php">
            <i class="fa-solid fa-arrow-left me-2"></i> SCMS | HR & Faculty Directory
        </a>
    </nav>

    <div class="container-fluid px-4 mt-4 pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-dark"><i class="fa-solid fa-chalkboard-user text-primary me-2"></i> Faculty Directory & Management</h3>
            <button class="btn btn-primary shadow-sm fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addFacultyModal">
                <i class="fa-solid fa-user-plus me-2"></i> Onboard New Faculty
            </button>
        </div>

        <!-- HR KPIs -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm border-start border-primary border-4 h-100 rounded-4">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase mb-1">Active Faculty Members</div>
                            <h3 class="mb-0 fw-bold text-dark"><?= $totalFaculty ?></h3>
                        </div>
                        <i class="fa-solid fa-user-tie fa-3x text-primary opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm border-start border-success border-4 h-100 rounded-4">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase mb-1">Covered Academic Departments</div>
                            <h3 class="mb-0 fw-bold text-dark"><?= $distinctDeptsCount ?></h3>
                        </div>
                        <i class="fa-solid fa-building-columns fa-3x text-success opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <?php if($success) echo "<div class='alert alert-success shadow-sm rounded-3 fw-bold'><i class='fa-solid fa-check-circle me-2'></i>$success</div>"; ?>
        <?php if($error) echo "<div class='alert alert-danger shadow-sm rounded-3 fw-bold'><i class='fa-solid fa-triangle-exclamation me-2'></i>$error</div>"; ?>

        <!-- Main Data Table with Search and Sort -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-0 pt-4 pb-3">
                <div class="row g-3 bg-light p-3 rounded-3 border">
                    <div class="col-md-7">
                        <div class="input-group search-wrapper">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="searchData" class="form-control border-start-0 ps-0" placeholder="Search by Name, Email, Phone, or ID...">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-building text-muted"></i></span>
                            <select id="filterDept" class="form-select border-start-0 ps-0">
                                <option value="all">All Departments</option>
                                <?php foreach($distinctDepts as $d): ?>
                                    <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                                <?php endforeach; ?>
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
                                <th class="ps-4 sortable" onclick="sortTable(0)">Staff ID <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-0"></i></th>
                                <th class="sortable" onclick="sortTable(1)">Faculty Profile <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-1"></i></th>
                                <th class="sortable" onclick="sortTable(2)">Contact Info <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-2"></i></th>
                                <th class="sortable" onclick="sortTable(3)">Assigned Department <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-3"></i></th>
                                <th class="text-end pe-4">HR Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dataTableBody">
                            <?php foreach($faculty_list as $prof): ?>
                            <tr class="data-row">
                                <td class="ps-4 fw-bold text-secondary">#EMP-<?= str_pad($prof['faculty_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary-subtle text-primary rounded-circle d-flex justify-content-center align-items-center fw-bold me-3 shadow-sm" style="width: 40px; height: 40px;">
                                            <?= strtoupper(substr($prof['name'], 0, 1)) ?>
                                        </div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($prof['name']) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small text-muted"><i class="fa-solid fa-phone me-1"></i><?= htmlspecialchars($prof['phone'] ?? 'N/A') ?></div>
                                    <div class="small text-muted"><i class="fa-solid fa-envelope me-1"></i><?= htmlspecialchars($prof['email']) ?></div>
                                </td>
                                <td class="search-dept"><span class="badge bg-dark-subtle text-dark px-3 py-2 border shadow-sm"><?= htmlspecialchars($prof['department']) ?></span></td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-primary rounded-circle me-1" style="width: 32px; height: 32px; padding: 0;" data-bs-toggle="modal" data-bs-target="#editFacultyModal<?= $prof['faculty_id'] ?>" title="Edit Profile">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('WARNING: Are you sure you want to terminate this faculty record? This will delete their system access and unassign them from courses!');">
                                        <input type="hidden" name="user_id" value="<?= $prof['user_id'] ?>">
                                        <button type="submit" name="delete_faculty" class="btn btn-sm btn-outline-danger border-0 rounded-circle" style="width: 32px; height: 32px; padding: 0;" title="Purge Record">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Faculty Modal -->
                            <div class="modal fade" id="editFacultyModal<?= $prof['faculty_id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                        <div class="modal-header bg-dark text-white border-0 py-3">
                                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-pen me-2"></i>Update Faculty Profile</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body p-4 bg-light">
                                                <input type="hidden" name="user_id" value="<?= $prof['user_id'] ?>">
                                                <input type="hidden" name="faculty_id" value="<?= $prof['faculty_id'] ?>">
                                                
                                                <div class="row mb-3">
                                                    <div class="col-md-12">
                                                        <label class="form-label fw-semibold text-dark">Full Name (with Title)</label>
                                                        <input type="text" name="name" class="form-control border-primary" value="<?= htmlspecialchars($prof['name']) ?>" required>
                                                    </div>
                                                </div>
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold text-dark">Email Address</label>
                                                        <input type="email" name="email" class="form-control border-primary" value="<?= htmlspecialchars($prof['email']) ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold text-dark">Phone Number</label>
                                                        <input type="text" name="phone" class="form-control border-primary" value="<?= htmlspecialchars($prof['phone'] ?? '') ?>">
                                                    </div>
                                                </div>
                                                <div class="row mb-3">
                                                    <div class="col-md-12">
                                                        <label class="form-label fw-semibold text-dark">Assigned Department</label>
                                                        <select name="department" class="form-select border-primary" required>
                                                            <option value="Computer Science" <?= $prof['department'] == 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                                                            <option value="Information Tech" <?= $prof['department'] == 'Information Tech' ? 'selected' : '' ?>>Information Tech</option>
                                                            <option value="Electronics" <?= $prof['department'] == 'Electronics' ? 'selected' : '' ?>>Electronics</option>
                                                            <option value="Mechanical" <?= $prof['department'] == 'Mechanical' ? 'selected' : '' ?>>Mechanical</option>
                                                            <option value="Business Admin" <?= $prof['department'] == 'Business Admin' ? 'selected' : '' ?>>Business Admin</option>
                                                            <option value="General" <?= $prof['department'] == 'General' ? 'selected' : '' ?>>General Core / Applied Sciences</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 p-4 pt-0 bg-light">
                                                <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="edit_faculty" class="btn btn-primary rounded-pill px-5 fw-bold"><i class="fa-solid fa-floppy-disk me-2"></i> Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <tr id="noRecordsRow" style="display: <?= empty($faculty_list) ? 'table-row' : 'none' ?>;">
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-users-slash fa-3x mb-3 opacity-50"></i>
                                    <p class="mb-0 fw-bold" id="noRecordsText">No active faculty found matching your search.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add New Faculty Modal -->
    <div class="modal fade" id="addFacultyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus me-2"></i>Onboard New Faculty</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4 bg-light">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold text-dark">Full Name (with Title)</label>
                                <input type="text" name="name" class="form-control border-primary" placeholder="e.g. Dr. Alan Turing" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Official Email Address</label>
                                <input type="email" name="email" class="form-control border-primary" placeholder="faculty@scms.edu" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Phone Number</label>
                                <input type="text" name="phone" class="form-control border-primary" placeholder="+91...">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold text-dark">Assigned Department</label>
                                <select name="department" class="form-select border-primary" required>
                                    <option value="" disabled selected>Select Department</option>
                                    <option value="Computer Science">Computer Science</option>
                                    <option value="Information Tech">Information Tech</option>
                                    <option value="Electronics">Electronics</option>
                                    <option value="Mechanical">Mechanical</option>
                                    <option value="Business Admin">Business Admin</option>
                                    <option value="General">General Core / Applied Sciences</option>
                                </select>
                            </div>
                        </div>
                        <div class="alert alert-info py-2 mb-0 mt-3 small rounded-3 border-0">
                            <i class="fa-solid fa-circle-info me-2"></i> Initial login password will automatically be set to: <strong>password123</strong>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0 bg-light">
                        <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_faculty" class="btn btn-primary rounded-pill px-5 fw-bold"><i class="fa-solid fa-user-check me-2"></i> Complete Onboarding</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Real-Time Search & Filter Engine
        document.getElementById('searchData').addEventListener('input', filterData);
        document.getElementById('filterDept').addEventListener('change', filterData);

        function filterData() {
            const term = document.getElementById('searchData').value.toLowerCase();
            const dept = document.getElementById('filterDept').value.toLowerCase();
            let visibleCount = 0;
            
            document.querySelectorAll('.data-row').forEach(row => {
                const text = row.innerText.toLowerCase();
                const rowDept = row.querySelector('.search-dept').innerText.toLowerCase();
                
                if(text.includes(term) && (dept === 'all' || rowDept.includes(dept))) {
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
                
                // Numeric Fallback for IDs like #EMP-0001
                let numA = parseFloat(vA.replace(/[^0-9.-]+/g,""));
                let numB = parseFloat(vB.replace(/[^0-9.-]+/g,""));
                if (!isNaN(numA) && !isNaN(numB) && vA.includes("emp")) {
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