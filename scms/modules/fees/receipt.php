<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("Error: No Invoice ID provided.");
}

$fee_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Fetch specific fee and join student info
$stmt = $pdo->prepare("
    SELECT f.*, s.enrollment_no, s.dept, s.semester, u.name, u.email, u.phone, s.user_id as student_user_id
    FROM fees f
    JOIN students s ON f.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE f.id = ?
");
$stmt->execute([$fee_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die("Invoice not found.");
}

// Security: If user is a student, they can only view THEIR OWN invoices
if ($user_role === 'student' && $invoice['student_user_id'] != $user_id) {
    die("Security Error: Unauthorized access to this financial document.");
}

$invoice_number = "INV-" . date('Y') . "-" . str_pad($invoice['id'], 5, '0', STR_PAD_LEFT);
$status_color = $invoice['status'] === 'Paid' ? '#1cc88a' : ($invoice['status'] === 'Overdue' ? '#e74a3b' : '#f6c23e');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $invoice_number ?> | Official Receipt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Inter', sans-serif; }
        .receipt-container {
            max-width: 800px; margin: 40px auto; background: #fff; padding: 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08); border-top: 8px solid <?= $status_color ?>;
            position: relative;
        }
        .watermark {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 8rem; color: <?= $status_color ?>; opacity: 0.05; z-index: 0; pointer-events: none;
            font-weight: 900; text-transform: uppercase;
        }
        .header-content, .body-content { position: relative; z-index: 1; }
        .status-stamp {
            border: 3px solid <?= $status_color ?>; color: <?= $status_color ?>;
            padding: 10px 20px; font-weight: 900; font-size: 1.5rem; text-transform: uppercase;
            border-radius: 8px; display: inline-block; transform: rotate(-15deg);
            position: absolute; right: 50px; top: 80px; opacity: 0.8;
        }
        @media print {
            body { background: #fff; }
            .receipt-container { box-shadow: none; margin: 0; padding: 20px; border-top: 5px solid <?= $status_color ?> !important; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="text-center mt-4 no-print">
        <button onclick="window.history.back()" class="btn btn-dark rounded-pill px-4 me-2 fw-bold"><i class="fa-solid fa-arrow-left me-2"></i> Go Back</button>
        <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 fw-bold shadow"><i class="fa-solid fa-print me-2"></i> Print / Save PDF</button>
    </div>

    <div class="receipt-container">
        <div class="watermark"><?= $invoice['status'] ?></div>
        <div class="status-stamp"><?= $invoice['status'] ?></div>

        <div class="header-content d-flex justify-content-between align-items-center border-bottom pb-4 mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-0">SCMS Enterprise</h2>
                <div class="text-muted small">Smart College Management System</div>
                <div class="text-muted small">123 Tech Boulevard, Global City</div>
            </div>
            <div class="text-end">
                <h3 class="fw-bold text-secondary mb-0">INVOICE</h3>
                <div class="fw-bold text-dark"><?= $invoice_number ?></div>
                <div class="small text-muted">Issue Date: <?= date('d M Y') ?></div>
            </div>
        </div>

        <div class="body-content row mb-5">
            <div class="col-sm-6">
                <div class="text-muted small fw-bold text-uppercase mb-2">Billed To:</div>
                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($invoice['name']) ?></h5>
                <div class="text-dark"><i class="fa-solid fa-id-badge text-muted me-2"></i><?= htmlspecialchars($invoice['enrollment_no']) ?></div>
                <div class="text-dark"><i class="fa-solid fa-building text-muted me-2"></i><?= htmlspecialchars($invoice['dept']) ?> (Sem <?= $invoice['semester'] ?>)</div>
                <div class="text-dark"><i class="fa-solid fa-envelope text-muted me-2"></i><?= htmlspecialchars($invoice['email']) ?></div>
            </div>
            <div class="col-sm-6 text-end">
                <div class="text-muted small fw-bold text-uppercase mb-2">Payment Details:</div>
                <div class="mb-1"><strong>Due Date:</strong> <?= date('d F Y', strtotime($invoice['due_date'])) ?></div>
                <div><strong>Status:</strong> <span style="color: <?= $status_color ?>; font-weight: bold;"><?= strtoupper($invoice['status']) ?></span></div>
            </div>
        </div>

        <table class="table table-bordered body-content mb-5">
            <thead class="table-light">
                <tr>
                    <th>Description</th>
                    <th class="text-end">Amount (INR)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="py-3">
                        <div class="fw-bold">Semester Academic Tuition Fee</div>
                        <div class="small text-muted">Charges for Semester <?= $invoice['semester'] ?> - <?= htmlspecialchars($invoice['dept']) ?></div>
                    </td>
                    <td class="text-end py-3 fw-bold">₹<?= number_format($invoice['amount'], 2) ?></td>
                </tr>
                <tr class="table-dark">
                    <td class="text-end fw-bold">GRAND TOTAL DUE</td>
                    <td class="text-end fw-bold fs-5">₹<?= number_format($invoice['amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="body-content text-center mt-5 pt-4 border-top text-muted small">
            <p class="mb-1">This is a system-generated invoice. For payment queries, contact finance@scms.edu.</p>
            <p class="fw-bold">Thank you for your prompt payment.</p>
        </div>
    </div>
</body>
</html>