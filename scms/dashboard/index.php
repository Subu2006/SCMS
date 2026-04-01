<?php
session_start();
require_once '../config/db.php';

// Strict RBAC Security
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'faculty')) {
    header("Location: ../auth/login.php");
    exit;
}

$role = $_SESSION['role'];
$success = '';
$error = '';

// 🔥 QUICK REGISTRATION ENGINE (EMBEDDED IN DASHBOARD) 🔥
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_reg']) && $role === 'admin') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $new_role = $_POST['new_role'];

    try {
        $pdo->beginTransaction();

        // Step A: Create the Login Identity
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password, $new_role]);
        $new_user_id = $pdo->lastInsertId();

        // Step B: Create the Role-Specific Profile Automatically
        if ($new_role === 'student') {
            $enrollment = "REG" . time(); 
            $stmt2 = $pdo->prepare("INSERT INTO students (user_id, enrollment_no, dept, semester) VALUES (?, ?, 'Unassigned', 1)");
            $stmt2->execute([$new_user_id, $enrollment]);
        } elseif ($new_role === 'faculty') {
            $stmt2 = $pdo->prepare("INSERT INTO faculty (user_id, department) VALUES (?, 'General')");
            $stmt2->execute([$new_user_id]);
        } elseif ($new_role === 'staff') {
            $stmt2 = $pdo->prepare("INSERT INTO staff_profiles (user_id, designation, department) VALUES (?, 'Executive', 'Administration')");
            $stmt2->execute([$new_user_id]);
        }

        $pdo->commit();
        $success = "Success! [$name] has been registered as a [$new_role] and their profile was auto-generated.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Registration Failed: The email '$email' might already be registered in the system.";
    }
}

// Fetch Analytics
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalFaculty = $pdo->query("SELECT COUNT(*) FROM faculty")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// 🔥 RISK ENGINE LOGIC
$riskQuery = "
    SELECT u.name, s.enrollment_no, 
           COALESCE(AVG((a.attended_classes / NULLIF(a.total_classes, 0)) * 100), 0) as avg_attendance,
           COALESCE(AVG(m.total_marks), 0) as avg_marks
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN attendance a ON s.id = a.student_id
    LEFT JOIN marks m ON s.id = m.student_id
    GROUP BY s.id
    HAVING avg_attendance < 75 OR avg_marks < 40
";
$atRiskStudents = $pdo->query($riskQuery)->fetchAll();

// Chart Data
$chartQuery = $pdo->query("SELECT c.name, AVG(m.total_marks) as avg_mark FROM courses c LEFT JOIN marks m ON c.id = m.course_id GROUP BY c.id");
$chartData = $chartQuery->fetchAll();
$labels = array_column($chartData, 'name');
$data = array_column($chartData, 'avg_mark');

