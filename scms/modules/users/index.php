<?php
session_start();
require_once '../../config/db.php';

// Strict Admin Security
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

$success = '';
$error = '';

// --- 0. DATABASE SELF-HEALING ---
try {
    $columnCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'");
    if ($columnCheck->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD phone VARCHAR(20) DEFAULT NULL AFTER email");
    }
} catch (PDOException $e) {}

// --- 1. CREATE: QUICK REGISTRATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_reg'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $phone, $password, $role]);
        $new_user_id = $pdo->lastInsertId();

        if ($role === 'student') {
            $enrollment = "REG" . time(); 
            $stmt2 = $pdo->prepare("INSERT INTO students (user_id, enrollment_no, dept, semester) VALUES (?, ?, 'Unassigned', 1)");
            $stmt2->execute([$new_user_id, $enrollment]);
        } elseif ($role === 'faculty') {
            $stmt2 = $pdo->prepare("INSERT INTO faculty (user_id, department) VALUES (?, 'General')");
            $stmt2->execute([$new_user_id]);
        } elseif ($role === 'staff') {
            $stmt2 = $pdo->prepare("INSERT INTO staff_profiles (user_id, designation, department) VALUES (?, 'Executive', 'Administration')");
            $stmt2->execute([$new_user_id]);
        }

        $pdo->commit();
        $success = "User [$name] registered as $role successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Registration Failed: Email might already be registered.";
    }
}

// --- 2. UPDATE: EDIT USER DETAILS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $uid = filter_input(INPUT_POST, 'edit_user_id', FILTER_SANITIZE_NUMBER_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $new_password = $_POST['new_password'];

    try {
        if (!empty($new_password)) {
            $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $hashed_pass, $uid]);
            $success = "User profile and password updated successfully.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $uid]);
            $success = "User profile updated successfully.";
        }
    } catch (PDOException $e) {
        $error = "Failed to update user. Email may be in use.";
    }
}

// --- 3. 1-CLICK PASSWORD RESET ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_reset'])) {
    $uid = filter_input(INPUT_POST, 'target_user_id', FILTER_SANITIZE_NUMBER_INT);
    try {
        $hashed_pass = password_hash('password123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_pass, $uid]);
        $success = "Password instantly reset to 'password123'.";
    } catch (PDOException $e) {
        $error = "Failed to quick-reset password.";
    }
}

// --- 4. DELETE USER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $uid = filter_input(INPUT_POST, 'target_user_id', FILTER_SANITIZE_NUMBER_INT);
    if ($uid == $_SESSION['user_id']) {
        $error = "Security Error: You cannot delete your own admin account.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $success = "User permanently purged.";
        } catch (PDOException $e) {}
    }
}

