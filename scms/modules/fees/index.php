<?php
session_start();
require_once '../../config/db.php';

// Strict Role-Based Access Control: Only Admins can manipulate financial records
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../../auth/login.php"); 
    exit; 
}

$success = '';
$error = '';

// --- 1. DIRECT ACTION: 1-CLICK BULK BILLING ENGINE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_fee'])) {
    $dept = filter_input(INPUT_POST, 'target_dept', FILTER_SANITIZE_STRING);
    $semester = filter_input(INPUT_POST, 'target_sem', FILTER_SANITIZE_NUMBER_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $due_date = filter_input(INPUT_POST, 'due_date', FILTER_SANITIZE_STRING);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT id FROM students WHERE dept = ? AND semester = ?");
        $stmt->execute([$dept, $semester]);
        $target_students = $stmt->fetchAll();

        $count = 0;
        if (count($target_students) > 0) {
            $insert = $pdo->prepare("INSERT INTO fees (student_id, amount, status, due_date) VALUES (?, ?, ?, ?)");
            foreach ($target_students as $ts) {
                $insert->execute([$ts['id'], $amount, $status, $due_date]);
                $count++;
            }
            $pdo->commit();
            $success = "Enterprise Billing Success: Automatically generated $count invoices for $dept (Semester $semester).";
        } else {
            $pdo->rollBack();
            $error = "No students found matching $dept (Semester $semester). No invoices generated.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to process bulk billing batch.";
    }
}

// --- 2. DIRECT ACTION: 1-CLICK MARK AS PAID ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_pay'])) {
    $fee_id = filter_input(INPUT_POST, 'target_fee_id', FILTER_SANITIZE_NUMBER_INT);
    try {
        $stmt = $pdo->prepare("UPDATE fees SET status = 'Paid' WHERE id = ?");
        $stmt->execute([$fee_id]);
        $success = "Invoice #INV-" . str_pad($fee_id, 4, '0', STR_PAD_LEFT) . " instantly marked as PAID. Revenue KPIs updated.";
    } catch (PDOException $e) {
        $error = "Failed to process quick payment.";
    }
}

// --- 3. DIRECT ACTION: 1-CLICK FLAG OVERDUE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_overdue'])) {
    $fee_id = filter_input(INPUT_POST, 'target_fee_id', FILTER_SANITIZE_NUMBER_INT);
    try {
        $stmt = $pdo->prepare("UPDATE fees SET status = 'Overdue' WHERE id = ?");
        $stmt->execute([$fee_id]);
        $success = "Invoice #INV-" . str_pad($fee_id, 4, '0', STR_PAD_LEFT) . " flagged as OVERDUE.";
    } catch (PDOException $e) {
        $error = "Failed to flag invoice.";
    }
}

// --- 4. HANDLE ADD NEW FEE (Manual Single Generation) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fee'])) {
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_SANITIZE_NUMBER_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $due_date = filter_input(INPUT_POST, 'due_date', FILTER_SANITIZE_STRING);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    try {
        $stmt = $pdo->prepare("INSERT INTO fees (student_id, amount, status, due_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$student_id, $amount, $status, $due_date]);
        $success = "New individual financial invoice generated successfully.";
    } catch (PDOException $e) {
        $error = "Failed to add fee record: " . $e->getMessage();
    }
}

// --- 5. HANDLE EDIT FEE (Full CRUD) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_fee'])) {
    $fee_id = filter_input(INPUT_POST, 'fee_id', FILTER_SANITIZE_NUMBER_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $due_date = filter_input(INPUT_POST, 'due_date', FILTER_SANITIZE_STRING);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    try {
        $stmt = $pdo->prepare("UPDATE fees SET amount = ?, due_date = ?, status = ? WHERE id = ?");
        $stmt->execute([$amount, $due_date, $status, $fee_id]);
        $success = "Financial record updated successfully.";
    } catch (PDOException $e) {
        $error = "Failed to update fee record.";
    }
}

// --- 6. HANDLE DELETE FEE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_fee'])) {
    $fee_id = filter_input(INPUT_POST, 'fee_id', FILTER_SANITIZE_NUMBER_INT);
    try {
        $stmt = $pdo->prepare("DELETE FROM fees WHERE id = ?");
        $stmt->execute([$fee_id]);
        $success = "Financial record securely deleted from the ledger.";
    } catch (PDOException $e) {
        $error = "Failed to delete fee record.";
    }
}

