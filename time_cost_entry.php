<?php
session_start();
require_once('db_connection.php');

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if user is admin or has appropriate role
$isAdmin = false;
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    $isAdmin = true;
} elseif (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    $isAdmin = true;
}

// Get current user info
$current_user = [];
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    $result = $conn->query("SELECT * FROM admin WHERE admin_id = " . $_SESSION['user_id']);
    if ($result && $result->num_rows > 0) {
        $current_user = $result->fetch_assoc();
    }
} else {
    $result = $conn->query("SELECT * FROM user WHERE user_id = " . $_SESSION['user_id']);
    if ($result && $result->num_rows > 0) {
        $current_user = $result->fetch_assoc();
    }
}

// Create time_cost table if it doesn't exist
$create_time_cost_table = "
CREATE TABLE IF NOT EXISTS `time_cost` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `link_user` varchar(255) NOT NULL,
  `type` varchar(100) NOT NULL,
  `doc` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `work_done` text NOT NULL,
  `fy_year` varchar(20) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
";
$conn->query($create_time_cost_table);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_time_cost') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $staff_id = $_SESSION['user_id'];
        $link_user = $conn->real_escape_string($_POST['link_user']);
        $type = $conn->real_escape_string($_POST['type']);
        $doc = $conn->real_escape_string($_POST['doc']);
        $date = $conn->real_escape_string($_POST['date']);
        $company_name = $conn->real_escape_string($_POST['company_name']);
        $work_done = $conn->real_escape_string($_POST['work_done']);
        $fy_year = $conn->real_escape_string($_POST['fy_year']);
        
        $sql = "INSERT INTO time_cost (staff_id, link_user, type, doc, date, company_name, work_done, fy_year) 
                VALUES ('$staff_id', '$link_user', '$type', '$doc', '$date', '$company_name', '$work_done', '$fy_year')";
        
        if ($conn->query($sql)) {
            $response['success'] = true;
            $response['message'] = 'Time cost entry added successfully!';
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Fetch companies for dropdown
$companies = [];
$companyRes = $conn->query("SELECT company_id, company_name FROM company ORDER BY company_name ASC");
if ($companyRes) {
    while ($row = $companyRes->fetch_assoc()) {
        $companies[] = $row;
    }
}

// Fetch users for link user dropdown
$users = [];
$userRes = $conn->query("SELECT user_id, full_name FROM user ORDER BY full_name ASC");
if ($userRes) {
    while ($row = $userRes->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Cost Entry - Account Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        
        .main-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .form-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .form-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .form-header h2 {
            color: white;
            font-weight: 600;
            font-size: 1.5rem;
            margin: 0;
        }
        
        .btn-close-custom {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 1.2rem;
        }
        
        .btn-close-custom:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }
        
        .form-body {
            padding: 2rem;
        }
        
        .form-row {
            display: grid;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-row.cols-4 {
            grid-template-columns: 1fr 1fr 1fr 1fr;
        }
        
        .form-row.cols-3 {
            grid-template-columns: 2fr 3fr 1fr;
        }
        
        .form-row.cols-1 {
            grid-template-columns: 1fr;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .required {
            color: #e53e3e;
            margin-left: 2px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-control:read-only {
            background-color: #f7fafc;
            color: #4a5568;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .btn-back {
            background: white;
            border: 2px solid white;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            color: #667eea;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.95);
            color: #764ba2;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        @media (max-width: 768px) {
            body {
                padding: 1rem 0.5rem;
            }
            
            .form-row.cols-4,
            .form-row.cols-3 {
                grid-template-columns: 1fr;
            }
            
            .form-body {
                padding: 1.5rem;
            }
            
            .form-header {
                padding: 1.25rem 1.5rem;
            }
            
            .form-header h2 {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
        
        <div class="form-card">
            <div class="form-header">
                <h2><i class="fas fa-clock"></i> Time Cost Entry</h2>
                <button type="button" class="btn-close-custom" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="form-body">
                <form id="timeCostForm">
                    <!-- Row 1: Staff, Link User, Type, Doc -->
                    <div class="form-row cols-4">
                        <div class="form-group">
                            <label class="form-label">Staff<span class="required">*</span></label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_user['full_name'] ?? ''); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Link User</label>
                            <select class="form-select" name="link_user">
                                <option value="">Select User</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['full_name']); ?>">
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Type<span class="required">*</span></label>
                            <select class="form-select" name="type" required>
                                <option value="">Select Type</option>
                                <option value="Audit">Audit</option>
                                <option value="Tax">Tax</option>
                                <option value="Accounting">Accounting</option>
                                <option value="Consultation">Consultation</option>
                                <option value="Review">Review</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Doc<span class="required">*</span></label>
                            <input type="text" class="form-control" name="doc" required placeholder="Doc No.">
                        </div>
                    </div>
                    
                    <!-- Row 2: Date -->
                    <div class="form-row cols-1">
                        <div class="form-group">
                            <label class="form-label">Date<span class="required">*</span></label>
                            <input type="date" class="form-control" name="date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <!-- Row 3: Company Name, Work Done, FY Year -->
                    <div class="form-row cols-3">
                        <div class="form-group">
                            <label class="form-label">Company Name<span class="required">*</span></label>
                            <select class="form-select" name="company_name" required>
                                <option value="">Select Company</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo htmlspecialchars($company['company_name']); ?>">
                                        <?php echo htmlspecialchars($company['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Work Done<span class="required">*</span></label>
                            <textarea class="form-control" name="work_done" required placeholder="Describe the work performed..."></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">FY Year<span class="required">*</span></label>
                            <input type="text" class="form-control" name="fy_year" required placeholder="2024" value="<?php echo date('Y'); ?>">
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Save Time Cost Entry
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('timeCostForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_time_cost');
            
            const submitBtn = this.querySelector('.btn-submit');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            submitBtn.disabled = true;
            
            fetch('time_cost_entry.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Time cost entry saved successfully!');
                    this.reset();
                    // Set today's date again
                    this.querySelector('input[name="date"]').value = new Date().toISOString().split('T')[0];
                    this.querySelector('input[name="fy_year"]').value = new Date().getFullYear();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the entry.');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // Close button functionality
        document.querySelector('.btn-close-custom').addEventListener('click', function() {
            if (confirm('Are you sure you want to close? Any unsaved changes will be lost.')) {
                window.location.href = 'index.php';
            }
        });
    </script>
</body>
</html>