<?php
session_start();
require_once '../config/db.php';

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please provide both email and password.";
    } else {
        // Secure Prepared Statement to prevent SQL Injection
        $stmt = $pdo->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Session protection & fixation prevention
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'student') header("Location: ../dashboard/student.php");
            else header("Location: ../dashboard/index.php");
            exit;
        } else {
            $error = "Invalid credentials or unauthorized access.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise Login | SCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        /* Dynamic Background Elements */
        .bg-shape-1 { position: absolute; top: -10%; left: -10%; width: 500px; height: 500px; background: radial-gradient(circle, rgba(56,189,248,0.15) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; }
        .bg-shape-2 { position: absolute; bottom: -20%; right: -10%; width: 600px; height: 600px; background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; }
        
        .login-container {
            width: 100%;
            max-width: 1000px;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            display: flex;
            overflow: hidden;
            z-index: 10;
        }
        .login-sidebar {
            background: linear-gradient(135deg, #3b82f6 0%, #4f46e5 100%);
            padding: 50px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 45%;
        }
        .login-form-area {
            padding: 60px 50px;
            width: 55%;
            background: #ffffff;
        }
        .form-control {
            background-color: #f8fafc;
            border: 2px solid #e2e8f0;
            padding: 12px 16px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            background-color: #fff;
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        .btn-login {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
            background: linear-gradient(135deg, #4338ca 0%, #3730a3 100%);
        }
        @media (max-width: 768px) {
            .login-container { flex-direction: column; max-width: 500px; margin: 20px; }
            .login-sidebar { width: 100%; padding: 40px; text-align: center; }
            .login-form-area { width: 100%; padding: 40px; }
        }
    </style>
</head>
<body>
    <div class="bg-shape-1"></div>
    <div class="bg-shape-2"></div>

    <div class="login-container">
        <!-- Branding Sidebar -->
        <div class="login-sidebar">
            <div>
                <div class="d-inline-flex align-items-center justify-content-center bg-white text-primary rounded-circle mb-4" style="width: 60px; height: 60px; font-size: 28px;">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <h2 class="fw-bold mb-3" style="letter-spacing: -1px;">SCMS<br>Enterprise</h2>
                <p class="opacity-75 mb-0" style="font-size: 1.1rem; line-height: 1.6;">
                    The next-generation, AI-powered Smart College Management System.
                </p>
            </div>
            
            <div class="mt-5 pt-5 border-top border-white border-opacity-25">
                <div class="d-flex align-items-center gap-3">
                    <i class="fa-solid fa-shield-check fs-2 opacity-75"></i>
                    <div>
                        <div class="fw-bold">Bank-Grade Security</div>
                        <div class="small opacity-75">Bcrypt Encryption & Strict RBAC</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Login Form -->
        <div class="login-form-area">
            <div class="mb-5 text-center text-md-start">
                <h3 class="fw-bold text-dark mb-1">Secure Authentication</h3>
                <p class="text-muted small fw-semibold">Please enter your institutional credentials to proceed.</p>
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger border-0 border-start border-danger border-4 shadow-sm mb-4 fw-bold py-2" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i><?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark small text-uppercase" style="letter-spacing: 0.5px;">Institutional Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 border-2 border-light-subtle text-muted px-3"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" name="email" class="form-control border-start-0 ps-0" required placeholder="name@scms.edu">
                    </div>
                </div>
                
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label fw-bold text-dark small text-uppercase" style="letter-spacing: 0.5px;">Security Key</label>
                        <a href="#" class="small fw-bold text-decoration-none text-primary" tabindex="-1">Forgot Key?</a>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 border-2 border-light-subtle text-muted px-3"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" class="form-control border-start-0 ps-0" required placeholder="••••••••">
                    </div>
                </div>

                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input border-secondary" id="rememberMe">
                    <label class="form-check-label small fw-semibold text-muted" for="rememberMe">Maintain secure session on this device</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-login text-white mt-2">
                    Authenticate Identity <i class="fa-solid fa-arrow-right ms-2"></i>
                </button>
            </form>
            
            <div class="text-center mt-5">
                <span class="badge bg-light text-secondary border px-3 py-2 fw-semibold">
                    <i class="fa-solid fa-server text-success me-1"></i> SCMS Server Engine Active
                </span>
            </div>
        </div>
    </div>
</body>
</html>