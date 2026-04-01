<?php
session_start();
require_once '../config/db.php';

// Strict Security - Only Admins get access to global Business Intelligence
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// --- 1. FINANCIAL ANALYTICS ---
$feeStats = $pdo->query("SELECT status, SUM(amount) as total FROM fees GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
$revenueData = ['Paid' => 0, 'Pending' => 0, 'Overdue' => 0];
foreach($feeStats as $fs) { $revenueData[$fs['status']] = (float)$fs['total']; }

// --- 2. ACADEMIC ANALYTICS (Department Attendance) ---
$attStats = $pdo->query("
    SELECT s.dept, AVG(a.attended_classes / NULLIF(a.total_classes, 0) * 100) as avg_att 
    FROM attendance a 
    JOIN students s ON a.student_id = s.id 
    GROUP BY s.dept
")->fetchAll(PDO::FETCH_ASSOC);
$deptLabels = []; $deptAttData = [];
foreach($attStats as $att) { 
    $deptLabels[] = $att['dept']; 
    $deptAttData[] = round($att['avg_att'], 1); 
}

// --- 3. PERFORMANCE ANALYTICS (Semester Average Marks) ---
$markStats = $pdo->query("
    SELECT s.semester, AVG(m.total_marks) as avg_marks 
    FROM marks m 
    JOIN students s ON m.student_id = s.id 
    GROUP BY s.semester ORDER BY s.semester ASC
")->fetchAll(PDO::FETCH_ASSOC);
$semLabels = []; $semMarksData = [];
foreach($markStats as $ms) { 
    $semLabels[] = "Semester " . $ms['semester']; 
    $semMarksData[] = round($ms['avg_marks'], 1); 
}

// --- 4. SYSTEM HEALTH KPIs ---
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$healthScore = 100;
if ($revenueData['Overdue'] > 50000) $healthScore -= 10;
if (count($deptAttData) > 0 && (array_sum($deptAttData)/count($deptAttData)) < 75) $healthScore -= 15;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enterprise BI & Analytics | SCMS ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/style.css" rel="stylesheet">
    <!-- Chart.js for God-Level Graphs -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container { position: relative; height: 300px; width: 100%; }
        .print-btn { position: fixed; bottom: 30px; right: 30px; z-index: 999; box-shadow: 0 .5rem 1rem rgba(0,0,0,.15); }
        @media print {
            .navbar, .print-btn { display: none !important; }
            .card { border: 1px solid #ddd !important; box-shadow: none !important; break-inside: avoid; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark px-4 shadow-sm">
        <a class="navbar-brand fw-bold" href="../dashboard/index.php">
            <i class="fa-solid fa-arrow-left me-2"></i> SCMS | Enterprise Business Intelligence
        </a>
    </nav>

    <div class="container-fluid px-4 mt-4 pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-0"><i class="fa-solid fa-chart-pie text-primary me-2"></i> Global Analytics Engine</h2>
                <p class="text-muted">Real-time overview of financial health, academic trends, and demographic distribution.</p>
            </div>
            <button class="btn btn-outline-dark fw-bold rounded-pill px-4" onclick="window.print()">
                <i class="fa-solid fa-print me-2"></i> Export Report
            </button>
        </div>

        <!-- Global Health KPIs -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm bg-gradient text-white h-100 rounded-4" style="background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 fw-bold text-uppercase mb-1 small">Total Accounts</div>
                            <h2 class="mb-0 fw-bold"><?= $totalUsers ?></h2>
                        </div>
                        <i class="fa-solid fa-users fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm bg-gradient text-white h-100 rounded-4" style="background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 fw-bold text-uppercase mb-1 small">Total Collections</div>
                            <h2 class="mb-0 fw-bold">₹<?= number_format($revenueData['Paid'] / 1000, 1) ?>K</h2>
                        </div>
                        <i class="fa-solid fa-wallet fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm bg-gradient text-white h-100 rounded-4" style="background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%);">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 fw-bold text-uppercase mb-1 small">Total Overdue Risk</div>
                            <h2 class="mb-0 fw-bold">₹<?= number_format($revenueData['Overdue'] / 1000, 1) ?>K</h2>
                        </div>
                        <i class="fa-solid fa-triangle-exclamation fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm bg-gradient text-white h-100 rounded-4" style="background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 fw-bold text-uppercase mb-1 small">ERP Health Score</div>
                            <h2 class="mb-0 fw-bold"><?= $healthScore ?>/100</h2>
                        </div>
                        <i class="fa-solid fa-heart-pulse fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Financial Chart -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h6 class="fw-bold text-success"><i class="fa-solid fa-chart-pie me-2"></i> Financial Distribution</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="financeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Department Attendance Chart -->
            <div class="col-md-8">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h6 class="fw-bold text-primary"><i class="fa-solid fa-chart-column me-2"></i> Department Average Attendance (%)</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Semester Performance Chart -->
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h6 class="fw-bold text-info"><i class="fa-solid fa-chart-line me-2"></i> Academic Performance Curve (By Semester)</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height: 350px;">
                            <canvas id="performanceChartBI"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Print Button -->
    <button class="btn btn-primary btn-lg rounded-circle print-btn" style="width: 60px; height: 60px;" onclick="window.print()" title="Print Executive Report">
        <i class="fa-solid fa-print"></i>
    </button>

    <script>
        // Set global chart defaults for modern look
        Chart.defaults.font.family = "'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
        Chart.defaults.color = '#858796';

        // 1. Financial Doughnut Chart
        new Chart(document.getElementById('financeChart'), {
            type: 'doughnut',
            data: {
                labels: ['Paid Revenue', 'Pending Expected', 'Overdue Debt'],
                datasets: [{
                    data: [<?= $revenueData['Paid'] ?>, <?= $revenueData['Pending'] ?>, <?= $revenueData['Overdue'] ?>],
                    backgroundColor: ['#1cc88a', '#f6c23e', '#e74a3b'],
                    hoverBackgroundColor: ['#17a673', '#dda20a', '#e02d1b'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }]
            },
            options: {
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // 2. Department Attendance Bar Chart
        new Chart(document.getElementById('attendanceChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($deptLabels) ?>,
                datasets: [{
                    label: 'Avg Attendance %',
                    data: <?= json_encode($deptAttData) ?>,
                    backgroundColor: '#4e73df',
                    hoverBackgroundColor: '#2e59d9',
                    borderRadius: 4
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, max: 100 } },
                plugins: { legend: { display: false } }
            }
        });

        // 3. Semester Performance Line Chart
        new Chart(document.getElementById('performanceChartBI'), {
            type: 'line',
            data: {
                labels: <?= json_encode($semLabels) ?>,
                datasets: [{
                    label: 'Average Class Marks',
                    data: <?= json_encode($semMarksData) ?>,
                    borderColor: '#36b9cc',
                    backgroundColor: 'rgba(54, 185, 204, 0.1)',
                    pointBackgroundColor: '#36b9cc',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#36b9cc',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, max: 100 } }
            }
        });
    </script>
</body>
</html>