<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$userId]);
$student = $stmt->fetch();
$studentId = $student['id'];

// Get Academic Data & AI Recommendations
$marksQuery = "
    SELECT c.name as subject, m.internal_marks, m.external_marks, m.total_marks,
           (a.attended_classes / NULLIF(a.total_classes, 0)) * 100 as attendance_percent
    FROM marks m
    JOIN courses c ON m.course_id = c.id
    JOIN attendance a ON a.student_id = m.student_id AND a.course_id = c.id
    WHERE m.student_id = ?
";
$stmt = $pdo->prepare($marksQuery);
$stmt->execute([$studentId]);
$academics = $stmt->fetchAll();

$weakSubjects = [];
foreach ($academics as $record) {
    if ($record['total_marks'] < 40) $weakSubjects[] = $record['subject'];
}

// Fetch Announcements
$stmtAnn = $pdo->query("SELECT title, created_at FROM announcements WHERE target_audience IN ('all', 'student') ORDER BY created_at DESC LIMIT 4");
$announcements = $stmtAnn->fetchAll();

// 🔥 NEW: Fetch Financial Due Balance 🔥
$stmtFee = $pdo->prepare("SELECT SUM(amount) FROM fees WHERE student_id = ? AND status != 'Paid'");
$stmtFee->execute([$studentId]);
$totalDues = $stmtFee->fetchColumn() ?: 0;

