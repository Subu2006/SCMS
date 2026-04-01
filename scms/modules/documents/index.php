<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) { 
    header("Location: ../../auth/login.php"); 
    exit; 
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// --- 0. DATABASE INITIALIZATION & SELF-HEALING ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_type VARCHAR(50),
        uploaded_by INT,
        category VARCHAR(50) DEFAULT 'Notes',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Auto-upgrade the category column just in case it was created as an ENUM previously
    $pdo->exec("ALTER TABLE documents MODIFY COLUMN category VARCHAR(50) DEFAULT 'Notes'");
} catch (PDOException $e) {}

// --- 1. HANDLE FILE UPLOAD (Dynamic for All Roles) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_doc'])) {
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
    
    $target_dir = "../../uploads/assignments/";
    // Auto-create directory if it doesn't exist
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = time() . "_" . basename($_FILES["file_to_upload"]["name"]);
    $target_file = $target_dir . $file_name;
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Basic Security: Limit file types
    $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'png', 'jpeg', 'zip', 'pptx'];
    
    if (!in_array($file_type, $allowed_types)) {
        $error = "Sorry, only PDF, DOCX, PPTX, ZIP & Images are allowed.";
    } else {
        if (move_uploaded_file($_FILES["file_to_upload"]["tmp_name"], $target_file)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO documents (title, file_path, file_type, category, uploaded_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $file_name, $file_type, $category, $user_id]);
                $success = $user_role === 'student' ? "Assignment submitted successfully!" : "Document published successfully to the student portal.";
            } catch (PDOException $e) {
                $error = "Database mapping failed.";
            }
        } else {
            $error = "Server Error: Could not move file to uploads folder. Check folder permissions.";
        }
    }
}

// --- 2. HANDLE DELETE (Role-Aware) ---
if (isset($_POST['delete_doc'])) {
    $doc_id = filter_input(INPUT_POST, 'doc_id', FILTER_SANITIZE_NUMBER_INT);
    $file_path = filter_input(INPUT_POST, 'file_path', FILTER_SANITIZE_STRING);
    
    // Check ownership
    $stmt = $pdo->prepare("SELECT uploaded_by FROM documents WHERE id = ?");
    $stmt->execute([$doc_id]);
    $owner_id = $stmt->fetchColumn();

    // Admins/Faculty can delete anything, Students can only delete their own submissions
    if ($user_role !== 'student' || $owner_id == $user_id) {
        if (file_exists("../../uploads/assignments/" . $file_path)) {
            unlink("../../uploads/assignments/" . $file_path);
        }
        $stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->execute([$doc_id]);
        $success = "Document permanently removed from the server.";
    } else {
        $error = "Security Error: Unauthorized to delete this file.";
    }
}

// --- 3. FETCH DOCUMENTS (Role-Aware) ---
if ($user_role === 'student') {
    // Students see public study materials + THEIR OWN submissions
    $stmt = $pdo->prepare("SELECT d.*, u.name as author FROM documents d JOIN users u ON d.uploaded_by = u.id WHERE d.category != 'Submission' OR d.uploaded_by = ? ORDER BY d.created_at DESC");
    $stmt->execute([$user_id]);
    $docs = $stmt->fetchAll();
} else {
    // Admin/Faculty see EVERYTHING (All study materials + All student submissions)
    $docs = $pdo->query("SELECT d.*, u.name as author FROM documents d JOIN users u ON d.uploaded_by = u.id ORDER BY d.created_at DESC")->fetchAll();
}

