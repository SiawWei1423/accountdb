<?php
session_start();
require_once('db_connection.php');

// Get company ID from URL parameter
$company_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch company details
$company = null;
if ($company_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM company WHERE company_id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $company = $result->fetch_assoc();
    }
    $stmt->close();
}

// If company not found, redirect back to Manage Companies
if (!$company) {
    header('Location: index.php#companies');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Company Details - Accounting System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<style>
/* BODY & GENERAL */
body { background: #0f1b33; color: #e0e0e0; font-family: 'Segoe UI', sans-serif; }

/* NAVBAR */
.navbar { background: linear-gradient(90deg, #004f99, #0072ff); padding: 0.8rem 2rem; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
.navbar-brand { color: #fff !important; font-weight: bold; }
.navbar span { font-size: 0.95rem; color: #fff; }

/* SIDEBAR */
.sidebar {
    width: 240px; height: 100vh; position: fixed; top: 0; left: 0;
    background: #00264d; padding-top: 70px; border-right: 2px solid #0072ff; overflow-y: auto;
}
.sidebar h5, .sidebar h6 { color: #aad4ff; margin-left: 1rem; }
.sidebar a {
    color: #fff; display: block; padding: 12px 20px; text-decoration: none;
    margin: 0.2rem 0; border-radius: 8px; transition: 0.3s;
}
.sidebar a:hover { background: #0072ff; padding-left: 25px; }

/* CONTENT */
.content { margin-left: 240px; padding: 25px; min-height: 100vh; }

/* CARDS / DETAIL SECTIONS */
.card-detail {
    border-radius: 12px; background: linear-gradient(135deg, #1b2a6a, #004f99);
    padding: 20px; margin-bottom: 20px; color: #fff;
    transition: transform 0.2s, box-shadow 0.2s;
}
.card-detail:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,123,255,0.5); }
.card-detail h4 {
    color: #00c6ff; border-bottom: 2px solid #ff4ec7; padding-bottom: 10px; margin-bottom: 15px;
}
.detail-item { margin-bottom: 12px; padding: 8px; background: rgba(255,255,255,0.05); border-radius: 8px; border-left: 4px solid #ff4ec7; }
.detail-item strong { color: #00c6ff; display: inline-block; width: 200px; }
.detail-item span { color: #fff; }

/* BUTTONS */
.btn-back {
    background: linear-gradient(90deg, #0072ff, #00c6ff); border: none; color: #fff;
    padding: 10px 20px; border-radius: 8px; text-decoration: none; transition: all 0.3s;
}
.btn-back:hover { background: linear-gradient(90deg, #0056b3, #0099cc); transform: translateY(-2px); }

/* RESPONSIVE */
@media (max-width: 768px) {
    .sidebar { width: 100%; height: auto; position: relative; border-right: none; }
    .content { margin-left: 0; }
    .detail-item strong { width: 120px; display: inline-block; }
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar fixed-top">
    <a class="navbar-brand" href="index.php"><i class="fa-solid fa-calculator"></i> Accounting System</a>
    <span>Company Details</span>
</nav>

<!-- SIDEBAR -->
<div class="sidebar">
    <h5 class="mt-3">Main</h5>
    <a href="index.php#dashboard"><i class="fa-solid fa-gauge"></i> Dashboard</a>
    <h6 class="mt-4">Admin</h6>
    <a href="admin_register.php"><i class="fa-solid fa-user-plus"></i> Add Admin</a>
    <a href="admin_login.php"><i class="fa-solid fa-right-to-bracket"></i> Admin Login</a>
    <h6 class="mt-4">User</h6>
    <a href="user_register.php"><i class="fa-solid fa-user-plus"></i> Add User</a>
    <a href="user_login.php"><i class="fa-solid fa-right-to-bracket"></i> User Login</a>
    <h6 class="mt-4">Company</h6>
    <a href="company_information.php"><i class="fa-solid fa-building"></i> Add Company</a>
    <a href="index.php#companies"><i class="fa-solid fa-table"></i> Manage Companies</a>
</div>

<!-- CONTENT -->
<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-info">Company Details</h2>
        <a href="index.php#companies" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Companies</a>
    </div>

    <!-- Company Details -->
    <div class="card-detail">
        <h4><i class="fa-solid fa-building"></i> Company Information</h4>
        <div class="detail-item"><strong>ID:</strong> <span><?php echo htmlspecialchars($company['company_id']); ?></span></div>
        <div class="detail-item"><strong>Name:</strong> <span><?php echo htmlspecialchars($company['company_name'] ?: 'N/A'); ?></span></div>
        <div class="detail-item"><strong>SSM No:</strong> <span><?php echo htmlspecialchars($company['ssm_no'] ?: 'N/A'); ?></span></div>
        <div class="detail-item"><strong>Type:</strong> <span><?php echo htmlspecialchars($company['company_type'] ?: 'N/A'); ?></span></div>
        <div class="detail-item"><strong>Sub Type:</strong> <span><?php echo htmlspecialchars($company['sub_type'] ?: 'N/A'); ?></span></div>
        <div class="detail-item"><strong>Incorporation Date:</strong> <span><?php echo htmlspecialchars($company['incorporation_date'] ?: 'N/A'); ?></span></div>
        <div class="detail-item"><strong>Financial Year End:</strong> <span><?php echo htmlspecialchars($company['financial_year_end'] ?: 'N/A'); ?></span></div>
    </div>

    <div class="card-detail">
        <h4><i class="fa-solid fa-briefcase"></i> Business Information</h4>
        <div class="detail-item"><strong>Nature of Business:</strong> <span><?php echo htmlspecialchars($company['nature_of_business'] ?: 'N/A'); ?></span></div>
        <div class="detail-item"><strong>MSIC Code:</strong> <span><?php echo htmlspecialchars($company['msic_code'] ?: 'N/A'); ?></span></div>
    </div>

    <div class="card-detail">
        <h4><i class="fa-solid fa-address-book"></i> Contact Information</h4>
        <div class="detail-item"><strong>Address:</strong> <span><?php echo htmlspecialchars($company['address'] ?: 'N/A'); ?></span></div>
        <div class="detail-item"><strong>Email:</strong> <span><?php echo htmlspecialchars($company['email'] ?: 'N/A'); ?></span></div>
        <div class="detail-item"><strong>Office No:</strong> <span><?php echo htmlspecialchars($company['office_no'] ?: 'N/A'); ?></span></div>
        <div class="detail-item"><strong>Fax No:</strong> <span><?php echo htmlspecialchars($company['fax_no'] ?: 'N/A'); ?></span></div>
    </div>

    <div class="card-detail">
        <h4><i class="fa-solid fa-users"></i> Contact Persons</h4>
        <div class="detail-item"><strong>Accountant Name:</strong> <span><?php echo htmlspecialchars($company['accountant_name'] ?: 'N/A'); ?></span></div>
        <div class="detail-item"><strong>Accountant Phone:</strong> <span><?php echo htmlspecialchars($company['accountant_phone'] ?: 'N/A'); ?></span></div>
        <div class="detail-item"><strong>HR Name:</strong> <span><?php echo htmlspecialchars($company['hr_name'] ?: 'N/A'); ?></span></div>
        <div class="detail-item"><strong>HR Phone:</strong> <span><?php echo htmlspecialchars($company['hr_phone'] ?: 'N/A'); ?></span></div>
    </div>
</div>
</body>
</html>
