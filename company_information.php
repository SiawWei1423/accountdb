<?php
// Include database connection
require_once('db_connection.php');

// Initialize variables
$company_name = $ssm_no = $company_type = $sub_type = $incorporation_date = '';
$nature_of_business = $msic_code = $description = $address = $email = '';
$office_no = $fax_no = $accountant_name = $accountant_phone = '';
$hr_name = $hr_phone = $financial_year_end = '';
$errors = [];
$successMsg = '';
$current_step = isset($_GET['step']) ? $_GET['step'] : 'company';
$company_id = isset($_GET['company_id']) ? $_GET['company_id'] : '';

// Process Company Form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_company'])) {
    $company_name = $_POST['company_name'];
    $ssm_no = $_POST['ssm_no'];
    $company_type = $_POST['company_type'];
    $sub_type = $_POST['sub_type'];
    $incorporation_date = $_POST['incorporation_date'];
    $nature_of_business = $_POST['nature_of_business'];
    $msic_code = $_POST['msic_code'];
    $description = $_POST['description'];
    $address = $_POST['address'];
    $email = $_POST['email'];
    $office_no = $_POST['office_no'];
    $fax_no = $_POST['fax_no'];
    $accountant_name = $_POST['accountant_name'];
    $accountant_phone = $_POST['accountant_phone'];
    $hr_name = $_POST['hr_name'];
    $hr_phone = $_POST['hr_phone'];
    $financial_year_end = $_POST['financial_year_end'];

    if (empty($company_name)) $errors[] = 'Company Name is required.';
    if (empty($ssm_no)) $errors[] = 'SSM Number is required.';
    if (empty($incorporation_date)) $errors[] = 'Incorporation Date is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid Email is required.';

    if (empty($errors)) {
        $sql = "INSERT INTO company (
            company_name, ssm_no, company_type, sub_type, incorporation_date, 
            nature_of_business, msic_code, description, address, email, office_no, 
            fax_no, accountant_name, accountant_phone, hr_name, hr_phone, financial_year_end
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sssssssssssssssss", 
                $company_name, $ssm_no, $company_type, $sub_type, $incorporation_date,
                $nature_of_business, $msic_code, $description, $address, $email, $office_no, 
                $fax_no, $accountant_name, $accountant_phone, $hr_name, $hr_phone, 
                $financial_year_end
            );
            if ($stmt->execute()) {
                $company_id = $conn->insert_id;
                $successMsg = "✅ Company added successfully! You can now add members and directors.";
                $current_step = 'member';
                
                // Clear form
                $company_name = $ssm_no = $company_type = $sub_type = $incorporation_date = '';
                $nature_of_business = $msic_code = $description = $address = $email = '';
                $office_no = $fax_no = $accountant_name = $accountant_phone = '';
                $hr_name = $hr_phone = $financial_year_end = '';
            } else {
                $errors[] = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = "Failed to prepare statement: " . $conn->error;
        }
    }
}

// Process Member Form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_member'])) {
    $company_id = $_POST['company_id'];
    $member_name = $_POST['member_name'];
    $id_type = $_POST['id_type'];
    $identification_no = $_POST['identification_no'];
    $nationality = $_POST['nationality'];
    $address = $_POST['address'];
    $race = $_POST['race'];
    $price_per_share = $_POST['price_per_share'];
    $class_of_share = $_POST['class_of_share'];
    $number_of_share = $_POST['number_of_share'];

    if (empty($company_id)) $errors[] = 'Company ID is required.';
    if (empty($member_name)) $errors[] = 'Member Name is required.';
    if (empty($identification_no)) $errors[] = 'Identification Number is required.';

    if (empty($errors)) {
        $sql = "INSERT INTO member (
            company_id, member_name, id_type, identification_no, nationality, 
            address, race, price_per_share, class_of_share, number_of_share
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("issssssiis", 
                $company_id, $member_name, $id_type, $identification_no, $nationality,
                $address, $race, $price_per_share, $class_of_share, $number_of_share
            );
            if ($stmt->execute()) {
                $successMsg = "✅ Member added successfully!";
                $current_step = 'director';
                
                // Clear form
                $member_name = $id_type = $identification_no = $nationality = '';
                $address = $race = $price_per_share = $class_of_share = $number_of_share = '';
            } else {
                $errors[] = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = "Failed to prepare statement: " . $conn->error;
        }
    }
}