// --- 4. CALCULATE KPIs ---
$totalDocs = count($docs);
$myUploads = 0;
$totalSubmissions = 0;
foreach($docs as $d) {
    if($d['uploaded_by'] == $user_id) $myUploads++;
    if($d['category'] === 'Submission') $totalSubmissions++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Digital Resource Center | SCMS ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/style.css" rel="stylesheet">
    <style>
        .search-wrapper .form-control:focus { box-shadow: none; border-color: #dee2e6; }
        .search-wrapper .input-group-text { background-color: #fff; border-right: none; }
        .search-wrapper .form-control { border-left: none; padding-left: 0; }
        .doc-card { transition: transform 0.2s, box-shadow 0.2s; }
        .doc-card:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark px-4 shadow-sm">
        <a class="navbar-brand fw-bold" href="../../dashboard/<?= $user_role === 'student' ? 'student' : 'index' ?>.php">
            <i class="fa-solid fa-arrow-left me-2"></i> SCMS | Resource Center
        </a>
    </nav>

    <div class="container-fluid px-4 mt-4 pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-0"><i class="fa-solid fa-folder-tree text-danger me-2"></i> Digital Document Library</h3>
                <p class="text-muted small">Access syllabus, lecture notes, and assignment files securely.</p>
            </div>
            <!-- Top Right Action Button -->
            <button class="btn <?= $user_role === 'student' ? 'btn-success' : 'btn-primary' ?> shadow-sm fw-bold px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="fa-solid fa-cloud-arrow-up me-2"></i> <?= $user_role === 'student' ? 'Submit Assignment' : 'Upload Resource' ?>
            </button>
        </div>

        <!-- KPIs -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-primary border-4 h-100 rounded-4">
                    <div class="card-body">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Total Accessible Files</div>
                        <h3 class="mb-0 fw-bold text-dark"><?= $totalDocs ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-success border-4 h-100 rounded-4">
                    <div class="card-body">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Your Uploads/Submissions</div>
                        <h3 class="mb-0 fw-bold text-success"><?= $myUploads ?></h3>
                    </div>
                </div>
            </div>
            <?php if($user_role !== 'student'): ?>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-warning border-4 h-100 rounded-4">
                    <div class="card-body">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Student Submissions</div>
                        <h3 class="mb-0 fw-bold text-warning text-dark"><?= $totalSubmissions ?></h3>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if($success) echo "<div class='alert alert-success shadow-sm rounded-3 fw-bold'><i class='fa-solid fa-check-circle me-2'></i>$success</div>"; ?>
        <?php if($error) echo "<div class='alert alert-danger shadow-sm rounded-3 fw-bold'><i class='fa-solid fa-triangle-exclamation me-2'></i>$error</div>"; ?>

        <!-- 🔥 REAL-TIME SEARCH & FILTER ENGINE 🔥 -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-3">
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="input-group search-wrapper">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="searchData" class="form-control border-start-0 ps-0" placeholder="Search Document Title or Author...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-filter text-muted"></i></span>
                            <select id="filterCategory" class="form-select border-start-0 ps-0">
                                <option value="all">All Categories</option>
                                <option value="Notes">Lecture Notes</option>
                                <option value="Assignment">Assignment Briefs</option>
                                <option value="Syllabus">Official Syllabus</option>
                                <option value="Submission">Student Submissions</option>
                                <option value="Other">Other References</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Grid -->
        <div class="row g-4" id="documentGrid">
            <?php foreach($docs as $doc): 
                $icon = "fa-file-lines";
                if($doc['file_type'] == 'pdf') $icon = "fa-file-pdf text-danger";
                if(in_array($doc['file_type'], ['jpg', 'png', 'jpeg'])) $icon = "fa-file-image text-primary";
                if($doc['file_type'] == 'zip') $icon = "fa-file-zipper text-warning";
                
                $badgeClass = 'bg-primary-subtle text-primary border border-primary-subtle';
                if($doc['category'] === 'Submission') $badgeClass = 'bg-success-subtle text-success border border-success';
            ?>
            <div class="col-xl-3 col-lg-4 col-md-6 doc-card-wrapper" data-title="<?= htmlspecialchars($doc['title']) ?>" data-author="<?= htmlspecialchars($doc['author']) ?>" data-category="<?= htmlspecialchars($doc['category']) ?>">
                <div class="card border-0 shadow-sm h-100 rounded-4 overflow-hidden doc-card">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="bg-light rounded-circle d-flex justify-content-center align-items-center" style="width: 50px; height: 50px;">
                                <i class="fa-solid <?= $icon ?> fa-xl"></i>
                            </div>
                            <span class="badge <?= $badgeClass ?> rounded-pill px-3 py-2 fw-semibold"><?= htmlspecialchars($doc['category']) ?></span>
                        </div>
                        
                        <h5 class="fw-bold text-dark text-truncate mb-2" title="<?= htmlspecialchars($doc['title']) ?>"><?= htmlspecialchars($doc['title']) ?></h5>
                        
                        <div class="small text-muted mb-4">
                            <div><i class="fa-solid fa-user-pen me-2 w-10px"></i><?= htmlspecialchars($doc['author']) ?></div>
                            <div class="mt-1"><i class="fa-regular fa-clock me-2 w-10px"></i><?= date('d M, Y', strtotime($doc['created_at'])) ?></div>
                            <div class="mt-1"><i class="fa-solid fa-file-code me-2 w-10px"></i><?= strtoupper($doc['file_type']) ?> File</div>
                        </div>
                        
                        <div class="d-flex gap-2 mt-auto">
                            <a href="../../uploads/assignments/<?= htmlspecialchars($doc['file_path']) ?>" class="btn btn-outline-primary w-100 fw-bold rounded-pill" download>
                                <i class="fa-solid fa-download me-1"></i> Get
                            </a>
                            
                            <!-- Delete logic: Admins see all, Students see only for their own submissions -->
                            <?php if($user_role !== 'student' || $doc['uploaded_by'] == $user_id): ?>
                            <form method="POST" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to permanently delete this file?');">
                                <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                <input type="hidden" name="file_path" value="<?= htmlspecialchars($doc['file_path']) ?>">
                                <button type="submit" name="delete_doc" class="btn btn-outline-danger rounded-circle" style="width: 38px; height: 38px; padding: 0;" title="Delete File">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Empty State / No Records Fallback -->
            <div class="col-12 text-center py-5" id="noRecordsDiv" style="display: <?= empty($docs) ? 'block' : 'none' ?>;">
                <i class="fa-solid fa-folder-open fa-4x mb-3 text-muted opacity-50"></i>
                <h4 class="fw-bold text-dark">No documents found.</h4>
                <p class="text-muted mb-4">No files match your search criteria or the directory is empty.</p>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header <?= $user_role === 'student' ? 'bg-success' : 'bg-dark' ?> text-white border-0 py-3 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-cloud-arrow-up me-2"></i><?= $user_role === 'student' ? 'Submit Assignment' : 'Upload New Resource' ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4 bg-light">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Document Title</label>
                            <input type="text" name="title" class="form-control border-secondary" placeholder="<?= $user_role === 'student' ? 'e.g. Physics Assignment 1 - John Doe' : 'e.g. Unit 1 - Web Tech Notes' ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Category Classification</label>
                            <select name="category" class="form-select border-secondary">
                                <?php if($user_role !== 'student'): ?>
                                    <option value="Notes">Lecture Notes</option>
                                    <option value="Assignment">Assignment Brief</option>
                                    <option value="Syllabus">Official Syllabus</option>
                                    <option value="Other">Other Reference</option>
                                <?php else: ?>
                                    <option value="Submission" selected>Assignment Submission</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Select File</label>
                            <input type="file" name="file_to_upload" class="form-control border-primary shadow-sm" required>
                            <div class="form-text mt-2 fw-semibold text-muted"><i class="fa-solid fa-circle-info text-primary me-1"></i> Allowed: PDF, DOCX, PPTX, ZIP, Images.</div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light p-4 pt-0">
                        <button type="button" class="btn btn-secondary px-4 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="upload_doc" class="btn <?= $user_role === 'student' ? 'btn-success' : 'btn-primary' ?> px-5 rounded-pill fw-bold">
                            <?= $user_role === 'student' ? 'Submit Securely' : 'Upload & Publish' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // 1. Real-Time Grid Search & Filter Engine
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchData');
            const filterCategory = document.getElementById('filterCategory');
            const docCards = document.querySelectorAll('.doc-card-wrapper');
            const noRecordsDiv = document.getElementById('noRecordsDiv');

            function filterGrid() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const catTerm = filterCategory.value.toLowerCase();
                let visibleCount = 0;

                docCards.forEach(card => {
                    const title = card.getAttribute('data-title').toLowerCase();
                    const author = card.getAttribute('data-author').toLowerCase();
                    const category = card.getAttribute('data-category').toLowerCase();

                    const matchesSearch = title.includes(searchTerm) || author.includes(searchTerm);
                    const matchesCat = catTerm === 'all' || category === catTerm;

                    if (matchesSearch && matchesCat) {
                        card.style.display = 'block';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                noRecordsDiv.style.display = (visibleCount === 0) ? 'block' : 'none';
            }

            searchInput.addEventListener('input', filterGrid);
            filterCategory.addEventListener('change', filterGrid);
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>