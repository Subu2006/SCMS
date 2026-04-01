<?php
session_start();
require_once '../../config/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../../auth/login.php"); 
    exit; 
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// --- 0. SELF-HEALING DATABASE: Auto-Create Table if missing ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        target_audience ENUM('all', 'student', 'faculty') DEFAULT 'all',
        posted_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE CASCADE
    )");
} catch (PDOException $e) {
    die("System Initialization Error: " . $e->getMessage());
}

// --- 1. HANDLE ADD ANNOUNCEMENT (Admin/Faculty Only) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_announcement'])) {
    if ($user_role === 'student') die("Unauthorized Action.");
    
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
    $audience = filter_input(INPUT_POST, 'target_audience', FILTER_SANITIZE_STRING);

    try {
        $stmt = $pdo->prepare("INSERT INTO announcements (title, message, target_audience, posted_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $message, $audience, $user_id]);
        $success = "Announcement broadcasted successfully.";
    } catch (PDOException $e) {
        $error = "Failed to post announcement.";
    }
}

// --- 2. HANDLE EDIT ANNOUNCEMENT (Admin/Faculty Only) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_announcement'])) {
    if ($user_role === 'student') die("Unauthorized Action.");
    
    $announcement_id = filter_input(INPUT_POST, 'announcement_id', FILTER_SANITIZE_NUMBER_INT);
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
    $audience = filter_input(INPUT_POST, 'target_audience', FILTER_SANITIZE_STRING);

    try {
        $stmt = $pdo->prepare("UPDATE announcements SET title = ?, message = ?, target_audience = ? WHERE id = ?");
        $stmt->execute([$title, $message, $audience, $announcement_id]);
        $success = "Announcement updated successfully.";
    } catch (PDOException $e) {
        $error = "Failed to update announcement.";
    }
}

// --- 3. HANDLE DELETE ANNOUNCEMENT (Admin/Faculty Only) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    if ($user_role === 'student') die("Unauthorized Action.");
    
    $announcement_id = filter_input(INPUT_POST, 'announcement_id', FILTER_SANITIZE_NUMBER_INT);
    try {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->execute([$announcement_id]);
        $success = "Announcement permanently removed.";
    } catch (PDOException $e) {
        $error = "Failed to delete announcement.";
    }
}

// --- 4. FETCH ANNOUNCEMENTS BASED ON ROLE ---
$query = "
    SELECT a.id, a.title, a.message, a.target_audience, a.created_at, u.name as author, u.role as author_role 
    FROM announcements a 
    JOIN users u ON a.posted_by = u.id 
";

// If student, only show 'all' or 'student' targeted announcements
if ($user_role === 'student') {
    $query .= " WHERE a.target_audience IN ('all', 'student') ";
}
// If faculty, show 'all' or 'faculty' targeted, plus ones they posted themselves
elseif ($user_role === 'faculty') {
    $query .= " WHERE a.target_audience IN ('all', 'faculty') OR a.posted_by = " . (int)$user_id;
}
$query .= " ORDER BY a.created_at DESC";

