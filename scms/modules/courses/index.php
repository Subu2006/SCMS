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

// --- 1. DIRECT ACTION: 1-CLICK FACULTY REASSIGNMENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_reassign'])) {
    $course_id = filter_input(INPUT_POST, 'target_course_id', FILTER_SANITIZE_NUMBER_INT);
    $new_faculty_id = filter_input(INPUT_POST, 'new_faculty_id', FILTER_SANITIZE_NUMBER_INT);
    $new_faculty_id = !empty($new_faculty_id) ? $new_faculty_id : null;

    try {
        $stmt = $pdo->prepare("UPDATE courses SET faculty_id = ? WHERE id = ?");
        $stmt->execute([$new_faculty_id, $course_id]);
        $success = "Subject successfully reassigned to the selected Faculty member.";
    } catch (PDOException $e) {
        $error = "Failed to reassign faculty.";
    }
}

// --- 2. MANUAL CRUD: ADD COURSE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $credits = filter_input(INPUT_POST, 'credits', FILTER_SANITIZE_NUMBER_INT);
    $faculty_id = filter_input(INPUT_POST, 'faculty_id', FILTER_SANITIZE_NUMBER_INT);
    $faculty_id = !empty($faculty_id) ? $faculty_id : null;

    try {
        $stmt = $pdo->prepare("INSERT INTO courses (name, credits, faculty_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $credits, $faculty_id]);
        $success = "New academic subject formally added to the curriculum.";
    } catch (PDOException $e) {
        $error = "Failed to add course. It may already exist.";
    }
}

// --- 3. MANUAL CRUD: EDIT COURSE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_course'])) {
    $course_id = filter_input(INPUT_POST, 'course_id', FILTER_SANITIZE_NUMBER_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $credits = filter_input(INPUT_POST, 'credits', FILTER_SANITIZE_NUMBER_INT);
    $faculty_id = filter_input(INPUT_POST, 'faculty_id', FILTER_SANITIZE_NUMBER_INT);
    $faculty_id = !empty($faculty_id) ? $faculty_id : null;

    try {
        $stmt = $pdo->prepare("UPDATE courses SET name = ?, credits = ?, faculty_id = ? WHERE id = ?");
        $stmt->execute([$name, $credits, $faculty_id, $course_id]);
        $success = "Course parameters updated successfully.";
    } catch (PDOException $e) {
        $error = "Failed to update course details.";
    }
}

// --- 4. MANUAL CRUD: DELETE COURSE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {
    $course_id = filter_input(INPUT_POST, 'course_id', FILTER_SANITIZE_NUMBER_INT);
    try {
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $success = "Subject completely removed from the academic syllabus.";
    } catch (PDOException $e) {
        $error = "Failed to delete. Remove associated marks and attendance first to maintain data integrity.";
    }
}

