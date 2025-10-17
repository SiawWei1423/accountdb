<?php
session_start();
require_once('db_connection.php');

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    
    // Check in admin table first
    $result = $conn->query("SELECT * FROM admin WHERE email = '$email'");
    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            // Update last_login timestamp (Malaysia time)
            date_default_timezone_set('Asia/Kuala_Lumpur');
            $loginTime = date('Y-m-d H:i:s');
            $conn->query("UPDATE admin SET last_login = '$loginTime' WHERE admin_id = " . $admin['admin_id']);
            
            $_SESSION['user_id'] = $admin['admin_id'];
            $_SESSION['user_type'] = 'admin';
            // Logged in via admin table — grant admin privileges
            $_SESSION['is_admin'] = true;
            $_SESSION['full_name'] = $admin['full_name'];
            $_SESSION['email'] = $admin['email'];
            // Ensure role is available for display
            $_SESSION['role'] = 'admin';
            header('Location: index.php');
            exit;
        }
    }
    
    // Check in user table
    $result = $conn->query("SELECT * FROM user WHERE email = '$email'");
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Update last_login timestamp (Malaysia time)
            date_default_timezone_set('Asia/Kuala_Lumpur');
            $loginTime = date('Y-m-d H:i:s');
            $conn->query("UPDATE user SET last_login = '$loginTime' WHERE user_id = " . $user['user_id']);
            
            // Set session values
            $_SESSION['user_id'] = $user['user_id'];
            // Always mark this as a 'user' record for lookups. If the user's role
            // field is 'admin', grant admin privileges via is_admin flag so the
            // app shows admin panels but still uses the user table for profile data.
            $_SESSION['user_type'] = 'user';
            $userRole = strtolower($user['role'] ?? 'user');
            $_SESSION['is_admin'] = ($userRole === 'admin');
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $userRole;

            // Redirect to main index which will render the appropriate view
            header('Location: index.php');
            exit;
        }
    }
    
    $error = 'Invalid email or password!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Accounting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0f1b33, #004f99);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #0072ff;
            color: white;
            box-shadow: 0 0 0 0.2rem rgba(0, 114, 255, 0.25);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="text-center mb-4">
            <h2 class="text-white"><i class="fa-solid fa-calculator"></i> Accounting System</h2>
            <p class="text-white-50">Please sign in to continue</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label text-white">Email</label>
                <input type="email" class="form-control" name="email" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-white">Password</label>
                <input type="password" class="form-control" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Sign In</button>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>