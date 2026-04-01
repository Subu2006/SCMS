<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$user_role = $_SESSION['role'];
$logged_in_user_id = $_SESSION['user_id'];

// Determine which student's marksheet to show
$student_id = null;

if ($user_role === 'student') {
    // If student, fetch their own student_id
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$logged_in_user_id]);
    $student_id = $stmt->fetchColumn();
} else {
    // If admin/faculty, they must pass the student_id via URL (e.g., marksheet.php?id=1)
    if (!isset($_GET['id'])) {
        die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'><h3>Error: No Student Selected.</h3><a href='index.php'>Go Back</a></div>");
    }
    $student_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
}

// Fetch Student Details
$stmt = $pdo->prepare("
    SELECT s.*, u.name, u.email 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    die("Student record not found.");
}

// Fetch Marks Details
$stmt = $pdo->prepare("
    SELECT c.name as subject_name, c.credits, m.internal_marks, m.external_marks, m.total_marks
    FROM marks m
    JOIN courses c ON m.course_id = c.id
    WHERE m.student_id = ?
");
$stmt->execute([$student_id]);
$marks = $stmt->fetchAll();

// Calculations
$total_obtained = 0;
$max_possible = count($marks) * 100;
$has_failed = false;

foreach ($marks as $m) {
    $total_obtained += $m['total_marks'];
    if ($m['total_marks'] < 40) {
        $has_failed = true;
    }
}

$percentage = $max_possible > 0 ? round(($total_obtained / $max_possible) * 100, 2) : 0;

$final_status = "PASS";
$status_color = "text-success";
if ($has_failed || $percentage < 40) {
    $final_status = "FAIL";
    $status_color = "text-danger";
} elseif ($percentage >= 75) {
    $final_status = "PASS WITH DISTINCTION";
    $status_color = "text-primary";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($student['enrollment_no']) ?>_Marksheet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Times New Roman', serif; }
        .marksheet-container {
            max-width: 850px;
            margin: 40px auto;
            background: #fff;
            padding: 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid #ddd;
            position: relative;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 8rem;
            color: rgba(0,0,0,0.03);
            z-index: 0;
            pointer-events: none;
            white-space: nowrap;
            font-weight: bold;
        }
        .header-section { border-bottom: 3px double #333; padding-bottom: 20px; margin-bottom: 30px; position: relative; z-index: 1; }
        .college-title { font-size: 2.2rem; font-weight: bold; color: #1a237e; text-transform: uppercase; letter-spacing: 2px; }
        .sub-title { font-size: 1.2rem; color: #555; }
        .document-title { text-align: center; font-size: 1.5rem; font-weight: bold; margin-bottom: 30px; text-transform: uppercase; text-decoration: underline; position: relative; z-index: 1; }
        .info-table { width: 100%; margin-bottom: 30px; position: relative; z-index: 1; font-size: 1.1rem; }
        .info-table td { padding: 5px 10px; }
        .marks-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; position: relative; z-index: 1; }
        .marks-table th, .marks-table td { border: 1px solid #444; padding: 12px; text-align: center; }
        .marks-table th { background-color: #f8f9fa; font-weight: bold; text-transform: uppercase; }
        .marks-table .text-start { text-align: left; }
        .footer-section { margin-top: 60px; display: flex; justify-content: space-between; position: relative; z-index: 1; }
        .signature-box { text-align: center; width: 200px; }
        .signature-line { border-top: 1px solid #000; margin-top: 40px; padding-top: 5px; font-weight: bold; }
        
        .action-buttons { text-align: center; margin-bottom: 30px; }
        
        /* Print Specific CSS */
        @media print {
            body { background-color: #fff; }
            .marksheet-container { box-shadow: none; border: none; margin: 0; padding: 20px; max-width: 100%; }
            .action-buttons { display: none !important; }
            nav { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- Non-Printable Header Actions -->
    <div class="action-buttons pt-4">
        <a href="../../dashboard/<?= $user_role === 'student' ? 'student' : 'index' ?>.php" class="btn btn-dark rounded-pill px-4 me-2"><i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard</a>
        <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow"><i class="fa-solid fa-print me-2"></i> Print / Save as PDF</button>
    </div>

    <div class="marksheet-container">
        <!-- Background Watermark -->
        <div class="watermark">SCMS OFFICIAL</div>

        <!-- Header -->
        <div class="header-section text-center">
            <div class="college-title">Smart College of Engineering</div>
            <div class="sub-title">Affiliated to the National Technical University, Recognized by AICTE</div>
            <div class="mt-2 text-muted">123 Education Boulevard, Tech City - 100010</div>
        </div>

        <div class="document-title">Official Academic Transcript</div>

        <!-- Student Info -->
        <table class="info-table">
            <tr>
                <td><strong>Student Name:</strong> <?= htmlspecialchars($student['name']) ?></td>
                <td><strong>Enrollment No:</strong> <?= htmlspecialchars($student['enrollment_no']) ?></td>
            </tr>
            <tr>
                <td><strong>Department:</strong> <?= htmlspecialchars($student['dept']) ?></td>
                <td><strong>Semester:</strong> <?= htmlspecialchars($student['semester']) ?></td>
            </tr>
        </table>

        <!-- Marks Grid -->
        <table class="marks-table">
            <thead>
                <tr>
                    <th rowspan="2" class="text-start">Course / Subject Name</th>
                    <th colspan="3">Marks Obtained</th>
                    <th rowspan="2">Max Marks</th>
                </tr>
                <tr>
                    <th>Internal (30)</th>
                    <th>External (70)</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($marks)): ?>
                    <tr><td colspan="5" style="padding: 30px;">No grading records available for this semester.</td></tr>
                <?php else: ?>
                    <?php foreach($marks as $m): ?>
                    <tr>
                        <td class="text-start fw-bold"><?= htmlspecialchars($m['subject_name']) ?></td>
                        <td><?= $m['internal_marks'] ?></td>
                        <td><?= $m['external_marks'] ?></td>
                        <td class="fw-bold"><?= $m['total_marks'] ?></td>
                        <td>100</td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Totals Row -->
                    <tr style="background-color: #f8f9fa; font-weight: bold; font-size: 1.1rem;">
                        <td colspan="3" class="text-end">GRAND TOTAL</td>
                        <td><?= $total_obtained ?></td>
                        <td><?= $max_possible ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Final Result Section -->
        <div style="border: 1px solid #444; padding: 15px; margin-top: 20px; font-size: 1.1rem;">
            <div class="row text-center">
                <div class="col-6 border-end border-dark">
                    <strong>Percentage:</strong> <?= $percentage ?>%
                </div>
                <div class="col-6 fw-bold">
                    <strong>Result:</strong> <span class="<?= $status_color ?>"><?= $final_status ?></span>
                </div>
            </div>
        </div>

        <div style="margin-top: 15px; font-size: 0.9rem; color: #666;">
            * Note: Minimum pass mark for each subject is 40. This is a system-generated document and does not require a physical signature for digital validation.
        </div>

        <!-- Signatures -->
        <div class="footer-section">
            <div class="signature-box">
                <div class="signature-line">Date of Issue<br><span style="font-weight:normal; font-size:0.9rem;"><?= date('d-m-Y') ?></span></div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Controller of Examinations</div>
            </div>
        </div>

    </div>
</body>
</html>