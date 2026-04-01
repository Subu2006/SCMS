<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$success = '';
$error = '';

// --- HANDLE PASSWORD & PROFILE UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    try {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!empty($new_password)) {
            if (!password_verify($current_password, $user['password'])) {
                $error = "Current password is incorrect.";
            } elseif ($new_password !== $confirm_password) {
                $error = "New passwords do not match.";
            } elseif (strlen($new_password) < 6) {
                $error = "New password must be at least 6 characters long.";
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE users SET phone = ?, password = ? WHERE id = ?");
                $update->execute([$phone, $hashed, $user_id]);
                $success = "Profile and Password updated securely!";
            }
        } else {
            // Just update phone
            $update = $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?");
            $update->execute([$phone, $user_id]);
            $success = "Contact information updated.";
        }
    } catch (PDOException $e) {
        $error = "Database Error.";
    }
}

// Fetch Current Info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$me = $stmt->fetch();

$dashboard_link = $user_role === 'student' ? '../../dashboard/student.php' : '../../dashboard/index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile | SCMS ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark px-4 shadow-sm">
        <a class="navbar-brand fw-bold" href="<?= $dashboard_link ?>">
            <i class="fa-solid fa-arrow-left me-2"></i> SCMS | Account Settings
        </a>
    </nav>

    <div class="container mt-5 pb-5" style="max-width: 800px;">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="card-header bg-primary text-white border-0 py-4 text-center">
                <div class="bg-white text-primary rounded-circle d-inline-flex justify-content-center align-items-center shadow-sm mb-3" style="width: 80px; height: 80px; font-size: 2rem; font-weight: bold;">
                    <?= strtoupper(substr($me['name'], 0, 1)) ?>
                </div>
                <h3 class="fw-bold mb-0"><?= htmlspecialchars($me['name']) ?></h3>
                <p class="mb-0 text-white-50 text-uppercase fw-semibold tracking-wide"><?= $me['role'] ?> Account</p>
            </div>
            
            <div class="card-body p-5">
                <?php if($success) echo "<div class='alert alert-success shadow-sm rounded-3 fw-bold'><i class='fa-solid fa-check-circle me-2'></i>$success</div>"; ?>
                <?php if($error) echo "<div class='alert alert-danger shadow-sm rounded-3 fw-bold'><i class='fa-solid fa-triangle-exclamation me-2'></i>$error</div>"; ?>

                <form method="POST">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-4"><i class="fa-solid fa-address-card text-primary me-2"></i> Personal Information</h5>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted">Registered Email (Read Only)</label>
                            <input type="email" class="form-control bg-light text-secondary" value="<?= htmlspecialchars($me['email']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Mobile Number</label>
                            <input type="text" name="phone" class="form-control border-primary" value="<?= htmlspecialchars($me['phone'] ?? '') ?>" placeholder="+91...">
                        </div>
                    </div>

                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-4 mt-5"><i class="fa-solid fa-shield-halved text-danger me-2"></i> Security Settings</h5>
                    <div class="alert alert-warning small py-2 border-0 fw-semibold">
                        <i class="fa-solid fa-circle-info me-2"></i> Leave password fields blank if you only want to update your phone number.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">Current Password</label>
                        <input type="password" name="current_password" class="form-control border-secondary" placeholder="Required only if changing password">
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-danger">New Password</label>
                            <input type="password" name="new_password" class="form-control border-danger">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-danger">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control border-danger">
                        </div>
                    </div>

                    <div class="text-end mt-4 pt-3 border-top">
                        <a href="<?= $dashboard_link ?>" class="btn btn-secondary rounded-pill px-4 fw-bold me-2">Cancel</a>
                        <button type="submit" name="update_profile" class="btn btn-primary rounded-pill px-5 fw-bold shadow"><i class="fa-solid fa-user-shield me-2"></i> Update Security & Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>