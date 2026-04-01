<?php
session_start();
require_once '../../config/db.php';

// Strict Role-Based Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'faculty')) { 
    header("Location: ../../auth/login.php"); 
    exit; 
}

$success = '';
$error = '';

// --- 1. DIRECT ACTION: EXCEL-STYLE BATCH GRADING SAVER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_save'])) {
    $course_id = filter_input(INPUT_POST, 'batch_course_id', FILTER_SANITIZE_NUMBER_INT);
    $internals = $_POST['internal_marks'] ?? [];
    $externals = $_POST['external_marks'] ?? [];

    try {
        $pdo->beginTransaction();
        foreach ($internals as $s_id => $int_mark) {
            $ext_mark = $externals[$s_id] ?? 0;
            
            // Only process if marks are actually entered
            if ($int_mark !== '' && $ext_mark !== '') {
                $check = $pdo->prepare("SELECT id FROM marks WHERE student_id = ? AND course_id = ?");
                $check->execute([$s_id, $course_id]);
                
                if ($check->rowCount() > 0) {
                    $update = $pdo->prepare("UPDATE marks SET internal_marks = ?, external_marks = ? WHERE student_id = ? AND course_id = ?");
                    $update->execute([$int_mark, $ext_mark, $s_id, $course_id]);
                } else {
                    $insert = $pdo->prepare("INSERT INTO marks (student_id, course_id, internal_marks, external_marks) VALUES (?, ?, ?, ?)");
                    $insert->execute([$s_id, $course_id, $int_mark, $ext_mark]);
                }
            }
        }
        $pdo->commit();
        $success = "Batch grading saved successfully! Academic profiles updated globally.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to save batch grades. Database error.";
    }
}

// --- 2. MANUAL CRUD: ADD SINGLE MARK ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_mark'])) {
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_SANITIZE_NUMBER_INT);
    $course_id = filter_input(INPUT_POST, 'course_id', FILTER_SANITIZE_NUMBER_INT);
    $internal = filter_input(INPUT_POST, 'internal_marks', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $external = filter_input(INPUT_POST, 'external_marks', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    try {
        $check = $pdo->prepare("SELECT id FROM marks WHERE student_id = ? AND course_id = ?");
        $check->execute([$student_id, $course_id]);
        if ($check->rowCount() > 0) {
            $error = "Record already exists. Use the edit feature.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO marks (student_id, course_id, internal_marks, external_marks) VALUES (?, ?, ?, ?)");
            $stmt->execute([$student_id, $course_id, $internal, $external]);
            $success = "Manual grading record successfully added.";
        }
    } catch (PDOException $e) {
        $error = "Database Error.";
    }
}

// --- 3. MANUAL CRUD: EDIT SINGLE MARK ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_mark'])) {
    $mark_id = filter_input(INPUT_POST, 'mark_id', FILTER_SANITIZE_NUMBER_INT);
    $internal = filter_input(INPUT_POST, 'internal_marks', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $external = filter_input(INPUT_POST, 'external_marks', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    try {
        $stmt = $pdo->prepare("UPDATE marks SET internal_marks = ?, external_marks = ? WHERE id = ?");
        $stmt->execute([$internal, $external, $mark_id]);
        $success = "Student's specific grade updated successfully.";
    } catch (PDOException $e) {
        $error = "Failed to update record.";
    }
}

// --- 4. MANUAL CRUD: DELETE SINGLE MARK ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_mark'])) {
    $mark_id = filter_input(INPUT_POST, 'mark_id', FILTER_SANITIZE_NUMBER_INT);
    try {
        $stmt = $pdo->prepare("DELETE FROM marks WHERE id = ?");
        $stmt->execute([$mark_id]);
        $success = "Grade permanently removed from transcript.";
    } catch (PDOException $e) {
        $error = "Failed to delete record.";
    }
}

// --- FETCH DATA ---
// Added Phone to Students List
$studentsList = $pdo->query("SELECT s.id as student_id, u.name, u.phone, s.enrollment_no, s.dept, s.semester FROM students s JOIN users u ON s.user_id = u.id ORDER BY s.semester ASC, u.name ASC")->fetchAll();
$coursesList = $pdo->query("SELECT id, name FROM courses ORDER BY name")->fetchAll();

$all_marks = $pdo->query("SELECT m.id as mark_id, m.student_id, m.course_id, c.name as course_name, m.internal_marks, m.external_marks, m.total_marks FROM marks m JOIN courses c ON m.course_id = c.id")->fetchAll();
$marks_by_student = [];
$totalScoreSystemWide = 0; $failingSubjectsCount = 0; $highestScore = 0;