// --- 5. FETCH DATA FOR UI ---
$courses = $pdo->query("
    SELECT c.id as course_id, c.name as course_name, c.credits, f.id as faculty_id, u.name as faculty_name, f.department as faculty_dept
    FROM courses c 
    LEFT JOIN faculty f ON c.faculty_id = f.id 
    LEFT JOIN users u ON f.user_id = u.id
    ORDER BY c.name ASC
")->fetchAll();

$facultyList = $pdo->query("SELECT f.id, u.name, f.department FROM faculty f JOIN users u ON f.user_id = u.id ORDER BY u.name")->fetchAll();

// KPIs
$totalCourses = count($courses);
$totalCredits = 0;
$unassignedCount = 0;
$distinctCredits = [];
foreach($courses as $c) {
    $totalCredits += $c['credits'];
    if(empty($c['faculty_id'])) $unassignedCount++;
    if(!in_array($c['credits'], $distinctCredits)) $distinctCredits[] = $c['credits'];
}
sort($distinctCredits);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Course Management | SCMS ERP</title>
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
        <a class="navbar-brand fw-bold" href="../../dashboard/index.php">
            <i class="fa-solid fa-arrow-left me-2"></i> SCMS | Curriculum Management
        </a>
    </nav>

    <div class="container-fluid px-4 mt-4 pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-dark"><i class="fa-solid fa-book-open-reader text-primary me-2"></i> Academic Subject Master</h3>
            <button class="btn btn-primary shadow-sm fw-bold px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                <i class="fa-solid fa-plus me-2"></i> Add New Subject
            </button>
        </div>

        <!-- Academic KPIs -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-primary border-4 h-100 rounded-4">
                    <div class="card-body">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Total Active Subjects</div>
                        <h3 class="mb-0 fw-bold text-dark"><?= $totalCourses ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-success border-4 h-100 rounded-4">
                    <div class="card-body">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Total Academic Credits</div>
                        <h3 class="mb-0 fw-bold text-success"><?= $totalCredits ?> <span class="fs-6 text-muted">Cr.</span></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-warning border-4 h-100 rounded-4">
                    <div class="card-body">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Unassigned Subjects</div>
                        <h3 class="mb-0 fw-bold <?= $unassignedCount > 0 ? 'text-danger' : 'text-dark' ?>"><?= $unassignedCount ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <?php if($success) echo "<div class='alert alert-success shadow-sm rounded-3 fw-bold'><i class='fa-solid fa-check-circle me-2'></i>$success</div>"; ?>
        <?php if($error) echo "<div class='alert alert-danger shadow-sm rounded-3 fw-bold'><i class='fa-solid fa-triangle-exclamation me-2'></i>$error</div>"; ?>

        <!-- Main Data Table with Search/Sort -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-0 pt-4 pb-3">
                <div class="row g-3 bg-light p-3 rounded-3 border">
                    <div class="col-md-6">
                        <div class="input-group search-wrapper">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="searchData" class="form-control border-start-0 ps-0" placeholder="Search Subject Name or Faculty...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-star text-muted"></i></span>
                            <select id="filterCredits" class="form-select border-start-0 ps-0">
                                <option value="all">All Credits</option>
                                <?php foreach($distinctCredits as $cr): ?>
                                    <option value="<?= $cr ?>"><?= $cr ?> Credits</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-filter text-muted"></i></span>
                            <select id="filterStatus" class="form-select border-start-0 ps-0">
                                <option value="all">All Assignment Status</option>
                                <option value="assigned">Assigned Only</option>
                                <option value="unassigned">Unassigned Only</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="coursesTable">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-4 sortable" onclick="sortTable(0)">Subject Title <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-0"></i></th>
                                <th class="sortable" onclick="sortTable(1)">Credit Weight <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-1"></i></th>
                                <th class="sortable" onclick="sortTable(2)">Assigned Faculty <i class="fa-solid fa-sort ms-1 sort-icon text-muted" id="sort-icon-2"></i></th>
                                <th class="text-end pe-4">Manual Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dataTableBody">
                            <?php foreach($courses as $c): 
                                $isUnassigned = empty($c['faculty_id']);
                            ?>
                            <tr class="data-row">
                                <td class="ps-4 fw-bold text-dark fs-6 search-title"><?= htmlspecialchars($c['course_name']) ?></td>
                                <td class="search-credits" data-sort-val="<?= $c['credits'] ?>"><span class="badge bg-secondary rounded-pill px-3"><?= $c['credits'] ?> Credits</span></td>
                                
                                <!-- 🔥 1-CLICK QUICK REASSIGNMENT UI 🔥 -->
                                <td class="search-faculty" data-status="<?= $isUnassigned ? 'unassigned' : 'assigned' ?>" data-sort-val="<?= $isUnassigned ? 'zzz' : htmlspecialchars($c['faculty_name']) ?>">
                                    <form method="POST" class="d-flex align-items-center gap-2 m-0 p-0">
                                        <input type="hidden" name="target_course_id" value="<?= $c['course_id'] ?>">
                                        <select name="new_faculty_id" class="form-select form-select-sm <?= $isUnassigned ? 'border-danger text-danger' : 'border-primary' ?> fw-semibold" style="width: auto; min-width: 220px;" onchange="this.form.submit()">
                                            <option value="" <?= $isUnassigned ? 'selected' : '' ?>>-- Unassigned --</option>
                                            <?php foreach($facultyList as $f): ?>
                                                <option value="<?= $f['id'] ?>" <?= $c['faculty_id'] == $f['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($f['name']) ?> (<?= htmlspecialchars($f['department']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="quick_reassign" value="1">
                                    </form>
                                    <span class="d-none"><?= htmlspecialchars($c['faculty_name'] ?? 'Unassigned') ?></span> <!-- Hidden for text search -->
                                </td>
                                
                                <td class="text-end pe-4">
                                    <!-- Edit Button -->
                                    <button class="btn btn-sm btn-outline-primary rounded-circle px-0 me-1" style="width: 32px; height: 32px;" data-bs-toggle="modal" data-bs-target="#editCourseModal<?= $c['course_id'] ?>" title="Edit Subject Details">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <!-- Delete Button -->
                                    <form method="POST" class="d-inline" onsubmit="return confirm('WARNING: Deleting a subject might fail if students currently have marks/attendance for it. Continue?');">
                                        <input type="hidden" name="course_id" value="<?= $c['course_id'] ?>">
                                        <button type="submit" name="delete_course" class="btn btn-sm btn-outline-danger rounded-circle px-0" style="width: 32px; height: 32px;" title="Purge Subject">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            <!-- 🚀 EDIT COURSE MODAL 🚀 -->
                            <div class="modal fade" id="editCourseModal<?= $c['course_id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                        <div class="modal-header bg-dark text-white border-0 py-3">
                                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-book-open me-2"></i>Edit Subject Parameters</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body p-4 text-start bg-light">
                                                <input type="hidden" name="course_id" value="<?= $c['course_id'] ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold text-dark">Subject Name</label>
                                                    <input type="text" name="name" class="form-control border-primary" value="<?= htmlspecialchars($c['course_name']) ?>" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold text-dark">Academic Credits</label>
                                                    <input type="number" name="credits" class="form-control border-primary" value="<?= $c['credits'] ?>" min="1" max="10" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold text-dark">Primary Faculty</label>
                                                    <select name="faculty_id" class="form-select border-primary">
                                                        <option value="">-- Unassigned --</option>
                                                        <?php foreach($facultyList as $f): ?>
                                                            <option value="<?= $f['id'] ?>" <?= $c['faculty_id'] == $f['id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($f['name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 bg-light p-4 pt-0">
                                                <button type="button" class="btn btn-secondary px-4 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="edit_course" class="btn btn-primary px-5 rounded-pill fw-bold"><i class="fa-solid fa-floppy-disk me-2"></i>Save Setup</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <tr id="noRecordsRow" style="display: <?= empty($courses) ? 'table-row' : 'none' ?>;">
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-book-open-reader fa-3x mb-3 opacity-50"></i>
                                    <p class="mb-0 fw-bold" id="noRecordsText">No subjects found matching your search.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ADD COURSE MODAL -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-plus me-2"></i>Add Academic Subject</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4 bg-light">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Subject Name</label>
                            <input type="text" name="name" class="form-control border-primary" placeholder="e.g. Artificial Intelligence" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Academic Credits</label>
                            <input type="number" name="credits" class="form-control border-primary" value="4" min="1" max="10" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Assign Faculty (Optional)</label>
                            <select name="faculty_id" class="form-select border-primary">
                                <option value="" selected>-- Assign Later --</option>
                                <?php foreach($facultyList as $f): ?>
                                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?> (<?= htmlspecialchars($f['department']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light p-4 pt-0">
                        <button type="button" class="btn btn-secondary px-4 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_course" class="btn btn-primary px-5 rounded-pill fw-bold w-100"><i class="fa-solid fa-book-medical me-2"></i>Provision Subject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // 1. Real-Time Search & Filter Engine
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchData');
            const filterCredits = document.getElementById('filterCredits');
            const filterStatus = document.getElementById('filterStatus');
            const tableRows = document.querySelectorAll('#dataTableBody .data-row');
            const noRecordsRow = document.getElementById('noRecordsRow');

            function filterTable() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const credTerm = filterCredits.value;
                const statusTerm = filterStatus.value;
                let visibleCount = 0;

                tableRows.forEach(row => {
                    const textContent = row.textContent.toLowerCase();
                    const credVal = row.querySelector('.search-credits').getAttribute('data-sort-val');
                    const statusVal = row.querySelector('.search-faculty').getAttribute('data-status');

                    const matchesSearch = textContent.includes(searchTerm);
                    const matchesCred = credTerm === 'all' || credVal === credTerm;
                    const matchesStatus = statusTerm === 'all' || statusVal === statusTerm;

                    if (matchesSearch && matchesCred && matchesStatus) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                noRecordsRow.style.display = (visibleCount === 0) ? 'table-row' : 'none';
            }

            searchInput.addEventListener('input', filterTable);
            filterCredits.addEventListener('change', filterTable);
            filterStatus.addEventListener('change', filterTable);
        });

        // 2. Universal Click-to-Sort Engine
        let sortAsc = true;
        let currentSortCol = -1;
        
        function sortTable(colIndex) {
            const tableBody = document.getElementById("dataTableBody");
            const rows = Array.from(tableBody.querySelectorAll("tr.data-row"));
            
            if (currentSortCol === colIndex) { sortAsc = !sortAsc; } 
            else { sortAsc = true; currentSortCol = colIndex; }

            document.querySelectorAll('.sort-icon').forEach(icon => icon.className = 'fa-solid fa-sort ms-1 sort-icon text-muted');
            const activeIcon = document.getElementById('sort-icon-' + colIndex);
            if(activeIcon) activeIcon.className = sortAsc ? 'fa-solid fa-sort-up ms-1 sort-icon text-dark' : 'fa-solid fa-sort-down ms-1 sort-icon text-dark';

            rows.sort((rowA, rowB) => {
                let cellA = rowA.querySelectorAll("td")[colIndex];
                let cellB = rowB.querySelectorAll("td")[colIndex];
                
                let valA = cellA.getAttribute('data-sort-val') || cellA.textContent.trim().toLowerCase();
                let valB = cellB.getAttribute('data-sort-val') || cellB.textContent.trim().toLowerCase();

                let numA = parseFloat(valA.replace(/[^0-9.-]+/g,""));
                let numB = parseFloat(valB.replace(/[^0-9.-]+/g,""));
                if (!isNaN(numA) && !isNaN(numB)) { valA = numA; valB = numB; }

                if (valA < valB) return sortAsc ? -1 : 1;
                if (valA > valB) return sortAsc ? 1 : -1;
                return 0;
            });
            rows.forEach(row => tableBody.appendChild(row));
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>