// Process Director Form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_director'])) {
    $company_id = $_POST['company_id'];
    $director_name = $_POST['director_name'];
    $identification_no = $_POST['identification_no'];
    $nationality = $_POST['nationality'];
    $address = $_POST['address'];
    $date_of_birth = $_POST['date_of_birth'];
    $race = $_POST['race'];
    $email = $_POST['email'];

    if (empty($company_id)) $errors[] = 'Company ID is required.';
    if (empty($director_name)) $errors[] = 'Director Name is required.';
    if (empty($identification_no)) $errors[] = 'Identification Number is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid Email is required.';

    if (empty($errors)) {
        $sql = "INSERT INTO director (
            company_id, director_name, identification_no, nationality, 
            address, date_of_birth, race, email
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("isssssss", 
                $company_id, $director_name, $identification_no, $nationality,
                $address, $date_of_birth, $race, $email
            );
            if ($stmt->execute()) {
                $successMsg = "✅ Director added successfully!";
                $current_step = 'complete';
                
                // Clear form
                $director_name = $identification_no = $nationality = '';
                $address = $date_of_birth = $race = $email = '';
            } else {
                $errors[] = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = "Failed to prepare statement: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Company & Members</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
body {
    background-color: #0f1b33;
    color: #e0e0e0;
    font-family: 'Segoe UI', sans-serif;
}
.navbar {
    background: linear-gradient(90deg, #004f99, #0072ff);
    padding: 0.8rem 2rem;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
}
.navbar-brand { font-weight: bold; color: #fff !important; }
.sidebar {
    width: 240px; height: 100vh; position: fixed; top: 0; left: 0;
    background: #00264d; padding-top: 70px; border-right: 2px solid #0072ff;
}
.sidebar a {
    color: #fff; display: block; padding: 12px 20px;
    text-decoration: none; border-radius: 8px;
}
.sidebar a:hover { background: #0072ff; color: #fff; padding-left: 25px; }
.content { margin-left: 240px; padding: 25px; min-height: 100vh; }

/* Progress Steps */
.progress-steps {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    position: relative;
}
.progress-steps::before {
    content: '';
    position: absolute;
    top: 15px;
    left: 0;
    right: 0;
    height: 3px;
    background: #0072ff;
    z-index: 1;
}
.step {
    text-align: center;
    position: relative;
    z-index: 2;
}
.step-circle {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: #0072ff;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px;
    font-weight: bold;
}
.step.active .step-circle {
    background: #00c6ff;
    box-shadow: 0 0 10px rgba(0,198,255,0.7);
}
.step.completed .step-circle {
    background: #00ff88;
}
.step-label {
    font-size: 14px;
    color: #aad4ff;
}

/* FORM */
.form-section {
    background: #10294d;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}
.form-section h4 {
    color: #00c6ff;
    margin-bottom: 15px;
}
.form-group label { font-weight: 500; margin-bottom: 6px; color: #aad4ff; }
.form-control, .form-select, textarea {
    background: #0f1b33;
    border: 1px solid #0072ff;
    color: #fff;
    border-radius: 8px;
    padding: 10px;
}
.form-control:focus, .form-select:focus, textarea:focus {
    border-color: #00c6ff;
    box-shadow: 0 0 6px rgba(0,198,255,0.4);
}
button.submit-btn {
    background: #0072ff;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    color: #fff;
    font-weight: 600;
    transition: background 0.3s ease, transform 0.2s ease;
    margin-right: 10px;
}
button.submit-btn:hover {
    background: #005fcc;
    transform: translateY(-2px);
}
button.next-btn {
    background: #00c6ff;
}
button.next-btn:hover {
    background: #00b4e6;
}
.alert-success { background: #1a7f38; color: #fff; }
.alert-danger { background: #721c24; color: #fff; }
.complete-section {
    text-align: center;
    padding: 50px 20px;
}
.complete-icon {
    font-size: 80px;
    color: #00ff88;
    margin-bottom: 20px;
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar fixed-top">
  <a class="navbar-brand" href="#"><i class="fa-solid fa-building"></i> Accounting System</a>
  <span class="ms-auto">Add Company & Members</span>
</nav>

<!-- SIDEBAR -->
<div class="sidebar">
  <h5 class="mt-3">Main</h5>
  <a href="index.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
  <a href="company_information.php" class="bg-primary"><i class="fa-solid fa-building"></i> Add Company</a>
</div>

<!-- CONTENT -->
<div class="content">
    <h2 class="fw-bold text-info mb-4"><i class="fa-solid fa-building-circle-check"></i> Add Company Information</h2>

    <!-- Progress Steps -->
    <div class="progress-steps">
        <div class="step <?= $current_step == 'company' ? 'active' : ($current_step == 'member' || $current_step == 'director' || $current_step == 'complete' ? 'completed' : '') ?>">
            <div class="step-circle">1</div>
            <div class="step-label">Company</div>
        </div>
        <div class="step <?= $current_step == 'member' ? 'active' : ($current_step == 'director' || $current_step == 'complete' ? 'completed' : '') ?>">
            <div class="step-circle">2</div>
            <div class="step-label">Members</div>
        </div>
        <div class="step <?= $current_step == 'director' ? 'active' : ($current_step == 'complete' ? 'completed' : '') ?>">
            <div class="step-circle">3</div>
            <div class="step-label">Directors</div>
        </div>
        <div class="step <?= $current_step == 'complete' ? 'active' : '' ?>">
            <div class="step-circle">4</div>
            <div class="step-label">Complete</div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger"><?php echo implode("<br>", $errors); ?></div>
    <?php endif; ?>
    <?php if (!empty($successMsg)): ?>
      <div class="alert alert-success"><?php echo $successMsg; ?></div>
    <?php endif; ?>

    <!-- Company Form -->
    <?php if ($current_step == 'company'): ?>
    <form action="company_information.php" method="POST">
        <input type="hidden" name="add_company" value="1">
        
        <!-- Company Info -->
        <div class="form-section">
            <h4><i class="fa-solid fa-circle-info"></i> Company Information</h4>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="company_name" class="form-label">Company Name *</label>
                    <input type="text" id="company_name" name="company_name" class="form-control" value="<?= htmlspecialchars($company_name) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="ssm_no" class="form-label">SSM No *</label>
                    <input type="text" id="ssm_no" name="ssm_no" class="form-control" value="<?= htmlspecialchars($ssm_no) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="company_type" class="form-label">Company Type</label>
                    <select id="company_type" name="company_type" class="form-select">
                        <option value="">Select</option>
                        <option value="A" <?= $company_type=="A"?"selected":"" ?>>A</option>
                        <option value="B" <?= $company_type=="B"?"selected":"" ?>>B</option>
                        <option value="C" <?= $company_type=="C"?"selected":"" ?>>C</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="sub_type" class="form-label">Sub Type</label>
                    <select id="sub_type" name="sub_type" class="form-select">
                        <option value="">Select</option>
                        <option value="SDN_BHD" <?= $sub_type=="SDN_BHD"?"selected":"" ?>>SDN_BHD</option>
                        <option value="SOLE_PROPRIETOR" <?= $sub_type=="SOLE_PROPRIETOR"?"selected":"" ?>>SOLE_PROPRIETOR</option>
                        <option value="PARTNERSHIP" <?= $sub_type=="PARTNERSHIP"?"selected":"" ?>>PARTNERSHIP</option>
                        <option value="LLP" <?= $sub_type=="LLP"?"selected":"" ?>>LLP</option>
                        <option value="BERHAD" <?= $sub_type=="BERHAD"?"selected":"" ?>>BERHAD</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="incorporation_date" class="form-label">Incorporation Date *</label>
                    <input type="date" id="incorporation_date" name="incorporation_date" class="form-control" value="<?= htmlspecialchars($incorporation_date) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="financial_year_end" class="form-label">Financial Year End</label>
                    <input type="date" id="financial_year_end" name="financial_year_end" class="form-control" value="<?= htmlspecialchars($financial_year_end) ?>">
                </div>
                <div class="col-12">
                    <label for="address" class="form-label">Address</label>
                    <textarea id="address" name="address" class="form-control"><?= htmlspecialchars($address) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email *</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="office_no" class="form-label">Office Tel</label>
                    <input type="text" id="office_no" name="office_no" class="form-control" value="<?= htmlspecialchars($office_no) ?>">
                </div>
                <div class="col-md-3">
                    <label for="fax_no" class="form-label">Fax</label>
                    <input type="text" id="fax_no" name="fax_no" class="form-control" value="<?= htmlspecialchars($fax_no) ?>">
                </div>
            </div>
        </div>

        <!-- Business Info -->
        <div class="form-section">
            <h4><i class="fa-solid fa-briefcase"></i> Business Information</h4>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="msic_code" class="form-label">MSIC Code</label>
                    <input type="text" id="msic_code" name="msic_code" class="form-control" value="<?= htmlspecialchars($msic_code) ?>">
                </div>
                <div class="col-12">
                    <label for="nature_of_business" class="form-label">Nature of Business</label>
                    <textarea id="nature_of_business" name="nature_of_business" class="form-control" rows="2"><?= htmlspecialchars($nature_of_business) ?></textarea>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Business Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="Provide a detailed description of the business activities, products, or services..."><?= htmlspecialchars($description) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Contact Persons -->
        <div class="form-section">
            <h4><i class="fa-solid fa-users"></i> Contact Persons</h4>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="accountant_name" class="form-label">Accountant Name</label>
                    <input type="text" id="accountant_name" name="accountant_name" class="form-control" value="<?= htmlspecialchars($accountant_name) ?>">
                </div>
                <div class="col-md-6">
                    <label for="accountant_phone" class="form-label">Accountant Phone</label>
                    <input type="text" id="accountant_phone" name="accountant_phone" class="form-control" value="<?= htmlspecialchars($accountant_phone) ?>">
                </div>
                <div class="col-md-6">
                    <label for="hr_name" class="form-label">HR Name</label>
                    <input type="text" id="hr_name" name="hr_name" class="form-control" value="<?= htmlspecialchars($hr_name) ?>">
                </div>
                <div class="col-md-6">
                    <label for="hr_phone" class="form-label">HR Phone</label>
                    <input type="text" id="hr_phone" name="hr_phone" class="form-control" value="<?= htmlspecialchars($hr_phone) ?>">
                </div>
            </div>
        </div>

        <button type="submit" class="submit-btn next-btn"><i class="fa-solid fa-plus-circle"></i> Add Company & Continue to Members</button>
    </form>
    <?php endif; ?>

    <!-- Member Form -->
    <?php if ($current_step == 'member'): ?>
    <form action="company_information.php" method="POST">
        <input type="hidden" name="add_member" value="1">
        <input type="hidden" name="company_id" value="<?= $company_id ?>">
        
        <div class="form-section">
            <h4><i class="fa-solid fa-user-plus"></i> Add Member</h4>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="member_name" class="form-label">Member Name *</label>
                    <input type="text" id="member_name" name="member_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label for="id_type" class="form-label">ID Type *</label>
                    <select id="id_type" name="id_type" class="form-select" required>
                        <option value="NRIC">NRIC</option>
                        <option value="PASSPORT">PASSPORT</option>
                        <option value="ARMY/POLICE_ID">ARMY/POLICE ID</option>
                        <option value="OTHER">OTHER</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="identification_no" class="form-label">Identification No *</label>
                    <input type="text" id="identification_no" name="identification_no" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label for="nationality" class="form-label">Nationality</label>
                    <input type="text" id="nationality" name="nationality" class="form-control">
                </div>
                <div class="col-12">
                    <label for="address" class="form-label">Address</label>
                    <textarea id="address" name="address" class="form-control"></textarea>
                </div>
                <div class="col-md-4">
                    <label for="race" class="form-label">Race</label>
                    <input type="text" id="race" name="race" class="form-control">
                </div>
                <div class="col-md-4">
                    <label for="price_per_share" class="form-label">Price Per Share</label>
                    <input type="number" id="price_per_share" name="price_per_share" class="form-control">
                </div>
                <div class="col-md-4">
                    <label for="class_of_share" class="form-label">Class of Share</label>
                    <select id="class_of_share" name="class_of_share" class="form-select">
                        <option value="Ordinary">Ordinary</option>
                        <option value="Preference">Preference</option>
                        <option value="Redeemable">Redeemable</option>
                        <option value="Employee">Employee</option>
                    </select>
                </div>
                <div class="col-12">
                    <label for="number_of_share" class="form-label">Number of Share</label>
                    <input type="text" id="number_of_share" name="number_of_share" class="form-control">
                </div>
            </div>
        </div>

        <button type="submit" class="submit-btn next-btn"><i class="fa-solid fa-user-plus"></i> Add Member & Continue to Directors</button>
        <a href="company_information.php?step=director&company_id=<?= $company_id ?>" class="submit-btn" style="text-decoration: none; display: inline-block;">
            <i class="fa-solid fa-forward"></i> Skip to Directors
        </a>
    </form>
    <?php endif; ?>

    <!-- Director Form -->
    <?php if ($current_step == 'director'): ?>
    <form action="company_information.php" method="POST">
        <input type="hidden" name="add_director" value="1">
        <input type="hidden" name="company_id" value="<?= $company_id ?>">
        
        <div class="form-section">
            <h4><i class="fa-solid fa-user-tie"></i> Add Director</h4>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="director_name" class="form-label">Director Name *</label>
                    <input type="text" id="director_name" name="director_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label for="identification_no" class="form-label">Identification No *</label>
                    <input type="text" id="identification_no" name="identification_no" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label for="nationality" class="form-label">Nationality</label>
                    <input type="text" id="nationality" name="nationality" class="form-control">
                </div>
                <div class="col-md-6">
                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control">
                </div>
                <div class="col-12">
                    <label for="address" class="form-label">Address</label>
                    <textarea id="address" name="address" class="form-control"></textarea>
                </div>
                <div class="col-md-6">
                    <label for="race" class="form-label">Race</label>
                    <input type="text" id="race" name="race" class="form-control">
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email *</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
            </div>
        </div>

        <button type="submit" class="submit-btn next-btn"><i class="fa-solid fa-user-tie"></i> Add Director & Complete</button>
        <a href="company_information.php?step=complete&company_id=<?= $company_id ?>" class="submit-btn" style="text-decoration: none; display: inline-block;">
            <i class="fa-solid fa-flag-checkered"></i> Skip to Complete
        </a>
    </form>
    <?php endif; ?>

    <!-- Complete Section -->
    <?php if ($current_step == 'complete'): ?>
    <div class="form-section complete-section">
        <div class="complete-icon">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <h3 class="text-success mb-3">Setup Complete!</h3>
        <p class="mb-4">Your company, members, and directors have been successfully added to the system.</p>
        <div class="d-flex justify-content-center gap-3">
            <a href="company_information.php" class="submit-btn">
                <i class="fa-solid fa-plus"></i> Add Another Company
            </a>
            <a href="index.php" class="submit-btn next-btn">
                <i class="fa-solid fa-home"></i> Go to Dashboard
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
<?php $conn->close(); ?>