foreach ($all_marks as $mark) {
    $marks_by_student[$mark['student_id']][] = $mark;
    if ($mark['total_marks'] < 40) $failingSubjectsCount++;
    if ($mark['total_marks'] > $highestScore) $highestScore = $mark['total_marks'];
    $totalScoreSystemWide += $mark['total_marks'];
}
$avgScoreSystemWide = count($all_marks) > 0 ? round($totalScoreSystemWide / count($all_marks), 1) : 0;

$distinctDepts = $pdo->query("SELECT DISTINCT dept FROM students")->fetchAll(PDO::FETCH_COLUMN);

// Check if we are in Batch Grading Mode
$batch_mode = isset($_GET['batch_course_id']) ? (int)$_GET['batch_course_id'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enterprise Grading Management | SCMS ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/style.css" rel="stylesheet">
    <style>
        .transcript-table th { background-color: #f8f9fc; color: #4e73df; font-weight: 700; }
        .inline-edit-input { width: 80px; text-align: center; font-weight: bold; }
        .batch-input { max-width: 100px; text-align: center; }
        
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
            <i class="fa-solid fa-arrow-left me-2"></i> SCMS | Advanced Grading System
        </a>
    </nav>

    <div class="container-fluid px-4 mt-4 pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-dark"><i class="fa-solid fa-user-graduate text-primary me-2"></i> Student Evaluation Dashboards</h3>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary shadow-sm fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#batchGradeInitModal">
                    <i class="fa-solid fa-table-cells me-2"></i> Batch Grade Subject
                </button>
            </div>
        </div>

        <?php if($success) echo "<div class='alert alert-success shadow-sm rounded-3 fw-bold'><i class='fa-solid fa-check-circle me-2'></i>$success</div>"; ?>
        <?php if($error) echo "<div class='alert alert-danger shadow-sm rounded-3 fw-bold'><i class='fa-solid fa-triangle-exclamation me-2'></i>$error</div>"; ?>

        <!-- 🔥 BATCH GRADING EXCEL-STYLE VIEW 🔥 -->
        <?php if($batch_mode): 
            $selected_course_name = array_filter($coursesList, fn($c) => $c['id'] == $batch_mode)[array_key_first(array_filter($coursesList, fn($c) => $c['id'] == $batch_mode))]['name'];
            
            // Pre-fetch existing marks for this course to auto-fill the grid
            $existing_course_marks = [];
            foreach($all_marks as $m) {
                if($m['course_id'] == $batch_mode) {
                    $existing_course_marks[$m['student_id']] = $m;
                }
            }
        ?>
            <div class="card border-0 shadow-lg rounded-4 mb-5 border-top border-primary border-5">
                <div class="card-header bg-white border-0 pt-4 pb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold text-primary mb-0"><i class="fa-solid fa-file-excel me-2"></i> Express Batch Grading Mode</h4>
                        <p class="text-muted mb-0 small">Currently grading: <strong><?= htmlspecialchars($selected_course_name) ?></strong></p>
                    </div>
                    <a href="index.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold">Exit Batch Mode</a>
                </div>
                <div class="card-body p-0">
                    <form method="POST">
                        <input type="hidden" name="batch_course_id" value="<?= $batch_mode ?>">
                        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th class="ps-4">Student Details</th>
                                        <th>Enrollment</th>
                                        <th class="text-center">Internal Marks (30)</th>
                                        <th class="text-center">External Marks (70)</th>
                                        <th class="text-center">Previous Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($studentsList as $st): 
                                        $sid = $st['student_id'];
                                        $int_val = isset($existing_course_marks[$sid]) ? $existing_course_marks[$sid]['internal_marks'] : '';
                                        $ext_val = isset($existing_course_marks[$sid]) ? $existing_course_marks[$sid]['external_marks'] : '';
                                        $tot_val = isset($existing_course_marks[$sid]) ? $existing_course_marks[$sid]['total_marks'] : '-';
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($st['name']) ?></td>
                                        <td class="text-muted small"><?= htmlspecialchars($st['enrollment_no']) ?></td>
                                        <td class="text-center">
                                            <input type="number" name="internal_marks[<?= $sid ?>]" class="form-control batch-input mx-auto border-primary" value="<?= $int_val ?>" min="0" max="30" step="0.1" placeholder="--">
                                        </td>
                                        <td class="text-center">
                                            <input type="number" name="external_marks[<?= $sid ?>]" class="form-control batch-input mx-auto border-primary" value="<?= $ext_val ?>" min="0" max="70" step="0.1" placeholder="--">
                                        </td>
                                        <td class="text-center fw-bold <?= $tot_val !== '-' && $tot_val < 40 ? 'text-danger' : 'text-success' ?>"><?= $tot_val ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-4 bg-light text-end rounded-bottom-4">
                            <button type="submit" name="batch_save" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow"><i class="fa-solid fa-floppy-disk me-2"></i> Save Entire Class Batch</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- STANDARD VIEW: Student Academic Profiles (With Search & Filter) -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-0 pt-4 pb-3">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-folder-open text-warning me-2"></i> Student Academic Profiles</h5>
                
                <!-- 🔥 REAL-TIME SEARCH & FILTER ENGINE 🔥 -->
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
                                <th class="ps-4 sortable" onclick="sortTable(0)">Student Name & ID <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-0"></i></th>
                                <th class="sortable" onclick="sortTable(1)">Department & Sem <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-1"></i></th>
                                <th class="sortable" onclick="sortTable(2)">Subjects Evaluated <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-2"></i></th>
                                <th class="sortable" onclick="sortTable(3)">Overall Avg Score <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-3"></i></th>
                                <th class="text-end pe-4">Manage Transcripts</th>
                            </tr>
                        </thead>
                        <tbody id="dataTableBody">
                            <?php foreach($studentsList as $student): 
                                $s_id = $student['student_id'];
                                $student_marks = $marks_by_student[$s_id] ?? [];
                                $subjects_count = count($student_marks);
                                
                                $student_total = 0;
                                foreach($student_marks as $sm) { $student_total += $sm['total_marks']; }
                                $student_avg = $subjects_count > 0 ? round($student_total / $subjects_count, 1) : 0;
                            ?>
                            <tr class="data-row">
                                <td class="ps-4">
                                    <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($student['name']) ?></div>
                                    <div class="small text-muted mt-1">
                                        <i class="fa-solid fa-id-badge text-primary me-1"></i><?= htmlspecialchars($student['enrollment_no']) ?> | 
                                        <i class="fa-solid fa-phone me-1"></i><?= htmlspecialchars($student['phone'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-secondary search-dept"><?= htmlspecialchars($student['dept']) ?></div>
                                    <span class="badge bg-dark-subtle text-dark search-sem">Sem <?= htmlspecialchars($student['semester']) ?></span>
                                </td>
                                <td data-sort-val="<?= $subjects_count ?>">
                                    <span class="badge rounded-pill <?= $subjects_count > 0 ? 'bg-primary' : 'bg-secondary' ?> fs-6 px-3">
                                        <?= $subjects_count ?> Subjects
                                    </span>
                                </td>
                                <td data-sort-val="<?= $student_avg ?>">
                                    <?php if($subjects_count > 0): ?>
                                        <div class="fw-bold <?= $student_avg < 40 ? 'text-danger' : 'text-success' ?> fs-5">
                                            <?= $student_avg ?>%
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-primary shadow-sm rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#transcriptModal<?= $s_id ?>">
                                        <i class="fa-solid fa-folder-open me-2"></i> Open Record
                                    </button>
                                    <a href="marksheet.php?id=<?= $s_id ?>" target="_blank" class="btn btn-warning shadow-sm rounded-circle fw-bold text-dark ms-1" style="width: 38px; height: 38px; padding-top:6px;" title="Print Official Marksheet">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                </td>
                            </tr>

                            <!-- 🚀 ENTERPRISE TRANSCRIPT MODAL (MANUAL CRUD) -->
                            <div class="modal fade" id="transcriptModal<?= $s_id ?>" tabindex="-1">
                                <div class="modal-dialog modal-xl">
                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                        <div class="modal-header bg-dark text-white border-0 py-3 rounded-top-4">
                                            <div>
                                                <h4 class="modal-title fw-bold mb-0"><i class="fa-solid fa-file-contract me-2"></i> Official Grading Transcript</h4>
                                                <div class="small text-white-50 mt-1"><?= htmlspecialchars($student['name']) ?> | <?= htmlspecialchars($student['enrollment_no']) ?></div>
                                            </div>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4 bg-light">
                                            
                                            <!-- Existing Marks Table (Inline Edit & Delete) -->
                                            <div class="card border-0 shadow-sm mb-4">
                                                <div class="card-header bg-white fw-bold text-primary"><i class="fa-solid fa-list-check me-2"></i> Evaluated Subjects</div>
                                                <div class="card-body p-0">
                                                    <table class="table table-hover transcript-table mb-0 align-middle text-center">
                                                        <thead>
                                                            <tr>
                                                                <th class="text-start ps-3">Course / Subject</th>
                                                                <th>Internal (30)</th>
                                                                <th>External (70)</th>
                                                                <th>Total (100)</th>
                                                                <th>Status</th>
                                                                <th class="text-end pe-3">Manual Override</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if(empty($student_marks)): ?>
                                                                <tr><td colspan="6" class="py-4 text-muted fst-italic">No subjects graded for this student yet.</td></tr>
                                                            <?php else: ?>
                                                                <?php foreach($student_marks as $mark): 
                                                                    $isFail = $mark['total_marks'] < 40;
                                                                ?>
                                                                <tr>
                                                                    <form method="POST">
                                                                        <input type="hidden" name="mark_id" value="<?= $mark['mark_id'] ?>">
                                                                        <td class="text-start ps-3 fw-bold text-dark"><?= htmlspecialchars($mark['course_name']) ?></td>
                                                                        <td><input type="number" name="internal_marks" value="<?= $mark['internal_marks'] ?>" class="form-control form-control-sm inline-edit-input mx-auto border-primary" max="30" step="0.1" required></td>
                                                                        <td><input type="number" name="external_marks" value="<?= $mark['external_marks'] ?>" class="form-control form-control-sm inline-edit-input mx-auto border-primary" max="70" step="0.1" required></td>
                                                                        <td class="fw-bold fs-6 <?= $isFail ? 'text-danger' : 'text-success' ?>"><?= $mark['total_marks'] ?></td>
                                                                        <td>
                                                                            <?php if($isFail): ?> <span class="badge bg-danger rounded-pill">Fail</span>
                                                                            <?php else: ?> <span class="badge bg-success rounded-pill">Pass</span> <?php endif; ?>
                                                                        </td>
                                                                        <td class="text-end pe-3">
                                                                            <button type="submit" name="edit_mark" class="btn btn-sm btn-success" title="Update Marks"><i class="fa-solid fa-check"></i></button>
                                                                            <button type="submit" name="delete_mark" class="btn btn-sm btn-danger ms-1" onclick="return confirm('Remove this subject grade?');" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                                                        </td>
                                                                    </form>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <!-- Manual Add Single Subject Form -->
                                            <div class="card border-0 shadow-sm border-start border-primary border-4">
                                                <div class="card-body">
                                                    <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-plus-circle text-primary me-2"></i> Manual Single Entry Addition</h6>
                                                    <form method="POST" class="row g-3 align-items-end">
                                                        <input type="hidden" name="student_id" value="<?= $s_id ?>">
                                                        <div class="col-md-5">
                                                            <label class="form-label small fw-bold text-muted">Select Subject</label>
                                                            <select name="course_id" class="form-select form-select-sm border-primary" required>
                                                                <option value="" disabled selected>-- Choose Subject --</option>
                                                                <?php foreach($coursesList as $cr): ?>
                                                                    <option value="<?= $cr['id'] ?>"><?= htmlspecialchars($cr['name']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label small fw-bold text-muted">Internal (30)</label>
                                                            <input type="number" name="internal_marks" class="form-control form-control-sm border-primary" min="0" max="30" step="0.1" required>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label small fw-bold text-muted">External (70)</label>
                                                            <input type="number" name="external_marks" class="form-control form-control-sm border-primary" min="0" max="70" step="0.1" required>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <button type="submit" name="add_mark" class="btn btn-sm btn-primary fw-bold w-100 py-2 rounded-pill shadow-sm">Submit Record</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <tr id="noRecordsRow" style="display: <?= empty($studentsList) ? 'table-row' : 'none' ?>;">
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-user-graduate fa-3x mb-3 opacity-50"></i>
                                    <p class="mb-0 fw-bold" id="noRecordsText">No academic profiles found matching your search.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal to Init Batch Grading -->
    <div class="modal fade" id="batchGradeInitModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-table-cells me-2"></i>Initialize Batch Grading</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="GET">
                    <div class="modal-body p-4 bg-light">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Select Subject to Grade</label>
                            <select name="batch_course_id" class="form-select form-select-lg border-primary" required>
                                <option value="" disabled selected>-- Choose Subject --</option>
                                <?php foreach($coursesList as $cr): ?>
                                    <option value="<?= $cr['id'] ?>"><?= htmlspecialchars($cr['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="text-muted small mb-0"><i class="fa-solid fa-circle-info me-1"></i> This will open an Excel-style grid allowing you to input marks for every student simultaneously.</p>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0 bg-light">
                        <button type="button" class="btn btn-secondary px-4 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-5 rounded-pill fw-bold">Open Batch Grid</button>
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
                let cellA = a.querySelectorAll("td")[col];
                let cellB = b.querySelectorAll("td")[col];
                
                let vA = cellA.getAttribute('data-sort-val') || cellA.innerText.trim().toLowerCase();
                let vB = cellB.getAttribute('data-sort-val') || cellB.innerText.trim().toLowerCase();
                
                // Numeric Fallback
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