$announcements = $pdo->query($query)->fetchAll();
$totalAnnouncements = count($announcements);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enterprise Communication - SCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/style.css" rel="stylesheet">
    <style>
        .announcement-card { border-left: 5px solid #4361ee; transition: all 0.2s ease; }
        .announcement-card:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
        .audience-badge-all { background-color: #e0e7ff; color: #4361ee; }
        .audience-badge-student { background-color: #d1fae5; color: #10b981; }
        .audience-badge-faculty { background-color: #fef3c7; color: #f59e0b; }
    </style>
</head>
<body class="bg-light">
    <!-- Top Navigation -->
    <nav class="navbar navbar-dark bg-dark px-4 shadow-sm">
        <a class="navbar-brand fw-bold" href="../../dashboard/<?= $user_role === 'student' ? 'student' : 'index' ?>.php">
            <i class="fa-solid fa-arrow-left me-2"></i> SCMS | Communication Center
        </a>
    </nav>

    <div class="container mt-5 pb-5" style="max-width: 900px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-0"><i class="fa-solid fa-bullhorn text-primary me-2"></i> Campus Notice Board</h2>
                <p class="text-muted small mt-1">Stay updated with the latest alerts and institutional broadcasts.</p>
            </div>
            
            <?php if($user_role !== 'student'): ?>
            <button class="btn btn-primary shadow-sm fw-bold px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                <i class="fa-solid fa-pen-nib me-2"></i> Post Update
            </button>
            <?php endif; ?>
        </div>

        <?php if($success) echo "<div class='alert alert-success shadow-sm rounded-3'><i class='fa-solid fa-check-circle me-2'></i>$success</div>"; ?>
        <?php if($error) echo "<div class='alert alert-danger shadow-sm rounded-3'><i class='fa-solid fa-triangle-exclamation me-2'></i>$error</div>"; ?>

        <!-- Announcements Feed -->
        <div class="announcements-feed mt-4">
            <?php if(empty($announcements)): ?>
                <div class="text-center py-5">
                    <i class="fa-solid fa-inbox fa-4x text-muted opacity-25 mb-3"></i>
                    <h5 class="text-muted fw-bold">No Recent Announcements</h5>
                    <p class="text-muted small">You're all caught up! Check back later.</p>
                </div>
            <?php else: ?>
                <?php foreach($announcements as $ann): 
                    $badgeClass = 'audience-badge-all';
                    $audienceText = 'Everyone';
                    if($ann['target_audience'] == 'student') { $badgeClass = 'audience-badge-student'; $audienceText = 'Students Only'; }
                    if($ann['target_audience'] == 'faculty') { $badgeClass = 'audience-badge-faculty'; $audienceText = 'Faculty Only'; }
                ?>
                <div class="card announcement-card shadow-sm border-0 mb-4 rounded-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-light rounded-circle d-flex justify-content-center align-items-center text-primary border" style="width: 45px; height: 45px; font-size: 1.2rem;">
                                    <i class="fa-solid <?= $ann['author_role'] === 'admin' ? 'fa-shield-halved' : 'fa-user-tie' ?>"></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($ann['title']) ?></h5>
                                    <div class="small text-muted mt-1">
                                        <span class="fw-semibold"><?= htmlspecialchars($ann['author']) ?></span> • 
                                        <?= date('D, M j, Y g:i A', strtotime($ann['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                            <span class="badge <?= $badgeClass ?> rounded-pill px-3 py-2 fw-semibold">
                                <i class="fa-solid fa-users me-1"></i> <?= $audienceText ?>
                            </span>
                        </div>
                        
                        <div class="card-text text-dark" style="line-height: 1.7; font-size: 1.05rem;">
                            <?= nl2br(htmlspecialchars($ann['message'])) ?>
                        </div>

                        <?php if($user_role !== 'student'): ?>
                        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                            <button class="btn btn-sm btn-outline-secondary px-3 rounded-pill" data-bs-toggle="modal" data-bs-target="#editAnnouncementModal<?= $ann['id'] ?>">
                                <i class="fa-solid fa-pen me-1"></i> Edit
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this announcement?');">
                                <input type="hidden" name="announcement_id" value="<?= $ann['id'] ?>">
                                <button type="submit" name="delete_announcement" class="btn btn-sm btn-outline-danger px-3 rounded-pill">
                                    <i class="fa-solid fa-trash me-1"></i> Delete
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if($user_role !== 'student'): ?>
                <!-- Edit Modal -->
                <div class="modal fade" id="editAnnouncementModal<?= $ann['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content border-0 shadow-lg rounded-4">
                            <div class="modal-header bg-dark text-white border-0 py-3 rounded-top-4">
                                <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-nib me-2"></i>Edit Announcement</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body p-4">
                                    <input type="hidden" name="announcement_id" value="<?= $ann['id'] ?>">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-muted">Subject / Title</label>
                                        <input type="text" name="title" class="form-control form-control-lg bg-light border-0" value="<?= htmlspecialchars($ann['title']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-muted">Message Content</label>
                                        <textarea name="message" class="form-control bg-light border-0" rows="6" required><?= htmlspecialchars($ann['message']) ?></textarea>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label fw-bold text-muted">Target Audience</label>
                                        <select name="target_audience" class="form-select bg-light border-0" required>
                                            <option value="all" <?= $ann['target_audience'] == 'all' ? 'selected' : '' ?>>Entire Campus (Everyone)</option>
                                            <option value="student" <?= $ann['target_audience'] == 'student' ? 'selected' : '' ?>>Students Only</option>
                                            <option value="faculty" <?= $ann['target_audience'] == 'faculty' ? 'selected' : '' ?>>Faculty Only</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer border-0 p-4 pt-0">
                                    <button type="button" class="btn btn-light px-4 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="edit_announcement" class="btn btn-primary px-5 rounded-pill fw-bold">Update Post</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if($user_role !== 'student'): ?>
    <!-- Add New Announcement Modal -->
    <div class="modal fade" id="addAnnouncementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-primary text-white border-0 py-3 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-bullhorn me-2"></i>Create New Broadcast</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted">Subject / Title</label>
                            <input type="text" name="title" class="form-control form-control-lg bg-light border-0" placeholder="e.g. End Semester Exam Schedule" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted">Message Content</label>
                            <textarea name="message" class="form-control bg-light border-0" rows="6" placeholder="Type your detailed announcement here..." required></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold text-muted">Target Audience</label>
                            <select name="target_audience" class="form-select bg-light border-0" required>
                                <option value="all" selected>Entire Campus (Everyone)</option>
                                <option value="student">Students Only</option>
                                <option value="faculty">Faculty Only</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light px-4 rounded-pill fw-bold" data-bs-dismiss="modal">Discard</button>
                        <button type="submit" name="add_announcement" class="btn btn-primary px-5 rounded-pill fw-bold"><i class="fa-solid fa-paper-plane me-2"></i> Publish Now</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>