// --- 7. FETCH DATA FOR UI ---
$fees = $pdo->query("
    SELECT f.id as fee_id, u.name as student_name, u.phone, s.enrollment_no, s.dept, f.amount, f.status, f.due_date 
    FROM fees f 
    JOIN students s ON f.student_id = s.id 
    JOIN users u ON s.user_id = u.id
    ORDER BY FIELD(f.status, 'Overdue', 'Pending', 'Paid'), f.due_date ASC
")->fetchAll();

$studentsList = $pdo->query("SELECT s.id, u.name, s.enrollment_no FROM students s JOIN users u ON s.user_id = u.id ORDER BY u.name")->fetchAll();
$distinctDepts = $pdo->query("SELECT DISTINCT dept FROM students")->fetchAll(PDO::FETCH_COLUMN);

// Calculate KPIs
$totalPaid = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM fees WHERE status = 'Paid'")->fetchColumn();
$totalPending = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM fees WHERE status = 'Pending'")->fetchColumn();
$totalOverdue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM fees WHERE status = 'Overdue'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enterprise Fee Management | SCMS ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/style.css" rel="stylesheet">
    <style>
        .row-paid { background-color: #f8f9fa; opacity: 0.7; }
        .row-paid td { text-decoration: line-through; color: #6c757d !important; }
        .row-paid .badge, .row-paid .btn, .row-paid .student-contact { text-decoration: none; opacity: 1; }
        
        .search-wrapper .form-control:focus { box-shadow: none; border-color: #dee2e6; }
        th.sortable { cursor: pointer; user-select: none; transition: background-color 0.2s; }
        th.sortable:hover { background-color: #2c3034 !important; }
        .sort-icon { font-size: 0.8em; opacity: 0.5; transition: opacity 0.2s; }
        th.sortable:hover .sort-icon { opacity: 1; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark px-4 shadow-sm">
        <a class="navbar-brand fw-bold" href="../../dashboard/index.php">
            <i class="fa-solid fa-arrow-left me-2"></i> SCMS | Financial Operations
        </a>
    </nav>

    <div class="container-fluid px-4 mt-4 pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-dark"><i class="fa-solid fa-file-invoice-dollar text-success me-2"></i> Fee Processing & Billing</h3>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-success shadow-sm fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#bulkFeeModal">
                    <i class="fa-solid fa-layer-group me-2"></i> Bulk Invoice Generation
                </button>
                <button class="btn btn-primary shadow-sm fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addFeeModal">
                    <i class="fa-solid fa-plus me-2"></i> Single Invoice
                </button>
            </div>
        </div>

        <!-- Financial KPIs -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-success border-4 h-100 rounded-4">
                    <div class="card-body">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Total Revenue Collected</div>
                        <h3 class="mb-0 fw-bold text-success">₹<?= number_format($totalPaid, 2) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-warning border-4 h-100 rounded-4">
                    <div class="card-body">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Expected Revenue (Pending)</div>
                        <h3 class="mb-0 fw-bold text-warning text-dark">₹<?= number_format($totalPending, 2) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-danger border-4 h-100 rounded-4">
                    <div class="card-body">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Critical Overdue</div>
                        <h3 class="mb-0 fw-bold text-danger">₹<?= number_format($totalOverdue, 2) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <?php if($success) echo "<div class='alert alert-success shadow-sm rounded-3 fw-bold'><i class='fa-solid fa-check-circle me-2'></i>$success</div>"; ?>
        <?php if($error) echo "<div class='alert alert-danger shadow-sm rounded-3 fw-bold'><i class='fa-solid fa-triangle-exclamation me-2'></i>$error</div>"; ?>

        <!-- Main Data Table with Search/Sort -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-0 pt-4 pb-3">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-book-journal-whills text-info me-2"></i> General Ledger</h5>
                
                <!-- 🔥 REAL-TIME SEARCH & FILTER ENGINE 🔥 -->
                <div class="row g-3 bg-light p-3 rounded-3 border">
                    <div class="col-md-5">
                        <div class="input-group search-wrapper">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="searchData" class="form-control border-start-0 ps-0" placeholder="Search Invoice ID, Name, Phone...">
                        </div>
                    </div>
                    <div class="col-md-4">
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
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-filter text-muted"></i></span>
                            <select id="filterStatus" class="form-select border-start-0 ps-0">
                                <option value="all">All Statuses</option>
                                <option value="Paid">Paid</option>
                                <option value="Pending">Pending</option>
                                <option value="Overdue">Overdue</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="feesTable">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-4 sortable" onclick="sortTable(0)">Invoice ID <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-0"></i></th>
                                <th class="sortable" onclick="sortTable(1)">Student Details <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-1"></i></th>
                                <th class="sortable" onclick="sortTable(2)">Amount Due <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-2"></i></th>
                                <th class="sortable" onclick="sortTable(3)">Due Date <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-3"></i></th>
                                <th class="sortable" onclick="sortTable(4)">Status <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-4"></i></th>
                                <th class="text-end pe-4">1-Click Actions & Manage</th>
                            </tr>
                        </thead>
                        <tbody id="dataTableBody">
                            <?php foreach($fees as $fee): 
                                $isPaid = $fee['status'] === 'Paid';
                            ?>
                            <tr class="data-row <?= $isPaid ? 'row-paid' : '' ?>">
                                <td class="ps-4 fw-bold text-secondary search-inv">#INV-<?= str_pad($fee['fee_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <div class="fw-bold text-dark search-name"><?= htmlspecialchars($fee['student_name']) ?></div>
                                    <div class="small text-muted student-contact mt-1 d-flex flex-wrap gap-2">
                                        <span class="search-enrollment"><i class="fa-solid fa-id-badge text-primary"></i> <?= htmlspecialchars($fee['enrollment_no']) ?></span>
                                        <span class="search-phone"><i class="fa-solid fa-phone text-success"></i> <?= htmlspecialchars($fee['phone'] ?? 'N/A') ?></span>
                                        <span class="search-dept badge bg-light text-dark border d-none"><?= htmlspecialchars($fee['dept']) ?></span> <!-- Hidden field for filter -->
                                    </div>
                                </td>
                                <td class="fw-bold fs-5 text-dark" data-sort-val="<?= $fee['amount'] ?>">₹<?= number_format($fee['amount'], 2) ?></td>
                                <td data-sort-val="<?= strtotime($fee['due_date']) ?>">
                                    <span class="<?= (strtotime($fee['due_date']) < time() && !$isPaid) ? 'text-danger fw-bold' : 'fw-semibold' ?>">
                                        <?= date('d M Y', strtotime($fee['due_date'])) ?>
                                    </span>
                                </td>
                                <td class="search-status">
                                    <?php 
                                        $badge = 'bg-warning text-dark';
                                        if($fee['status'] == 'Paid') $badge = 'bg-success';
                                        if($fee['status'] == 'Overdue') $badge = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $badge ?> rounded-pill px-3 py-2 shadow-sm"><?= htmlspecialchars($fee['status']) ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end align-items-center gap-1">
                                        
                                        <!-- 🔥 1-CLICK DIRECT ACTIONS 🔥 -->
                                        <?php if(!$isPaid): ?>
                                            <form method="POST" class="m-0 p-0" title="Instant Mark as Paid">
                                                <input type="hidden" name="target_fee_id" value="<?= $fee['fee_id'] ?>">
                                                <button type="submit" name="quick_pay" class="btn btn-sm btn-success rounded-pill fw-bold px-3 shadow-sm">
                                                    <i class="fa-solid fa-check-double me-1"></i> Pay
                                                </button>
                                            </form>
                                            
                                            <?php if($fee['status'] !== 'Overdue'): ?>
                                            <form method="POST" class="m-0 p-0" title="Flag as Overdue">
                                                <input type="hidden" name="target_fee_id" value="<?= $fee['fee_id'] ?>">
                                                <button type="submit" name="quick_overdue" class="btn btn-sm btn-outline-danger rounded-circle" style="width: 32px; height: 32px; padding: 0;">
                                                    <i class="fa-solid fa-clock"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-info rounded-circle" style="width: 32px; height: 32px; padding: 0;" title="Print Receipt" onclick="window.print()">
                                                <i class="fa-solid fa-print"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- Standard Edit (Modal) -->
                                        <button class="btn btn-sm btn-outline-primary rounded-circle ms-2" style="width: 32px; height: 32px; padding: 0;" data-bs-toggle="modal" data-bs-target="#editFeeModal<?= $fee['fee_id'] ?>" title="Edit Full Details">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>

                                        <!-- Standard Delete -->
                                        <form method="POST" class="m-0 p-0 d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this financial record?');">
                                            <input type="hidden" name="fee_id" value="<?= $fee['fee_id'] ?>">
                                            <button type="submit" name="delete_fee" class="btn btn-sm btn-outline-secondary border-0 rounded-circle" style="width: 32px; height: 32px; padding: 0;" title="Delete Record">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <!-- Edit Fee Modal -->
                            <div class="modal fade" id="editFeeModal<?= $fee['fee_id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                        <div class="modal-header bg-dark text-white border-0 py-3">
                                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Financial Record</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body p-4 bg-light text-start">
                                                <input type="hidden" name="fee_id" value="<?= $fee['fee_id'] ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold text-muted">Student Name</label>
                                                    <input type="text" class="form-control bg-white border-0 fw-bold text-dark" value="<?= htmlspecialchars($fee['student_name']) ?> (<?= htmlspecialchars($fee['enrollment_no']) ?>)" readonly>
                                                </div>

                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-bold">Amount (₹)</label>
                                                        <input type="number" name="amount" class="form-control border-primary" value="<?= $fee['amount'] ?>" step="0.01" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-bold">Due Date</label>
                                                        <input type="date" name="due_date" class="form-control border-primary" value="<?= $fee['due_date'] ?>" required>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Payment Status</label>
                                                    <select name="status" class="form-select border-primary" required>
                                                        <option value="Pending" <?= $fee['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="Paid" <?= $fee['status'] == 'Paid' ? 'selected' : '' ?>>Paid</option>
                                                        <option value="Overdue" <?= $fee['status'] == 'Overdue' ? 'selected' : '' ?>>Overdue</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light border-0 p-4 pt-0">
                                                <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="edit_fee" class="btn btn-primary rounded-pill px-4 fw-bold"><i class="fa-solid fa-floppy-disk me-2"></i>Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <tr id="noRecordsRow" style="display: <?= empty($fees) ? 'table-row' : 'none' ?>;">
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-wallet fa-3x mb-3 opacity-50"></i>
                                    <p class="mb-0 fw-bold" id="noRecordsText">No financial records found matching your search.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 🔥 Bulk Billing Modal 🔥 -->
    <div class="modal fade" id="bulkFeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-success text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-layer-group me-2"></i>Enterprise Bulk Billing</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" onsubmit="return confirm('You are about to generate an invoice for an entire demographic. Ensure details are correct. Proceed?');">
                    <div class="modal-body p-4 bg-light">
                        <p class="text-muted small mb-4">Automatically generate invoices for every student within a specific department and semester.</p>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Target Department</label>
                                <select name="target_dept" class="form-select border-success" required>
                                    <option value="" disabled selected>-- Select Dept --</option>
                                    <?php foreach($distinctDepts as $d): ?>
                                        <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Target Semester</label>
                                <input type="number" name="target_sem" class="form-control border-success" min="1" max="8" placeholder="e.g. 5" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Billing Amount (₹) Per Student</label>
                                <input type="number" name="amount" class="form-control border-success" placeholder="e.g. 45000" step="0.01" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Due Date</label>
                                <input type="date" name="due_date" class="form-control border-success" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Initial Status</label>
                            <select name="status" class="form-select border-success" required>
                                <option value="Pending" selected>Pending</option>
                                <option value="Paid">Paid (Already Received)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light p-4 pt-0">
                        <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="bulk_fee" class="btn btn-success rounded-pill px-4 fw-bold"><i class="fa-solid fa-bolt me-2"></i>Generate Bulk Invoices</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add New Fee Modal (Manual Single) -->
    <div class="modal fade" id="addFeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-invoice me-2"></i>Generate Individual Invoice</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4 bg-light">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Student</label>
                            <select name="student_id" class="form-select border-primary" required>
                                <option value="" disabled selected>-- Search & Select Student --</option>
                                <?php foreach($studentsList as $st): ?>
                                    <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?> (<?= htmlspecialchars($st['enrollment_no']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Billing Amount (₹)</label>
                                <input type="number" name="amount" class="form-control border-primary" placeholder="e.g. 55000" step="0.01" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Due Date</label>
                                <input type="date" name="due_date" class="form-control border-primary" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Initial Status</label>
                            <select name="status" class="form-select border-primary" required>
                                <option value="Pending" selected>Pending</option>
                                <option value="Paid">Paid</option>
                                <option value="Overdue">Overdue</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0 p-4 pt-0">
                        <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_fee" class="btn btn-primary rounded-pill px-4 fw-bold"><i class="fa-solid fa-paper-plane me-2"></i>Generate Invoice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // 1. Real-Time Search & Filter Engine
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchData');
            const filterDept = document.getElementById('filterDept');
            const filterStatus = document.getElementById('filterStatus');
            const tableRows = document.querySelectorAll('#dataTableBody .data-row');
            const noRecordsRow = document.getElementById('noRecordsRow');

            function filterTable() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const deptTerm = filterDept.value.toLowerCase();
                const statusTerm = filterStatus.value.toLowerCase();
                let visibleCount = 0;

                tableRows.forEach(row => {
                    const textContent = row.textContent.toLowerCase();
                    const deptText = row.querySelector('.search-dept').textContent.toLowerCase();
                    const statusText = row.querySelector('.search-status').textContent.toLowerCase();

                    const matchesSearch = textContent.includes(searchTerm);
                    const matchesDept = deptTerm === 'all' || deptText.includes(deptTerm);
                    const matchesStatus = statusTerm === 'all' || statusText.includes(statusTerm);

                    if (matchesSearch && matchesDept && matchesStatus) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                noRecordsRow.style.display = (visibleCount === 0) ? 'table-row' : 'none';
            }

            searchInput.addEventListener('input', filterTable);
            filterDept.addEventListener('change', filterTable);
            filterStatus.addEventListener('change', filterTable);
        });

        // 2. Universal Click-to-Sort Engine
        let sortAsc = true;
        let currentSortCol = -1;
        
        function sortTable(colIndex) {
            const tableBody = document.getElementById("dataTableBody");
            const rows = Array.from(tableBody.querySelectorAll("tr.data-row"));
            
            // Toggle direction
            if (currentSortCol === colIndex) {
                sortAsc = !sortAsc;
            } else {
                sortAsc = true;
                currentSortCol = colIndex;
            }

            // Reset UI
            document.querySelectorAll('.sort-icon').forEach(icon => {
                icon.className = 'fa-solid fa-sort ms-1 sort-icon text-muted';
            });
            
            // Activate current icon
            const activeIcon = document.getElementById('sort-icon-' + colIndex);
            if(activeIcon) {
                activeIcon.className = sortAsc ? 'fa-solid fa-sort-up ms-1 sort-icon text-dark' : 'fa-solid fa-sort-down ms-1 sort-icon text-dark';
            }

            // Execute sort
            rows.sort((rowA, rowB) => {
                let cellA = rowA.querySelectorAll("td")[colIndex];
                let cellB = rowB.querySelectorAll("td")[colIndex];
                
                let valA = cellA.getAttribute('data-sort-val') || cellA.textContent.trim().toLowerCase();
                let valB = cellB.getAttribute('data-sort-val') || cellB.textContent.trim().toLowerCase();

                // Numeric fallback
                let numA = parseFloat(valA.replace(/[^0-9.-]+/g,""));
                let numB = parseFloat(valB.replace(/[^0-9.-]+/g,""));
                if (!isNaN(numA) && !isNaN(numB) && (valA.includes("₹") || valA.includes("inv"))) {
                    valA = numA;
                    valB = numB;
                }

                if (valA < valB) return sortAsc ? -1 : 1;
                if (valA > valB) return sortAsc ? 1 : -1;
                return 0;
            });

            // Re-attach sorted
            rows.forEach(row => tableBody.appendChild(row));
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>