$stmtAllFees = $pdo->prepare("SELECT id, amount, status, due_date FROM fees WHERE student_id = ? ORDER BY due_date ASC");
$stmtAllFees->execute([$studentId]);
$myFees = $stmtAllFees->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Portal - SCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm py-3">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#"><i class="fa-solid fa-graduation-cap text-primary me-2"></i> SCMS Student Hub</a>
            <div class="d-flex align-items-center gap-2">
                
                <a href="../modules/announcements/index.php" class="btn btn-sm btn-outline-light fw-bold rounded-pill px-3 shadow-sm"><i class="fa-solid fa-bullhorn me-1"></i> Notices</a>
                <a href="../modules/documents/index.php" class="btn btn-sm btn-outline-light fw-bold rounded-pill px-3 shadow-sm"><i class="fa-solid fa-folder-open me-1"></i> Resources</a>
                
                <div class="dropdown ms-3 border-start ps-3 border-secondary">
                    <button class="btn btn-dark dropdown-toggle text-white fw-semibold border-0" type="button" data-bs-toggle="dropdown">
                        <i class="fa-solid fa-user-circle text-primary me-2 fs-5 align-middle"></i><?= htmlspecialchars($student['name'] ?? $_SESSION['name']) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 rounded-3">
                        <li><a class="dropdown-item py-2 fw-semibold" href="../modules/profile/index.php"><i class="fa-solid fa-user-shield text-muted me-2"></i>Account Security</a></li>
                        <li><a class="dropdown-item py-2 fw-semibold" href="../modules/marks/marksheet.php" target="_blank"><i class="fa-solid fa-print text-muted me-2"></i>Print Marksheet</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 fw-bold text-danger" href="../auth/logout.php"><i class="fa-solid fa-power-off me-2"></i>Secure Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    <div class="container mt-4 pb-5">
        
        <?php if($totalDues > 0): ?>
            <div class="alert alert-danger shadow-sm border-start border-danger border-5 mb-4 d-flex justify-content-between align-items-center" role="alert">
                <div>
                    <h5 class="alert-heading fw-bold mb-1"><i class="fa-solid fa-wallet me-2"></i> Action Required: Pending Financial Dues</h5>
                    <p class="mb-0 fw-semibold text-dark">You have an outstanding balance of <strong>₹<?= number_format($totalDues, 2) ?></strong>. Please clear your dues to avoid late penalties.</p>
                </div>
                <button class="btn btn-danger fw-bold shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#financeModal">View Invoices</button>
            </div>
        <?php endif; ?>

        <?php if(count($weakSubjects) > 0): ?>
            <div class="alert alert-warning shadow-sm border-start border-warning border-5 mb-4" role="alert">
                <h5 class="alert-heading fw-bold text-dark"><i class="fa-solid fa-lightbulb text-warning me-2"></i> AI Recommendation System</h5>
                <p class="mb-1 text-dark">Prediction engine shows weak performance in: <strong><?= implode(', ', $weakSubjects) ?></strong>.</p>
                <hr><p class="mb-0 fw-semibold text-dark">Recommendation: Utilize the Automatic Study Planner below to reorganize your week.</p>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm mb-4 rounded-4 overflow-hidden">
                    <div class="card-header bg-white pt-4 pb-3 border-0"><h5 class="fw-bold"><i class="fa-solid fa-book-open text-primary me-2"></i> Semester Performance</h5></div>
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th class="ps-4">Subject</th><th>Attendance</th><th>Internal</th><th>External</th><th>Total</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($academics as $row): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($row['subject']) ?></td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <div class="progress mb-1 shadow-sm" style="height: 6px; width: 80px;">
                                                <div class="progress-bar <?= $row['attendance_percent'] < 75 ? 'bg-danger' : 'bg-success' ?>" role="progressbar" style="width: <?= $row['attendance_percent'] ?>%"></div>
                                            </div>
                                            <small class="text-muted fw-bold"><?= number_format($row['attendance_percent'], 1) ?>%</small>
                                        </div>
                                    </td>
                                    <td class="fw-semibold"><?= $row['internal_marks'] ?></td>
                                    <td class="fw-semibold"><?= $row['external_marks'] ?></td>
                                    <td class="fw-bold fs-5 text-dark"><?= $row['total_marks'] ?></td>
                                    <td>
                                        <?php if($row['total_marks'] < 40): ?> <span class="badge bg-danger-subtle text-danger border border-danger rounded-pill px-3">Needs Work</span>
                                        <?php else: ?> <span class="badge bg-success-subtle text-success border border-success rounded-pill px-3">Pass</span> <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($academics)): ?>
                                    <tr><td colspan="6" class="text-center py-5 text-muted fst-italic fw-bold"><i class="fa-solid fa-folder-open fa-2x mb-2 d-block opacity-50"></i>No academic records found for this semester.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm custom-gradient-card text-white h-100 mb-4 rounded-4">
                    <div class="card-body text-center p-4 d-flex flex-column justify-content-center">
                        <div class="mb-3 mt-2"><i class="fa-solid fa-calendar-check fa-4x text-white opacity-75"></i></div>
                        <h4 class="fw-bold">AI Study Planner</h4>
                        <p class="small text-white-50 mb-4">Generate a dynamic weekly schedule based on your weak areas automatically.</p>
                        <button onclick="generatePlanner()" class="btn btn-light text-primary btn-lg fw-bold w-100 rounded-pill shadow-lg mt-auto">Generate My Plan</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4 d-none" id="plannerResult">
            <div class="col-12">
                <div class="card border-0 shadow-sm border-top border-primary border-4 rounded-4 overflow-hidden">
                    <div class="card-header bg-white border-0 pt-4 pb-0"><h5 class="fw-bold"><i class="fa-solid fa-clock text-primary me-2"></i> Your Custom Weekly Study Plan</h5></div>
                    <div class="card-body" id="plannerTableContainer"></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm border-top border-info border-4 rounded-4">
                    <div class="card-header bg-white border-0 pt-4 pb-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-clipboard-list text-info me-2"></i> Latest Campus Notices</h5>
                        <a href="../modules/announcements/index.php" class="btn btn-sm btn-outline-info rounded-pill px-3 fw-bold">Open Full Board &rarr;</a>
                    </div>
                    <div class="card-body bg-light">
                        <div class="row g-3">
                            <?php if(empty($announcements)): ?>
                                <div class="col-12 text-muted text-center py-4 fw-bold"><i class="fa-regular fa-bell-slash fa-2x mb-2 d-block opacity-50"></i>No new notices at the moment.</div>
                            <?php else: ?>
                                <?php foreach($announcements as $ann): ?>
                                    <div class="col-md-3">
                                        <div class="p-4 bg-white rounded-4 border-0 h-100 shadow-sm" style="transition: transform 0.2s;">
                                            <div class="small text-muted mb-2 fw-semibold"><i class="fa-regular fa-clock me-1 text-info"></i><?= date('d M, Y', strtotime($ann['created_at'])) ?></div>
                                            <div class="fw-bold text-dark fs-6" style="line-height: 1.4;"><?= htmlspecialchars($ann['title']) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- 🔥 My Invoices Modal 🔥 -->
    <div class="modal fade" id="financeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-dark text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-invoice-dollar me-2 text-success"></i> My Financial Invoices</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Invoice ID</th>
                                <th>Due Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($myFees as $f): 
                                $badge = $f['status'] === 'Paid' ? 'bg-success' : ($f['status'] === 'Overdue' ? 'bg-danger' : 'bg-warning text-dark');
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold text-secondary">#INV-<?= str_pad($f['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td class="fw-semibold text-dark"><?= date('d M Y', strtotime($f['due_date'])) ?></td>
                                <td class="fw-bold fs-5">₹<?= number_format($f['amount'], 2) ?></td>
                                <td><span class="badge <?= $badge ?> rounded-pill px-3 py-2"><?= $f['status'] ?></span></td>
                                <td class="text-end pe-4">
                                    <a href="../modules/fees/receipt.php?id=<?= $f['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill fw-bold px-3">
                                        <i class="fa-solid fa-print me-1"></i> Print
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($myFees)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted fw-bold">No financial records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>const weakSubjects = <?= json_encode($weakSubjects) ?>;</script>
    <script src="../assets/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>