// --- 5. FETCH DIRECTORY ---
$users = $pdo->query("SELECT id, name, email, phone, role, created_at FROM users ORDER BY role ASC, name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Identity Management | SCMS ERP</title>
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
        <a class="navbar-brand fw-bold" href="../../dashboard/index.php"><i class="fa-solid fa-arrow-left me-2"></i> SCMS | Identity Management</a>
    </nav>

    <div class="container-fluid px-4 mt-4 pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-0"><i class="fa-solid fa-users-gear text-primary me-2"></i> Global User Control</h2>
            </div>
            <button class="btn btn-primary shadow-sm fw-bold px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#quickRegModal">
                <i class="fa-solid fa-user-plus me-2"></i> Quick Registration
            </button>
        </div>

        <?php if($success) echo "<div class='alert alert-success shadow-sm fw-bold rounded-3'>$success</div>"; ?>
        <?php if($error) echo "<div class='alert alert-danger shadow-sm fw-bold rounded-3'>$error</div>"; ?>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-0 pt-4 pb-3">
                <div class="row g-3 bg-light p-3 rounded-3 border">
                    <div class="col-md-8">
                        <div class="input-group search-wrapper">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="searchData" class="form-control border-start-0 ps-0" placeholder="Search by Name, Email, or Phone...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select id="filterRole" class="form-select">
                            <option value="all">All Roles</option>
                            <option value="admin">Administrators</option>
                            <option value="faculty">Faculty</option>
                            <option value="student">Students</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-4 sortable" onclick="sortTable(0)">User Details <i class="fa-solid fa-sort ms-1 sort-icon" id="sort-icon-0"></i></th>
                                <th class="sortable" onclick="sortTable(1)">Contact Info <i class="fa-solid fa-sort ms-1 sort-icon" id="sort-icon-1"></i></th>
                                <th class="sortable" onclick="sortTable(2)">Role <i class="fa-solid fa-sort ms-1 sort-icon" id="sort-icon-2"></i></th>
                                <th class="sortable" onclick="sortTable(3)">Joined <i class="fa-solid fa-sort ms-1 sort-icon" id="sort-icon-3"></i></th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dataTableBody">
                            <?php foreach($users as $u): 
                                $roleColor = ['admin'=>'bg-danger', 'faculty'=>'bg-primary', 'student'=>'bg-success', 'staff'=>'bg-warning text-dark'][$u['role']] ?? 'bg-secondary';
                            ?>
                            <tr class="data-row">
                                <td class="ps-4">
                                    <div class="fw-bold text-dark search-name"><?= htmlspecialchars($u['name']) ?></div>
                                </td>
                                <td>
                                    <div class="small text-muted search-email"><i class="fa-solid fa-envelope me-1"></i><?= htmlspecialchars($u['email']) ?></div>
                                    <div class="small text-muted fw-semibold search-phone"><i class="fa-solid fa-phone me-1"></i><?= htmlspecialchars($u['phone'] ?? 'N/A') ?></div>
                                </td>
                                <td class="search-role" data-sort-val="<?= $u['role'] ?>"><span class="badge <?= $roleColor ?> rounded-pill px-3 text-uppercase shadow-sm"><?= $u['role'] ?></span></td>
                                <td class="small text-muted" data-sort-val="<?= strtotime($u['created_at']) ?>"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                                <td class="text-end pe-4">
                                    <!-- 1-Click Reset -->
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Reset password to password123?');">
                                        <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="quick_reset" class="btn btn-sm btn-outline-warning rounded-pill px-3 fw-bold" title="Reset Password"><i class="fa-solid fa-rotate-left me-1"></i> Reset</button>
                                    </form>
                                    <button class="btn btn-sm btn-outline-primary rounded-circle mx-1" style="width: 32px; height: 32px; padding: 0;" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['id'] ?>" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                                    <?php if($u['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete user permanently?');">
                                        <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="delete_user" class="btn btn-sm btn-outline-danger rounded-circle" style="width: 32px; height: 32px; padding: 0;" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- EDIT MODAL -->
                            <div class="modal fade" id="editUserModal<?= $u['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                        <div class="modal-header bg-dark text-white border-0 py-3">
                                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-pen me-2"></i>Edit User Profile</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body p-4 bg-light">
                                                <input type="hidden" name="edit_user_id" value="<?= $u['id'] ?>">
                                                <div class="mb-3"><label class="form-label fw-bold">Full Name</label><input type="text" name="name" class="form-control border-primary" value="<?= htmlspecialchars($u['name']) ?>" required></div>
                                                <div class="row mb-3">
                                                    <div class="col-md-6"><label class="form-label fw-bold">Email</label><input type="email" name="email" class="form-control border-primary" value="<?= htmlspecialchars($u['email']) ?>" required></div>
                                                    <div class="col-md-6"><label class="form-label fw-bold">Phone Number</label><input type="text" name="phone" class="form-control border-primary" value="<?= htmlspecialchars($u['phone'] ?? '') ?>"></div>
                                                </div>
                                                <hr>
                                                <div class="mb-3"><label class="form-label fw-bold text-danger"><i class="fa-solid fa-key me-2"></i>Custom Password Reset</label><input type="password" name="new_password" class="form-control border-danger" placeholder="Leave blank to keep current"></div>
                                            </div>
                                            <div class="modal-footer bg-light border-0 p-4 pt-0">
                                                <button type="button" class="btn btn-secondary px-4 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="edit_user" class="btn btn-success rounded-pill px-4 fw-bold"><i class="fa-solid fa-floppy-disk me-2"></i>Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- QUICK REG MODAL -->
    <div class="modal fade" id="quickRegModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-bolt me-2"></i>Enterprise Quick Registration</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4 bg-light">
                        <div class="mb-3"><label class="form-label fw-bold">Full Name</label><input type="text" name="name" class="form-control border-primary" required></div>
                        <div class="row mb-3">
                            <div class="col-md-6"><label class="form-label fw-bold">Email Address</label><input type="email" name="email" class="form-control border-primary" required></div>
                            <div class="col-md-6"><label class="form-label fw-bold">Phone Number</label><input type="text" name="phone" class="form-control border-primary"></div>
                        </div>
                        <div class="mb-3"><label class="form-label fw-bold">Initial Password</label><input type="password" name="password" class="form-control border-primary" value="password123" required></div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">System Role</label>
                            <select name="role" class="form-select border-primary" required>
                                <option value="student">Student (Academic Portal)</option>
                                <option value="faculty">Faculty (Teaching Portal)</option>
                                <option value="staff">Staff (Administrative Portal)</option>
                                <option value="admin">Administrator (Full Access)</option>
                            </select>
                        </div>
                        <div class="alert alert-info mt-3 small py-2 mb-0 border-0">
                            <i class="fa-solid fa-circle-info me-2"></i> System will auto-initialize their profile securely in the background.
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light p-4 pt-0">
                        <button type="button" class="btn btn-secondary px-4 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="quick_reg" class="btn btn-primary rounded-pill px-4 fw-bold"><i class="fa-solid fa-user-check me-2"></i>Provision User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Real-Time Search & Filter
        document.getElementById('searchData').addEventListener('input', filterData);
        document.getElementById('filterRole').addEventListener('change', filterData);

        function filterData() {
            const term = document.getElementById('searchData').value.toLowerCase();
            const role = document.getElementById('filterRole').value.toLowerCase();
            document.querySelectorAll('.data-row').forEach(row => {
                const text = row.innerText.toLowerCase();
                const rowRole = row.querySelector('.search-role').innerText.toLowerCase();
                if(text.includes(term) && (role === 'all' || rowRole.includes(role))) row.style.display = '';
                else row.style.display = 'none';
            });
        }

        // Click to Sort
        let sortAsc = true;
        let currentSortCol = -1;
        function sortTable(col) {
            const tbody = document.getElementById("dataTableBody");
            const rows = Array.from(tbody.querySelectorAll("tr.data-row"));
            
            if (currentSortCol === col) { sortAsc = !sortAsc; } 
            else { sortAsc = true; currentSortCol = col; }

            document.querySelectorAll('.sort-icon').forEach(icon => icon.className = 'fa-solid fa-sort ms-1 sort-icon text-muted');
            const activeIcon = document.getElementById('sort-icon-' + col);
            if(activeIcon) activeIcon.className = sortAsc ? 'fa-solid fa-sort-up ms-1 sort-icon text-white' : 'fa-solid fa-sort-down ms-1 sort-icon text-white';

            rows.sort((a, b) => {
                let cellA = a.querySelectorAll("td")[col];
                let cellB = b.querySelectorAll("td")[col];
                let vA = cellA.getAttribute('data-sort-val') || cellA.innerText.trim().toLowerCase();
                let vB = cellB.getAttribute('data-sort-val') || cellB.innerText.trim().toLowerCase();
                return vA < vB ? (sortAsc ? -1 : 1) : (vA > vB ? (sortAsc ? 1 : -1) : 0);
            });
            rows.forEach(r => tbody.appendChild(r));
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>