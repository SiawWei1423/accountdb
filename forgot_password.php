<?php
session_start();
require_once('db_connection.php');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    try {
        if (empty($email)) {
            throw new Exception('Please enter your email address.');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        // Check if email exists in either admin or user table
        $admin_stmt = $conn->prepare("SELECT admin_id, full_name FROM admin WHERE email = ?");
        $admin_stmt->bind_param("s", $email);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();
        
        $user_stmt = $conn->prepare("SELECT user_id, full_name FROM user WHERE email = ? AND status = 'active'");
        $user_stmt->bind_param("s", $email);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        
        if ($admin_result->num_rows > 0 || $user_result->num_rows > 0) {
            // In a real application, you would:
            // 1. Generate a reset token
            // 2. Send email with reset link
            // 3. Store token in database with expiration
            
            $success = 'Password reset instructions have been sent to your email.';
        } else {
            $success = 'If the email exists in our system, password reset instructions have been sent.';
            // Don't reveal whether email exists for security
        }
        
        $admin_stmt->close();
        $user_stmt->close();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Accounting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0f1b33, #004f99);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
        }
        .forgot-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="text-center mb-4">
            <i class="fa-solid fa-key fa-3x text-primary mb-3"></i>
            <h2 class="text-white">Forgot Password</h2>
            <p class="text-light">Enter your email to reset your password</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <input type="email" class="form-control" name="email" placeholder="Enter your email" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Reset Password</button>
        </form>
        
        <div class="text-center">
            <a href="login.php" class="text-decoration-none text-light">
                <i class="fa-solid fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
</body>
</html>