// Widget Data: Dynamic Announcements
$announcements = [];
try {
    $announcements = $pdo->query("SELECT title, created_at FROM announcements ORDER BY created_at DESC LIMIT 4")->fetchAll();
} catch (Exception $e) {
    // Failsafe
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ERP Command Center | SCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="erp-body">
    <nav class="sidebar bg-dark text-white shadow-lg">
        <div class="sidebar-header p-4 pb-2 border-bottom border-secondary">
            <h4 class="fw-bold"><i class="fa-solid fa-graduation-cap text-primary me-2"></i> SCMS PRO</h4>
            <span class="badge <?= $role === 'admin' ? 'bg-danger' : 'bg-primary' ?> text-uppercase"><?= htmlspecialchars($role) ?> Mode</span>
        </div>
        <ul class="nav flex-column mt-3">
            <li class="nav-item"><a href="index.php" class="nav-link active"><i class="fa-solid fa-gauge-high me-2"></i> Console Home</a></li>
            
            <?php if($role === 'admin'): ?>
                <li class="nav-item"><a href="../modules/users/index.php" class="nav-link text-info fw-bold"><i class="fa-solid fa-user-gear me-2"></i> User Management</a></li>
                <li class="nav-item"><a href="../modules/students/index.php" class="nav-link"><i class="fa-solid fa-users me-2"></i> Students</a></li>
                <li class="nav-item"><a href="../modules/faculty/index.php" class="nav-link"><i class="fa-solid fa-chalkboard-user me-2"></i> Faculty</a></li>
                <li class="nav-item"><a href="../modules/courses/index.php" class="nav-link text-warning fw-bold"><i class="fa-solid fa-book-open-reader me-2"></i> Course & Subjects</a></li>
                <li class="nav-item"><a href="../modules/fees/index.php" class="nav-link"><i class="fa-solid fa-wallet me-2"></i> Financial Records</a></li>
            <?php endif; ?>
            
            <li class="nav-item"><a href="../modules/attendance/index.php" class="nav-link"><i class="fa-solid fa-calendar-check me-2"></i> Attendance</a></li>
            <li class="nav-item"><a href="../modules/marks/index.php" class="nav-link"><i class="fa-solid fa-award me-2"></i> Grade Master</a></li>
            <li class="nav-item"><a href="../modules/announcements/index.php" class="nav-link"><i class="fa-solid fa-bullhorn me-2"></i> Announcements</a></li>
            <li class="nav-item"><a href="../modules/documents/index.php" class="nav-link"><i class="fa-solid fa-folder-tree me-2"></i> Resource Library</a></li>

            <?php if($role === 'admin'): ?>
                <li class="nav-item border-top mt-2"><a href="../analytics/index.php" class="nav-link"><i class="fa-solid fa-chart-line me-2"></i> Enterprise BI</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    
    <main class="main-content bg-light min-vh-100">
        <header class="top-header bg-white shadow-sm p-3 d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <h5 class="m-0 fw-bold text-dark me-4">Administrative Dashboard</h5>
                <?php if($role === 'admin'): ?>
                <button class="btn btn-sm btn-primary rounded-pill px-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#quickRegModal">
                    <i class="fa-solid fa-user-plus me-1"></i> Quick Add User
                </button>
                <?php endif; ?>
            </div>
            
            <!-- 🔥 UPDATED: Dynamic Profile Dropdown with Security Link 🔥 -->
            <div class="user-profile d-flex align-items-center">
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle fw-semibold border-0 shadow-sm rounded-pill px-3" type="button" data-bs-toggle="dropdown">
                        <i class="fa-solid fa-user-tie text-primary me-2 fs-6 align-middle"></i><?= htmlspecialchars($_SESSION['name']) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 rounded-3">
                        <li><a class="dropdown-item py-2 fw-semibold" href="../modules/profile/index.php"><i class="fa-solid fa-user-shield text-muted me-2"></i>Account Security</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 fw-bold text-danger" href="../auth/logout.php"><i class="fa-solid fa-power-off me-2"></i>Secure Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <div class="container-fluid px-4 pb-5">
            <?php if($success) echo "<div class='alert alert-success shadow-sm rounded-3 fw-bold'><i class='fa-solid fa-check-circle me-2'></i>$success</div>"; ?>
            <?php if($error) echo "<div class='alert alert-danger shadow-sm rounded-3 fw-bold'><i class='fa-solid fa-triangle-exclamation me-2'></i>$error</div>"; ?>

            <div class="row g-4 mb-4 mt-1">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm bg-primary text-white p-3 h-100 kpi-card rounded-4">
                        <h6 class="mb-1 opacity-75 fw-bold text-uppercase small">Active Students</h6><h2 class="mb-0 fw-bold"><?= $totalStudents ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm bg-success text-white p-3 h-100 kpi-card rounded-4">
                        <h6 class="mb-1 opacity-75 fw-bold text-uppercase small">Faculty Strength</h6><h2 class="mb-0 fw-bold"><?= $totalFaculty ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm bg-dark text-white p-3 h-100 kpi-card rounded-4">
                        <h6 class="mb-1 opacity-75 fw-bold text-uppercase small">Total System Users</h6><h2 class="mb-0 fw-bold"><?= $totalUsers ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm bg-warning text-dark p-3 h-100 kpi-card rounded-4">
                        <h6 class="mb-1 opacity-75 fw-bold text-uppercase small">Academic Risk Count</h6><h2 class="mb-0 fw-bold"><?= count($atRiskStudents) ?></h2>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card shadow-sm border-0 h-100 rounded-4">
                        <div class="card-header bg-white border-0 pt-4 pb-0"><h6 class="fw-bold text-primary"><i class="fa-solid fa-chart-bar me-2"></i>System Performance Trend</h6></div>
                        <div class="card-body"><canvas id="performanceChart" height="100"></canvas></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 border-top border-danger border-4 h-100 rounded-4">
                        <div class="card-header bg-white border-0 pt-4 pb-0"><h6 class="fw-bold text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Real-time Risk Alerts</h6></div>
                        <ul class="list-group list-group-flush mt-2">
                            <?php foreach($atRiskStudents as $risk): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <span class="fw-bold text-dark d-block"><?= htmlspecialchars($risk['name']) ?></span>
                                    <span class="small text-muted"><?= htmlspecialchars($risk['enrollment_no']) ?></span>
                                </div>
                                <span class="badge bg-danger rounded-pill shadow-sm">Risk Detected</span>
                            </li>
                            <?php endforeach; if(empty($atRiskStudents)) echo "<li class='list-group-item text-success fw-bold py-4'><i class='fa-solid fa-check-circle me-2'></i>All students compliant.</li>"; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm border-top border-info border-4 rounded-4">
                        <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold"><i class="fa-solid fa-bullhorn text-info me-2"></i>Campus Broadcast Hub</h6>
                            <a href="../modules/announcements/index.php" class="btn btn-sm btn-outline-info rounded-pill fw-bold px-3">Manage Boards &rarr;</a>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php if(empty($announcements)): ?>
                                    <div class="text-muted fst-italic py-3">No recent announcements found in the system.</div>
                                <?php else: ?>
                                    <?php foreach($announcements as $ann): ?>
                                        <div class="list-group-item px-0 d-flex justify-content-between align-items-center py-3 border-bottom">
                                            <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($ann['title']) ?></span>
                                            <span class="badge bg-light text-secondary border px-3 py-2"><i class="fa-regular fa-clock me-1"></i><?= date('d M, Y', strtotime($ann['created_at'])) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <?php if($role === 'admin'): ?>
    <div class="modal fade" id="quickRegModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-bolt me-2"></i>Dashboard Quick Registration</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted">Full Name</label>
                            <input type="text" name="name" class="form-control bg-light border-0" placeholder="e.g. Robert Smith" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted">Email Address</label>
                            <input type="email" name="email" class="form-control bg-light border-0" placeholder="rsmith@scms.edu" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted">Assign Initial Password</label>
                            <input type="password" name="password" class="form-control bg-light border-0" value="password123" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold text-muted">System Access Role</label>
                            <select name="new_role" class="form-select bg-light border-0" required>
                                <option value="student">Student (Academic Portal)</option>
                                <option value="faculty">Faculty (Teaching Portal)</option>
                                <option value="staff">Staff (Administrative Portal)</option>
                                <option value="admin">Administrator (Full Access)</option>
                            </select>
                        </div>
                        <div class="alert alert-info mt-3 small py-2 mb-0 border-0">
                            <i class="fa-solid fa-circle-info me-2"></i> Pro Tip: The system will automatically create their core academic/faculty profiles in the background!
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light p-4 pt-0">
                        <button type="submit" name="quick_reg" class="btn btn-primary px-5 rounded-pill fw-bold w-100">Instantly Provision User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const chartLabels = <?= json_encode($labels) ?>;
        const chartDataValues = <?= json_encode($data) ?>;
    </script>
    <script src="../assets/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>