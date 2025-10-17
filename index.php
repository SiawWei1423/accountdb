<?php
session_start();
require_once('db_connection.php');

// Debug mode - remove this after fixing
if (isset($_GET['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Helper: some users may be regular user records but have admin privileges
// via the `role` field (e.g. user.role = 'admin'). Normalize that into
// an explicit boolean to use for permission checks and UI.
$isAdmin = false;
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
  $isAdmin = true;
} elseif (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
  $isAdmin = true;
}

// Determine display role: prefer the role stored from the user table (e.g. manager,
// accountant). Fall back to user_type or Admin/User based on $isAdmin.
$displayRole = 'User';
if (!empty($_SESSION['role'])) {
  $displayRole = ucfirst($_SESSION['role']);
} elseif (!empty($_SESSION['user_type'])) {
  $displayRole = ucfirst($_SESSION['user_type']);
} elseif ($isAdmin) {
  $displayRole = 'Admin';
}

// Check if user still exists in database and get profile data
$current_user = [];
// If the session originated from the admin table, load admin record. Otherwise
// load the user record. Note: users who are 'admin' via the user.role field are
// still stored as user records (so $isAdmin may be true while we still load
// the user table). This preserves correct profile data while allowing admin
// privileges.
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
  $result = $conn->query("SELECT * FROM admin WHERE admin_id = " . $_SESSION['user_id']);
  if (!$result || $result->num_rows === 0) {
    session_destroy();
    header('Location: login.php');
    exit;
  } else {
    $current_user = $result->fetch_assoc();
  }
} else {
  $result = $conn->query("SELECT * FROM user WHERE user_id = " . $_SESSION['user_id']);
  if (!$result || $result->num_rows === 0) {
    session_destroy();
    header('Location: login.php');
    exit;
  } else {
    $current_user = $result->fetch_assoc();
  }
}

// Create member and director tables if they don't exist
$create_member_table = "
CREATE TABLE IF NOT EXISTS `member` (
  `member_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `member_name` varchar(255) NOT NULL,
  `id_type` varchar(50) NOT NULL,
  `identification_no` varchar(100) NOT NULL,
  `nationality` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `race` varchar(50) NOT NULL,
  `price_per_share` decimal(15,2) NOT NULL,
  `class_of_share` varchar(50) NOT NULL,
  `number_of_share` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`member_id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
";

$create_director_table = "
CREATE TABLE IF NOT EXISTS `director` (
  `director_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `director_name` varchar(255) NOT NULL,
  `identification_no` varchar(100) NOT NULL,
  `nationality` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `date_of_birth` date NOT NULL,
  `race` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  PRIMARY KEY (`director_id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
";

// Create company table if it doesn't exist
$create_company_table = "
CREATE TABLE IF NOT EXISTS `company` (
  `company_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `ssm_no` varchar(100) NOT NULL,
  `company_type` varchar(50) NOT NULL,
  `sub_type` varchar(100) DEFAULT NULL,
  `incorporation_date` date DEFAULT NULL,
  `financial_year_end` date DEFAULT NULL,
  `nature_of_business` text,
  `msic_code` varchar(255) DEFAULT NULL,
  `address` text,
  `email` varchar(255) DEFAULT NULL,
  `office_no` varchar(50) DEFAULT NULL,
  `fax_no` varchar(50) DEFAULT NULL,
  `accountant_name` varchar(255) DEFAULT NULL,
  `accountant_phone` varchar(50) DEFAULT NULL,
  `accountant_email` varchar(255) DEFAULT NULL,
  `hr_name` varchar(255) DEFAULT NULL,
  `hr_phone` varchar(50) DEFAULT NULL,
  `hr_email` varchar(255) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
";

// Create document table if it doesn't exist
$create_document_table = "
CREATE TABLE IF NOT EXISTS `document` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_title` varchar(255) NOT NULL,
  `document_type` enum('Sales','Receiving','Purchase','Payment','Bank Statement','Journal','Others') NOT NULL,
  `source_type` enum('Supplier','Customer','Bank','Government','Client','Internal') DEFAULT 'Client',
  `source_name` varchar(255) DEFAULT NULL,
  `source_reference` varchar(255) DEFAULT NULL,
  `description` text,
  `file_name` varchar(255) NOT NULL,
  `company_id` int(11) NOT NULL,
  `location` text,
  `date_of_collect` date NOT NULL,
  `status` enum('Pending','Reviewed','Approved','Final Approved','Rejected','Returned','Submit') DEFAULT 'Pending',
  `created_by` int(11) NOT NULL,
  `current_handler` int(11) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `audited_by` int(11) DEFAULT NULL,
  `returned_by` int(11) DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_id`),
  KEY `company_id` (`company_id`),
  KEY `created_by` (`created_by`),
  KEY `current_handler` (`current_handler`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
";

// Create document history table for audit trail
$create_document_history_table = "
CREATE TABLE IF NOT EXISTS `document_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `comments` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `document_id` (`document_id`),
  KEY `performed_by` (`performed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
";

// Create document files table to store multiple files per document
$create_document_files_table = "
CREATE TABLE IF NOT EXISTS `document_files` (
  `file_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `category` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `supplier_pi_no` varchar(255) DEFAULT NULL,
  `inventory` text DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `tax_amount` decimal(15,2) DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `invoice_number` varchar(255) DEFAULT NULL,
  `sales_date` date DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `account_number` varchar(255) DEFAULT NULL,
  `statement_period` varchar(255) DEFAULT NULL,
  `total_debit` decimal(15,2) DEFAULT NULL,
  `total_credit` decimal(15,2) DEFAULT NULL,
  `balance` decimal(15,2) DEFAULT NULL,
  `agency_name` varchar(255) DEFAULT NULL,
  `reference_no` varchar(255) DEFAULT NULL,
  `period_covered` varchar(255) DEFAULT NULL,
  `submission_date` date DEFAULT NULL,
  `amount_paid` decimal(15,2) DEFAULT NULL,
  `acknowledgement_file` varchar(255) DEFAULT NULL,
  `employee_name` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `claim_date` date DEFAULT NULL,
  `approved_by` varchar(255) DEFAULT NULL,
  `details_entered_by` int(11) DEFAULT NULL,
  `details_entered_at` datetime DEFAULT NULL,
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`file_id`),
  KEY `document_id` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
";

// Create notifications table
$create_notifications_table = "
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL,
  `action_by` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_shown` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  KEY `document_id` (`document_id`),
  KEY `is_read` (`is_read`),
  KEY `is_shown` (`is_shown`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
";

// Execute table creation
$conn->query($create_company_table);
$conn->query($create_member_table);
$conn->query($create_director_table);
$conn->query($create_document_table);
$conn->query($create_document_history_table);

// Add business_address column to company table if it doesn't exist
$check_business_address = $conn->query("SHOW COLUMNS FROM `company` LIKE 'business_address'");
if ($check_business_address && $check_business_address->num_rows == 0) {
    $conn->query("ALTER TABLE `company` ADD COLUMN `business_address` TEXT DEFAULT NULL AFTER `address`");
}

// Add email column to member table if it doesn't exist
$check_member_email = $conn->query("SHOW COLUMNS FROM `member` LIKE 'email'");
if ($check_member_email && $check_member_email->num_rows == 0) {
    $conn->query("ALTER TABLE `member` ADD COLUMN `email` varchar(255) DEFAULT NULL");
}
$conn->query($create_document_files_table);
$conn->query($create_notifications_table);

// Create financial year-end notifications tracking table
$create_fye_notifications_table = "
CREATE TABLE IF NOT EXISTS `fye_notifications` (
  `fye_notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `notification_year` int(11) NOT NULL,
  `notification_sent` tinyint(1) DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`fye_notification_id`),
  UNIQUE KEY `company_year` (`company_id`, `notification_year`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
";
$conn->query($create_fye_notifications_table);

// Ensure company table has sub_type column
$check_sub_type = $conn->query("SHOW COLUMNS FROM company LIKE 'sub_type'");
if ($check_sub_type->num_rows == 0) {
    $conn->query("ALTER TABLE company ADD COLUMN sub_type VARCHAR(100) AFTER company_type");
}

// Ensure company table has subsequent_year_end column
$check_subsequent_year_end = $conn->query("SHOW COLUMNS FROM company LIKE 'subsequent_year_end'");
if ($check_subsequent_year_end->num_rows == 0) {
    $conn->query("ALTER TABLE company ADD COLUMN subsequent_year_end DATE DEFAULT NULL AFTER financial_year_end");
}

// Ensure document table has new tracking columns
$tracking_columns = ['reviewed_by', 'approved_by', 'audited_by', 'returned_by', 'rejected_by'];
foreach ($tracking_columns as $col) {
    $check_col = $conn->query("SHOW COLUMNS FROM document LIKE '$col'");
    if ($check_col->num_rows == 0) {
        $conn->query("ALTER TABLE document ADD COLUMN $col INT(11) DEFAULT NULL AFTER current_handler");
    }
}

// Update status enum to include 'Final Approved' and 'Submit'
$conn->query("ALTER TABLE document MODIFY COLUMN status ENUM('Pending','Reviewed','Approved','Final Approved','Rejected','Returned','Submit') DEFAULT 'Pending'");

// Ensure document table has document_category column
$check_doc_category = $conn->query("SHOW COLUMNS FROM document LIKE 'document_category'");
if ($check_doc_category->num_rows == 0) {
    $conn->query("ALTER TABLE document ADD COLUMN document_category VARCHAR(255) DEFAULT NULL AFTER source_type");
}

// Ensure document_history table has correct structure
$check_history_table = $conn->query("SHOW TABLES LIKE 'document_history'");
if ($check_history_table->num_rows > 0) {
    // Check if action column exists
    $check_action_col = $conn->query("SHOW COLUMNS FROM document_history LIKE 'action'");
    if ($check_action_col->num_rows == 0) {
        // Drop and recreate table with correct structure
        $conn->query("DROP TABLE IF EXISTS document_history");
        $conn->query($create_document_history_table);
    }
}

// Ensure document_files table has supplier detail columns
$supplier_columns = [
    'supplier_name' => 'VARCHAR(255) DEFAULT NULL',
    'supplier_pi_no' => 'VARCHAR(255) DEFAULT NULL',
    'inventory' => 'TEXT DEFAULT NULL',
    'invoice_date' => 'DATE DEFAULT NULL',
    'amount' => 'DECIMAL(15,2) DEFAULT NULL',
    'tax_amount' => 'DECIMAL(15,2) DEFAULT NULL',
    'total_amount' => 'DECIMAL(15,2) DEFAULT NULL',
    'customer_name' => 'VARCHAR(255) DEFAULT NULL',
    'invoice_number' => 'VARCHAR(255) DEFAULT NULL',
    'sales_date' => 'DATE DEFAULT NULL',
    'bank_name' => 'VARCHAR(255) DEFAULT NULL',
    'account_number' => 'VARCHAR(255) DEFAULT NULL',
    'statement_period' => 'VARCHAR(255) DEFAULT NULL',
    'total_debit' => 'DECIMAL(15,2) DEFAULT NULL',
    'total_credit' => 'DECIMAL(15,2) DEFAULT NULL',
    'balance' => 'DECIMAL(15,2) DEFAULT NULL',
    'agency_name' => 'VARCHAR(255) DEFAULT NULL',
    'reference_no' => 'VARCHAR(255) DEFAULT NULL',
    'period_covered' => 'VARCHAR(255) DEFAULT NULL',
    'submission_date' => 'DATE DEFAULT NULL',
    'amount_paid' => 'DECIMAL(15,2) DEFAULT NULL',
    'acknowledgement_file' => 'VARCHAR(255) DEFAULT NULL',
    'employee_name' => 'VARCHAR(255) DEFAULT NULL',
    'department' => 'VARCHAR(255) DEFAULT NULL',
    'claim_date' => 'DATE DEFAULT NULL',
    'approved_by' => 'VARCHAR(255) DEFAULT NULL',
    'details_entered_by' => 'INT(11) DEFAULT NULL',
    'details_entered_at' => 'DATETIME DEFAULT NULL'
];
foreach ($supplier_columns as $col => $definition) {
    $check_col = $conn->query("SHOW COLUMNS FROM document_files LIKE '$col'");
    if ($check_col->num_rows == 0) {
        $conn->query("ALTER TABLE document_files ADD COLUMN $col $definition");
    }
}

// Ensure notifications table has all required columns
$check_notifications_table = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check_notifications_table->num_rows > 0) {
    // Check for is_shown column
    $check_is_shown = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_shown'");
    if ($check_is_shown->num_rows == 0) {
        $conn->query("ALTER TABLE notifications ADD COLUMN is_shown TINYINT(1) DEFAULT 0 AFTER is_read");
        $conn->query("ALTER TABLE notifications ADD INDEX idx_is_shown (is_shown)");
    }
    
    // Check for action_by column
    $check_action_by = $conn->query("SHOW COLUMNS FROM notifications LIKE 'action_by'");
    if ($check_action_by->num_rows == 0) {
        $conn->query("ALTER TABLE notifications ADD COLUMN action_by INT(11) DEFAULT NULL AFTER type");
    }
}

// Helper function to create notifications
function createNotification($conn, $userId, $documentId, $title, $message, $type, $actionBy = null) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, document_id, title, message, type, action_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssi", $userId, $documentId, $title, $message, $type, $actionBy);
    return $stmt->execute();
}

// Function to check and create financial year-end notifications
function checkFinancialYearEndNotifications($conn) {
    // Get all companies with financial year-end dates
    $companiesQuery = $conn->query("
        SELECT company_id, company_name, financial_year_end 
        FROM company 
        WHERE financial_year_end IS NOT NULL
    ");
    
    if (!$companiesQuery) return;
    
    $currentDate = new DateTime();
    $currentYear = (int)$currentDate->format('Y');
    
    while ($company = $companiesQuery->fetch_assoc()) {
        $companyId = $company['company_id'];
        $companyName = $company['company_name'];
        $fyeDate = new DateTime($company['financial_year_end']);
        
        // Calculate this year's FYE date
        $thisYearFYE = new DateTime($currentYear . '-' . $fyeDate->format('m-d'));
        
        // If this year's FYE has passed, check next year
        if ($thisYearFYE < $currentDate) {
            $thisYearFYE->modify('+1 year');
            $notificationYear = $currentYear + 1;
        } else {
            $notificationYear = $currentYear;
        }
        
        // Calculate notification date (30 days before FYE)
        $notificationDate = clone $thisYearFYE;
        $notificationDate->modify('-30 days');
        
        // Check if we should send notification (within 30 days window)
        $daysUntilFYE = $currentDate->diff($thisYearFYE)->days;
        $isPast = $currentDate > $thisYearFYE;
        
        if (!$isPast && $daysUntilFYE <= 30) {
            // Check if notification already sent for this year
            $checkQuery = $conn->prepare("
                SELECT notification_sent 
                FROM fye_notifications 
                WHERE company_id = ? AND notification_year = ?
            ");
            $checkQuery->bind_param("ii", $companyId, $notificationYear);
            $checkQuery->execute();
            $result = $checkQuery->get_result();
            
            if ($result->num_rows == 0) {
                // Create notification record
                $insertTrack = $conn->prepare("
                    INSERT INTO fye_notifications (company_id, notification_year, notification_sent, sent_at) 
                    VALUES (?, ?, 1, NOW())
                ");
                $insertTrack->bind_param("ii", $companyId, $notificationYear);
                $insertTrack->execute();
                
                // Get all users (employees and admins) to notify
                $usersQuery = $conn->query("
                    SELECT user_id FROM user WHERE role IN ('employee', 'accountant', 'manager', 'auditor')
                    UNION
                    SELECT admin_id as user_id FROM admin
                ");
                
                $title = "Financial Year-End Reminder";
                $fyeDateFormatted = $thisYearFYE->format('d M Y');
                $message = "The financial year-end for {$companyName} is approaching on {$fyeDateFormatted} ({$daysUntilFYE} days remaining). Please ensure all necessary documents and reports are prepared.";
                
                // Create notification for each user
                while ($user = $usersQuery->fetch_assoc()) {
                    createNotification($conn, $user['user_id'], null, $title, $message, 'financial_year_end', null);
                }
            } elseif ($result->fetch_assoc()['notification_sent'] == 0) {
                // Update existing record
                $updateTrack = $conn->prepare("
                    UPDATE fye_notifications 
                    SET notification_sent = 1, sent_at = NOW() 
                    WHERE company_id = ? AND notification_year = ?
                ");
                $updateTrack->bind_param("ii", $companyId, $notificationYear);
                $updateTrack->execute();
                
                // Get all users to notify
                $usersQuery = $conn->query("
                    SELECT user_id FROM user WHERE role IN ('employee', 'accountant', 'manager', 'auditor')
                    UNION
                    SELECT admin_id as user_id FROM admin
                ");
                
                $title = "Financial Year-End Reminder";
                $fyeDateFormatted = $thisYearFYE->format('d M Y');
                $message = "The financial year-end for {$companyName} is approaching on {$fyeDateFormatted} ({$daysUntilFYE} days remaining). Please ensure all necessary documents and reports are prepared.";
                
                while ($user = $usersQuery->fetch_assoc()) {
                    createNotification($conn, $user['user_id'], null, $title, $message, 'financial_year_end', null);
                }
            }
        }
    }
}

// Check for financial year-end notifications on page load
if (isset($_SESSION['user_id'])) {
    checkFinancialYearEndNotifications($conn);
}

// Helper function to notify next handler in workflow
function notifyNextHandler($conn, $documentId, $newStatus, $actionBy) {
    // Get document details
    $docQuery = $conn->query("SELECT d.*, c.company_name, COALESCE(u.full_name, a.full_name) as creator_name 
                              FROM document d 
                              LEFT JOIN company c ON d.company_id = c.company_id
                              LEFT JOIN user u ON d.created_by = u.user_id
                              LEFT JOIN admin a ON d.created_by = a.admin_id
                              WHERE d.document_id = $documentId");
    if (!$docQuery || $docQuery->num_rows == 0) return;
    
    $doc = $docQuery->fetch_assoc();
    $docTitle = $doc['document_title'];
    $companyName = $doc['company_name'] ?? 'N/A';
    
    // Get action performer name
    $actionByName = 'Someone';
    $actionQuery = $conn->query("SELECT full_name FROM user WHERE user_id = $actionBy 
                                 UNION SELECT full_name FROM admin WHERE admin_id = $actionBy");
    if ($actionQuery && $actionQuery->num_rows > 0) {
        $actionByName = $actionQuery->fetch_assoc()['full_name'];
    }
    
    // Determine who to notify based on status
    $notifyUsers = [];
    
    switch($newStatus) {
        case 'Pending':
            // Workflow: Employee creates → Notify Accountants to review
            $accountantQuery = $conn->query("SELECT user_id FROM user WHERE role = 'accountant'");
            while ($row = $accountantQuery->fetch_assoc()) {
                $notifyUsers[] = $row['user_id'];
            }
            $message = "$actionByName submitted a new document: '$docTitle' for $companyName. Please review.";
            $title = "New Document - Needs Review";
            break;
            
        case 'Reviewed':
            // Workflow: Accountant reviewed → Notify Managers to approve
            $managerQuery = $conn->query("SELECT user_id FROM user WHERE role = 'manager'");
            while ($row = $managerQuery->fetch_assoc()) {
                $notifyUsers[] = $row['user_id'];
            }
            $message = "$actionByName reviewed document: '$docTitle' for $companyName. Awaiting your approval.";
            $title = "Document Reviewed - Needs Approval";
            break;
            
        case 'Approved':
            // Workflow: Manager approved → Notify Auditors for final approval
            $auditorQuery = $conn->query("SELECT user_id FROM user WHERE role = 'auditor'");
            while ($row = $auditorQuery->fetch_assoc()) {
                $notifyUsers[] = $row['user_id'];
            }
            $message = "$actionByName approved document: '$docTitle' for $companyName. Awaiting final approval.";
            $title = "Document Approved - Needs Final Approval";
            break;
            
        case 'Final Approved':
            // Workflow: Auditor gave final approval → Notify creator to submit to client
            if ($doc['created_by']) {
                $notifyUsers[] = $doc['created_by'];
            }
            $message = "$actionByName gave final approval to document: '$docTitle' for $companyName. You can now submit to client.";
            $title = "Document Final Approved - Ready to Submit";
            break;
            
        case 'Submit':
            // Workflow: Document submitted to client → Notify all admins and managers
            $adminQuery = $conn->query("SELECT admin_id FROM admin");
            while ($row = $adminQuery->fetch_assoc()) {
                $notifyUsers[] = $row['admin_id'];
            }
            $managerQuery = $conn->query("SELECT user_id FROM user WHERE role = 'manager'");
            while ($row = $managerQuery->fetch_assoc()) {
                $notifyUsers[] = $row['user_id'];
            }
            $message = "$actionByName submitted document: '$docTitle' for $companyName to client.";
            $title = "Document Submitted to Client";
            break;
            
        case 'Rejected':
            // Notify creator
            if ($doc['created_by']) {
                $notifyUsers[] = $doc['created_by'];
            }
            $message = "$actionByName rejected document: '$docTitle' for $companyName. Please review the feedback.";
            $title = "Document Rejected";
            break;
            
        case 'Returned':
            // Determine who to notify based on current handler
            if ($doc['current_handler']) {
                $notifyUsers[] = $doc['current_handler'];
            } else if ($doc['created_by']) {
                $notifyUsers[] = $doc['created_by'];
            }
            $message = "$actionByName returned document: '$docTitle' for $companyName for revision.";
            $title = "Document Returned - Needs Revision";
            break;
    }
    
    // Create notifications for all relevant users
    foreach (array_unique($notifyUsers) as $userId) {
        if ($userId != $actionBy) { // Don't notify the person who performed the action
            createNotification($conn, $userId, $documentId, $title, $message, $newStatus, $actionBy);
        }
    }
}

// Fetch counts
$totalCompanies = $totalUsers = $totalAdmins = $totalDocuments = $totalQueries = $totalReviews = 0;

$res = $conn->query("SELECT COUNT(*) AS total FROM company");
if ($res) $totalCompanies = $res->fetch_assoc()['total'];

$uRes = $conn->query("SELECT COUNT(*) AS total FROM user");
if ($uRes) $totalUsers = $uRes->fetch_assoc()['total'];

$aRes = $conn->query("SELECT COUNT(*) AS total FROM admin");



if ($aRes) $totalAdmins = $aRes->fetch_assoc()['total'];

$dRes = $conn->query("SELECT COUNT(*) AS total FROM document");
if ($dRes) $totalDocuments = $dRes->fetch_assoc()['total'];

// Check if client_queries table exists before querying
$tableCheck = $conn->query("SHOW TABLES LIKE 'client_queries'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $qRes = $conn->query("SELECT COUNT(*) AS total FROM client_queries");
    if ($qRes) $totalQueries = $qRes->fetch_assoc()['total'];
}

// Check if manager_reviews table exists before querying
$reviewTableCheck = $conn->query("SHOW TABLES LIKE 'manager_reviews'");
if ($reviewTableCheck && $reviewTableCheck->num_rows > 0) {
    $rRes = $conn->query("SELECT COUNT(*) AS total FROM manager_reviews");
    if ($rRes) $totalReviews = $rRes->fetch_assoc()['total'];
}

// Fetch additional dashboard statistics
$pendingDocuments = 0;
$reviewedDocuments = 0;
$approvedDocuments = 0;
$rejectedDocuments = 0;
$recentDocuments = [];

$pendingRes = $conn->query("SELECT COUNT(*) AS total FROM document WHERE status = 'Pending'");
if ($pendingRes) $pendingDocuments = $pendingRes->fetch_assoc()['total'];

$reviewedRes = $conn->query("SELECT COUNT(*) AS total FROM document WHERE status = 'Reviewed'");
if ($reviewedRes) $reviewedDocuments = $reviewedRes->fetch_assoc()['total'];

$approvedRes = $conn->query("SELECT COUNT(*) AS total FROM document WHERE status IN ('Approved', 'Final Approved')");
if ($approvedRes) $approvedDocuments = $approvedRes->fetch_assoc()['total'];

$rejectedRes = $conn->query("SELECT COUNT(*) AS total FROM document WHERE status = 'Rejected'");
if ($rejectedRes) $rejectedDocuments = $rejectedRes->fetch_assoc()['total'];

// Fetch recent documents (last 5)
$recentQuery = "SELECT d.*, c.company_name, 
                COALESCE(u.full_name, a.full_name) as creator_name
                FROM document d 
                LEFT JOIN company c ON d.company_id = c.company_id
                LEFT JOIN user u ON d.created_by = u.user_id
                LEFT JOIN admin a ON d.created_by = a.admin_id
                ORDER BY d.created_at DESC LIMIT 5";
$recentRes = $conn->query($recentQuery);
if ($recentRes) {
    while ($row = $recentRes->fetch_assoc()) {
        $recentDocuments[] = $row;
    }
}

// Fetch notifications for current user
$unreadNotifications = 0;
$unshownNotifications = [];
$allNotifications = [];

$currentUserId = $_SESSION['user_id'];
$unreadQuery = $conn->query("SELECT COUNT(*) as total FROM notifications WHERE user_id = $currentUserId AND is_read = 0");
if ($unreadQuery) {
    $unreadNotifications = $unreadQuery->fetch_assoc()['total'];
}

// Get unshown notifications (for popup)
$unshownQuery = $conn->query("SELECT n.*, COALESCE(u.full_name, a.full_name) as action_by_name 
                              FROM notifications n
                              LEFT JOIN user u ON n.action_by = u.user_id
                              LEFT JOIN admin a ON n.action_by = a.admin_id
                              WHERE n.user_id = $currentUserId AND n.is_shown = 0 
                              ORDER BY n.created_at DESC");
if ($unshownQuery) {
    while ($row = $unshownQuery->fetch_assoc()) {
        $unshownNotifications[] = $row;
    }
}

// Get all notifications for notification center
$allNotifQuery = $conn->query("SELECT n.*, COALESCE(u.full_name, a.full_name) as action_by_name 
                               FROM notifications n
                               LEFT JOIN user u ON n.action_by = u.user_id
                               LEFT JOIN admin a ON n.action_by = a.admin_id
                               WHERE n.user_id = $currentUserId 
                               ORDER BY n.created_at DESC LIMIT 50");
if ($allNotifQuery) {
    while ($row = $allNotifQuery->fetch_assoc()) {
        $allNotifications[] = $row;
    }
}

// Fetch companies - ORDER BY ASC to show oldest first, new at bottom
$companies = [];
$cRes = $conn->query("SELECT * FROM company ORDER BY company_id ASC");
if ($cRes) {
    while ($row = $cRes->fetch_assoc()) {
        $companies[] = $row;
    }
} else {
    echo "Query error: " . $conn->error;
}

// Fetch users (only for admins) - ORDER BY ASC to show oldest first, new at bottom
$users = [];
if ($isAdmin) {
    $userRes = $conn->query("SELECT * FROM user ORDER BY user_id ASC");
    if ($userRes) {
        while ($row = $userRes->fetch_assoc()) {
            $users[] = $row;
        }
    }
}

// Fetch admins (only for admins) - ORDER BY ASC to show oldest first, new at bottom
$admins = [];
if ($_SESSION['user_type'] === 'admin') {
    $adminRes = $conn->query("SELECT * FROM admin ORDER BY admin_id ASC");
    if ($adminRes) {
        while ($row = $adminRes->fetch_assoc()) {
            $admins[] = $row;
        }
    }
}

// Fetch documents based on user role
$documents = [];
$documentQuery = "SELECT d.*, c.company_name, 
                 COALESCE(u.full_name, a.full_name) as creator_name, 
                 h.full_name as handler_name
                 FROM document d 
                 LEFT JOIN company c ON d.company_id = c.company_id
                 LEFT JOIN user u ON d.created_by = u.user_id
                 LEFT JOIN admin a ON d.created_by = a.admin_id
                 LEFT JOIN user h ON d.current_handler = h.user_id
                 WHERE 1=1";

// Role-based filtering - current_handler is just an indicator, not a restriction
// All users can see all documents, current_handler just shows who is working on it
if (!$isAdmin) {
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['role'] ?? 'employee';
    
    // All roles can see all documents
    // current_handler field is just for tracking who is handling the document
    // No filtering needed - everyone can see all documents
}

$documentQuery .= " ORDER BY d.document_id DESC";
$docRes = $conn->query($documentQuery);
if ($docRes) {
    while ($row = $docRes->fetch_assoc()) {
        $documents[] = $row;
    }
}

// Handle AJAX operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Check if user is admin for admin-only actions
    $adminOnlyActions = ['add_user', 'add_admin', 'edit_admin', 'delete_admin', 'edit_user', 'delete_user', 'change_user_password'];
    if (in_array($_POST['action'], $adminOnlyActions) && $_SESSION['user_type'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        if ($_POST['action'] === 'edit_user') {
            $user_id = $conn->real_escape_string($_POST['user_id']);
            $full_name = $conn->real_escape_string($_POST['full_name']);
            $email = $conn->real_escape_string($_POST['email']);
            $phone = $conn->real_escape_string($_POST['phone']);
            $role = $conn->real_escape_string($_POST['role']);
            
            // Handle profile picture upload
            $image_url = '';
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
                $uploadDir = 'uploads/profile_pictures/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileExtension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $fileName = 'user_' . $user_id . '_' . time() . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;
                
                // Check file type
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array(strtolower($fileExtension), $allowedTypes)) {
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filePath)) {
                        $image_url = $filePath;
                    }
                }
            }
            
            if ($image_url) {
                $sql = "UPDATE user SET full_name='$full_name', email='$email', phone='$phone', role='$role', image_url='$image_url' WHERE user_id='$user_id'";
            } else {
                $sql = "UPDATE user SET full_name='$full_name', email='$email', phone='$phone', role='$role' WHERE user_id='$user_id'";
            }
            
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'User updated successfully!';
                if ($image_url) {
                    $response['image_url'] = $image_url;
                }
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'change_user_password') {
            $user_id = $conn->real_escape_string($_POST['user_id']);
            $new_password = $conn->real_escape_string($_POST['new_password']);
            
            // Validate password length
            if (strlen($new_password) < 6) {
                throw new Exception('Password must be at least 6 characters long');
            }
            
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $sql = "UPDATE user SET password='$hashed_password' WHERE user_id='$user_id'";
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'User password updated successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'delete_user') {
            $user_id = $conn->real_escape_string($_POST['user_id']);
            $sql = "DELETE FROM user WHERE user_id='$user_id'";
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'User deleted successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'edit_admin') {
            $admin_id = $conn->real_escape_string($_POST['admin_id']);
            $full_name = $conn->real_escape_string($_POST['full_name']);
            $email = $conn->real_escape_string($_POST['email']);
            $phone = $conn->real_escape_string($_POST['phone']);
            
            // Handle profile picture upload
            $image_url = '';
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
                $uploadDir = 'uploads/profile_pictures/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileExtension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $fileName = 'admin_' . $admin_id . '_' . time() . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;
                
                // Check file type
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array(strtolower($fileExtension), $allowedTypes)) {
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filePath)) {
                        $image_url = $filePath;
                    }
                }
            }
            
            if ($image_url) {
                $sql = "UPDATE admin SET full_name='$full_name', email='$email', phone='$phone', image_url='$image_url' WHERE admin_id='$admin_id'";
            } else {
                $sql = "UPDATE admin SET full_name='$full_name', email='$email', phone='$phone' WHERE admin_id='$admin_id'";
            }
            
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'Admin updated successfully!';
                if ($image_url) {
                    $response['image_url'] = $image_url;
                }
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'delete_admin') {
            $admin_id = $conn->real_escape_string($_POST['admin_id']);
            $sql = "DELETE FROM admin WHERE admin_id='$admin_id'";
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'Admin deleted successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'add_user') {
            $full_name = $conn->real_escape_string($_POST['full_name']);
            $email = $conn->real_escape_string($_POST['email']);
            $phone = $conn->real_escape_string($_POST['phone']);
            $role = $conn->real_escape_string($_POST['role']);
            $password = password_hash($conn->real_escape_string($_POST['password']), PASSWORD_DEFAULT);
            
            // Handle profile picture upload
            $image_url = '';
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
                $uploadDir = 'uploads/profile_pictures/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileExtension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $fileName = 'user_new_' . time() . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;
                
                // Check file type
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array(strtolower($fileExtension), $allowedTypes)) {
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filePath)) {
                        $image_url = $filePath;
                    }
                }
            }
            
            if ($image_url) {
                $sql = "INSERT INTO user (full_name, email, phone, role, password, image_url) 
                        VALUES ('$full_name', '$email', '$phone', '$role', '$password', '$image_url')";
            } else {
                $sql = "INSERT INTO user (full_name, email, phone, role, password) 
                        VALUES ('$full_name', '$email', '$phone', '$role', '$password')";
            }
            
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'User added successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'add_admin') {
            $full_name = $conn->real_escape_string($_POST['full_name']);
            $email = $conn->real_escape_string($_POST['email']);
            $phone = $conn->real_escape_string($_POST['phone']);
            $password = password_hash($conn->real_escape_string($_POST['password']), PASSWORD_DEFAULT);
            
            // Handle profile picture upload
            $image_url = '';
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
                $uploadDir = 'uploads/profile_pictures/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileExtension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $fileName = 'admin_new_' . time() . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;
                
                // Check file type
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array(strtolower($fileExtension), $allowedTypes)) {
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filePath)) {
                        $image_url = $filePath;
                    }
                }
            }
            
            if ($image_url) {
                $sql = "INSERT INTO admin (full_name, email, phone, password, image_url) 
                        VALUES ('$full_name', '$email', '$phone', '$password', '$image_url')";
            } else {
                $sql = "INSERT INTO admin (full_name, email, phone, password) 
                        VALUES ('$full_name', '$email', '$phone', '$password')";
            }
            
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'Admin added successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        // Handle current user profile update
        elseif ($_POST['action'] === 'update_profile') {
            $user_id = $_SESSION['user_id'];
            $full_name = $conn->real_escape_string($_POST['full_name']);
            $email = $conn->real_escape_string($_POST['email']);
            $phone = $conn->real_escape_string($_POST['phone']);
            
            // Handle profile picture upload
            $image_url = '';
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
                $uploadDir = 'uploads/profile_pictures/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                if ($_SESSION['user_type'] === 'admin') {
                    $fileName = 'admin_' . $user_id . '_' . time() . '.' . pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                } else {
                    $fileName = 'user_' . $user_id . '_' . time() . '.' . pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                }
                $filePath = $uploadDir . $fileName;
                
                // Check file type
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                $fileExtension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                if (in_array($fileExtension, $allowedTypes)) {
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filePath)) {
                        $image_url = $filePath;
                    }
                }
            }
            
            $table = $_SESSION['user_type'] === 'admin' ? 'admin' : 'user';
            if ($image_url) {
                $sql = "UPDATE $table SET full_name='$full_name', email='$email', phone='$phone', image_url='$image_url' WHERE {$table}_id='$user_id'";
            } else {
                $sql = "UPDATE $table SET full_name='$full_name', email='$email', phone='$phone' WHERE {$table}_id='$user_id'";
            }
            
            if ($conn->query($sql)) {
                $_SESSION['full_name'] = $full_name;
                $response['success'] = true;
                $response['message'] = 'Profile updated successfully!';
                if ($image_url) {
                    $response['image_url'] = $image_url;
                    $_SESSION['profile_picture'] = $image_url;
                }
            } else {
                throw new Exception($conn->error);
            }
        }
        // Handle current user password change
        elseif ($_POST['action'] === 'change_password') {
            $user_id = $_SESSION['user_id'];
            $current_password = $conn->real_escape_string($_POST['current_password']);
            $new_password = $conn->real_escape_string($_POST['new_password']);
            
            // Validate password length
            if (strlen($new_password) < 6) {
                throw new Exception('New password must be at least 6 characters long');
            }
            
          $table = $isAdmin && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin' ? 'admin' : 'user';
          // Note: prefer admin table only if the session originally came from admin login
          $id_field = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') ? 'admin_id' : 'user_id';
            
            // Verify current password
            $result = $conn->query("SELECT password FROM $table WHERE $id_field = $user_id");
            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (!password_verify($current_password, $user['password'])) {
                    throw new Exception('Current password is incorrect');
                }
            } else {
                throw new Exception('User not found');
            }
            
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE $table SET password='$hashed_password' WHERE $id_field='$user_id'";
            
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'Password changed successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        // Handle member operations
        elseif ($_POST['action'] === 'add_member') {
            $company_id = (int)$_POST['company_id'];
            $member_name = $conn->real_escape_string($_POST['member_name']);
            $id_type = $conn->real_escape_string($_POST['id_type']);
            $identification_no = $conn->real_escape_string($_POST['identification_no']);
            $nationality = $conn->real_escape_string($_POST['nationality']);
            $address = $conn->real_escape_string($_POST['address']);
            $race = $conn->real_escape_string($_POST['race']);
            $price_per_share = (float)$_POST['price_per_share'];
            $class_of_share = $conn->real_escape_string($_POST['class_of_share']);
            $number_of_share = $conn->real_escape_string($_POST['number_of_share']);
            $email = isset($_POST['email']) ? $conn->real_escape_string($_POST['email']) : NULL;
            
            $sql = "INSERT INTO member (company_id, member_name, id_type, identification_no, nationality, address, race, price_per_share, class_of_share, number_of_share, email) 
                    VALUES ('$company_id', '$member_name', '$id_type', '$identification_no', '$nationality', '$address', '$race', '$price_per_share', '$class_of_share', '$number_of_share', " . ($email ? "'$email'" : "NULL") . ")";
            
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'Member added successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'update_member') {
            $member_id = (int)$_POST['member_id'];
            $member_name = $conn->real_escape_string($_POST['member_name']);
            $id_type = $conn->real_escape_string($_POST['id_type']);
            $identification_no = $conn->real_escape_string($_POST['identification_no']);
            $nationality = $conn->real_escape_string($_POST['nationality']);
            $address = $conn->real_escape_string($_POST['address']);
            $race = $conn->real_escape_string($_POST['race']);
            $price_per_share = (float)$_POST['price_per_share'];
            $class_of_share = $conn->real_escape_string($_POST['class_of_share']);
            $number_of_share = $conn->real_escape_string($_POST['number_of_share']);
            $email = isset($_POST['email']) ? $conn->real_escape_string($_POST['email']) : NULL;
            
            $sql = "UPDATE member SET member_name='$member_name', id_type='$id_type', identification_no='$identification_no', nationality='$nationality', address='$address', race='$race', price_per_share='$price_per_share', class_of_share='$class_of_share', number_of_share='$number_of_share', email=" . ($email ? "'$email'" : "NULL") . " 
                    WHERE member_id='$member_id'";
            
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'Member updated successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'delete_member') {
            $member_id = (int)$_POST['member_id'];
            $sql = "DELETE FROM member WHERE member_id='$member_id'";
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'Member deleted successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        // Handle director operations
        elseif ($_POST['action'] === 'add_director') {
            $company_id = (int)$_POST['company_id'];
            $director_name = $conn->real_escape_string($_POST['director_name']);
            $identification_no = $conn->real_escape_string($_POST['identification_no']);
            $nationality = $conn->real_escape_string($_POST['nationality']);
            $address = $conn->real_escape_string($_POST['address']);
            $date_of_birth = $conn->real_escape_string($_POST['date_of_birth']);
            $race = $conn->real_escape_string($_POST['race']);
            $email = $conn->real_escape_string($_POST['email']);
            
            $sql = "INSERT INTO director (company_id, director_name, identification_no, nationality, address, date_of_birth, race, email) 
                    VALUES ('$company_id', '$director_name', '$identification_no', '$nationality', '$address', '$date_of_birth', '$race', '$email')";
            
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'Director added successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'update_director') {
            $director_id = (int)$_POST['director_id'];
            $director_name = $conn->real_escape_string($_POST['director_name']);
            $identification_no = $conn->real_escape_string($_POST['identification_no']);
            $nationality = $conn->real_escape_string($_POST['nationality']);
            $address = $conn->real_escape_string($_POST['address']);
            $date_of_birth = $conn->real_escape_string($_POST['date_of_birth']);
            $race = $conn->real_escape_string($_POST['race']);
            $email = $conn->real_escape_string($_POST['email']);
            
            $sql = "UPDATE director SET director_name='$director_name', identification_no='$identification_no', nationality='$nationality', address='$address', date_of_birth='$date_of_birth', race='$race', email='$email' 
                    WHERE director_id='$director_id'";
            
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'Director updated successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'delete_director') {
            $director_id = (int)$_POST['director_id'];
            $sql = "DELETE FROM director WHERE director_id='$director_id'";
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'Director deleted successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        // Handle company deletion
        elseif ($_POST['action'] === 'delete_company') {
            $company_id = (int)$_POST['company_id'];
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Delete members
                $conn->query("DELETE FROM member WHERE company_id = $company_id");
                
                // Delete directors
                $conn->query("DELETE FROM director WHERE company_id = $company_id");
                
                // Delete company
                $conn->query("DELETE FROM company WHERE company_id = $company_id");
                
                // Commit transaction
                $conn->commit();
                
                $response['success'] = true;
                $response['message'] = 'Company and all associated data deleted successfully!';
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                throw $e;
            }
        }
        // Handle company update - ENHANCED WITH PREPARED STATEMENTS
        elseif ($_POST['action'] === 'update_company') {
            $company_id = (int)$_POST['company_id'];
            $company_name = $conn->real_escape_string($_POST['company_name']);
            $ssm_no = $conn->real_escape_string($_POST['ssm_no']);
            $company_type = $conn->real_escape_string($_POST['company_type']);
            $sub_type = $conn->real_escape_string($_POST['sub_type']);
            $incorporation_date = $conn->real_escape_string($_POST['incorporation_date']);
            $financial_year_end = $conn->real_escape_string($_POST['financial_year_end']);
            $subsequent_year_end = $conn->real_escape_string($_POST['subsequent_year_end']);
            $nature_of_business = $conn->real_escape_string($_POST['nature_of_business']);
            
            // Get MSIC codes and combine them (comma-separated)
            $msic_codes = [];
            
            if (!empty($_POST['msic_code_1'])) {
                $msic_codes[] = $conn->real_escape_string($_POST['msic_code_1']);
            }
            if (!empty($_POST['msic_code_2'])) {
                $msic_codes[] = $conn->real_escape_string($_POST['msic_code_2']);
            }
            if (!empty($_POST['msic_code_3'])) {
                $msic_codes[] = $conn->real_escape_string($_POST['msic_code_3']);
            }
            
            // Combine codes (comma-separated)
            $msic_code = implode(', ', $msic_codes);
            
            $description = isset($_POST['description']) ? $conn->real_escape_string($_POST['description']) : '';
            $address = $conn->real_escape_string($_POST['address']);
            $business_address = isset($_POST['business_address']) ? $conn->real_escape_string($_POST['business_address']) : NULL;
            $email = $conn->real_escape_string($_POST['email']);
            $office_no = $conn->real_escape_string($_POST['office_no']);
            $fax_no = $conn->real_escape_string($_POST['fax_no']);
            $accountant_name = $conn->real_escape_string($_POST['accountant_name']);
            $accountant_phone = $conn->real_escape_string($_POST['accountant_phone']);
            $accountant_email = $conn->real_escape_string($_POST['accountant_email']);
            $hr_name = $conn->real_escape_string($_POST['hr_name']);
            $hr_phone = $conn->real_escape_string($_POST['hr_phone']);
            $hr_email = $conn->real_escape_string($_POST['hr_email']);
            
            // Debug logging
            error_log("Updating company with sub_type: " . $sub_type);
            
            // Use prepared statement for better security
            $stmt = $conn->prepare("UPDATE company SET 
                company_name = ?,
                ssm_no = ?,
                company_type = ?,
                sub_type = ?,
                incorporation_date = ?,
                financial_year_end = ?,
                subsequent_year_end = ?,
                nature_of_business = ?,
                msic_code = ?,
                description = ?,
                address = ?,
                business_address = ?,
                email = ?,
                office_no = ?,
                fax_no = ?,
                accountant_name = ?,
                accountant_phone = ?,
                accountant_email = ?,
                hr_name = ?,
                hr_phone = ?,
                hr_email = ?
                WHERE company_id = ?");
            
            if ($stmt) {
                $stmt->bind_param("sssssssssssssssssssssi", 
                    $company_name, $ssm_no, $company_type, $sub_type, $incorporation_date,
                    $financial_year_end, $subsequent_year_end, $nature_of_business, $msic_code, $description, $address, $business_address,
                    $email, $office_no, $fax_no, $accountant_name, $accountant_phone, $accountant_email,
                    $hr_name, $hr_phone, $hr_email, $company_id);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Company updated successfully!';
                    error_log("Company updated successfully with sub_type: " . $sub_type);
                } else {
                    throw new Exception($stmt->error);
                }
                $stmt->close();
            } else {
                throw new Exception($conn->error);
            }
        }
        // Handle document operations
        elseif ($_POST['action'] === 'add_document') {
            $document_title = $conn->real_escape_string($_POST['document_title']);
            $document_type = $conn->real_escape_string($_POST['document_type']);
            $source_type = $conn->real_escape_string($_POST['source_type']);
            $description = $conn->real_escape_string($_POST['description']);
            $company_id = (int)$_POST['company_id'];
            $location = $conn->real_escape_string($_POST['location']);
            $date_of_collect = $conn->real_escape_string($_POST['date_of_collect']);
            $created_by = $_SESSION['user_id'];
            
            // Handle multiple file uploads
            $uploadDir = 'uploads/documents/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $uploadedFiles = [];
            $categories = isset($_POST['doc_categories']) ? $_POST['doc_categories'] : [];
            
            if (isset($_FILES['doc_files']) && is_array($_FILES['doc_files']['name'])) {
                foreach ($_FILES['doc_files']['name'] as $key => $fileName) {
                    if ($_FILES['doc_files']['error'][$key] === 0) {
                        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                        $newFileName = 'doc_' . time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
                        $filePath = $uploadDir . $newFileName;
                        
                        if (move_uploaded_file($_FILES['doc_files']['tmp_name'][$key], $filePath)) {
                            // Get the category for this file
                            $categoryIndex = array_search($key, array_keys($_FILES['doc_files']['name']));
                            $category = isset($categories[$categoryIndex]) ? $categories[$categoryIndex] : 'Unknown';
                            
                            $uploadedFiles[] = [
                                'file_name' => $newFileName,
                                'category' => $category
                            ];
                        }
                    }
                }
            }
            
            if (empty($uploadedFiles)) {
                throw new Exception('At least one file upload is required');
            }
            
            // Auto-assign to first available accountant
            $accountantResult = $conn->query("SELECT user_id FROM user WHERE role = 'accountant' ORDER BY user_id ASC LIMIT 1");
            $current_handler = 'NULL';
            if ($accountantResult && $accountantResult->num_rows > 0) {
                $accountant = $accountantResult->fetch_assoc();
                $current_handler = $accountant['user_id'];
            }
            
            // Use first file as main file_name for backward compatibility
            $main_file = $uploadedFiles[0]['file_name'];
            
            $sql = "INSERT INTO document (document_title, document_type, source_type, description, file_name, company_id, location, date_of_collect, created_by, current_handler, status) 
                    VALUES ('$document_title', '$document_type', '$source_type', '$description', '$main_file', '$company_id', '$location', '$date_of_collect', '$created_by', $current_handler, 'Pending')";
            
            if ($conn->query($sql)) {
                $document_id = $conn->insert_id;
                
                // Insert all uploaded files into document_files table
                $file_stmt = $conn->prepare("INSERT INTO document_files (document_id, category, file_name) VALUES (?, ?, ?)");
                foreach ($uploadedFiles as $file) {
                    $file_stmt->bind_param("iss", $document_id, $file['category'], $file['file_name']);
                    $file_stmt->execute();
                }
                $file_stmt->close();
                
                // Log to history using prepared statement
                $history_stmt = $conn->prepare("INSERT INTO document_history (document_id, action, old_status, new_status, performed_by, comments) 
                                                VALUES (?, ?, NULL, ?, ?, ?)");
                if ($history_stmt) {
                    $action = 'Submitted';
                    $status = 'Pending';
                    $comment = 'Document uploaded and submitted to accountant for review';
                    $history_stmt->bind_param("issis", $document_id, $action, $status, $created_by, $comment);
                    $history_stmt->execute();
                    $history_stmt->close();
                }
                
                // Create notification for next handlers
                notifyNextHandler($conn, $document_id, 'Pending', $created_by);
                
                $response['success'] = true;
                $response['message'] = 'Document with ' . count($uploadedFiles) . ' file(s) submitted successfully to accountant for review!';
                $response['document_id'] = $document_id;
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'update_document') {
            $document_id = (int)$_POST['document_id'];
            $document_title = $conn->real_escape_string($_POST['document_title']);
            $document_type = $conn->real_escape_string($_POST['document_type']);
            $source_type = $conn->real_escape_string($_POST['source_type']);
            $description = $conn->real_escape_string($_POST['description']);
            $company_id = (int)$_POST['company_id'];
            $location = $conn->real_escape_string($_POST['location']);
            $date_of_collect = $conn->real_escape_string($_POST['date_of_collect']);
            
            // Handle multiple file uploads if new files provided
            $uploadDir = 'uploads/documents/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $uploadedFiles = [];
            $categories = isset($_POST['doc_categories']) ? $_POST['doc_categories'] : [];
            $file_update = '';
            
            if (isset($_FILES['doc_files']) && is_array($_FILES['doc_files']['name'])) {
                foreach ($_FILES['doc_files']['name'] as $key => $fileName) {
                    if ($_FILES['doc_files']['error'][$key] === 0) {
                        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                        $newFileName = 'doc_' . time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
                        $filePath = $uploadDir . $newFileName;
                        
                        if (move_uploaded_file($_FILES['doc_files']['tmp_name'][$key], $filePath)) {
                            // Get the category for this file
                            $categoryIndex = array_search($key, array_keys($_FILES['doc_files']['name']));
                            $category = isset($categories[$categoryIndex]) ? $categories[$categoryIndex] : 'Unknown';
                            
                            $uploadedFiles[] = [
                                'file_name' => $newFileName,
                                'category' => $category
                            ];
                        }
                    }
                }
                
                // If new files uploaded, update main file_name and add to document_files
                if (!empty($uploadedFiles)) {
                    $main_file = $uploadedFiles[0]['file_name'];
                    $file_update = ", file_name='$main_file'";
                    
                    // Delete old files from document_files table
                    $conn->query("DELETE FROM document_files WHERE document_id = $document_id");
                    
                    // Insert new files
                    $file_stmt = $conn->prepare("INSERT INTO document_files (document_id, category, file_name) VALUES (?, ?, ?)");
                    foreach ($uploadedFiles as $file) {
                        $file_stmt->bind_param("iss", $document_id, $file['category'], $file['file_name']);
                        $file_stmt->execute();
                    }
                    $file_stmt->close();
                }
            }
            
            $sql = "UPDATE document SET document_title='$document_title', document_type='$document_type', source_type='$source_type', description='$description', company_id='$company_id', location='$location', date_of_collect='$date_of_collect'$file_update WHERE document_id='$document_id'";
            
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'Document updated successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'delete_document') {
            $document_id = (int)$_POST['document_id'];
            
            // Check permission: Only admin or current handler can delete
            $checkResult = $conn->query("SELECT current_handler FROM document WHERE document_id = $document_id");
            if ($checkResult && $checkResult->num_rows > 0) {
                $docData = $checkResult->fetch_assoc();
                $currentHandler = $docData['current_handler'];
                
                // Verify user has permission to delete
                if (!$isAdmin && $currentHandler != $_SESSION['user_id']) {
                    throw new Exception('You do not have permission to delete this document. Only admin or the current handler can delete.');
                }
            }
            
            // Get all files to delete them
            $filesResult = $conn->query("SELECT file_name FROM document_files WHERE document_id = $document_id");
            if ($filesResult) {
                while ($file = $filesResult->fetch_assoc()) {
                    $filePath = 'uploads/documents/' . $file['file_name'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }
            
            // Also delete main file if exists
            $result = $conn->query("SELECT file_name FROM document WHERE document_id = $document_id");
            if ($result && $result->num_rows > 0) {
                $doc = $result->fetch_assoc();
                $filePath = 'uploads/documents/' . $doc['file_name'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Delete from document_files table
            $conn->query("DELETE FROM document_files WHERE document_id='$document_id'");
            
            // Delete from document table
            $sql = "DELETE FROM document WHERE document_id='$document_id'";
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'Document and all associated files deleted successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'update_document_status') {
            $document_id = (int)$_POST['document_id'];
            $new_status = $conn->real_escape_string($_POST['status']);
            $comments = isset($_POST['comments']) ? $conn->real_escape_string($_POST['comments']) : '';
            $user_id = $_SESSION['user_id'];
            $user_role = $_SESSION['role'] ?? 'employee';
            
            // Get current document status
            $docResult = $conn->query("SELECT status, current_handler, created_by FROM document WHERE document_id = $document_id");
            if (!$docResult || $docResult->num_rows == 0) {
                throw new Exception('Document not found');
            }
            $doc = $docResult->fetch_assoc();
            $old_status = $doc['status'];
            $current_handler = 'NULL';
            $tracking_field = '';
            $tracking_value = 'NULL';
            
            // Workflow logic based on role and status
            // New workflow: Employee → Accountant → Manager → Auditor
            if ($new_status === 'Reviewed') {
                // Accountant reviews first (Pending → Reviewed)
                if ($user_role !== 'accountant' && !$isAdmin) {
                    throw new Exception('Only accountants can review documents');
                }
                
                // Validate supplier document details before approval
                $docCheckResult = $conn->query("SELECT source_type FROM document WHERE document_id = $document_id");
                if ($docCheckResult && $docCheckResult->num_rows > 0) {
                    $docInfo = $docCheckResult->fetch_assoc();
                    if ($docInfo['source_type'] === 'Supplier') {
                        // Check if all files have details entered
                        $filesResult = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN supplier_name IS NOT NULL THEN 1 ELSE 0 END) as with_details FROM document_files WHERE document_id = $document_id");
                        if ($filesResult) {
                            $fileStats = $filesResult->fetch_assoc();
                            if ($fileStats['total'] > 0 && $fileStats['with_details'] < $fileStats['total']) {
                                throw new Exception('Cannot approve! Please enter details for all ' . $fileStats['total'] . ' files. Currently ' . $fileStats['with_details'] . ' of ' . $fileStats['total'] . ' files have details.');
                            }
                        }
                        
                        // Validate consistency across all files
                        $allFilesResult = $conn->query("SELECT category, supplier_name, supplier_pi_no, invoice_date, amount, tax_amount, total_amount FROM document_files WHERE document_id = $document_id ORDER BY file_id ASC");
                        if ($allFilesResult && $allFilesResult->num_rows > 0) {
                            $firstFile = $allFilesResult->fetch_assoc();
                            $expectedSupplier = $firstFile['supplier_name'];
                            $expectedPiNo = $firstFile['supplier_pi_no'];
                            $expectedDate = $firstFile['invoice_date'];
                            $expectedAmount = floatval($firstFile['amount']);
                            $expectedTaxRate = floatval($firstFile['tax_amount']);
                            $errors = [];
                            
                            // Validate first file calculation
                            $expectedTaxAmount = $expectedAmount * ($expectedTaxRate / 100);
                            $actualTaxAmount = floatval($firstFile['total_amount']);
                            if (abs($actualTaxAmount - $expectedTaxAmount) > 0.01) {
                                $errors[] = $firstFile['category'] . ': Tax amount calculation error';
                            }
                            
                            // Check remaining files
                            while ($file = $allFilesResult->fetch_assoc()) {
                                if ($file['supplier_name'] !== $expectedSupplier) {
                                    $errors[] = $file['category'] . ': Supplier name mismatch';
                                }
                                if ($file['supplier_pi_no'] !== $expectedPiNo) {
                                    $errors[] = $file['category'] . ': PI No mismatch';
                                }
                                if ($file['invoice_date'] !== $expectedDate) {
                                    $errors[] = $file['category'] . ': Invoice date mismatch';
                                }
                                
                                // Validate amount consistency
                                $fileAmount = floatval($file['amount']);
                                if (abs($fileAmount - $expectedAmount) > 0.01) {
                                    $errors[] = $file['category'] . ': Amount mismatch';
                                }
                                
                                // Validate tax rate consistency
                                $fileTaxRate = floatval($file['tax_amount']);
                                if (abs($fileTaxRate - $expectedTaxRate) > 0.01) {
                                    $errors[] = $file['category'] . ': Tax rate mismatch';
                                }
                                
                                // Validate tax amount calculation
                                $calcTaxAmount = $fileAmount * ($fileTaxRate / 100);
                                $fileTotalAmount = floatval($file['total_amount']);
                                if (abs($fileTotalAmount - $calcTaxAmount) > 0.01) {
                                    $errors[] = $file['category'] . ': Tax amount calculation error';
                                }
                            }
                            
                            if (count($errors) > 0) {
                                throw new Exception('Cannot approve! Information is inconsistent across files: ' . implode(', ', $errors) . '. Please ensure all details match across all files.');
                            }
                        }
                    }
                }
                
                // Assign to first available manager
                $managerResult = $conn->query("SELECT user_id FROM user WHERE role = 'manager' ORDER BY user_id ASC LIMIT 1");
                if ($managerResult && $managerResult->num_rows > 0) {
                    $manager = $managerResult->fetch_assoc();
                    $current_handler = $manager['user_id'];
                }
                $tracking_field = 'reviewed_by';
                $tracking_value = $user_id;
                
            } elseif ($new_status === 'Approved') {
                // Manager approves (Reviewed → Approved)
                if ($user_role !== 'manager' && !$isAdmin) {
                    throw new Exception('Only managers can approve documents');
                }
                // Assign to first available auditor
                $auditorResult = $conn->query("SELECT user_id FROM user WHERE role = 'auditor' ORDER BY user_id ASC LIMIT 1");
                if ($auditorResult && $auditorResult->num_rows > 0) {
                    $auditor = $auditorResult->fetch_assoc();
                    $current_handler = $auditor['user_id'];
                }
                $tracking_field = 'approved_by';
                $tracking_value = $user_id;
                
            } elseif ($new_status === 'Final Approved') {
                // Auditor gives final approval (Approved → Final Approved)
                if ($user_role !== 'auditor' && !$isAdmin) {
                    throw new Exception('Only auditors can give final approval');
                }
                // Assign back to document creator for final submission
                $current_handler = $doc['created_by'];
                $tracking_field = 'audited_by';
                $tracking_value = $user_id;
                
            } elseif ($new_status === 'Submit') {
                // Document creator submits to client (Final Approved → Submit)
                if ($doc['created_by'] != $user_id && !$isAdmin) {
                    throw new Exception('Only the document creator can submit to client');
                }
                $current_handler = 'NULL'; // No more handlers needed
                $tracking_field = '';
                $tracking_value = 'NULL';
                
            } elseif ($new_status === 'Returned') {
                // Determine who to return to based on current status
                // New workflow: Employee → Accountant → Manager → Auditor
                if ($old_status === 'Pending') {
                    // Accountant returns to employee
                    $current_handler = $doc['created_by'];
                } elseif ($old_status === 'Reviewed') {
                    // Manager returns to accountant
                    $accountantResult = $conn->query("SELECT reviewed_by FROM document WHERE document_id = $document_id");
                    if ($accountantResult && $accountantResult->num_rows > 0) {
                        $acc = $accountantResult->fetch_assoc();
                        $current_handler = $acc['reviewed_by'] ?? 'NULL';
                    }
                } elseif ($old_status === 'Approved') {
                    // Auditor returns to manager
                    $managerResult = $conn->query("SELECT approved_by FROM document WHERE document_id = $document_id");
                    if ($managerResult && $managerResult->num_rows > 0) {
                        $mgr = $managerResult->fetch_assoc();
                        $current_handler = $mgr['approved_by'] ?? 'NULL';
                    }
                }
                $tracking_field = 'returned_by';
                $tracking_value = $user_id;
                
            } elseif ($new_status === 'Rejected') {
                $current_handler = 'NULL'; // No handler for rejected
                $tracking_field = 'rejected_by';
                $tracking_value = $user_id;
            }
            
            // Build update SQL
            $updateFields = "status='$new_status', current_handler=$current_handler";
            if (!empty($tracking_field)) {
                $updateFields .= ", $tracking_field=$tracking_value";
            }
            
            $sql = "UPDATE document SET $updateFields WHERE document_id='$document_id'";
            
            if ($conn->query($sql)) {
                // Log to history using prepared statement
                $action = ucfirst(str_replace('_', ' ', $new_status));
                $history_stmt = $conn->prepare("INSERT INTO document_history (document_id, action, old_status, new_status, performed_by, comments) 
                                                VALUES (?, ?, ?, ?, ?, ?)");
                
                if ($history_stmt) {
                    $history_stmt->bind_param("isssis", $document_id, $action, $old_status, $new_status, $user_id, $comments);
                    if (!$history_stmt->execute()) {
                        error_log("History insert failed: " . $history_stmt->error);
                    }
                    $history_stmt->close();
                } else {
                    error_log("History prepare failed: " . $conn->error);
                }
                
                // Create notification for next handlers
                notifyNextHandler($conn, $document_id, $new_status, $user_id);
                
                $response['success'] = true;
                $response['message'] = 'Document status updated successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'save_supplier_details') {
            $file_id = (int)$_POST['file_id'];
            $document_id = (int)$_POST['document_id'];
            $supplier_name = $conn->real_escape_string($_POST['supplier_name']);
            $supplier_pi_no = $conn->real_escape_string($_POST['supplier_pi_no']);
            $inventory = $conn->real_escape_string($_POST['inventory']);
            $invoice_date = $conn->real_escape_string($_POST['invoice_date']);
            $amount = (float)$_POST['amount'];
            $tax_amount = (float)$_POST['tax_amount'];
            $total_amount = (float)$_POST['total_amount'];
            $user_id = $_SESSION['user_id'];
            
            $sql = "UPDATE document_files SET 
                    supplier_name='$supplier_name',
                    supplier_pi_no='$supplier_pi_no',
                    inventory='$inventory',
                    invoice_date='$invoice_date',
                    amount='$amount',
                    tax_amount='$tax_amount',
                    total_amount='$total_amount',
                    details_entered_by='$user_id',
                    details_entered_at=NOW()
                    WHERE file_id='$file_id'";
            
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'Supplier details saved successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'save_customer_details') {
            $file_id = (int)$_POST['file_id'];
            $document_id = (int)$_POST['document_id'];
            $customer_name = $conn->real_escape_string($_POST['customer_name']);
            $invoice_number = $conn->real_escape_string($_POST['invoice_number']);
            $sales_date = $conn->real_escape_string($_POST['sales_date']);
            $amount = (float)$_POST['amount'];
            $user_id = $_SESSION['user_id'];
            
            $sql = "UPDATE document_files SET 
                    customer_name='$customer_name',
                    invoice_number='$invoice_number',
                    sales_date='$sales_date',
                    amount='$amount',
                    details_entered_by='$user_id',
                    details_entered_at=NOW()
                    WHERE file_id='$file_id'";
            
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'Customer details saved successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'save_bank_details') {
            $file_id = (int)$_POST['file_id'];
            $document_id = (int)$_POST['document_id'];
            $bank_name = $conn->real_escape_string($_POST['bank_name']);
            $account_number = $conn->real_escape_string($_POST['account_number']);
            $statement_period = $conn->real_escape_string($_POST['statement_period']);
            $total_debit = (float)$_POST['total_debit'];
            $total_credit = (float)$_POST['total_credit'];
            $balance = (float)$_POST['balance'];
            $user_id = $_SESSION['user_id'];
            
            $sql = "UPDATE document_files SET 
                    bank_name='$bank_name',
                    account_number='$account_number',
                    statement_period='$statement_period',
                    total_debit='$total_debit',
                    total_credit='$total_credit',
                    balance='$balance',
                    details_entered_by='$user_id',
                    details_entered_at=NOW()
                    WHERE file_id='$file_id'";
            
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'Bank statement details saved successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'save_government_details') {
            $file_id = (int)$_POST['file_id'];
            $document_id = (int)$_POST['document_id'];
            $agency_name = $conn->real_escape_string($_POST['agency_name']);
            $reference_no = $conn->real_escape_string($_POST['reference_no']);
            $period_covered = $conn->real_escape_string($_POST['period_covered']);
            $submission_date = $conn->real_escape_string($_POST['submission_date']);
            $amount_paid = (float)$_POST['amount_paid'];
            $acknowledgement_file = $conn->real_escape_string($_POST['acknowledgement_file']);
            $user_id = $_SESSION['user_id'];
            
            $sql = "UPDATE document_files SET 
                    agency_name='$agency_name',
                    reference_no='$reference_no',
                    period_covered='$period_covered',
                    submission_date='$submission_date',
                    amount_paid='$amount_paid',
                    acknowledgement_file='$acknowledgement_file',
                    details_entered_by='$user_id',
                    details_entered_at=NOW()
                    WHERE file_id='$file_id'";
            
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'Government submission details saved successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        elseif ($_POST['action'] === 'save_client_details') {
            $file_id = (int)$_POST['file_id'];
            $document_id = (int)$_POST['document_id'];
            $employee_name = $conn->real_escape_string($_POST['employee_name']);
            $department = $conn->real_escape_string($_POST['department']);
            $claim_date = $conn->real_escape_string($_POST['claim_date']);
            $amount = (float)$_POST['amount'];
            $approved_by = $conn->real_escape_string($_POST['approved_by']);
            $user_id = $_SESSION['user_id'];
            
            $sql = "UPDATE document_files SET 
                    employee_name='$employee_name',
                    department='$department',
                    claim_date='$claim_date',
                    amount='$amount',
                    approved_by='$approved_by',
                    details_entered_by='$user_id',
                    details_entered_at=NOW()
                    WHERE file_id='$file_id'";
            
            if ($conn->query($sql)) {
                $response['success'] = true;
                $response['message'] = 'Client/Employee details saved successfully!';
            } else {
                throw new Exception($conn->error);
            }
        }
        // Handle company creation - ENHANCED WITH VALIDATION
        elseif ($_POST['action'] === 'add_company') {
            // Get and sanitize form data
            $company_name = $conn->real_escape_string($_POST['company_name']);
            $ssm_no = $conn->real_escape_string($_POST['ssm_no']);
            $company_type = $conn->real_escape_string($_POST['company_type']);
            $sub_type = $conn->real_escape_string($_POST['sub_type']);
            $incorporation_date = $conn->real_escape_string($_POST['incorporation_date']);
            $financial_year_end = $conn->real_escape_string($_POST['financial_year_end']);
            $subsequent_year_end = $conn->real_escape_string($_POST['subsequent_year_end']);
            $nature_of_business = $conn->real_escape_string($_POST['nature_of_business']);
            
            // Get MSIC codes and combine them (comma-separated)
            $msic_codes = [];
            
            if (!empty($_POST['msic_code_1'])) {
                $msic_codes[] = $conn->real_escape_string($_POST['msic_code_1']);
            }
            if (!empty($_POST['msic_code_2'])) {
                $msic_codes[] = $conn->real_escape_string($_POST['msic_code_2']);
            }
            if (!empty($_POST['msic_code_3'])) {
                $msic_codes[] = $conn->real_escape_string($_POST['msic_code_3']);
            }
            
            // Combine codes (comma-separated)
            $msic_code = implode(', ', $msic_codes);
            
            $description = isset($_POST['description']) ? $conn->real_escape_string($_POST['description']) : '';
            $address = $conn->real_escape_string($_POST['address']);
            $business_address = isset($_POST['business_address']) ? $conn->real_escape_string($_POST['business_address']) : NULL;
            $email = $conn->real_escape_string($_POST['email']);
            $office_no = $conn->real_escape_string($_POST['office_no']);
            $fax_no = $conn->real_escape_string($_POST['fax_no']);
            $accountant_name = $conn->real_escape_string($_POST['accountant_name']);
            $accountant_phone = $conn->real_escape_string($_POST['accountant_phone']);
            $accountant_email = $conn->real_escape_string($_POST['accountant_email']);
            $hr_name = $conn->real_escape_string($_POST['hr_name']);
            $hr_phone = $conn->real_escape_string($_POST['hr_phone']);
            $hr_email = $conn->real_escape_string($_POST['hr_email']);
            
            // Server-side validation
            if (empty($company_name) || empty($ssm_no)) {
                throw new Exception('Company name and SSM number are required');
            }
            
            // Check if company with same SSM number already exists
            $check_sql = "SELECT company_id FROM company WHERE ssm_no = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $ssm_no);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                throw new Exception('A company with this SSM number already exists');
            }
            $check_stmt->close();
            
            // Debug logging
            error_log("Adding company: " . $company_name . " with sub_type: " . $sub_type);
            
            // Use prepared statement for better security
            $stmt = $conn->prepare("INSERT INTO company (company_name, ssm_no, company_type, sub_type, incorporation_date, financial_year_end, subsequent_year_end, nature_of_business, msic_code, description, address, business_address, email, office_no, fax_no, accountant_name, accountant_phone, accountant_email, hr_name, hr_phone, hr_email) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt) {
                $stmt->bind_param("sssssssssssssssssssss", 
                    $company_name, $ssm_no, $company_type, $sub_type, $incorporation_date,
                    $financial_year_end, $subsequent_year_end, $nature_of_business, $msic_code, $description, $address, $business_address,
                    $email, $office_no, $fax_no, $accountant_name, $accountant_phone, $accountant_email,
                    $hr_name, $hr_phone, $hr_email);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Company added successfully!';
                    $response['company_id'] = $conn->insert_id;
                    error_log("Company added successfully with ID: " . $conn->insert_id . " and sub_type: " . $sub_type);
                } else {
                    error_log("Company insertion failed: " . $stmt->error);
                    throw new Exception('Failed to add company to database: ' . $stmt->error);
                }
                $stmt->close();
            } else {
                error_log("Failed to prepare statement: " . $conn->error);
                throw new Exception('Database error: ' . $conn->error);
            }
        }
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
        error_log("Error in company operation: " . $e->getMessage());
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle notification AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_action'])) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $currentUserId = $_SESSION['user_id'];
        
        if ($_POST['notification_action'] === 'mark_shown') {
            // Mark notifications as shown (for popup dismissal)
            $notifIds = json_decode($_POST['notification_ids'], true);
            if (is_array($notifIds) && count($notifIds) > 0) {
                $ids = implode(',', array_map('intval', $notifIds));
                $sql = "UPDATE notifications SET is_shown = 1 WHERE notification_id IN ($ids) AND user_id = $currentUserId";
                if ($conn->query($sql)) {
                    $response['success'] = true;
                }
            }
        }
        elseif ($_POST['notification_action'] === 'mark_read') {
            // Mark notification as read
            $notifId = (int)$_POST['notification_id'];
            $sql = "UPDATE notifications SET is_read = 1 WHERE notification_id = $notifId AND user_id = $currentUserId";
            if ($conn->query($sql)) {
                $response['success'] = true;
            }
        }
        elseif ($_POST['notification_action'] === 'mark_all_read') {
            // Mark all notifications as read
            $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = $currentUserId";
            if ($conn->query($sql)) {
                $response['success'] = true;
            }
        }
        elseif ($_POST['notification_action'] === 'get_unread_count') {
            // Get unread count
            $result = $conn->query("SELECT COUNT(*) as total FROM notifications WHERE user_id = $currentUserId AND is_read = 0");
            if ($result) {
                $response['success'] = true;
                $response['count'] = $result->fetch_assoc()['total'];
            }
        }
        elseif ($_POST['notification_action'] === 'get_notifications') {
            // Get all notifications
            $query = "SELECT n.*, COALESCE(u.full_name, a.full_name) as action_by_name 
                      FROM notifications n
                      LEFT JOIN user u ON n.action_by = u.user_id
                      LEFT JOIN admin a ON n.action_by = a.admin_id
                      WHERE n.user_id = $currentUserId 
                      ORDER BY n.created_at DESC LIMIT 50";
            $result = $conn->query($query);
            if ($result) {
                $notifications = [];
                while ($row = $result->fetch_assoc()) {
                    $notifications[] = $row;
                }
                $response['success'] = true;
                $response['notifications'] = $notifications;
            }
        }
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle MSIC code search API
if (isset($_GET['action']) && $_GET['action'] === 'search_msic' && isset($_GET['query'])) {
    header('Content-Type: application/json');
    $query = strtolower(trim($_GET['query']));
    
    // Load MSIC codes from JSON file
    $json_file = __DIR__ . '/docs/MSICSubCategoryCodes.json';
    $results = [];
    
    if (file_exists($json_file)) {
        $msic_data = json_decode(file_get_contents($json_file), true);
        
        if ($msic_data && is_array($msic_data)) {
            foreach ($msic_data as $item) {
                $code = isset($item['Code']) ? $item['Code'] : '';
                $description = isset($item['Description']) ? $item['Description'] : '';
                
                // Skip "NOT APPLICABLE" entry
                if ($code === '00000') {
                    continue;
                }
                
                // Search in both code and description
                if (stripos($code, $query) !== false || stripos($description, $query) !== false) {
                    $results[] = [
                        'code' => $code,
                        'description' => $description
                    ];
                    
                    // Limit results to 20 for performance
                    if (count($results) >= 20) {
                        break;
                    }
                }
            }
        }
    }
    
    echo json_encode($results);
    exit;
}

// Handle view requests
if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['type'])) {
    $type = $_GET['type'];
    $data = [];
    
    try {
        if ($type === 'user' && isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT * FROM user WHERE user_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $data = $result->fetch_assoc();
            }
            $stmt->close();
        } elseif ($type === 'admin' && isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $data = $result->fetch_assoc();
            }
            $stmt->close();
        } elseif ($type === 'member' && isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT * FROM member WHERE member_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $data = $result->fetch_assoc();
            }
            $stmt->close();
        } elseif ($type === 'director' && isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT * FROM director WHERE director_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $data = $result->fetch_assoc();
            }
            $stmt->close();
        } elseif ($type === 'company' && isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT * FROM company WHERE company_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $data = $result->fetch_assoc();
            }
            $stmt->close();
        } elseif ($type === 'members' && isset($_GET['company_id'])) {
            $company_id = (int)$_GET['company_id'];
            $members = [];
            
            // Check if member table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'member'");
            if ($table_check->num_rows == 0) {
                throw new Exception('Member table does not exist');
            }
            
            $stmt = $conn->prepare("SELECT * FROM member WHERE company_id = ?");
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $conn->error);
            }
            
            $stmt->bind_param("i", $company_id);
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute query: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $members[] = $row;
            }
            $stmt->close();
            
            header('Content-Type: application/json');
            echo json_encode($members);
            exit;
        } elseif ($type === 'directors' && isset($_GET['company_id'])) {
            $company_id = (int)$_GET['company_id'];
            $directors = [];
            
            // Check if director table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'director'");
            if ($table_check->num_rows == 0) {
                throw new Exception('Director table does not exist');
            }
            
            $stmt = $conn->prepare("SELECT * FROM director WHERE company_id = ?");
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $conn->error);
            }
            
            $stmt->bind_param("i", $company_id);
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute query: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $directors[] = $row;
            }
            $stmt->close();
            
            header('Content-Type: application/json');
            echo json_encode($directors);
            exit;
        } elseif ($type === 'document' && isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT d.*, c.company_name, 
                                    COALESCE(u.full_name, a.full_name) as creator_name, 
                                    COALESCE(u.phone, a.phone) as creator_phone,
                                    h.full_name as handler_name, h.phone as handler_phone 
                                    FROM document d 
                                    LEFT JOIN company c ON d.company_id = c.company_id
                                    LEFT JOIN user u ON d.created_by = u.user_id
                                    LEFT JOIN admin a ON d.created_by = a.admin_id
                                    LEFT JOIN user h ON d.current_handler = h.user_id
                                    WHERE d.document_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $data = $result->fetch_assoc();
            }
            $stmt->close();
        } elseif ($type === 'document_history' && isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $history = [];
            $stmt = $conn->prepare("SELECT h.*, 
                                    COALESCE(u.full_name, a.full_name) as performer_name 
                                    FROM document_history h 
                                    LEFT JOIN user u ON h.performed_by = u.user_id
                                    LEFT JOIN admin a ON h.performed_by = a.admin_id
                                    WHERE h.document_id = ?
                                    ORDER BY h.created_at DESC");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $history[] = $row;
            }
            $stmt->close();
            
            header('Content-Type: application/json');
            echo json_encode($history);
            exit;
        } elseif ($type === 'document_files' && isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $files = [];
            $stmt = $conn->prepare("SELECT * FROM document_files WHERE document_id = ? ORDER BY file_id ASC");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $files[] = $row;
            }
            $stmt->close();
            
            header('Content-Type: application/json');
            echo json_encode($files);
            exit;
        } elseif ($type === 'supplier_details' && isset($_GET['file_id'])) {
            $file_id = (int)$_GET['file_id'];
            $stmt = $conn->prepare("SELECT * FROM document_files WHERE file_id = ?");
            $stmt->bind_param("i", $file_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();
            
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        } elseif ($type === 'customer_details' && isset($_GET['file_id'])) {
            $file_id = (int)$_GET['file_id'];
            $stmt = $conn->prepare("SELECT * FROM document_files WHERE file_id = ?");
            $stmt->bind_param("i", $file_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();
            
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        } elseif ($type === 'bank_details' && isset($_GET['file_id'])) {
            $file_id = (int)$_GET['file_id'];
            $stmt = $conn->prepare("SELECT * FROM document_files WHERE file_id = ?");
            $stmt->bind_param("i", $file_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();
            
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        } elseif ($type === 'government_details' && isset($_GET['file_id'])) {
            $file_id = (int)$_GET['file_id'];
            $stmt = $conn->prepare("SELECT * FROM document_files WHERE file_id = ?");
            $stmt->bind_param("i", $file_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();
            
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        } elseif ($type === 'client_details' && isset($_GET['file_id'])) {
            $file_id = (int)$_GET['file_id'];
            $stmt = $conn->prepare("SELECT * FROM document_files WHERE file_id = ?");
            $stmt->bind_param("i", $file_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();
            
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        } elseif ($type === 'user' && isset($_GET['id'])) {
            $user_id = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT user_id, username, email, role FROM user WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();
            
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        } else {
            throw new Exception('Invalid request parameters');
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
    
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Accounting Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- PDF Export Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
  /* [ALL THE CSS STYLES FROM YOUR ORIGINAL CODE REMAIN EXACTLY THE SAME] */
  /* ... Include all the CSS styles from your original code here ... */
  
  body {
    background-color: #0f1b33;
    color: #e0e0e0;
    font-family: 'Segoe UI', sans-serif;
  }
  .navbar {
    background: linear-gradient(90deg, #004f99, #0072ff);
    padding: 0.8rem 2rem;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .navbar-brand { 
    font-weight: bold; 
    color: #fff !important; 
  }
  .navbar-user-info {
    display: flex;
    align-items: center;
    gap: 15px;
  }
  .user-display-name {
    color: white;
    font-weight: 500;
  }
  .user-role-badge {
    font-size: 0.8rem;
  }
  .logout-btn {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 6px 15px;
    border-radius: 20px;
    transition: all 0.3s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 5px;
  }
  .logout-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    color: white;
    transform: translateY(-2px);
  }
  
  /* NOTIFICATION BELL */
  .notification-bell-container {
    position: relative;
  }
  .notification-bell-btn {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
  }
  .notification-bell-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.1);
  }
  .notification-bell-btn i {
    font-size: 1.2rem;
  }
  .notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ff3b3b;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: bold;
    border: 2px solid #0072ff;
    animation: pulse 2s infinite;
  }
  @keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
  }
  
  /* NOTIFICATION POPUP */
  .notification-popup {
    position: fixed;
    top: 80px;
    right: 20px;
    width: 400px;
    max-width: 90vw;
    background: linear-gradient(135deg, #1b2a6a, #004f99);
    border: 2px solid #00c6ff;
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(0, 198, 255, 0.4);
    z-index: 9999;
    animation: slideInRight 0.4s ease-out;
    overflow: hidden;
  }
  @keyframes slideInRight {
    from {
      transform: translateX(450px);
      opacity: 0;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }
  @keyframes slideOutRight {
    from {
      transform: translateX(0);
      opacity: 1;
    }
    to {
      transform: translateX(450px);
      opacity: 0;
    }
  }
  .notification-popup.closing {
    animation: slideOutRight 0.3s ease-in forwards;
  }
  .notification-popup-header {
    background: linear-gradient(90deg, #0072ff, #00c6ff);
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid #00c6ff;
  }
  .notification-popup-header h5 {
    margin: 0;
    color: white;
    font-size: 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .notification-popup-close {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .notification-popup-close:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
  }
  .notification-popup-body {
    padding: 15px;
    max-height: 400px;
    overflow-y: auto;
  }
  .notification-popup-item {
    background: rgba(15, 27, 51, 0.6);
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 10px;
    border-left: 3px solid #0072ff;
    cursor: pointer;
    transition: all 0.2s ease;
    animation: fadeIn 0.3s ease-out;
  }
  .notification-popup-item.fye-notification {
    background: linear-gradient(135deg, rgba(255, 152, 0, 0.15), rgba(255, 193, 7, 0.1));
    border-left: 4px solid #ff9800;
    box-shadow: 0 2px 10px rgba(255, 152, 0, 0.3);
  }
  .notification-popup-item.fye-notification:hover {
    background: linear-gradient(135deg, rgba(255, 152, 0, 0.25), rgba(255, 193, 7, 0.2));
    border-left-color: #ffc107;
    transform: translateX(5px);
  }
  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(-10px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
  .notification-popup-item:hover {
    background: rgba(0, 114, 255, 0.2);
    border-left-color: #00c6ff;
    transform: translateX(5px);
  }
  .notification-popup-item:last-child {
    margin-bottom: 0;
  }
  .notification-popup-item-title {
    color: #00c6ff;
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 0.95rem;
  }
  .notification-popup-item-message {
    color: #e0e0e0;
    font-size: 0.85rem;
    margin-bottom: 5px;
  }
  .notification-popup-item-time {
    color: #aad4ff;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 5px;
  }
  .fye-notification .notification-popup-item-message {
    color: #fff;
    font-weight: 500;
  }
  .fye-notification-icon {
    display: inline-block;
    margin-right: 8px;
    color: #ff9800;
    font-size: 1.1rem;
    animation: pulse 2s infinite;
  }
  @keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.1); }
  }
  
  /* FYE Row Highlighting */
  .company-row.fye-urgent {
    background: linear-gradient(90deg, rgba(220, 53, 69, 0.15), rgba(220, 53, 69, 0.05)) !important;
    border-left: 4px solid #dc3545;
  }
  .company-row.fye-warning {
    background: linear-gradient(90deg, rgba(255, 193, 7, 0.15), rgba(255, 193, 7, 0.05)) !important;
    border-left: 4px solid #ffc107;
  }
  .company-row.fye-info {
    background: linear-gradient(90deg, rgba(13, 202, 240, 0.15), rgba(13, 202, 240, 0.05)) !important;
    border-left: 4px solid #0dcaf0;
  }
  .company-row.fye-urgent:hover,
  .company-row.fye-warning:hover,
  .company-row.fye-info:hover {
    transform: translateX(5px);
    box-shadow: 0 2px 10px rgba(0, 114, 255, 0.3);
  }
  
  .sidebar {
    width: 240px; 
    height: calc(100vh - 64px); 
    position: fixed; 
    top: 64px; 
    left: 0;
    background: #00264d; 
    border-right: 2px solid #0072ff;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }
  .profile-section {
    background: linear-gradient(135deg, #004f99, #0072ff);
    padding: 20px 15px;
    text-align: center;
    border-bottom: 2px solid #00c6ff;
    position: relative;
    overflow: hidden;
  }
  .profile-section::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
    z-index: 0;
  }
  .sidebar-links {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 20px 0;
    scrollbar-width: thin;
    scrollbar-color: #0072ff #001a33;
  }
  
  .sidebar-links::-webkit-scrollbar {
    width: 8px;
  }
  .sidebar-links::-webkit-scrollbar-track {
    background: #001a33;
    border-radius: 4px;
  }
  .sidebar-links::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #0072ff, #00c6ff);
    border-radius: 4px;
    border: 1px solid #001a33;
  }
  .sidebar-links::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #00c6ff, #0072ff);
  }
  
  /* Sidebar section headers */
  .sidebar h5, .sidebar h6 {
    color: #00c6ff;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 15px 20px 8px 20px;
    margin: 0;
    border-bottom: 1px solid rgba(0, 114, 255, 0.2);
  }
  .sidebar h5 {
    font-size: 0.85rem;
    color: #fff;
    background: rgba(0, 114, 255, 0.1);
    margin-top: 10px;
  }
  
  /* Sidebar section wrapper */
  .sidebar-section-wrapper {
    position: relative;
    margin: 8px 0;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
  }
  
  /* Collapsible sidebar section headers */
  .sidebar-section-header {
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px !important;
    transition: all 0.3s ease;
    user-select: none;
    position: relative;
    border-left: 3px solid transparent;
    background: linear-gradient(90deg, transparent, rgba(0, 114, 255, 0.05));
  }
  .sidebar-section-header::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 0;
    background: linear-gradient(90deg, rgba(0, 198, 255, 0.3), transparent);
    transition: width 0.4s ease;
  }
  .sidebar-section-header:hover {
    background: linear-gradient(90deg, rgba(0, 114, 255, 0.15), rgba(0, 198, 255, 0.08));
    color: #fff !important;
    border-left-color: #00c6ff;
    transform: translateX(2px);
  }
  .sidebar-section-header:hover::before {
    width: 100%;
  }
  .sidebar-section-wrapper.active {
    background: linear-gradient(135deg, rgba(0, 114, 255, 0.08), rgba(0, 198, 255, 0.05));
    box-shadow: 0 2px 8px rgba(0, 198, 255, 0.2);
  }
  .sidebar-section-header span {
    display: flex;
    align-items: center;
    font-weight: 600;
    letter-spacing: 0.5px;
  }
  .sidebar-section-header span i {
    font-size: 1.1rem;
    filter: drop-shadow(0 0 4px rgba(0, 198, 255, 0.5));
  }
  .sidebar-section-header .toggle-icon {
    font-size: 0.75rem;
    transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    color: #00c6ff;
    filter: drop-shadow(0 0 3px rgba(0, 198, 255, 0.6));
  }
  .sidebar-section-wrapper.active .sidebar-section-header .toggle-icon {
    transform: rotate(0deg) scale(1.2);
    color: #fff;
  }
  .sidebar-section-wrapper:not(.active) .sidebar-section-header .toggle-icon {
    transform: rotate(-90deg) scale(1);
  }
  
  /* Collapsible section content */
  .sidebar-section-content {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.4s ease;
    opacity: 0;
    background: linear-gradient(180deg, rgba(0, 26, 51, 0.5), rgba(0, 26, 51, 0.3));
    border-left: 3px solid rgba(0, 198, 255, 0.3);
    margin-left: 10px;
  }
  .sidebar-section-wrapper.active .sidebar-section-content {
    max-height: 500px;
    opacity: 1;
  }
  .sidebar-section-content a {
    padding-left: 50px !important;
    position: relative;
    border-left: 2px solid transparent;
    transition: all 0.3s ease;
  }
  .sidebar-section-content a::before {
    content: '';
    position: absolute;
    left: 30px;
    top: 50%;
    transform: translateY(-50%);
    width: 6px;
    height: 6px;
    background: #0072ff;
    border-radius: 50%;
    transition: all 0.3s ease;
    box-shadow: 0 0 6px rgba(0, 114, 255, 0.6);
  }
  .sidebar-section-content a:hover::before {
    background: #00c6ff;
    transform: translateY(-50%) scale(1.5);
    box-shadow: 0 0 10px rgba(0, 198, 255, 0.8);
  }
  .sidebar-section-content a:hover {
    background: linear-gradient(90deg, rgba(0, 198, 255, 0.15), rgba(0, 114, 255, 0.1));
    border-left-color: #00c6ff;
    padding-left: 55px !important;
  }
  
  /* Sidebar links */
  .sidebar a { 
    color: #b8d4ff; 
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px; 
    text-decoration: none; 
    border-radius: 0;
    margin: 0;
    transition: all 0.3s ease;
    position: relative;
    font-size: 0.9rem;
    border-left: 3px solid transparent;
  }
  .sidebar a i {
    width: 20px;
    text-align: center;
    font-size: 1rem;
    color: #0072ff;
    transition: all 0.3s ease;
  }
  .sidebar a:hover { 
    background: rgba(0, 114, 255, 0.15);
    color: #fff; 
    border-left-color: #00c6ff;
    padding-left: 25px;
  }
  .sidebar a:hover i {
    color: #00c6ff;
    transform: scale(1.1);
  }
  .sidebar a:active {
    background: rgba(0, 114, 255, 0.25);
  }
  .content { margin-left: 240px; padding: 25px; padding-top: 100px; min-height: 100vh; }
  .card-dashboard { 
    border-radius: 12px; 
    background: linear-gradient(135deg, #1b2a6a, #004f99); 
    color: #fff; 
    text-align: center; 
    padding: 25px 20px; 
    box-shadow: 0 4px 15px rgba(0, 114, 255, 0.3);
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
  }
  .card-dashboard::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: left 0.5s ease;
  }
  .card-dashboard:hover::before {
    left: 100%;
  }
  .card-dashboard:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 198, 255, 0.4);
  }
  .card-dashboard i {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: #00c6ff;
  }
  .card-dashboard h5 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  .card-dashboard p {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
  }
  .card-dashboard .card-label {
    font-size: 0.85rem;
    opacity: 0.8;
    margin-top: 5px;
  }
  .table-container { background: #10294d; border-radius: 12px; padding: 15px; }
  .table thead { background: linear-gradient(90deg, #0072ff, #00c6ff); color: #fff; }
  .form-control { background-color: #0f1b33; border: 1px solid #0072ff; color: #fff; border-radius: 8px; }
  .highlight { background-color: #ff4ec7; color: #fff; padding: 2px 4px; border-radius: 4px; }
  .hidden { display: none; }
  
  /* Clickable query rows */
  #queriesTable tbody tr[onclick] {
    transition: all 0.3s ease;
  }
  #queriesTable tbody tr[onclick]:hover {
    background-color: rgba(13, 110, 253, 0.2) !important;
    transform: translateX(5px);
    box-shadow: 0 2px 8px rgba(13, 110, 253, 0.3);
  }

  /* DASHBOARD ENHANCEMENTS */
  .dashboard-section {
    background: linear-gradient(135deg, rgba(27, 42, 77, 0.95), rgba(0, 79, 153, 0.3));
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid rgba(0, 198, 255, 0.2);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
  }
  .dashboard-section h4 {
    color: #00c6ff;
    margin-bottom: 15px;
    font-size: 1.2rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .dashboard-section h4 i {
    color: #0072ff;
  }
  .quick-action-btn {
    background: linear-gradient(135deg, #0072ff, #00c6ff);
    border: none;
    color: #fff;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 10px rgba(0, 114, 255, 0.3);
  }
  .quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0, 198, 255, 0.5);
    background: linear-gradient(135deg, #0088ff, #00d4ff);
  }
  .quick-action-btn i {
    font-size: 1.2rem;
  }
  .recent-activity-item {
    background: rgba(15, 27, 51, 0.4);
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 10px;
    border-left: 3px solid #0072ff;
    transition: all 0.2s ease;
  }
  .recent-activity-item:hover {
    background: rgba(0, 114, 255, 0.1);
    border-left-color: #00c6ff;
    transform: translateX(5px);
  }
  .recent-activity-item:last-child {
    margin-bottom: 0;
  }
  .activity-title {
    color: #00c6ff;
    font-weight: 600;
    margin-bottom: 5px;
  }
  .activity-meta {
    color: #aad4ff;
    font-size: 0.85rem;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
  }
  .activity-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
  }
  .status-breakdown {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
  }
  .status-card {
    background: rgba(15, 27, 51, 0.4);
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    border: 1px solid rgba(0, 114, 255, 0.2);
    transition: all 0.3s ease;
  }
  .status-card:hover {
    border-color: rgba(0, 198, 255, 0.5);
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 114, 255, 0.3);
  }
  .status-card-number {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 5px;
  }
  .status-card-label {
    font-size: 0.9rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .status-pending { color: #ffc107; }
  .status-reviewed { color: #17a2b8; }
  .status-approved { color: #28a745; }
  .status-rejected { color: #dc3545; }

  /* DETAILS CARDS */
  .card-detail {
    background: linear-gradient(135deg, rgba(27, 42, 77, 0.95), rgba(0, 79, 153, 0.3));
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid rgba(0, 198, 255, 0.2);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    transition: all 0.3s ease;
  }
  .card-detail:hover {
    border-color: rgba(0, 198, 255, 0.4);
    box-shadow: 0 6px 20px rgba(0, 114, 255, 0.2);
    transform: translateY(-2px);
  }
  .card-detail h4 {
    color: #00c6ff;
    margin-bottom: 18px;
    font-size: 1.15rem;
    font-weight: 600;
    padding-bottom: 12px;
    border-bottom: 2px solid rgba(0, 198, 255, 0.3);
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .card-detail h4 i {
    font-size: 1.3rem;
    color: #0072ff;
  }
  .detail-item { 
    margin-bottom: 12px;
    padding: 8px 12px;
    background: rgba(15, 27, 51, 0.4);
    border-radius: 6px;
    display: flex;
    align-items: flex-start;
    transition: background 0.2s ease;
  }
  .detail-item:hover {
    background: rgba(0, 114, 255, 0.1);
  }
  .detail-item:last-child {
    margin-bottom: 0;
  }
  .detail-item strong { 
    color: #aad4ff;
    min-width: 140px;
    font-weight: 600;
    font-size: 0.95rem;
  }
  .detail-item span {
    color: #e0e0e0;
    flex: 1;
    word-break: break-word;
  }
  
  /* Document Header Section */
  .document-header-section {
    background: linear-gradient(135deg, #004f99, #0072ff);
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
    border: 1px solid rgba(0, 198, 255, 0.3);
    box-shadow: 0 4px 20px rgba(0, 114, 255, 0.3);
  }
  .document-title-main {
    font-size: 1.5rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 15px;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
  }
  .document-meta-badges {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
  }
  .meta-badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
  }
  .meta-badge i {
    font-size: 0.9rem;
  }
  .status-badge-large {
    padding: 8px 18px;
    border-radius: 25px;
    font-size: 0.95rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.3);
  }
  .status-badge-large.bg-warning { background: linear-gradient(135deg, #ffc107, #ff9800) !important; color: #000; }
  .status-badge-large.bg-info { background: linear-gradient(135deg, #17a2b8, #0dcaf0) !important; }
  .status-badge-large.bg-primary { background: linear-gradient(135deg, #0d6efd, #0056b3) !important; }
  .status-badge-large.bg-success { background: linear-gradient(135deg, #28a745, #20c997) !important; }
  .status-badge-large.bg-danger { background: linear-gradient(135deg, #dc3545, #c82333) !important; }
  .status-badge-large.bg-secondary { background: linear-gradient(135deg, #6c757d, #5a6268) !important; }
  .status-badge-large.bg-dark { background: linear-gradient(135deg, #343a40, #23272b) !important; }
  
  /* Download Button Enhanced */
  .btn-download-doc {
    background: linear-gradient(135deg, #00c6ff, #0072ff);
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 114, 255, 0.3);
  }
  .btn-download-doc:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 198, 255, 0.5);
    background: linear-gradient(135deg, #0072ff, #00c6ff);
  }
  
  /* History Table Enhanced */
  .history-table {
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(0, 114, 255, 0.2);
  }
  .history-table thead {
    background: linear-gradient(135deg, #1a2332, #2d3e50);
    border-bottom: 2px solid #0072ff;
  }
  .history-table thead th {
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 1px;
    padding: 16px 14px;
    border: none;
    color: #00c6ff;
    white-space: nowrap;
  }
  .history-table thead th i {
    margin-right: 6px;
    opacity: 0.9;
  }
  .history-table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid rgba(0, 114, 255, 0.08);
  }
  .history-table tbody tr:last-child {
    border-bottom: none;
  }
  .history-table tbody tr:hover {
    background: rgba(0, 114, 255, 0.12) !important;
    transform: translateX(4px);
    box-shadow: inset 4px 0 0 #0072ff;
  }
  .history-table tbody td {
    padding: 16px 14px;
    vertical-align: middle;
    border: none;
    font-size: 0.9rem;
  }
  .history-table tbody td:first-child {
    font-weight: 500;
  }
  .history-datetime {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }
  .history-date {
    font-weight: 600;
    color: #00c6ff;
    font-size: 0.9rem;
  }
  .history-time {
    font-size: 0.8rem;
    color: #8899aa;
  }
  .history-action-badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
    text-transform: capitalize;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
  }
  .history-status-badge {
    padding: 5px 12px;
    border-radius: 16px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
    text-transform: capitalize;
    border: 1px solid rgba(255, 255, 255, 0.1);
  }
  .history-status-change {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }
  .history-status-arrow {
    color: #0072ff;
    font-size: 0.9rem;
  }
  .history-performer {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
  }
  .history-performer i {
    color: #0072ff;
  }
  .history-comments {
    color: #c0c8d0;
    line-height: 1.5;
    max-width: 300px;
  }
  
  /* ACTION BUTTONS */
  .btn-action { margin: 2px; padding: 4px 8px; font-size: 0.8rem; }
  
  /* Toast positioning */
  .position-fixed {
    z-index: 9999;
  }
  
  /* User role badge */
  .user-badge-admin { background-color: #dc3545 !important; }
  .user-badge-user { background-color: #6c757d !important; }
  
  /* Password toggle */
  .password-toggle {
    cursor: pointer;
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #0072ff;
  }
  .password-input-group {
    position: relative;
  }
  
  /* Profile Picture Styles */
  .profile-picture {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: contain;
    border: 2px solid #0072ff;
    background-color: #001a33;
  }
  
  .profile-picture-lg {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: contain;
    border: 3px solid #0072ff;
    margin: 0 auto 15px;
    display: block;
    background-color: #001a33;
  }
  
  .profile-picture-sidebar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: contain;
    border: 3px solid #00c6ff;
    margin: 0 auto 15px;
    display: block;
    position: relative;
    z-index: 1;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    background-color: #001a33;
  }
  
  .profile-picture-md {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: contain;
    border: 2px solid #0072ff;
    background-color: #001a33;
  }
  
  .profile-picture-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0072ff, #00c6ff);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    border: 2px solid #0072ff;
  }
  
  .profile-picture-placeholder-lg {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0072ff, #00c6ff);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 2rem;
    border: 3px solid #0072ff;
    margin: 0 auto 15px;
  }
  
  .profile-picture-placeholder-sidebar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00c6ff, #0072ff);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.5rem;
    border: 3px solid #00c6ff;
    margin: 0 auto 15px;
    position: relative;
    z-index: 1;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
  }
  
  .current-picture {
    text-align: center;
    margin-bottom: 15px;
  }
  
  .file-input-wrapper {
    position: relative;
    overflow: hidden;
    display: inline-block;
    width: 100%;
  }
  
  .file-input-btn {
    display: block;
    padding: 8px 15px;
    background: #0072ff;
    color: white;
    border-radius: 5px;
    text-align: center;
    cursor: pointer;
    transition: background 0.3s;
  }
  
  .file-input-btn:hover {
    background: #0056cc;
  }

  /* Styled filename and preview for custom file inputs */
  .file-input-filename {
    margin-left: 12px;
    color: #aad4ff;
    font-size: 0.95rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 45%;
  }

  .file-preview-img {
    display: inline-block;
    margin-left: 12px;
    width: 56px;
    height: 56px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid rgba(0,81,153,0.15);
    background-color: #001a33;
  }

  /* Enhanced picker card */
  .picker-card {
    background: linear-gradient(135deg, rgba(0,81,153,0.12), rgba(0,198,255,0.06));
    border: 1px solid rgba(0,114,255,0.12);
    padding: 8px 12px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .picker-clear {
    background: transparent;
    border: none;
    color: #ff6b6b;
    cursor: pointer;
    font-size: 1rem;
    padding: 4px;
    display: inline-flex;
    align-items: center;
  }

  .picker-filesize {
    color: #9bd1ff;
    font-size: 0.85rem;
    margin-left: 6px;
  }

  /* Keep native file input functional but invisible so the custom button can trigger it */
  .file-input-wrapper input[type="file"] {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
  }
  
  /* Circular Profile Picture Upload Container */
  .profile-upload-container {
    text-align: center;
    margin: 20px auto;
  }
  
  .profile-upload-circle {
    position: relative;
    width: 150px;
    height: 150px;
    margin: 0 auto 15px;
    cursor: pointer;
    transition: all 0.3s ease;
  }
  
  .profile-upload-circle:hover {
    transform: scale(1.05);
  }
  
  .profile-upload-preview {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #0072ff;
    background: linear-gradient(135deg, rgba(0, 114, 255, 0.1), rgba(0, 198, 255, 0.05));
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 114, 255, 0.3);
  }
  
  .profile-upload-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
  }
  
  .profile-upload-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #004f99, #0072ff);
    border-radius: 50%;
  }
  
  .profile-upload-placeholder i {
    font-size: 3rem;
    color: rgba(255, 255, 255, 0.7);
  }
  
  /* Initials display in circular preview */
  .profile-upload-preview .profile-upload-placeholder {
    font-size: 2.5rem;
    font-weight: bold;
    color: white;
  }
  
  .profile-upload-overlay {
    position: absolute;
    bottom: 5px;
    right: 5px;
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #0072ff, #00c6ff);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid #0a1929;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 3px 10px rgba(0, 114, 255, 0.5);
  }
  
  .profile-upload-overlay:hover {
    background: linear-gradient(135deg, #00c6ff, #0072ff);
    transform: scale(1.1);
  }
  
  .profile-upload-overlay i {
    font-size: 1.2rem;
    color: white;
  }
  
  .profile-upload-input {
    display: none;
  }
  
  .profile-upload-info {
    color: #aad4ff;
    font-size: 0.9rem;
    margin-top: 10px;
  }
  
  /* Profile Info in Sidebar */
  .profile-name {
    font-size: 1.1rem;
    font-weight: bold;
    margin-bottom: 5px;
    color: white;
    position: relative;
    z-index: 1;
  }
  
  .profile-role {
    font-size: 0.9rem;
    color: #aad4ff;
    margin-bottom: 15px;
    position: relative;
    z-index: 1;
  }
  
  /* Sidebar headings */
  .sidebar h5, .sidebar h6 {
    color: #00c6ff;
    padding: 10px 20px 5px;
    margin-bottom: 0;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    position: relative;
  }
  
  .sidebar h5::after, .sidebar h6::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 20px;
    width: 30px;
    height: 2px;
    background: #00c6ff;
  }
  
  /* Enhanced Profile Modal Styles */
  .profile-modal-content {
    border-radius: 15px;
    overflow: hidden;
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
  }
  
  .profile-modal-header {
    background: linear-gradient(135deg, #004f99, #0072ff);
    border-bottom: none;
    padding: 20px 25px;
    position: relative;
  }
  
  .profile-modal-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjA1KSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNwYXR0ZXJuKSIvPjwvc3ZnPg==');
  }
  
  .profile-modal-title {
    font-weight: 600;
    font-size: 1.3rem;
    color: white;
  }
  
  .profile-modal-body {
    padding: 25px;
  }
  
  .profile-tabs .nav-tabs {
    border-bottom: 2px solid #0072ff;
  }
  
  .profile-tabs .nav-link {
    color: #aad4ff;
    border: none;
    border-radius: 8px 8px 0 0;
    padding: 10px 20px;
    font-weight: 500;
  }
  
  .profile-tabs .nav-link.active {
    background: #0072ff;
    color: white;
    border: none;
  }
  
  .profile-tabs .nav-link:hover {
    border: none;
    color: white;
  }
  
  .profile-tab-content {
    padding-top: 20px;
  }
  
  .profile-form-label {
    font-weight: 500;
    color: #aad4ff;
    margin-bottom: 8px;
  }
  
  .profile-submit-btn {
    background: linear-gradient(135deg, #0072ff, #00c6ff);
    border: none;
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 500;
    transition: all 0.3s ease;
  }
  
  .profile-submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,114,255,0.4);
  }
  
  /* Make sure modal images are also fully visible */
  #view-user-picture img,
  #view-admin-picture img,
  #edit_user_current_picture img,
  #edit_admin_current_picture img {
    object-fit: contain;
    background-color: #001a33;
  }

  /* Enhanced Modal Scrollbar Styling */
  .modal-dialog-scrollable .modal-content {
    max-height: 85vh;
    display: flex;
    flex-direction: column;
  }

  .modal-dialog-scrollable .modal-body {
    overflow-y: auto;
    max-height: calc(85vh - 120px);
    padding: 20px;
  }

  /* Custom scrollbar styling for modals */
  .modal-body::-webkit-scrollbar {
    width: 10px;
  }

  .modal-body::-webkit-scrollbar-track {
    background: #0f1b33;
    border-radius: 5px;
    margin: 5px 0;
  }

  .modal-body::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #0072ff, #00c6ff);
    border-radius: 5px;
    border: 2px solid #0f1b33;
  }

  .modal-body::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #00c6ff, #0072ff);
  }

  /* Firefox scrollbar */
  .modal-body {
    scrollbar-width: thin;
    scrollbar-color: #0072ff #0f1b33;
  }

  /* Ensure the modal backdrop doesn't interfere with scrolling */
  .modal {
    backdrop-filter: blur(5px);
  }

  /* Improved modal header and footer styling */
  .modal-header {
    background: linear-gradient(135deg, #004f99, #0072ff);
    border-bottom: 2px solid #00c6ff;
    padding: 15px 25px;
    position: sticky;
    top: 0;
    z-index: 1020;
  }

  .modal-footer {
    background: #10294d;
    border-top: 1px solid #0072ff;
    padding: 15px 25px;
    position: sticky;
    bottom: 0;
    z-index: 1020;
  }

  /* Better form spacing in modal */
  .modal-body .row {
    margin-bottom: 10px;
  }

  .modal-body .mb-3 {
    margin-bottom: 1rem !important;
  }

  /* Ensure form controls are properly spaced */
  .form-control {
    margin-bottom: 8px;
  }

  /* Section headers in modal */
  .modal-body h5 {
    color: #00c6ff;
    border-bottom: 1px solid #0072ff;
    padding-bottom: 8px;
    margin-bottom: 15px;
    font-size: 1.1rem;
  }

  /* Truncate long text in tables and wrap long text in modals */
  .truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 220px;
  }

  .wrap-long {
    white-space: normal;
    word-break: break-word;
  }

  /* Make members/directors tables stable and truncated */
  #membersTable, #directorsTable {
    table-layout: fixed;
    width: 100%;
  }
  #membersTable td, #membersTable th, #directorsTable td, #directorsTable th {
    overflow: hidden;
    text-overflow: ellipsis;
  }
  /* Make actions column compact */
  #membersTable td:last-child, #directorsTable td:last-child {
    width: 110px;
    white-space: nowrap;
  }
  
  /* Workflow Progress Styles */
  .workflow-progress-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .workflow-step {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px 15px;
    background: rgba(15, 27, 51, 0.4);
    border-radius: 8px;
    border-left: 4px solid rgba(108, 117, 125, 0.3);
    opacity: 0.5;
    transition: all 0.3s ease;
  }
  .workflow-step.active {
    opacity: 1;
    border-left-color: #0072ff;
    background: rgba(0, 114, 255, 0.15);
    box-shadow: 0 0 15px rgba(0, 114, 255, 0.3);
  }
  .workflow-step.completed {
    opacity: 1;
    border-left-color: #28a745;
    background: rgba(40, 167, 69, 0.1);
  }
  .workflow-step.rejected {
    opacity: 1;
    border-left-color: #dc3545;
    background: rgba(220, 53, 69, 0.1);
  }
  .workflow-step-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(0, 114, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #6c757d;
    transition: all 0.3s ease;
  }
  .workflow-step.active .workflow-step-icon {
    background: linear-gradient(135deg, #0072ff, #00c6ff);
    color: white;
    box-shadow: 0 4px 15px rgba(0, 114, 255, 0.4);
  }
  .workflow-step.completed .workflow-step-icon {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
  }
  .workflow-step.rejected .workflow-step-icon {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
  }
  .workflow-step-content {
    flex: 1;
  }
  .workflow-step-title {
    font-weight: 600;
    font-size: 0.95rem;
    color: #aad4ff;
    margin-bottom: 2px;
  }
  .workflow-step.active .workflow-step-title {
    color: #00c6ff;
  }
  .workflow-step-subtitle {
    font-size: 0.8rem;
    color: #6c757d;
  }
  .workflow-step.active .workflow-step-subtitle {
    color: #9bd1ff;
  }
  
  /* Alert styling in modal footer */
  .modal-footer .alert {
    border-radius: 8px;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.95rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
  }
  .modal-footer .alert i {
    font-size: 1.2rem;
  }
  
  /* Responsive adjustments for document header */
  @media (max-width: 768px) {
    .document-header-section {
      padding: 20px;
    }
    .document-title-main {
      font-size: 1.2rem;
    }
    .document-meta-badges {
      flex-direction: column;
      align-items: flex-start;
    }
  }
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar fixed-top">
  <a class="navbar-brand" href="#"><i class="fa-solid fa-calculator"></i> Accounting System</a>
  
  <div class="navbar-user-info">
    <!-- Notification Bell -->
    <div class="notification-bell-container">
      <button class="notification-bell-btn" id="notificationBellBtn" data-bs-toggle="modal" data-bs-target="#notificationModal">
        <i class="fa-solid fa-bell"></i>
        <?php if ($unreadNotifications > 0): ?>
        <span class="notification-badge" id="notificationBadge"><?php echo $unreadNotifications; ?></span>
        <?php endif; ?>
      </button>
    </div>
    
    <span class="user-display-name"><?php echo htmlspecialchars($current_user['full_name']); ?></span>
    <span class="badge user-role-badge <?php echo $isAdmin ? 'user-badge-admin' : 'user-badge-user'; ?>">
      <?php echo htmlspecialchars($displayRole); ?>
    </span>
    <a href="logout.php" class="logout-btn">
      <i class="fa-solid fa-right-from-bracket"></i> Logout
    </a>
  </div>
</nav>

<!-- SIDEBAR -->
<div class="sidebar">
  <!-- PROFILE SECTION -->
  <div class="profile-section">
    <?php
    $initials = '';
    if (!empty($current_user['full_name'])) {
      $nameParts = explode(' ', $current_user['full_name']);
      $initials = '';
      foreach ($nameParts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
      }
      $initials = substr($initials, 0, 2);
    }
    ?>
    
    <?php if (!empty($current_user['image_url'])): ?>
      <img src="<?php echo htmlspecialchars($current_user['image_url']); ?>" alt="Profile" class="profile-picture-sidebar">
    <?php else: ?>
      <div class="profile-picture-placeholder-sidebar"><?php echo $initials; ?></div>
    <?php endif; ?>
    
    <div class="profile-name"><?php echo htmlspecialchars($current_user['full_name']); ?></div>
    <div class="profile-role">
      <span class="badge <?php echo $isAdmin ? 'user-badge-admin' : 'user-badge-user'; ?>">
        <?php echo htmlspecialchars($displayRole); ?>
      </span>
    </div>
  </div>
  
  <!-- SIDEBAR LINKS -->
  <div class="sidebar-links">
    <h5>MAIN</h5>
    <a href="#" onclick="showPage('dashboard')"><i class="fa-solid fa-gauge"></i><span>Dashboard</span></a>
    
  <?php if ($isAdmin): ?>
    <!-- ADMIN Section -->
    <div class="sidebar-section-wrapper" id="adminWrapper">
      <h6 class="sidebar-section-header" onclick="toggleSidebarSection('adminWrapper')">
        <span><i class="fa-solid fa-user-shield me-2"></i>ADMIN</span>
        <i class="fa-solid fa-chevron-down toggle-icon"></i>
      </h6>
      <div class="sidebar-section-content">
        <a href="#" data-bs-toggle="modal" data-bs-target="#addAdminModal"><i class="fa-solid fa-user-plus"></i><span>Add Admin</span></a>
        <a href="#" onclick="showPage('admins')"><i class="fa-solid fa-users-gear"></i><span>Manage Admins</span></a>
      </div>
    </div>
    
    <!-- USER Section -->
    <div class="sidebar-section-wrapper" id="userWrapper">
      <h6 class="sidebar-section-header" onclick="toggleSidebarSection('userWrapper')">
        <span><i class="fa-solid fa-users me-2"></i>USER</span>
        <i class="fa-solid fa-chevron-down toggle-icon"></i>
      </h6>
      <div class="sidebar-section-content">
        <a href="#" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="fa-solid fa-user-plus"></i><span>Add User</span></a>
        <a href="#" onclick="showPage('users')"><i class="fa-solid fa-users"></i><span>Manage Users</span></a>
      </div>
    </div>
    <?php endif; ?>
    
    <!-- COMPANY Section -->
    <div class="sidebar-section-wrapper" id="companyWrapper">
      <h6 class="sidebar-section-header" onclick="toggleSidebarSection('companyWrapper')">
        <span><i class="fa-solid fa-building me-2"></i>COMPANY</span>
        <i class="fa-solid fa-chevron-down toggle-icon"></i>
      </h6>
      <div class="sidebar-section-content">
        <a href="#" data-bs-toggle="modal" data-bs-target="#addCompanyModal"><i class="fa-solid fa-building"></i><span>Add Company</span></a>
        <a href="#" onclick="showPage('companies')"><i class="fa-solid fa-building-user"></i><span>Manage Companies</span></a>
      </div>
    </div>
    
    <!-- DOCUMENT FLOW Section -->
    <div class="sidebar-section-wrapper" id="documentWrapper">
      <h6 class="sidebar-section-header" onclick="toggleSidebarSection('documentWrapper')">
        <span><i class="fa-solid fa-folder me-2"></i>DOCUMENT FLOW</span>
        <i class="fa-solid fa-chevron-down toggle-icon"></i>
      </h6>
      <div class="sidebar-section-content">
        <a href="#" data-bs-toggle="modal" data-bs-target="#addDocumentModal"><i class="fa-solid fa-file-circle-plus"></i><span>Collect Document</span></a>
        <a href="#" onclick="showPage('documents')"><i class="fa-solid fa-folder-open"></i><span>Manage Documents</span></a>
      </div>
    </div>
    
    <!-- CLIENT QUERIES Section -->
    <div class="sidebar-section-wrapper" id="queriesWrapper">
      <h6 class="sidebar-section-header" onclick="toggleSidebarSection('queriesWrapper')">
        <span><i class="fa-solid fa-comments me-2"></i>CLIENT QUERIES</span>
        <i class="fa-solid fa-chevron-down toggle-icon"></i>
      </h6>
      <div class="sidebar-section-content">
        <a href="#" data-bs-toggle="modal" data-bs-target="#addQueryModal"><i class="fa-solid fa-circle-question"></i><span>Add Query</span></a>
        <a href="#" onclick="showPage('queries')"><i class="fa-solid fa-comments"></i><span>Manage Queries</span></a>
      </div>
    </div>
    
    <!-- MANAGER REVIEW Section -->
    <div class="sidebar-section-wrapper" id="reviewWrapper">
      <h6 class="sidebar-section-header" onclick="toggleSidebarSection('reviewWrapper')">
        <span><i class="fa-solid fa-clipboard-check me-2"></i>MANAGER REVIEW</span>
        <i class="fa-solid fa-chevron-down toggle-icon"></i>
      </h6>
      <div class="sidebar-section-content">
        <a href="#" data-bs-toggle="modal" data-bs-target="#addReviewModal"><i class="fa-solid fa-user-tie"></i><span>Add Review</span></a>
        <a href="#" onclick="showPage('reviews')"><i class="fa-solid fa-clipboard-check"></i><span>Manage Reviews</span></a>
      </div>
    </div>
    
    <!-- MANAGEMENT LETTER Section -->
    <div class="sidebar-section-wrapper" id="managementLetterWrapper">
      <h6 class="sidebar-section-header" onclick="toggleSidebarSection('managementLetterWrapper')">
        <span><i class="fa-solid fa-file-contract me-2"></i>MANAGEMENT LETTER</span>
        <i class="fa-solid fa-chevron-down toggle-icon"></i>
      </h6>
      <div class="sidebar-section-content">
        <a href="#" onclick="showPage('managementLetter')"><i class="fa-solid fa-file-signature"></i><span>View Letter</span></a>
      </div>
    </div>
  </div>
</div>

<!-- CONTENT -->
<div class="content">
  <!-- DASHBOARD -->
  <div id="dashboard">
    <div class="row mb-4">
      <div class="col">
        <h2 class="fw-bold text-info">Dashboard Overview</h2>
      </div>
    </div>
    
    <!-- Main Stats Cards - Row 1 -->
    <div class="row g-4 mb-4">
      <div class="col-md-3">
        <div class="card-dashboard" onclick="showPage('companies')">
          <i class="fa-solid fa-building"></i>
          <h5>Companies</h5>
          <p><?php echo $totalCompanies; ?></p>
          <div class="card-label">Total Registered</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card-dashboard" onclick="showPage('documents')">
          <i class="fa-solid fa-file-lines"></i>
          <h5>Documents</h5>
          <p><?php echo $totalDocuments; ?></p>
          <div class="card-label">All Documents</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card-dashboard" onclick="showPage('queries')" style="background: linear-gradient(135deg, #6a4d1b, #997a00);">
          <i class="fa-solid fa-comments" style="color: #ffc107;"></i>
          <h5>Client Queries</h5>
          <p><?php echo $totalQueries; ?></p>
          <div class="card-label">Total Queries</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card-dashboard" onclick="showPage('reviews')" style="background: linear-gradient(135deg, #1e3a8a, #3b82f6);">
          <i class="fa-solid fa-clipboard-check" style="color: #60a5fa;"></i>
          <h5>Manager Reviews</h5>
          <p><?php echo $totalReviews; ?></p>
          <div class="card-label">Total Reviews</div>
        </div>
      </div>
    </div>
    
    <!-- Second Row of Cards -->
    <div class="row g-4 mb-4">
      <div class="col-md-3">
        <div class="card-dashboard" <?php if($isAdmin): ?>onclick="showPage('users')"<?php endif; ?>>
          <i class="fa-solid fa-users"></i>
          <h5>Users</h5>
          <p><?php echo $totalUsers; ?></p>
          <div class="card-label">Active Users</div>
        </div>
      </div>
      <?php if ($isAdmin): ?>
      <div class="col-md-3">
        <div class="card-dashboard" onclick="showPage('admins')">
          <i class="fa-solid fa-user-shield"></i>
          <h5>Admins</h5>
          <p><?php echo $totalAdmins; ?></p>
          <div class="card-label">Administrators</div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Document Status Breakdown -->
    <div class="dashboard-section mb-4">
      <h4><i class="fa-solid fa-chart-pie"></i> Document Status Overview</h4>
      <div class="status-breakdown">
        <div class="status-card">
          <div class="status-card-number status-pending"><?php echo $pendingDocuments; ?></div>
          <div class="status-card-label">Pending</div>
        </div>
        <div class="status-card">
          <div class="status-card-number status-reviewed"><?php echo $reviewedDocuments; ?></div>
          <div class="status-card-label">Reviewed</div>
        </div>
        <div class="status-card">
          <div class="status-card-number status-approved"><?php echo $approvedDocuments; ?></div>
          <div class="status-card-label">Approved</div>
        </div>
        <div class="status-card">
          <div class="status-card-number status-rejected"><?php echo $rejectedDocuments; ?></div>
          <div class="status-card-label">Rejected</div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- Quick Actions -->
      <div class="col-md-6">
        <div class="dashboard-section">
          <h4><i class="fa-solid fa-bolt"></i> Quick Actions</h4>
          <div class="d-flex flex-column gap-3">
            <button class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
              <i class="fa-solid fa-building-circle-plus"></i>
              <span>Add New Company</span>
            </button>
            <button class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#addDocumentModal">
              <i class="fa-solid fa-file-circle-plus"></i>
              <span>Collect Document</span>
            </button>
            <button class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#addQueryModal" style="background: linear-gradient(135deg, #ffc107, #ff9800);">
              <i class="fa-solid fa-circle-question"></i>
              <span>Add Client Query</span>
            </button>
            <?php if ($isAdmin): ?>
            <button class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#addUserModal">
              <i class="fa-solid fa-user-plus"></i>
              <span>Add New User</span>
            </button>
            <?php endif; ?>
            <button class="quick-action-btn" onclick="showPage('documents')">
              <i class="fa-solid fa-folder-open"></i>
              <span>View All Documents</span>
            </button>
            <button class="quick-action-btn" onclick="showPage('queries')" style="background: linear-gradient(135deg, #997a00, #6a4d1b);">
              <i class="fa-solid fa-comments"></i>
              <span>View All Queries</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="col-md-6">
        <div class="dashboard-section">
          <h4><i class="fa-solid fa-clock-rotate-left"></i> Recent Documents</h4>
          <?php if (count($recentDocuments) > 0): ?>
            <?php foreach ($recentDocuments as $recent): 
              $statusClass = '';
              switch($recent['status']) {
                case 'Pending': $statusClass = 'status-pending'; break;
                case 'Reviewed': $statusClass = 'status-reviewed'; break;
                case 'Approved': 
                case 'Final Approved': $statusClass = 'status-approved'; break;
                case 'Rejected': $statusClass = 'status-rejected'; break;
              }
            ?>
            <div class="recent-activity-item" onclick="viewDocumentDetails(<?php echo $recent['document_id']; ?>)" style="cursor: pointer;">
              <div class="activity-title"><?php echo htmlspecialchars($recent['document_title']); ?></div>
              <div class="activity-meta">
                <span><i class="fa-solid fa-building"></i> <?php echo htmlspecialchars($recent['company_name'] ?? 'N/A'); ?></span>
                <span><i class="fa-solid fa-tag"></i> <?php echo htmlspecialchars($recent['document_type']); ?></span>
                <span class="<?php echo $statusClass; ?>"><i class="fa-solid fa-circle-dot"></i> <?php echo htmlspecialchars($recent['status']); ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="text-center text-muted py-3">
              <i class="fa-solid fa-inbox fa-3x mb-2" style="opacity: 0.3;"></i>
              <p>No recent documents</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- MANAGE COMPANIES -->
  <div id="companies" class="hidden">
    <h2 class="fw-bold text-info mb-3">Manage Companies</h2>
    
    <!-- Export Buttons -->
    <div class="mb-2 d-flex gap-2">
      <button class="btn btn-outline-danger btn-sm" onclick="exportTableToPDF('companiesTable', 'Companies_List')">
        <i class="fa-solid fa-file-pdf"></i> Export to PDF
      </button>
      <button class="btn btn-outline-primary btn-sm" onclick="printTable('companiesTable')">
        <i class="fa-solid fa-print"></i> Print
      </button>
    </div>
    
    <!-- Filter and Search Section -->
    <div class="mb-3">
      <div class="row g-2">
        <div class="col-md-3">
          <input type="text" id="searchBar" class="form-control" placeholder="🔍 Search companies...">
        </div>
        <div class="col-md-2">
          <select id="fyeQuickFilter" class="form-select" onchange="filterFYEQuick()">
            <option value="all">All Companies</option>
            <option value="next30">Next 30 Days</option>
            <option value="next60">Next 60 Days</option>
            <option value="next90">Next 90 Days</option>
            <option value="thisMonth">This Month</option>
            <option value="nextMonth">Next Month</option>
          </select>
        </div>
        <div class="col-md-2">
          <input type="date" id="fyeFromDate" class="form-control" placeholder="FYE From" onchange="filterFYECustomRange()">
        </div>
        <div class="col-md-2">
          <input type="date" id="fyeToDate" class="form-control" placeholder="FYE To" onchange="filterFYECustomRange()">
        </div>
        <div class="col-md-3">
          <button type="button" class="btn btn-secondary w-100" onclick="clearFYEFilter()">
            <i class="fa-solid fa-filter-circle-xmark"></i> Clear Filters
          </button>
        </div>
      </div>
    </div>
    
    <!-- Filter Statistics -->
    <div class="mb-3 p-3" style="background: rgba(0, 114, 255, 0.1); border-radius: 8px; border-left: 4px solid #0072ff;">
      <div class="row text-center">
        <div class="col-md-3">
          <div class="text-light small">Total Companies</div>
          <div class="h5 text-info mb-0" id="totalCompaniesCount">0</div>
        </div>
        <div class="col-md-3">
          <div class="text-light small">Filtered Results</div>
          <div class="h5 text-warning mb-0" id="filteredCompaniesCount">0</div>
        </div>
        <div class="col-md-3">
          <div class="text-light small">Next 30 Days</div>
          <div class="h5 text-danger mb-0" id="next30DaysCount">0</div>
        </div>
        <div class="col-md-3">
          <div class="text-light small">Active Filter</div>
          <div class="h5 text-success mb-0" id="activeFilterLabel">None</div>
        </div>
      </div>
    </div>
    
    <!-- Company Table -->
    <div class="table-container">
      <table class="table table-hover table-striped" id="companiesTable">
        <thead><tr><th>No</th><th>Company Name</th><th>SSM No</th><th>Type</th><th>Sub Type</th><th>Email</th><th>MSIC Code</th><th>Office No</th><th>FYE Date</th><th>Days Until FYE</th></tr></thead>
        <tbody>
          <?php 
          $companyCounter = 1;
          foreach ($companies as $c): 
          ?>
          <?php
          // Calculate days until FYE
          $daysUntilFYE = 'N/A';
          $fyeClass = '';
          $fyeDateDisplay = $c['financial_year_end'] ?? 'Not Set';
          
          if (!empty($c['financial_year_end'])) {
            $fyeDate = new DateTime($c['financial_year_end']);
            $currentDate = new DateTime();
            $currentYear = (int)$currentDate->format('Y');
            
            // Calculate this year's FYE
            $thisYearFYE = new DateTime($currentYear . '-' . $fyeDate->format('m-d'));
            
            // If this year's FYE has passed, check next year
            if ($thisYearFYE < $currentDate) {
              $thisYearFYE->modify('+1 year');
            }
            
            $interval = $currentDate->diff($thisYearFYE);
            $days = (int)$interval->format('%r%a');
            
            if ($days >= 0) {
              $daysUntilFYE = $days;
              if ($days <= 30) {
                $fyeClass = 'fye-urgent';
              } elseif ($days <= 60) {
                $fyeClass = 'fye-warning';
              } elseif ($days <= 90) {
                $fyeClass = 'fye-info';
              }
            }
            $fyeDateDisplay = $fyeDate->format('d M Y');
          }
          ?>
          <tr class="company-row <?php echo $fyeClass; ?>" style="cursor:pointer;"
              data-company='<?php echo json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
              data-fye-date="<?php echo $c['financial_year_end'] ?? ''; ?>"
              data-days-until-fye="<?php echo is_numeric($daysUntilFYE) ? $daysUntilFYE : '9999'; ?>">
            <td><?php echo $companyCounter; ?></td>
            <td class="fw-semibold"><?php echo htmlspecialchars($c['company_name']); ?></td>
            <td><?php echo htmlspecialchars($c['ssm_no']); ?></td>
            <td><?php echo htmlspecialchars($c['company_type']); ?></td>
            <td><?php echo htmlspecialchars($c['sub_type'] ?? 'N/A'); ?></td>
            <td><?php echo htmlspecialchars($c['email']); ?></td>
            <td><?php echo htmlspecialchars($c['msic_code']); ?></td>
            <td><?php echo htmlspecialchars($c['office_no']); ?></td>
            <td>
              <?php if (!empty($c['financial_year_end'])): ?>
                <span class="badge bg-info"><?php echo $fyeDateDisplay; ?></span>
              <?php else: ?>
                <span class="text-muted">Not Set</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (is_numeric($daysUntilFYE)): ?>
                <?php if ($daysUntilFYE <= 30): ?>
                  <span class="badge bg-danger"><i class="fa-solid fa-exclamation-triangle"></i> <?php echo $daysUntilFYE; ?> days</span>
                <?php elseif ($daysUntilFYE <= 60): ?>
                  <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock"></i> <?php echo $daysUntilFYE; ?> days</span>
                <?php elseif ($daysUntilFYE <= 90): ?>
                  <span class="badge bg-info"><i class="fa-solid fa-calendar"></i> <?php echo $daysUntilFYE; ?> days</span>
                <?php else: ?>
                  <span class="badge bg-secondary"><?php echo $daysUntilFYE; ?> days</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-muted">N/A</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php 
          $companyCounter++;
          endforeach; 
          ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($isAdmin): ?>
  <!-- MANAGE USERS -->
  <div id="users" class="hidden">
    <h2 class="fw-bold text-info mb-3">Manage Users</h2>
    <div class="mb-2 d-flex gap-2">
      <button class="btn btn-outline-danger btn-sm" onclick="exportTableToPDF('usersTable', 'Users_List')">
        <i class="fa-solid fa-file-pdf"></i> Export to PDF
      </button>
      <button class="btn btn-outline-primary btn-sm" onclick="printTable('usersTable')">
        <i class="fa-solid fa-print"></i> Print
      </button>
    </div>
    <div class="mb-3"><input type="text" id="searchUsers" class="form-control" placeholder="🔍 Search users..."></div>
    <div class="table-container">
      <table class="table table-hover table-striped" id="usersTable">
        <thead>
          <tr>
            <th>No</th>
            <th>Profile</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Role</th>
            <th>Last Login</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $userCounter = 1;
          foreach ($users as $u): 
            $initials = '';
            if (!empty($u['full_name'])) {
              $nameParts = explode(' ', $u['full_name']);
              $initials = '';
              foreach ($nameParts as $part) {
                $initials .= strtoupper(substr($part, 0, 1));
              }
              $initials = substr($initials, 0, 2);
            }
          ?>
          <tr data-user-id="<?php echo $u['user_id']; ?>">
            <td><?php echo $userCounter; ?></td>
            <td>
              <?php if (!empty($u['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($u['image_url']); ?>" alt="Profile" class="profile-picture">
              <?php else: ?>
                <div class="profile-picture-placeholder"><?php echo $initials; ?></div>
              <?php endif; ?>
            </td>
            <td class="user-name"><?php echo htmlspecialchars($u['full_name']); ?></td>
            <td class="user-email"><?php echo htmlspecialchars($u['email']); ?></td>
            <td class="user-phone"><?php echo htmlspecialchars($u['phone'] ?? 'N/A'); ?></td>
            <td>
              <span class="user-role badge 
                <?php 
                  if(isset($u['role'])) {
                    echo $u['role'] == 'manager' ? 'bg-primary' : 
                         ($u['role'] == 'accountant' ? 'bg-warning' : 
                         ($u['role'] == 'auditor' ? 'bg-info' : 'bg-secondary'));
                  } else {
                    echo 'bg-secondary';
                  }
                ?>">
                <?php echo isset($u['role']) ? htmlspecialchars(ucfirst($u['role'])) : 'Employee'; ?>
              </span>
            </td>
            <td><?php echo htmlspecialchars($u['last_login'] ?? 'Never'); ?></td>
            <td><span class="badge bg-success">Active</span></td>
            <td>
              <button class="btn btn-sm btn-info btn-action view-user" data-id="<?php echo $u['user_id']; ?>">View</button>
              <button class="btn btn-sm btn-warning btn-action edit-user" 
                      data-id="<?php echo $u['user_id']; ?>"
                      data-full_name="<?php echo htmlspecialchars($u['full_name']); ?>"
                      data-email="<?php echo htmlspecialchars($u['email']); ?>"
                      data-phone="<?php echo htmlspecialchars($u['phone'] ?? ''); ?>"
                      data-role="<?php echo htmlspecialchars($u['role'] ?? 'employee'); ?>"
                      data-image_url="<?php echo htmlspecialchars($u['image_url'] ?? ''); ?>">Edit</button>
              <button class="btn btn-sm btn-primary btn-action change-password-user" 
                      data-id="<?php echo $u['user_id']; ?>"
                      data-full_name="<?php echo htmlspecialchars($u['full_name']); ?>">Change Password</button>
              <button class="btn btn-sm btn-danger btn-action delete-user" data-id="<?php echo $u['user_id']; ?>">Delete</button>
            </td>
          </tr>
          <?php 
          $userCounter++;
          endforeach; 
          ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- MANAGE ADMINS -->
  <div id="admins" class="hidden">
    <h2 class="fw-bold text-info mb-3">Manage Admins</h2>
    <div class="mb-2 d-flex gap-2">
      <button class="btn btn-outline-danger btn-sm" onclick="exportTableToPDF('adminsTable', 'Admins_List')">
        <i class="fa-solid fa-file-pdf"></i> Export to PDF
      </button>
      <button class="btn btn-outline-primary btn-sm" onclick="printTable('adminsTable')">
        <i class="fa-solid fa-print"></i> Print
      </button>
    </div>
    <div class="mb-3"><input type="text" id="searchAdmins" class="form-control" placeholder="🔍 Search admins..."></div>
    <div class="table-container">
      <table class="table table-hover table-striped" id="adminsTable">
        <thead>
          <tr>
            <th>No</th>
            <th>Profile</th>
            <th>Username</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Role</th>
            <th>Last Login</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $adminCounter = 1;
          foreach ($admins as $a): 
            $initials = '';
            if (!empty($a['full_name'])) {
              $nameParts = explode(' ', $a['full_name']);
              $initials = '';
              foreach ($nameParts as $part) {
                $initials .= strtoupper(substr($part, 0, 1));
              }
              $initials = substr($initials, 0, 2);
            }
          ?>
          <tr data-admin-id="<?php echo $a['admin_id']; ?>">
            <td><?php echo $adminCounter; ?></td>
            <td>
              <?php if (!empty($a['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($a['image_url']); ?>" alt="Profile" class="profile-picture">
              <?php else: ?>
                <div class="profile-picture-placeholder"><?php echo $initials; ?></div>
              <?php endif; ?>
            </td>
            <td class="admin-name"><?php echo htmlspecialchars($a['full_name']); ?></td>
            <td class="admin-email"><?php echo htmlspecialchars($a['email']); ?></td>
            <td class="admin-phone"><?php echo htmlspecialchars($a['phone'] ?? 'N/A'); ?></td>
            <td><span class="badge bg-primary">Administrator</span></td>
            <td><?php echo htmlspecialchars($a['last_login'] ?? 'Never'); ?></td>
            <td>
              <button class="btn btn-sm btn-info btn-action view-admin" data-id="<?php echo $a['admin_id']; ?>">View</button>
              <button class="btn btn-sm btn-warning btn-action edit-admin" 
                      data-id="<?php echo $a['admin_id']; ?>"
                      data-full_name="<?php echo htmlspecialchars($a['full_name']); ?>"
                      data-email="<?php echo htmlspecialchars($a['email']); ?>"
                      data-phone="<?php echo htmlspecialchars($a['phone'] ?? ''); ?>"
                      data-image_url="<?php echo htmlspecialchars($a['image_url'] ?? ''); ?>">Edit</button>
              <button class="btn btn-sm btn-danger btn-action delete-admin" data-id="<?php echo $a['admin_id']; ?>">Delete</button>
            </td>
          </tr>
          <?php 
          $adminCounter++;
          endforeach; 
          ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- MANAGE DOCUMENTS -->
  <div id="documents" class="hidden">
    <h2 class="fw-bold text-info mb-3">Document Flow Management</h2>
    <div class="mb-2 d-flex gap-2">
      <button class="btn btn-outline-danger btn-sm" onclick="exportTableToPDF('documentsTable', 'Documents_List')">
        <i class="fa-solid fa-file-pdf"></i> Export to PDF
      </button>
      <button class="btn btn-outline-primary btn-sm" onclick="printTable('documentsTable')">
        <i class="fa-solid fa-print"></i> Print
      </button>
    </div>
    <div class="mb-3">
      <div class="row g-2">
        <div class="col-md-4">
          <input type="text" id="searchDocuments" class="form-control" placeholder="🔍 Search documents...">
        </div>
        <div class="col-md-3">
          <input type="date" id="filterDateFrom" class="form-control" placeholder="From Date">
        </div>
        <div class="col-md-3">
          <input type="date" id="filterDateTo" class="form-control" placeholder="To Date">
        </div>
        <div class="col-md-2">
          <button class="btn btn-secondary w-100" id="clearDocumentFilters">
            <i class="fa-solid fa-filter-circle-xmark"></i> Clear
          </button>
        </div>
      </div>
    </div>
    <div class="table-container">
      <table class="table table-hover table-striped" id="documentsTable">
        <thead>
          <tr>
            <th>No</th>
            <th>Document Title</th>
            <th>Type</th>
            <th>Company</th>
            <th>Location</th>
            <th>Collect Date</th>
            <th>Status</th>
            <th>Created By</th>
            <th>Current Handler</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $docCounter = 1;
          foreach ($documents as $doc): 
            $statusClass = '';
            switch($doc['status']) {
              case 'Pending': $statusClass = 'bg-warning'; break;
              case 'Reviewed': $statusClass = 'bg-info'; break;
              case 'Approved': $statusClass = 'bg-primary'; break;
              case 'Final Approved': $statusClass = 'bg-success'; break;
              case 'Rejected': $statusClass = 'bg-danger'; break;
              case 'Returned': $statusClass = 'bg-secondary'; break;
              case 'Submit': $statusClass = 'bg-dark'; break;
            }
          ?>
          <tr data-document-id="<?php echo $doc['document_id']; ?>" class="document-row" style="cursor: pointer;">
            <td><?php echo $docCounter; ?></td>
            <td class="doc-title"><?php echo htmlspecialchars($doc['document_title']); ?></td>
            <td><?php echo htmlspecialchars($doc['document_type']); ?></td>
            <td><?php echo htmlspecialchars($doc['company_name'] ?? 'N/A'); ?></td>
            <td><?php echo htmlspecialchars($doc['location'] ?? 'N/A'); ?></td>
            <td><?php echo htmlspecialchars($doc['date_of_collect']); ?></td>
            <td><span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($doc['status']); ?></span></td>
            <td><?php echo htmlspecialchars($doc['creator_name'] ?? 'N/A'); ?></td>
            <td>
              <?php 
                if ($doc['status'] === 'Submit') {
                  echo '<span class="text-dark">Submitted to Client</span>';
                } elseif ($doc['status'] === 'Final Approved') {
                  echo '<span class="text-success">' . htmlspecialchars($doc['creator_name'] ?? 'Creator') . '</span>';
                } elseif ($doc['status'] === 'Rejected') {
                  echo '<span class="text-danger">None</span>';
                } elseif (!empty($doc['handler_name'])) {
                  echo htmlspecialchars($doc['handler_name']);
                } else {
                  echo '<span class="text-muted">Unassigned</span>';
                }
              ?>
            </td>
            <td onclick="event.stopPropagation();">
              <button class="btn btn-sm btn-info btn-action" onclick="event.stopPropagation(); viewDocumentDetails(<?php echo $doc['document_id']; ?>)">
                <i class="fa-solid fa-eye"></i> View Details
              </button>
            </td>
          </tr>
          <?php 
          $docCounter++;
          endforeach; 
          ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- MANAGE CLIENT QUERIES -->
  <div id="queries" class="hidden">
    <h2 class="fw-bold text-info mb-3">Client Queries Management</h2>
    
    <!-- Export Buttons -->
    <div class="mb-2 d-flex gap-2">
      <button class="btn btn-outline-danger btn-sm" onclick="exportTableToPDF('queriesTable', 'Client_Queries')">
        <i class="fa-solid fa-file-pdf"></i> Export to PDF
      </button>
      <button class="btn btn-outline-primary btn-sm" onclick="printTable('queriesTable')">
        <i class="fa-solid fa-print"></i> Print
      </button>
    </div>
    
    <!-- Filter and Search Section -->
    <div class="mb-3">
      <div class="row g-2">
        <div class="col-md-3">
          <input type="text" id="searchQueries" class="form-control" placeholder="🔍 Search queries...">
        </div>
        <div class="col-md-2">
          <select id="filterQueryType" class="form-select">
            <option value="">All Types</option>
            <option value="RD">RD</option>
            <option value="AG">AG</option>
            <option value="Doc">Doc</option>
          </select>
        </div>
        <div class="col-md-2">
          <select id="filterRiskLevel" class="form-select">
            <option value="">All Risk Levels</option>
            <option value="Low">Low</option>
            <option value="Middle">Middle</option>
            <option value="High">High</option>
          </select>
        </div>
        <div class="col-md-2">
          <select id="filterQueryStatus" class="form-select">
            <option value="">All Status</option>
            <option value="Pending">Pending</option>
            <option value="In Progress">In Progress</option>
            <option value="Resolved">Resolved</option>
            <option value="Closed">Closed</option>
          </select>
        </div>
        <div class="col-md-3">
          <button type="button" class="btn btn-secondary w-100" id="clearQueryFilters">
            <i class="fa-solid fa-filter-circle-xmark"></i> Clear Filters
          </button>
        </div>
      </div>
    </div>
    
    <!-- Queries Table -->
    <div class="table-container">
      <table class="table table-hover table-striped" id="queriesTable">
        <thead>
          <tr>
            <th>No</th>
            <th>Client Name</th>
            <th>Company</th>
            <th>Question</th>
            <th>Type</th>
            <th>Risk Level</th>
            <th title="Management Letter">ML</th>
            <th>Date</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="10" class="text-center text-muted py-4">
              <i class="fa-solid fa-inbox fa-3x mb-2" style="opacity: 0.3;"></i>
              <p>No queries found. Click "Add Query" to create one.</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- MANAGE MANAGER REVIEWS -->
  <div id="reviews" class="hidden">
    <h2 class="fw-bold text-info mb-3">Manager Reviews Management</h2>
    
    <!-- Export Buttons -->
    <div class="mb-2 d-flex gap-2">
      <button class="btn btn-outline-danger btn-sm" onclick="exportTableToPDF('reviewsTable', 'Manager_Reviews')">
        <i class="fa-solid fa-file-pdf"></i> Export to PDF
      </button>
      <button class="btn btn-outline-primary btn-sm" onclick="printTable('reviewsTable')">
        <i class="fa-solid fa-print"></i> Print
      </button>
    </div>
    
    <!-- Filter and Search Section -->
    <div class="mb-3">
      <div class="row g-2">
        <div class="col-md-3">
          <input type="text" id="searchReviews" class="form-control" placeholder="🔍 Search reviews...">
        </div>
        <div class="col-md-2">
          <select id="filterReviewType" class="form-select">
            <option value="">All Types</option>
            <option value="RD">RD</option>
            <option value="AG">AG</option>
            <option value="Doc">Doc</option>
          </select>
        </div>
        <div class="col-md-2">
          <select id="filterReviewRiskLevel" class="form-select">
            <option value="">All Risk Levels</option>
            <option value="Low">Low</option>
            <option value="Middle">Middle</option>
            <option value="High">High</option>
          </select>
        </div>
        <div class="col-md-2">
          <select id="filterReviewStatus" class="form-select">
            <option value="">All Status</option>
            <option value="Pending">Pending</option>
            <option value="In Progress">In Progress</option>
            <option value="Resolved">Resolved</option>
            <option value="Closed">Closed</option>
          </select>
        </div>
        <div class="col-md-3">
          <button type="button" class="btn btn-secondary w-100" id="clearReviewFilters">
            <i class="fa-solid fa-filter-circle-xmark"></i> Clear Filters
          </button>
        </div>
      </div>
    </div>
    
    <!-- Reviews Table -->
    <div class="table-container">
      <table class="table table-hover table-striped" id="reviewsTable">
        <thead>
          <tr>
            <th>No</th>
            <th>Manager Name</th>
            <th>Company</th>
            <th>Question</th>
            <th>Type</th>
            <th>Risk Level</th>
            <th title="Management Letter">ML</th>
            <th>Date</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="10" class="text-center text-muted py-4">
              <i class="fa-solid fa-inbox fa-3x mb-2" style="opacity: 0.3;"></i>
              <p>No reviews found. Click "Add Review" to create one.</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- TIME COST PAGE -->
  <div id="timeCost" class="hidden">
    <h2 class="fw-bold text-info mb-3">Time Cost Management</h2>
    
    <!-- Action Buttons -->
    <div class="mb-3 d-flex gap-2">
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#timeCostEntryModal">
        <i class="fa-solid fa-plus me-1"></i> Add Entry
      </button>
      <button class="btn btn-outline-primary" onclick="loadTimeCostList()">
        <i class="fa-solid fa-rotate me-1"></i> Refresh
      </button>
      <a class="btn btn-outline-danger" href="view_time_cost_errors.php" target="_blank">
        <i class="fa-solid fa-bug me-1"></i> Error Logs
      </a>
    </div>
    
    <!-- Filter Section -->
    <div class="mb-3">
    <div class="row g-2">
    <div class="col-md-2">
    <label class="form-label text-light">From Date</label>
    <input type="date" id="tcl_from" class="form-control" />
    </div>
    <div class="col-md-2">
    <label class="form-label text-light">To Date</label>
    <input type="date" id="tcl_to" class="form-control" />
    </div>
    <div class="col-md-3">
    <label class="form-label text-light">Company</label>
    <select id="tcl_company" class="form-select">
    <option value="">All Companies</option>
    </select>
    </div>
    <div class="col-md-3">
    <label class="form-label text-light">Job Classification</label>
    <select id="tcl_dept" class="form-select">
    <option value="">All Classifications</option>
    </select>
    </div>
    <div class="col-md-2">
    <label class="form-label text-light">Staff Name</label>
    <input type="text" id="tcl_staff" class="form-control" placeholder="Name" />
    </div>
    </div>
    </div>
    
    <!-- Embedded Time Cost Summary -->
    <div class="card mb-3" style="background: linear-gradient(135deg, rgba(0,81,153,0.12), rgba(0,198,255,0.06)); border: 1px solid rgba(0,114,255,0.12); border-radius: 10px;">
    <div class="card-header d-flex justify-content-between align-items-center" style="background: rgba(0,114,255,0.12); border-bottom: 1px solid rgba(0,114,255,0.2);">
    <h6 class="mb-0 text-info"><i class="fa-solid fa-table me-2"></i>Time Cost Summary</h6>
    <div class="d-flex gap-2">
    <a href="view_time_cost_errors.php" class="btn btn-sm btn-outline-danger" target="_blank"><i class="fa-solid fa-bug me-1"></i>Error Logs</a>
    <button class="btn btn-sm btn-outline-info" id="tcs_refresh"><i class="fa-solid fa-rotate me-1"></i>Refresh Summary</button>
    </div>
    </div>
    <div class="card-body">
    <div class="row g-2 mb-2">
    <div class="col-md-3">
    <label class="form-label text-light">From</label>
    <input type="date" id="tcs_from" class="form-control" />
    </div>
    <div class="col-md-3">
    <label class="form-label text-light">To</label>
    <input type="date" id="tcs_to" class="form-control" />
    </div>
    <div class="col-md-3">
    <label class="form-label text-light">Job Classification</label>
    <select id="tcs_dept" class="form-select"></select>
    </div>
    <div class="col-md-3">
    <label class="form-label text-light">Financial Year</label>
    <input type="number" id="tcs_year" class="form-control" min="2000" max="2099" />
    </div>
    </div>
    <div class="d-flex justify-content-end mb-2 small text-info">
    <span class="me-3">BF Qty: <strong id="tcs_bf_qty">0</strong></span>
    <span>BF Cost: <strong id="tcs_bf_cost">0.00</strong></span>
    </div>
    <div class="table-responsive" style="border:1px solid rgba(13,202,240,.2); border-radius:8px;">
    <table class="table table-sm table-dark mb-0">
    <thead>
    <tr>
    <th>Date</th><th>Type</th><th>Doc No.</th><th>Company</th><th>Staff</th><th>Year</th><th>Scope</th><th>In/Out Qty</th><th>Unit</th><th>Total Cost</th><th>B/F Qty</th><th>B/F Cost</th>
    </tr>
    </thead>
    <tbody id="tcs_body"></tbody>
    </table>
    </div>
    </div>
    </div>
    
    <!-- Time Cost List Table -->
    <div class="table-container">
      <table class="table table-hover table-striped" id="timeCostTable">
        <thead>
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Doc No.</th>
            <th>Company</th>
            <th>Staff</th>
            <th>Year</th>
            <th>Scope</th>
            <th>Hours</th>
            <th>Time Cost</th>
            <th>Total Cost</th>
            <th>Description</th>
            <th>Description 2</th>
          </tr>
        </thead>
        <tbody id="tcl_body">
          <tr>
            <td colspan="12" class="text-center text-muted py-4">
              <i class="fa-solid fa-inbox fa-3x mb-2" style="opacity: 0.3;"></i>
              <p>No time cost entries found. Click "Add Entry" to create one.</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- MANAGEMENT LETTER -->
  <div id="managementLetter" class="hidden">
    <!-- Page Header -->
    <div class="mb-4" style="background: linear-gradient(135deg, #1e3a8a, #3b82f6); padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);">
      <h2 class="fw-bold text-white mb-2" style="font-size: 28px; letter-spacing: 0.5px;">
        <i class="fa-solid fa-file-contract me-2"></i>Management Letter
      </h2>
      <p class="text-white mb-0" style="opacity: 0.9; font-size: 15px;">Review and export medium and high-risk items from Client Queries and Manager Reviews</p>
    </div>
    
    <!-- Filter Controls -->
    <div class="mb-4" style="background: #1a1d23; padding: 20px; border-radius: 10px; border: 1px solid #2d3748;">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label text-light fw-semibold mb-2" style="font-size: 14px;">
            <i class="fa-solid fa-filter me-1"></i>Risk Level Filter
          </label>
          <select id="filterMLRiskLevel" class="form-select" style="background: #2d3748; color: #fff; border: 1px solid #4a5568; font-size: 15px; padding: 10px;">
            <option value="Middle,High">Middle & High Risk</option>
            <option value="Middle">Middle Risk Only</option>
            <option value="High">High Risk Only</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label text-light fw-semibold mb-2" style="font-size: 14px;">
            <i class="fa-solid fa-building me-1"></i>Company Filter
          </label>
          <select id="filterMLCompany" class="form-select" style="background: #2d3748; color: #fff; border: 1px solid #4a5568; font-size: 15px; padding: 10px;">
            <option value="">All Companies</option>
            <!-- Will be populated dynamically -->
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button class="btn btn-primary w-100" onclick="loadManagementLetterData()" style="padding: 12px; font-size: 15px; font-weight: 600; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);">
            <i class="fa-solid fa-sync-alt me-2"></i>Apply Filter
          </button>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button class="btn btn-danger w-100" onclick="previewManagementLetter()" style="padding: 12px; font-size: 15px; font-weight: 600; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);">
            <i class="fa-solid fa-file-pdf me-2"></i>Preview & Export
          </button>
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
      <div class="col-md-4">
        <div class="card" style="background: linear-gradient(135deg, #dc2626, #991b1b); border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);">
          <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <h6 class="text-white mb-2" style="font-size: 14px; font-weight: 600; opacity: 0.9; letter-spacing: 0.5px; text-transform: uppercase;">
                  <i class="fa-solid fa-triangle-exclamation me-2"></i>High Risk Items
                </h6>
                <h2 id="mlHighRiskCount" class="text-white mb-0" style="font-size: 42px; font-weight: 700;">0</h2>
              </div>
              <div style="opacity: 0.2;">
                <i class="fa-solid fa-exclamation-triangle" style="font-size: 60px; color: white;"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card" style="background: linear-gradient(135deg, #f59e0b, #d97706); border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);">
          <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <h6 class="text-white mb-2" style="font-size: 14px; font-weight: 600; opacity: 0.9; letter-spacing: 0.5px; text-transform: uppercase;">
                  <i class="fa-solid fa-exclamation-circle me-2"></i>Middle Risk Items
                </h6>
                <h2 id="mlMiddleRiskCount" class="text-white mb-0" style="font-size: 42px; font-weight: 700;">0</h2>
              </div>
              <div style="opacity: 0.2;">
                <i class="fa-solid fa-exclamation-circle" style="font-size: 60px; color: white;"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);">
          <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <h6 class="text-white mb-2" style="font-size: 14px; font-weight: 600; opacity: 0.9; letter-spacing: 0.5px; text-transform: uppercase;">
                  <i class="fa-solid fa-building me-2"></i>Companies Affected
                </h6>
                <h2 id="mlCompaniesCount" class="text-white mb-0" style="font-size: 42px; font-weight: 700;">0</h2>
              </div>
              <div style="opacity: 0.2;">
                <i class="fa-solid fa-building" style="font-size: 60px; color: white;"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Client Queries Section -->
    <div class="card mb-4" style="background: white; border: 2px solid #3b82f6; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);">
      <div class="card-header" style="background: linear-gradient(135deg, #3b82f6, #2563eb); padding: 18px 24px; border: none;">
        <h5 class="mb-0 text-white" style="font-size: 18px; font-weight: 700; letter-spacing: 0.3px;">
          <i class="fa-solid fa-comments me-2"></i>Client Queries - Risk Items
        </h5>
      </div>
      <div class="card-body p-0">
        <div id="mlQueriesContent" class="table-responsive">
          <table class="table table-hover mb-0">
            <thead style="background: #f0f9ff;">
              <tr>
                <th style="padding: 16px 20px; font-size: 15px; font-weight: 700; color: #1e40af; border: none;">No</th>
                <th style="padding: 16px 20px; font-size: 15px; font-weight: 700; color: #1e40af; border: none;">Company</th>
                <th style="padding: 16px 20px; font-size: 15px; font-weight: 700; color: #1e40af; border: none;">Client Name</th>
                <th style="padding: 16px 20px; font-size: 15px; font-weight: 700; color: #1e40af; border: none;">Risk Level</th>
                <th style="padding: 16px 20px; font-size: 15px; font-weight: 700; color: #1e40af; border: none;">Type</th>
                <th style="padding: 16px 20px; font-size: 15px; font-weight: 700; color: #1e40af; border: none;">Date</th>
                <th style="padding: 16px 20px; font-size: 15px; font-weight: 700; color: #1e40af; border: none;">Q&A Summary</th>
                <th style="padding: 16px 20px; font-size: 15px; font-weight: 700; color: #1e40af; border: none;">Actions</th>
              </tr>
            </thead>
            <tbody id="mlQueriesTableBody" style="font-size: 15px;">
              <tr>
                <td colspan="8" class="text-center py-5" style="border: none; color: #64748b;">
                  <i class="fa-solid fa-inbox fa-3x mb-3" style="opacity: 0.4; color: #94a3b8;"></i>
                  <p class="mb-0" style="font-size: 16px; color: #475569; font-weight: 500;">No risk items found. Apply filter to load data.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Manager Reviews Section -->
    <div class="card mb-4" style="background: white; border: 2px solid #10b981; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);">
      <div class="card-header" style="background: linear-gradient(135deg, #10b981, #059669); padding: 18px 24px; border: none;">
        <h5 class="mb-0 text-white" style="font-size: 18px; font-weight: 700; letter-spacing: 0.3px;">
          <i class="fa-solid fa-clipboard-check me-2"></i>Manager Reviews - Risk Items
        </h5>
      </div>
      <div class="card-body p-0">
        <div id="mlReviewsContent" class="table-responsive">
          <table class="table table-hover mb-0">
            <thead style="background: #f0fdf4;">
              <tr>
                <th style="padding: 16px 20px; font-size: 15px; font-weight: 700; color: #065f46; border: none;">No</th>
                <th style="padding: 16px 20px; font-size: 15px; font-weight: 700; color: #065f46; border: none;">Company</th>
                <th style="padding: 16px 20px; font-size: 15px; font-weight: 700; color: #065f46; border: none;">Manager Name</th>
                <th style="padding: 16px 20px; font-size: 15px; font-weight: 700; color: #065f46; border: none;">Risk Level</th>
                <th style="padding: 16px 20px; font-size: 15px; font-weight: 700; color: #065f46; border: none;">Type</th>
                <th style="padding: 16px 20px; font-size: 15px; font-weight: 700; color: #065f46; border: none;">Date</th>
                <th style="padding: 16px 20px; font-size: 15px; font-weight: 700; color: #065f46; border: none;">Q&A Summary</th>
                <th style="padding: 16px 20px; font-size: 15px; font-weight: 700; color: #065f46; border: none;">Actions</th>
              </tr>
            </thead>
            <tbody id="mlReviewsTableBody" style="font-size: 15px;">
              <tr>
                <td colspan="8" class="text-center py-5" style="border: none; color: #64748b;">
                  <i class="fa-solid fa-inbox fa-3x mb-3" style="opacity: 0.4; color: #94a3b8;"></i>
                  <p class="mb-0" style="font-size: 16px; color: #475569; font-weight: 500;">No risk items found. Apply filter to load data.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Management Letter Preview Modal -->
<div class="modal fade" id="managementLetterModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content" style="background: #1a1d23; border: 2px solid #3498db;">
      <div class="modal-header" style="background: linear-gradient(135deg, #2c3e50, #3498db); border-bottom: 2px solid #3498db;">
        <h5 class="modal-title text-white"><i class="fa-solid fa-file-contract me-2"></i>Management Letter Preview</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div class="d-flex justify-content-end gap-2 p-3 bg-dark border-bottom border-secondary">
          <button class="btn btn-primary" onclick="printManagementLetter()">
            <i class="fa-solid fa-print"></i> Print
          </button>
          <button class="btn btn-danger" onclick="downloadManagementLetterPDF()">
            <i class="fa-solid fa-file-pdf"></i> Export to PDF
          </button>
        </div>
        <div id="managementLetterContent" style="background: white; padding: 40px; min-height: 500px;">
          <!-- Content will be loaded here -->
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Item Details Modal -->
<div class="modal fade" id="mlItemDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="background: white;">
      <div class="modal-header" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none;">
        <h5 class="modal-title">
          <i class="fa-solid fa-info-circle me-2"></i><span id="mlDetailsTitle">Item Details</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="mlDetailsContent" style="color: #1e293b; padding: 30px;">
        <!-- Content will be loaded dynamically -->
      </div>
      <div class="modal-footer" style="border-top: 1px solid #e5e7eb;">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fa-solid fa-times me-1"></i>Close
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Toast Notification Container -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 9999">
  <div id="successToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header bg-success text-white">
      <strong class="me-auto">Success</strong>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
    </div>
    <div class="toast-body bg-dark text-white">
      Operation completed successfully!
    </div>
  </div>
</div>

<!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content profile-modal-content">
      <div class="profile-modal-header">
        <h5 class="profile-modal-title"><i class="fa-solid fa-user"></i> My Profile</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="profile-modal-body">
        <div class="profile-tabs">
          <ul class="nav nav-tabs" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">Profile</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">Change Password</button>
            </li>
          </ul>
          
          <div class="profile-tab-content" id="profileTabsContent">
            <!-- Profile Tab -->
            <div class="tab-pane fade show active" id="profile" role="tabpanel">
              <form id="profileForm" enctype="multipart/form-data">
                <div class="current-picture">
                  <?php if (!empty($current_user['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($current_user['image_url']); ?>" alt="Profile" class="profile-picture-lg">
                  <?php else: ?>
                    <div class="profile-picture-placeholder-lg"><?php echo $initials; ?></div>
                  <?php endif; ?>
                  <small class="text-muted">Current Profile Picture</small>
                </div>
                
                <div class="mb-3">
                  <label for="profile_full_name" class="profile-form-label">Full Name</label>
                  <input type="text" class="form-control" id="profile_full_name" name="full_name" value="<?php echo htmlspecialchars($current_user['full_name']); ?>" required>
                </div>
                
                <div class="mb-3">
                  <label for="profile_email" class="profile-form-label">Email</label>
                  <input type="email" class="form-control" id="profile_email" name="email" value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                </div>
                
                <div class="mb-3">
                  <label for="profile_phone" class="profile-form-label">Phone</label>
                  <input type="text" class="form-control" id="profile_phone" name="phone" value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                  <label class="profile-form-label">Change Profile Picture</label>
                  <div class="d-flex align-items-center">
                    <div class="picker-card">
                      <div class="file-input-wrapper d-flex align-items-center">
                        <div class="file-input-btn" id="profile_pick_btn"><i class="fa-solid fa-upload"></i></div>
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
                      </div>
                      <span id="profile_picture_name" class="file-input-filename">No file chosen</span>
                      <span id="profile_picture_size" class="picker-filesize"></span>
                      <button class="picker-clear" id="profile_clear_btn" title="Remove selected file"><i class="fa-solid fa-xmark"></i></button>
                      <img id="profile_picture_preview" class="file-preview-img" src="" alt="" style="display:none;">
                    </div>
                  </div>
                  <div class="form-text text-info">Optional: Upload a new profile picture (JPG, PNG, GIF)</div>
                </div>
                
                <div class="d-grid">
                  <button type="submit" class="btn profile-submit-btn">Update Profile</button>
                </div>
              </form>
            </div>
            
            <!-- Change Password Tab -->
            <div class="tab-pane fade" id="password" role="tabpanel">
              <form id="changePasswordProfileForm">
                <div class="mb-3">
                  <label for="current_password" class="profile-form-label">Current Password</label>
                  <div class="password-input-group">
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                    <span class="password-toggle" onclick="togglePassword('current_password')">
                      <i class="fa-solid fa-eye"></i>
                    </span>
                  </div>
                </div>
                
                <div class="mb-3">
                  <label for="new_password_profile" class="profile-form-label">New Password</label>
                  <div class="password-input-group">
                    <input type="password" class="form-control" id="new_password_profile" name="new_password" required minlength="6">
                    <span class="password-toggle" onclick="togglePassword('new_password_profile')">
                      <i class="fa-solid fa-eye"></i>
                    </span>
                  </div>
                  <div class="form-text text-info">Password must be at least 6 characters long</div>
                </div>
                
                <div class="mb-3">
                  <label for="confirm_password_profile" class="profile-form-label">Confirm New Password</label>
                  <div class="password-input-group">
                    <input type="password" class="form-control" id="confirm_password_profile" name="confirm_password" required minlength="6">
                    <span class="password-toggle" onclick="togglePassword('confirm_password_profile')">
                      <i class="fa-solid fa-eye"></i>
                    </span>
                  </div>
                  <div id="password-match-profile" class="form-text"></div>
                </div>
                
                <div class="d-grid">
                  <button type="submit" class="btn profile-submit-btn" id="change-password-profile-btn" disabled>Change Password</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Notification Popup Container -->
<div id="notificationPopupContainer"></div>

<!-- Notification Center Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header" style="background: linear-gradient(90deg, #0072ff, #00c6ff); border-bottom: 2px solid #00c6ff;">
        <h5 class="modal-title"><i class="fa-solid fa-bell"></i> Notifications</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="max-height: 600px; overflow-y: auto;">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="text-info mb-0">All Notifications</h6>
          <button class="btn btn-sm btn-outline-info" id="markAllReadBtn">
            <i class="fa-solid fa-check-double"></i> Mark All Read
          </button>
        </div>
        
        <div id="notificationList">
          <?php if (count($allNotifications) > 0): ?>
            <?php foreach ($allNotifications as $notif): 
              $timeAgo = '';
              $timestamp = strtotime($notif['created_at']);
              $diff = time() - $timestamp;
              if ($diff < 60) $timeAgo = 'Just now';
              elseif ($diff < 3600) $timeAgo = floor($diff / 60) . ' minutes ago';
              elseif ($diff < 86400) $timeAgo = floor($diff / 3600) . ' hours ago';
              else $timeAgo = floor($diff / 86400) . ' days ago';
              
              $statusClass = '';
              $isFYE = $notif['type'] === 'financial_year_end';
              switch($notif['type']) {
                case 'Pending': $statusClass = 'status-pending'; break;
                case 'Reviewed': $statusClass = 'status-reviewed'; break;
                case 'Approved': 
                case 'Final Approved': $statusClass = 'status-approved'; break;
                case 'Rejected': $statusClass = 'status-rejected'; break;
              }
              
              // Special styling for FYE notifications
              if ($isFYE) {
                $bgColor = $notif['is_read'] ? 'linear-gradient(135deg, rgba(255, 152, 0, 0.1), rgba(255, 193, 7, 0.05))' : 'linear-gradient(135deg, rgba(255, 152, 0, 0.2), rgba(255, 193, 7, 0.15))';
                $borderColor = $notif['is_read'] ? '#cc7a00' : '#ff9800';
                $titleColor = '#ffc107';
              } else {
                $bgColor = $notif['is_read'] ? 'rgba(15, 27, 51, 0.3)' : 'rgba(0, 114, 255, 0.15)';
                $borderColor = $notif['is_read'] ? '#555' : '#0072ff';
                $titleColor = '#00c6ff';
              }
            ?>
            <div class="notification-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?> <?php echo $isFYE ? 'fye-notification' : ''; ?>" 
                 data-notification-id="<?php echo $notif['notification_id']; ?>"
                 data-document-id="<?php echo $notif['document_id'] ?? ''; ?>"
                 style="background: <?php echo $bgColor; ?>; 
                        padding: 15px; border-radius: 8px; margin-bottom: 10px; 
                        border-left: 4px solid <?php echo $borderColor; ?>; 
                        cursor: pointer; transition: all 0.2s ease;
                        <?php echo $isFYE ? 'box-shadow: 0 2px 10px rgba(255, 152, 0, 0.2);' : ''; ?>">
              <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                <div style="color: <?php echo $titleColor; ?>; font-weight: 600; font-size: 0.95rem;">
                  <?php if ($isFYE): ?>
                    <i class="fa-solid fa-calendar-check" style="margin-right: 8px; color: #ff9800;"></i>
                  <?php endif; ?>
                  <?php echo htmlspecialchars($notif['title']); ?>
                </div>
                <?php if (!$notif['is_read']): ?>
                <span class="badge <?php echo $isFYE ? 'bg-warning text-dark' : 'bg-primary'; ?>" style="font-size: 0.7rem;">NEW</span>
                <?php endif; ?>
              </div>
              <div style="color: <?php echo $isFYE ? '#ffffffff' : '#e0e0e0'; ?>; font-size: 0.9rem; margin-bottom: 8px; font-weight: <?php echo $isFYE ? '500' : 'normal'; ?>;">
                <?php echo htmlspecialchars($notif['message']); ?>
              </div>
              <div style="color: #aad4ff; font-size: 0.8rem; display: flex; align-items: center; gap: 5px;">
                <i class="fa-solid fa-clock"></i>
                <?php echo $timeAgo; ?>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="text-center text-muted py-5">
              <i class="fa-solid fa-bell-slash fa-3x mb-3" style="opacity: 0.3;"></i>
              <p>No notifications yet</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Company Modal -->
<div class="modal fade" id="addCompanyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); border: none; border-radius: 1rem; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
      <div class="modal-header" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); border: none; border-radius: 1rem 1rem 0 0; padding: 1.5rem 2rem;">
        <h5 class="modal-title text-white fw-bold" style="font-size: 1.4rem; letter-spacing: 0.5px;">
          <i class="fa-solid fa-building-circle-plus me-2"></i> Add New Company
          <span class="badge bg-light text-primary ms-2 px-3 py-1" style="font-size: 0.75rem; font-weight: 600;">
            <i class="fa-solid fa-plus me-1"></i> New Registration
          </span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="opacity: 0.9; filter: brightness(1.2);"></button>
      </div>
      <form id="addCompanyForm">
        <div class="modal-body text-light" style="padding: 2.5rem; background: rgba(255,255,255,0.02);">
          <!-- Company Basic Information -->
          <div class="form-section mb-5">
            <div class="section-header mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.1) 0%, rgba(13, 110, 253, 0.1) 100%); border-radius: 0.75rem; padding: 1.5rem; border: 1px solid rgba(13, 202, 240, 0.2);">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="section-title mb-0" style="color: #0dcaf0; font-weight: 700; font-size: 1.2rem; display: flex; align-items: center;">
                  <i class="fa-solid fa-building me-2" style="font-size: 1.1rem;"></i> Basic Information
              </h6>
                <span class="badge bg-primary-subtle text-primary-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                  <i class="fa-solid fa-asterisk me-1"></i> Required Fields
                </span>
              </div>
              <p class="text-light mb-0" style="font-size: 0.9rem; opacity: 0.8;">
                <i class="fa-solid fa-info-circle me-2"></i> Enter the primary company registration details
              </p>
            </div>
            
            <div class="row g-4">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="add_company_name" class="form-label d-flex justify-content-between align-items-center mb-2" 
                         style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                    <span>Company Name <span class="text-danger ms-1">*</span></span>
                    <i class="fa-solid fa-building-user text-info" style="font-size: 1rem;"></i>
                  </label>
                  <input type="text" class="form-control" id="add_company_name" name="company_name" 
                    style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;" 
                    placeholder="Enter registered company name"
                    required>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="form-group">
                  <label for="add_ssm_no" class="form-label d-flex justify-content-between align-items-center mb-2" 
                         style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                    <span>SSM Registration No <span class="text-danger ms-1">*</span></span>
                    <i class="fa-solid fa-id-card text-info" style="font-size: 1rem;"></i>
                  </label>
                  <input type="text" class="form-control" id="add_ssm_no" name="ssm_no" 
                    style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                    placeholder="Enter SSM registration number"
                    required>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="form-group mb-4">
                  <label for="add_company_type" class="form-label d-flex justify-content-between align-items-center mb-2" 
                         style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                    <span>Company Type <span class="text-danger ms-1">*</span></span>
                    <i class="fa-solid fa-sitemap text-info" style="font-size: 1rem;"></i>
                  </label>
                  <select class="form-select" id="add_company_type" name="company_type" required
                          style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;">
                    <option value="" disabled selected style="background: #1a1a2e; color: #adb5bd;">Select company type</option>
                    <option value="A" style="background: #1a1a2e; color: #ffffff;">A</option>
                    <option value="B" style="background: #1a1a2e; color: #ffffff;">B</option>
                    <option value="C" style="background: #1a1a2e; color: #ffffff;">C</option>
                  </select>
                </div>
              </div>

              <div class="col-md-6">
                <div class="form-group mb-4">
                  <label for="add_sub_type" class="form-label d-flex justify-content-between align-items-center mb-2" 
                         style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                    <span>Sub Type <span class="text-danger ms-1">*</span></span>
                    <i class="fa-solid fa-code-branch text-info" style="font-size: 1rem;"></i>
                  </label>
                  <select class="form-select" id="add_sub_type" name="sub_type" required
                          style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;">
                    <option value="" disabled selected style="background: #1a1a2e; color: #adb5bd;">Select business sub type</option>
                    <option value="SDN_BHD" style="background: #1a1a2e; color: #ffffff;">Sdn Bhd</option>
                    <option value="SOLE_PROPRIETOR" style="background: #1a1a2e; color: #ffffff;">Sole Proprietor</option>
                    <option value="Partnership" style="background: #1a1a2e; color: #ffffff;">Partnership</option>
                    <option value="LLP" style="background: #1a1a2e; color: #ffffff;">Limited Liability Partnership (LLP)</option>
                    <option value="BERHAD" style="background: #1a1a2e; color: #ffffff;">Berhad</option>
                  </select>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="form-group mb-4">
                  <label for="add_incorporation_date" class="form-label d-flex justify-content-between align-items-center mb-2" 
                         style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                    <span>Incorporation Date <span class="text-danger ms-1">*</span></span>
                    <i class="fa-solid fa-calendar-plus text-info" style="font-size: 1rem;"></i>
                  </label>
                  <input type="date" class="form-control" id="add_incorporation_date" name="incorporation_date" 
                         style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                         required>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="form-group mb-4">
                  <label for="add_financial_year_end" class="form-label d-flex justify-content-between align-items-center mb-2" 
                         style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                    <span>Financial Year End <span class="text-danger ms-1">*</span></span>
                    <i class="fa-solid fa-calendar-check text-info" style="font-size: 1rem;"></i>
                  </label>
                  <input type="date" class="form-control" id="add_financial_year_end" name="financial_year_end" 
                         style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                         required onchange="autoCalculateSubsequentYearEnd('add')">
                  <small class="form-text text-info mt-2" style="font-size: 0.8rem; opacity: 0.8;">
                    <i class="fa-solid fa-info-circle me-1"></i> Must be within 18 months of incorporation date
                  </small>
                </div>
                <div class="form-group mb-4">
                  <label for="add_subsequent_year_end" class="form-label d-flex justify-content-between align-items-center mb-2" 
                         style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                    <span>Subsequent Year End</span>
                    <i class="fa-solid fa-calendar-days text-info" style="font-size: 1rem;"></i>
                  </label>
                  <input type="date" class="form-control" id="add_subsequent_year_end" name="subsequent_year_end" 
                         style="background: rgba(255, 255, 255, 0.05); border: 2px solid rgba(13, 202, 240, 0.2); color: #adb5bd; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem;"
                         readonly tabindex="-1">
                  <small class="form-text text-info mt-2" style="font-size: 0.8rem; opacity: 0.8;">
                    <i class="fa-solid fa-info-circle me-1"></i> Automatically set to one year after Financial Year End
                  </small>
                </div>
              </div>
            </div>
            
            <div class="col-12">
              <div class="form-group mb-4">
                <label for="add_nature_of_business" class="form-label d-flex justify-content-between align-items-center mb-2" 
                       style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                  <span>Nature of Business <span class="text-danger ms-1">*</span></span>
                  <i class="fa-solid fa-briefcase text-info" style="font-size: 1rem;"></i>
                </label>
                <input type="text" class="form-control" id="add_nature_of_business" name="nature_of_business" 
                       style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                       placeholder="Enter the primary business activity"
                       required>
              </div>
              
              <!-- MSIC Codes Section -->
              <div class="form-section mb-5">
                <div class="section-header mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.1) 0%, rgba(13, 110, 253, 0.1) 100%); border-radius: 0.75rem; padding: 1.5rem; border: 1px solid rgba(13, 202, 240, 0.2);">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="section-title mb-0" style="color: #0dcaf0; font-weight: 700; font-size: 1.2rem; display: flex; align-items: center;">
                      <i class="fa-solid fa-tags me-2" style="font-size: 1.1rem;"></i> MSIC Codes
                  </h6>
                    <span class="badge bg-info-subtle text-info-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                      <i class="fa-solid fa-hashtag me-1"></i> 1-3 Codes
                    </span>
                  </div>
                  <p class="text-light mb-0" style="font-size: 0.9rem; opacity: 0.8;">
                    <i class="fa-solid fa-info-circle me-2"></i> Select up to 3 MSIC codes that best describe your business activities
                  </p>
                </div>
                
                <!-- MSIC Codes Cards Container -->
                <div class="msic-codes-container">
                  <!-- MSIC Code 1 -->
                  <div class="card mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.08) 0%, rgba(13, 110, 253, 0.08) 100%); border: 2px solid rgba(13, 202, 240, 0.3); border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    <div class="card-body p-4">
                      <div class="d-flex justify-content-between align-items-center mb-3">
                        <label for="add_msic_code_1" class="form-label mb-0" style="font-size: 1rem; color: #0dcaf0; font-weight: 600;">
                          <i class="fa-solid fa-hashtag me-2"></i> Primary MSIC Code <span class="text-danger ms-1">*</span>
                        </label>
                        <span class="badge bg-primary-subtle text-primary-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                          <i class="fa-solid fa-star me-1"></i> Required
                        </span>
                      </div>
                      <div class="input-group">
                        <input type="text" class="form-control msic-autocomplete" id="add_msic_code_1" name="msic_code_1" 
                               style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                               placeholder="Enter MSIC code..." required autocomplete="off">
                        <button class="btn btn-outline-info" type="button" onclick="clearMsicCode('1')" title="Clear MSIC code" style="border-radius: 0 0.75rem 0.75rem 0; border: 2px solid rgba(13, 202, 240, 0.3); background: rgba(13, 202, 240, 0.1); color: #0dcaf0;">
                          <i class="fa-solid fa-xmark"></i>
                        </button>
                      </div>
                      <input type="hidden" id="add_msic_desc_1" name="msic_desc_1">
                      <div id="add_msic_desc_display_1" class="text-info mt-3 p-3 rounded" 
                           style="font-size: 0.9rem; min-height: 30px; background: rgba(13, 202, 240, 0.15); border: 1px solid rgba(13, 202, 240, 0.2); border-radius: 0.5rem;"></div>
                    </div>
                  </div>
                  
                  <!-- MSIC Code 2 -->
                  <div class="card mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.08) 0%, rgba(13, 110, 253, 0.08) 100%); border: 2px solid rgba(13, 202, 240, 0.3); border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    <div class="card-body p-4">
                      <div class="d-flex justify-content-between align-items-center mb-3">
                        <label for="add_msic_code_2" class="form-label mb-0" style="font-size: 1rem; color: #0dcaf0; font-weight: 600;">
                          <i class="fa-solid fa-hashtag me-2"></i> Secondary MSIC Code
                        </label>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                          <i class="fa-solid fa-plus me-1"></i> Optional
                        </span>
                      </div>
                      <div class="input-group">
                        <input type="text" class="form-control msic-autocomplete" id="add_msic_code_2" name="msic_code_2" 
                               style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                               placeholder="Enter MSIC code..." autocomplete="off">
                        <button class="btn btn-outline-info" type="button" onclick="clearMsicCode('2')" title="Clear MSIC code" style="border-radius: 0 0.75rem 0.75rem 0; border: 2px solid rgba(13, 202, 240, 0.3); background: rgba(13, 202, 240, 0.1); color: #0dcaf0;">
                          <i class="fa-solid fa-xmark"></i>
                        </button>
                      </div>
                      <input type="hidden" id="add_msic_desc_2" name="msic_desc_2">
                      <div id="add_msic_desc_display_2" class="text-info mt-3 p-3 rounded" 
                           style="font-size: 0.9rem; min-height: 30px; background: rgba(13, 202, 240, 0.15); border: 1px solid rgba(13, 202, 240, 0.2); border-radius: 0.5rem;"></div>
                    </div>
                  </div>
                  
                  <!-- MSIC Code 3 -->
                  <div class="card mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.08) 0%, rgba(13, 110, 253, 0.08) 100%); border: 2px solid rgba(13, 202, 240, 0.3); border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    <div class="card-body p-4">
                      <div class="d-flex justify-content-between align-items-center mb-3">
                        <label for="add_msic_code_3" class="form-label mb-0" style="font-size: 1rem; color: #0dcaf0; font-weight: 600;">
                          <i class="fa-solid fa-hashtag me-2"></i> Additional MSIC Code
                        </label>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                          <i class="fa-solid fa-plus me-1"></i> Optional
                        </span>
                      </div>
                      <div class="input-group">
                        <input type="text" class="form-control msic-autocomplete" id="add_msic_code_3" name="msic_code_3" 
                               style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                               placeholder="Enter MSIC code..." autocomplete="off">
                        <button class="btn btn-outline-info" type="button" onclick="clearMsicCode('3')" title="Clear MSIC code" style="border-radius: 0 0.75rem 0.75rem 0; border: 2px solid rgba(13, 202, 240, 0.3); background: rgba(13, 202, 240, 0.1); color: #0dcaf0;">
                          <i class="fa-solid fa-xmark"></i>
                        </button>
                      </div>
                      <input type="hidden" id="add_msic_desc_3" name="msic_desc_3">
                      <div id="add_msic_desc_display_3" class="text-info mt-3 p-3 rounded" 
                           style="font-size: 0.9rem; min-height: 30px; background: rgba(13, 202, 240, 0.15); border: 1px solid rgba(13, 202, 240, 0.2); border-radius: 0.5rem;"></div>
                    </div>
                  </div>
                </div>
                
                <input type="hidden" id="add_msic_code" name="msic_code" value="">
              </div>
              
              <div class="form-group mb-4">
                <label for="add_description" class="form-label d-flex justify-content-between align-items-center mb-2" 
                       style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                  <span>Business Description</span>
                  <i class="fa-solid fa-align-left text-info" style="font-size: 1rem;"></i>
                </label>
                <textarea class="form-control" id="add_description" name="description" rows="4"
                          style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease; resize: vertical;"
                          placeholder="Provide a detailed description of the business activities, products, or services..."></textarea>
              </div>
              
              <!-- Contact Information Card -->
              <div class="card mb-5" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.08) 0%, rgba(13, 110, 253, 0.08) 100%); border: 2px solid rgba(13, 202, 240, 0.3); border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <div class="card-body p-4">
                  <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="card-title mb-0" style="color: #0dcaf0; font-size: 1.2rem; font-weight: 700;">
                    <i class="fa-solid fa-address-card me-2"></i> Contact Details
                  </h6>
                    <span class="badge bg-info-subtle text-info-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                      <i class="fa-solid fa-phone me-1"></i> Communication
                    </span>
                  </div>
                  
                  <div class="row g-4">
                    <div class="col-md-6">
                      <div class="form-group mb-4">
                        <label for="add_email" class="form-label d-flex justify-content-between align-items-center mb-2" 
                               style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                          <span>Email Address <span class="text-danger ms-1">*</span></span>
                          <i class="fa-solid fa-envelope text-info" style="font-size: 1rem;"></i>
                    </label>
                        <input type="email" class="form-control" id="add_email" name="email" 
                               style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                           placeholder="Enter company email address"
                           required>
                      </div>
                  </div>
                  
                    <div class="col-md-6">
                      <div class="form-group mb-4">
                        <label for="add_office_no" class="form-label d-flex justify-content-between align-items-center mb-2" 
                               style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                      <span>Office Phone</span>
                          <i class="fa-solid fa-phone text-info" style="font-size: 1rem;"></i>
                    </label>
                        <input type="text" class="form-control" id="add_office_no" name="office_no" 
                               style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                           placeholder="Enter office contact number">
                      </div>
                  </div>
                  
                    <div class="col-md-6">
                      <div class="form-group mb-4">
                        <label for="add_fax_no" class="form-label d-flex justify-content-between align-items-center mb-2" 
                               style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                      <span>Fax Number</span>
                          <i class="fa-solid fa-fax text-info" style="font-size: 1rem;"></i>
                    </label>
                        <input type="text" class="form-control" id="add_fax_no" name="fax_no" 
                               style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                           placeholder="Enter fax number">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Address Section -->
          <div class="form-section mb-5">
            <div class="section-header mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.1) 0%, rgba(13, 110, 253, 0.1) 100%); border-radius: 0.75rem; padding: 1.5rem; border: 1px solid rgba(13, 202, 240, 0.2);">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="section-title mb-0" style="color: #0dcaf0; font-weight: 700; font-size: 1.2rem; display: flex; align-items: center;">
                  <i class="fa-solid fa-location-dot me-2" style="font-size: 1.1rem;"></i> Company Addresses
              </h6>
                <span class="badge bg-info-subtle text-info-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                  <i class="fa-solid fa-map-marker-alt me-1"></i> Location Details
                </span>
              </div>
              <p class="text-light mb-0" style="font-size: 0.9rem; opacity: 0.8;">
                <i class="fa-solid fa-info-circle me-2"></i> Official addresses for company registration and operations
              </p>
            </div>

            <div class="row g-4">
              <!-- Registered Address -->
              <div class="col-12">
                <div class="card mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.08) 0%, rgba(13, 110, 253, 0.08) 100%); border: 2px solid rgba(13, 202, 240, 0.3); border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                  <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <label for="add_address" class="form-label mb-0" style="font-size: 1rem; color: #0dcaf0; font-weight: 600;">
                        <i class="fa-solid fa-building-circle-check me-2"></i> Registered Address <span class="text-danger ms-1">*</span>
                      </label>
                      <span class="badge bg-primary-subtle text-primary-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                        <i class="fa-solid fa-certificate me-1"></i> Official Address
                      </span>
                    </div>
                    <textarea class="form-control" id="add_address" name="address" rows="4" required
                              style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease; resize: vertical;"
                              placeholder="Enter the official registered address..."></textarea>
                    <small class="form-text text-info mt-3 d-block" style="font-size: 0.8rem; opacity: 0.8;">
                      <i class="fa-solid fa-info-circle me-1"></i> This address will be used for official documentation
                    </small>
                  </div>
                </div>
              </div>

              <!-- Business Address -->
              <div class="col-12">
                <div class="card mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.08) 0%, rgba(13, 110, 253, 0.08) 100%); border: 2px solid rgba(13, 202, 240, 0.3); border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                  <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <label for="add_business_address" class="form-label mb-0" style="font-size: 1rem; color: #0dcaf0; font-weight: 600;">
                        <i class="fa-solid fa-store me-2"></i> Business Address
                      </label>
                      <span class="badge bg-secondary-subtle text-secondary-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                        <i class="fa-solid fa-building me-1"></i> Operational Address
                      </span>
                    </div>
                    
                    <div class="form-check mb-4" style="background: rgba(13, 202, 240, 0.15); padding: 1rem; border-radius: 0.75rem; border: 1px solid rgba(13, 202, 240, 0.2);">
                      <input class="form-check-input" type="checkbox" id="add_same_as_registered" style="margin-top: 0.25rem;" />
                      <label class="form-check-label" for="add_same_as_registered" style="font-size: 0.9rem; color: #e9ecef; font-weight: 500;">
                        <i class="fa-solid fa-copy me-2"></i> Same as Registered Address
                      </label>
                    </div>
                    
                    <textarea class="form-control" id="add_business_address" name="business_address" rows="4"
                              style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease; resize: vertical;"
                              placeholder="Enter the business operational address..."></textarea>
                    <small class="form-text text-info mt-3 d-block" style="font-size: 0.8rem; opacity: 0.8;">
                      <i class="fa-solid fa-info-circle me-1"></i> Address where business operations are conducted
                    </small>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Contact Information Section -->
          <div class="form-section mb-4">
            <div class="section-header mb-4 pb-2 border-bottom border-secondary">
              <h6 class="section-title mb-1" style="color: #0dcaf0; font-weight: 600; font-size: 1.1rem; display: flex; align-items: center;">
                <i class="fa-solid fa-address-card me-2"></i> Contact Information
                <span class="ms-2 badge bg-info-subtle text-info-emphasis px-2" style="font-size: 0.7rem;">Key Personnel</span>
              </h6>
              <p class="text-muted mb-0" style="font-size: 0.85rem;">
                <i class="fa-solid fa-info-circle me-1"></i> Contact details for important company representatives
              </p>
            </div>

            <div class="row g-4">
              <!-- Accountant Contact Card -->
              <div class="col-md-6">
                <div class="card h-100" style="background: rgba(13, 110, 253, 0.05); border: 1px solid rgba(13, 202, 240, 0.2);">
                  <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <h6 class="card-title mb-0" style="color: #0dcaf0; font-size: 1rem;">
                        <i class="fa-solid fa-calculator me-2"></i> Accountant Contact
                      </h6>
                      <span class="badge bg-info-subtle text-info-emphasis px-2 py-1" style="font-size: 0.7rem;">
                        <i class="fa-solid fa-file-invoice-dollar me-1"></i> Financial Contact
                      </span>
                    </div>
                    
                    <div class="contact-fields">
                      <div class="mb-3">
                        <label for="add_accountant_name" class="form-label d-flex justify-content-between align-items-center"
                               style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">
                          <span>Accountant Name</span>
                          <i class="fa-solid fa-user text-info-emphasis" style="font-size: 0.9rem;"></i>
                        </label>
                        <input type="text" class="form-control custom-input" id="add_accountant_name" name="accountant_name"
                               style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 202, 240, 0.2); color: #e9ecef; padding: 0.7rem 1rem;"
                               placeholder="Enter accountant's full name">
                      </div>
                      
                      <div class="mb-3">
                        <label for="add_accountant_phone" class="form-label d-flex justify-content-between align-items-center"
                               style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">
                          <span>Phone Number</span>
                          <i class="fa-solid fa-phone text-info-emphasis" style="font-size: 0.9rem;"></i>
                        </label>
                        <input type="tel" class="form-control custom-input" id="add_accountant_phone" name="accountant_phone"
                               style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 202, 240, 0.2); color: #e9ecef; padding: 0.7rem 1rem;"
                               placeholder="Enter contact number">
                      </div>
                      
                      <div class="mb-3">
                        <label for="add_accountant_email" class="form-label d-flex justify-content-between align-items-center"
                               style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">
                          <span>Email Address</span>
                          <i class="fa-solid fa-envelope text-info-emphasis" style="font-size: 0.9rem;"></i>
                        </label>
                        <input type="email" class="form-control custom-input" id="add_accountant_email" name="accountant_email"
                               style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 202, 240, 0.2); color: #e9ecef; padding: 0.7rem 1rem;"
                               placeholder="Enter email address">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- HR Contact Card -->
              <div class="col-md-6">
                <div class="card h-100" style="background: rgba(13, 110, 253, 0.05); border: 1px solid rgba(13, 202, 240, 0.2);">
                  <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <h6 class="card-title mb-0" style="color: #0dcaf0; font-size: 1rem;">
                        <i class="fa-solid fa-users me-2"></i> HR Contact
                      </h6>
                      <span class="badge bg-info-subtle text-info-emphasis px-2 py-1" style="font-size: 0.7rem;">
                        <i class="fa-solid fa-user-tie me-1"></i> Personnel Contact
                      </span>
                    </div>
                    
                    <div class="contact-fields">
                      <div class="mb-3">
                        <label for="add_hr_name" class="form-label d-flex justify-content-between align-items-center"
                               style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">
                          <span>HR Representative</span>
                          <i class="fa-solid fa-user text-info-emphasis" style="font-size: 0.9rem;"></i>
                        </label>
                        <input type="text" class="form-control custom-input" id="add_hr_name" name="hr_name"
                               style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 202, 240, 0.2); color: #e9ecef; padding: 0.7rem 1rem;"
                               placeholder="Enter HR representative's name">
                      </div>
                      
                      <div class="mb-3">
                        <label for="add_hr_phone" class="form-label d-flex justify-content-between align-items-center"
                               style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">
                          <span>Phone Number</span>
                          <i class="fa-solid fa-phone text-info-emphasis" style="font-size: 0.9rem;"></i>
                        </label>
                        <input type="tel" class="form-control custom-input" id="add_hr_phone" name="hr_phone"
                               style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 202, 240, 0.2); color: #e9ecef; padding: 0.7rem 1rem;"
                               placeholder="Enter contact number">
                      </div>
                      
                      <div class="mb-3">
                        <label for="add_hr_email" class="form-label d-flex justify-content-between align-items-center"
                               style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">
                          <span>Email Address</span>
                          <i class="fa-solid fa-envelope text-info-emphasis" style="font-size: 0.9rem;"></i>
                        </label>
                        <input type="email" class="form-control custom-input" id="add_hr_email" name="hr_email"
                               style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 202, 240, 0.2); color: #e9ecef; padding: 0.7rem 1rem;"
                               placeholder="Enter email address">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.1) 0%, rgba(13, 110, 253, 0.1) 100%); border-top: 2px solid rgba(13, 202, 240, 0.2); padding: 2rem; border-radius: 0 0 1rem 1rem;">
          <div class="d-flex justify-content-between align-items-center w-100">
            <div class="d-flex align-items-center">
              <i class="fa-solid fa-circle-info text-info me-2" style="font-size: 1rem;"></i>
              <small class="text-light" style="font-size: 0.9rem; opacity: 0.8;">
                Review all information before submitting
            </small>
            </div>
            <div class="button-group d-flex gap-3">
              <button type="button" class="btn btn-outline-light px-4 py-2" data-bs-dismiss="modal" style="border-radius: 0.75rem; font-weight: 600; border: 2px solid rgba(255, 255, 255, 0.3); transition: all 0.3s ease;">
                <i class="fa-solid fa-xmark me-2"></i> Cancel
              </button>
              <button type="submit" class="btn btn-primary px-5 py-2" id="addCompanyBtn" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); border: none; border-radius: 0.75rem; font-weight: 600; box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3); transition: all 0.3s ease;">
                <span class="d-flex align-items-center">
                  <i class="fa-solid fa-building-circle-check me-2"></i>
                  <span class="button-text">Add Company</span>
                  <span class="spinner-border spinner-border-sm ms-2 d-none" role="status"></span>
                </span>
              </button>
            </div>
          </div>
        </div>

        <script>
          document.getElementById('addCompanyForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('addCompanyBtn');
            const spinner = btn.querySelector('.spinner-border');
            const buttonText = btn.querySelector('.button-text');
            
            // Show loading state
            spinner.classList.remove('d-none');
            buttonText.textContent = 'Adding...';
            btn.disabled = true;
          });
        </script>
      </form>
    </div>
  </div>
</div>

<?php if ($isAdmin): ?>
<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-user-plus"></i> Add New User</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="addUserForm" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="mb-3">
            <label for="add_full_name" class="form-label">Full Name</label>
            <input type="text" class="form-control" id="add_full_name" name="full_name" required>
          </div>
          <div class="mb-3">
            <label for="add_email" class="form-label">Email</label>
            <input type="email" class="form-control" id="add_email" name="email" required>
          </div>
          <div class="mb-3">
            <label for="add_phone" class="form-label">Phone</label>
            <input type="text" class="form-control" id="add_phone" name="phone">
          </div>
          <div class="mb-3">
            <label for="add_password" class="form-label">Password</label>
            <div class="password-input-group">
              <input type="password" class="form-control" id="add_password" name="password" required>
              <span class="password-toggle" onclick="togglePassword('add_password')">
                <i class="fa-solid fa-eye"></i>
              </span>
            </div>
          </div>
          <div class="mb-3">
            <label for="add_role" class="form-label">Role</label>
            <select class="form-control" id="add_role" name="role" required>
              <option value="employee">Employee</option>
              <option value="manager">Manager</option>
              <option value="accountant">Accountant</option>
              <option value="auditor">Auditor</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Profile Picture</label>
            <div class="profile-upload-container">
              <div class="profile-upload-circle" onclick="document.getElementById('add_profile_picture').click()">
                <div class="profile-upload-preview" id="add_user_preview_container">
                  <div class="profile-upload-placeholder">
                    <i class="fa-solid fa-user"></i>
                  </div>
                </div>
                <div class="profile-upload-overlay">
                  <i class="fa-solid fa-camera"></i>
                </div>
              </div>
              <input type="file" id="add_profile_picture" name="profile_picture" accept="image/*" class="profile-upload-input">
              <div class="profile-upload-info">Click to upload profile picture</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-user-shield"></i> Add New Admin</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="addAdminForm" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="mb-3">
            <label for="add_admin_full_name" class="form-label">Full Name</label>
            <input type="text" class="form-control" id="add_admin_full_name" name="full_name" required>
          </div>
          <div class="mb-3">
            <label for="add_admin_email" class="form-label">Email</label>
            <input type="email" class="form-control" id="add_admin_email" name="email" required>
          </div>
          <div class="mb-3">
            <label for="add_admin_phone" class="form-label">Phone</label>
            <input type="text" class="form-control" id="add_admin_phone" name="phone">
          </div>
          <div class="mb-3">
            <label for="add_admin_password" class="form-label">Password</label>
            <div class="password-input-group">
              <input type="password" class="form-control" id="add_admin_password" name="password" required>
              <span class="password-toggle" onclick="togglePassword('add_admin_password')">
                <i class="fa-solid fa-eye"></i>
              </span>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Profile Picture</label>
            <div class="profile-upload-container">
              <div class="profile-upload-circle" onclick="document.getElementById('add_admin_profile_picture').click()">
                <div class="profile-upload-preview" id="add_admin_preview_container">
                  <div class="profile-upload-placeholder">
                    <i class="fa-solid fa-user-shield"></i>
                  </div>
                </div>
                <div class="profile-upload-overlay">
                  <i class="fa-solid fa-camera"></i>
                </div>
              </div>
              <input type="file" id="add_admin_profile_picture" name="profile_picture" accept="image/*" class="profile-upload-input">
              <div class="profile-upload-info">Click to upload profile picture</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Admin</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-user-edit"></i> Edit User</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="editUserForm" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" id="edit_user_id" name="user_id">
          <div class="mb-3">
            <label class="form-label">Profile Picture</label>
            <div class="profile-upload-container">
              <div class="profile-upload-circle" onclick="document.getElementById('edit_profile_picture').click()">
                <div class="profile-upload-preview" id="edit_user_preview_container">
                  <div class="profile-upload-placeholder" id="edit_user_current_picture">
                    <i class="fa-solid fa-user"></i>
                  </div>
                </div>
                <div class="profile-upload-overlay">
                  <i class="fa-solid fa-camera"></i>
                </div>
              </div>
              <input type="file" id="edit_profile_picture" name="profile_picture" accept="image/*" class="profile-upload-input">
              <div class="profile-upload-info">Click to change profile picture</div>
            </div>
          </div>
          <div class="mb-3">
            <label for="edit_full_name" class="form-label">Full Name</label>
            <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
          </div>
          <div class="mb-3">
            <label for="edit_email" class="form-label">Email</label>
            <input type="email" class="form-control" id="edit_email" name="email" required>
          </div>
          <div class="mb-3">
            <label for="edit_phone" class="form-label">Phone</label>
            <input type="text" class="form-control" id="edit_phone" name="phone">
          </div>
          <div class="mb-3">
            <label for="edit_role" class="form-label">Role</label>
            <select class="form-control" id="edit_role" name="role" required>
              <option value="employee">Employee</option>
              <option value="manager">Manager</option>
              <option value="accountant">Accountant</option>
              <option value="auditor">Auditor</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Change User Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-key"></i> Change Password for <span id="password-user-name"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="changePasswordForm">
        <div class="modal-body">
          <input type="hidden" id="password_user_id" name="user_id">
          <div class="mb-3">
            <label for="new_password" class="form-label">New Password</label>
            <div class="password-input-group">
              <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
              <span class="password-toggle" onclick="togglePassword('new_password')">
                <i class="fa-solid fa-eye"></i>
              </span>
            </div>
            <div class="form-text text-info">Password must be at least 6 characters long</div>
          </div>
          <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <div class="password-input-group">
              <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
              <span class="password-toggle" onclick="togglePassword('confirm_password')">
                <i class="fa-solid fa-eye"></i>
              </span>
            </div>
            <div id="password-match" class="form-text"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="change-password-btn" disabled>Change Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-user-shield"></i> Edit Admin</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="editAdminForm" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" id="edit_admin_id" name="admin_id">
          <div class="mb-3">
            <label class="form-label">Profile Picture</label>
            <div class="profile-upload-container">
              <div class="profile-upload-circle" onclick="document.getElementById('edit_admin_profile_picture').click()">
                <div class="profile-upload-preview" id="edit_admin_preview_container">
                  <div class="profile-upload-placeholder" id="edit_admin_current_picture">
                    <i class="fa-solid fa-user-shield"></i>
                  </div>
                </div>
                <div class="profile-upload-overlay">
                  <i class="fa-solid fa-camera"></i>
                </div>
              </div>
              <input type="file" id="edit_admin_profile_picture" name="profile_picture" accept="image/*" class="profile-upload-input">
              <div class="profile-upload-info">Click to change profile picture</div>
            </div>
          </div>
          <div class="mb-3">
            <label for="edit_admin_full_name" class="form-label">Full Name</label>
            <input type="text" class="form-control" id="edit_admin_full_name" name="full_name" required>
          </div>
          <div class="mb-3">
            <label for="edit_admin_email" class="form-label">Email</label>
            <input type="email" class="form-control" id="edit_admin_email" name="email" required>
          </div>
          <div class="mb-3">
            <label for="edit_admin_phone" class="form-label">Phone</label>
            <input type="text" class="form-control" id="edit_admin_phone" name="phone">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Add Document Modal -->
<div class="modal fade" id="addDocumentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content bg-dark text-light" style="border-radius: 12px; border: 1px solid rgba(13, 110, 253, 0.3);">
      <div class="modal-header" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); border-bottom: 2px solid rgba(13, 110, 253, 0.5); border-radius: 12px 12px 0 0; padding: 1.25rem 1.5rem;">
        <h5 class="modal-title fw-bold" style="font-size: 1.25rem; letter-spacing: 0.3px;">
          <i class="fa-solid fa-file-circle-plus me-2"></i> Collect New Document
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="opacity: 0.9;"></button>
      </div>
      <form id="addDocumentForm" enctype="multipart/form-data">
        <div class="modal-body" style="padding: 2rem 1.5rem; background-color: #1a1d29;">
          <div class="row g-4">
            <!-- Left Column -->
            <div class="col-md-6">
              <!-- Company Information Section -->
              <div class="mb-4">
                <div class="mb-3">
                  <label for="add_doc_company" class="form-label fw-semibold" style="color: #e9ecef; font-size: 0.95rem; margin-bottom: 0.6rem;">
                    <i class="fa-solid fa-building me-1" style="color: #0d6efd;"></i> Company Name <span style="color: #dc3545;">*</span>
                  </label>
                  <select class="form-control" id="add_doc_company" name="company_id" required 
                    style="background-color: #2b3035; border: 1.5px solid #0d6efd; color: #fff; padding: 0.65rem 0.85rem; border-radius: 8px; font-size: 0.95rem; transition: all 0.3s ease;">
                    <option value="">-- Select Company --</option>
                    <?php foreach ($companies as $c): ?>
                    <option value="<?php echo $c['company_id']; ?>"><?php echo htmlspecialchars($c['company_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <!-- Document Details Section -->
              <div class="mb-4">
                <div class="mb-3">
                  <label for="add_doc_title" class="form-label fw-semibold" style="color: #e9ecef; font-size: 0.95rem; margin-bottom: 0.6rem;">
                    <i class="fa-solid fa-file-signature me-1" style="color: #0d6efd;"></i> Document Name/Title <span style="color: #dc3545;">*</span>
                  </label>
                  <input type="text" class="form-control" id="add_doc_title" name="document_title" required 
                    style="background-color: #2b3035; border: 1.5px solid #0d6efd; color: #fff; padding: 0.65rem 0.85rem; border-radius: 8px; font-size: 0.95rem; transition: all 0.3s ease;">
                </div>
              </div>

              <!-- Source & Type Section -->
              <div class="mb-4">
                <div class="mb-3">
                  <label for="add_doc_source_type" class="form-label fw-semibold" style="color: #e9ecef; font-size: 0.95rem; margin-bottom: 0.6rem;">
                    <i class="fa-solid fa-share-nodes me-1" style="color: #0d6efd;"></i> Source Type <span style="color: #dc3545;">*</span>
                  </label>
                  <select class="form-control" id="add_doc_source_type" name="source_type" required 
                    style="background-color: #2b3035; border: 1.5px solid #0d6efd; color: #fff; padding: 0.65rem 0.85rem; border-radius: 8px; font-size: 0.95rem; transition: all 0.3s ease;">
                    <option value="">-- Select Source Type --</option>
                    <option value="Supplier">Supplier</option>
                    <option value="Customer">Customer</option>
                    <option value="Bank">Bank</option>
                    <option value="Government">Government Agency</option>
                    <option value="Client">Client Themselves</option>
                  </select>
                  <div class="form-text mt-2" style="color: #6ea8fe; font-size: 0.85rem; display: flex; align-items: center;">
                    <i class="fa-solid fa-circle-info me-1"></i> Select source type to show required document uploads
                  </div>
                </div>
              </div>

              <div class="mb-4">
                <div class="mb-3">
                  <label for="add_doc_type" class="form-label fw-semibold" style="color: #e9ecef; font-size: 0.95rem; margin-bottom: 0.6rem;">
                    <i class="fa-solid fa-tags me-1" style="color: #0d6efd;"></i> Document Type <span style="color: #dc3545;">*</span>
                  </label>
                  <select class="form-control" id="add_doc_type" name="document_type" required 
                    style="background-color: #2b3035; border: 1.5px solid #0d6efd; color: #fff; padding: 0.65rem 0.85rem; border-radius: 8px; font-size: 0.95rem; transition: all 0.3s ease;">
                    <option value="">-- Select Type --</option>
                    <option value="Sales">Sales</option>
                    <option value="Receiving">Receiving</option>
                    <option value="Purchase">Purchase</option>
                    <option value="Payment">Payment</option>
                    <option value="Bank Statement">Bank Statement</option>
                    <option value="Journal">Journal</option>
                    <option value="Others">Others</option>
                  </select>
                </div>
              </div>
              
              <!-- Dynamic file upload fields will appear here -->
              <div id="add_doc_file_container" class="mb-4">
                <div class="alert" style="background-color: rgba(255, 193, 7, 0.15); border: 1.5px solid #ffc107; border-radius: 8px; padding: 1rem; color: #ffc107;">
                  <i class="fa-solid fa-info-circle me-2"></i> Please select a Source Type to see required document uploads
                </div>
              </div>
              
              <div class="mb-3">
                <label for="add_doc_description" class="form-label fw-semibold" style="color: #e9ecef; font-size: 0.95rem; margin-bottom: 0.6rem;">
                  <i class="fa-solid fa-align-left me-1" style="color: #0d6efd;"></i> Description
                </label>
                <textarea class="form-control" id="add_doc_description" name="description" rows="3" 
                  style="background-color: #2b3035; border: 1.5px solid #0d6efd; color: #fff; padding: 0.65rem 0.85rem; border-radius: 8px; font-size: 0.95rem; resize: vertical; transition: all 0.3s ease;"></textarea>
              </div>
            </div>
            
            <!-- Right Column -->
            <div class="col-md-6">
              <!-- Date & Location Section -->
              <div class="mb-4">
                <div class="mb-3">
                  <label for="add_doc_date" class="form-label fw-semibold" style="color: #e9ecef; font-size: 0.95rem; margin-bottom: 0.6rem;">
                    <i class="fa-solid fa-calendar-days me-1" style="color: #0d6efd;"></i> Date of Collect <span style="color: #dc3545;">*</span>
                  </label>
                  <input type="date" class="form-control" id="add_doc_date" name="date_of_collect" required 
                    style="background-color: #2b3035; border: 1.5px solid #0d6efd; color: #fff; padding: 0.65rem 0.85rem; border-radius: 8px; font-size: 0.95rem; transition: all 0.3s ease;">
                </div>
              </div>
              
              <div class="mb-4">
                <div class="mb-3">
                  <label for="add_doc_location" class="form-label fw-semibold" style="color: #e9ecef; font-size: 0.95rem; margin-bottom: 0.6rem;">
                    <i class="fa-solid fa-location-dot me-1" style="color: #0d6efd;"></i> Location
                  </label>
                  <input type="text" class="form-control" id="add_doc_location" name="location" placeholder="Physical location or storage area" 
                    style="background-color: #2b3035; border: 1.5px solid #0d6efd; color: #fff; padding: 0.65rem 0.85rem; border-radius: 8px; font-size: 0.95rem; transition: all 0.3s ease;">
                </div>
              </div>

              <!-- Info Card -->
              <div class="mt-4" style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 110, 253, 0.05) 100%); border: 1.5px solid rgba(13, 110, 253, 0.3); border-radius: 10px; padding: 1.25rem;">
                <h6 class="fw-bold mb-3" style="color: #6ea8fe; font-size: 1rem;">
                  <i class="fa-solid fa-lightbulb me-2"></i> Quick Tips
                </h6>
                <ul class="mb-0" style="color: #adb5bd; font-size: 0.9rem; line-height: 1.8; padding-left: 1.2rem;">
                  <li>Fill in all required fields marked with <span style="color: #dc3545;">*</span></li>
                  <li>Select the source type to enable document uploads</li>
                  <li>Ensure the date format is correct (dd/mm/yyyy)</li>
                  <li>Add a clear description for better tracking</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer" style="background-color: #1a1d29; border-top: 2px solid rgba(13, 110, 253, 0.3); padding: 1.25rem 1.5rem; border-radius: 0 0 12px 12px;">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" 
            style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 500; font-size: 0.95rem; transition: all 0.3s ease;">
            Cancel
          </button>
          <button type="submit" class="btn btn-primary" 
            style="padding: 0.6rem 1.8rem; border-radius: 8px; font-weight: 600; font-size: 0.95rem; background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); border: none; box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3); transition: all 0.3s ease;">
            <i class="fa-solid fa-check me-2"></i> Collect Document
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
/* Enhanced form control hover and focus states */
#addDocumentModal .form-control:hover {
  border-color: #6ea8fe !important;
  box-shadow: 0 0 0 0.15rem rgba(13, 110, 253, 0.15);
}

#addDocumentModal .form-control:focus {
  background-color: #2b3035 !important;
  border-color: #6ea8fe !important;
  color: #fff !important;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
}

#addDocumentModal .form-select:focus {
  background-color: #2b3035 !important;
  border-color: #6ea8fe !important;
  color: #fff !important;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
}

#addDocumentModal .btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(13, 110, 253, 0.4) !important;
}

#addDocumentModal .btn-secondary:hover {
  background-color: #5c636a;
  transform: translateY(-1px);
}

/* Date input styling */
#addDocumentModal input[type="date"]::-webkit-calendar-picker-indicator {
  filter: invert(1);
  cursor: pointer;
}

/* --- Add/Edit Company Modal Enhancements --- */
#addCompanyModal .modal-content, #editCompanyModal .modal-content {
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
  border: none;
  border-radius: 1rem;
  box-shadow: 0 20px 40px rgba(0,0,0,0.3);
}

#addCompanyModal .modal-header, #editCompanyModal .modal-header {
  background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
  border: none;
  border-radius: 1rem 1rem 0 0;
}

#addCompanyModal .modal-footer, #editCompanyModal .modal-footer {
  background: linear-gradient(135deg, rgba(13, 202, 240, 0.1) 0%, rgba(13, 110, 253, 0.1) 100%);
  border-top: 2px solid rgba(13, 202, 240, 0.2);
  border-radius: 0 0 1rem 1rem;
}

#addCompanyModal .form-control, #editCompanyModal .form-control {
  background: rgba(255, 255, 255, 0.1);
  border: 2px solid rgba(13, 202, 240, 0.3);
  color: #ffffff;
  border-radius: 0.75rem;
  transition: all 0.3s ease;
}

#addCompanyModal .form-control:focus, #editCompanyModal .form-control:focus {
  background: rgba(255, 255, 255, 0.15);
  border-color: #0dcaf0;
  box-shadow: 0 0 0 0.2rem rgba(13, 202, 240, 0.25);
  color: #ffffff;
}

#addCompanyModal .form-select, #editCompanyModal .form-select {
  background: rgba(255, 255, 255, 0.1);
  border: 2px solid rgba(13, 202, 240, 0.3);
  color: #ffffff;
  border-radius: 0.75rem;
  transition: all 0.3s ease;
}

#addCompanyModal .form-select:focus, #editCompanyModal .form-select:focus {
  background: rgba(255, 255, 255, 0.15);
  border-color: #0dcaf0;
  box-shadow: 0 0 0 0.2rem rgba(13, 202, 240, 0.25);
  color: #ffffff;
}

#addCompanyModal .form-control::placeholder, #editCompanyModal .form-control::placeholder {
  color: rgba(255, 255, 255, 0.6);
}

#addCompanyModal .btn-primary, #editCompanyModal .btn-primary {
  background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
  border: none;
  font-weight: 600;
  border-radius: 0.75rem;
  box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
  transition: all 0.3s ease;
}

#addCompanyModal .btn-primary:hover, #editCompanyModal .btn-primary:hover {
  background: linear-gradient(135deg, #0b5ed7 0%, #084298 100%);
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(13, 110, 253, 0.4);
}

#addCompanyModal .btn-outline-light, #editCompanyModal .btn-outline-light {
  border: 2px solid rgba(255, 255, 255, 0.3);
  color: #ffffff;
  border-radius: 0.75rem;
  font-weight: 600;
  transition: all 0.3s ease;
}

#addCompanyModal .btn-outline-light:hover, #editCompanyModal .btn-outline-light:hover {
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.5);
  transform: translateY(-1px);
}

#addCompanyModal .card, #editCompanyModal .card {
  background: linear-gradient(135deg, rgba(13, 202, 240, 0.08) 0%, rgba(13, 110, 253, 0.08) 100%);
  border: 2px solid rgba(13, 202, 240, 0.3);
  border-radius: 1rem;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

#addCompanyModal .form-check-input:checked, #editCompanyModal .form-check-input:checked {
  background-color: #0dcaf0;
  border-color: #0dcaf0;
}

#addCompanyModal .form-check-input, #editCompanyModal .form-check-input {
  background-color: rgba(255, 255, 255, 0.1);
  border: 2px solid rgba(13, 202, 240, 0.3);
}

#addCompanyModal .form-check-input:focus, #editCompanyModal .form-check-input:focus {
  box-shadow: 0 0 0 0.2rem rgba(13, 202, 240, 0.25);
}

/* Hover effects for better interactivity */
#addCompanyModal .form-control:hover, #editCompanyModal .form-control:hover {
  border-color: rgba(13, 202, 240, 0.5);
  background: rgba(255, 255, 255, 0.12);
}

#addCompanyModal .form-select:hover, #editCompanyModal .form-select:hover {
  border-color: rgba(13, 202, 240, 0.5);
  background: rgba(255, 255, 255, 0.12);
}

/* Animation for form sections */
#addCompanyModal .form-section, #editCompanyModal .form-section {
  animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
}
#addCompanyModal .btn-secondary:hover, #editCompanyModal .btn-secondary:hover {
  background: #565e64;
}
#addCompanyModal .form-text, #editCompanyModal .form-text {
  color: #6c757d;
  font-size: 0.92em;
}
</style>

<!-- Add Query Modal -->
<div class="modal fade" id="addQueryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content bg-dark text-light" style="border-radius: 16px; border: 2px solid rgba(13, 110, 253, 0.4); box-shadow: 0 10px 40px rgba(13, 110, 253, 0.2);">
      <div class="modal-header" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); border-bottom: none; border-radius: 16px 16px 0 0; padding: 1.5rem 2rem; box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);">
        <div>
          <h4 class="modal-title fw-bold text-white mb-1" style="font-size: 1.5rem; letter-spacing: 0.5px;">
            <i class="fa-solid fa-comments me-2"></i> Add Client Query
          </h4>
          <p class="mb-0 text-white" style="font-size: 0.9rem; opacity: 0.9;">
            <i class="fa-solid fa-info-circle me-1"></i> Fill in the details below to create a new client query
          </p>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="opacity: 0.9;"></button>
      </div>
      <form id="addQueryForm" enctype="multipart/form-data">
        <div class="modal-body" style="padding: 2.5rem 2rem; background: linear-gradient(135deg, #1a1d29 0%, #252a3a 100%);">
          
          <!-- Section 1: Basic Information -->
          <div class="section-header mb-4" style="border-bottom: 2px solid rgba(13, 110, 253, 0.3); padding-bottom: 0.75rem;">
            <h5 class="text-light mb-0" style="font-weight: 600;">
              <i class="fa-solid fa-user-circle me-2" style="color: #0d6efd;"></i> Basic Information
            </h5>
          </div>
          
          <div class="row g-4 mb-5">
            <!-- Company Selection -->
            <div class="col-md-6">
              <div class="form-group-enhanced">
                <label for="query_company" class="form-label fw-semibold d-flex align-items-center" style="color: #0d6efd; font-size: 1rem; margin-bottom: 0.75rem;">
                  <i class="fa-solid fa-building me-2" style="font-size: 1.1rem;"></i> Company <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                </label>
                <select class="form-control form-control-lg" id="query_company" name="company_id" required 
                  style="background-color: #2b3035; border: 2px solid rgba(13, 110, 253, 0.5); color: #fff; padding: 0.75rem 1rem; border-radius: 10px; font-size: 1rem; transition: all 0.3s ease;">
                  <option value="">-- Select Company --</option>
                  <?php foreach ($companies as $c): ?>
                  <option value="<?php echo $c['company_id']; ?>"><?php echo htmlspecialchars($c['company_name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Client Name -->
            <div class="col-md-6">
              <div class="form-group-enhanced">
                <label for="query_client_name" class="form-label fw-semibold d-flex align-items-center" style="color: #0d6efd; font-size: 1rem; margin-bottom: 0.75rem;">
                  <i class="fa-solid fa-user me-2" style="font-size: 1.1rem;"></i> Client Name <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                </label>
                <input type="text" class="form-control form-control-lg" id="query_client_name" name="client_name" required placeholder="Enter client's full name"
                  style="background-color: #2b3035; border: 2px solid rgba(13, 110, 253, 0.5); color: #fff; padding: 0.75rem 1rem; border-radius: 10px; font-size: 1rem; transition: all 0.3s ease;">
              </div>
            </div>
          </div>
          
          <!-- Section 2: Questions & Answers -->
          <div class="section-header mb-4" style="border-bottom: 2px solid rgba(13, 110, 253, 0.3); padding-bottom: 0.75rem;">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="text-light mb-0" style="font-weight: 600;">
                <i class="fa-solid fa-list-check me-2" style="color: #0d6efd;"></i> Questions & Answers
              </h5>
              <button type="button" class="btn btn-primary" onclick="addQAPair()" 
                style="background: linear-gradient(135deg, #0d6efd, #0a58ca); color: #fff; font-weight: 700; border: none; padding: 0.5rem 1.25rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3); transition: all 0.3s ease;">
                <i class="fa-solid fa-plus-circle me-2"></i> Add Q&A Pair
              </button>
            </div>
          </div>
          
          <!-- Q&A Pairs Container -->
          <div id="qaPairsContainer" class="mb-5" style="max-height: 500px; overflow-y: auto; padding-right: 0.5rem;">
            <!-- First Q&A Pair (Required) -->
            <div class="qa-pair mb-4" style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.08) 0%, rgba(10, 88, 202, 0.05) 100%); padding: 1.5rem; border-radius: 12px; border: 2px solid rgba(13, 110, 253, 0.3); box-shadow: 0 4px 12px rgba(13, 110, 253, 0.1);">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0" style="color: #0d6efd; font-weight: 700; font-size: 1.1rem;">
                  <i class="fa-solid fa-circle-1 me-2"></i> Q&A Pair #1
                </h6>
                <span class="badge bg-success" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">Required</span>
              </div>
              
              <div class="mb-3">
                <label class="form-label fw-semibold d-flex align-items-center" style="color: #e9ecef; font-size: 0.95rem;">
                  <i class="fa-solid fa-circle-question me-2" style="color: #0d6efd;"></i> Question (Q) <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                </label>
                <textarea class="form-control qa-question" name="questions[]" rows="3" required placeholder="Enter the client's question here..."
                  style="background-color: #2b3035; border: 2px solid rgba(13, 110, 253, 0.4); color: #fff; padding: 0.75rem 1rem; border-radius: 8px; resize: vertical; font-size: 0.95rem; transition: all 0.3s ease;"></textarea>
              </div>
              
              <div>
                <label class="form-label fw-semibold d-flex align-items-center" style="color: #e9ecef; font-size: 0.95rem;">
                  <i class="fa-solid fa-comment-dots me-2" style="color: #28a745;"></i> Answer (A) <span class="badge bg-secondary ms-2" style="font-size: 0.75rem;">Optional</span>
                </label>
                <textarea class="form-control qa-answer" name="answers[]" rows="3" placeholder="Enter the answer (can be filled later)..."
                  style="background-color: #2b3035; border: 2px solid rgba(40, 167, 69, 0.4); color: #fff; padding: 0.75rem 1rem; border-radius: 8px; resize: vertical; font-size: 0.95rem; transition: all 0.3s ease;"></textarea>
              </div>
            </div>
          </div>
          
          <!-- Section 3: Query Details -->
          <div class="section-header mb-4" style="border-bottom: 2px solid rgba(13, 110, 253, 0.3); padding-bottom: 0.75rem;">
            <h5 class="text-light mb-0" style="font-weight: 600;">
              <i class="fa-solid fa-sliders me-2" style="color: #0d6efd;"></i> Query Details
            </h5>
          </div>
          
          <div class="row g-4 mb-5">
            <!-- Query Type -->
            <div class="col-md-4">
              <div class="form-group-enhanced">
                <label for="query_type" class="form-label fw-semibold d-flex align-items-center" style="color: #0d6efd; font-size: 1rem; margin-bottom: 0.75rem;">
                  <i class="fa-solid fa-tag me-2" style="font-size: 1.1rem;"></i> Type <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                </label>
                <select class="form-control form-control-lg" id="query_type" name="query_type" required 
                  style="background-color: #2b3035; border: 2px solid rgba(13, 110, 253, 0.5); color: #fff; padding: 0.75rem 1rem; border-radius: 10px; font-size: 1rem; transition: all 0.3s ease;">
                  <option value="">-- Select Type --</option>
                  <option value="RD">🔬 RD (Research & Development)</option>
                  <option value="AG">📄 AG (Agreement)</option>
                  <option value="Doc">📋 Doc (Documentation)</option>
                </select>
              </div>
            </div>

            <!-- Risk Level -->
            <div class="col-md-4">
              <div class="form-group-enhanced">
                <label for="query_risk" class="form-label fw-semibold d-flex align-items-center" style="color: #0d6efd; font-size: 1rem; margin-bottom: 0.75rem;">
                  <i class="fa-solid fa-triangle-exclamation me-2" style="font-size: 1.1rem;"></i> Risk Level <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                </label>
                <select class="form-control form-control-lg" id="query_risk" name="risk_level" required 
                  style="background-color: #2b3035; border: 2px solid rgba(13, 110, 253, 0.5); color: #fff; padding: 0.75rem 1rem; border-radius: 10px; font-size: 1rem; transition: all 0.3s ease;">
                  <option value="">-- Select Risk Level --</option>
                  <option value="Low">🟢 Low Risk</option>
                  <option value="Middle">🟡 Middle Risk</option>
                  <option value="High">🔴 High Risk</option>
                </select>
              </div>
            </div>

            <!-- Query Date -->
            <div class="col-md-4">
              <div class="form-group-enhanced">
                <label for="query_date" class="form-label fw-semibold d-flex align-items-center" style="color: #0d6efd; font-size: 1rem; margin-bottom: 0.75rem;">
                  <i class="fa-solid fa-calendar-days me-2" style="font-size: 1.1rem;"></i> Date <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                </label>
                <input type="date" class="form-control form-control-lg" id="query_date" name="query_date" required 
                  style="background-color: #2b3035; border: 2px solid rgba(13, 110, 253, 0.5); color: #fff; padding: 0.75rem 1rem; border-radius: 10px; font-size: 1rem; transition: all 0.3s ease;">
              </div>
            </div>
          </div>
          
          <!-- Section 4: Additional Options -->
          <div class="section-header mb-4" style="border-bottom: 2px solid rgba(13, 110, 253, 0.3); padding-bottom: 0.75rem;">
            <h5 class="text-light mb-0" style="font-weight: 600;">
              <i class="fa-solid fa-paperclip me-2" style="color: #0d6efd;"></i> Additional Options
            </h5>
          </div>
          
          <div class="row g-4 mb-4">
            <!-- ML Checkbox -->
            <div class="col-12">
              <div class="form-check p-4" style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 110, 253, 0.05) 100%); border: 2px solid rgba(13, 110, 253, 0.3); border-radius: 12px; padding-left: 3rem !important;">
                <input class="form-check-input" type="checkbox" id="query_ml" name="ml_enabled" value="1" 
                  style="width: 24px; height: 24px; margin-right: 15px; cursor: pointer; border: 2px solid #0d6efd; margin-top: 0.25rem;">
                <label class="form-check-label fw-semibold" for="query_ml" style="color: #e9ecef; cursor: pointer; font-size: 1.05rem;">
                  <i class="fa-solid fa-envelope me-2" style="color: #0d6efd; font-size: 1.2rem;"></i> Enable Management Letter (ML)
                  <p class="mb-0 mt-1" style="font-size: 0.85rem; color: #adb5bd; font-weight: normal;">
                    <i class="fa-solid fa-info-circle me-1"></i> Send management letter notifications and updates for this query
                  </p>
                </label>
              </div>
            </div>

            <!-- Photo Upload -->
            <div class="col-md-6">
              <div class="form-group-enhanced">
                <label for="query_photo" class="form-label fw-semibold d-flex align-items-center" style="color: #0d6efd; font-size: 1rem; margin-bottom: 0.75rem;">
                  <i class="fa-solid fa-image me-2" style="font-size: 1.1rem;"></i> Photo Attachment <span class="badge bg-secondary ms-2" style="font-size: 0.75rem;">Optional</span>
                </label>
                <div class="file-upload-wrapper" style="position: relative;">
                  <input type="file" class="form-control form-control-lg" id="query_photo" name="photo" accept="image/*" 
                    style="background-color: #2b3035; border: 2px dashed rgba(13, 110, 253, 0.5); color: #fff; padding: 0.75rem 1rem; border-radius: 10px; font-size: 0.95rem; transition: all 0.3s ease; cursor: pointer;">
                </div>
                <div class="form-text mt-2" style="color: #adb5bd; font-size: 0.85rem;">
                  <i class="fa-solid fa-info-circle me-1"></i> Supported: JPG, PNG, GIF (Max 10MB)
                </div>
              </div>
            </div>

            <!-- Voice Recording -->
            <div class="col-md-6">
              <div class="form-group-enhanced">
                <label for="query_voice" class="form-label fw-semibold d-flex align-items-center" style="color: #0d6efd; font-size: 1rem; margin-bottom: 0.75rem;">
                  <i class="fa-solid fa-microphone me-2" style="font-size: 1.1rem;"></i> Voice Recording <span class="badge bg-secondary ms-2" style="font-size: 0.75rem;">Optional</span>
                </label>
                <div class="file-upload-wrapper" style="position: relative;">
                  <input type="file" class="form-control form-control-lg" id="query_voice" name="voice" accept="audio/*" 
                    style="background-color: #2b3035; border: 2px dashed rgba(13, 110, 253, 0.5); color: #fff; padding: 0.75rem 1rem; border-radius: 10px; font-size: 0.95rem; transition: all 0.3s ease; cursor: pointer;">
                </div>
                <div class="form-text mt-2" style="color: #adb5bd; font-size: 0.85rem;">
                  <i class="fa-solid fa-info-circle me-1"></i> Supported: MP3, WAV, OGG (Max 20MB)
                </div>
              </div>
            </div>

            <!-- Document Upload -->
            <div class="col-md-6">
              <div class="form-group-enhanced">
                <label for="query_document" class="form-label fw-semibold d-flex align-items-center" style="color: #0d6efd; font-size: 1rem; margin-bottom: 0.75rem;">
                  <i class="fa-solid fa-file-pdf me-2" style="font-size: 1.1rem;"></i> Document Attachment <span class="badge bg-secondary ms-2" style="font-size: 0.75rem;">Optional</span>
                </label>
                <div class="file-upload-wrapper" style="position: relative;">
                  <input type="file" class="form-control form-control-lg" id="query_document" name="document" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt" 
                    style="background-color: #2b3035; border: 2px dashed rgba(13, 110, 253, 0.5); color: #fff; padding: 0.75rem 1rem; border-radius: 10px; font-size: 0.95rem; transition: all 0.3s ease; cursor: pointer;">
                </div>
                <div class="form-text mt-2" style="color: #adb5bd; font-size: 0.85rem;">
                  <i class="fa-solid fa-info-circle me-1"></i> Supported: PDF, DOC, DOCX, XLS, XLSX, TXT (Max 25MB)
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="modal-footer" style="background: linear-gradient(135deg, #1a1d29 0%, #252a3a 100%); border-top: 2px solid rgba(13, 110, 253, 0.3); padding: 1.5rem 2rem; border-radius: 0 0 16px 16px;">
          <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal" 
            style="padding: 0.75rem 2rem; border-radius: 10px; font-weight: 600; font-size: 1rem; border: 2px solid #6c757d; transition: all 0.3s ease;">
            <i class="fa-solid fa-times me-2"></i> Cancel
          </button>
          <button type="submit" class="btn btn-primary btn-lg" 
            style="padding: 0.75rem 2.5rem; border-radius: 10px; font-weight: 700; font-size: 1rem; background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); border: none; color: #fff; box-shadow: 0 6px 20px rgba(13, 110, 253, 0.4); transition: all 0.3s ease;">
            <i class="fa-solid fa-paper-plane me-2"></i> Submit Query
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
/* Enhanced Query Modal Styling */
#addQueryModal .form-control:hover {
  border-color: #0d6efd !important;
  box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.2);
  transform: translateY(-1px);
}

#addQueryModal .form-control:focus {
  background-color: #2b3035 !important;
  border-color: #0d6efd !important;
  color: #fff !important;
  box-shadow: 0 0 0 0.3rem rgba(13, 110, 253, 0.3) !important;
  transform: translateY(-1px);
}

#addQueryModal input[type="date"]::-webkit-calendar-picker-indicator {
  filter: invert(1);
  cursor: pointer;
  transition: all 0.3s ease;
}

#addQueryModal input[type="date"]::-webkit-calendar-picker-indicator:hover {
  transform: scale(1.1);
}

#addQueryModal .form-check-input:checked {
  background-color: #0d6efd;
  border-color: #0d6efd;
}

#addQueryModal .btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(13, 110, 253, 0.5) !important;
}

#addQueryModal .btn-secondary:hover {
  background-color: #5c636a;
  border-color: #5c636a;
  box-shadow: 0 4px 12px rgba(108, 117, 125, 0.4) !important;
}

#addQueryModal .qa-pair {
  transition: all 0.3s ease;
}

#addQueryModal .qa-pair:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(13, 110, 253, 0.2) !important;
}

#addQueryModal .section-header {
  animation: fadeInDown 0.5s ease;
}

@keyframes fadeInDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(-20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes pulse {
  0% {
    transform: scale(1);
    box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.7);
  }
  50% {
    transform: scale(1.02);
    box-shadow: 0 0 0 10px rgba(13, 110, 253, 0);
  }
  100% {
    transform: scale(1);
    box-shadow: 0 0 0 0 rgba(13, 110, 253, 0);
  }
}

/* Scrollbar styling for Q&A container */
#qaPairsContainer::-webkit-scrollbar {
  width: 8px;
}

#qaPairsContainer::-webkit-scrollbar-track {
  background: rgba(13, 110, 253, 0.1);
  border-radius: 10px;
}

#qaPairsContainer::-webkit-scrollbar-thumb {
  background: linear-gradient(135deg, #0d6efd, #0a58ca);
  border-radius: 10px;
}

#qaPairsContainer::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(135deg, #0a58ca, #0d6efd);
}

/* File input hover effect */
#addQueryModal input[type="file"]:hover {
  border-color: #0d6efd !important;
  border-style: solid !important;
}

/* Placeholder styling */
#addQueryModal ::placeholder {
  color: #6c757d;
  opacity: 0.7;
}

#addQueryModal ::-ms-input-placeholder {
  color: #6c757d;
}
</style>

<!-- Add Manager Review Modal -->
<div class="modal fade" id="addReviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content bg-dark text-light" style="border-radius: 16px; border: 2px solid rgba(59, 130, 246, 0.4); box-shadow: 0 10px 40px rgba(59, 130, 246, 0.2);">
      <div class="modal-header" style="background: linear-gradient(135deg, #3b82f6 0%, #1e3a8a 100%); border-bottom: none; border-radius: 16px 16px 0 0; padding: 1.5rem 2rem; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">
        <div>
          <h4 class="modal-title fw-bold text-white mb-1" style="font-size: 1.5rem; letter-spacing: 0.5px;">
            <i class="fa-solid fa-clipboard-check me-2"></i> Add Manager Review
          </h4>
          <p class="mb-0 text-white" style="font-size: 0.9rem; opacity: 0.9;">
            <i class="fa-solid fa-info-circle me-1"></i> Fill in the details below to create a new manager review
          </p>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="opacity: 0.9;"></button>
      </div>
      <form id="addReviewForm" enctype="multipart/form-data">
        <div class="modal-body" style="padding: 2.5rem 2rem; background: linear-gradient(135deg, #1a1d29 0%, #252a3a 100%);">
          
          <!-- Section 1: Basic Information -->
          <div class="section-header mb-4" style="border-bottom: 2px solid rgba(59, 130, 246, 0.3); padding-bottom: 0.75rem;">
            <h5 class="text-light mb-0" style="font-weight: 600;">
              <i class="fa-solid fa-user-circle me-2" style="color: #3b82f6;"></i> Basic Information
            </h5>
          </div>
          
          <div class="row g-4 mb-5">
            <!-- Company Selection -->
            <div class="col-md-6">
              <div class="form-group-enhanced">
                <label for="review_company" class="form-label fw-semibold d-flex align-items-center" style="color: #3b82f6; font-size: 1rem; margin-bottom: 0.75rem;">
                  <i class="fa-solid fa-building me-2" style="font-size: 1.1rem;"></i> Company <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                </label>
                <select class="form-control form-control-lg" id="review_company" name="company_id" required 
                  style="background-color: #2b3035; border: 2px solid rgba(59, 130, 246, 0.5); color: #fff; padding: 0.75rem 1rem; border-radius: 10px; font-size: 1rem; transition: all 0.3s ease;">
                  <option value="">-- Select Company --</option>
                  <?php foreach ($companies as $c): ?>
                  <option value="<?php echo $c['company_id']; ?>"><?php echo htmlspecialchars($c['company_name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Manager Name -->
            <div class="col-md-6">
              <div class="form-group-enhanced">
                <label for="review_manager_name" class="form-label fw-semibold d-flex align-items-center" style="color: #3b82f6; font-size: 1rem; margin-bottom: 0.75rem;">
                  <i class="fa-solid fa-user-tie me-2" style="font-size: 1.1rem;"></i> Manager Name <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                </label>
                <input type="text" class="form-control form-control-lg" id="review_manager_name" name="manager_name" required placeholder="Enter manager's full name"
                  style="background-color: #2b3035; border: 2px solid rgba(59, 130, 246, 0.5); color: #fff; padding: 0.75rem 1rem; border-radius: 10px; font-size: 1rem; transition: all 0.3s ease;">
              </div>
            </div>
          </div>
          
          <!-- Section 2: Questions & Answers -->
          <div class="section-header mb-4" style="border-bottom: 2px solid rgba(59, 130, 246, 0.3); padding-bottom: 0.75rem;">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="text-light mb-0" style="font-weight: 600;">
                <i class="fa-solid fa-list-check me-2" style="color: #3b82f6;"></i> Questions & Answers
              </h5>
              <button type="button" class="btn btn-primary" onclick="addReviewQAPair()" 
                style="background: linear-gradient(135deg, #3b82f6, #1e3a8a); color: #fff; font-weight: 700; border: none; padding: 0.5rem 1.25rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); transition: all 0.3s ease;">
                <i class="fa-solid fa-plus-circle me-2"></i> Add Q&A Pair
              </button>
            </div>
          </div>
          
          <!-- Q&A Pairs Container -->
          <div id="reviewQaPairsContainer" class="mb-5" style="max-height: 500px; overflow-y: auto; padding-right: 0.5rem;">
            <!-- First Q&A Pair (Required) -->
            <div class="review-qa-pair mb-4" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(30, 58, 138, 0.05) 100%); padding: 1.5rem; border-radius: 12px; border: 2px solid rgba(59, 130, 246, 0.3); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0" style="color: #3b82f6; font-weight: 700; font-size: 1.1rem;">
                  <i class="fa-solid fa-circle-1 me-2"></i> Q&A Pair #1
                </h6>
                <span class="badge bg-success" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">Required</span>
              </div>
              
              <div class="mb-3">
                <label class="form-label fw-semibold d-flex align-items-center" style="color: #e9ecef; font-size: 0.95rem;">
                  <i class="fa-solid fa-circle-question me-2" style="color: #3b82f6;"></i> Question (Q) <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                </label>
                <textarea class="form-control review-qa-question" name="review_questions[]" rows="3" required placeholder="Enter the review question here..."
                  style="background-color: #2b3035; border: 2px solid rgba(59, 130, 246, 0.4); color: #fff; padding: 0.75rem 1rem; border-radius: 8px; resize: vertical; font-size: 0.95rem; transition: all 0.3s ease;"></textarea>
              </div>
              
              <div>
                <label class="form-label fw-semibold d-flex align-items-center" style="color: #e9ecef; font-size: 0.95rem;">
                  <i class="fa-solid fa-comment-dots me-2" style="color: #28a745;"></i> Answer (A) <span class="badge bg-secondary ms-2" style="font-size: 0.75rem;">Optional</span>
                </label>
                <textarea class="form-control review-qa-answer" name="review_answers[]" rows="3" placeholder="Enter the answer (can be filled later)..."
                  style="background-color: #2b3035; border: 2px solid rgba(40, 167, 69, 0.4); color: #fff; padding: 0.75rem 1rem; border-radius: 8px; resize: vertical; font-size: 0.95rem; transition: all 0.3s ease;"></textarea>
              </div>
            </div>
          </div>
          
          <!-- Section 3: Review Details -->
          <div class="section-header mb-4" style="border-bottom: 2px solid rgba(59, 130, 246, 0.3); padding-bottom: 0.75rem;">
            <h5 class="text-light mb-0" style="font-weight: 600;">
              <i class="fa-solid fa-sliders me-2" style="color: #3b82f6;"></i> Review Details
            </h5>
          </div>
          
          <div class="row g-4 mb-5">
            <!-- Review Type -->
            <div class="col-md-4">
              <div class="form-group-enhanced">
                <label for="review_type" class="form-label fw-semibold d-flex align-items-center" style="color: #3b82f6; font-size: 1rem; margin-bottom: 0.75rem;">
                  <i class="fa-solid fa-tag me-2" style="font-size: 1.1rem;"></i> Type <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                </label>
                <select class="form-control form-control-lg" id="review_type" name="review_type" required 
                  style="background-color: #2b3035; border: 2px solid rgba(59, 130, 246, 0.5); color: #fff; padding: 0.75rem 1rem; border-radius: 10px; font-size: 1rem; transition: all 0.3s ease;">
                  <option value="">-- Select Type --</option>
                  <option value="RD">🔬 RD (Research & Development)</option>
                  <option value="AG">📄 AG (Agreement)</option>
                  <option value="Doc">📋 Doc (Documentation)</option>
                </select>
              </div>
            </div>

            <!-- Risk Level -->
            <div class="col-md-4">
              <div class="form-group-enhanced">
                <label for="review_risk" class="form-label fw-semibold d-flex align-items-center" style="color: #3b82f6; font-size: 1rem; margin-bottom: 0.75rem;">
                  <i class="fa-solid fa-triangle-exclamation me-2" style="font-size: 1.1rem;"></i> Risk Level <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                </label>
                <select class="form-control form-control-lg" id="review_risk" name="risk_level" required 
                  style="background-color: #2b3035; border: 2px solid rgba(59, 130, 246, 0.5); color: #fff; padding: 0.75rem 1rem; border-radius: 10px; font-size: 1rem; transition: all 0.3s ease;">
                  <option value="">-- Select Risk Level --</option>
                  <option value="Low">🟢 Low Risk</option>
                  <option value="Middle">🟡 Middle Risk</option>
                  <option value="High">🔴 High Risk</option>
                </select>
              </div>
            </div>

            <!-- Review Date -->
            <div class="col-md-4">
              <div class="form-group-enhanced">
                <label for="review_date" class="form-label fw-semibold d-flex align-items-center" style="color: #3b82f6; font-size: 1rem; margin-bottom: 0.75rem;">
                  <i class="fa-solid fa-calendar-days me-2" style="font-size: 1.1rem;"></i> Date <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                </label>
                <input type="date" class="form-control form-control-lg" id="review_date" name="review_date" required 
                  style="background-color: #2b3035; border: 2px solid rgba(59, 130, 246, 0.5); color: #fff; padding: 0.75rem 1rem; border-radius: 10px; font-size: 1rem; transition: all 0.3s ease;">
              </div>
            </div>
          </div>
          
          <!-- Section 4: Additional Options -->
          <div class="section-header mb-4" style="border-bottom: 2px solid rgba(59, 130, 246, 0.3); padding-bottom: 0.75rem;">
            <h5 class="text-light mb-0" style="font-weight: 600;">
              <i class="fa-solid fa-paperclip me-2" style="color: #3b82f6;"></i> Additional Options
            </h5>
          </div>
          
          <div class="row g-4 mb-4">
            <!-- ML Checkbox -->
            <div class="col-12">
              <div class="form-check p-4" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%); border: 2px solid rgba(59, 130, 246, 0.3); border-radius: 12px; padding-left: 3rem !important;">
                <input class="form-check-input" type="checkbox" id="review_ml" name="ml_enabled" value="1" 
                  style="width: 24px; height: 24px; margin-right: 15px; cursor: pointer; border: 2px solid #3b82f6; margin-top: 0.25rem;">
                <label class="form-check-label fw-semibold" for="review_ml" style="color: #e9ecef; cursor: pointer; font-size: 1.05rem;">
                  <i class="fa-solid fa-envelope me-2" style="color: #3b82f6; font-size: 1.2rem;"></i> Enable Management Letter (ML)
                  <p class="mb-0 mt-1" style="font-size: 0.85rem; color: #adb5bd; font-weight: normal;">
                    <i class="fa-solid fa-info-circle me-1"></i> Send management letter notifications and updates for this review
                  </p>
                </label>
              </div>
            </div>

            <!-- Photo Upload -->
            <div class="col-md-6">
              <div class="form-group-enhanced">
                <label for="review_photo" class="form-label fw-semibold d-flex align-items-center" style="color: #3b82f6; font-size: 1rem; margin-bottom: 0.75rem;">
                  <i class="fa-solid fa-image me-2" style="font-size: 1.1rem;"></i> Photo Attachment <span class="badge bg-secondary ms-2" style="font-size: 0.75rem;">Optional</span>
                </label>
                <div class="file-upload-wrapper" style="position: relative;">
                  <input type="file" class="form-control form-control-lg" id="review_photo" name="photo" accept="image/*" 
                    style="background-color: #2b3035; border: 2px dashed rgba(59, 130, 246, 0.5); color: #fff; padding: 0.75rem 1rem; border-radius: 10px; font-size: 0.95rem; transition: all 0.3s ease; cursor: pointer;">
                </div>
                <div class="form-text mt-2" style="color: #adb5bd; font-size: 0.85rem;">
                  <i class="fa-solid fa-info-circle me-1"></i> Supported: JPG, PNG, GIF (Max 10MB)
                </div>
              </div>
            </div>

            <!-- Voice Recording -->
            <div class="col-md-6">
              <div class="form-group-enhanced">
                <label for="review_voice" class="form-label fw-semibold d-flex align-items-center" style="color: #3b82f6; font-size: 1rem; margin-bottom: 0.75rem;">
                  <i class="fa-solid fa-microphone me-2" style="font-size: 1.1rem;"></i> Voice Recording <span class="badge bg-secondary ms-2" style="font-size: 0.75rem;">Optional</span>
                </label>
                <div class="file-upload-wrapper" style="position: relative;">
                  <input type="file" class="form-control form-control-lg" id="review_voice" name="voice" accept="audio/*" 
                    style="background-color: #2b3035; border: 2px dashed rgba(59, 130, 246, 0.5); color: #fff; padding: 0.75rem 1rem; border-radius: 10px; font-size: 0.95rem; transition: all 0.3s ease; cursor: pointer;">
                </div>
                <div class="form-text mt-2" style="color: #adb5bd; font-size: 0.85rem;">
                  <i class="fa-solid fa-info-circle me-1"></i> Supported: MP3, WAV, OGG (Max 20MB)
                </div>
              </div>
            </div>

            <!-- Document Upload -->
            <div class="col-md-6">
              <div class="form-group-enhanced">
                <label for="review_document" class="form-label fw-semibold d-flex align-items-center" style="color: #3b82f6; font-size: 1rem; margin-bottom: 0.75rem;">
                  <i class="fa-solid fa-file-pdf me-2" style="font-size: 1.1rem;"></i> Document Attachment <span class="badge bg-secondary ms-2" style="font-size: 0.75rem;">Optional</span>
                </label>
                <div class="file-upload-wrapper" style="position: relative;">
                  <input type="file" class="form-control form-control-lg" id="review_document" name="document" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt" 
                    style="background-color: #2b3035; border: 2px dashed rgba(59, 130, 246, 0.5); color: #fff; padding: 0.75rem 1rem; border-radius: 10px; font-size: 0.95rem; transition: all 0.3s ease; cursor: pointer;">
                </div>
                <div class="form-text mt-2" style="color: #adb5bd; font-size: 0.85rem;">
                  <i class="fa-solid fa-info-circle me-1"></i> Supported: PDF, DOC, DOCX, XLS, XLSX, TXT (Max 25MB)
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="modal-footer" style="background: linear-gradient(135deg, #1a1d29 0%, #252a3a 100%); border-top: 2px solid rgba(59, 130, 246, 0.3); padding: 1.5rem 2rem; border-radius: 0 0 16px 16px;">
          <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal" 
            style="padding: 0.75rem 2rem; border-radius: 10px; font-weight: 600; font-size: 1rem; border: 2px solid #6c757d; transition: all 0.3s ease;">
            <i class="fa-solid fa-times me-2"></i> Cancel
          </button>
          <button type="submit" class="btn btn-primary btn-lg" 
            style="padding: 0.75rem 2.5rem; border-radius: 10px; font-weight: 700; font-size: 1rem; background: linear-gradient(135deg, #3b82f6 0%, #1e3a8a 100%); border: none; color: #fff; box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4); transition: all 0.3s ease;">
            <i class="fa-solid fa-paper-plane me-2"></i> Submit Review
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Manager Review Modal -->
<div class="modal fade" id="viewReviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content bg-dark text-light" style="border-radius: 16px; border: 2px solid rgba(59, 130, 246, 0.4); box-shadow: 0 10px 40px rgba(59, 130, 246, 0.2);">
      <div class="modal-header" style="background: linear-gradient(135deg, #3b82f6 0%, #1e3a8a 100%); border-bottom: none; border-radius: 16px 16px 0 0; padding: 1.5rem 2rem;">
        <h4 class="modal-title fw-bold" style="color: #fff; font-size: 1.5rem;">
          <i class="fa-solid fa-clipboard-check me-2" style="color: #60a5fa;"></i>Manager Review Details
        </h4>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="reviewDetailsContent" style="background: linear-gradient(135deg, #1a1d29 0%, #252a3a 100%); padding: 2rem;">
        <!-- Content will be loaded dynamically -->
        <div class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
      </div>
      <div class="modal-footer" style="background: linear-gradient(135deg, #1a1d29 0%, #252a3a 100%); border-top: 2px solid rgba(59, 130, 246, 0.3); padding: 1.5rem 2rem; border-radius: 0 0 16px 16px;">
        <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal" style="padding: 0.75rem 2rem; border-radius: 10px; font-weight: 600;">
          <i class="fa-solid fa-times me-2"></i> Close
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Document Modal -->
<div class="modal fade" id="editDocumentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Edit Document</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="editDocumentForm" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" id="edit_doc_id" name="document_id">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit_doc_company" class="form-label">Company Name *</label>
                <select class="form-control" id="edit_doc_company" name="company_id" required>
                  <option value="">-- Select Company --</option>
                  <?php foreach ($companies as $c): ?>
                  <option value="<?php echo $c['company_id']; ?>"><?php echo htmlspecialchars($c['company_name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <div class="mb-3">
                <label for="edit_doc_title" class="form-label">Document Name/Title *</label>
                <input type="text" class="form-control" id="edit_doc_title" name="document_title" required>
              </div>
              
              <div class="mb-3">
                <label for="edit_doc_source_type" class="form-label">Source Type *</label>
                <select class="form-control" id="edit_doc_source_type" name="source_type" required>
                  <option value="">-- Select Source Type --</option>
                  <option value="Supplier">Supplier</option>
                  <option value="Customer">Customer</option>
                  <option value="Bank">Bank</option>
                  <option value="Government">Government Agency</option>
                  <option value="Client">Client Themselves</option>
                </select>
                <div class="form-text text-info">Select source type to show required document uploads</div>
              </div>
              
              <div class="mb-3">
                <label for="edit_doc_type" class="form-label">Document Type *</label>
                <select class="form-control" id="edit_doc_type" name="document_type" required>
                  <option value="Sales">Sales</option>
                  <option value="Receiving">Receiving</option>
                  <option value="Purchase">Purchase</option>
                  <option value="Payment">Payment</option>
                  <option value="Bank Statement">Bank Statement</option>
                  <option value="Journal">Journal</option>
                  <option value="Others">Others</option>
                </select>
              </div>
              
              <!-- Dynamic file upload fields will appear here -->
              <div id="edit_doc_file_container">
                <div class="alert alert-warning">
                  <i class="fa-solid fa-info-circle"></i> Please select a Source Type to see required document uploads
                </div>
              </div>
              
              <div class="mb-3">
                <label for="edit_doc_description" class="form-label">Description</label>
                <textarea class="form-control" id="edit_doc_description" name="description" rows="3"></textarea>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit_doc_date" class="form-label">Date of Collect *</label>
                <input type="date" class="form-control" id="edit_doc_date" name="date_of_collect" required>
              </div>
              
              <div class="mb-3">
                <label for="edit_doc_location" class="form-label">Location</label>
                <input type="text" class="form-control" id="edit_doc_location" name="location" placeholder="Physical location or storage area">
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Update Document Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-arrows-spin"></i> Update Document Status</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="updateStatusForm">
        <div class="modal-body">
          <input type="hidden" id="status_doc_id" name="document_id">
          
          <div class="alert alert-info">
            <small><strong>Workflow Guide:</strong><br>
            • <strong>Reviewed:</strong> Accountant reviews → Forwards to Manager<br>
            • <strong>Approved:</strong> Manager approves → Forwards to Auditor<br>
            • <strong>Final Approved:</strong> Auditor gives final approval → Returns to Creator<br>
            • <strong>Submit:</strong> Creator submits to client → Process complete<br>
            • <strong>Returned:</strong> Send back to previous handler for corrections<br>
            • <strong>Rejected:</strong> Document is invalid/rejected</small>
          </div>
          
          <div class="mb-3">
            <label for="update_status" class="form-label">New Status *</label>
            <select class="form-control" id="update_status" name="status" required>
              <option value="">-- Select Status --</option>
              <option value="Reviewed">✓ Reviewed (Accountant → Manager)</option>
              <option value="Approved">✓ Approved (Manager → Auditor)</option>
              <option value="Final Approved">✓ Final Approved (Auditor → Creator)</option>
              <option value="Submit">📤 Submit (Creator → Client)</option>
              <option value="Returned">↩ Returned (Send Back)</option>
              <option value="Rejected">✗ Rejected (Invalid)</option>
            </select>
          </div>
          
          <div class="mb-3">
            <label for="update_comments" class="form-label">Comments/Remarks</label>
            <textarea class="form-control" id="update_comments" name="comments" rows="3" placeholder="Add any comments or reasons for this action..."></textarea>
            <small class="text-muted">Recommended for Returned or Rejected status</small>
          </div>
          
          <div class="alert alert-warning">
            <small><i class="fa-solid fa-info-circle"></i> Handler will be automatically assigned based on the workflow. You don't need to manually select.</small>
          </div>
          
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Status</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Comment/Reason Modal -->
<div class="modal fade" id="commentModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title" id="commentModalTitle"><i class="fa-solid fa-comment"></i> Enter Reason</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="commentModalMessage">Please enter reason for this action:</p>
        <textarea class="form-control" id="commentTextarea" rows="4" placeholder="Enter your reason here..."></textarea>
        <small class="text-muted">This will be saved in the document history.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="commentSubmitBtn">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Supplier Details Modal -->
<div class="modal fade" id="supplierDetailsModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-file-invoice"></i> Enter Document Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="supplierDetailsForm">
        <div class="modal-body">
          <input type="hidden" id="supplier_file_id" name="file_id">
          <input type="hidden" id="supplier_doc_id" name="document_id">
          
          <div class="alert alert-info">
            <i class="fa-solid fa-info-circle"></i> <strong>Document Category:</strong> <span id="supplier_category_display"></span>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="supplier_name" class="form-label">Supplier Name *</label>
                <input type="text" class="form-control" id="supplier_name" name="supplier_name" required>
              </div>
              
              <div class="mb-3">
                <label for="supplier_pi_no" class="form-label">Supplier PI No *</label>
                <input type="text" class="form-control" id="supplier_pi_no" name="supplier_pi_no" required>
              </div>
              
              <div class="mb-3">
                <label for="invoice_date" class="form-label">Invoice Date *</label>
                <input type="date" class="form-control" id="invoice_date" name="invoice_date" required>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="mb-3">
                <label for="amount" class="form-label">Amount *</label>
                <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
              </div>
              
              <div class="mb-3">
                <label for="tax_amount" class="form-label">Tax Rate (%) *</label>
                <input type="number" step="0.01" class="form-control" id="tax_amount" name="tax_amount" placeholder="e.g., 6 for 6%" required>
                <small class="text-muted">Enter tax rate percentage (e.g., 6 for 6%)</small>
              </div>
              
              <div class="mb-3">
                <label for="total_amount" class="form-label">Tax Amount (Auto-calculated)</label>
                <input type="number" step="0.01" class="form-control" id="total_amount" name="total_amount" readonly style="background-color: #2a3f5f;">
              </div>
            </div>
          </div>
          
          <div class="mb-3">
            <label for="inventory" class="form-label">Inventory/Items *</label>
            <textarea class="form-control" id="inventory" name="inventory" rows="4" placeholder="List items/inventory details..." required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Save Details</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Supplier Details Modal -->
<div class="modal fade" id="viewSupplierDetailsModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background-color: #1a1d29; border-radius: 16px; border: 1px solid rgba(13, 110, 253, 0.3); box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);">
      <!-- Header -->
      <div class="modal-header" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); border-bottom: none; border-radius: 16px 16px 0 0; padding: 1.5rem 2rem;">
        <h5 class="modal-title fw-bold" style="font-size: 1.35rem; letter-spacing: 0.3px; color: #fff;">
          <i class="fa-solid fa-eye me-2" style="font-size: 1.2rem;"></i> View Document Details
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="opacity: 0.9;"></button>
      </div>
      
      <div class="modal-body" style="padding: 2rem; background-color: #1a1d29;">
        <!-- Document Category Banner -->
        <div class="mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.15) 0%, rgba(13, 110, 253, 0.15) 100%); border-left: 4px solid #0dcaf0; border-radius: 10px; padding: 1rem 1.25rem;">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-info-circle" style="color: #0dcaf0; font-size: 1.25rem;"></i>
            <div>
              <span style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">Document Category</span>
              <div style="color: #fff; font-size: 1.1rem; font-weight: 600; margin-top: 0.15rem;">
                <span id="view_supplier_category"></span>
              </div>
            </div>
          </div>
        </div>
        
        <div class="row g-4">
          <!-- Supplier Information Card -->
          <div class="col-md-6">
            <div style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.15) 0%, rgba(73, 80, 87, 0.15) 100%); border: 1.5px solid rgba(108, 117, 125, 0.3); border-radius: 12px; padding: 1.5rem; height: 100%; transition: all 0.3s ease;">
              <div class="d-flex align-items-center mb-3" style="border-bottom: 2px solid rgba(108, 117, 125, 0.3); padding-bottom: 0.75rem;">
                <i class="fa-solid fa-building me-2" style="color: #6c757d; font-size: 1.15rem;"></i>
                <h6 class="mb-0 fw-bold" style="color: #e9ecef; font-size: 1.05rem;">Supplier Information</h6>
              </div>
              
              <div class="mb-3">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Supplier Name</label>
                <div id="view_supplier_name" style="color: #ffc107; font-size: 1.25rem; font-weight: 600; line-height: 1.3;"></div>
              </div>
              
              <div class="mb-3">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Supplier PI No</label>
                <div id="view_supplier_pi_no" style="color: #0dcaf0; font-size: 1.15rem; font-weight: 600; font-family: 'Courier New', monospace;"></div>
              </div>
              
              <div class="mb-0">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Invoice Date</label>
                <div id="view_invoice_date" style="color: #20c997; font-size: 1.15rem; font-weight: 600;"></div>
              </div>
            </div>
          </div>
          
          <!-- Financial Details Card -->
          <div class="col-md-6">
            <div style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.15) 0%, rgba(73, 80, 87, 0.15) 100%); border: 1.5px solid rgba(108, 117, 125, 0.3); border-radius: 12px; padding: 1.5rem; height: 100%; transition: all 0.3s ease;">
              <div class="d-flex align-items-center mb-3" style="border-bottom: 2px solid rgba(108, 117, 125, 0.3); padding-bottom: 0.75rem;">
                <i class="fa-solid fa-money-bill me-2" style="color: #6c757d; font-size: 1.15rem;"></i>
                <h6 class="mb-0 fw-bold" style="color: #e9ecef; font-size: 1.05rem;">Financial Details</h6>
              </div>
              
              <div class="mb-3">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Amount</label>
                <div id="view_amount" style="color: #fff; font-size: 1.25rem; font-weight: 700;"></div>
              </div>
              
              <div class="mb-3">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Tax Rate</label>
                <div id="view_tax_rate" style="color: #e9ecef; font-size: 1.15rem; font-weight: 600;"></div>
              </div>
              
              <div class="mb-0">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Tax Amount</label>
                <div id="view_tax_amount" style="color: #ffc107; font-size: 1.35rem; font-weight: 700;"></div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Inventory/Items Card -->
        <div class="mt-4" style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.15) 0%, rgba(73, 80, 87, 0.15) 100%); border: 1.5px solid rgba(108, 117, 125, 0.3); border-radius: 12px; padding: 1.5rem;">
          <div class="d-flex align-items-center mb-3" style="border-bottom: 2px solid rgba(108, 117, 125, 0.3); padding-bottom: 0.75rem;">
            <i class="fa-solid fa-boxes-stacked me-2" style="color: #6c757d; font-size: 1.15rem;"></i>
            <h6 class="mb-0 fw-bold" style="color: #e9ecef; font-size: 1.05rem;">Inventory/Items</h6>
          </div>
          <pre id="view_inventory" class="mb-0" style="white-space: pre-wrap; font-family: inherit; color: #e9ecef; font-size: 0.95rem; line-height: 1.6;"></pre>
        </div>
        
        <!-- Footer Info -->
        <div class="mt-4" style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.1) 0%, rgba(73, 80, 87, 0.1) 100%); border: 1px solid rgba(108, 117, 125, 0.25); border-radius: 10px; padding: 1rem 1.25rem;">
          <div style="display: flex; align-items: center; gap: 0.5rem; color: #adb5bd; font-size: 0.9rem;">
            <i class="fa-solid fa-user" style="color: #6c757d;"></i>
            <span style="font-weight: 500;">Entered by:</span>
            <span id="view_entered_by" style="color: #e9ecef; font-weight: 600;"></span>
            <span style="margin: 0 0.25rem;">on</span>
            <span id="view_entered_at" style="color: #e9ecef; font-weight: 600;"></span>
          </div>
        </div>
      </div>
      
      <div class="modal-footer" style="background-color: #1a1d29; border-top: 2px solid rgba(13, 110, 253, 0.2); padding: 1.25rem 2rem; border-radius: 0 0 16px 16px;">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" 
          style="padding: 0.65rem 2rem; border-radius: 8px; font-weight: 600; font-size: 0.95rem; background-color: #6c757d; border: none; transition: all 0.3s ease;">
          <i class="fa-solid fa-xmark me-2"></i> Close
        </button>
      </div>
    </div>
  </div>
</div>

<style>
/* View Supplier Details Modal Enhancements */
#viewSupplierDetailsModal .modal-content:hover {
  border-color: rgba(13, 110, 253, 0.5);
}

#viewSupplierDetailsModal .btn-secondary:hover {
  background-color: #5c636a !important;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
}

/* Card hover effects */
#viewSupplierDetailsModal [style*="border: 1.5px solid rgba(108, 117, 125"]:hover {
  border-color: rgba(13, 110, 253, 0.4) !important;
  box-shadow: 0 4px 16px rgba(13, 110, 253, 0.1);
  transform: translateY(-2px);
}
</style>

<!-- Customer Details Modal -->
<div class="modal fade" id="customerDetailsModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-file-invoice"></i> Enter Customer Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="customerDetailsForm">
        <div class="modal-body">
          <input type="hidden" id="customer_file_id" name="file_id">
          <input type="hidden" id="customer_doc_id" name="document_id">
          
          <div class="alert alert-info">
            <i class="fa-solid fa-info-circle"></i> <strong>Document Category:</strong> <span id="customer_category_display"></span>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="customer_name" class="form-label">Customer Name *</label>
                <input type="text" class="form-control" id="customer_name" name="customer_name" required>
              </div>
              
              <div class="mb-3">
                <label for="invoice_number" class="form-label">Invoice Number *</label>
                <input type="text" class="form-control" id="invoice_number" name="invoice_number" required>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="mb-3">
                <label for="sales_date" class="form-label">Sales Date *</label>
                <input type="date" class="form-control" id="sales_date" name="sales_date" required>
              </div>
              
              <div class="mb-3">
                <label for="customer_amount" class="form-label">Amount *</label>
                <input type="number" step="0.01" class="form-control" id="customer_amount" name="amount" required>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Save Details</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Customer Details Modal -->
<div class="modal fade" id="viewCustomerDetailsModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background-color: #1a1d29; border-radius: 16px; border: 1px solid rgba(13, 110, 253, 0.3); box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);">
      <div class="modal-header" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); border-bottom: none; border-radius: 16px 16px 0 0; padding: 1.5rem 2rem;">
        <h5 class="modal-title fw-bold" style="font-size: 1.35rem; letter-spacing: 0.3px; color: #fff;">
          <i class="fa-solid fa-eye me-2" style="font-size: 1.2rem;"></i> View Customer Details
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="opacity: 0.9;"></button>
      </div>
      
      <div class="modal-body" style="padding: 2rem; background-color: #1a1d29;">
        <div class="mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.15) 0%, rgba(13, 110, 253, 0.15) 100%); border-left: 4px solid #0dcaf0; border-radius: 10px; padding: 1rem 1.25rem;">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-info-circle" style="color: #0dcaf0; font-size: 1.25rem;"></i>
            <div>
              <span style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">Document Category</span>
              <div style="color: #fff; font-size: 1.1rem; font-weight: 600; margin-top: 0.15rem;">
                <span id="view_customer_category"></span>
              </div>
            </div>
          </div>
        </div>
        
        <div class="row g-4">
          <div class="col-md-6">
            <div style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.15) 0%, rgba(73, 80, 87, 0.15) 100%); border: 1.5px solid rgba(108, 117, 125, 0.3); border-radius: 12px; padding: 1.5rem; height: 100%; transition: all 0.3s ease;">
              <div class="d-flex align-items-center mb-3" style="border-bottom: 2px solid rgba(108, 117, 125, 0.3); padding-bottom: 0.75rem;">
                <i class="fa-solid fa-user me-2" style="color: #6c757d; font-size: 1.15rem;"></i>
                <h6 class="mb-0 fw-bold" style="color: #e9ecef; font-size: 1.05rem;">Customer Information</h6>
              </div>
              
              <div class="mb-3">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Customer Name</label>
                <div id="view_customer_name" style="color: #ffc107; font-size: 1.25rem; font-weight: 600; line-height: 1.3;"></div>
              </div>
              
              <div class="mb-0">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Invoice Number</label>
                <div id="view_invoice_number" style="color: #0dcaf0; font-size: 1.15rem; font-weight: 600; font-family: 'Courier New', monospace;"></div>
              </div>
            </div>
          </div>
          
          <div class="col-md-6">
            <div style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.15) 0%, rgba(73, 80, 87, 0.15) 100%); border: 1.5px solid rgba(108, 117, 125, 0.3); border-radius: 12px; padding: 1.5rem; height: 100%; transition: all 0.3s ease;">
              <div class="d-flex align-items-center mb-3" style="border-bottom: 2px solid rgba(108, 117, 125, 0.3); padding-bottom: 0.75rem;">
                <i class="fa-solid fa-money-bill me-2" style="color: #6c757d; font-size: 1.15rem;"></i>
                <h6 class="mb-0 fw-bold" style="color: #e9ecef; font-size: 1.05rem;">Sales Details</h6>
              </div>
              
              <div class="mb-3">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Sales Date</label>
                <div id="view_sales_date" style="color: #20c997; font-size: 1.15rem; font-weight: 600;"></div>
              </div>
              
              <div class="mb-0">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Amount</label>
                <div id="view_customer_amount" style="color: #fff; font-size: 1.35rem; font-weight: 700;"></div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="mt-4 pt-3" style="border-top: 2px solid rgba(108, 117, 125, 0.2); color: #adb5bd; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
          <i class="fa-solid fa-user" style="color: #6c757d;"></i>
          <span style="font-weight: 500;">Entered by:</span>
          <span id="view_customer_entered_by" style="color: #e9ecef; font-weight: 600;"></span>
          <span style="margin: 0 0.25rem;">on</span>
          <span id="view_customer_entered_at" style="color: #e9ecef; font-weight: 600;"></span>
        </div>
      </div>
      
      <div class="modal-footer" style="background-color: #1a1d29; border-top: 2px solid rgba(13, 110, 253, 0.2); padding: 1.25rem 2rem; border-radius: 0 0 16px 16px;">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" 
          style="padding: 0.65rem 2rem; border-radius: 8px; font-weight: 600; font-size: 0.95rem; background-color: #6c757d; border: none; transition: all 0.3s ease;">
          <i class="fa-solid fa-xmark me-2"></i> Close
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Bank Details Modal -->
<div class="modal fade" id="bankDetailsModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-building-columns"></i> Enter Bank Statement Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="bankDetailsForm">
        <div class="modal-body">
          <input type="hidden" id="bank_file_id" name="file_id">
          <input type="hidden" id="bank_doc_id" name="document_id">
          
          <div class="alert alert-info">
            <i class="fa-solid fa-info-circle"></i> <strong>Document Category:</strong> <span id="bank_category_display"></span>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="bank_name" class="form-label">Bank Name *</label>
                <input type="text" class="form-control" id="bank_name" name="bank_name" placeholder="e.g., Maybank, CIMB" required>
              </div>
              
              <div class="mb-3">
                <label for="account_number" class="form-label">Account Number *</label>
                <input type="text" class="form-control" id="account_number" name="account_number" required>
              </div>
              
              <div class="mb-3">
                <label for="statement_period" class="form-label">Statement Period *</label>
                <input type="text" class="form-control" id="statement_period" name="statement_period" placeholder="e.g., Jan 2025 - Feb 2025" required>
                <small class="text-muted">Enter the start and end date of the statement period</small>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="mb-3">
                <label for="total_debit" class="form-label">Total Debit (Outgoing) *</label>
                <input type="number" step="0.01" class="form-control" id="total_debit" name="total_debit" required>
                <small class="text-muted">Total outgoing for the period</small>
              </div>
              
              <div class="mb-3">
                <label for="total_credit" class="form-label">Total Credit (Incoming) *</label>
                <input type="number" step="0.01" class="form-control" id="total_credit" name="total_credit" required>
                <small class="text-muted">Total incoming for the period</small>
              </div>
              
              <div class="mb-3">
                <label for="balance" class="form-label">Ending Balance *</label>
                <input type="number" step="0.01" class="form-control" id="balance" name="balance" required>
                <small class="text-muted">Bank balance at end of period</small>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Save Details</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Bank Details Modal -->
<div class="modal fade" id="viewBankDetailsModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background-color: #1a1d29; border-radius: 16px; border: 1px solid rgba(13, 110, 253, 0.3); box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);">
      <div class="modal-header" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); border-bottom: none; border-radius: 16px 16px 0 0; padding: 1.5rem 2rem;">
        <h5 class="modal-title fw-bold" style="font-size: 1.35rem; letter-spacing: 0.3px; color: #fff;">
          <i class="fa-solid fa-eye me-2" style="font-size: 1.2rem;"></i> View Bank Statement Details
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="opacity: 0.9;"></button>
      </div>
      
      <div class="modal-body" style="padding: 2rem; background-color: #1a1d29;">
        <div class="mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.15) 0%, rgba(13, 110, 253, 0.15) 100%); border-left: 4px solid #0dcaf0; border-radius: 10px; padding: 1rem 1.25rem;">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-info-circle" style="color: #0dcaf0; font-size: 1.25rem;"></i>
            <div>
              <span style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">Document Category</span>
              <div style="color: #fff; font-size: 1.1rem; font-weight: 600; margin-top: 0.15rem;">
                <span id="view_bank_category"></span>
              </div>
            </div>
          </div>
        </div>
        
        <div class="row g-4">
          <div class="col-md-6">
            <div style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.15) 0%, rgba(73, 80, 87, 0.15) 100%); border: 1.5px solid rgba(108, 117, 125, 0.3); border-radius: 12px; padding: 1.5rem; height: 100%; transition: all 0.3s ease;">
              <div class="d-flex align-items-center mb-3" style="border-bottom: 2px solid rgba(108, 117, 125, 0.3); padding-bottom: 0.75rem;">
                <i class="fa-solid fa-building-columns me-2" style="color: #6c757d; font-size: 1.15rem;"></i>
                <h6 class="mb-0 fw-bold" style="color: #e9ecef; font-size: 1.05rem;">Bank Information</h6>
              </div>
              
              <div class="mb-3">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Bank Name</label>
                <div id="view_bank_name" style="color: #ffc107; font-size: 1.25rem; font-weight: 600; line-height: 1.3;"></div>
              </div>
              
              <div class="mb-3">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Account Number</label>
                <div id="view_account_number" style="color: #0dcaf0; font-size: 1.15rem; font-weight: 600; font-family: 'Courier New', monospace;"></div>
              </div>
              
              <div class="mb-0">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Statement Period</label>
                <div id="view_statement_period" style="color: #20c997; font-size: 1.15rem; font-weight: 600;"></div>
              </div>
            </div>
          </div>
          
          <div class="col-md-6">
            <div style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.15) 0%, rgba(73, 80, 87, 0.15) 100%); border: 1.5px solid rgba(108, 117, 125, 0.3); border-radius: 12px; padding: 1.5rem; height: 100%; transition: all 0.3s ease;">
              <div class="d-flex align-items-center mb-3" style="border-bottom: 2px solid rgba(108, 117, 125, 0.3); padding-bottom: 0.75rem;">
                <i class="fa-solid fa-money-bill-transfer me-2" style="color: #6c757d; font-size: 1.15rem;"></i>
                <h6 class="mb-0 fw-bold" style="color: #e9ecef; font-size: 1.05rem;">Transaction Summary</h6>
              </div>
              
              <div class="mb-3">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Total Debit (Outgoing)</label>
                <div id="view_total_debit" style="color: #dc3545; font-size: 1.25rem; font-weight: 700;"></div>
              </div>
              
              <div class="mb-3">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Total Credit (Incoming)</label>
                <div id="view_total_credit" style="color: #28a745; font-size: 1.25rem; font-weight: 700;"></div>
              </div>
              
              <div class="mb-0">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Ending Balance</label>
                <div id="view_balance" style="color: #fff; font-size: 1.35rem; font-weight: 700;"></div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="mt-4 pt-3" style="border-top: 2px solid rgba(108, 117, 125, 0.2); color: #adb5bd; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
          <i class="fa-solid fa-user" style="color: #6c757d;"></i>
          <span style="font-weight: 500;">Entered by:</span>
          <span id="view_bank_entered_by" style="color: #e9ecef; font-weight: 600;"></span>
          <span style="margin: 0 0.25rem;">on</span>
          <span id="view_bank_entered_at" style="color: #e9ecef; font-weight: 600;"></span>
        </div>
      </div>
      
      <div class="modal-footer" style="background-color: #1a1d29; border-top: 2px solid rgba(13, 110, 253, 0.2); padding: 1.25rem 2rem; border-radius: 0 0 16px 16px;">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" 
          style="padding: 0.65rem 2rem; border-radius: 8px; font-weight: 600; font-size: 0.95rem; background-color: #6c757d; border: none; transition: all 0.3s ease;">
          <i class="fa-solid fa-xmark me-2"></i> Close
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Government Details Modal -->
<div class="modal fade" id="governmentDetailsModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-landmark"></i> Enter Government Submission Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="governmentDetailsForm">
        <div class="modal-body">
          <input type="hidden" id="gov_file_id" name="file_id">
          <input type="hidden" id="gov_doc_id" name="document_id">
          
          <div class="alert alert-info">
            <i class="fa-solid fa-info-circle"></i> <strong>Document Category:</strong> <span id="gov_category_display"></span>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="agency_name" class="form-label">Government Agency *</label>
                <input type="text" class="form-control" id="agency_name" name="agency_name" placeholder="e.g., LHDN, KWSP, PERKESO, Customs" required>
                <small class="text-muted">Name of the government agency</small>
              </div>
              
              <div class="mb-3">
                <label for="reference_no" class="form-label">Reference Number *</label>
                <input type="text" class="form-control" id="reference_no" name="reference_no" required>
                <small class="text-muted">Official reference or submission number</small>
              </div>
              
              <div class="mb-3">
                <label for="period_covered" class="form-label">Period Covered *</label>
                <input type="text" class="form-control" id="period_covered" name="period_covered" placeholder="e.g., January 2025, Q1 2025" required>
                <small class="text-muted">The month/quarter covered by this submission</small>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="mb-3">
                <label for="submission_date" class="form-label">Submission Date *</label>
                <input type="date" class="form-control" id="submission_date" name="submission_date" required>
                <small class="text-muted">When submitted to the government</small>
              </div>
              
              <div class="mb-3">
                <label for="amount_paid" class="form-label">Amount Paid *</label>
                <input type="number" step="0.01" class="form-control" id="amount_paid" name="amount_paid" required>
                <small class="text-muted">Total statutory or tax payment made</small>
              </div>
              
              <div class="mb-3">
                <label for="acknowledgement_file" class="form-label">Acknowledgement File</label>
                <input type="text" class="form-control" id="acknowledgement_file" name="acknowledgement_file" placeholder="e.g., e-Daftar, e-Data file reference">
                <small class="text-muted">Proof of submission reference (optional)</small>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Save Details</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Government Details Modal -->
<div class="modal fade" id="viewGovernmentDetailsModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background-color: #1a1d29; border-radius: 16px; border: 1px solid rgba(13, 110, 253, 0.3); box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);">
      <div class="modal-header" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); border-bottom: none; border-radius: 16px 16px 0 0; padding: 1.5rem 2rem;">
        <h5 class="modal-title fw-bold" style="font-size: 1.35rem; letter-spacing: 0.3px; color: #fff;">
          <i class="fa-solid fa-eye me-2" style="font-size: 1.2rem;"></i> View Government Submission Details
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="opacity: 0.9;"></button>
      </div>
      
      <div class="modal-body" style="padding: 2rem; background-color: #1a1d29;">
        <div class="mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.15) 0%, rgba(13, 110, 253, 0.15) 100%); border-left: 4px solid #0dcaf0; border-radius: 10px; padding: 1rem 1.25rem;">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-info-circle" style="color: #0dcaf0; font-size: 1.25rem;"></i>
            <div>
              <span style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">Document Category</span>
              <div style="color: #fff; font-size: 1.1rem; font-weight: 600; margin-top: 0.15rem;">
                <span id="view_gov_category"></span>
              </div>
            </div>
          </div>
        </div>
        
        <div class="row g-4">
          <div class="col-md-6">
            <div style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.15) 0%, rgba(73, 80, 87, 0.15) 100%); border: 1.5px solid rgba(108, 117, 125, 0.3); border-radius: 12px; padding: 1.5rem; height: 100%; transition: all 0.3s ease;">
              <div class="d-flex align-items-center mb-3" style="border-bottom: 2px solid rgba(108, 117, 125, 0.3); padding-bottom: 0.75rem;">
                <i class="fa-solid fa-landmark me-2" style="color: #6c757d; font-size: 1.15rem;"></i>
                <h6 class="mb-0 fw-bold" style="color: #e9ecef; font-size: 1.05rem;">Agency Information</h6>
              </div>
              
              <div class="mb-3">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Government Agency</label>
                <div id="view_agency_name" style="color: #ffc107; font-size: 1.25rem; font-weight: 600; line-height: 1.3;"></div>
              </div>
              
              <div class="mb-3">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Reference Number</label>
                <div id="view_reference_no" style="color: #0dcaf0; font-size: 1.15rem; font-weight: 600; font-family: 'Courier New', monospace;"></div>
              </div>
              
              <div class="mb-0">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Period Covered</label>
                <div id="view_period_covered" style="color: #20c997; font-size: 1.15rem; font-weight: 600;"></div>
              </div>
            </div>
          </div>
          
          <div class="col-md-6">
            <div style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.15) 0%, rgba(73, 80, 87, 0.15) 100%); border: 1.5px solid rgba(108, 117, 125, 0.3); border-radius: 12px; padding: 1.5rem; height: 100%; transition: all 0.3s ease;">
              <div class="d-flex align-items-center mb-3" style="border-bottom: 2px solid rgba(108, 117, 125, 0.3); padding-bottom: 0.75rem;">
                <i class="fa-solid fa-file-invoice-dollar me-2" style="color: #6c757d; font-size: 1.15rem;"></i>
                <h6 class="mb-0 fw-bold" style="color: #e9ecef; font-size: 1.05rem;">Payment Details</h6>
              </div>
              
              <div class="mb-3">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Submission Date</label>
                <div id="view_submission_date" style="color: #e9ecef; font-size: 1.15rem; font-weight: 600;"></div>
              </div>
              
              <div class="mb-3">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Amount Paid</label>
                <div id="view_amount_paid" style="color: #28a745; font-size: 1.35rem; font-weight: 700;"></div>
              </div>
              
              <div class="mb-0">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Acknowledgement File</label>
                <div id="view_acknowledgement_file" style="color: #17a2b8; font-size: 1rem; font-weight: 600; word-break: break-all;"></div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="mt-4 pt-3" style="border-top: 2px solid rgba(108, 117, 125, 0.2); color: #adb5bd; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
          <i class="fa-solid fa-user" style="color: #6c757d;"></i>
          <span style="font-weight: 500;">Entered by:</span>
          <span id="view_gov_entered_by" style="color: #e9ecef; font-weight: 600;"></span>
          <span style="margin: 0 0.25rem;">on</span>
          <span id="view_gov_entered_at" style="color: #e9ecef; font-weight: 600;"></span>
        </div>
      </div>
      
      <div class="modal-footer" style="background-color: #1a1d29; border-top: 2px solid rgba(13, 110, 253, 0.2); padding: 1.25rem 2rem; border-radius: 0 0 16px 16px;">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" 
          style="padding: 0.65rem 2rem; border-radius: 8px; font-weight: 600; font-size: 0.95rem; background-color: #6c757d; border: none; transition: all 0.3s ease;">
          <i class="fa-solid fa-xmark me-2"></i> Close
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Client Details Modal -->
<div class="modal fade" id="clientDetailsModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-users"></i> Enter Client/Employee Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="clientDetailsForm">
        <div class="modal-body">
          <input type="hidden" id="client_file_id" name="file_id">
          <input type="hidden" id="client_doc_id" name="document_id">
          
          <div class="alert alert-info">
            <i class="fa-solid fa-info-circle"></i> <strong>Document Category:</strong> <span id="client_category_display"></span>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="employee_name" class="form-label">Employee Name</label>
                <input type="text" class="form-control" id="employee_name" name="employee_name" placeholder="e.g., John Doe">
                <small class="text-muted">Name of the employee (if applicable)</small>
              </div>
              
              <div class="mb-3">
                <label for="department" class="form-label">Department/Project *</label>
                <input type="text" class="form-control" id="department" name="department" placeholder="e.g., Finance, Marketing" required>
                <small class="text-muted">Related department or project</small>
              </div>
              
              <div class="mb-3">
                <label for="claim_date" class="form-label">Claim/Submission Date *</label>
                <input type="date" class="form-control" id="claim_date" name="claim_date" required>
                <small class="text-muted">Submission or claim date</small>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="mb-3">
                <label for="client_amount" class="form-label">Amount *</label>
                <input type="number" step="0.01" class="form-control" id="client_amount" name="amount" required>
                <small class="text-muted">Total claim or payroll value</small>
              </div>
              
              <div class="mb-3">
                <label for="approved_by" class="form-label">Approved By *</label>
                <input type="text" class="form-control" id="approved_by" name="approved_by" placeholder="e.g., Manager Name" required>
                <small class="text-muted">Supervisor or manager who approved</small>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Save Details</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Client Details Modal -->
<div class="modal fade" id="viewClientDetailsModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background-color: #1a1d29; border-radius: 16px; border: 1px solid rgba(13, 110, 253, 0.3); box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);">
      <div class="modal-header" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); border-bottom: none; border-radius: 16px 16px 0 0; padding: 1.5rem 2rem;">
        <h5 class="modal-title fw-bold" style="font-size: 1.35rem; letter-spacing: 0.3px; color: #fff;">
          <i class="fa-solid fa-eye me-2" style="font-size: 1.2rem;"></i> View Client/Employee Details
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="opacity: 0.9;"></button>
      </div>
      
      <div class="modal-body" style="padding: 2rem; background-color: #1a1d29;">
        <div class="mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.15) 0%, rgba(13, 110, 253, 0.15) 100%); border-left: 4px solid #0dcaf0; border-radius: 10px; padding: 1rem 1.25rem;">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-info-circle" style="color: #0dcaf0; font-size: 1.25rem;"></i>
            <div>
              <span style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">Document Category</span>
              <div style="color: #fff; font-size: 1.1rem; font-weight: 600; margin-top: 0.15rem;">
                <span id="view_client_category"></span>
              </div>
            </div>
          </div>
        </div>
        
        <div class="row g-4">
          <div class="col-md-6">
            <div style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.15) 0%, rgba(73, 80, 87, 0.15) 100%); border: 1.5px solid rgba(108, 117, 125, 0.3); border-radius: 12px; padding: 1.5rem; height: 100%; transition: all 0.3s ease;">
              <div class="d-flex align-items-center mb-3" style="border-bottom: 2px solid rgba(108, 117, 125, 0.3); padding-bottom: 0.75rem;">
                <i class="fa-solid fa-user me-2" style="color: #6c757d; font-size: 1.15rem;"></i>
                <h6 class="mb-0 fw-bold" style="color: #e9ecef; font-size: 1.05rem;">Employee Information</h6>
              </div>
              
              <div class="mb-3">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Employee Name</label>
                <div id="view_employee_name" style="color: #17a2b8; font-size: 1.25rem; font-weight: 600; line-height: 1.3;"></div>
              </div>
              
              <div class="mb-3">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Department/Project</label>
                <div id="view_department" style="color: #ffc107; font-size: 1.15rem; font-weight: 600;"></div>
              </div>
              
              <div class="mb-0">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Claim Date</label>
                <div id="view_claim_date" style="color: #e9ecef; font-size: 1.15rem; font-weight: 600;"></div>
              </div>
            </div>
          </div>
          
          <div class="col-md-6">
            <div style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.15) 0%, rgba(73, 80, 87, 0.15) 100%); border: 1.5px solid rgba(108, 117, 125, 0.3); border-radius: 12px; padding: 1.5rem; height: 100%; transition: all 0.3s ease;">
              <div class="d-flex align-items-center mb-3" style="border-bottom: 2px solid rgba(108, 117, 125, 0.3); padding-bottom: 0.75rem;">
                <i class="fa-solid fa-file-invoice-dollar me-2" style="color: #6c757d; font-size: 1.15rem;"></i>
                <h6 class="mb-0 fw-bold" style="color: #e9ecef; font-size: 1.05rem;">Claim Details</h6>
              </div>
              
              <div class="mb-3">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Amount</label>
                <div id="view_client_amount" style="color: #28a745; font-size: 1.35rem; font-weight: 700;"></div>
              </div>
              
              <div class="mb-0">
                <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Approved By</label>
                <div id="view_approved_by" style="color: #20c997; font-size: 1.15rem; font-weight: 600;"></div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="mt-4 pt-3" style="border-top: 2px solid rgba(108, 117, 125, 0.2); color: #adb5bd; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
          <i class="fa-solid fa-user" style="color: #6c757d;"></i>
          <span style="font-weight: 500;">Entered by:</span>
          <span id="view_client_entered_by" style="color: #e9ecef; font-weight: 600;"></span>
          <span style="margin: 0 0.25rem;">on</span>
          <span id="view_client_entered_at" style="color: #e9ecef; font-weight: 600;"></span>
        </div>
      </div>
      
      <div class="modal-footer" style="background-color: #1a1d29; border-top: 2px solid rgba(13, 110, 253, 0.2); padding: 1.25rem 2rem; border-radius: 0 0 16px 16px;">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" 
          style="padding: 0.65rem 2rem; border-radius: 8px; font-weight: 600; font-size: 0.95rem; background-color: #6c757d; border: none; transition: all 0.3s ease;">
          <i class="fa-solid fa-xmark me-2"></i> Close
        </button>
      </div>
    </div>
  </div>
</div>

<!-- View Document Modal -->
<div class="modal fade" id="viewDocumentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-file-lines"></i> Document Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Document Header with Status Badge -->
        <div class="document-header-section">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div class="flex-grow-1">
              <h3 class="document-title-main" id="view-doc-title-header"></h3>
              <div class="document-meta-badges">
                <span class="meta-badge">
                  <i class="fa-solid fa-hashtag"></i>
                  <span id="view-doc-id-badge"></span>
                </span>
                <span class="meta-badge">
                  <i class="fa-solid fa-tag"></i>
                  <span id="view-doc-type-badge"></span>
                </span>
                <span class="status-badge-large" id="view-doc-status-badge"></span>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <!-- Left Column -->
          <div class="col-md-6">
            <!-- Document Information -->
            <div class="card-detail">
              <h4><i class="fa-solid fa-info-circle"></i> Document Information</h4>
              <div class="detail-item">
                <strong>Title:</strong>
                <span id="view-doc-title"></span>
              </div>
              <div class="detail-item">
                <strong>Company:</strong>
                <span id="view-doc-company"></span>
              </div>
              <div class="detail-item">
                <strong>Source Type:</strong>
                <span id="view-doc-source-type"></span>
              </div>
              <div class="detail-item">
                <strong>Type:</strong>
                <span id="view-doc-type"></span>
              </div>
              <div class="detail-item">
                <strong>Description:</strong>
                <span id="view-doc-description" style="color: #b8d4ff;"></span>
              </div>
            </div>
            
            <!-- Uploaded Files -->
            <div class="card-detail">
              <h4><i class="fa-solid fa-files"></i> Uploaded Files</h4>
              <div id="view-doc-files-list">
                <div class="text-muted">Loading files...</div>
              </div>
            </div>
            
            <!-- Dates & Location -->
            <div class="card-detail">
              <h4><i class="fa-solid fa-calendar-days"></i> Dates & Location</h4>
              <div class="detail-item">
                <strong>Date of Collect:</strong>
                <span id="view-doc-date"></span>
              </div>
              <div class="detail-item">
                <strong>Location:</strong>
                <span id="view-doc-location"></span>
              </div>
              <div class="detail-item">
                <strong>Created At:</strong>
                <span id="view-doc-created" style="color: #b8d4ff;"></span>
              </div>
              <div class="detail-item">
                <strong>Last Updated:</strong>
                <span id="view-doc-updated" style="color: #b8d4ff;"></span>
              </div>
            </div>
          </div>
          
          <!-- Right Column -->
          <div class="col-md-6">
            <!-- Workflow Information -->
            <div class="card-detail">
              <h4><i class="fa-solid fa-diagram-project"></i> Workflow Information</h4>
              <div class="detail-item">
                <strong>Created By:</strong>
                <span>
                  <i class="fa-solid fa-user-circle text-info"></i>
                  <span id="view-doc-creator"></span>
                </span>
              </div>
              <div class="detail-item">
                <strong>Current Handler:</strong>
                <span>
                  <i class="fa-solid fa-user-check text-success"></i>
                  <span id="view-doc-handler"></span>
                </span>
              </div>
              <div class="detail-item">
                <strong>Handler Phone:</strong>
                <span>
                  <i class="fa-solid fa-phone text-warning"></i>
                  <span id="view-doc-handler-phone"></span>
                </span>
              </div>
            </div>

            <!-- Workflow Progress Indicator -->
            <div class="card-detail">
              <h4><i class="fa-solid fa-list-check"></i> Workflow Progress</h4>
              <div class="workflow-progress-container">
                <div class="workflow-step" id="workflow-step-pending">
                  <div class="workflow-step-icon">
                    <i class="fa-solid fa-clock"></i>
                  </div>
                  <div class="workflow-step-content">
                    <div class="workflow-step-title">Pending</div>
                    <div class="workflow-step-subtitle">Awaiting Review</div>
                  </div>
                </div>
                <div class="workflow-step" id="workflow-step-reviewed">
                  <div class="workflow-step-icon">
                    <i class="fa-solid fa-eye"></i>
                  </div>
                  <div class="workflow-step-content">
                    <div class="workflow-step-title">Reviewed</div>
                    <div class="workflow-step-subtitle">Accountant Reviewed</div>
                  </div>
                </div>
                <div class="workflow-step" id="workflow-step-approved">
                  <div class="workflow-step-icon">
                    <i class="fa-solid fa-check"></i>
                  </div>
                  <div class="workflow-step-content">
                    <div class="workflow-step-title">Approved</div>
                    <div class="workflow-step-subtitle">Manager Approved</div>
                  </div>
                </div>
                <div class="workflow-step" id="workflow-step-final">
                  <div class="workflow-step-icon">
                    <i class="fa-solid fa-check-double"></i>
                  </div>
                  <div class="workflow-step-content">
                    <div class="workflow-step-title">Final Approved</div>
                    <div class="workflow-step-subtitle">Auditor Approved</div>
                  </div>
                </div>
                <div class="workflow-step" id="workflow-step-submit">
                  <div class="workflow-step-icon">
                    <i class="fa-solid fa-paper-plane"></i>
                  </div>
                  <div class="workflow-step-content">
                    <div class="workflow-step-title">Submitted</div>
                    <div class="workflow-step-subtitle">Sent to Client</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Document History/Audit Trail -->
        <div class="row mt-4">
          <div class="col-12">
            <div class="card-detail" style="background: linear-gradient(135deg, #1a1f2e 0%, #252d3d 100%); border: 1px solid rgba(0, 114, 255, 0.3); box-shadow: 0 4px 20px rgba(0, 114, 255, 0.15);">
              <h4 style="color: #00c6ff; font-weight: 700; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid rgba(0, 114, 255, 0.3);"><i class="fa-solid fa-clock-rotate-left" style="margin-right: 10px;"></i> Document History & Audit Trail</h4>
              <div id="document-history-container" class="table-responsive" style="border-radius: 12px; overflow: hidden;">
                <table class="table table-dark mb-0 history-table">
                  <thead>
                    <tr>
                      <th style="width: 15%;"><i class="fa-solid fa-calendar"></i> DATE/TIME</th>
                      <th style="width: 15%;"><i class="fa-solid fa-bolt"></i> ACTION</th>
                      <th style="width: 25%;"><i class="fa-solid fa-arrow-right"></i> STATUS CHANGE</th>
                      <th style="width: 18%;"><i class="fa-solid fa-user"></i> PERFORMED BY</th>
                      <th style="width: 27%;"><i class="fa-solid fa-comment"></i> COMMENTS</th>
                    </tr>
                  </thead>
                  <tbody id="document-history-body">
                    <tr><td colspan="5" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Loading history...</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        
      </div>
      <div class="modal-footer" id="document-action-buttons">
        <!-- Action buttons will be dynamically inserted here based on role and status -->
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-user"></i> User Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="text-center mb-4">
          <div id="view-user-picture" class="profile-picture-placeholder-lg"></div>
          <h4 id="view-user-name" class="mt-2"></h4>
          <span id="view-user-role" class="badge bg-primary"></span>
        </div>
        <div class="card-detail">
          <h4><i class="fa-solid fa-id-card"></i> Basic Information</h4>
          <div class="detail-item"><strong>User ID:</strong> <span id="view-user-id"></span></div>
          <div class="detail-item"><strong>Email:</strong> <span id="view-user-email"></span></div>
          <div class="detail-item"><strong>Phone:</strong> <span id="view-user-phone"></span></div>
        </div>
        <div class="card-detail">
          <h4><i class="fa-solid fa-calendar"></i> Account Information</h4>
          <div class="detail-item"><strong>Status:</strong> <span class="badge bg-success">Active</span></div>
          <div class="detail-item"><strong>Registration Date:</strong> <span id="view-user-regdate">N/A</span></div>
          <div class="detail-item"><strong>Last Login:</strong> <span id="view-user-lastlogin">N/A</span></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- View Admin Modal -->
<div class="modal fade" id="viewAdminModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-user-shield"></i> Admin Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="text-center mb-4">
          <div id="view-admin-picture" class="profile-picture-placeholder-lg"></div>
          <h4 id="view-admin-name" class="mt-2"></h4>
          <span class="badge bg-primary">Administrator</span>
        </div>
        <div class="card-detail">
          <h4><i class="fa-solid fa-id-card"></i> Basic Information</h4>
          <div class="detail-item"><strong>Admin ID:</strong> <span id="view-admin-id"></span></div>
          <div class="detail-item"><strong>Email:</strong> <span id="view-admin-email"></span></div>
          <div class="detail-item"><strong>Phone:</strong> <span id="view-admin-phone"></span></div>
        </div>
        <div class="card-detail">
          <h4><i class="fa-solid fa-calendar"></i> Account Information</h4>
          <div class="detail-item"><strong>Last Login:</strong> <span id="view-admin-lastlogin"></span></div>
          <div class="detail-item"><strong>Account Created:</strong> <span id="view-admin-created">N/A</span></div>
        </div>
        <div class="card-detail">
          <h4><i class="fa-solid fa-key"></i> Permissions</h4>
          <div class="detail-item"><strong>Access Level:</strong> <span class="badge bg-info">Full Access</span></div>
          <div class="detail-item"><strong>Can Manage Users:</strong> <span class="badge bg-success">Yes</span></div>
          <div class="detail-item"><strong>Can Manage Admins:</strong> <span class="badge bg-success">Yes</span></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- COMPANY DETAILS MODAL -->
<div class="modal fade" id="companyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-building"></i> Company Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="companyDetailsContent">
        <!-- Export/Print Buttons -->
        <div class="mb-2 d-flex gap-2">
          <button class="btn btn-outline-danger btn-sm" onclick="exportModalToPDF('companyDetailsContent', 'Company_Details')">
            <i class="fa-solid fa-file-pdf"></i> Export to PDF
          </button>
          <button class="btn btn-outline-primary btn-sm" onclick="printModalContent('companyDetailsContent')">
            <i class="fa-solid fa-print"></i> Print
          </button>
        </div>
        <!-- Action Buttons -->
        <div class="d-flex justify-content-between mb-4">
          <div>
            <button class="btn btn-warning btn-sm" onclick="editCompany()">
              <i class="fa-solid fa-edit"></i> Edit Company
            </button>
            <button class="btn btn-danger btn-sm" onclick="deleteCompany()">
              <i class="fa-solid fa-trash"></i> Delete Company
            </button>
          </div>
          <button class="btn btn-primary btn-sm" onclick="refreshCompanyData()">
            <i class="fa-solid fa-refresh"></i> Refresh
          </button>
        </div>

        <!-- Company Information -->
        <div class="card-detail">
          <h4><i class="fa-solid fa-building"></i> Company Information</h4>
          <div class="row">
            <div class="col-md-6">
              <div class="detail-item"><strong>ID:</strong> <span id="detail-id"></span></div>
              <div class="detail-item"><strong>Name:</strong> <span id="detail-name"></span></div>
              <div class="detail-item"><strong>SSM No:</strong> <span id="detail-ssm"></span></div>
              <div class="detail-item"><strong>Type:</strong> <span id="detail-type"></span></div>
              <div class="detail-item"><strong>Sub Type:</strong> <span id="detail-subtype"></span></div>
            </div>
            <div class="col-md-6">
              <div class="detail-item"><strong>Incorporation Date:</strong> <span id="detail-incdate"></span></div>
              <div class="detail-item"><strong>Financial Year End:</strong> <span id="detail-fye"></span></div>
              <div class="detail-item"><strong>Subsequent Year End:</strong> <span id="detail-subsequent-year-end"></span></div>
            </div>
          </div>
        </div>

        <!-- Business Information -->
        <div class="card-detail">
          <h4><i class="fa-solid fa-briefcase"></i> Business Information</h4>
          <div class="detail-item">
            <div style="color: #0dcaf0; margin-bottom: 1rem;">MSIC Codes & Nature of Business:</div>
            <div id="detail-msic-codes">
              <div id="detail-msic-1" style="display: none; margin-bottom: 1rem;">
                <div style="margin-bottom: 0.5rem; margin-left: 1rem;"><span style="color: #0dcaf0;">Code:</span> <span id="detail-msic-code-1" style="color: #ffffff;"></span></div>
                <div style="margin-left: 1rem;"><span style="color: #0dcaf0;">Nature of Business:</span> <span id="detail-msic-nature-1" style="color: #ffffff;"></span></div>
              </div>
              <div id="detail-msic-2" style="display: none; margin-bottom: 1rem;">
                <div style="margin-bottom: 0.5rem; margin-left: 1rem;"><span style="color: #0dcaf0;">Code:</span> <span id="detail-msic-code-2" style="color: #ffffff;"></span></div>
                <div style="margin-left: 1rem;"><span style="color: #0dcaf0;">Nature of Business:</span> <span id="detail-msic-nature-2" style="color: #ffffff;"></span></div>
              </div>
              <div id="detail-msic-3" style="display: none; margin-bottom: 1rem;">
                <div style="margin-bottom: 0.5rem; margin-left: 1rem;"><span style="color: #0dcaf0;">Code:</span> <span id="detail-msic-code-3" style="color: #ffffff;"></span></div>
                <div style="margin-left: 1rem;"><span style="color: #0dcaf0;">Nature of Business:</span> <span id="detail-msic-nature-3" style="color: #ffffff;"></span></div>
              </div>
              <div id="detail-msic-none" style="color: #6c757d;">N/A</div>
            </div>
          </div>
          <div class="detail-item"><strong>Business Description:</strong> <div id="detail-description" style="margin-top: 0.5rem; color: #e9ecef; line-height: 1.6;"></div></div>
        </div>

        <!-- Contact Information -->
        <div class="card-detail">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <h4 class="mb-1" style="color: #0dcaf0; font-weight: 700; font-size: 1.5rem;">
                <i class="fa-solid fa-building-user me-2"></i> Business Contact Details
              </h4>
              <p class="mb-0" style="color: #6c757d; font-size: 0.9rem;">Official company addresses and communication channels</p>
            </div>
          </div>
          <div class="row g-4">
            <!-- Address Information -->
            <div class="col-md-6">
              <div style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 202, 240, 0.1) 100%); padding: 1.5rem; border-radius: 8px; border: 1px solid rgba(13, 202, 240, 0.2);">
                <h5 style="color: #0dcaf0; font-size: 1.1rem; font-weight: 600; margin-bottom: 1.25rem;">
                  <i class="fa-solid fa-location-dot me-2"></i> Company Addresses
                </h5>
                <div class="mb-3">
                  <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">Registered Address</label>
                  <div id="detail-address" style="color: #e9ecef; font-size: 1rem; line-height: 1.5; word-wrap: break-word;"></div>
                </div>
                <div>
                  <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">Business Address</label>
                  <div id="detail-business-address" style="color: #e9ecef; font-size: 1rem; line-height: 1.5; word-wrap: break-word;"></div>
                </div>
              </div>
            </div>
            <!-- Communication Details -->
            <div class="col-md-6">
              <div style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 202, 240, 0.1) 100%); padding: 1.5rem; border-radius: 8px; border: 1px solid rgba(13, 202, 240, 0.2);">
                <h5 style="color: #0dcaf0; font-size: 1.1rem; font-weight: 600; margin-bottom: 1.25rem;">
                  <i class="fa-solid fa-phone me-2"></i> Contact Numbers & Email
                </h5>
                <div class="mb-3">
                  <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">Office Phone</label>
                  <div id="detail-office" style="color: #20c997; font-size: 1.1rem; font-weight: 600; font-family: 'Courier New', monospace;"></div>
                </div>
                <div class="mb-3">
                  <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">Fax Number</label>
                  <div id="detail-fax" style="color: #20c997; font-size: 1.1rem; font-weight: 600; font-family: 'Courier New', monospace;"></div>
                </div>
                <div>
                  <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">Email Address</label>
                  <div id="detail-email" style="color: #0dcaf0; font-size: 1rem; font-weight: 500; word-break: break-all;"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Contact Persons -->
        <div class="card-detail">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <h4 class="mb-1" style="color: #0dcaf0; font-weight: 700; font-size: 1.5rem;">
                <i class="fa-solid fa-address-book me-2"></i> Key Personnel
              </h4>
              <p class="mb-0" style="color: #6c757d; font-size: 0.9rem;">Primary contact information for accounting and HR departments</p>
            </div>
          </div>
          <div class="row g-4">
            <!-- Accountant Details -->
            <div class="col-md-6">
              <div style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 202, 240, 0.1) 100%); padding: 1.5rem; border-radius: 8px; border: 1px solid rgba(13, 202, 240, 0.2);">
                <h5 style="color: #0dcaf0; font-size: 1.1rem; font-weight: 600; margin-bottom: 1.25rem;">
                  <i class="fa-solid fa-calculator me-2"></i> Accounting Department
                </h5>
                <div class="mb-3">
                  <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">Contact Person</label>
                  <div id="detail-accname" style="color: #e9ecef; font-size: 1.1rem; font-weight: 600;"></div>
                </div>
                <div class="mb-3">
                  <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">Phone Number</label>
                  <div id="detail-accphone" style="color: #20c997; font-size: 1.1rem; font-weight: 600; font-family: 'Courier New', monospace;"></div>
                </div>
                <div>
                  <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">Email Address</label>
                  <div id="detail-accemail" style="color: #0dcaf0; font-size: 1rem; font-weight: 500; word-break: break-all;"></div>
                </div>
              </div>
            </div>
            <!-- HR Details -->
            <div class="col-md-6">
              <div style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 202, 240, 0.1) 100%); padding: 1.5rem; border-radius: 8px; border: 1px solid rgba(13, 202, 240, 0.2);">
                <h5 style="color: #0dcaf0; font-size: 1.1rem; font-weight: 600; margin-bottom: 1.25rem;">
                  <i class="fa-solid fa-users me-2"></i> Human Resources
                </h5>
                <div class="mb-3">
                  <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">Contact Person</label>
                  <div id="detail-hrname" style="color: #e9ecef; font-size: 1.1rem; font-weight: 600;"></div>
                </div>
                <div class="mb-3">
                  <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">Phone Number</label>
                  <div id="detail-hrphone" style="color: #20c997; font-size: 1.1rem; font-weight: 600; font-family: 'Courier New', monospace;"></div>
                </div>
                <div>
                  <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">Email Address</label>
                  <div id="detail-hremail" style="color: #0dcaf0; font-size: 1rem; font-weight: 500; word-break: break-all;"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Members Section -->
        <div class="card-detail">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <h4 class="mb-1" style="color: #0dcaf0; font-weight: 700; font-size: 1.5rem;">
                <i class="fa-solid fa-users me-2"></i> Company Members
              </h4>
              <p class="mb-0" style="color: #6c757d; font-size: 0.9rem;">Comprehensive list of shareholders and their holdings</p>
            </div>
            <button class="btn btn-success" onclick="addMember()" style="padding: 0.5rem 1.25rem; font-weight: 600;">
              <i class="fa-solid fa-plus me-2"></i> Add Member
            </button>
          </div>
          <div style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 202, 240, 0.1) 100%); padding: 1.5rem; border-radius: 8px; border: 1px solid rgba(13, 202, 240, 0.2);">
            <div class="table-responsive">
              <table class="table table-dark table-hover" id="membersTable" style="margin-bottom: 1rem;">
                <thead>
                  <tr style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.3) 0%, rgba(13, 202, 240, 0.3) 100%); border-bottom: 2px solid #0dcaf0;">
                    <th style="padding: 1rem 1.25rem; font-weight: 700; text-align: left; white-space: nowrap; color: #e9ecef;">Name</th>
                    <th style="padding: 1rem 1.25rem; font-weight: 700; text-align: center; white-space: nowrap; color: #e9ecef;">ID No</th>
                    <th style="padding: 1rem 1.25rem; font-weight: 700; text-align: center; white-space: nowrap; color: #e9ecef;">Nationality</th>
                    <th style="padding: 1rem 1.25rem; font-weight: 700; text-align: right; white-space: nowrap; color: #e9ecef;">Price/Share</th>
                    <th style="padding: 1rem 1.25rem; font-weight: 700; text-align: center; white-space: nowrap; color: #e9ecef;">No of Shares</th>
                    <th style="padding: 1rem 1.25rem; font-weight: 700; text-align: center; white-space: nowrap; color: #e9ecef;">Percentage</th>
                    <th style="padding: 1rem 1.25rem; font-weight: 700; text-align: right; white-space: nowrap; color: #e9ecef;">Total</th>
                    <th style="padding: 1rem 1.25rem; font-weight: 700; text-align: center; white-space: nowrap; color: #e9ecef;">Actions</th>
                  </tr>
                </thead>
                <tbody id="membersList">
                  <!-- Members will be loaded here -->
                </tbody>
              </table>
            </div>
            <div class="d-flex gap-3 justify-content-start mt-3">
              <button class="btn btn-outline-danger" onclick="exportTableToPDF('membersTable', 'Members_List')" style="padding: 0.5rem 1.25rem; font-weight: 600;">
                <i class="fa-solid fa-file-pdf me-2"></i> Export PDF
              </button>
              <button class="btn btn-outline-primary" onclick="printTable('membersTable')" style="padding: 0.5rem 1.25rem; font-weight: 600;">
                <i class="fa-solid fa-print me-2"></i> Print Report
              </button>
            </div>
          </div>
        </div>

        <!-- Directors Section -->
        <div class="card-detail">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <h4 class="mb-1" style="color: #0dcaf0; font-weight: 700; font-size: 1.5rem;">
                <i class="fa-solid fa-user-tie me-2"></i> Board of Directors
              </h4>
              <p class="mb-0" style="color: #6c757d; font-size: 0.9rem;">List of company directors and their information</p>
            </div>
            <button class="btn btn-success" onclick="addDirector()" style="padding: 0.5rem 1.25rem; font-weight: 600;">
              <i class="fa-solid fa-plus me-2"></i> Add Director
            </button>
          </div>
          <div style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 202, 240, 0.1) 100%); padding: 1.5rem; border-radius: 8px; border: 1px solid rgba(13, 202, 240, 0.2);">
            <div class="table-responsive">
              <table class="table table-dark table-hover" id="directorsTable" style="margin-bottom: 1rem;">
                <thead>
                  <tr style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.3) 0%, rgba(13, 202, 240, 0.3) 100%); border-bottom: 2px solid #0dcaf0;">
                    <th style="padding: 1rem 1.25rem; font-weight: 700; text-align: left; white-space: nowrap; color: #e9ecef;">Name</th>
                    <th style="padding: 1rem 1.25rem; font-weight: 700; text-align: center; white-space: nowrap; color: #e9ecef;">ID No</th>
                    <th style="padding: 1rem 1.25rem; font-weight: 700; text-align: center; white-space: nowrap; color: #e9ecef;">Nationality</th>
                    <th style="padding: 1rem 1.25rem; font-weight: 700; text-align: center; white-space: nowrap; color: #e9ecef;">Date of Birth</th>
                    <th style="padding: 1rem 1.25rem; font-weight: 700; text-align: center; white-space: nowrap; color: #e9ecef;">Actions</th>
                  </tr>
                </thead>
                <tbody id="directorsList">
                  <!-- Directors will be loaded here -->
                </tbody>
              </table>
            </div>
            <div class="d-flex gap-3 justify-content-start mt-3">
              <button class="btn btn-outline-danger" onclick="exportTableToPDF('directorsTable', 'Directors_List')" style="padding: 0.5rem 1.25rem; font-weight: 600;">>
              <i class="fa-solid fa-file-pdf"></i> Export to PDF
            </button>
            <button class="btn btn-outline-primary btn-sm" onclick="printTable('directorsTable')">
              <i class="fa-solid fa-print"></i> Print
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- EDIT COMPANY MODAL -->
<div class="modal fade" id="editCompanyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); border: none; border-radius: 1rem; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
      <div class="modal-header" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); border: none; border-radius: 1rem 1rem 0 0; padding: 1.5rem 2rem;">
        <h5 class="modal-title text-white fw-bold" style="font-size: 1.4rem; letter-spacing: 0.5px;">
          <i class="fa-solid fa-building-circle-arrow-right me-2"></i> Edit Company Information
          <span class="badge bg-light text-primary ms-2 px-3 py-1" style="font-size: 0.75rem; font-weight: 600;">
            <i class="fa-solid fa-edit me-1"></i> Update
          </span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="opacity: 0.9; filter: brightness(1.2);"></button>
      </div>
      <form id="editCompanyForm">
        <div class="modal-body text-light" style="padding: 2.5rem; background: rgba(255,255,255,0.02);">
          <input type="hidden" id="edit_company_id" name="company_id">

          <!-- Company Basic Information -->
          <div class="form-section mb-5">
            <div class="section-header mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.1) 0%, rgba(13, 110, 253, 0.1) 100%); border-radius: 0.75rem; padding: 1.5rem; border: 1px solid rgba(13, 202, 240, 0.2);">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="section-title mb-0" style="color: #0dcaf0; font-weight: 700; font-size: 1.2rem; display: flex; align-items: center;">
                  <i class="fa-solid fa-building me-2" style="font-size: 1.1rem;"></i> Basic Information
              </h6>
                <span class="badge bg-primary-subtle text-primary-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                  <i class="fa-solid fa-asterisk me-1"></i> Required Fields
                </span>
              </div>
              <p class="text-light mb-0" style="font-size: 0.9rem; opacity: 0.8;">
                <i class="fa-solid fa-info-circle me-2"></i> Enter the primary company registration details
              </p>
            </div>
            
            <div class="row g-4">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="edit_company_name" class="form-label d-flex justify-content-between align-items-center mb-2" 
                         style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                    <span>Company Name <span class="text-danger ms-1">*</span></span>
                    <i class="fa-solid fa-building-user text-info" style="font-size: 1rem;"></i>
                  </label>
                  <input type="text" class="form-control" id="edit_company_name" name="company_name" 
                    style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;" 
                    placeholder="Enter registered company name"
                    required>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="form-group">
                  <label for="edit_ssm_no" class="form-label d-flex justify-content-between align-items-center mb-2" 
                         style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                    <span>SSM Registration No <span class="text-danger ms-1">*</span></span>
                    <i class="fa-solid fa-id-card text-info" style="font-size: 1rem;"></i>
                  </label>
                  <input type="text" class="form-control" id="edit_ssm_no" name="ssm_no" 
                    style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                    placeholder="Enter SSM registration number"
                    required>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="form-group mb-4">
                  <label for="edit_company_type" class="form-label d-flex justify-content-between align-items-center mb-2" 
                         style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                    <span>Company Type <span class="text-danger ms-1">*</span></span>
                    <i class="fa-solid fa-sitemap text-info" style="font-size: 1rem;"></i>
                  </label>
                  <select class="form-select" id="edit_company_type" name="company_type" required
                          style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;">
                    <option value="" disabled selected style="background: #1a1a2e; color: #adb5bd;">Select company type</option>
                    <option value="A" style="background: #1a1a2e; color: #ffffff;">A</option>
                    <option value="B" style="background: #1a1a2e; color: #ffffff;">B</option>
                    <option value="C" style="background: #1a1a2e; color: #ffffff;">C</option>
                  </select>
                </div>
              </div>

              <div class="col-md-6">
                <div class="form-group mb-4">
                  <label for="edit_sub_type" class="form-label d-flex justify-content-between align-items-center mb-2" 
                         style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                    <span>Sub Type <span class="text-danger ms-1">*</span></span>
                    <i class="fa-solid fa-code-branch text-info" style="font-size: 1rem;"></i>
                  </label>
                  <select class="form-select" id="edit_sub_type" name="sub_type" required
                          style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;">
                    <option value="" disabled selected style="background: #1a1a2e; color: #adb5bd;">Select business sub type</option>
                    <option value="SDN_BHD" style="background: #1a1a2e; color: #ffffff;">Sdn Bhd</option>
                    <option value="SOLE_PROPRIETOR" style="background: #1a1a2e; color: #ffffff;">Sole Proprietor</option>
                    <option value="Partnership" style="background: #1a1a2e; color: #ffffff;">Partnership</option>
                    <option value="LLP" style="background: #1a1a2e; color: #ffffff;">Limited Liability Partnership (LLP)</option>
                    <option value="BERHAD" style="background: #1a1a2e; color: #ffffff;">Berhad</option>
                  </select>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="form-group mb-4">
                  <label for="edit_incorporation_date" class="form-label d-flex justify-content-between align-items-center mb-2" 
                         style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                    <span>Incorporation Date <span class="text-danger ms-1">*</span></span>
                    <i class="fa-solid fa-calendar-plus text-info" style="font-size: 1rem;"></i>
                  </label>
                  <input type="date" class="form-control" id="edit_incorporation_date" name="incorporation_date" 
                         style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                         required>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="form-group mb-4">
                  <label for="edit_financial_year_end" class="form-label d-flex justify-content-between align-items-center mb-2" 
                         style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                    <span>Financial Year End <span class="text-danger ms-1">*</span></span>
                    <i class="fa-solid fa-calendar-check text-info" style="font-size: 1rem;"></i>
                  </label>
                  <input type="date" class="form-control" id="edit_financial_year_end" name="financial_year_end" 
                         style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                         required onchange="autoCalculateSubsequentYearEnd('edit')">
                  <small class="form-text text-info mt-2" style="font-size: 0.8rem; opacity: 0.8;">
                    <i class="fa-solid fa-info-circle me-1"></i> Must be within 18 months of incorporation date
                  </small>
                </div>
                <div class="form-group mb-4">
                  <label for="edit_subsequent_year_end" class="form-label d-flex justify-content-between align-items-center mb-2" 
                         style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                    <span>Subsequent Year End</span>
                    <i class="fa-solid fa-calendar-days text-info" style="font-size: 1rem;"></i>
                  </label>
                  <input type="date" class="form-control" id="edit_subsequent_year_end" name="subsequent_year_end" 
                         style="background: rgba(255, 255, 255, 0.05); border: 2px solid rgba(13, 202, 240, 0.2); color: #adb5bd; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem;"
                         readonly tabindex="-1">
                  <small class="form-text text-info mt-2" style="font-size: 0.8rem; opacity: 0.8;">
                    <i class="fa-solid fa-info-circle me-1"></i> Automatically set to one year after Financial Year End
                  </small>
                </div>
              </div>
            </div>
            
            <div class="col-12">
              <div class="form-group mb-4">
                <label for="edit_nature_of_business" class="form-label d-flex justify-content-between align-items-center mb-2" 
                       style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                  <span>Nature of Business <span class="text-danger ms-1">*</span></span>
                  <i class="fa-solid fa-briefcase text-info" style="font-size: 1rem;"></i>
                </label>
                <input type="text" class="form-control" id="edit_nature_of_business" name="nature_of_business" 
                       style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                       placeholder="Enter the primary business activity"
                       required>
              </div>
              
              <!-- MSIC Codes Section -->
              <div class="form-section mb-5">
                <div class="section-header mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.1) 0%, rgba(13, 110, 253, 0.1) 100%); border-radius: 0.75rem; padding: 1.5rem; border: 1px solid rgba(13, 202, 240, 0.2);">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="section-title mb-0" style="color: #0dcaf0; font-weight: 700; font-size: 1.2rem; display: flex; align-items: center;">
                      <i class="fa-solid fa-tags me-2" style="font-size: 1.1rem;"></i> MSIC Codes
                    </h6>
                    <span class="badge bg-info-subtle text-info-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                      <i class="fa-solid fa-hashtag me-1"></i> 1-3 Codes
                  </span>
                  </div>
                  <p class="text-light mb-0" style="font-size: 0.9rem; opacity: 0.8;">
                    <i class="fa-solid fa-info-circle me-2"></i> Select up to 3 MSIC codes that best describe your business activities
                  </p>
                </div>
                
                <!-- MSIC Code Cards Container -->
                <div class="msic-codes-container">
                  <!-- MSIC Code 1 -->
                  <div class="card mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.08) 0%, rgba(13, 110, 253, 0.08) 100%); border: 2px solid rgba(13, 202, 240, 0.3); border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    <div class="card-body p-4">
                      <div class="d-flex justify-content-between align-items-center mb-3">
                        <label for="edit_msic_code_1" class="form-label mb-0" style="font-size: 1rem; color: #0dcaf0; font-weight: 600;">
                          <i class="fa-solid fa-hashtag me-2"></i> Primary MSIC Code <span class="text-danger ms-1">*</span>
                        </label>
                        <span class="badge bg-primary-subtle text-primary-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                          <i class="fa-solid fa-star me-1"></i> Required
                        </span>
                      </div>
                      <div class="input-group">
                        <input type="text" class="form-control msic-autocomplete" id="edit_msic_code_1" name="msic_code_1" 
                               style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                               placeholder="Enter MSIC code..." required autocomplete="off">
                        <button class="btn btn-outline-info" type="button" onclick="clearMsicCode('1')" title="Clear MSIC code" style="border-radius: 0 0.75rem 0.75rem 0; border: 2px solid rgba(13, 202, 240, 0.3); background: rgba(13, 202, 240, 0.1); color: #0dcaf0;">
                          <i class="fa-solid fa-xmark"></i>
                        </button>
                      </div>
                      <input type="hidden" id="edit_msic_desc_1" name="msic_desc_1">
                      <div id="edit_msic_desc_display_1" class="text-info mt-3 p-3 rounded" 
                           style="font-size: 0.9rem; min-height: 30px; background: rgba(13, 202, 240, 0.15); border: 1px solid rgba(13, 202, 240, 0.2); border-radius: 0.5rem;"></div>
                    </div>
                  </div>
                  
                  <!-- MSIC Code 2 -->
                  <div class="card mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.08) 0%, rgba(13, 110, 253, 0.08) 100%); border: 2px solid rgba(13, 202, 240, 0.3); border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    <div class="card-body p-4">
                      <div class="d-flex justify-content-between align-items-center mb-3">
                        <label for="edit_msic_code_2" class="form-label mb-0" style="font-size: 1rem; color: #0dcaf0; font-weight: 600;">
                          <i class="fa-solid fa-hashtag me-2"></i> Secondary MSIC Code
                        </label>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                          <i class="fa-solid fa-plus me-1"></i> Optional
                        </span>
                      </div>
                      <div class="input-group">
                        <input type="text" class="form-control msic-autocomplete" id="edit_msic_code_2" name="msic_code_2" 
                               style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                               placeholder="Enter MSIC code..." autocomplete="off">
                        <button class="btn btn-outline-info" type="button" onclick="clearMsicCode('2')" title="Clear MSIC code" style="border-radius: 0 0.75rem 0.75rem 0; border: 2px solid rgba(13, 202, 240, 0.3); background: rgba(13, 202, 240, 0.1); color: #0dcaf0;">
                          <i class="fa-solid fa-xmark"></i>
                        </button>
                      </div>
                      <input type="hidden" id="edit_msic_desc_2" name="msic_desc_2">
                      <div id="edit_msic_desc_display_2" class="text-info mt-3 p-3 rounded" 
                           style="font-size: 0.9rem; min-height: 30px; background: rgba(13, 202, 240, 0.15); border: 1px solid rgba(13, 202, 240, 0.2); border-radius: 0.5rem;"></div>
                    </div>
                  </div>
                  
                  <!-- MSIC Code 3 -->
                  <div class="card mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.08) 0%, rgba(13, 110, 253, 0.08) 100%); border: 2px solid rgba(13, 202, 240, 0.3); border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    <div class="card-body p-4">
                      <div class="d-flex justify-content-between align-items-center mb-3">
                        <label for="edit_msic_code_3" class="form-label mb-0" style="font-size: 1rem; color: #0dcaf0; font-weight: 600;">
                          <i class="fa-solid fa-hashtag me-2"></i> Additional MSIC Code
                        </label>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                          <i class="fa-solid fa-plus me-1"></i> Optional
                        </span>
                      </div>
                      <div class="input-group">
                        <input type="text" class="form-control msic-autocomplete" id="edit_msic_code_3" name="msic_code_3" 
                               style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                               placeholder="Enter MSIC code..." autocomplete="off">
                        <button class="btn btn-outline-info" type="button" onclick="clearMsicCode('3')" title="Clear MSIC code" style="border-radius: 0 0.75rem 0.75rem 0; border: 2px solid rgba(13, 202, 240, 0.3); background: rgba(13, 202, 240, 0.1); color: #0dcaf0;">
                          <i class="fa-solid fa-xmark"></i>
                        </button>
                      </div>
                      <input type="hidden" id="edit_msic_desc_3" name="msic_desc_3">
                      <div id="edit_msic_desc_display_3" class="text-info mt-3 p-3 rounded" 
                           style="font-size: 0.9rem; min-height: 30px; background: rgba(13, 202, 240, 0.15); border: 1px solid rgba(13, 202, 240, 0.2); border-radius: 0.5rem;"></div>
                    </div>
                  </div>
                </div>
                
                <input type="hidden" id="edit_msic_code" name="msic_code" value="">
              </div>
              
              <div class="form-group mb-4">
                <label for="edit_description" class="form-label d-flex justify-content-between align-items-center mb-2" 
                       style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                  <span>Business Description</span>
                  <i class="fa-solid fa-align-left text-info" style="font-size: 1rem;"></i>
                </label>
                <textarea class="form-control" id="edit_description" name="description" rows="4"
                          style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease; resize: vertical;"
                          placeholder="Provide a detailed description of the business activities, products, or services..."></textarea>
              </div>
              
              <!-- Contact Information Card -->
              <div class="card mb-5" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.08) 0%, rgba(13, 110, 253, 0.08) 100%); border: 2px solid rgba(13, 202, 240, 0.3); border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <div class="card-body p-4">
                  <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="card-title mb-0" style="color: #0dcaf0; font-size: 1.2rem; font-weight: 700;">
                      <i class="fa-solid fa-address-card me-2"></i> Contact Details
                    </h6>
                    <span class="badge bg-info-subtle text-info-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                      <i class="fa-solid fa-phone me-1"></i> Communication
                    </span>
              </div>
              
                  <div class="row g-4">
                    <div class="col-md-6">
                      <div class="form-group mb-4">
                        <label for="edit_email" class="form-label d-flex justify-content-between align-items-center mb-2" 
                               style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                          <span>Email Address <span class="text-danger ms-1">*</span></span>
                          <i class="fa-solid fa-envelope text-info" style="font-size: 1rem;"></i>
                        </label>
                        <input type="email" class="form-control" id="edit_email" name="email" 
                               style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                               placeholder="Enter company email address"
                               required>
                      </div>
              </div>
              
                    <div class="col-md-6">
                      <div class="form-group mb-4">
                        <label for="edit_office_no" class="form-label d-flex justify-content-between align-items-center mb-2" 
                               style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                          <span>Office Phone</span>
                          <i class="fa-solid fa-phone text-info" style="font-size: 1rem;"></i>
                        </label>
                        <input type="text" class="form-control" id="edit_office_no" name="office_no" 
                               style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                               placeholder="Enter office contact number">
                      </div>
                    </div>
                    
                    <div class="col-md-6">
                      <div class="form-group mb-4">
                        <label for="edit_fax_no" class="form-label d-flex justify-content-between align-items-center mb-2" 
                               style="color: #e9ecef; font-size: 0.9rem; font-weight: 600;">
                          <span>Fax Number</span>
                          <i class="fa-solid fa-fax text-info" style="font-size: 1rem;"></i>
                        </label>
                        <input type="text" class="form-control" id="edit_fax_no" name="fax_no" 
                               style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease;"
                               placeholder="Enter fax number">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Address Section -->
          <div class="form-section mb-5">
            <div class="section-header mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.1) 0%, rgba(13, 110, 253, 0.1) 100%); border-radius: 0.75rem; padding: 1.5rem; border: 1px solid rgba(13, 202, 240, 0.2);">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="section-title mb-0" style="color: #0dcaf0; font-weight: 700; font-size: 1.2rem; display: flex; align-items: center;">
                  <i class="fa-solid fa-location-dot me-2" style="font-size: 1.1rem;"></i> Company Addresses
              </h6>
                <span class="badge bg-info-subtle text-info-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                  <i class="fa-solid fa-map-marker-alt me-1"></i> Location Details
                </span>
              </div>
              <p class="text-light mb-0" style="font-size: 0.9rem; opacity: 0.8;">
                <i class="fa-solid fa-info-circle me-2"></i> Official addresses for company registration and operations
              </p>
            </div>

            <div class="row g-4">
              <!-- Registered Address -->
              <div class="col-12">
                <div class="card mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.08) 0%, rgba(13, 110, 253, 0.08) 100%); border: 2px solid rgba(13, 202, 240, 0.3); border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                  <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <label for="edit_address" class="form-label mb-0" style="font-size: 1rem; color: #0dcaf0; font-weight: 600;">
                        <i class="fa-solid fa-building-circle-check me-2"></i> Registered Address <span class="text-danger ms-1">*</span>
                      </label>
                      <span class="badge bg-primary-subtle text-primary-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                        <i class="fa-solid fa-certificate me-1"></i> Official Address
                      </span>
                    </div>
                    <textarea class="form-control" id="edit_address" name="address" rows="4" required
                              style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease; resize: vertical;"
                              placeholder="Enter the official registered address..."></textarea>
                    <small class="form-text text-info mt-3 d-block" style="font-size: 0.8rem; opacity: 0.8;">
                      <i class="fa-solid fa-info-circle me-1"></i> This address will be used for official documentation
                    </small>
                  </div>
                </div>
              </div>

              <!-- Business Address -->
              <div class="col-12">
                <div class="card mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.08) 0%, rgba(13, 110, 253, 0.08) 100%); border: 2px solid rgba(13, 202, 240, 0.3); border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                  <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <label for="edit_business_address" class="form-label mb-0" style="font-size: 1rem; color: #0dcaf0; font-weight: 600;">
                        <i class="fa-solid fa-store me-2"></i> Business Address
                      </label>
                      <span class="badge bg-secondary-subtle text-secondary-emphasis px-3 py-2" style="font-size: 0.8rem; font-weight: 600; border-radius: 0.5rem;">
                        <i class="fa-solid fa-building me-1"></i> Operational Address
                      </span>
                    </div>
                    
                    <div class="form-check mb-4" style="background: rgba(13, 202, 240, 0.15); padding: 1rem; border-radius: 0.75rem; border: 1px solid rgba(13, 202, 240, 0.2);">
                      <input class="form-check-input" type="checkbox" id="edit_same_as_registered" style="margin-top: 0.25rem;" />
                      <label class="form-check-label" for="edit_same_as_registered" style="font-size: 0.9rem; color: #e9ecef; font-weight: 500;">
                        <i class="fa-solid fa-copy me-2"></i> Same as Registered Address
                      </label>
                    </div>
                    
                    <textarea class="form-control" id="edit_business_address" name="business_address" rows="4"
                              style="background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(13, 202, 240, 0.3); color: #ffffff; padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-size: 0.95rem; transition: all 0.3s ease; resize: vertical;"
                              placeholder="Enter the business operational address..."></textarea>
                    <small class="form-text text-info mt-3 d-block" style="font-size: 0.8rem; opacity: 0.8;">
                      <i class="fa-solid fa-info-circle me-1"></i> Address where business operations are conducted
                    </small>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Contact Information Section -->
          <div class="form-section mb-4">
            <div class="section-header mb-4 pb-2 border-bottom border-secondary">
              <h6 class="section-title mb-1" style="color: #0dcaf0; font-weight: 600; font-size: 1.1rem; display: flex; align-items: center;">
                <i class="fa-solid fa-address-card me-2"></i> Contact Information
                <span class="ms-2 badge bg-info-subtle text-info-emphasis px-2" style="font-size: 0.7rem;">Key Personnel</span>
              </h6>
              <p class="text-muted mb-0" style="font-size: 0.85rem;">
                <i class="fa-solid fa-info-circle me-1"></i> Contact details for important company representatives
              </p>
            </div>

            <div class="row g-4">
              <!-- Accountant Contact Card -->
              <div class="col-md-6">
                <div class="card h-100" style="background: rgba(13, 110, 253, 0.05); border: 1px solid rgba(13, 202, 240, 0.2);">
                  <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <h6 class="card-title mb-0" style="color: #0dcaf0; font-size: 1rem;">
                        <i class="fa-solid fa-calculator me-2"></i> Accountant Contact
                      </h6>
                      <span class="badge bg-info-subtle text-info-emphasis px-2 py-1" style="font-size: 0.7rem;">
                        <i class="fa-solid fa-file-invoice-dollar me-1"></i> Financial Contact
                      </span>
                    </div>
                    
                    <div class="contact-fields">
                      <div class="mb-3">
                        <label for="edit_accountant_name" class="form-label d-flex justify-content-between align-items-center"
                               style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">
                          <span>Accountant Name</span>
                          <i class="fa-solid fa-user text-info-emphasis" style="font-size: 0.9rem;"></i>
                        </label>
                        <input type="text" class="form-control custom-input" id="edit_accountant_name" name="accountant_name"
                               style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 202, 240, 0.2); color: #e9ecef; padding: 0.7rem 1rem;"
                               placeholder="Enter accountant's full name">
                      </div>
                      
                      <div class="mb-3">
                        <label for="edit_accountant_phone" class="form-label d-flex justify-content-between align-items-center"
                               style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">
                          <span>Phone Number</span>
                          <i class="fa-solid fa-phone text-info-emphasis" style="font-size: 0.9rem;"></i>
                        </label>
                        <input type="tel" class="form-control custom-input" id="edit_accountant_phone" name="accountant_phone"
                               style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 202, 240, 0.2); color: #e9ecef; padding: 0.7rem 1rem;"
                               placeholder="Enter contact number">
                      </div>
                      
                      <div class="mb-3">
                        <label for="edit_accountant_email" class="form-label d-flex justify-content-between align-items-center"
                               style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">
                          <span>Email Address</span>
                          <i class="fa-solid fa-envelope text-info-emphasis" style="font-size: 0.9rem;"></i>
                        </label>
                        <input type="email" class="form-control custom-input" id="edit_accountant_email" name="accountant_email"
                               style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 202, 240, 0.2); color: #e9ecef; padding: 0.7rem 1rem;"
                               placeholder="Enter email address">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- HR Contact Card -->
              <div class="col-md-6">
                <div class="card h-100" style="background: rgba(13, 110, 253, 0.05); border: 1px solid rgba(13, 202, 240, 0.2);">
                  <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <h6 class="card-title mb-0" style="color: #0dcaf0; font-size: 1rem;">
                        <i class="fa-solid fa-users me-2"></i> HR Contact
                      </h6>
                      <span class="badge bg-info-subtle text-info-emphasis px-2 py-1" style="font-size: 0.7rem;">
                        <i class="fa-solid fa-user-tie me-1"></i> Personnel Contact
                      </span>
                    </div>
                    
                    <div class="contact-fields">
                      <div class="mb-3">
                        <label for="edit_hr_name" class="form-label d-flex justify-content-between align-items-center"
                               style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">
                          <span>HR Representative</span>
                          <i class="fa-solid fa-user text-info-emphasis" style="font-size: 0.9rem;"></i>
                        </label>
                        <input type="text" class="form-control custom-input" id="edit_hr_name" name="hr_name"
                               style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 202, 240, 0.2); color: #e9ecef; padding: 0.7rem 1rem;"
                               placeholder="Enter HR representative's name">
                      </div>
                      
                      <div class="mb-3">
                        <label for="edit_hr_phone" class="form-label d-flex justify-content-between align-items-center"
                               style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">
                          <span>Phone Number</span>
                          <i class="fa-solid fa-phone text-info-emphasis" style="font-size: 0.9rem;"></i>
                        </label>
                        <input type="tel" class="form-control custom-input" id="edit_hr_phone" name="hr_phone"
                               style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 202, 240, 0.2); color: #e9ecef; padding: 0.7rem 1rem;"
                               placeholder="Enter contact number">
                      </div>
                      
                      <div class="mb-3">
                        <label for="edit_hr_email" class="form-label d-flex justify-content-between align-items-center"
                               style="color: #adb5bd; font-size: 0.85rem; font-weight: 500;">
                          <span>Email Address</span>
                          <i class="fa-solid fa-envelope text-info-emphasis" style="font-size: 0.9rem;"></i>
                        </label>
                        <input type="email" class="form-control custom-input" id="edit_hr_email" name="hr_email"
                               style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 202, 240, 0.2); color: #e9ecef; padding: 0.7rem 1rem;"
                               placeholder="Enter email address">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.1) 0%, rgba(13, 110, 253, 0.1) 100%); border-top: 2px solid rgba(13, 202, 240, 0.2); padding: 2rem; border-radius: 0 0 1rem 1rem;">
          <div class="d-flex justify-content-between align-items-center w-100">
            <div class="d-flex align-items-center">
              <i class="fa-solid fa-circle-info text-info me-2" style="font-size: 1rem;"></i>
              <small class="text-light" style="font-size: 0.9rem; opacity: 0.8;">
                Review all information before submitting
            </small>
            </div>
            <div class="button-group d-flex gap-3">
              <button type="button" class="btn btn-outline-light px-4 py-2" data-bs-dismiss="modal" style="border-radius: 0.75rem; font-weight: 600; border: 2px solid rgba(255, 255, 255, 0.3); transition: all 0.3s ease;">
                <i class="fa-solid fa-xmark me-2"></i> Cancel
              </button>
              <button type="submit" class="btn btn-primary px-5 py-2" id="updateCompanyBtn" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); border: none; border-radius: 0.75rem; font-weight: 600; box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3); transition: all 0.3s ease;">
                <span class="d-flex align-items-center">
                  <i class="fa-solid fa-save me-2"></i>
                  <span class="button-text">Update Company</span>
                  <span class="spinner-border spinner-border-sm ms-2 d-none" role="status"></span>
                </span>
              </button>
            </div>
          </div>
        </div>
        
        <script>
          document.getElementById('editCompanyForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('updateCompanyBtn');
            const spinner = btn.querySelector('.spinner-border');
            const buttonText = btn.querySelector('.button-text');
            
            // Show loading state
            spinner.classList.remove('d-none');
            buttonText.textContent = 'Updating...';
            btn.disabled = true;
          });
        </script>
      </form>
    </div>
  </div>
</div>

<!-- Add/Edit Member Modal -->
<div class="modal fade" id="memberModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title" id="memberModalTitle"><i class="fa-solid fa-user-plus"></i> Add Member</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="memberForm">
        <div class="modal-body">
          <input type="hidden" id="member_id" name="member_id">
          <input type="hidden" id="company_id_member" name="company_id">
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="member_name" class="form-label">Member Name *</label>
                <input type="text" class="form-control" id="member_name" name="member_name" required>
              </div>
              
              <div class="mb-3">
                <label for="id_type" class="form-label">ID Type *</label>
                <select class="form-control" id="id_type" name="id_type" required>
                  <option value="NRIC">NRIC</option>
                  <option value="PASSPORT">PASSPORT</option>
                  <option value="ARMY/POLICE_ID">ARMY/POLICE ID</option>
                  <option value="OTHER">OTHER</option>
                </select>
              </div>
              
              <div class="mb-3">
                <label for="identification_no" class="form-label">Identification No *</label>
                <input type="text" class="form-control" id="identification_no" name="identification_no" required>
              </div>
              
              <div class="mb-3">
                <label for="nationality" class="form-label">Nationality *</label>
                <input type="text" class="form-control" id="nationality" name="nationality" required>
              </div>
              
              <div class="mb-3">
                <label for="member_email" class="form-label">Email</label>
                <input type="email" class="form-control" id="member_email" name="email">
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="mb-3">
                <label for="race" class="form-label">Race *</label>
                <input type="text" class="form-control" id="race" name="race" required>
              </div>
              
              <div class="mb-3">
                <label for="class_of_share" class="form-label">Class of Share *</label>
                <select class="form-control" id="class_of_share" name="class_of_share" required>
                  <option value="Ordinary">Ordinary</option>
                  <option value="Preference">Preference</option>
                  <option value="Redeemable">Redeemable</option>
                  <option value="Employee">Employee</option>
                </select>
              </div>
              
              <div class="mb-3">
                <label for="number_of_share" class="form-label">Number of Shares *</label>
                <input type="text" class="form-control" id="number_of_share" name="number_of_share" required>
              </div>
              
              <div class="mb-3">
                <label for="price_per_share" class="form-label">Price Per Share *</label>
                <input type="number" class="form-control" id="price_per_share" name="price_per_share" required>
              </div>
            </div>
          </div>
          
          <div class="mb-3">
            <label for="address" class="form-label">Address *</label>
            <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success" id="saveMemberAndAddAnother">
            <i class="fa-solid fa-plus-circle"></i> Save & Add Another
          </button>
          <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-save"></i> Save Member
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add/Edit Director Modal -->
<div class="modal fade" id="directorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title" id="directorModalTitle"><i class="fa-solid fa-user-tie"></i> Add Director</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="directorForm">
        <div class="modal-body">
          <input type="hidden" id="director_id" name="director_id">
          <input type="hidden" id="company_id_director" name="company_id">
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="director_name" class="form-label">Director Name *</label>
                <input type="text" class="form-control" id="director_name" name="director_name" required>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="mb-3">
                <label for="director_race" class="form-label">Race *</label>
                <input type="text" class="form-control" id="director_race" name="race" required>
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="director_identification_no" class="form-label">Identification No *</label>
                <input type="text" class="form-control" id="director_identification_no" name="identification_no" required>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="mb-3">
                <label for="email" class="form-label">Email *</label>
                <input type="email" class="form-control" id="email" name="email" required>
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="director_nationality" class="form-label">Nationality *</label>
                <input type="text" class="form-control" id="director_nationality" name="nationality" required>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="mb-3">
                <label for="date_of_birth" class="form-label">Date of Birth *</label>
                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required>
              </div>
            </div>
          </div>
          
          <div class="mb-3">
            <label for="director_address" class="form-label">Address *</label>
            <textarea class="form-control" id="director_address" name="address" rows="3" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success" id="saveDirectorAndAddAnother">
            <i class="fa-solid fa-plus-circle"></i> Save & Add Another
          </button>
          <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-save"></i> Save Director
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
        // Trigger calculation on FYE change (for edit form)
        document.getElementById('edit_financial_year_end').addEventListener('change', function() {
          autoCalculateSubsequentYearEnd('edit');
        });
        
        // Set FYE limits when incorporation date changes (for add form)
        document.getElementById('add_incorporation_date').addEventListener('change', function() {
          setFinancialYearEndLimits('add');
        });
        
        // Set FYE limits when incorporation date changes (for edit form)
        document.getElementById('edit_incorporation_date').addEventListener('change', function() {
          setFinancialYearEndLimits('edit');
        });
        // Set Financial Year End date limits based on Incorporation Date
        function setFinancialYearEndLimits(formType) {
          const prefix = formType === 'add' ? 'add_' : 'edit_';
          const incDateInput = document.getElementById(prefix + 'incorporation_date');
          const fyeInput = document.getElementById(prefix + 'financial_year_end');
          
          if (!incDateInput || !fyeInput || !incDateInput.value) {
            // Reset limits if no incorporation date
            fyeInput.min = '';
            fyeInput.max = '';
            return;
          }
          
          const incDate = new Date(incDateInput.value);
          const incYear = incDate.getFullYear();
          
          // Set minimum date to January 1st of the incorporation year
          const minDate = new Date(incYear, 0, 1); // January 1st of incorporation year
          
          // Set maximum date to 18 months after incorporation date
          const maxDate = new Date(incDate);
          maxDate.setMonth(maxDate.getMonth() + 18);
          
          // Format dates for input min/max attributes (YYYY-MM-DD)
          const formatDate = (date) => {
            return date.getFullYear() + '-' + 
                   String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                   String(date.getDate()).padStart(2, '0');
          };
          
          fyeInput.min = formatDate(minDate);
          fyeInput.max = formatDate(maxDate);
          
          // Clear financial year end if it's outside the new limits
          if (fyeInput.value) {
            const fyeDate = new Date(fyeInput.value);
            if (fyeDate < minDate || fyeDate > maxDate) {
              fyeInput.value = '';
              // Also clear subsequent year end
              const subsequentInput = document.getElementById(prefix + 'subsequent_year_end');
              if (subsequentInput) {
                subsequentInput.value = '';
              }
            }
          }
        }

        // Auto-calculate Subsequent Year End based on Financial Year End
        function autoCalculateSubsequentYearEnd(formType) {
          const prefix = formType === 'add' ? 'add_' : 'edit_';
          const fyeInput = document.getElementById(prefix + 'financial_year_end');
          const subsequentInput = document.getElementById(prefix + 'subsequent_year_end');
          if (!fyeInput || !subsequentInput || !fyeInput.value) {
            subsequentInput.value = '';
            return;
          }
          const fyeDate = new Date(fyeInput.value);
          if (isNaN(fyeDate.getTime())) {
            subsequentInput.value = '';
            return;
          }
          const nextYear = new Date(fyeDate);
          nextYear.setFullYear(fyeDate.getFullYear() + 1);
          const year = nextYear.getFullYear();
          const month = String(nextYear.getMonth() + 1).padStart(2, '0');
          const day = String(nextYear.getDate()).padStart(2, '0');
          subsequentInput.value = `${year}-${month}-${day}`;
        }

        // Trigger calculation on FYE change (for add form)
        document.getElementById('add_financial_year_end').addEventListener('change', function() {
          autoCalculateSubsequentYearEnd('add');
        });
// Global variable to store current company ID
let currentCompanyId = null;

// ============ SIDEBAR TOGGLE FUNCTION ============
function toggleSidebarSection(wrapperId) {
  const wrapper = document.getElementById(wrapperId);
  wrapper.classList.toggle('active');
}

// ============ NOTIFICATION SYSTEM ============
const unshownNotifications = <?php echo json_encode($unshownNotifications); ?>;

// Show notification popup on page load for unshown notifications
document.addEventListener('DOMContentLoaded', function() {
  if (unshownNotifications.length > 0) {
    setTimeout(() => {
      showNotificationPopup(unshownNotifications);
    }, 1000); // Show after 1 second
  }
  
  // Setup notification item click handlers
  setupNotificationHandlers();
});

function showNotificationPopup(notifications) {
  const container = document.getElementById('notificationPopupContainer');
  
  notifications.forEach((notif, index) => {
    setTimeout(() => {
      const popup = createNotificationPopup(notif);
      container.appendChild(popup);
      
      // Auto-dismiss after 10 seconds
      setTimeout(() => {
        dismissNotificationPopup(popup, notif.notification_id);
      }, 10000);
    }, index * 300); // Stagger popups by 300ms
  });
}

function createNotificationPopup(notif) {
  const popup = document.createElement('div');
  popup.className = 'notification-popup';
  popup.style.top = `${80 + (document.querySelectorAll('.notification-popup').length * 10)}px`;
  popup.dataset.notificationId = notif.notification_id;
  
  const timeAgo = getTimeAgo(notif.created_at);
  const isFYE = notif.type === 'financial_year_end';
  const itemClass = isFYE ? 'notification-popup-item fye-notification' : 'notification-popup-item';
  const icon = isFYE ? '<i class="fa-solid fa-calendar-check fye-notification-icon"></i>' : '';
  const headerIcon = isFYE ? '<i class="fa-solid fa-calendar-check"></i>' : '<i class="fa-solid fa-bell"></i>';
  
  popup.innerHTML = `
    <div class="notification-popup-header">
      <h5>${headerIcon} ${escapeHtml(notif.title)}</h5>
      <button class="notification-popup-close" onclick="dismissNotificationPopup(this.closest('.notification-popup'), ${notif.notification_id})">
        <i class="fa-solid fa-times"></i>
      </button>
    </div>
    <div class="notification-popup-body">
      <div class="${itemClass}" onclick="handleNotificationClick(${notif.notification_id}, ${notif.document_id})">
        <div class="notification-popup-item-message">${icon}${escapeHtml(notif.message)}</div>
        <div class="notification-popup-item-time">
          <i class="fa-solid fa-clock"></i> ${timeAgo}
        </div>
      </div>
    </div>
  `;
  
  return popup;
}

function dismissNotificationPopup(popup, notificationId) {
  popup.classList.add('closing');
  
  setTimeout(() => {
    popup.remove();
  }, 300);
  
  // Mark as shown in database
  fetch('index.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `notification_action=mark_shown&notification_ids=${JSON.stringify([notificationId])}`
  });
}

function handleNotificationClick(notificationId, documentId) {
  // Mark as read
  fetch('index.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `notification_action=mark_read&notification_id=${notificationId}`
  }).then(() => {
    updateNotificationBadge();
  });
  
  // Navigate to document only if documentId exists (not for FYE notifications)
  if (documentId && !isNaN(documentId)) {
    viewDocumentDetails(documentId);
  }
  
  // Close any open popups
  document.querySelectorAll('.notification-popup').forEach(popup => {
    dismissNotificationPopup(popup, popup.dataset.notificationId);
  });
  
  // Close modal if open
  const modal = bootstrap.Modal.getInstance(document.getElementById('notificationModal'));
  if (modal) modal.hide();
}

function setupNotificationHandlers() {
  // Handle notification item clicks in modal
  document.addEventListener('click', function(e) {
    const notifItem = e.target.closest('.notification-item');
    if (notifItem) {
      const notifId = parseInt(notifItem.dataset.notificationId);
      const docId = parseInt(notifItem.dataset.documentId);
      handleNotificationClick(notifId, docId);
    }
  });
  
  // Mark all as read button
  document.getElementById('markAllReadBtn')?.addEventListener('click', function() {
    fetch('index.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'notification_action=mark_all_read'
    }).then(response => response.json())
      .then(data => {
        if (data.success) {
          showToast('All notifications marked as read');
          updateNotificationBadge();
          // Update UI
          document.querySelectorAll('.notification-item').forEach(item => {
            item.classList.remove('unread');
            item.classList.add('read');
            item.style.background = 'rgba(15, 27, 51, 0.3)';
            item.style.borderLeftColor = '#555';
            const badge = item.querySelector('.badge');
            if (badge) badge.remove();
          });
        }
      });
  });
}

function updateNotificationBadge() {
  fetch('index.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'notification_action=get_unread_count'
  }).then(response => response.json())
    .then(data => {
      if (data.success) {
        const badge = document.getElementById('notificationBadge');
        if (data.count > 0) {
          if (badge) {
            badge.textContent = data.count;
          } else {
            const newBadge = document.createElement('span');
            newBadge.className = 'notification-badge';
            newBadge.id = 'notificationBadge';
            newBadge.textContent = data.count;
            document.getElementById('notificationBellBtn').appendChild(newBadge);
          }
        } else {
          if (badge) badge.remove();
        }
      }
    });
}

function getTimeAgo(dateString) {
  const timestamp = new Date(dateString).getTime();
  const diff = Math.floor((Date.now() - timestamp) / 1000);
  
  if (diff < 60) return 'Just now';
  if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
  if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
  return Math.floor(diff / 86400) + ' days ago';
}

// Real-time notification polling
let lastNotificationCheck = Date.now();
let knownNotificationIds = new Set();

function checkForNewNotifications() {
  fetch('index.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'notification_action=get_notifications'
  }).then(response => response.json())
    .then(data => {
      if (data.success && data.notifications) {
        // Find new notifications that we haven't seen before
        const newNotifications = data.notifications.filter(notif => {
          const isNew = !knownNotificationIds.has(notif.notification_id) && 
                        notif.is_shown == 0;
          knownNotificationIds.add(notif.notification_id);
          return isNew;
        });
        
        // Show popup for new notifications
        if (newNotifications.length > 0) {
          showNotificationPopup(newNotifications);
        }
        
        // Update badge count
        updateNotificationBadge();
      }
    })
    .catch(error => console.error('Error checking notifications:', error));
}

// Initialize known notification IDs on page load
function initializeNotificationSystem() {
  // Mark existing notifications as known
  const existingNotifications = <?php echo json_encode($allNotifications); ?>;
  existingNotifications.forEach(notif => {
    knownNotificationIds.add(notif.notification_id);
  });
  
  // Start polling for new notifications every 10 seconds
  setInterval(checkForNewNotifications, 10000);
  
  console.log('Real-time notification system initialized');
}

// Start notification polling when page loads
document.addEventListener('DOMContentLoaded', function() {
  initializeNotificationSystem();
});

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// ============ END NOTIFICATION SYSTEM ============

function showPage(page) {
  document.getElementById('dashboard').classList.add('hidden');
  document.getElementById('companies').classList.add('hidden');
  document.getElementById('documents').classList.add('hidden');
  document.getElementById('queries').classList.add('hidden');
  document.getElementById('reviews').classList.add('hidden');
  document.getElementById('managementLetter').classList.add('hidden');
  document.getElementById('timeCost').classList.add('hidden');
  <?php if ($_SESSION['user_type'] === 'admin'): ?>
  document.getElementById('users').classList.add('hidden');
  document.getElementById('admins').classList.add('hidden');
  <?php endif; ?>
  document.getElementById(page).classList.remove('hidden');
  
  // Auto-load Time Cost data when showing Time Cost page
  if (page === 'timeCost') {
    try { if (typeof loadTimeCostList === 'function') loadTimeCostList(); } catch(e) {}
    try { if (typeof loadTimeCostSummary === 'function') loadTimeCostSummary(); } catch(e) {}
  }
  
  // Load management letter data when page is shown
  if (page === 'managementLetter') {
    loadManagementLetterData();
    populateMLCompanyFilter();
  }
}

// Toast notification function 
function showToast(message) {
  const toast = new bootstrap.Toast(document.getElementById('successToast'));
  document.querySelector('#successToast .toast-body').textContent = message;
  toast.show();
}

// Password toggle function
function togglePassword(inputId) {
  const input = document.getElementById(inputId);
  const icon = input.parentNode.querySelector('.password-toggle i');
  
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'fa-solid fa-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'fa-solid fa-eye';
  }
}

// Password confirmation validation
function validatePassword() {
  const password = document.getElementById('new_password').value;
  const confirmPassword = document.getElementById('confirm_password').value;
  const matchText = document.getElementById('password-match');
  const submitBtn = document.getElementById('change-password-btn');
  
  if (password.length < 6) {
    matchText.innerHTML = '<span class="text-warning">Password must be at least 6 characters</span>';
    submitBtn.disabled = true;
    return false;
  }
  
  if (password === confirmPassword && password.length >= 6) {
    matchText.innerHTML = '<span class="text-success">Passwords match!</span>';
    submitBtn.disabled = false;
    return true;
  } else {
    matchText.innerHTML = '<span class="text-danger">Passwords do not match!</span>';
    submitBtn.disabled = true;
    return false;
  }
}

// Profile password confirmation validation
function validateProfilePassword() {
  const password = document.getElementById('new_password_profile').value;
  const confirmPassword = document.getElementById('confirm_password_profile').value;
  const matchText = document.getElementById('password-match-profile');
  const submitBtn = document.getElementById('change-password-profile-btn');
  
  if (password.length < 6) {
    matchText.innerHTML = '<span class="text-warning">Password must be at least 6 characters</span>';
    submitBtn.disabled = true;
    return false;
  }
  
  if (password === confirmPassword && password.length >= 6) {
    matchText.innerHTML = '<span class="text-success">Passwords match!</span>';
    submitBtn.disabled = false;
    return true;
  } else {
    matchText.innerHTML = '<span class="text-danger">Passwords do not match!</span>';
    submitBtn.disabled = true;
    return false;
  }
}

// ============ FYE FILTER FUNCTIONALITY ============
let currentFYEFilter = 'all';

// Initialize FYE statistics on page load
function updateFYEStatistics() {
  const allRows = document.querySelectorAll('#companiesTable tbody tr');
  const visibleRows = Array.from(allRows).filter(row => row.style.display !== 'none');
  
  // Count companies with FYE in next 30 days
  let next30Count = 0;
  allRows.forEach(row => {
    const days = parseInt(row.getAttribute('data-days-until-fye'));
    if (!isNaN(days) && days <= 30) {
      next30Count++;
    }
  });
  
  document.getElementById('totalCompaniesCount').textContent = allRows.length;
  document.getElementById('filteredCompaniesCount').textContent = visibleRows.length;
  document.getElementById('next30DaysCount').textContent = next30Count;
}

// Filter companies by FYE date range
function filterFYE(filterType) {
  currentFYEFilter = filterType;
  const rows = document.querySelectorAll('#companiesTable tbody tr');
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  
  let filterLabel = 'None';
  
  rows.forEach(row => {
    const fyeDateStr = row.getAttribute('data-fye-date');
    const daysUntilFYE = parseInt(row.getAttribute('data-days-until-fye'));
    
    let show = false;
    
    switch(filterType) {
      case 'all':
        show = true;
        filterLabel = 'All Companies';
        break;
        
      case 'next30':
        show = !isNaN(daysUntilFYE) && daysUntilFYE <= 30;
        filterLabel = 'Next 30 Days';
        break;
        
      case 'next60':
        show = !isNaN(daysUntilFYE) && daysUntilFYE <= 60;
        filterLabel = 'Next 60 Days';
        break;
        
      case 'next90':
        show = !isNaN(daysUntilFYE) && daysUntilFYE <= 90;
        filterLabel = 'Next 90 Days';
        break;
        
      case 'thisMonth':
        if (fyeDateStr) {
          const fyeDate = new Date(fyeDateStr);
          const currentYear = today.getFullYear();
          const thisYearFYE = new Date(currentYear, fyeDate.getMonth(), fyeDate.getDate());
          
          if (thisYearFYE < today) {
            thisYearFYE.setFullYear(currentYear + 1);
          }
          
          show = thisYearFYE.getMonth() === today.getMonth() && 
                 thisYearFYE.getFullYear() === today.getFullYear();
        }
        filterLabel = 'This Month';
        break;
        
      case 'nextMonth':
        if (fyeDateStr) {
          const fyeDate = new Date(fyeDateStr);
          const currentYear = today.getFullYear();
          const thisYearFYE = new Date(currentYear, fyeDate.getMonth(), fyeDate.getDate());
          
          if (thisYearFYE < today) {
            thisYearFYE.setFullYear(currentYear + 1);
          }
          
          const nextMonth = new Date(today);
          nextMonth.setMonth(today.getMonth() + 1);
          
          show = thisYearFYE.getMonth() === nextMonth.getMonth() && 
                 thisYearFYE.getFullYear() === nextMonth.getFullYear();
        }
        filterLabel = 'Next Month';
        break;
    }
    
    row.style.display = show ? '' : 'none';
  });
  
  document.getElementById('activeFilterLabel').textContent = filterLabel;
  updateFYEStatistics();
  
  // Clear custom date inputs
  document.getElementById('fyeFromDate').value = '';
  document.getElementById('fyeToDate').value = '';
}

// Filter by custom date range
function filterFYECustomRange() {
  const fromDateStr = document.getElementById('fyeFromDate').value;
  const toDateStr = document.getElementById('fyeToDate').value;
  
  if (!fromDateStr && !toDateStr) {
    filterFYE('all');
    return;
  }
  
  const rows = document.querySelectorAll('#companiesTable tbody tr');
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  
  rows.forEach(row => {
    const fyeDateStr = row.getAttribute('data-fye-date');
    
    if (!fyeDateStr) {
      row.style.display = 'none';
      return;
    }
    
    const fyeDate = new Date(fyeDateStr);
    const currentYear = today.getFullYear();
    const thisYearFYE = new Date(currentYear, fyeDate.getMonth(), fyeDate.getDate());
    
    if (thisYearFYE < today) {
      thisYearFYE.setFullYear(currentYear + 1);
    }
    
    let show = true;
    
    if (fromDateStr) {
      const fromDate = new Date(fromDateStr);
      show = show && thisYearFYE >= fromDate;
    }
    
    if (toDateStr) {
      const toDate = new Date(toDateStr);
      show = show && thisYearFYE <= toDate;
    }
    
    row.style.display = show ? '' : 'none';
  });
  
  currentFYEFilter = 'custom';
  document.getElementById('activeFilterLabel').textContent = 'Custom Range';
  updateFYEStatistics();
}

// Filter FYE using dropdown
function filterFYEQuick() {
  const filterType = document.getElementById('fyeQuickFilter').value;
  filterFYE(filterType);
}

// Clear all FYE filters
function clearFYEFilter() {
  document.getElementById('fyeFromDate').value = '';
  document.getElementById('fyeToDate').value = '';
  document.getElementById('fyeQuickFilter').value = 'all';
  filterFYE('all');
}

// Initialize statistics on page load
document.addEventListener('DOMContentLoaded', function() {
  updateFYEStatistics();
});

// SEARCH FUNCTIONALITY
const originalData = [];
document.querySelectorAll('#companiesTable tbody tr').forEach((row, index) => {
  const cells = row.querySelectorAll('td');
  originalData[index] = Array.from(cells).map(td => td.textContent);
});
document.getElementById('searchBar').addEventListener('keyup', function(){
  const filter = this.value.toLowerCase();
  const rows = document.querySelectorAll('#companiesTable tbody tr');
  rows.forEach((row, index) => {
    const rowData = originalData[index];
    const match = rowData.some(cell => cell.toLowerCase().includes(filter));
    
    // Check if row is already hidden by FYE filter
    const isHiddenByFYE = currentFYEFilter !== 'all' && row.style.display === 'none';
    
    if (filter === "") {
      // If search is cleared, respect FYE filter
      if (currentFYEFilter !== 'all') {
        // Re-apply FYE filter
        filterFYE(currentFYEFilter);
      } else {
        row.style.display = '';
      }
    } else {
      // Apply search on top of FYE filter
      row.style.display = match ? '' : 'none';
    }
  });
  updateFYEStatistics();
});

// Users search
document.getElementById('searchUsers')?.addEventListener('keyup', function() {
  const filter = this.value.toLowerCase();
  const rows = document.querySelectorAll('#usersTable tbody tr');
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(filter) ? '' : 'none';
  });
});

// Admins search
document.getElementById('searchAdmins')?.addEventListener('keyup', function() {
  const filter = this.value.toLowerCase();
  const rows = document.querySelectorAll('#adminsTable tbody tr');
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(filter) ? '' : 'none';
  });
});

// Enhanced company row click handler
document.querySelectorAll('.company-row').forEach(row => {
  row.addEventListener('click', function() {
    const company = JSON.parse(this.getAttribute('data-company'));
    currentCompanyId = company.company_id;
    
    // Populate company details
    document.getElementById('detail-id').textContent = company.company_id || 'N/A';
    document.getElementById('detail-name').textContent = company.company_name || 'N/A';
    document.getElementById('detail-ssm').textContent = company.ssm_no || 'N/A';
    document.getElementById('detail-type').textContent = company.company_type || 'N/A';
    document.getElementById('detail-subtype').textContent = company.sub_type || 'N/A';
    document.getElementById('detail-incdate').textContent = company.incorporation_date || 'N/A';
    document.getElementById('detail-fye').textContent = company.financial_year_end || 'N/A';
    document.getElementById('detail-subsequent-year-end').textContent = company.subsequent_year_end || 'N/A';
    // Display MSIC codes - Parse from combined field (comma-separated) and display codes with individual descriptions
    const msicCodes = company.msic_code ? company.msic_code.split(',').map(c => c.trim()) : [];
    
    // Function to display MSIC code with its individual nature of business
    const displayMSIC = async (code, index) => {
      if (code) {
        document.getElementById(`detail-msic-${index}`).style.display = 'block';
        document.getElementById(`detail-msic-code-${index}`).textContent = code;
        
        // Fetch description for this specific MSIC code
        try {
          const response = await fetch(`?action=search_msic&query=${encodeURIComponent(code)}`);
          const results = await response.json();
          const match = results.find(r => r.code === code);
          if (match) {
            document.getElementById(`detail-msic-nature-${index}`).textContent = match.description;
          } else {
            document.getElementById(`detail-msic-nature-${index}`).textContent = 'Description not found';
          }
        } catch (error) {
          document.getElementById(`detail-msic-nature-${index}`).textContent = 'Unable to fetch description';
        }
        return true;
      } else {
        document.getElementById(`detail-msic-${index}`).style.display = 'none';
        return false;
      }
    };
    
    // Display all MSIC codes with individual nature of business
    Promise.all([
      displayMSIC(msicCodes[0], 1),
      displayMSIC(msicCodes[1], 2),
      displayMSIC(msicCodes[2], 3)
    ]).then(results => {
      const hasAnyMsic = results.some(r => r);
      document.getElementById('detail-msic-none').style.display = hasAnyMsic ? 'none' : 'block';
    });
    
    document.getElementById('detail-description').textContent = company.description || 'N/A';
    document.getElementById('detail-description').classList.add('wrap-long');
    document.getElementById('detail-address').textContent = company.address || 'N/A';
    document.getElementById('detail-business-address').textContent = company.business_address || 'Same as Registered Address';
    document.getElementById('detail-email').textContent = company.email || 'N/A';
    document.getElementById('detail-office').textContent = company.office_no || 'N/A';
    document.getElementById('detail-fax').textContent = company.fax_no || 'N/A';
    document.getElementById('detail-accname').textContent = company.accountant_name || 'N/A';
    document.getElementById('detail-accphone').textContent = company.accountant_phone || 'N/A';
    document.getElementById('detail-accemail').textContent = company.accountant_email || 'N/A';
    document.getElementById('detail-hrname').textContent = company.hr_name || 'N/A';
    document.getElementById('detail-hrphone').textContent = company.hr_phone || 'N/A';
    document.getElementById('detail-hremail').textContent = company.hr_email || 'N/A';
    
    // Load members and directors
    loadMembers(company.company_id);
    loadDirectors(company.company_id);
    
    new bootstrap.Modal(document.getElementById('companyModal')).show();
  });
});

// Load members for a company
function loadMembers(companyId) {
  console.log('Loading members for company:', companyId);
  
  fetch(`?action=view&type=members&company_id=${companyId}`)
    .then(response => {
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        throw new Error('Server returned non-JSON response');
      }
      return response.json().then(data => {
        if (!response.ok) {
          throw new Error(data.error || `HTTP error! status: ${response.status}`);
        }
        return data;
      });
    })
    .then(data => {
      const membersList = document.getElementById('membersList');
      membersList.innerHTML = '';
      
      if (data.error) {
        membersList.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error: ' + escapeHtml(data.error) + '</td></tr>';
        return;
      }
      
      if (!Array.isArray(data)) {
        membersList.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Invalid data format received</td></tr>';
        return;
      }
      
      if (data.length === 0) {
        membersList.innerHTML = '<tr><td colspan="8" class="text-center">No members found</td></tr>';
        return;
      }
      
      // First pass: calculate total shares
      let totalShares = 0;
      data.forEach(member => {
        const numShares = parseFloat(member.number_of_share) || 0;
        totalShares += numShares;
      });
      
      // Second pass: render rows with percentages
      data.forEach(member => {
        const numShares = parseFloat(member.number_of_share) || 0;
        const pricePerShare = parseFloat(member.price_per_share) || 0;
        const total = numShares * pricePerShare;
        const percentage = totalShares > 0 ? (numShares / totalShares * 100) : 0;
        
        const row = document.createElement('tr');
        row.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
          row.innerHTML = `
            <td style="padding: 0.875rem 0.75rem; vertical-align: middle; text-align: left; font-weight: 500;">${escapeHtml(member.member_name || 'N/A')}</td>
            <td style="padding: 0.875rem 0.75rem; vertical-align: middle; text-align: center; font-family: 'Courier New', monospace; color: #0dcaf0;">${escapeHtml(member.identification_no || 'N/A')}</td>
            <td style="padding: 0.875rem 0.75rem; vertical-align: middle; text-align: center;">${escapeHtml(member.nationality || 'N/A')}</td>
            <td style="padding: 0.875rem 0.75rem; vertical-align: middle; text-align: right; font-weight: 600; color: #20c997;">${member.price_per_share ? 'RM ' + parseFloat(member.price_per_share).toFixed(2) : 'N/A'}</td>
            <td style="padding: 0.875rem 0.75rem; vertical-align: middle; text-align: center; font-weight: 600; color: #ffc107;">${escapeHtml(member.number_of_share || 'N/A')}</td>
            <td style="padding: 0.875rem 0.75rem; vertical-align: middle; text-align: center; font-weight: 700; color: #20c997; font-size: 1.05rem;">${percentage > 0 ? percentage.toFixed(2) + '%' : 'N/A'}</td>
            <td style="padding: 0.875rem 0.75rem; vertical-align: middle; text-align: right; font-weight: 700; color: #0dcaf0; font-size: 1.05rem;">${total > 0 ? 'RM ' + total.toFixed(2) : 'N/A'}</td>
            <td style="padding: 0.875rem 0.75rem; vertical-align: middle; text-align: center;">
              <div class="d-flex gap-1 justify-content-center">
              <button class="btn btn-sm btn-info" title="View" onclick="viewMember(${member.member_id})">
                <i class="fa-solid fa-eye"></i>
              </button>
              <button class="btn btn-sm btn-warning" title="Edit" onclick="editMember(${member.member_id})">
                <i class="fa-solid fa-edit"></i>
              </button>
              <button class="btn btn-sm" style="background-color: #dc3545; color: #fff;" title="Delete" onclick="deleteMember(${member.member_id})">
                <i class="fa-solid fa-trash"></i>
              </button>
              </div>
            </td>
          `;
        membersList.appendChild(row);
      });
      
      // Add total row
      if (data.length > 0) {
        const totalRow = document.createElement('tr');
        totalRow.style.fontWeight = 'bold';
        totalRow.style.background = 'linear-gradient(135deg, rgba(13, 202, 240, 0.15) 0%, rgba(13, 110, 253, 0.15) 100%)';
        totalRow.style.borderTop = '2px solid #0dcaf0';
        totalRow.style.borderBottom = '2px solid #0dcaf0';
        totalRow.innerHTML = `
          <td colspan="4" style="padding: 1rem 0.75rem; text-align: right; font-weight: 700; font-size: 1.05rem; color: #e9ecef;">Total Number of Shares:</td>
          <td style="padding: 1rem 0.75rem; text-align: center; font-weight: 700; font-size: 1.15rem; color: #ffc107;">${totalShares}</td>
          <td style="padding: 1rem 0.75rem; text-align: center; font-weight: 700; font-size: 1.15rem; color: #20c997;">100.00%</td>
          <td colspan="2" style="padding: 1rem 0.75rem;"></td>
        `;
        membersList.appendChild(totalRow);
      }
    })
    .catch(error => {
      console.error('Error loading members:', error);
      const membersList = document.getElementById('membersList');
      membersList.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error loading members: ' + escapeHtml(error.message) + '</td></tr>';
    });
}

// Load directors for a company
function loadDirectors(companyId) {
  console.log('Loading directors for company:', companyId);
  
  fetch(`?action=view&type=directors&company_id=${companyId}`)
    .then(response => {
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        throw new Error('Server returned non-JSON response');
      }
      return response.json().then(data => {
        if (!response.ok) {
          throw new Error(data.error || `HTTP error! status: ${response.status}`);
        }
        return data;
      });
    })
    .then(data => {
      const directorsList = document.getElementById('directorsList');
      directorsList.innerHTML = '';
      
      if (data.error) {
        directorsList.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error: ' + escapeHtml(data.error) + '</td></tr>';
        return;
      }
      
      if (!Array.isArray(data)) {
        directorsList.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Invalid data format received</td></tr>';
        return;
      }
      
      if (data.length === 0) {
        directorsList.innerHTML = '<tr><td colspan="5" class="text-center">No directors found</td></tr>';
        return;
      }
      
      data.forEach(director => {
        const row = document.createElement('tr');
        row.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
        row.innerHTML = `
          <td style="padding: 0.875rem 0.75rem; vertical-align: middle; text-align: left; font-weight: 500;">${escapeHtml(director.director_name || 'N/A')}</td>
          <td style="padding: 0.875rem 0.75rem; vertical-align: middle; text-align: center; font-family: 'Courier New', monospace; color: #0dcaf0;">${escapeHtml(director.identification_no || 'N/A')}</td>
          <td style="padding: 0.875rem 0.75rem; vertical-align: middle; text-align: center;">${escapeHtml(director.nationality || 'N/A')}</td>
          <td style="padding: 0.875rem 0.75rem; vertical-align: middle; text-align: center; color: #20c997; font-weight: 500;">${escapeHtml(director.date_of_birth || 'N/A')}</td>
          <td style="padding: 0.875rem 0.75rem; vertical-align: middle; text-align: center;">
            <div class="d-flex gap-1 justify-content-center">
            <button class="btn btn-sm btn-info" onclick="viewDirector(${director.director_id})">
              <i class="fa-solid fa-eye"></i>
            </button>
            <button class="btn btn-sm btn-warning" onclick="editDirector(${director.director_id})">
              <i class="fa-solid fa-edit"></i>
            </button>
            <button class="btn btn-sm btn-danger" onclick="deleteDirector(${director.director_id})">
              <i class="fa-solid fa-trash"></i>
            </button>
            </div>
          </td>
        `;
        directorsList.appendChild(row);
      });
    })
    .catch(error => {
      console.error('Error loading directors:', error);
      const directorsList = document.getElementById('directorsList');
      directorsList.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading directors: ' + escapeHtml(error.message) + '</td></tr>';
    });
}

// HTML escaping function
function escapeHtml(unsafe) {
  if (unsafe === null || unsafe === undefined) return 'N/A';
  return unsafe
    .toString()
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// Add new member
function addMember() {
  document.getElementById('memberModalTitle').textContent = 'Add Member';
  document.getElementById('memberForm').reset();
  document.getElementById('member_id').value = '';
  document.getElementById('company_id_member').value = currentCompanyId;
  new bootstrap.Modal(document.getElementById('memberModal')).show();
}

// Edit member
function editMember(memberId) {
  fetch(`?action=view&type=member&id=${memberId}`)
    .then(response => response.json())
    .then(member => {
      document.getElementById('memberModalTitle').textContent = 'Edit Member';
      document.getElementById('member_id').value = member.member_id;
      document.getElementById('company_id_member').value = member.company_id;
      document.getElementById('member_name').value = member.member_name || '';
      document.getElementById('id_type').value = member.id_type || 'NRIC';
      document.getElementById('identification_no').value = member.identification_no || '';
      document.getElementById('nationality').value = member.nationality || '';
      document.getElementById('address').value = member.address || '';
      document.getElementById('race').value = member.race || '';
      document.getElementById('price_per_share').value = member.price_per_share || '';
      document.getElementById('class_of_share').value = member.class_of_share || 'Ordinary';
      document.getElementById('number_of_share').value = member.number_of_share || '';
      document.getElementById('member_email').value = member.email || '';
      
      new bootstrap.Modal(document.getElementById('memberModal')).show();
    })
    .catch(err => { console.error('Error fetching member:', err); alert('Error loading member details'); });
}

// Add new director
function addDirector() {
  document.getElementById('directorModalTitle').textContent = 'Add Director';
  document.getElementById('directorForm').reset();
  document.getElementById('director_id').value = '';
  document.getElementById('company_id_director').value = currentCompanyId;
  new bootstrap.Modal(document.getElementById('directorModal')).show();
}

// Edit director
function editDirector(directorId) {
  fetch(`?action=view&type=director&id=${directorId}`)
    .then(response => response.json())
    .then(director => {
      document.getElementById('directorModalTitle').textContent = 'Edit Director';
      document.getElementById('director_id').value = director.director_id;
      document.getElementById('company_id_director').value = director.company_id;
      document.getElementById('director_name').value = director.director_name || '';
      document.getElementById('director_identification_no').value = director.identification_no || '';
      document.getElementById('director_nationality').value = director.nationality || '';
      document.getElementById('date_of_birth').value = director.date_of_birth || '';
      document.getElementById('director_race').value = director.race || '';
      document.getElementById('email').value = director.email || '';
      document.getElementById('director_address').value = director.address || '';
      
      new bootstrap.Modal(document.getElementById('directorModal')).show();
    })
    .catch(error => {
      console.error('Error loading director:', error);
      alert('Error loading director data');
    });
}

// Delete member
function deleteMember(memberId) {
  if (confirm('Are you sure you want to delete this member?')) {
    const formData = new FormData();
    formData.append('action', 'delete_member');
    formData.append('member_id', memberId);
    
    fetch('', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showToast(data.message);
        loadMembers(currentCompanyId);
      } else {
        alert('Error: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Error deleting member:', error);
      alert('Error deleting member');
    });
  }
}

// Delete director
function deleteDirector(directorId) {
  if (confirm('Are you sure you want to delete this director?')) {
    const formData = new FormData();
    formData.append('action', 'delete_director');
    formData.append('director_id', directorId);
    
    fetch('', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showToast(data.message);
        loadDirectors(currentCompanyId);
      } else {
        alert('Error: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Error deleting director:', error);
      alert('Error deleting director');
    });
  }
}

// Edit company
function editCompany() {
  if (!currentCompanyId) {
    alert('No company selected');
    return;
  }
  
  fetch(`?action=view&type=company&id=${currentCompanyId}`)
    .then(response => response.json())
    .then(company => {
      if (!company || Object.keys(company).length === 0) {
        alert('Company not found');
        return;
      }
      
      // Populate the edit form with company data
      document.getElementById('edit_company_id').value = company.company_id || '';
      document.getElementById('edit_company_name').value = company.company_name || '';
      document.getElementById('edit_ssm_no').value = company.ssm_no || '';
      document.getElementById('edit_company_type').value = company.company_type || '';
      document.getElementById('edit_sub_type').value = company.sub_type || '';
      document.getElementById('edit_incorporation_date').value = company.incorporation_date || '';
      document.getElementById('edit_financial_year_end').value = company.financial_year_end || '';
      document.getElementById('edit_subsequent_year_end').value = company.subsequent_year_end || '';
      document.getElementById('edit_nature_of_business').value = company.nature_of_business || '';
      document.getElementById('edit_msic_code').value = company.msic_code || '';
      
      // Populate MSIC codes - Parse from combined field (comma-separated) and fetch descriptions
      const msicCodes = company.msic_code ? company.msic_code.split(',').map(c => c.trim()) : [];
      
      // Function to load MSIC code and description
      const loadMSICCode = async (code, index) => {
        document.getElementById(`edit_msic_code_${index}`).value = code || '';
        if (code) {
          try {
            const response = await fetch(`?action=search_msic&query=${encodeURIComponent(code)}`);
            const results = await response.json();
            const match = results.find(r => r.code === code);
            if (match) {
              document.getElementById(`edit_msic_desc_${index}`).value = match.description;
              document.getElementById(`edit_msic_desc_display_${index}`).textContent = match.description;
              document.getElementById(`edit_msic_desc_display_${index}`).style.color = '#0dcaf0';
            } else {
              document.getElementById(`edit_msic_desc_${index}`).value = '';
              document.getElementById(`edit_msic_desc_display_${index}`).textContent = '';
            }
          } catch (error) {
            document.getElementById(`edit_msic_desc_${index}`).value = '';
            document.getElementById(`edit_msic_desc_display_${index}`).textContent = '';
          }
        } else {
          document.getElementById(`edit_msic_desc_${index}`).value = '';
          document.getElementById(`edit_msic_desc_display_${index}`).textContent = '';
        }
        return Promise.resolve(); // Ensure it returns a Promise
      };
      
      // Load all MSIC codes and descriptions
      Promise.all([
        loadMSICCode(msicCodes[0], 1),
        loadMSICCode(msicCodes[1], 2),
        loadMSICCode(msicCodes[2], 3)
      ]).then(() => {
        // Update nature of business field after all MSIC codes are loaded
        updateNatureOfBusiness('edit_');
      });
      
      document.getElementById('edit_description').value = company.description || '';
      document.getElementById('edit_address').value = company.address || '';
      document.getElementById('edit_business_address').value = company.business_address || '';
      document.getElementById('edit_email').value = company.email || '';
      document.getElementById('edit_office_no').value = company.office_no || '';
      document.getElementById('edit_fax_no').value = company.fax_no || '';
      document.getElementById('edit_accountant_name').value = company.accountant_name || '';
      document.getElementById('edit_accountant_phone').value = company.accountant_phone || '';
      document.getElementById('edit_accountant_email').value = company.accountant_email || '';
      document.getElementById('edit_hr_name').value = company.hr_name || '';
      document.getElementById('edit_hr_phone').value = company.hr_phone || '';
      document.getElementById('edit_hr_email').value = company.hr_email || '';
      
      // Show the edit modal
      new bootstrap.Modal(document.getElementById('editCompanyModal')).show();
    })
    .catch(error => {
      console.error('Error loading company data:', error);
      alert('Error loading company data');
    });
}

// Delete company
function deleteCompany() {
  if (confirm('Are you sure you want to delete this company and all its associated members and directors?')) {
    const formData = new FormData();
    formData.append('action', 'delete_company');
    formData.append('company_id', currentCompanyId);
    
    fetch('', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showToast(data.message);
        bootstrap.Modal.getInstance(document.getElementById('companyModal')).hide();
        // Reload the page to update the companies list
        location.reload();
      } else {
        alert('Error: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Error deleting company:', error);
      alert('Error deleting company');
    });
  }
}

// Refresh company data
function refreshCompanyData() {
  if (currentCompanyId) {
    loadMembers(currentCompanyId);
    loadDirectors(currentCompanyId);
    showToast('Data refreshed successfully');
  }
}

// Function to save member data
function saveMemberData(addAnother = false) {
  const form = document.getElementById('memberForm');
  const formData = new FormData(form);
  const isEdit = form.querySelector('#member_id').value;
  formData.append('action', isEdit ? 'update_member' : 'add_member');
  
  fetch('', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      loadMembers(currentCompanyId);
      
      if (addAnother && !isEdit) {
        // Clear form for next entry but keep modal open
        form.reset();
        document.getElementById('member_id').value = '';
        document.getElementById('company_id_member').value = currentCompanyId;
        // Reset to default values
        document.getElementById('id_type').value = 'NRIC';
        document.getElementById('class_of_share').value = 'Ordinary';
      } else {
        // Close modal
        bootstrap.Modal.getInstance(document.getElementById('memberModal')).hide();
      }
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error saving member:', error);
    alert('Error saving member');
  });
}

// Handle member form submission
document.getElementById('memberForm').addEventListener('submit', function(e) {
  e.preventDefault();
  saveMemberData(false);
});

// Handle "Save & Add Another" button
document.getElementById('saveMemberAndAddAnother').addEventListener('click', function(e) {
  e.preventDefault();
  
  // Check if it's an edit - if yes, just save normally
  const memberId = document.getElementById('member_id').value;
  if (memberId) {
    alert('You can only use "Save & Add Another" when adding new members, not when editing.');
    return;
  }
  
  // Validate form before saving
  const form = document.getElementById('memberForm');
  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }
  
  saveMemberData(true);
});

// Function to save director data
function saveDirectorData(addAnother = false) {
  const form = document.getElementById('directorForm');
  const formData = new FormData(form);
  const isEdit = form.querySelector('#director_id').value;
  formData.append('action', isEdit ? 'update_director' : 'add_director');
  
  fetch('', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      loadDirectors(currentCompanyId);
      
      if (addAnother && !isEdit) {
        // Clear form for next entry but keep modal open
        form.reset();
        document.getElementById('director_id').value = '';
        document.getElementById('company_id_director').value = currentCompanyId;
      } else {
        // Close modal
        bootstrap.Modal.getInstance(document.getElementById('directorModal')).hide();
      }
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error saving director:', error);
    alert('Error saving director');
  });
}

// Handle director form submission
document.getElementById('directorForm').addEventListener('submit', function(e) {
  e.preventDefault();
  saveDirectorData(false);
});

// Handle "Save & Add Another" button for directors
document.getElementById('saveDirectorAndAddAnother').addEventListener('click', function(e) {
  e.preventDefault();
  
  // Check if it's an edit - if yes, just save normally
  const directorId = document.getElementById('director_id').value;
  if (directorId) {
    alert('You can only use "Save & Add Another" when adding new directors, not when editing.');
    return;
  }
  
  // Validate form before saving
  const form = document.getElementById('directorForm');
  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }
  
  saveDirectorData(true);
});

// Handle company edit form submission
document.getElementById('editCompanyForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  formData.append('action', 'update_company');
  
  fetch('', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('editCompanyModal')).hide();
      
      // Update the company details in the view modal without reloading
      if (currentCompanyId) {
        // Reload the company data to reflect changes
        fetch(`?action=view&type=company&id=${currentCompanyId}`)
          .then(response => response.json())
          .then(company => {
            // Update the displayed company details
            updateCompanyDetailsDisplay(company);
          })
          .catch(error => {
            console.error('Error refreshing company data:', error);
          });
      }
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error saving company:', error);
    alert('Error saving company');
  });
});

// ============ AUTO-CALCULATE FYE FUNCTIONALITY ============

// Auto-calculate Financial Year End based on Incorporation Date
function autoCalculateFYE(formType) {
  const prefix = formType === 'add' ? 'add_' : 'edit_';
  const autoCheckbox = document.getElementById(prefix + 'auto_fye');
  
  // Only auto-calculate if checkbox is checked (or doesn't exist for edit form on load)
  if (autoCheckbox && !autoCheckbox.checked && formType === 'edit') {
    return;
  }
  
  const incorporationDateInput = document.getElementById(prefix + 'incorporation_date');
  const fyeDateInput = document.getElementById(prefix + 'financial_year_end');
  
  if (!incorporationDateInput.value) {
    return;
  }
  
  // Parse incorporation date
  const incDate = new Date(incorporationDateInput.value);
  
  // Calculate FYE as 12 months from incorporation date
  const fyeDate = new Date(incDate);
  fyeDate.setFullYear(incDate.getFullYear() + 1);
  
  // Format date as YYYY-MM-DD for input field
  const year = fyeDate.getFullYear();
  const month = String(fyeDate.getMonth() + 1).padStart(2, '0');
  const day = String(fyeDate.getDate()).padStart(2, '0');
  const formattedDate = `${year}-${month}-${day}`;
  
  // Set the FYE date
  fyeDateInput.value = formattedDate;
  
  // Visual feedback
  fyeDateInput.style.background = 'rgba(0, 255, 0, 0.1)';
  setTimeout(() => {
    fyeDateInput.style.background = '';
  }, 1000);
}

// Toggle auto-calculation on/off
function toggleAutoFYE(formType) {
  const prefix = formType === 'add' ? 'add_' : 'edit_';
  const autoCheckbox = document.getElementById(prefix + 'auto_fye');
  const fyeDateInput = document.getElementById(prefix + 'financial_year_end');
  
  if (autoCheckbox.checked) {
    // Enable auto-calculation
    fyeDateInput.readOnly = false;
    fyeDateInput.style.opacity = '1';
    autoCalculateFYE(formType);
  } else {
    // Disable auto-calculation - allow manual input
    fyeDateInput.readOnly = false;
    fyeDateInput.style.opacity = '1';
  }
}

// Initialize auto-calculation when modals open
document.getElementById('addCompanyModal').addEventListener('shown.bs.modal', function() {
  // Set checkbox to checked by default
  document.getElementById('add_auto_fye').checked = true;
  
  // Reset business address checkbox
  document.getElementById('add_same_as_registered').checked = false;
  document.getElementById('add_business_address').disabled = false;
  
  // Add event listener to incorporation date
  const incDateInput = document.getElementById('add_incorporation_date');
  if (incDateInput.value) {
    autoCalculateFYE('add');
    setFinancialYearEndLimits('add');
  }
});

document.getElementById('editCompanyModal').addEventListener('shown.bs.modal', function() {
  // Check if incorporation date exists and auto-calculate
  const incDateInput = document.getElementById('edit_incorporation_date');
  const fyeDateInput = document.getElementById('edit_financial_year_end');
  
  // Set FYE limits based on incorporation date
  if (incDateInput.value) {
    setFinancialYearEndLimits('edit');
  }
  
  // Check if FYE is already 12 months from incorporation
  if (incDateInput.value && fyeDateInput.value) {
    const incDate = new Date(incDateInput.value);
    const fyeDate = new Date(fyeDateInput.value);
    const expectedFYE = new Date(incDate);
    expectedFYE.setFullYear(incDate.getFullYear() + 1);
    
    // If FYE matches expected calculation, check the auto checkbox
    if (fyeDate.getTime() === expectedFYE.getTime()) {
      document.getElementById('edit_auto_fye').checked = true;
    } else {
      document.getElementById('edit_auto_fye').checked = false;
    }
  }
  
  // Check if business address matches registered address
  const regAddr = document.getElementById('edit_address').value;
  const busAddr = document.getElementById('edit_business_address').value;
  if (busAddr && busAddr === regAddr) {
    document.getElementById('edit_same_as_registered').checked = true;
    document.getElementById('edit_business_address').disabled = true;
    document.getElementById('edit_business_address').classList.add('bg-light');
  } else {
    document.getElementById('edit_same_as_registered').checked = false;
    document.getElementById('edit_business_address').disabled = false;
    document.getElementById('edit_business_address').classList.remove('bg-light');
  }
});

// Business Address checkbox handler for Add Company form
document.getElementById('add_same_as_registered').addEventListener('change', function() {
  const businessAddressField = document.getElementById('add_business_address');
  const registeredAddressField = document.getElementById('add_address');
  
  if (this.checked) {
    businessAddressField.value = registeredAddressField.value;
    businessAddressField.disabled = true;
    businessAddressField.classList.add('bg-light');
  } else {
    businessAddressField.value = '';
    businessAddressField.disabled = false;
    businessAddressField.classList.remove('bg-light');
  }
});

// Update business address when registered address changes (if checkbox is checked) - Add form
document.getElementById('add_address').addEventListener('input', function() {
  const sameAsRegistered = document.getElementById('add_same_as_registered');
  const businessAddressField = document.getElementById('add_business_address');
  
  if (sameAsRegistered.checked) {
    businessAddressField.value = this.value;
  }
});

// Business Address checkbox handler for Edit Company form
document.getElementById('edit_same_as_registered').addEventListener('change', function() {
  const businessAddressField = document.getElementById('edit_business_address');
  const registeredAddressField = document.getElementById('edit_address');
  
  if (this.checked) {
    businessAddressField.value = registeredAddressField.value;
    businessAddressField.disabled = true;
    businessAddressField.classList.add('bg-light');
  } else {
    businessAddressField.disabled = false;
    businessAddressField.classList.remove('bg-light');
  }
});

// Update business address when registered address changes (if checkbox is checked) - Edit form
document.getElementById('edit_address').addEventListener('input', function() {
  const sameAsRegistered = document.getElementById('edit_same_as_registered');
  const businessAddressField = document.getElementById('edit_business_address');
  
  if (sameAsRegistered.checked) {
    businessAddressField.value = this.value;
  }
});

// Handle add company form submission
document.getElementById('addCompanyForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  // Client-side validation
  const companyName = document.getElementById('add_company_name').value.trim();
  const ssmNo = document.getElementById('add_ssm_no').value.trim();
  
  if (!companyName || !ssmNo) {
    alert('Please fill in all required fields');
    return;
  }
  
  const formData = new FormData(this);
  formData.append('action', 'add_company');
  
  fetch('', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('addCompanyModal')).hide();
      this.reset();
      // Reload the page to show the new company
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error adding company:', error);
    alert('Error adding company: ' + error.message);
  });
});

// Handle Profile Form Submission
document.getElementById('profileForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  formData.append('action', 'update_profile');
  
  fetch('', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Update the sidebar profile
      document.querySelector('.profile-name').textContent = formData.get('full_name');
      document.querySelector('.user-display-name').textContent = formData.get('full_name');
      
      // Update profile picture if available
      if (data.image_url) {
        const profilePicture = document.querySelector('.profile-picture-sidebar');
        if (profilePicture) {
          profilePicture.src = data.image_url;
        } else {
          const placeholder = document.querySelector('.profile-picture-placeholder-sidebar');
          if (placeholder) {
            placeholder.outerHTML = `<img src="${data.image_url}" alt="Profile" class="profile-picture-sidebar">`;
          }
        }
        
        // Also update the profile modal picture
        const modalPicture = document.querySelector('#profileForm .profile-picture-lg');
        if (modalPicture) {
          modalPicture.src = data.image_url;
        }
      }
      
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('profileModal')).hide();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while updating the profile.');
  });
});

// Handle Profile Password Form Submission
document.getElementById('changePasswordProfileForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  if (!validateProfilePassword()) {
    alert('Please make sure passwords match and are at least 6 characters long.');
    return;
  }
  
  const formData = new FormData(this);
  formData.append('action', 'change_password');
  
  fetch('', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      // Reset form
      document.getElementById('changePasswordProfileForm').reset();
      document.getElementById('password-match-profile').innerHTML = '';
      document.getElementById('change-password-profile-btn').disabled = true;
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while changing the password.');
  });
});

<?php if ($_SESSION['user_type'] === 'admin'): ?>
// Edit User functionality
document.querySelectorAll('.edit-user').forEach(button => {
  button.addEventListener('click', function(e) {
    e.stopPropagation();
    
    const userId = this.getAttribute('data-id');
    const userFullName = this.getAttribute('data-full_name');
    const userEmail = this.getAttribute('data-email');
    const userPhone = this.getAttribute('data-phone');
    const userRole = this.getAttribute('data-role');
    const userImageUrl = this.getAttribute('data-image_url');
    
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_full_name').value = userFullName;
    document.getElementById('edit_email').value = userEmail;
    document.getElementById('edit_phone').value = userPhone;
    document.getElementById('edit_role').value = userRole;
    
    // Handle profile picture display in circular preview
    const previewContainer = document.getElementById('edit_user_preview_container');
    if (userImageUrl) {
      previewContainer.innerHTML = `<img src="${userImageUrl}" alt="Profile">`;
    } else {
      const initials = userFullName ? 
        userFullName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() : 'U';
      previewContainer.innerHTML = `<div class="profile-upload-placeholder">${initials}</div>`;
    }
    
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
  });
});

// Change Password functionality
document.querySelectorAll('.change-password-user').forEach(button => {
  button.addEventListener('click', function(e) {
    e.stopPropagation();
    
    const userId = this.getAttribute('data-id');
    const userFullName = this.getAttribute('data-full_name');
    
    document.getElementById('password_user_id').value = userId;
    document.getElementById('password-user-name').textContent = userFullName;
    
    // Reset form
    document.getElementById('changePasswordForm').reset();
    document.getElementById('password-match').innerHTML = '';
    document.getElementById('change-password-btn').disabled = true;
    
    new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
  });
});

// Edit Admin functionality
document.querySelectorAll('.edit-admin').forEach(button => {
  button.addEventListener('click', function(e) {
    e.stopPropagation();
    
    const adminId = this.getAttribute('data-id');
    const adminFullName = this.getAttribute('data-full_name');
    const adminEmail = this.getAttribute('data-email');
    const adminPhone = this.getAttribute('data-phone');
    const adminImageUrl = this.getAttribute('data-image_url');
    
    document.getElementById('edit_admin_id').value = adminId;
    document.getElementById('edit_admin_full_name').value = adminFullName;
    document.getElementById('edit_admin_email').value = adminEmail;
    document.getElementById('edit_admin_phone').value = adminPhone;
    
    // Handle profile picture display in circular preview
    const previewContainer = document.getElementById('edit_admin_preview_container');
    if (adminImageUrl) {
      previewContainer.innerHTML = `<img src="${adminImageUrl}" alt="Profile">`;
    } else {
      const initials = adminFullName ? 
        adminFullName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() : 'A';
      previewContainer.innerHTML = `<div class="profile-upload-placeholder">${initials}</div>`;
    }
    
    new bootstrap.Modal(document.getElementById('editAdminModal')).show();
  });
});

// Handle User Form Submission
document.getElementById('editUserForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  formData.append('action', 'edit_user');
  
  fetch('', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Update the table row
      const userId = formData.get('user_id');
      const row = document.querySelector(`tr[data-user-id="${userId}"]`);
      if (row) {
        row.querySelector('.user-name').textContent = formData.get('full_name');
        row.querySelector('.user-email').textContent = formData.get('email');
        row.querySelector('.user-phone').textContent = formData.get('phone');
        
        const roleBadge = row.querySelector('.user-role');
        roleBadge.textContent = formData.get('role').charAt(0).toUpperCase() + formData.get('role').slice(1);
        roleBadge.className = 'user-role badge ' + 
          (formData.get('role') == 'manager' ? 'bg-primary' : 
           (formData.get('role') == 'accountant' ? 'bg-warning' : 'bg-secondary'));
           
        // Update the edit button data attributes
        const editBtn = row.querySelector('.edit-user');
        editBtn.setAttribute('data-full_name', formData.get('full_name'));
        editBtn.setAttribute('data-email', formData.get('email'));
        editBtn.setAttribute('data-phone', formData.get('phone'));
        editBtn.setAttribute('data-role', formData.get('role'));
        
        // Update profile picture if available
        if (data.image_url) {
          const profileCell = row.querySelector('td:nth-child(2)');
          profileCell.innerHTML = `<img src="${data.image_url}" alt="Profile" class="profile-picture">`;
          editBtn.setAttribute('data-image_url', data.image_url);
        }
      }
      
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while updating the user.');
  });
});

// Handle Change Password Form Submission
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  if (!validatePassword()) {
    alert('Please make sure passwords match and are at least 6 characters long.');
    return;
  }
  
  const formData = new FormData(this);
  formData.append('action', 'change_user_password');
  
  fetch('', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('changePasswordModal')).hide();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while changing the password.');
  });
});

// Handle Admin Form Submission
document.getElementById('editAdminForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  formData.append('action', 'edit_admin');
  
  fetch('', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Update the table row
      const adminId = formData.get('admin_id');
      const row = document.querySelector(`tr[data-admin-id="${adminId}"]`);
      if (row) {
        row.querySelector('.admin-name').textContent = formData.get('full_name');
        row.querySelector('.admin-email').textContent = formData.get('email');
        row.querySelector('.admin-phone').textContent = formData.get('phone') || 'N/A';
        
        // Update the edit button data attributes
        const editBtn = row.querySelector('.edit-admin');
        editBtn.setAttribute('data-full_name', formData.get('full_name'));
        editBtn.setAttribute('data-email', formData.get('email'));
        editBtn.setAttribute('data-phone', formData.get('phone') || '');
        
        // Update profile picture if available
        if (data.image_url) {
          const profileCell = row.querySelector('td:nth-child(2)');
          profileCell.innerHTML = `<img src="${data.image_url}" alt="Profile" class="profile-picture">`;
          editBtn.setAttribute('data-image_url', data.image_url);
        }
      }
      
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('editAdminModal')).hide();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while updating the admin.');
  });
});

// Delete User functionality
document.querySelectorAll('.delete-user').forEach(button => {
  button.addEventListener('click', function(e) {
    e.stopPropagation();
    
    if (confirm('Are you sure you want to delete this user?')) {
      const userId = this.getAttribute('data-id');
      const formData = new FormData();
      formData.append('action', 'delete_user');
      formData.append('user_id', userId);
      
      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Remove the row from table
          const row = document.querySelector(`tr[data-user-id="${userId}"]`);
          if (row) {
            row.remove();
          }
          // Reload to refresh sequential numbers
          location.reload();
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the user.');
      });
    }
  });
});

// Delete Admin functionality
document.querySelectorAll('.delete-admin').forEach(button => {
  button.addEventListener('click', function(e) {
    e.stopPropagation();
    
    if (confirm('Are you sure you want to delete this admin?')) {
      const adminId = this.getAttribute('data-id');
      const formData = new FormData();
      formData.append('action', 'delete_admin');
      formData.append('admin_id', adminId);
      
      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Remove the row from table
          const row = document.querySelector(`tr[data-admin-id="${adminId}"]`);
          if (row) {
            row.remove();
          }
          // Reload to refresh sequential numbers
          location.reload();
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the admin.');
      });
    }
  });
});

// Add User Form Submission
document.getElementById('addUserForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  formData.append('action', 'add_user');
  
  fetch('', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
      this.reset(); // Clear the form
      // Reload to show new user at bottom with correct sequential number
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while adding the user.');
  });
});

// Add Admin Form Submission
document.getElementById('addAdminForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  formData.append('action', 'add_admin');
  
  fetch('', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('addAdminModal')).hide();
      this.reset(); // Clear the form
      // Reload to show new admin at bottom with correct sequential number
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while adding the admin.');
  });
});

// Password validation on input
document.getElementById('new_password').addEventListener('input', validatePassword);
document.getElementById('confirm_password').addEventListener('input', validatePassword);
<?php endif; ?>

// Profile password validation on input
document.getElementById('new_password_profile').addEventListener('input', validateProfilePassword);
document.getElementById('confirm_password_profile').addEventListener('input', validateProfilePassword);

// View User functionality
document.querySelectorAll('.view-user').forEach(button => {
  button.addEventListener('click', function(e) {
    e.stopPropagation();
    const userId = this.getAttribute('data-id');
    
    fetch(`?action=view&type=user&id=${userId}`)
      .then(response => response.json())
      .then(user => {
        if (user && Object.keys(user).length > 0) {
          // Populate user data
          document.getElementById('view-user-id').textContent = user.user_id || 'N/A';
          document.getElementById('view-user-name').textContent = user.full_name || 'N/A';
          document.getElementById('view-user-email').textContent = user.email || 'N/A';
          document.getElementById('view-user-phone').textContent = user.phone || 'N/A';
          document.getElementById('view-user-role').textContent = user.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : 'User';
          document.getElementById('view-user-lastlogin').textContent = user.last_login || 'Never';
          document.getElementById('view-user-regdate').textContent = user.created_at || user.registration_date || 'N/A';
          
          // Handle profile picture
          const profilePicture = document.getElementById('view-user-picture');
          if (user.image_url) {
            profilePicture.innerHTML = `<img src="${user.image_url}" alt="Profile" class="profile-picture-lg">`;
          } else {
            const initials = user.full_name ? 
              user.full_name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() : 'U';
            profilePicture.innerHTML = initials;
          }
          
          // Show the modal
          new bootstrap.Modal(document.getElementById('viewUserModal')).show();
        } else {
          alert('User not found!');
        }
      })
      .catch(error => {
        console.error('Error fetching user data:', error);
        alert('Error loading user details!');
      });
  });
});

// View Admin functionality
document.querySelectorAll('.view-admin').forEach(button => {
  button.addEventListener('click', function(e) {
    e.stopPropagation();
    const adminId = this.getAttribute('data-id');
    
    fetch(`?action=view&type=admin&id=${adminId}`)
      .then(response => response.json())
      .then(admin => {
        if (admin && Object.keys(admin).length > 0) {
          // Populate admin data
          document.getElementById('view-admin-id').textContent = admin.admin_id || 'N/A';
          document.getElementById('view-admin-name').textContent = admin.full_name || 'N/A';
          document.getElementById('view-admin-email').textContent = admin.email || 'N/A';
          document.getElementById('view-admin-phone').textContent = admin.phone || 'N/A';
          document.getElementById('view-admin-lastlogin').textContent = admin.last_login || 'Never';
          document.getElementById('view-admin-created').textContent = admin.created_at || admin.registration_date || 'N/A';
          
          // Handle profile picture
          const profilePicture = document.getElementById('view-admin-picture');
          if (admin.image_url) {
            profilePicture.innerHTML = `<img src="${admin.image_url}" alt="Profile" class="profile-picture-lg">`;
          } else {
            const initials = admin.full_name ? 
              admin.full_name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() : 'A';
            profilePicture.innerHTML = initials;
          }
          
          // Show the modal
          new bootstrap.Modal(document.getElementById('viewAdminModal')).show();
        } else {
          alert('Admin not found!');
        }
      })
      .catch(error => {
        console.error('Error fetching admin data:', error);
        alert('Error loading admin details!');
      });
  });
});

// ========== DOCUMENT FLOW HANDLERS ==========

// Document category mapping based on source type
const documentCategoryMap = {
  'Supplier': ['Purchase Invoices', 'Delivery Orders', 'Credit Notes'],
  'Customer': ['Sales Orders', 'Debit Notes', 'Payment Advice'],
  'Bank': ['Bank Statements', 'Deposit Slips', 'Loan Agreements'],
  'Government': ['Tax Letters', 'EPF/SOCSO (Payroll Contributions)', 'SST Returns'],
  'Client': ['Internal Memos', 'Expense Claims', 'Payroll Data']
};

// Function to create dynamic file upload fields based on source type
function createFileUploadFields(sourceType, containerId, isEdit = false) {
  const container = document.getElementById(containerId);
  
  if (!sourceType || !documentCategoryMap[sourceType]) {
    container.innerHTML = '<div class="alert alert-warning"><i class="fa-solid fa-info-circle"></i> Please select a Source Type to see required document uploads</div>';
    return;
  }
  
  const categories = documentCategoryMap[sourceType];
  let html = '<div class="alert alert-info mb-3"><i class="fa-solid fa-info-circle"></i> <strong>Please upload the following documents:</strong></div>';
  
  categories.forEach((category, index) => {
    const fieldName = category.toLowerCase().replace(/[^a-z0-9]+/g, '_');
    const required = isEdit ? '' : 'required';
    const requiredLabel = isEdit ? '(Optional - Leave empty to keep existing)' : '*';
    
    html += `
      <div class="mb-3">
        <label for="${containerId}_${fieldName}" class="form-label">
          <i class="fa-solid fa-file-arrow-up"></i> ${category} ${requiredLabel}
        </label>
        <input type="file" 
               class="form-control" 
               id="${containerId}_${fieldName}" 
               name="doc_files[${fieldName}]" 
               ${required}
               accept=".pdf,.png,.jpg,.jpeg,.doc,.docx,.xls,.xlsx">
        <input type="hidden" name="doc_categories[]" value="${category}">
        <div class="form-text text-muted">Upload ${category} (PDF, PNG, JPG, etc.)</div>
      </div>
    `;
  });
  
  container.innerHTML = html;
}

// Add event listener for source type change in Add Document modal
document.getElementById('add_doc_source_type').addEventListener('change', function() {
  createFileUploadFields(this.value, 'add_doc_file_container', false);
});

// Add event listener for source type change in Edit Document modal
document.getElementById('edit_doc_source_type').addEventListener('change', function() {
  createFileUploadFields(this.value, 'edit_doc_file_container', true);
});

// Handle add document form submission
document.getElementById('addDocumentForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  formData.append('action', 'add_document');
  
  fetch('index.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('addDocumentModal')).hide();
      this.reset();
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while adding the document.');
  });
});

// Handle edit document button clicks
document.querySelectorAll('.edit-document').forEach(button => {
  button.addEventListener('click', function(e) {
    e.stopPropagation();
    const docId = this.getAttribute('data-id');
    
    // Fetch document data
    fetch(`?action=view&type=document&id=${docId}`)
      .then(response => response.json())
      .then(doc => {
        if (doc && doc.document_id) {
          document.getElementById('edit_doc_id').value = doc.document_id;
          document.getElementById('edit_doc_company').value = doc.company_id;
          document.getElementById('edit_doc_title').value = doc.document_title;
          document.getElementById('edit_doc_type').value = doc.document_type;
          document.getElementById('edit_doc_description').value = doc.description || '';
          document.getElementById('edit_doc_date').value = doc.date_of_collect;
          document.getElementById('edit_doc_location').value = doc.location || '';
          
          // Set source type and trigger file fields update
          if (doc.source_type) {
            document.getElementById('edit_doc_source_type').value = doc.source_type;
            createFileUploadFields(doc.source_type, 'edit_doc_file_container', true);
          }
          
          new bootstrap.Modal(document.getElementById('editDocumentModal')).show();
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error loading document data!');
      });
  });
});

// Handle edit document form submission
document.getElementById('editDocumentForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  formData.append('action', 'update_document');
  
  fetch('index.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('editDocumentModal')).hide();
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while updating the document.');
  });
});

// Handle delete document button clicks
document.querySelectorAll('.delete-document').forEach(button => {
  button.addEventListener('click', function(e) {
    e.stopPropagation();
    const docId = this.getAttribute('data-id');
    
    if (confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
      const formData = new FormData();
      formData.append('action', 'delete_document');
      formData.append('document_id', docId);
      
      fetch('index.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showToast(data.message);
          location.reload();
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the document.');
      });
    }
  });
});

// Handle update status button clicks
document.querySelectorAll('.update-status-document').forEach(button => {
  button.addEventListener('click', function(e) {
    e.stopPropagation();
    const docId = this.getAttribute('data-id');
    
    document.getElementById('status_doc_id').value = docId;
    new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
  });
});

// Handle update status form submission
document.getElementById('updateStatusForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const newStatus = document.getElementById('update_status').value;
  const docId = document.getElementById('status_doc_id').value;
  
  // If trying to approve (Reviewed status for manager), validate supplier details
  if (newStatus === 'Reviewed') {
    // Check if this is a supplier document and validate details
    fetch(`?action=view&type=document&id=${docId}`)
      .then(response => response.json())
      .then(doc => {
        if (doc.source_type === 'Supplier') {
          // Check if all files have details
          fetch(`?action=view&type=document_files&id=${docId}`)
            .then(response => response.json())
            .then(files => {
              const totalFiles = files.length;
              const filesWithDetails = files.filter(f => f.supplier_name).length;
              
              if (filesWithDetails < totalFiles) {
                alert(`Cannot approve! Please enter details for all ${totalFiles} files.\nCurrent status: ${filesWithDetails} of ${totalFiles} files have details entered.`);
                return;
              }
              
              // Validate consistency across all files
              const firstFile = files[0];
              const supplierName = firstFile.supplier_name;
              const piNo = firstFile.supplier_pi_no;
              const invoiceDate = firstFile.invoice_date;
              const amount = parseFloat(firstFile.amount);
              const taxRate = parseFloat(firstFile.tax_amount);
              
              let consistencyErrors = [];
              files.forEach((file, index) => {
                if (file.supplier_name !== supplierName) {
                  consistencyErrors.push(`${file.category}: Supplier name "${file.supplier_name}" does not match "${supplierName}"`);
                }
                if (file.supplier_pi_no !== piNo) {
                  consistencyErrors.push(`${file.category}: PI No "${file.supplier_pi_no}" does not match "${piNo}"`);
                }
                if (file.invoice_date !== invoiceDate) {
                  consistencyErrors.push(`${file.category}: Invoice date "${file.invoice_date}" does not match "${invoiceDate}"`);
                }
                
                // Validate amount consistency
                const fileAmount = parseFloat(file.amount);
                if (Math.abs(fileAmount - amount) > 0.01) {
                  consistencyErrors.push(`${file.category}: Amount RM ${fileAmount.toFixed(2)} does not match RM ${amount.toFixed(2)}`);
                }
                
                // Validate tax rate consistency
                const fileTaxRate = parseFloat(file.tax_amount);
                if (Math.abs(fileTaxRate - taxRate) > 0.01) {
                  consistencyErrors.push(`${file.category}: Tax rate ${fileTaxRate.toFixed(2)}% does not match ${taxRate.toFixed(2)}%`);
                }
                
                // Validate tax amount calculation
                const expectedTaxAmount = fileAmount * (fileTaxRate / 100);
                const actualTaxAmount = parseFloat(file.total_amount);
                if (Math.abs(actualTaxAmount - expectedTaxAmount) > 0.01) {
                  consistencyErrors.push(`${file.category}: Tax amount RM ${actualTaxAmount.toFixed(2)} is incorrect (should be RM ${expectedTaxAmount.toFixed(2)})`);
                }
              });
              
              if (consistencyErrors.length > 0) {
                alert(`Cannot approve! Information is inconsistent across files:\n\n${consistencyErrors.join('\n')}\n\nPlease ensure Supplier Name, PI No, and Invoice Date are the same for all 3 files.`);
                return;
              }
              
              // All details entered and consistent, proceed with status update
              submitStatusUpdate(this);
            });
        } else {
          // Not a supplier document, proceed normally
          submitStatusUpdate(this);
        }
      });
  } else {
    // Not trying to approve, proceed normally
    submitStatusUpdate(this);
  }
});

function submitStatusUpdate(form) {
  const formData = new FormData(form);
  formData.append('action', 'update_document_status');
  
  fetch('index.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('updateStatusModal')).hide();
      form.reset();
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while updating the status.');
  });
}

// Handle document row clicks to view details
document.querySelectorAll('.document-row').forEach(row => {
  row.addEventListener('click', function(e) {
    const docId = this.getAttribute('data-document-id');
    viewDocumentDetails(docId);
  });
});

// Function to view document details with role-based actions
function viewDocumentDetails(docId) {
  fetch(`?action=view&type=document&id=${docId}`)
    .then(response => response.json())
    .then(doc => {
      if (doc && doc.document_id) {
        // Populate header section
        document.getElementById('view-doc-title-header').textContent = doc.document_title;
        document.getElementById('view-doc-id-badge').textContent = `ID: ${doc.document_id}`;
        document.getElementById('view-doc-type-badge').textContent = doc.document_type;
        
        // Status badge in header
        const statusBadgeHeader = document.getElementById('view-doc-status-badge');
        let statusClass = 'status-badge-large ';
        switch(doc.status) {
          case 'Pending': statusClass += 'bg-warning'; break;
          case 'Reviewed': statusClass += 'bg-info'; break;
          case 'Approved': statusClass += 'bg-primary'; break;
          case 'Final Approved': statusClass += 'bg-success'; break;
          case 'Rejected': statusClass += 'bg-danger'; break;
          case 'Returned': statusClass += 'bg-secondary'; break;
          case 'Submit': statusClass += 'bg-dark'; break;
        }
        statusBadgeHeader.className = statusClass;
        statusBadgeHeader.textContent = doc.status;
        
        // Populate detail sections
        document.getElementById('view-doc-title').textContent = doc.document_title;
        document.getElementById('view-doc-type').textContent = doc.document_type;
        document.getElementById('view-doc-source-type').textContent = doc.source_type || 'N/A';
        document.getElementById('view-doc-company').textContent = doc.company_name || 'N/A';
        document.getElementById('view-doc-description').textContent = doc.description || 'No description provided';
        
        // Load document files
        loadDocumentFiles(docId);
        
        document.getElementById('view-doc-date').textContent = doc.date_of_collect;
        document.getElementById('view-doc-location').textContent = doc.location || 'N/A';
        document.getElementById('view-doc-creator').textContent = doc.creator_name || 'N/A';
        
        // Set handler based on status
        let handlerText = '';
        let handlerPhone = '';
        if (doc.status === 'Submit') {
          handlerText = 'Submitted to Client';
          handlerPhone = doc.creator_phone || 'N/A';
        } else if (doc.status === 'Final Approved') {
          handlerText = doc.creator_name || 'Creator';
          handlerPhone = doc.creator_phone || 'N/A';
        } else if (doc.status === 'Rejected') {
          handlerText = 'None (Rejected)';
          handlerPhone = 'N/A';
        } else if (doc.handler_name) {
          handlerText = doc.handler_name;
          handlerPhone = doc.handler_phone || 'N/A';
        } else {
          handlerText = 'Unassigned';
          handlerPhone = 'N/A';
        }
        document.getElementById('view-doc-handler').textContent = handlerText;
        document.getElementById('view-doc-handler-phone').textContent = handlerPhone;
        document.getElementById('view-doc-created').textContent = doc.created_at || 'N/A';
        document.getElementById('view-doc-updated').textContent = doc.updated_at || 'N/A';
        
        // Update workflow progress indicator
        updateWorkflowProgress(doc.status);
        
        // Load document history
        loadDocumentHistory(docId);
        
        // Generate role-based action buttons
        generateActionButtons(doc);
        
        new bootstrap.Modal(document.getElementById('viewDocumentModal')).show();
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error loading document details!');
    });
}

// Function to update workflow progress indicator
function updateWorkflowProgress(status) {
  // Reset all steps
  const steps = ['pending', 'reviewed', 'approved', 'final', 'submit'];
  steps.forEach(step => {
    const element = document.getElementById(`workflow-step-${step}`);
    element.className = 'workflow-step';
  });
  
  // Mark completed and active steps based on status
  switch(status) {
    case 'Pending':
      document.getElementById('workflow-step-pending').classList.add('active');
      break;
    case 'Reviewed':
      document.getElementById('workflow-step-pending').classList.add('completed');
      document.getElementById('workflow-step-reviewed').classList.add('active');
      break;
    case 'Approved':
      document.getElementById('workflow-step-pending').classList.add('completed');
      document.getElementById('workflow-step-reviewed').classList.add('completed');
      document.getElementById('workflow-step-approved').classList.add('active');
      break;
    case 'Final Approved':
      document.getElementById('workflow-step-pending').classList.add('completed');
      document.getElementById('workflow-step-reviewed').classList.add('completed');
      document.getElementById('workflow-step-approved').classList.add('completed');
      document.getElementById('workflow-step-final').classList.add('active');
      break;
    case 'Submit':
      document.getElementById('workflow-step-pending').classList.add('completed');
      document.getElementById('workflow-step-reviewed').classList.add('completed');
      document.getElementById('workflow-step-approved').classList.add('completed');
      document.getElementById('workflow-step-final').classList.add('completed');
      document.getElementById('workflow-step-submit').classList.add('active');
      break;
    case 'Rejected':
      document.getElementById('workflow-step-pending').classList.add('rejected');
      break;
    case 'Returned':
      // Show which step it was returned from
      document.getElementById('workflow-step-pending').classList.add('active');
      break;
  }
}

// Function to load document files
function loadDocumentFiles(documentId) {
  const filesContainer = document.getElementById('view-doc-files-list');
  filesContainer.innerHTML = '<div class="text-muted"><i class="fa-solid fa-spinner fa-spin"></i> Loading files...</div>';
  
  // Get document info first to check source type and user role
  fetch(`?action=view&type=document&id=${documentId}`)
    .then(response => response.json())
    .then(doc => {
      const isSupplier = doc.source_type === 'Supplier';
      const isCustomer = doc.source_type === 'Customer';
      const isBank = doc.source_type === 'Bank';
      const isGovernment = doc.source_type === 'Government';
      const isClient = doc.source_type === 'Client';
      const userRole = '<?php echo $_SESSION['role'] ?? 'employee'; ?>';
      const isAccountant = userRole === 'accountant';
      
      fetch(`?action=view&type=document_files&id=${documentId}`)
        .then(response => response.json())
        .then(files => {
          if (files && files.length > 0) {
            // Count files with details entered
            const totalFiles = files.length;
            const filesWithDetails = files.filter(f => isSupplier ? f.supplier_name : (isCustomer ? f.customer_name : (isBank ? f.bank_name : (isGovernment ? f.agency_name : (isClient ? f.department : false))))).length;
            const allDetailsEntered = filesWithDetails === totalFiles;
            
            // Validate consistency across all files
            let isConsistent = true;
            let consistencyErrors = [];
            
            if (allDetailsEntered && isSupplier) {
              const firstFile = files[0];
              const supplierName = firstFile.supplier_name;
              const piNo = firstFile.supplier_pi_no;
              const invoiceDate = firstFile.invoice_date;
              const amount = parseFloat(firstFile.amount);
              const taxRate = parseFloat(firstFile.tax_amount);
              
              files.forEach((file, index) => {
                if (file.supplier_name !== supplierName) {
                  isConsistent = false;
                  consistencyErrors.push(`${file.category}: Supplier name mismatch (${file.supplier_name} vs ${supplierName})`);
                }
                if (file.supplier_pi_no !== piNo) {
                  isConsistent = false;
                  consistencyErrors.push(`${file.category}: PI No mismatch (${file.supplier_pi_no} vs ${piNo})`);
                }
                if (file.invoice_date !== invoiceDate) {
                  isConsistent = false;
                  consistencyErrors.push(`${file.category}: Invoice date mismatch (${file.invoice_date} vs ${invoiceDate})`);
                }
                
                // Validate amount consistency
                const fileAmount = parseFloat(file.amount);
                if (Math.abs(fileAmount - amount) > 0.01) {
                  isConsistent = false;
                  consistencyErrors.push(`${file.category}: Amount mismatch (RM ${fileAmount.toFixed(2)} vs RM ${amount.toFixed(2)})`);
                }
                
                // Validate tax rate consistency
                const fileTaxRate = parseFloat(file.tax_amount);
                if (Math.abs(fileTaxRate - taxRate) > 0.01) {
                  isConsistent = false;
                  consistencyErrors.push(`${file.category}: Tax rate mismatch (${fileTaxRate.toFixed(2)}% vs ${taxRate.toFixed(2)}%)`);
                }
                
                // Validate tax amount calculation
                const expectedTaxAmount = fileAmount * (fileTaxRate / 100);
                const actualTaxAmount = parseFloat(file.total_amount);
                if (Math.abs(actualTaxAmount - expectedTaxAmount) > 0.01) {
                  isConsistent = false;
                  consistencyErrors.push(`${file.category}: Tax amount calculation error (RM ${actualTaxAmount.toFixed(2)} should be RM ${expectedTaxAmount.toFixed(2)})`);
                }
              });
            }
            
            // Add customer validation
            if (allDetailsEntered && isCustomer) {
              const firstFile = files[0];
              const customerName = firstFile.customer_name;
              const invoiceNumber = firstFile.invoice_number;
              const salesDate = firstFile.sales_date;
              const amount = parseFloat(firstFile.amount);
              
              files.forEach((file, index) => {
                if (file.customer_name !== customerName) {
                  isConsistent = false;
                  consistencyErrors.push(`${file.category}: Customer name mismatch (${file.customer_name} vs ${customerName})`);
                }
                if (file.invoice_number !== invoiceNumber) {
                  isConsistent = false;
                  consistencyErrors.push(`${file.category}: Invoice number mismatch (${file.invoice_number} vs ${invoiceNumber})`);
                }
                if (file.sales_date !== salesDate) {
                  isConsistent = false;
                  consistencyErrors.push(`${file.category}: Sales date mismatch (${file.sales_date} vs ${salesDate})`);
                }
                
                const fileAmount = parseFloat(file.amount);
                if (Math.abs(fileAmount - amount) > 0.01) {
                  isConsistent = false;
                  consistencyErrors.push(`${file.category}: Amount mismatch (RM ${fileAmount.toFixed(2)} vs RM ${amount.toFixed(2)})`);
                }
              });
            }
            
            // Add bank validation
            if (allDetailsEntered && isBank) {
              const firstFile = files[0];
              const bankName = firstFile.bank_name;
              const accountNumber = firstFile.account_number;
              const statementPeriod = firstFile.statement_period;
              
              files.forEach((file, index) => {
                if (file.bank_name !== bankName) {
                  isConsistent = false;
                  consistencyErrors.push(`${file.category}: Bank name mismatch (${file.bank_name} vs ${bankName})`);
                }
                if (file.account_number !== accountNumber) {
                  isConsistent = false;
                  consistencyErrors.push(`${file.category}: Account number mismatch (${file.account_number} vs ${accountNumber})`);
                }
                if (file.statement_period !== statementPeriod) {
                  isConsistent = false;
                  consistencyErrors.push(`${file.category}: Statement period mismatch (${file.statement_period} vs ${statementPeriod})`);
                }
              });
            }
            
            // Add government validation
            if (allDetailsEntered && isGovernment) {
              const firstFile = files[0];
              const agencyName = firstFile.agency_name;
              const periodCovered = firstFile.period_covered;
              
              files.forEach((file, index) => {
                if (file.agency_name !== agencyName) {
                  isConsistent = false;
                  consistencyErrors.push(`${file.category}: Agency name mismatch (${file.agency_name} vs ${agencyName})`);
                }
                if (file.period_covered !== periodCovered) {
                  isConsistent = false;
                  consistencyErrors.push(`${file.category}: Period covered mismatch (${file.period_covered} vs ${periodCovered})`);
                }
              });
            }
            
            // Add client validation
            if (allDetailsEntered && isClient) {
              const firstFile = files[0];
              const department = firstFile.department;
              const claimDate = firstFile.claim_date;
              
              files.forEach((file, index) => {
                if (file.department !== department) {
                  isConsistent = false;
                  consistencyErrors.push(`${file.category}: Department mismatch (${file.department} vs ${department})`);
                }
                if (file.claim_date !== claimDate) {
                  isConsistent = false;
                  consistencyErrors.push(`${file.category}: Claim date mismatch (${file.claim_date} vs ${claimDate})`);
                }
              });
            }
            
            const isValid = allDetailsEntered && isConsistent;
            
            let html = '';
            
            // Add validation summary for supplier documents
            if (isSupplier) {
              let alertClass = 'alert-warning';
              let statusIcon = 'fa-exclamation-triangle';
              let statusBadge = '<span class="badge bg-danger"><i class="fa-solid fa-times"></i> Details Required</span>';
              let statusMessage = `${filesWithDetails} of ${totalFiles} files have details entered`;
              let helpText = '';
              
              if (allDetailsEntered && !isConsistent) {
                alertClass = 'alert-danger';
                statusIcon = 'fa-times-circle';
                statusBadge = '<span class="badge bg-danger"><i class="fa-solid fa-times"></i> Inconsistent Data</span>';
                statusMessage = 'All files have details, but information is inconsistent';
                helpText = '<small class="d-block mt-2"><strong>Errors:</strong><br>' + consistencyErrors.join('<br>') + '</small>';
              } else if (isValid) {
                alertClass = 'alert-success';
                statusIcon = 'fa-check-circle';
                statusBadge = '<span class="badge bg-success"><i class="fa-solid fa-check"></i> Ready for Approval</span>';
                statusMessage = 'All files validated - Information is consistent';
              } else if (!allDetailsEntered && isAccountant) {
                helpText = '<small class="d-block mt-2">Please enter details for all files before approving this document.</small>';
              }
              
              html += `
                <div class="alert ${alertClass} mb-3">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <i class="fa-solid ${statusIcon}"></i>
                      <strong>Document Details Status:</strong> ${statusMessage}
                    </div>
                    ${statusBadge}
                  </div>
                  ${helpText}
                </div>
              `;
            }
            
            // Add validation summary for customer documents
            if (isCustomer) {
              let alertClass = 'alert-warning';
              let statusIcon = 'fa-exclamation-triangle';
              let statusBadge = '<span class="badge bg-danger"><i class="fa-solid fa-times"></i> Details Required</span>';
              let statusMessage = `${filesWithDetails} of ${totalFiles} files have details entered`;
              let helpText = '';
              
              if (allDetailsEntered && !isConsistent) {
                alertClass = 'alert-danger';
                statusIcon = 'fa-times-circle';
                statusBadge = '<span class="badge bg-danger"><i class="fa-solid fa-times"></i> Inconsistent Data</span>';
                statusMessage = 'All files have details, but information is inconsistent';
                helpText = '<small class="d-block mt-2"><strong>Errors:</strong><br>' + consistencyErrors.join('<br>') + '</small>';
              } else if (isValid) {
                alertClass = 'alert-success';
                statusIcon = 'fa-check-circle';
                statusBadge = '<span class="badge bg-success"><i class="fa-solid fa-check"></i> Ready for Approval</span>';
                statusMessage = 'All files validated - Information is consistent';
              } else if (!allDetailsEntered && isAccountant) {
                helpText = '<small class="d-block mt-2">Please enter details for all files before approving this document.</small>';
              }
              
              html += `
                <div class="alert ${alertClass} mb-3">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <i class="fa-solid ${statusIcon}"></i>
                      <strong>Document Details Status:</strong> ${statusMessage}
                    </div>
                    ${statusBadge}
                  </div>
                  ${helpText}
                </div>
              `;
            }
            
            // Add validation summary for bank documents
            if (isBank) {
              let alertClass = 'alert-warning';
              let statusIcon = 'fa-exclamation-triangle';
              let statusBadge = '<span class="badge bg-danger"><i class="fa-solid fa-times"></i> Details Required</span>';
              let statusMessage = `${filesWithDetails} of ${totalFiles} files have details entered`;
              let helpText = '';
              
              if (allDetailsEntered && !isConsistent) {
                alertClass = 'alert-danger';
                statusIcon = 'fa-times-circle';
                statusBadge = '<span class="badge bg-danger"><i class="fa-solid fa-times"></i> Inconsistent Data</span>';
                statusMessage = 'All files have details, but information is inconsistent';
                helpText = '<small class="d-block mt-2"><strong>Errors:</strong><br>' + consistencyErrors.join('<br>') + '</small>';
              } else if (isValid) {
                alertClass = 'alert-success';
                statusIcon = 'fa-check-circle';
                statusBadge = '<span class="badge bg-success"><i class="fa-solid fa-check"></i> Ready for Approval</span>';
                statusMessage = 'All files validated - Information is consistent';
              } else if (!allDetailsEntered && isAccountant) {
                helpText = '<small class="d-block mt-2">Please enter details for all files before approving this document.</small>';
              }
              
              html += `
                <div class="alert ${alertClass} mb-3">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <i class="fa-solid ${statusIcon}"></i>
                      <strong>Document Details Status:</strong> ${statusMessage}
                    </div>
                    ${statusBadge}
                  </div>
                  ${helpText}
                </div>
              `;
            }
            
            // Add validation summary for government documents
            if (isGovernment) {
              let alertClass = 'alert-warning';
              let statusIcon = 'fa-exclamation-triangle';
              let statusBadge = '<span class="badge bg-danger"><i class="fa-solid fa-times"></i> Details Required</span>';
              let statusMessage = `${filesWithDetails} of ${totalFiles} files have details entered`;
              let helpText = '';
              
              if (allDetailsEntered && !isConsistent) {
                alertClass = 'alert-danger';
                statusIcon = 'fa-times-circle';
                statusBadge = '<span class="badge bg-danger"><i class="fa-solid fa-times"></i> Inconsistent Data</span>';
                statusMessage = 'All files have details, but information is inconsistent';
                helpText = '<small class="d-block mt-2"><strong>Errors:</strong><br>' + consistencyErrors.join('<br>') + '</small>';
              } else if (isValid) {
                alertClass = 'alert-success';
                statusIcon = 'fa-check-circle';
                statusBadge = '<span class="badge bg-success"><i class="fa-solid fa-check"></i> Ready for Approval</span>';
                statusMessage = 'All files validated - Information is consistent';
              } else if (!allDetailsEntered && isAccountant) {
                helpText = '<small class="d-block mt-2">Please enter details for all files before approving this document.</small>';
              }
              
              html += `
                <div class="alert ${alertClass} mb-3">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <i class="fa-solid ${statusIcon}"></i>
                      <strong>Document Details Status:</strong> ${statusMessage}
                    </div>
                    ${statusBadge}
                  </div>
                  ${helpText}
                </div>
              `;
            }
            
            // Add validation summary for client documents
            if (isClient) {
              let alertClass = 'alert-warning';
              let statusIcon = 'fa-exclamation-triangle';
              let statusBadge = '<span class="badge bg-danger"><i class="fa-solid fa-times"></i> Details Required</span>';
              let statusMessage = `${filesWithDetails} of ${totalFiles} files have details entered`;
              let helpText = '';
              
              if (allDetailsEntered && !isConsistent) {
                alertClass = 'alert-danger';
                statusIcon = 'fa-times-circle';
                statusBadge = '<span class="badge bg-danger"><i class="fa-solid fa-times"></i> Inconsistent Data</span>';
                statusMessage = 'All files have details, but information is inconsistent';
                helpText = '<small class="d-block mt-2"><strong>Errors:</strong><br>' + consistencyErrors.join('<br>') + '</small>';
              } else if (isValid) {
                alertClass = 'alert-success';
                statusIcon = 'fa-check-circle';
                statusBadge = '<span class="badge bg-success"><i class="fa-solid fa-check"></i> Ready for Approval</span>';
                statusMessage = 'All files validated - Information is consistent';
              } else if (!allDetailsEntered && isAccountant) {
                helpText = '<small class="d-block mt-2">Please enter details for all files before approving this document.</small>';
              }
              
              html += `
                <div class="alert ${alertClass} mb-3">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <i class="fa-solid ${statusIcon}"></i>
                      <strong>Document Details Status:</strong> ${statusMessage}
                    </div>
                    ${statusBadge}
                  </div>
                  ${helpText}
                </div>
              `;
            }
            
            html += '<div class="list-group">';
            files.forEach(file => {
              const hasDetails = isSupplier ? (file.supplier_name ? true : false) : (isCustomer ? (file.customer_name ? true : false) : (isBank ? (file.bank_name ? true : false) : (isGovernment ? (file.agency_name ? true : false) : (isClient ? (file.department ? true : false) : false))));
              const detailsClass = hasDetails ? 'bg-success' : 'bg-secondary';
              const detailsIcon = hasDetails ? 'fa-check-circle' : 'fa-circle';
              
              html += `
                <div class="list-group-item ${detailsClass} text-light">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <i class="fa-solid fa-file-pdf text-danger"></i>
                      <strong>${file.category}</strong>
                      ${hasDetails ? '<span class="badge bg-info ms-2"><i class="fa-solid fa-check"></i> Details Entered</span>' : ''}
                    </div>
                    <div class="btn-group" role="group">
                      <a href="uploads/documents/${file.file_name}" target="_blank" class="btn btn-sm btn-primary" style="min-width: 110px;">
                        <i class="fa-solid fa-download"></i> Download
                      </a>
                      ${(isSupplier || isCustomer || isBank || isGovernment || isClient) && isAccountant ? `
                        <button class="btn btn-sm ${hasDetails ? 'btn-warning' : 'btn-info'}" style="min-width: 130px;" onclick="${isSupplier ? 'openSupplierDetailsModal' : isCustomer ? 'openCustomerDetailsModal' : isBank ? 'openBankDetailsModal' : isGovernment ? 'openGovernmentDetailsModal' : 'openClientDetailsModal'}(${file.file_id}, ${documentId}, '${file.category}', ${hasDetails})">
                          <i class="fa-solid ${hasDetails ? 'fa-edit' : 'fa-plus-circle'}"></i> ${hasDetails ? 'Edit Details' : 'Enter Details'}
                        </button>
                      ` : ''}
                      ${hasDetails && (isSupplier || isCustomer || isBank || isGovernment || isClient) ? `
                        <button class="btn btn-sm btn-light" style="min-width: 80px;" onclick="${isSupplier ? 'viewSupplierDetails' : isCustomer ? 'viewCustomerDetails' : isBank ? 'viewBankDetails' : isGovernment ? 'viewGovernmentDetails' : 'viewClientDetails'}(${file.file_id})">
                          <i class="fa-solid fa-eye"></i> View
                        </button>
                      ` : ''}
                    </div>
                  </div>
                  ${hasDetails && isSupplier ? `
                    <div class="mt-2 pt-2 border-top border-light" style="font-size: 0.9em;">
                      <div class="row">
                        <div class="col-md-6">
                          <strong>Supplier:</strong> ${file.supplier_name}<br>
                          <strong>PI No:</strong> ${file.supplier_pi_no}<br>
                          <strong>Invoice Date:</strong> ${file.invoice_date}
                        </div>
                        <div class="col-md-6 text-end">
                          <strong>Amount:</strong> RM ${parseFloat(file.amount).toFixed(2)}<br>
                          <strong>Tax Rate:</strong> ${parseFloat(file.tax_amount).toFixed(2)}%<br>
                          <strong class="text-warning">Tax Amount:</strong> <span class="text-warning">RM ${parseFloat(file.total_amount).toFixed(2)}</span>
                        </div>
                      </div>
                    </div>
                  ` : ''}
                  ${hasDetails && isCustomer ? `
                    <div class="mt-2 pt-2 border-top border-light" style="font-size: 0.9em;">
                      <div class="row">
                        <div class="col-md-6">
                          <strong>Customer:</strong> ${file.customer_name}<br>
                          <strong>Invoice No:</strong> ${file.invoice_number}<br>
                          <strong>Sales Date:</strong> ${file.sales_date}
                        </div>
                        <div class="col-md-6 text-end">
                          <strong>Amount:</strong> RM ${parseFloat(file.amount).toFixed(2)}
                        </div>
                      </div>
                    </div>
                  ` : ''}
                  ${hasDetails && isBank ? `
                    <div class="mt-2 pt-2 border-top border-light" style="font-size: 0.9em;">
                      <div class="row">
                        <div class="col-md-6">
                          <strong>Bank:</strong> ${file.bank_name}<br>
                          <strong>Account:</strong> ${file.account_number}<br>
                          <strong>Period:</strong> ${file.statement_period}
                        </div>
                        <div class="col-md-6 text-end">
                          <strong class="text-danger">Debit:</strong> <span class="text-danger">RM ${parseFloat(file.total_debit).toFixed(2)}</span><br>
                          <strong class="text-success">Credit:</strong> <span class="text-success">RM ${parseFloat(file.total_credit).toFixed(2)}</span><br>
                          <strong>Balance:</strong> RM ${parseFloat(file.balance).toFixed(2)}
                        </div>
                      </div>
                    </div>
                  ` : ''}
                  ${hasDetails && isGovernment ? `
                    <div class="mt-2 pt-2 border-top border-light" style="font-size: 0.9em;">
                      <div class="row">
                        <div class="col-md-6">
                          <strong>Agency:</strong> ${file.agency_name}<br>
                          <strong>Reference No:</strong> ${file.reference_no}<br>
                          <strong>Period:</strong> ${file.period_covered}
                        </div>
                        <div class="col-md-6 text-end">
                          <strong>Submission Date:</strong> ${file.submission_date}<br>
                          <strong class="text-success">Amount Paid:</strong> <span class="text-success">RM ${parseFloat(file.amount_paid).toFixed(2)}</span><br>
                          ${file.acknowledgement_file ? `<strong>Ack File:</strong> ${file.acknowledgement_file}` : ''}
                        </div>
                      </div>
                    </div>
                  ` : ''}
                  ${hasDetails && isClient ? `
                    <div class="mt-2 pt-2 border-top border-light" style="font-size: 0.9em;">
                      <div class="row">
                        <div class="col-md-6">
                          ${file.employee_name ? `<strong>Employee:</strong> ${file.employee_name}<br>` : ''}
                          <strong>Department:</strong> ${file.department}<br>
                          <strong>Claim Date:</strong> ${file.claim_date}
                        </div>
                        <div class="col-md-6 text-end">
                          <strong class="text-success">Amount:</strong> <span class="text-success">RM ${parseFloat(file.amount).toFixed(2)}</span><br>
                          <strong>Approved By:</strong> ${file.approved_by}
                        </div>
                      </div>
                    </div>
                  ` : ''}
                </div>
              `;
            });
            html += '</div>';
            filesContainer.innerHTML = html;
          } else {
            filesContainer.innerHTML = '<div class="text-muted">No files uploaded</div>';
          }
        })
        .catch(error => {
          console.error('Error loading files:', error);
          filesContainer.innerHTML = '<div class="text-danger">Error loading files</div>';
        });
    });
}

// Function to open supplier details modal
function openSupplierDetailsModal(fileId, documentId, category, hasDetails) {
  document.getElementById('supplier_file_id').value = fileId;
  document.getElementById('supplier_doc_id').value = documentId;
  document.getElementById('supplier_category_display').textContent = category;
  
  // Reset form
  document.getElementById('supplierDetailsForm').reset();
  document.getElementById('supplier_file_id').value = fileId;
  document.getElementById('supplier_doc_id').value = documentId;
  
  // If editing, load existing data
  if (hasDetails) {
    fetch(`?action=view&type=supplier_details&file_id=${fileId}`)
      .then(response => response.json())
      .then(data => {
        if (data) {
          document.getElementById('supplier_name').value = data.supplier_name || '';
          document.getElementById('supplier_pi_no').value = data.supplier_pi_no || '';
          document.getElementById('inventory').value = data.inventory || '';
          document.getElementById('invoice_date').value = data.invoice_date || '';
          document.getElementById('amount').value = data.amount || '';
          document.getElementById('tax_amount').value = data.tax_amount || '';
          document.getElementById('total_amount').value = data.total_amount || '';
        }
      });
  }
  
  const supplierModal = new bootstrap.Modal(document.getElementById('supplierDetailsModal'));
  
  // Ensure modal appears on top of document details modal
  const supplierModalElement = document.getElementById('supplierDetailsModal');
  supplierModalElement.addEventListener('shown.bs.modal', function () {
    const modalBackdrop = document.querySelector('.modal-backdrop:last-of-type');
    if (modalBackdrop) {
      modalBackdrop.style.zIndex = '1059';
    }
    supplierModalElement.style.zIndex = '1060';
  });
  
  supplierModal.show();
}

// Auto-calculate total amount
document.getElementById('amount').addEventListener('input', calculateTotal);
document.getElementById('tax_amount').addEventListener('input', calculateTotal);

function calculateTotal() {
  const amount = parseFloat(document.getElementById('amount').value) || 0;
  const taxRate = parseFloat(document.getElementById('tax_amount').value) || 0;
  const taxAmount = amount * (taxRate / 100);
  document.getElementById('total_amount').value = taxAmount.toFixed(2);
}

// Handle supplier details form submission
document.getElementById('supplierDetailsForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  formData.append('action', 'save_supplier_details');
  
  fetch('index.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('supplierDetailsModal')).hide();
      // Reload the files list
      const docId = document.getElementById('supplier_doc_id').value;
      loadDocumentFiles(docId);
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while saving supplier details.');
  });
});

// Function to view supplier details (read-only)
function viewSupplierDetails(fileId) {
  fetch(`?action=view&type=supplier_details&file_id=${fileId}`)
    .then(response => response.json())
    .then(data => {
      if (data) {
        const amount = parseFloat(data.amount);
        const taxRate = parseFloat(data.tax_amount);
        const taxAmount = parseFloat(data.total_amount);
        
        // Populate modal fields
        document.getElementById('view_supplier_category').textContent = data.category || 'N/A';
        document.getElementById('view_supplier_name').textContent = data.supplier_name || 'N/A';
        document.getElementById('view_supplier_pi_no').textContent = data.supplier_pi_no || 'N/A';
        document.getElementById('view_invoice_date').textContent = data.invoice_date || 'N/A';
        document.getElementById('view_amount').textContent = 'RM ' + amount.toFixed(2);
        document.getElementById('view_tax_rate').textContent = taxRate.toFixed(2) + '%';
        document.getElementById('view_tax_amount').textContent = 'RM ' + taxAmount.toFixed(2);
        document.getElementById('view_inventory').textContent = data.inventory || 'No inventory details';
        
        // Get user info for "entered by"
        if (data.details_entered_by) {
          fetch(`?action=view&type=user&id=${data.details_entered_by}`)
            .then(response => response.json())
            .then(user => {
              document.getElementById('view_entered_by').textContent = user.username || 'Unknown';
            })
            .catch(() => {
              document.getElementById('view_entered_by').textContent = 'User #' + data.details_entered_by;
            });
        } else {
          document.getElementById('view_entered_by').textContent = 'Unknown';
        }
        
        document.getElementById('view_entered_at').textContent = data.details_entered_at || 'N/A';
        
        // Show modal with z-index handling
        const viewModal = new bootstrap.Modal(document.getElementById('viewSupplierDetailsModal'));
        const viewModalElement = document.getElementById('viewSupplierDetailsModal');
        
        viewModalElement.addEventListener('shown.bs.modal', function () {
          const modalBackdrop = document.querySelector('.modal-backdrop:last-of-type');
          if (modalBackdrop) {
            modalBackdrop.style.zIndex = '1059';
          }
          viewModalElement.style.zIndex = '1060';
        });
        
        viewModal.show();
      }
    })
    .catch(error => {
      console.error('Error loading supplier details:', error);
      alert('Error loading supplier details. Please try again.');
    });
}

// Function to open customer details modal
function openCustomerDetailsModal(fileId, documentId, category, hasDetails) {
  document.getElementById('customer_file_id').value = fileId;
  document.getElementById('customer_doc_id').value = documentId;
  document.getElementById('customer_category_display').textContent = category;
  
  // Reset form
  document.getElementById('customerDetailsForm').reset();
  document.getElementById('customer_file_id').value = fileId;
  document.getElementById('customer_doc_id').value = documentId;
  
  // If editing, load existing data
  if (hasDetails) {
    fetch(`?action=view&type=customer_details&file_id=${fileId}`)
      .then(response => response.json())
      .then(data => {
        if (data) {
          document.getElementById('customer_name').value = data.customer_name || '';
          document.getElementById('invoice_number').value = data.invoice_number || '';
          document.getElementById('sales_date').value = data.sales_date || '';
          document.getElementById('customer_amount').value = data.amount || '';
        }
      });
  }
  
  const customerModal = new bootstrap.Modal(document.getElementById('customerDetailsModal'));
  
  // Ensure modal appears on top of document details modal
  const customerModalElement = document.getElementById('customerDetailsModal');
  customerModalElement.addEventListener('shown.bs.modal', function () {
    const modalBackdrop = document.querySelector('.modal-backdrop:last-of-type');
    if (modalBackdrop) {
      modalBackdrop.style.zIndex = '1059';
    }
    customerModalElement.style.zIndex = '1060';
  });
  
  customerModal.show();
}

// Handle customer details form submission
document.getElementById('customerDetailsForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  formData.append('action', 'save_customer_details');
  
  fetch('index.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('customerDetailsModal')).hide();
      // Reload the files list
      const docId = document.getElementById('customer_doc_id').value;
      loadDocumentFiles(docId);
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while saving customer details.');
  });
});

// Function to view customer details (read-only)
function viewCustomerDetails(fileId) {
  fetch(`?action=view&type=customer_details&file_id=${fileId}`)
    .then(response => response.json())
    .then(data => {
      if (data) {
        const amount = parseFloat(data.amount);
        
        // Populate modal fields
        document.getElementById('view_customer_category').textContent = data.category || 'N/A';
        document.getElementById('view_customer_name').textContent = data.customer_name || 'N/A';
        document.getElementById('view_invoice_number').textContent = data.invoice_number || 'N/A';
        document.getElementById('view_sales_date').textContent = data.sales_date || 'N/A';
        document.getElementById('view_customer_amount').textContent = 'RM ' + amount.toFixed(2);
        
        // Get user info for "entered by"
        if (data.details_entered_by) {
          fetch(`?action=view&type=user&id=${data.details_entered_by}`)
            .then(response => response.json())
            .then(user => {
              document.getElementById('view_customer_entered_by').textContent = user.username || 'Unknown';
            })
            .catch(() => {
              document.getElementById('view_customer_entered_by').textContent = 'User #' + data.details_entered_by;
            });
        } else {
          document.getElementById('view_customer_entered_by').textContent = 'Unknown';
        }
        
        document.getElementById('view_customer_entered_at').textContent = data.details_entered_at || 'N/A';
        
        // Show modal with z-index handling
        const viewModal = new bootstrap.Modal(document.getElementById('viewCustomerDetailsModal'));
        const viewModalElement = document.getElementById('viewCustomerDetailsModal');
        
        viewModalElement.addEventListener('shown.bs.modal', function () {
          const modalBackdrop = document.querySelector('.modal-backdrop:last-of-type');
          if (modalBackdrop) {
            modalBackdrop.style.zIndex = '1059';
          }
          viewModalElement.style.zIndex = '1060';
        });
        
        viewModal.show();
      }
    })
    .catch(error => {
      console.error('Error loading customer details:', error);
      alert('Error loading customer details. Please try again.');
    });
}

// Function to open bank details modal
function openBankDetailsModal(fileId, documentId, category, hasDetails) {
  document.getElementById('bank_file_id').value = fileId;
  document.getElementById('bank_doc_id').value = documentId;
  document.getElementById('bank_category_display').textContent = category;
  
  // Reset form
  document.getElementById('bankDetailsForm').reset();
  document.getElementById('bank_file_id').value = fileId;
  document.getElementById('bank_doc_id').value = documentId;
  
  // If editing, load existing data
  if (hasDetails) {
    fetch(`?action=view&type=bank_details&file_id=${fileId}`)
      .then(response => response.json())
      .then(data => {
        if (data) {
          document.getElementById('bank_name').value = data.bank_name || '';
          document.getElementById('account_number').value = data.account_number || '';
          document.getElementById('statement_period').value = data.statement_period || '';
          document.getElementById('total_debit').value = data.total_debit || '';
          document.getElementById('total_credit').value = data.total_credit || '';
          document.getElementById('balance').value = data.balance || '';
        }
      });
  }
  
  const bankModal = new bootstrap.Modal(document.getElementById('bankDetailsModal'));
  
  // Ensure modal appears on top of document details modal
  const bankModalElement = document.getElementById('bankDetailsModal');
  bankModalElement.addEventListener('shown.bs.modal', function () {
    const modalBackdrop = document.querySelector('.modal-backdrop:last-of-type');
    if (modalBackdrop) {
      modalBackdrop.style.zIndex = '1059';
    }
    bankModalElement.style.zIndex = '1060';
  });
  
  bankModal.show();
}

// Handle bank details form submission
document.getElementById('bankDetailsForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  formData.append('action', 'save_bank_details');
  
  fetch('index.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('bankDetailsModal')).hide();
      // Reload the files list
      const docId = document.getElementById('bank_doc_id').value;
      loadDocumentFiles(docId);
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while saving bank details.');
  });
});

// Function to view bank details (read-only)
function viewBankDetails(fileId) {
  fetch(`?action=view&type=bank_details&file_id=${fileId}`)
    .then(response => response.json())
    .then(data => {
      if (data) {
        const totalDebit = parseFloat(data.total_debit);
        const totalCredit = parseFloat(data.total_credit);
        const balance = parseFloat(data.balance);
        
        // Populate modal fields
        document.getElementById('view_bank_category').textContent = data.category || 'N/A';
        document.getElementById('view_bank_name').textContent = data.bank_name || 'N/A';
        document.getElementById('view_account_number').textContent = data.account_number || 'N/A';
        document.getElementById('view_statement_period').textContent = data.statement_period || 'N/A';
        document.getElementById('view_total_debit').textContent = 'RM ' + totalDebit.toFixed(2);
        document.getElementById('view_total_credit').textContent = 'RM ' + totalCredit.toFixed(2);
        document.getElementById('view_balance').textContent = 'RM ' + balance.toFixed(2);
        
        // Get user info for "entered by"
        if (data.details_entered_by) {
          fetch(`?action=view&type=user&id=${data.details_entered_by}`)
            .then(response => response.json())
            .then(user => {
              document.getElementById('view_bank_entered_by').textContent = user.username || 'Unknown';
            })
            .catch(() => {
              document.getElementById('view_bank_entered_by').textContent = 'User #' + data.details_entered_by;
            });
        } else {
          document.getElementById('view_bank_entered_by').textContent = 'Unknown';
        }
        
        document.getElementById('view_bank_entered_at').textContent = data.details_entered_at || 'N/A';
        
        // Show modal with z-index handling
        const viewModal = new bootstrap.Modal(document.getElementById('viewBankDetailsModal'));
        const viewModalElement = document.getElementById('viewBankDetailsModal');
        
        viewModalElement.addEventListener('shown.bs.modal', function () {
          const modalBackdrop = document.querySelector('.modal-backdrop:last-of-type');
          if (modalBackdrop) {
            modalBackdrop.style.zIndex = '1059';
          }
          viewModalElement.style.zIndex = '1060';
        });
        
        viewModal.show();
      }
    })
    .catch(error => {
      console.error('Error loading bank details:', error);
      alert('Error loading bank details. Please try again.');
    });
}

// Function to open government details modal
function openGovernmentDetailsModal(fileId, documentId, category, hasDetails) {
  document.getElementById('gov_file_id').value = fileId;
  document.getElementById('gov_doc_id').value = documentId;
  document.getElementById('gov_category_display').textContent = category;
  
  // Reset form
  document.getElementById('governmentDetailsForm').reset();
  document.getElementById('gov_file_id').value = fileId;
  document.getElementById('gov_doc_id').value = documentId;
  
  // If editing, load existing data
  if (hasDetails) {
    fetch(`?action=view&type=government_details&file_id=${fileId}`)
      .then(response => response.json())
      .then(data => {
        if (data) {
          document.getElementById('agency_name').value = data.agency_name || '';
          document.getElementById('reference_no').value = data.reference_no || '';
          document.getElementById('period_covered').value = data.period_covered || '';
          document.getElementById('submission_date').value = data.submission_date || '';
          document.getElementById('amount_paid').value = data.amount_paid || '';
          document.getElementById('acknowledgement_file').value = data.acknowledgement_file || '';
        }
      });
  }
  
  const govModal = new bootstrap.Modal(document.getElementById('governmentDetailsModal'));
  
  // Ensure modal appears on top of document details modal
  const govModalElement = document.getElementById('governmentDetailsModal');
  govModalElement.addEventListener('shown.bs.modal', function () {
    const modalBackdrop = document.querySelector('.modal-backdrop:last-of-type');
    if (modalBackdrop) {
      modalBackdrop.style.zIndex = '1059';
    }
    govModalElement.style.zIndex = '1060';
  });
  
  govModal.show();
}

// Handle government details form submission
document.getElementById('governmentDetailsForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  formData.append('action', 'save_government_details');
  
  fetch('index.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('governmentDetailsModal')).hide();
      // Reload the files list
      const docId = document.getElementById('gov_doc_id').value;
      loadDocumentFiles(docId);
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while saving government details.');
  });
});

// Function to view government details (read-only)
function viewGovernmentDetails(fileId) {
  fetch(`?action=view&type=government_details&file_id=${fileId}`)
    .then(response => response.json())
    .then(data => {
      if (data) {
        const amountPaid = parseFloat(data.amount_paid);
        
        // Populate modal fields
        document.getElementById('view_gov_category').textContent = data.category || 'N/A';
        document.getElementById('view_agency_name').textContent = data.agency_name || 'N/A';
        document.getElementById('view_reference_no').textContent = data.reference_no || 'N/A';
        document.getElementById('view_period_covered').textContent = data.period_covered || 'N/A';
        document.getElementById('view_submission_date').textContent = data.submission_date || 'N/A';
        document.getElementById('view_amount_paid').textContent = 'RM ' + amountPaid.toFixed(2);
        document.getElementById('view_acknowledgement_file').textContent = data.acknowledgement_file || 'N/A';
        
        // Get user info for "entered by"
        if (data.details_entered_by) {
          fetch(`?action=view&type=user&id=${data.details_entered_by}`)
            .then(response => response.json())
            .then(user => {
              document.getElementById('view_gov_entered_by').textContent = user.username || 'Unknown';
            })
            .catch(() => {
              document.getElementById('view_gov_entered_by').textContent = 'User #' + data.details_entered_by;
            });
        } else {
          document.getElementById('view_gov_entered_by').textContent = 'Unknown';
        }
        
        document.getElementById('view_gov_entered_at').textContent = data.details_entered_at || 'N/A';
        
        // Show modal with z-index handling
        const viewModal = new bootstrap.Modal(document.getElementById('viewGovernmentDetailsModal'));
        const viewModalElement = document.getElementById('viewGovernmentDetailsModal');
        
        viewModalElement.addEventListener('shown.bs.modal', function () {
          const modalBackdrop = document.querySelector('.modal-backdrop:last-of-type');
          if (modalBackdrop) {
            modalBackdrop.style.zIndex = '1059';
          }
          viewModalElement.style.zIndex = '1060';
        });
        
        viewModal.show();
      }
    })
    .catch(error => {
      console.error('Error loading government details:', error);
      alert('Error loading government details. Please try again.');
    });
}

// Function to open client details modal
function openClientDetailsModal(fileId, documentId, category, hasDetails) {
  document.getElementById('client_file_id').value = fileId;
  document.getElementById('client_doc_id').value = documentId;
  document.getElementById('client_category_display').textContent = category;
  
  // Reset form
  document.getElementById('clientDetailsForm').reset();
  document.getElementById('client_file_id').value = fileId;
  document.getElementById('client_doc_id').value = documentId;
  
  // If editing, load existing data
  if (hasDetails) {
    fetch(`?action=view&type=client_details&file_id=${fileId}`)
      .then(response => response.json())
      .then(data => {
        if (data) {
          document.getElementById('employee_name').value = data.employee_name || '';
          document.getElementById('department').value = data.department || '';
          document.getElementById('claim_date').value = data.claim_date || '';
          document.getElementById('client_amount').value = data.amount || '';
          document.getElementById('approved_by').value = data.approved_by || '';
        }
      });
  }
  
  const clientModal = new bootstrap.Modal(document.getElementById('clientDetailsModal'));
  
  // Ensure modal appears on top of document details modal
  const clientModalElement = document.getElementById('clientDetailsModal');
  clientModalElement.addEventListener('shown.bs.modal', function () {
    const modalBackdrop = document.querySelector('.modal-backdrop:last-of-type');
    if (modalBackdrop) {
      modalBackdrop.style.zIndex = '1059';
    }
    clientModalElement.style.zIndex = '1060';
  });
  
  clientModal.show();
}

// Handle client details form submission
document.getElementById('clientDetailsForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  formData.append('action', 'save_client_details');
  
  fetch('index.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('clientDetailsModal')).hide();
      // Reload the files list
      const docId = document.getElementById('client_doc_id').value;
      loadDocumentFiles(docId);
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while saving client details.');
  });
});

// Function to view client details (read-only)
function viewClientDetails(fileId) {
  fetch(`?action=view&type=client_details&file_id=${fileId}`)
    .then(response => response.json())
    .then(data => {
      if (data) {
        const amount = parseFloat(data.amount);
        
        // Populate modal fields
        document.getElementById('view_client_category').textContent = data.category || 'N/A';
        document.getElementById('view_employee_name').textContent = data.employee_name || 'N/A';
        document.getElementById('view_department').textContent = data.department || 'N/A';
        document.getElementById('view_claim_date').textContent = data.claim_date || 'N/A';
        document.getElementById('view_client_amount').textContent = 'RM ' + amount.toFixed(2);
        document.getElementById('view_approved_by').textContent = data.approved_by || 'N/A';
        
        // Get user info for "entered by"
        if (data.details_entered_by) {
          fetch(`?action=view&type=user&id=${data.details_entered_by}`)
            .then(response => response.json())
            .then(user => {
              document.getElementById('view_client_entered_by').textContent = user.username || 'Unknown';
            })
            .catch(() => {
              document.getElementById('view_client_entered_by').textContent = 'User #' + data.details_entered_by;
            });
        } else {
          document.getElementById('view_client_entered_by').textContent = 'Unknown';
        }
        
        document.getElementById('view_client_entered_at').textContent = data.details_entered_at || 'N/A';
        
        // Show modal with z-index handling
        const viewModal = new bootstrap.Modal(document.getElementById('viewClientDetailsModal'));
        const viewModalElement = document.getElementById('viewClientDetailsModal');
        
        viewModalElement.addEventListener('shown.bs.modal', function () {
          const modalBackdrop = document.querySelector('.modal-backdrop:last-of-type');
          if (modalBackdrop) {
            modalBackdrop.style.zIndex = '1059';
          }
          viewModalElement.style.zIndex = '1060';
        });
        
        viewModal.show();
      }
    })
    .catch(error => {
      console.error('Error loading client details:', error);
      alert('Error loading client details. Please try again.');
    });
}

// Function to load document history
function loadDocumentHistory(documentId) {
  const historyBody = document.getElementById('document-history-body');
  historyBody.innerHTML = '<tr><td colspan="5" class="text-center py-3"><i class="fa-solid fa-spinner fa-spin"></i> Loading history...</td></tr>';
  
  fetch(`?action=view&type=document_history&id=${documentId}`)
    .then(response => response.json())
    .then(history => {
      if (history && history.length > 0) {
        historyBody.innerHTML = '';
        history.forEach(item => {
          // Determine action badge color
          let actionBadgeClass = 'badge history-action-badge bg-secondary';
          if (item.action && item.action.toLowerCase().includes('submit')) actionBadgeClass = 'badge history-action-badge' + (item.action.toLowerCase().includes('final') ? ' bg-success' : ' bg-dark');
          else if (item.action && item.action.toLowerCase().includes('approved')) actionBadgeClass = 'badge history-action-badge bg-success';
          else if (item.action && item.action.toLowerCase().includes('review')) actionBadgeClass = 'badge history-action-badge bg-info';
          else if (item.action && item.action.toLowerCase().includes('reject')) actionBadgeClass = 'badge history-action-badge bg-danger';
          else if (item.action && item.action.toLowerCase().includes('return')) actionBadgeClass = 'badge history-action-badge bg-warning text-dark';
          
          // Determine status badge colors based on status
          const getStatusBadgeClass = (status) => {
            if (!status) return 'bg-secondary';
            const s = status.toLowerCase();
            if (s.includes('approved') || s.includes('submit')) return 'bg-success';
            if (s.includes('review')) return 'bg-info';
            if (s.includes('reject')) return 'bg-danger';
            if (s.includes('pending')) return 'bg-warning text-dark';
            return 'bg-secondary';
          };
          
          // Status badges with better styling
          const oldStatusBadge = item.old_status ? `<span class="badge history-status-badge ${getStatusBadgeClass(item.old_status)}">${item.old_status}</span>` : '<span class="text-muted" style="font-size: 0.85rem;">—</span>';
          const newStatusBadge = item.new_status ? `<span class="badge history-status-badge ${getStatusBadgeClass(item.new_status)}">${item.new_status}</span>` : '<span class="text-muted" style="font-size: 0.85rem;">—</span>';
          
          // Parse datetime
          const datetime = item.created_at || 'N/A';
          const [datePart, timePart] = datetime.split(' ');
          
          const row = document.createElement('tr');
          row.innerHTML = `
            <td>
              <div class="history-datetime">
                <span class="history-date"><i class="fa-solid fa-calendar-day" style="margin-right: 6px; font-size: 0.85rem;"></i>${datePart || 'N/A'}</span>
                <span class="history-time"><i class="fa-solid fa-clock" style="margin-right: 6px; font-size: 0.75rem;"></i>${timePart || ''}</span>
              </div>
            </td>
            <td><span class="${actionBadgeClass}">${item.action || 'N/A'}</span></td>
            <td>
              <div class="history-status-change">
                ${oldStatusBadge}
                <i class="fa-solid fa-arrow-right history-status-arrow"></i>
                ${newStatusBadge}
              </div>
            </td>
            <td>
              <div class="history-performer">
                <i class="fa-solid fa-user-circle"></i>
                <span>${item.performer_name || 'System'}</span>
              </div>
            </td>
            <td><div class="history-comments">${item.comments || '—'}</div></td>
          `;
          historyBody.appendChild(row);
        });
      } else {
        historyBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-5" style="font-size: 1rem;"><i class="fa-solid fa-inbox" style="font-size: 2rem; display: block; margin-bottom: 10px; opacity: 0.5;"></i> No history available</td></tr>';
      }
    })
    .catch(error => {
      console.error('Error loading history:', error);
      historyBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-5" style="font-size: 1rem;"><i class="fa-solid fa-exclamation-triangle" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i> Error loading history</td></tr>';
    });
}

// Function to generate role-based action buttons
function generateActionButtons(doc) {
  const actionContainer = document.getElementById('document-action-buttons');
  const userRole = '<?php echo $_SESSION['role'] ?? 'employee'; ?>';
  const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
  const userId = <?php echo $_SESSION['user_id']; ?>;
  const status = doc.status;
  
  // Clear existing buttons except Close button
  actionContainer.innerHTML = '';
  
  let buttons = [];
  
  // Submit - Final status, completely locked
  if (status === 'Submit') {
    if (isAdmin) {
      actionContainer.innerHTML = `
        <div class="alert alert-dark mb-0 me-auto">
          <i class="fa-solid fa-check-double"></i> Document has been submitted to client. Process complete.
        </div>
        <button type="button" class="btn btn-danger" onclick="deleteDocument(${doc.document_id})">
          <i class="fa-solid fa-trash"></i> Delete Document
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      `;
    } else {
      actionContainer.innerHTML = `
        <div class="alert alert-dark mb-0 me-auto">
          <i class="fa-solid fa-check-double"></i> Document has been submitted to client. Process complete.
        </div>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      `;
    }
    return;
  }
  
  // Final Approved - Creator can submit to client
  if (status === 'Final Approved') {
    // Check if current user is the document creator
    if (doc.created_by == userId) {
      actionContainer.innerHTML = `
        <div class="alert alert-success mb-0 me-auto">
          <i class="fa-solid fa-check-circle"></i> Document has been approved. You can now submit it to the client.
        </div>
        <button type="button" class="btn btn-primary" onclick="performDocumentAction(${doc.document_id}, 'Submit', 'Submitted to client')">
          <i class="fa-solid fa-paper-plane"></i> Submit to Client
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      `;
    } else if (isAdmin) {
      actionContainer.innerHTML = `
        <div class="alert alert-success mb-0 me-auto">
          <i class="fa-solid fa-lock"></i> Document is finalized. Waiting for creator to submit to client.
        </div>
        <button type="button" class="btn btn-danger" onclick="deleteDocument(${doc.document_id})">
          <i class="fa-solid fa-trash"></i> Delete Document
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      `;
    } else {
      actionContainer.innerHTML = `
        <div class="alert alert-success mb-0 me-auto">
          <i class="fa-solid fa-lock"></i> Document is finalized. Waiting for creator to submit to client.
        </div>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      `;
    }
    return;
  }
  
  // Rejected - View only (except delete for admin)
  if (status === 'Rejected') {
    if (isAdmin) {
      actionContainer.innerHTML = `
        <div class="alert alert-danger mb-0 me-auto">
          <i class="fa-solid fa-ban"></i> Document has been rejected.
        </div>
        <button type="button" class="btn btn-danger" onclick="deleteDocument(${doc.document_id})">
          <i class="fa-solid fa-trash"></i> Delete Document
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      `;
    } else {
      actionContainer.innerHTML = `
        <div class="alert alert-danger mb-0 me-auto">
          <i class="fa-solid fa-ban"></i> Document has been rejected.
        </div>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      `;
    }
    return;
  }
  
  // Check if user can edit the document
  let canEdit = false;
  if (isAdmin) {
    canEdit = true; // Admin can always edit
  } else if (userRole === 'employee' && doc.created_by == userId) {
    // Employee can edit their own documents if Pending or Returned
    if (status === 'Pending' || status === 'Returned') {
      canEdit = true;
    }
  }
  
  // Add Edit button if user can edit
  if (canEdit && status !== 'Final Approved' && status !== 'Rejected' && status !== 'Submit') {
    buttons.push(`
      <button type="button" class="btn btn-warning" onclick="editDocument(${doc.document_id})">
        <i class="fa-solid fa-edit"></i> Edit Document
      </button>
    `);
  }
  
  // Employee buttons
  if (userRole === 'employee') {
    // Can only return if document is still Pending and they are the creator
    if (status === 'Pending' && doc.created_by == userId) {
      buttons.push(`
        <button type="button" class="btn btn-secondary" onclick="promptCommentAndAction(${doc.document_id}, 'Returned')">
          <i class="fa-solid fa-undo"></i> Return/Cancel
        </button>
      `);
    } else if (status !== 'Pending' && status !== 'Returned' && !canEdit) {
      buttons.push(`
        <div class="alert alert-info mb-0 me-auto">
          <i class="fa-solid fa-info-circle"></i> Document is under review. You can only view the status.
        </div>
      `);
    }
  }
  
  // Accountant buttons (First reviewer: Pending → Reviewed)
  else if (userRole === 'accountant') {
    if (status === 'Pending') {
      buttons.push(`
        <button type="button" class="btn btn-success" onclick="performDocumentAction(${doc.document_id}, 'Reviewed', 'Approved by Accountant')">
          <i class="fa-solid fa-check-circle"></i> Approve
        </button>
        <button type="button" class="btn btn-danger" onclick="promptCommentAndAction(${doc.document_id}, 'Rejected')">
          <i class="fa-solid fa-times-circle"></i> Reject
        </button>
        <button type="button" class="btn btn-warning" onclick="promptCommentAndAction(${doc.document_id}, 'Returned')">
          <i class="fa-solid fa-undo"></i> Return
        </button>
      `);
    } else {
      buttons.push(`
        <div class="alert alert-secondary mb-0 me-auto">
          <i class="fa-solid fa-lock"></i> You can only act on Pending documents.
        </div>
      `);
    }
  }
  
  // Manager buttons (Second reviewer: Reviewed → Approved)
  else if (userRole === 'manager') {
    if (status === 'Reviewed') {
      buttons.push(`
        <button type="button" class="btn btn-success" onclick="performDocumentAction(${doc.document_id}, 'Approved', 'Approved by Manager')">
          <i class="fa-solid fa-check-circle"></i> Approve
        </button>
        <button type="button" class="btn btn-danger" onclick="promptCommentAndAction(${doc.document_id}, 'Rejected')">
          <i class="fa-solid fa-times-circle"></i> Reject
        </button>
        <button type="button" class="btn btn-warning" onclick="promptCommentAndAction(${doc.document_id}, 'Returned')">
          <i class="fa-solid fa-undo"></i> Return
        </button>
      `);
    } else {
      buttons.push(`
        <div class="alert alert-secondary mb-0 me-auto">
          <i class="fa-solid fa-lock"></i> You can only act on Reviewed documents (after Accountant approval).
        </div>
      `);
    }
  }
  
  // Auditor buttons (Final reviewer: Approved → Final Approved)
  else if (userRole === 'auditor') {
    if (status === 'Approved') {
      buttons.push(`
        <button type="button" class="btn btn-success" onclick="performDocumentAction(${doc.document_id}, 'Final Approved', 'Finalized by Auditor')">
          <i class="fa-solid fa-check-double"></i> Final Approve
        </button>
        <button type="button" class="btn btn-danger" onclick="promptCommentAndAction(${doc.document_id}, 'Rejected')">
          <i class="fa-solid fa-times-circle"></i> Reject
        </button>
        <button type="button" class="btn btn-warning" onclick="promptCommentAndAction(${doc.document_id}, 'Returned')">
          <i class="fa-solid fa-undo"></i> Return
        </button>
      `);
    } else {
      buttons.push(`
        <div class="alert alert-secondary mb-0 me-auto">
          <i class="fa-solid fa-lock"></i> You can only act on Approved documents (after Manager approval).
        </div>
      `);
    }
  }
  
  // Admin has all permissions including delete
  if (isAdmin) {
    // Add delete button for admin (can delete any document including finalized ones)
    buttons.push(`
      <button type="button" class="btn btn-danger" onclick="deleteDocument(${doc.document_id})">
        <i class="fa-solid fa-trash"></i> Delete Document
      </button>
    `);
    actionContainer.innerHTML = buttons.join('') + `
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    `;
  } else {
    actionContainer.innerHTML = buttons.join('') + `
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    `;
  }
}

// Function to perform document action
function performDocumentAction(documentId, newStatus, comments) {
  if (!comments) comments = '';
  
  const formData = new FormData();
  formData.append('action', 'update_document_status');
  formData.append('document_id', documentId);
  formData.append('status', newStatus);
  formData.append('comments', comments);
  
  fetch('index.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('viewDocumentModal')).hide();
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while updating the document.');
  });
}

// Function to prompt for comments before action using custom modal
function promptCommentAndAction(documentId, newStatus) {
  const actionName = newStatus === 'Returned' ? 'returning' : 'rejecting';
  
  // Set modal title and message
  document.getElementById('commentModalTitle').innerHTML = `<i class="fa-solid fa-comment"></i> Enter Reason`;
  document.getElementById('commentModalMessage').textContent = `Please enter reason for ${actionName} this document:`;
  document.getElementById('commentTextarea').value = '';
  
  // Show modal with higher z-index
  const commentModalElement = document.getElementById('commentModal');
  const commentModal = new bootstrap.Modal(commentModalElement, {
    backdrop: 'static',
    keyboard: false
  });
  
  // Ensure modal appears on top
  commentModalElement.addEventListener('shown.bs.modal', function () {
    // Set z-index higher than document modal
    const modalBackdrop = document.querySelector('.modal-backdrop:last-of-type');
    if (modalBackdrop) {
      modalBackdrop.style.zIndex = '1059';
    }
    commentModalElement.style.zIndex = '1060';
  });
  
  commentModal.show();
  
  // Handle submit button click
  document.getElementById('commentSubmitBtn').onclick = function() {
    const comments = document.getElementById('commentTextarea').value.trim();
    
    if (comments === '') {
      alert(`Please provide a reason for ${actionName} the document.`);
      return;
    }
    
    // Close modal and perform action
    commentModal.hide();
    performDocumentAction(documentId, newStatus, comments);
  };
}

// Function to edit document
function editDocument(documentId) {
  // Close the view document modal first
  const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewDocumentModal'));
  if (viewModal) {
    viewModal.hide();
  }
  
  // Load document data and show edit modal
  fetch(`?action=view&type=document&id=${documentId}`)
    .then(response => response.json())
    .then(doc => {
      if (doc && doc.document_id) {
        document.getElementById('edit_doc_id').value = doc.document_id;
        document.getElementById('edit_doc_company').value = doc.company_id;
        document.getElementById('edit_doc_title').value = doc.document_title;
        document.getElementById('edit_doc_type').value = doc.document_type;
        document.getElementById('edit_doc_description').value = doc.description || '';
        document.getElementById('edit_doc_date').value = doc.date_of_collect;
        document.getElementById('edit_doc_location').value = doc.location || '';
        
        new bootstrap.Modal(document.getElementById('editDocumentModal')).show();
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error loading document data!');
    });
}

// Function to delete document (Admin only)
function deleteDocument(documentId) {
  if (confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
    const formData = new FormData();
    formData.append('action', 'delete_document');
    formData.append('document_id', documentId);
    
    fetch('index.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showToast(data.message);
        // Close the modal
        const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewDocumentModal'));
        if (viewModal) {
          viewModal.hide();
        }
        // Reload page to refresh document list
        setTimeout(() => location.reload(), 500);
      } else {
        alert('Error: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('An error occurred while deleting the document.');
    });
  }
}

// Document search and filter functionality
function filterDocuments() {
  const searchTerm = document.getElementById('searchDocuments').value.toLowerCase();
  const dateFrom = document.getElementById('filterDateFrom').value;
  const dateTo = document.getElementById('filterDateTo').value;
  const rows = document.querySelectorAll('#documentsTable tbody tr');
  
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    const dateCell = row.cells[5]; // Collect Date column (index 5)
    const collectDate = dateCell ? dateCell.textContent.trim() : '';
    
    // Check search term
    const matchesSearch = text.includes(searchTerm);
    
    // Check date range
    let matchesDate = true;
    if (collectDate && (dateFrom || dateTo)) {
      const rowDate = new Date(collectDate);
      if (dateFrom) {
        const fromDate = new Date(dateFrom);
        if (rowDate < fromDate) matchesDate = false;
      }
      if (dateTo) {
        const toDate = new Date(dateTo);
        if (rowDate > toDate) matchesDate = false;
      }
    }
    
    // Show row only if it matches all filters
    row.style.display = (matchesSearch && matchesDate) ? '' : 'none';
  });
}

// Attach event listeners
const searchDocuments = document.getElementById('searchDocuments');
const filterDateFrom = document.getElementById('filterDateFrom');
const filterDateTo = document.getElementById('filterDateTo');
const clearDocumentFilters = document.getElementById('clearDocumentFilters');

if (searchDocuments) {
  searchDocuments.addEventListener('input', filterDocuments);
}

if (filterDateFrom) {
  filterDateFrom.addEventListener('change', filterDocuments);
}

if (filterDateTo) {
  filterDateTo.addEventListener('change', filterDocuments);
}

if (clearDocumentFilters) {
  clearDocumentFilters.addEventListener('click', function() {
    document.getElementById('searchDocuments').value = '';
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';
    filterDocuments();
  });
}

// Helper function to update company details display
function updateCompanyDetailsDisplay(company) {
    document.getElementById('detail-name').textContent = company.company_name || 'N/A';
    document.getElementById('detail-ssm').textContent = company.ssm_no || 'N/A';
    document.getElementById('detail-type').textContent = company.company_type || 'N/A';
    document.getElementById('detail-subtype').textContent = company.sub_type || 'N/A';
    document.getElementById('detail-incdate').textContent = company.incorporation_date || 'N/A';
    document.getElementById('detail-fye').textContent = company.financial_year_end || 'N/A';
    document.getElementById('detail-subsequent-year-end').textContent = company.subsequent_year_end || 'N/A';
    // Display MSIC codes - Parse from combined field (comma-separated) and display codes with individual descriptions
    let hasAnyMsic = false;
    const msicCodes = company.msic_code ? company.msic_code.split(',').map(c => c.trim()) : [];
    
    // Function to display MSIC code with its individual nature of business
    const displayMSIC = async (code, index) => {
      if (code) {
        document.getElementById(`detail-msic-${index}`).style.display = 'block';
        document.getElementById(`detail-msic-code-${index}`).textContent = code;
        
        // Fetch description for this specific MSIC code
        try {
          const response = await fetch(`?action=search_msic&query=${encodeURIComponent(code)}`);
          const results = await response.json();
          const match = results.find(r => r.code === code);
          if (match) {
            document.getElementById(`detail-msic-nature-${index}`).textContent = match.description;
          } else {
            document.getElementById(`detail-msic-nature-${index}`).textContent = 'Description not found';
          }
        } catch (error) {
          document.getElementById(`detail-msic-nature-${index}`).textContent = 'Unable to fetch description';
        }
        return true;
      } else {
        document.getElementById(`detail-msic-${index}`).style.display = 'none';
        return false;
      }
    };
    
    // Display all MSIC codes with individual nature of business
    Promise.all([
      displayMSIC(msicCodes[0], 1),
      displayMSIC(msicCodes[1], 2),
      displayMSIC(msicCodes[2], 3)
    ]).then(results => {
      hasAnyMsic = results.some(r => r);
      document.getElementById('detail-msic-none').style.display = hasAnyMsic ? 'none' : 'block';
    });
    
    document.getElementById('detail-description').textContent = company.description || 'N/A';
    document.getElementById('detail-description').classList.add('wrap-long');
    document.getElementById('detail-address').textContent = company.address || 'N/A';
  document.getElementById('detail-address').classList.add('wrap-long');
  document.getElementById('detail-address').title = company.address || '';
  document.getElementById('detail-nature').title = company.nature_of_business || '';
    document.getElementById('detail-business-address').textContent = company.business_address || 'Same as Registered Address';
  document.getElementById('detail-business-address').classList.add('wrap-long');
  document.getElementById('detail-business-address').title = company.business_address || '';
    document.getElementById('detail-email').textContent = company.email || 'N/A';
    document.getElementById('detail-office').textContent = company.office_no || 'N/A';
    document.getElementById('detail-fax').textContent = company.fax_no || 'N/A';
    document.getElementById('detail-accname').textContent = company.accountant_name || 'N/A';
    document.getElementById('detail-accphone').textContent = company.accountant_phone || 'N/A';
    document.getElementById('detail-accemail').textContent = company.accountant_email || 'N/A';
    document.getElementById('detail-hrname').textContent = company.hr_name || 'N/A';
    document.getElementById('detail-hrphone').textContent = company.hr_phone || 'N/A';
    document.getElementById('detail-hremail').textContent = company.hr_email || 'N/A';
}

// Initialize the modal setup when the page loads
document.addEventListener('DOMContentLoaded', function() {
    // Any initialization code if needed
});

// View Member Modal & handler
function viewMember(memberId) {
  // First, get the member data
  let memberData;
  fetch(`?action=view&type=member&id=${memberId}`)
    .then(response => response.json())
    .then(member => {
      if (!member || Object.keys(member).length === 0) {
        alert('Member not found');
        return Promise.reject('Member not found');
      }
      memberData = member;
      // Now fetch all members of the same company to calculate total shares
      return fetch(`?action=view&type=members&company_id=${member.company_id}`);
    })
    .then(response => response.json())
    .then(allMembers => {
      const member = memberData;
      // Calculate total shares
      let totalShares = 0;
      if (Array.isArray(allMembers)) {
        allMembers.forEach(m => {
          totalShares += parseFloat(m.number_of_share) || 0;
        });
      }
      
      // Calculate this member's percentage
      const memberShares = parseFloat(member.number_of_share) || 0;
      const percentage = totalShares > 0 ? (memberShares / totalShares * 100) : 0;
      // Create or populate modal content
      let modal = document.getElementById('viewMemberModal');
      if (!modal) {
        const html = `
        <div class="modal fade" id="viewMemberModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content bg-dark text-light">
              <div class="modal-header" style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.15) 0%, rgba(13, 202, 240, 0.15) 100%); border-bottom: 2px solid #0dcaf0;">
                <h5 class="modal-title" style="font-weight: 600; color: #0dcaf0;"><i class="fa-solid fa-user me-2"></i> Member Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body" style="padding: 2rem; background-color: #1a1d29;">
                <!-- Basic Information Section -->
                <div style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.15) 0%, rgba(73, 80, 87, 0.15) 100%); border: 1.5px solid rgba(108, 117, 125, 0.3); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                  <div class="d-flex align-items-center mb-3" style="border-bottom: 2px solid rgba(108, 117, 125, 0.3); padding-bottom: 0.75rem;">
                    <i class="fa-solid fa-id-card me-2" style="color: #6c757d; font-size: 1.15rem;"></i>
                    <h6 class="mb-0 fw-bold" style="color: #e9ecef; font-size: 1.05rem;">Basic Information</h6>
                  </div>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Member Name</label>
                      <div id="view-member-name" style="color: #0dcaf0; font-size: 1.15rem; font-weight: 600;"></div>
                    </div>
                    <div class="col-md-6">
                      <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">ID Type</label>
                      <div id="view-member-idtype" style="color: #20c997; font-size: 1.05rem; font-weight: 500;"></div>
                    </div>
                    <div class="col-md-6">
                      <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Identification No</label>
                      <div id="view-member-idno" style="color: #ffc107; font-size: 1.1rem; font-weight: 600; font-family: 'Courier New', monospace;"></div>
                    </div>
                    <div class="col-md-6">
                      <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Nationality</label>
                      <div id="view-member-nation" style="color: #e9ecef; font-size: 1.05rem; font-weight: 500;"></div>
                    </div>
                    <div class="col-md-6">
                      <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Race</label>
                      <div id="view-member-race" style="color: #e9ecef; font-size: 1.05rem; font-weight: 500;"></div>
                    </div>
                    <div class="col-md-6">
                      <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Email</label>
                      <div id="view-member-email" style="color: #e9ecef; font-size: 1.05rem; font-weight: 500;"></div>
                    </div>
                  </div>
                </div>
                
                <!-- Share Information Section -->
                <div style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.15) 0%, rgba(73, 80, 87, 0.15) 100%); border: 1.5px solid rgba(108, 117, 125, 0.3); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                  <div class="d-flex align-items-center mb-3" style="border-bottom: 2px solid rgba(108, 117, 125, 0.3); padding-bottom: 0.75rem;">
                    <i class="fa-solid fa-chart-pie me-2" style="color: #6c757d; font-size: 1.15rem;"></i>
                    <h6 class="mb-0 fw-bold" style="color: #e9ecef; font-size: 1.05rem;">Share Details</h6>
                  </div>
                  <div class="row g-3">
                    <div class="col-md-3">
                      <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Class of Share</label>
                      <div id="view-member-class" style="color: #20c997; font-size: 1.1rem; font-weight: 600;"></div>
                    </div>
                    <div class="col-md-3">
                      <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">No of Shares</label>
                      <div id="view-member-noof" style="color: #ffc107; font-size: 1.2rem; font-weight: 700;"></div>
                    </div>
                    <div class="col-md-3">
                      <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Percentage of Stock</label>
                      <div id="view-member-percentage" style="color: #20c997; font-size: 1.2rem; font-weight: 700;"></div>
                    </div>
                    <div class="col-md-3">
                      <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Price per Share</label>
                      <div id="view-member-price" style="color: #0dcaf0; font-size: 1.2rem; font-weight: 700;"></div>
                    </div>
                  </div>
                </div>
                
                <!-- Address Section -->
                <div style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.15) 0%, rgba(73, 80, 87, 0.15) 100%); border: 1.5px solid rgba(108, 117, 125, 0.3); border-radius: 12px; padding: 1.5rem;">
                  <div class="d-flex align-items-center mb-3" style="border-bottom: 2px solid rgba(108, 117, 125, 0.3); padding-bottom: 0.75rem;">
                    <i class="fa-solid fa-location-dot me-2" style="color: #6c757d; font-size: 1.15rem;"></i>
                    <h6 class="mb-0 fw-bold" style="color: #e9ecef; font-size: 1.05rem;">Address</h6>
                  </div>
                  <div id="view-member-address" style="color: #ffffff; font-size: 1rem; line-height: 1.6; word-wrap: break-word;"></div>
                </div>
              </div>
              <div class="modal-footer" style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 202, 240, 0.1) 100%); border-top: 2px solid rgba(13, 202, 240, 0.3);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fa-solid fa-times me-1"></i> Close</button>
              </div>
            </div>
          </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
        modal = document.getElementById('viewMemberModal');
      }

      // Populate
      document.getElementById('view-member-name').textContent = member.member_name || 'N/A';
      document.getElementById('view-member-idtype').textContent = member.id_type || 'N/A';
      document.getElementById('view-member-idno').textContent = member.identification_no || 'N/A';
      document.getElementById('view-member-nation').textContent = member.nationality || 'N/A';
      document.getElementById('view-member-race').textContent = member.race || 'N/A';
      document.getElementById('view-member-email').textContent = member.email || 'N/A';
      document.getElementById('view-member-class').textContent = member.class_of_share || 'N/A';
      document.getElementById('view-member-noof').textContent = member.number_of_share || 'N/A';
      document.getElementById('view-member-percentage').textContent = percentage > 0 ? percentage.toFixed(2) + '%' : 'N/A';
      document.getElementById('view-member-price').textContent = member.price_per_share ? 'RM ' + member.price_per_share : 'N/A';
      document.getElementById('view-member-address').textContent = member.address || 'N/A';
  document.getElementById('view-member-address').classList.add('wrap-long');

      new bootstrap.Modal(modal).show();
    })
    .catch(err => { console.error('Error fetching member:', err); alert('Error loading member details'); });
}

// View Director Modal & handler
function viewDirector(directorId) {
  fetch(`?action=view&type=director&id=${directorId}`)
    .then(response => response.json())
    .then(director => {
      if (!director || Object.keys(director).length === 0) {
        alert('Director not found');
        return;
      }
      let modal = document.getElementById('viewDirectorModal');
      if (!modal) {
        const html = `
        <div class="modal fade" id="viewDirectorModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content bg-dark text-light">
              <div class="modal-header" style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.15) 0%, rgba(13, 202, 240, 0.15) 100%); border-bottom: 2px solid #0dcaf0;">
                <h5 class="modal-title" style="font-weight: 600; color: #0dcaf0;"><i class="fa-solid fa-user-tie me-2"></i> Director Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body" style="padding: 2rem; background-color: #1a1d29;">
                <!-- Basic Information Section -->
                <div style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.15) 0%, rgba(73, 80, 87, 0.15) 100%); border: 1.5px solid rgba(108, 117, 125, 0.3); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                  <div class="d-flex align-items-center mb-3" style="border-bottom: 2px solid rgba(108, 117, 125, 0.3); padding-bottom: 0.75rem;">
                    <i class="fa-solid fa-id-card me-2" style="color: #6c757d; font-size: 1.15rem;"></i>
                    <h6 class="mb-0 fw-bold" style="color: #e9ecef; font-size: 1.05rem;">Basic Information</h6>
                  </div>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Director Name</label>
                      <div id="view-director-name" style="color: #0dcaf0; font-size: 1.15rem; font-weight: 600;"></div>
                    </div>
                    <div class="col-md-6">
                      <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Identification No</label>
                      <div id="view-director-idno" style="color: #ffc107; font-size: 1.1rem; font-weight: 600; font-family: 'Courier New', monospace;"></div>
                    </div>
                    <div class="col-md-6">
                      <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Nationality</label>
                      <div id="view-director-nation" style="color: #e9ecef; font-size: 1.05rem; font-weight: 500;"></div>
                    </div>
                    <div class="col-md-6">
                      <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Date of Birth</label>
                      <div id="view-director-dob" style="color: #20c997; font-size: 1.1rem; font-weight: 600;"></div>
                    </div>
                    <div class="col-md-6">
                      <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Race</label>
                      <div id="view-director-race" style="color: #e9ecef; font-size: 1.05rem; font-weight: 500;"></div>
                    </div>
                  </div>
                </div>
                
                <!-- Contact Information Section -->
                <div style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.15) 0%, rgba(73, 80, 87, 0.15) 100%); border: 1.5px solid rgba(108, 117, 125, 0.3); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                  <div class="d-flex align-items-center mb-3" style="border-bottom: 2px solid rgba(108, 117, 125, 0.3); padding-bottom: 0.75rem;">
                    <i class="fa-solid fa-envelope me-2" style="color: #6c757d; font-size: 1.15rem;"></i>
                    <h6 class="mb-0 fw-bold" style="color: #e9ecef; font-size: 1.05rem;">Contact Information</h6>
                  </div>
                  <div class="mb-3">
                    <label style="color: #adb5bd; font-size: 0.85rem; font-weight: 500; display: block; margin-bottom: 0.4rem;">Email Address</label>
                    <div id="view-director-email" style="color: #0dcaf0; font-size: 1.05rem; font-weight: 500;"></div>
                  </div>
                </div>
                
                <!-- Address Section -->
                <div style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.15) 0%, rgba(73, 80, 87, 0.15) 100%); border: 1.5px solid rgba(108, 117, 125, 0.3); border-radius: 12px; padding: 1.5rem;">
                  <div class="d-flex align-items-center mb-3" style="border-bottom: 2px solid rgba(108, 117, 125, 0.3); padding-bottom: 0.75rem;">
                    <i class="fa-solid fa-location-dot me-2" style="color: #6c757d; font-size: 1.15rem;"></i>
                    <h6 class="mb-0 fw-bold" style="color: #e9ecef; font-size: 1.05rem;">Address</h6>
                  </div>
                  <div id="view-director-address" style="color: #ffffff; font-size: 1rem; line-height: 1.6; word-wrap: break-word;"></div>
                </div>
              </div>
              <div class="modal-footer" style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 202, 240, 0.1) 100%); border-top: 2px solid rgba(13, 202, 240, 0.3);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fa-solid fa-times me-1"></i> Close</button>
              </div>
            </div>
          </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
        modal = document.getElementById('viewDirectorModal');
      }

      document.getElementById('view-director-name').textContent = director.director_name || 'N/A';
      document.getElementById('view-director-idno').textContent = director.identification_no || 'N/A';
      document.getElementById('view-director-nation').textContent = director.nationality || 'N/A';
      document.getElementById('view-director-dob').textContent = director.date_of_birth || 'N/A';
      document.getElementById('view-director-race').textContent = director.race || 'N/A';
      document.getElementById('view-director-email').textContent = director.email || 'N/A';
      document.getElementById('view-director-address').textContent = director.address || 'N/A';
  document.getElementById('view-director-address').classList.add('wrap-long');

      new bootstrap.Modal(modal).show();
    })
    .catch(err => { console.error('Error fetching director:', err); alert('Error loading director details'); });
}

// --- Circular Profile Picture Upload Handler ---
function setupCircularProfileUpload(inputId, containerPreviewId) {
  const input = document.getElementById(inputId);
  const previewContainer = document.getElementById(containerPreviewId);
  
  if (!input || !previewContainer) return;

  input.addEventListener('change', (e) => {
    const file = input.files && input.files[0];
    if (!file) return;

    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = function(ev) {
        // Replace the placeholder with the actual image
        previewContainer.innerHTML = `<img src="${ev.target.result}" alt="Profile">`;
      };
      reader.readAsDataURL(file);
    }
  });
}

// --- File input custom UI wiring (for other file inputs like profile settings) ---
function setupCustomFileInput(buttonId, inputId, nameSpanId, previewImgId, sizeSpanId, clearBtnId) {
  const input = document.getElementById(inputId);
  if (!input) return;
  
  const nameSpan = document.getElementById(nameSpanId);
  const preview = document.getElementById(previewImgId);
  const sizeSpan = sizeSpanId ? document.getElementById(sizeSpanId) : null;
  const clearBtn = clearBtnId ? document.getElementById(clearBtnId) : null;

  const btn = document.getElementById(buttonId);
  if (btn) btn.addEventListener('click', (e) => { e.preventDefault(); input.click(); });

  function resetPicker() {
    if (input) input.value = '';
    if (nameSpan) nameSpan.textContent = 'No file chosen';
    if (preview) { preview.style.display = 'none'; preview.src = ''; }
    if (sizeSpan) sizeSpan.textContent = '';
  }

  if (clearBtn) clearBtn.addEventListener('click', (e) => { e.preventDefault(); resetPicker(); });

  input.addEventListener('change', (e) => {
    const file = input.files && input.files[0];
    if (!file) { resetPicker(); return; }

    nameSpan.textContent = file.name;
    if (sizeSpan) {
      // show size in KB or MB
      const sizeKB = file.size / 1024;
      sizeSpan.textContent = sizeKB < 1024 ? (Math.round(sizeKB) + ' KB') : (Math.round(sizeKB/1024) + ' MB');
    }

    if (file.type.startsWith('image/') && preview) {
      const reader = new FileReader();
      reader.onload = function(ev) {
        preview.src = ev.target.result;
        preview.style.display = 'inline-block';
      };
      reader.readAsDataURL(file);
    } else if (preview) {
      preview.style.display = 'none';
      preview.src = '';
    }
  });
}

// Initialize circular profile uploads for Add/Edit User/Admin modals
setupCircularProfileUpload('add_profile_picture', 'add_user_preview_container');
setupCircularProfileUpload('add_admin_profile_picture', 'add_admin_preview_container');
setupCircularProfileUpload('edit_profile_picture', 'edit_user_preview_container');
setupCircularProfileUpload('edit_admin_profile_picture', 'edit_admin_preview_container');

// Initialize custom inputs (for profile settings modal if exists)
setupCustomFileInput('profile_pick_btn', 'profile_picture', 'profile_picture_name', 'profile_picture_preview', 'profile_picture_size', 'profile_clear_btn');

// Reset file name & preview when modals open/close to avoid stale previews
['profileModal','addUserModal','addAdminModal','editUserModal','editAdminModal'].forEach(modalId => {
  const modalEl = document.getElementById(modalId);
  if (!modalEl) return;
  modalEl.addEventListener('show.bs.modal', () => {
    // find any file inputs inside and reset their displays
    modalEl.querySelectorAll('input[type="file"]').forEach(inp => { inp.value = ''; });
    modalEl.querySelectorAll('.file-input-filename').forEach(span => { span.textContent = 'No file chosen'; });
    modalEl.querySelectorAll('.file-preview-img').forEach(img => { img.style.display = 'none'; img.src = ''; });
    modalEl.querySelectorAll('.picker-filesize').forEach(s => { s.textContent = ''; });
    modalEl.querySelectorAll('.picker-clear').forEach(b => { b.disabled = false; });
  });
});

// Reset circular profile upload placeholders for Add User/Admin modals
document.getElementById('addUserModal')?.addEventListener('show.bs.modal', () => {
  const container = document.getElementById('add_user_preview_container');
  if (container) {
    container.innerHTML = '<div class="profile-upload-placeholder"><i class="fa-solid fa-user"></i></div>';
  }
});

document.getElementById('addAdminModal')?.addEventListener('show.bs.modal', () => {
  const container = document.getElementById('add_admin_preview_container');
  if (container) {
    container.innerHTML = '<div class="profile-upload-placeholder"><i class="fa-solid fa-user-shield"></i></div>';
  }
});

// Enhanced PDF Export Functions with Beautiful Data Formatting
function exportTableToPDF(tableId, filename) {
  const table = document.getElementById(tableId);
  if (!table) return alert('Table not found!');
  
  // Get table title based on tableId
  const tableTitle = getTableTitle(tableId);
  
  // Create PDF with proper formatting
  const pdf = new jspdf.jsPDF('l', 'pt', 'a4');
  const pageWidth = pdf.internal.pageSize.getWidth();
  const pageHeight = pdf.internal.pageSize.getHeight();
  
  // Add high-contrast header
  pdf.setFillColor(11, 94, 215); // #0b5ed7
  pdf.rect(0, 0, pageWidth, 70, 'F');
  
  // Add subtle shadow line
  pdf.setFillColor(8, 66, 152); // #084298
  pdf.rect(0, 70, pageWidth, 3, 'F');
  
  pdf.setTextColor(255, 255, 255);
  pdf.setFontSize(22);
  pdf.setFont('helvetica', 'bold');
  pdf.text('Accounting Dashboard', 40, 30);
  
  pdf.setFontSize(14);
  pdf.setFont('helvetica', 'normal');
  pdf.text(tableTitle, 40, 55);
  
  // Add generation date and time in a box
  pdf.setFontSize(9);
  pdf.setTextColor(235, 242, 255);
  const dateText = `Generated: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}`;
  pdf.text(dateText, pageWidth - 220, 30);
  
  // Add company/system info
  pdf.setFontSize(8);
  pdf.text('Professional Accounting System', pageWidth - 220, 50);
  
  // Extract table data properly
  const headers = [];
  const rows = [];
  const excludeIndices = [];
  
  // Extract headers and identify "Actions" column
  const headerCells = table.querySelectorAll('thead th');
  headerCells.forEach((th, index) => {
    const headerText = th.textContent.trim();
    // Exclude "Actions" column
    if (headerText.toLowerCase() === 'actions' || headerText.toLowerCase() === 'action') {
      excludeIndices.push(index);
    } else {
      headers.push(headerText);
    }
  });
  
  // Extract rows with proper data, excluding action columns
  const tableRows = table.querySelectorAll('tbody tr');
  const rowImages = []; // Store image data for each row
  
  tableRows.forEach(row => {
    const rowData = [];
    const imageData = [];
    const cells = row.querySelectorAll('td');
    
    cells.forEach((cell, index) => {
      // Skip action columns
      if (!excludeIndices.includes(index)) {
        // Check if cell contains a profile picture (image)
        const profileImg = cell.querySelector('.profile-picture, .profile-picture-sidebar, .profile-picture-md, .profile-picture-lg');
        
        // Check if cell contains a profile placeholder (div with initials)
        const profilePlaceholder = cell.querySelector('.profile-picture-placeholder, .profile-picture-placeholder-sidebar, .profile-picture-placeholder-md, .profile-picture-placeholder-lg');
        
        if (profileImg) {
          // Store image info
          imageData.push({
            colIndex: rowData.length,
            src: profileImg.src,
            type: 'image'
          });
          rowData.push('[IMG]'); // Placeholder for image
        } else if (profilePlaceholder) {
          // Store placeholder info (initials)
          imageData.push({
            colIndex: rowData.length,
            text: profilePlaceholder.textContent.trim(),
            type: 'placeholder'
          });
          rowData.push('[PLACEHOLDER]'); // Placeholder for initials
        } else {
          imageData.push(null);
          // Remove action buttons and get clean text
          const cellClone = cell.cloneNode(true);
          const buttons = cellClone.querySelectorAll('button, .btn, a.btn');
          buttons.forEach(btn => btn.remove());
          rowData.push(cellClone.textContent.trim());
        }
      }
    });
    
    if (rowData.length > 0 && rowData.some(cell => cell.length > 0)) {
      rows.push(rowData);
      rowImages.push(imageData);
    }
  });
  
  // Calculate column widths
  const numCols = headers.length;
  const colWidth = (pageWidth - 80) / numCols;
  
  // Add table headers with beautiful styling and borders
  let yPosition = 105;
  
  // Table header background (high contrast)
  pdf.setFillColor(11, 94, 215); // #0b5ed7
  pdf.rect(30, yPosition - 25, pageWidth - 60, 28, 'F');
  
  // Add header border
  pdf.setDrawColor(8, 66, 152); // #084298
  pdf.setLineWidth(1.5);
  pdf.rect(30, yPosition - 25, pageWidth - 60, 28, 'S');
  
  pdf.setTextColor(255, 255, 255);
  pdf.setFontSize(10.5);
  pdf.setFont('helvetica', 'bold');
  
  // Draw vertical lines between headers
  headers.forEach((header, index) => {
    const xPosition = 30 + (index * colWidth);
    // Use text wrapping for long headers
    const headerLines = pdf.splitTextToSize(header, colWidth - 10);
    pdf.text(headerLines[0], xPosition + 8, yPosition - 6);
    
    // Draw vertical separator lines
    if (index > 0) {
      pdf.setDrawColor(240, 240, 240);
      pdf.setLineWidth(0.5);
      pdf.line(xPosition, yPosition - 25, xPosition, yPosition + 3);
    }
  });
  
  yPosition += 3;
  
  // Add table rows with alternating colors and borders
  pdf.setTextColor(17, 17, 17); // #111
  pdf.setFont('helvetica', 'normal');
  pdf.setFontSize(9);
  
  rows.forEach((row, rowIndex) => {
    // Calculate dynamic row height based on content
    let maxLines = 1;
    const cellLines = [];
    
    row.forEach((cell, colIndex) => {
      const lines = pdf.splitTextToSize(cell, colWidth - 16);
      cellLines.push(lines);
      if (lines.length > maxLines) {
        maxLines = lines.length;
      }
    });
    
    const rowHeight = Math.max(24, maxLines * 12 + 8);
    
    // Check if we need a new page
    if (yPosition + rowHeight > pageHeight - 80) {
      pdf.addPage();
      yPosition = 40;
      
      // Re-add table header on new page
      pdf.setFillColor(11, 94, 215);
      pdf.rect(30, yPosition - 25, pageWidth - 60, 28, 'F');
      
      pdf.setDrawColor(8, 66, 152);
      pdf.setLineWidth(1.5);
      pdf.rect(30, yPosition - 25, pageWidth - 60, 28, 'S');
      
      pdf.setTextColor(255, 255, 255);
      pdf.setFontSize(10.5);
      pdf.setFont('helvetica', 'bold');
      
      headers.forEach((header, index) => {
        const xPosition = 30 + (index * colWidth);
        // Use text wrapping for long headers
        const headerLines = pdf.splitTextToSize(header, colWidth - 10);
        pdf.text(headerLines[0], xPosition + 8, yPosition - 6);
        
        if (index > 0) {
          pdf.setDrawColor(240, 240, 240);
          pdf.setLineWidth(0.5);
          pdf.line(xPosition, yPosition - 25, xPosition, yPosition + 3);
        }
      });
      
      yPosition += 3;
      pdf.setTextColor(17, 17, 17);
      pdf.setFont('helvetica', 'normal');
      pdf.setFontSize(9);
    }
    
    // Alternate row colors for better readability
    if (rowIndex % 2 === 0) {
      pdf.setFillColor(247, 249, 252); // #f7f9fc
    } else {
      pdf.setFillColor(255, 255, 255);
    }
    pdf.rect(30, yPosition, pageWidth - 60, rowHeight, 'F');
    
    // Add row borders
    pdf.setDrawColor(204, 204, 204); // #ccc
    pdf.setLineWidth(0.5);
    pdf.rect(30, yPosition, pageWidth - 60, rowHeight, 'S');
    
    // Add row data with proper text wrapping and vertical separators
    row.forEach((cell, colIndex) => {
      const xPosition = 30 + (colIndex * colWidth);
      
      // Check if this cell has an image or placeholder
      const imgInfo = rowImages[rowIndex].find(img => img && img.colIndex === colIndex);
      
      if (imgInfo && imgInfo.type === 'image' && cell === '[IMG]') {
        // Draw profile picture
        try {
          // Detect image format from extension
          const imgFormat = imgInfo.src.toLowerCase().includes('.png') ? 'PNG' : 
                           imgInfo.src.toLowerCase().includes('.jpg') || imgInfo.src.toLowerCase().includes('.jpeg') ? 'JPEG' : 'PNG';
          
          // Add image to PDF (20x20 size, centered in cell)
          const imgSize = 20;
          const imgX = xPosition + (colWidth / 2) - (imgSize / 2);
          const imgY = yPosition + (rowHeight / 2) - (imgSize / 2);
          pdf.addImage(imgInfo.src, imgFormat, imgX, imgY, imgSize, imgSize);
        } catch (e) {
          // If image fails, draw a simple placeholder circle
          const circleSize = 20;
          const circleX = xPosition + (colWidth / 2);
          const circleY = yPosition + (rowHeight / 2);
          pdf.setFillColor(200, 200, 200);
          pdf.circle(circleX, circleY, circleSize / 2, 'F');
        }
      } else if (imgInfo && imgInfo.type === 'placeholder' && cell === '[PLACEHOLDER]') {
        // Draw profile placeholder with initials
        const circleSize = 20;
        const circleX = xPosition + (colWidth / 2);
        const circleY = yPosition + (rowHeight / 2);
        
        // Draw circle background
        pdf.setFillColor(11, 94, 215);
        pdf.circle(circleX, circleY, circleSize / 2, 'F');
        
        // Add initials text
        pdf.setTextColor(255, 255, 255);
        pdf.setFontSize(8);
        pdf.setFont('helvetica', 'bold');
        const textWidth = pdf.getTextWidth(imgInfo.text);
        pdf.text(imgInfo.text, circleX - (textWidth / 2), circleY + 3);
        
        // Reset text color
        pdf.setTextColor(0, 0, 0);
        pdf.setFontSize(9);
        pdf.setFont('helvetica', 'normal');
      } else {
        // Use wrapped text from earlier calculation - shows all content
        pdf.text(cellLines[colIndex], xPosition + 8, yPosition + 12);
      }
      
      // Draw vertical separator lines
      if (colIndex > 0) {
        pdf.setDrawColor(204, 204, 204);
        pdf.setLineWidth(0.3);
        pdf.line(xPosition, yPosition, xPosition, yPosition + rowHeight);
      }
    });
    
    yPosition += rowHeight;
  });
  
  // Add bottom border to table
  pdf.setDrawColor(8, 66, 152);
  pdf.setLineWidth(1.5);
  pdf.line(30, yPosition, pageWidth - 30, yPosition);
  
  // Add beautiful footer with border
  const totalPages = pdf.internal.getNumberOfPages();
  for (let i = 1; i <= totalPages; i++) {
    pdf.setPage(i);
    
    // Footer separator line
    pdf.setDrawColor(200, 200, 200);
    pdf.setLineWidth(0.5);
    pdf.line(30, pageHeight - 35, pageWidth - 30, pageHeight - 35);
    
    // Footer text
    pdf.setFontSize(9);
    pdf.setTextColor(60, 60, 60);
    pdf.setFont('helvetica', 'normal');
    pdf.text(`Accounting Dashboard © ${new Date().getFullYear()}`, 40, pageHeight - 20);
    
    pdf.setFont('helvetica', 'bold');
    pdf.text(`Page ${i} of ${totalPages}`, pageWidth - 80, pageHeight - 20);
    
    // Add confidential watermark
    pdf.setFontSize(8);
    pdf.setFont('helvetica', 'italic');
    pdf.setTextColor(160, 160, 160);
    pdf.text('Confidential Document', pageWidth / 2 - 40, pageHeight - 20);
  }
  
  pdf.save(filename + '.pdf');
}

function printTable(tableId) {
  const table = document.getElementById(tableId);
  if (!table) return alert('Table not found!');
  
  const tableTitle = getTableTitle(tableId);
  
  // Clone the table and remove action columns
  const tableClone = table.cloneNode(true);
  
  // Find and remove "Actions" column header
  const headerCells = tableClone.querySelectorAll('thead th');
  let actionColumnIndex = -1;
  headerCells.forEach((th, index) => {
    const headerText = th.textContent.trim();
    if (headerText.toLowerCase() === 'actions' || headerText.toLowerCase() === 'action') {
      actionColumnIndex = index;
      th.remove();
    }
  });
  
  // Remove action column cells from all rows
  if (actionColumnIndex !== -1) {
    const rows = tableClone.querySelectorAll('tbody tr');
    rows.forEach(row => {
      const cells = row.querySelectorAll('td');
      if (cells[actionColumnIndex]) {
        cells[actionColumnIndex].remove();
      }
    });
  }
  
  // Remove any remaining buttons
  const buttons = tableClone.querySelectorAll('button, .btn, a.btn');
  buttons.forEach(btn => btn.remove());
  
  const win = window.open('', '', 'width=1200,height=800');
  
  win.document.write(`
    <html>
      <head>
        <title>Print ${tableTitle}</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
        <style>
          body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: white;
          }
          .print-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #0072ff;
            padding-bottom: 15px;
          }
          .print-header h1 {
            color: #0072ff;
            margin: 0;
            font-size: 28px;
            font-weight: bold;
          }
          .print-header p {
            color: #666;
            margin: 8px 0 0 0;
            font-size: 13px;
          }
          table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 11px;
          }
          th {
            background-color: #0072ff;
            color: white;
            padding: 14px 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #0072ff;
            white-space: normal;
            word-wrap: break-word;
          }
          td {
            padding: 12px 10px;
            border: 1px solid #ddd;
            vertical-align: top;
            white-space: normal;
            word-wrap: break-word;
            max-width: 300px;
            overflow-wrap: break-word;
          }
          tbody tr:nth-child(even) {
            background-color: #f8f9fa;
          }
          tbody tr:nth-child(odd) {
            background-color: #ffffff;
          }
          tbody tr:hover {
            background-color: #e3f2fd;
          }
          .print-footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
            color: #666;
            font-size: 11px;
          }
          @media print {
            body { margin: 15px; }
            .no-print { display: none; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
            .profile-picture {
              width: 30px !important;
              height: 30px !important;
              max-width: 30px !important;
              max-height: 30px !important;
            }
            .profile-picture-placeholder {
              width: 30px !important;
              height: 30px !important;
              max-width: 30px !important;
              max-height: 30px !important;
              font-size: 11px !important;
              line-height: 30px !important;
            }
          }
        </style>
      </head>
      <body>
        <div class="print-header">
          <h1>${tableTitle}</h1>
          <p>Generated on ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}</p>
          <p style="font-size: 10px; color: #999; margin-top: 5px;">Accounting Dashboard © ${new Date().getFullYear()} - Confidential Document</p>
        </div>
        ${tableClone.outerHTML}
        <div class="print-footer">
          <strong>Accounting Dashboard</strong> - Professional Accounting System © ${new Date().getFullYear()}
        </div>
      </body>
    </html>
  `);
  
  win.document.close();
  win.focus();
  setTimeout(() => {
    win.print();
  }, 250);
}

function exportModalToPDF(contentId, filename) {
  const content = document.getElementById(contentId);
  if (!content) return alert('Content not found!');
  
  // Create PDF with proper formatting
  const pdf = new jspdf.jsPDF('p', 'pt', 'a4');
  const pageWidth = pdf.internal.pageSize.getWidth();
  const pageHeight = pdf.internal.pageSize.getHeight();
  
  // Add beautiful header with gradient effect
  pdf.setFillColor(0, 114, 255);
  pdf.rect(0, 0, pageWidth, 70, 'F');
  
  // Add shadow line
  pdf.setFillColor(0, 90, 200);
  pdf.rect(0, 70, pageWidth, 3, 'F');
  
  pdf.setTextColor(255, 255, 255);
  pdf.setFontSize(22);
  pdf.setFont('helvetica', 'bold');
  pdf.text('Accounting Dashboard', 30, 30);
  
  pdf.setFontSize(14);
  pdf.setFont('helvetica', 'normal');
  pdf.text('Company Details Report', 30, 55);
  
  // Add generation date in a styled box
  pdf.setFontSize(9);
  pdf.setTextColor(240, 240, 240);
  const dateText = `Generated: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}`;
  pdf.text(dateText, pageWidth - 200, 30);
  
  pdf.setFontSize(8);
  pdf.text('Confidential Report', pageWidth - 200, 50);
  
  // Extract and format company information
  let yPosition = 100;
  pdf.setTextColor(0, 0, 0);
  
  // Company basic info section with styled box
  const companyName = content.querySelector('#detail-name')?.textContent || 'N/A';
  const companyRegNo = content.querySelector('#detail-ssm')?.textContent || 'N/A';
  const companyType = content.querySelector('#detail-type')?.textContent || 'N/A';
  const companySubType = content.querySelector('#detail-subtype')?.textContent || 'N/A';
  const companyIncDate = content.querySelector('#detail-incdate')?.textContent || 'N/A';
  const companyFinancialYear = content.querySelector('#detail-fye')?.textContent || 'N/A';
  const companySubsequentYear = content.querySelector('#detail-subsequent-year-end')?.textContent || 'N/A';
  
  // Section header with background
  pdf.setFillColor(240, 245, 255);
  pdf.rect(30, yPosition - 15, pageWidth - 60, 25, 'F');
  pdf.setDrawColor(0, 114, 255);
  pdf.setLineWidth(1);
  pdf.rect(30, yPosition - 15, pageWidth - 60, 25, 'S');
  
  pdf.setFontSize(13);
  pdf.setFont('helvetica', 'bold');
  pdf.setTextColor(0, 114, 255);
  pdf.text('Company Information', 40, yPosition);
  yPosition += 30;
  
  // Info box with border
  const infoBoxHeight = 110;
  pdf.setFillColor(255, 255, 255);
  pdf.rect(30, yPosition - 10, pageWidth - 60, infoBoxHeight, 'F');
  pdf.setDrawColor(220, 220, 220);
  pdf.setLineWidth(0.5);
  pdf.rect(30, yPosition - 10, pageWidth - 60, infoBoxHeight, 'S');
  
  pdf.setFont('helvetica', 'normal');
  pdf.setFontSize(10);
  pdf.setTextColor(60, 60, 60);
  
  const leftCol = 45;
  const rightCol = pageWidth / 2 + 20;
  
  pdf.setFont('helvetica', 'bold');
  pdf.text('Company Name:', leftCol, yPosition);
  pdf.setFont('helvetica', 'normal');
  pdf.text(companyName, leftCol + 110, yPosition);
  yPosition += 18;
  
  pdf.setFont('helvetica', 'bold');
  pdf.text('SSM Number:', leftCol, yPosition);
  pdf.setFont('helvetica', 'normal');
  pdf.text(companyRegNo, leftCol + 110, yPosition);
  yPosition += 18;
  
  pdf.setFont('helvetica', 'bold');
  pdf.text('Company Type:', leftCol, yPosition);
  pdf.setFont('helvetica', 'normal');
  pdf.text(companyType, leftCol + 110, yPosition);
  yPosition += 18;
  
  pdf.setFont('helvetica', 'bold');
  pdf.text('Sub Type:', leftCol, yPosition);
  pdf.setFont('helvetica', 'normal');
  pdf.text(companySubType, leftCol + 110, yPosition);
  yPosition += 18;
  
  pdf.setFont('helvetica', 'bold');
  pdf.text('Incorporation Date:', leftCol, yPosition);
  pdf.setFont('helvetica', 'normal');
  pdf.text(companyIncDate, leftCol + 110, yPosition);
  yPosition += 18;
  
  pdf.setFont('helvetica', 'bold');
  pdf.text('Financial Year End:', leftCol, yPosition);
  pdf.setFont('helvetica', 'normal');
  pdf.text(companyFinancialYear, leftCol + 110, yPosition);
  yPosition += 18;
  
  pdf.setFont('helvetica', 'bold');
  pdf.text('Subsequent Year End:', leftCol, yPosition);
  pdf.setFont('helvetica', 'normal');
  pdf.text(companySubsequentYear, leftCol + 110, yPosition);
  yPosition += 30;
  
  // Extract and format company address
  const companyAddress = content.querySelector('#detail-address')?.textContent || 'N/A';
  const companyBusinessAddress = content.querySelector('#detail-business-address')?.textContent || 'Same as Registered Address';
  const companyEmail = content.querySelector('#detail-email')?.textContent || 'N/A';
  const companyOffice = content.querySelector('#detail-office')?.textContent || 'N/A';
  const companyFax = content.querySelector('#detail-fax')?.textContent || 'N/A';
  
  // Section header with background
  pdf.setFillColor(240, 245, 255);
  pdf.rect(30, yPosition - 15, pageWidth - 60, 25, 'F');
  pdf.setDrawColor(0, 114, 255);
  pdf.setLineWidth(1);
  pdf.rect(30, yPosition - 15, pageWidth - 60, 25, 'S');
  
  pdf.setFontSize(13);
  pdf.setFont('helvetica', 'bold');
  pdf.setTextColor(0, 114, 255);
  pdf.text('Contact Information', 40, yPosition);
  yPosition += 30;
  
  // Info box with border
  const contactBoxHeight = 98;
  pdf.setFillColor(255, 255, 255);
  pdf.rect(30, yPosition - 10, pageWidth - 60, contactBoxHeight, 'F');
  pdf.setDrawColor(220, 220, 220);
  pdf.setLineWidth(0.5);
  pdf.rect(30, yPosition - 10, pageWidth - 60, contactBoxHeight, 'S');
  
  pdf.setFont('helvetica', 'normal');
  pdf.setFontSize(10);
  pdf.setTextColor(60, 60, 60);
  
  pdf.setFont('helvetica', 'bold');
  pdf.text('Registered Address:', leftCol, yPosition);
  pdf.setFont('helvetica', 'normal');
  const addressLines = pdf.splitTextToSize(companyAddress, pageWidth - 200);
  pdf.text(addressLines, leftCol + 110, yPosition);
  yPosition += 18;
  
  pdf.setFont('helvetica', 'bold');
  pdf.text('Business Address:', leftCol, yPosition);
  pdf.setFont('helvetica', 'normal');
  const businessAddressLines = pdf.splitTextToSize(companyBusinessAddress, pageWidth - 200);
  pdf.text(businessAddressLines, leftCol + 110, yPosition);
  yPosition += 18;
  
  pdf.setFont('helvetica', 'bold');
  pdf.text('Email:', leftCol, yPosition);
  pdf.setFont('helvetica', 'normal');
  pdf.text(companyEmail, leftCol + 110, yPosition);
  yPosition += 18;
  
  pdf.setFont('helvetica', 'bold');
  pdf.text('Office No:', leftCol, yPosition);
  pdf.setFont('helvetica', 'normal');
  pdf.text(companyOffice, leftCol + 110, yPosition);
  yPosition += 18;
  
  pdf.setFont('helvetica', 'bold');
  pdf.text('Fax No:', leftCol, yPosition);
  pdf.setFont('helvetica', 'normal');
  pdf.text(companyFax, leftCol + 110, yPosition);
  yPosition += 30;
  
  // Extract and format business information
  const companyNature = content.querySelector('#detail-nature')?.textContent || 'N/A';
  const companyMsic = content.querySelector('#detail-msic')?.textContent || 'N/A';
  
  // Section header with background
  pdf.setFillColor(240, 245, 255);
  pdf.rect(30, yPosition - 15, pageWidth - 60, 25, 'F');
  pdf.setDrawColor(0, 114, 255);
  pdf.setLineWidth(1);
  pdf.rect(30, yPosition - 15, pageWidth - 60, 25, 'S');
  
  pdf.setFontSize(13);
  pdf.setFont('helvetica', 'bold');
  pdf.setTextColor(0, 114, 255);
  pdf.text('Business Information', 40, yPosition);
  yPosition += 30;
  
  // Info box with border
  const businessBoxHeight = 50;
  pdf.setFillColor(255, 255, 255);
  pdf.rect(30, yPosition - 10, pageWidth - 60, businessBoxHeight, 'F');
  pdf.setDrawColor(220, 220, 220);
  pdf.setLineWidth(0.5);
  pdf.rect(30, yPosition - 10, pageWidth - 60, businessBoxHeight, 'S');
  
  pdf.setFont('helvetica', 'normal');
  pdf.setFontSize(10);
  pdf.setTextColor(60, 60, 60);
  
  pdf.setFont('helvetica', 'bold');
  pdf.text('Nature of Business:', leftCol, yPosition);
  pdf.setFont('helvetica', 'normal');
  const natureLines = pdf.splitTextToSize(companyNature, pageWidth - 220);
  pdf.text(natureLines, leftCol + 110, yPosition);
  yPosition += 18;
  
  pdf.setFont('helvetica', 'bold');
  pdf.text('MSIC Code:', leftCol, yPosition);
  pdf.setFont('helvetica', 'normal');
  pdf.text(companyMsic, leftCol + 110, yPosition);
  yPosition += 30;
  
  // Extract and format members table
  const membersTable = content.querySelector('#membersTable');
  if (membersTable) {
    // Section header with background
    pdf.setFillColor(240, 245, 255);
    pdf.rect(30, yPosition - 15, pageWidth - 60, 25, 'F');
    pdf.setDrawColor(0, 114, 255);
    pdf.setLineWidth(1);
    pdf.rect(30, yPosition - 15, pageWidth - 60, 25, 'S');
    
    pdf.setFontSize(13);
    pdf.setFont('helvetica', 'bold');
    pdf.setTextColor(0, 114, 255);
    pdf.text('Company Members', 40, yPosition);
    yPosition += 30;
    
    // Get members data
    const memberRows = membersTable.querySelectorAll('tbody tr');
    if (memberRows.length > 0) {
      // Add table headers with enhanced styling
      pdf.setFillColor(0, 114, 255);
      pdf.rect(30, yPosition - 18, pageWidth - 60, 22, 'F');
      pdf.setDrawColor(0, 90, 200);
      pdf.setLineWidth(1);
      pdf.rect(30, yPosition - 18, pageWidth - 60, 22, 'S');
      
      pdf.setTextColor(255, 255, 255);
      pdf.setFontSize(9);
      pdf.setFont('helvetica', 'bold');
      
      const headers = ['Name', 'ID Type', 'ID No', 'Nationality', 'Race', 'Share Class', 'No of Shares', 'Price/Share'];
      const colWidth = (pageWidth - 60) / headers.length;
      
      headers.forEach((header, index) => {
        const xPosition = 30 + (index * colWidth);
        pdf.text(header, xPosition + 5, yPosition - 5);
        
        // Vertical separators
        if (index > 0) {
          pdf.setDrawColor(255, 255, 255);
          pdf.setLineWidth(0.5);
          pdf.line(xPosition, yPosition - 18, xPosition, yPosition + 4);
        }
      });
      
      yPosition += 4;
      pdf.setTextColor(0, 0, 0);
      pdf.setFont('helvetica', 'normal');
      
      memberRows.forEach((row, rowIndex) => {
        const cells = row.querySelectorAll('td');
        if (cells.length > 0) {
          // Calculate dynamic row height based on content
          let maxLines = 1;
          const cellLines = [];
          
          cells.forEach((cell, colIndex) => {
            const cellText = cell.textContent.trim();
            const lines = pdf.splitTextToSize(cellText, colWidth - 10);
            cellLines.push(lines);
            if (lines.length > maxLines) {
              maxLines = lines.length;
            }
          });
          
          const memberRowHeight = Math.max(20, maxLines * 10 + 6);
          
          if (yPosition + memberRowHeight > pageHeight - 60) {
            pdf.addPage();
            yPosition = 40;
          }
          
          // Alternate row colors
          if (rowIndex % 2 === 0) {
            pdf.setFillColor(250, 252, 255);
          } else {
            pdf.setFillColor(255, 255, 255);
          }
          pdf.rect(30, yPosition, pageWidth - 60, memberRowHeight, 'F');
          
          // Row borders
          pdf.setDrawColor(220, 220, 220);
          pdf.setLineWidth(0.5);
          pdf.rect(30, yPosition, pageWidth - 60, memberRowHeight, 'S');
          
          cells.forEach((cell, colIndex) => {
            const xPosition = 30 + (colIndex * colWidth);
            // Use wrapped text - shows all content
            pdf.text(cellLines[colIndex], xPosition + 5, yPosition + 10);
            
            // Vertical separators
            if (colIndex > 0) {
              pdf.setDrawColor(230, 230, 230);
              pdf.setLineWidth(0.3);
              pdf.line(xPosition, yPosition, xPosition, yPosition + memberRowHeight);
            }
          });
          
          yPosition += memberRowHeight;
        }
      });
    } else {
      pdf.text('No members found', 20, yPosition);
      yPosition += 20;
    }
  }
  
  // Extract and format directors table
  const directorsTable = content.querySelector('#directorsTable');
  if (directorsTable) {
    yPosition += 20;
    
    // Section header with background
    pdf.setFillColor(240, 245, 255);
    pdf.rect(30, yPosition - 15, pageWidth - 60, 25, 'F');
    pdf.setDrawColor(0, 114, 255);
    pdf.setLineWidth(1);
    pdf.rect(30, yPosition - 15, pageWidth - 60, 25, 'S');
    
    pdf.setFontSize(13);
    pdf.setFont('helvetica', 'bold');
    pdf.setTextColor(0, 114, 255);
    pdf.text('Company Directors', 40, yPosition);
    yPosition += 30;
    
    // Get directors data
    const directorRows = directorsTable.querySelectorAll('tbody tr');
    if (directorRows.length > 0) {
      // Add table headers with enhanced styling
      pdf.setFillColor(0, 114, 255);
      pdf.rect(30, yPosition - 18, pageWidth - 60, 22, 'F');
      pdf.setDrawColor(0, 90, 200);
      pdf.setLineWidth(1);
      pdf.rect(30, yPosition - 18, pageWidth - 60, 22, 'S');
      
      pdf.setTextColor(255, 255, 255);
      pdf.setFontSize(9);
      pdf.setFont('helvetica', 'bold');
      
      const headers = ['Name', 'ID No', 'Nationality', 'Date of Birth', 'Race', 'Email', 'Address'];
      const colWidth = (pageWidth - 60) / headers.length;
      
      headers.forEach((header, index) => {
        const xPosition = 30 + (index * colWidth);
        pdf.text(header, xPosition + 5, yPosition - 5);
        
        // Vertical separators
        if (index > 0) {
          pdf.setDrawColor(255, 255, 255);
          pdf.setLineWidth(0.5);
          pdf.line(xPosition, yPosition - 18, xPosition, yPosition + 4);
        }
      });
      
      yPosition += 4;
      pdf.setTextColor(0, 0, 0);
      pdf.setFont('helvetica', 'normal');
      
      directorRows.forEach((row, rowIndex) => {
        const cells = row.querySelectorAll('td');
        if (cells.length > 0) {
          // Calculate dynamic row height based on content
          let maxLines = 1;
          const cellLines = [];
          
          cells.forEach((cell, colIndex) => {
            const cellText = cell.textContent.trim();
            const lines = pdf.splitTextToSize(cellText, colWidth - 10);
            cellLines.push(lines);
            if (lines.length > maxLines) {
              maxLines = lines.length;
            }
          });
          
          const directorRowHeight = Math.max(20, maxLines * 10 + 6);
          
          if (yPosition + directorRowHeight > pageHeight - 60) {
            pdf.addPage();
            yPosition = 40;
          }
          
          // Alternate row colors
          if (rowIndex % 2 === 0) {
            pdf.setFillColor(250, 252, 255);
          } else {
            pdf.setFillColor(255, 255, 255);
          }
          pdf.rect(30, yPosition, pageWidth - 60, directorRowHeight, 'F');
          
          // Row borders
          pdf.setDrawColor(220, 220, 220);
          pdf.setLineWidth(0.5);
          pdf.rect(30, yPosition, pageWidth - 60, directorRowHeight, 'S');
          
          cells.forEach((cell, colIndex) => {
            const xPosition = 30 + (colIndex * colWidth);
            // Use wrapped text - shows all content
            pdf.text(cellLines[colIndex], xPosition + 5, yPosition + 10);
            
            // Vertical separators
            if (colIndex > 0) {
              pdf.setDrawColor(230, 230, 230);
              pdf.setLineWidth(0.3);
              pdf.line(xPosition, yPosition, xPosition, yPosition + directorRowHeight);
            }
          });
          
          yPosition += directorRowHeight;
        }
      });
    } else {
      pdf.text('No directors found', 20, yPosition);
      yPosition += 20;
    }
  }
  
  // Add footer with border
  const totalPages = pdf.internal.getNumberOfPages();
  for (let i = 1; i <= totalPages; i++) {
    pdf.setPage(i);
    
    // Footer separator line
    pdf.setDrawColor(200, 200, 200);
    pdf.setLineWidth(0.5);
    pdf.line(30, pageHeight - 35, pageWidth - 30, pageHeight - 35);
    
    // Footer text
    pdf.setFontSize(9);
    pdf.setTextColor(80, 80, 80);
    pdf.setFont('helvetica', 'normal');
    pdf.text(`Accounting Dashboard © ${new Date().getFullYear()}`, 40, pageHeight - 20);
    
    pdf.setFont('helvetica', 'bold');
    pdf.text(`Page ${i} of ${totalPages}`, pageWidth - 80, pageHeight - 20);
    
    // Add confidential watermark
    pdf.setFontSize(8);
    pdf.setFont('helvetica', 'italic');
    pdf.setTextColor(150, 150, 150);
    pdf.text('Confidential Document', pageWidth / 2 - 40, pageHeight - 20);
  }
  
  pdf.save(filename + '.pdf');
}

function printModalContent(contentId) {
  const content = document.getElementById(contentId);
  if (!content) return alert('Content not found!');
  
  const win = window.open('', '', 'width=1000,height=700');
  
  // Remove action buttons and export buttons from the content
  const contentClone = content.cloneNode(true);
  const buttonsToRemove = contentClone.querySelectorAll('button, .btn');
  buttonsToRemove.forEach(btn => btn.remove());
  
  win.document.write(`
    <html>
      <head>
        <title>Print Company Details</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
        <style>
          body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: white;
            color: #333;
            line-height: 1.6;
          }
          .print-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #0072ff;
            padding-bottom: 15px;
          }
          .print-header h1 {
            color: #0072ff;
            margin: 0;
            font-size: 24px;
          }
          .print-header p {
            color: #666;
            margin: 5px 0 0 0;
            font-size: 14px;
          }
          h1, h2, h3, h4, h5, h6 {
            color: #0072ff;
            margin-top: 20px;
            margin-bottom: 10px;
          }
          table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
          }
          th {
            background-color: #0072ff;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
          }
          td {
            padding: 10px 12px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            vertical-align: top;
          }
          .print-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
            color: #666;
            font-size: 11px;
          }
          @media print {
            body { margin: 15px; }
            .no-print { display: none; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            .profile-picture {
              width: 30px !important;
              height: 30px !important;
              max-width: 30px !important;
              max-height: 30px !important;
            }
            .profile-picture-placeholder {
              width: 30px !important;
              height: 30px !important;
              max-width: 30px !important;
              max-height: 30px !important;
              font-size: 11px !important;
              line-height: 30px !important;
            }
          }
        </style>
      </head>
      <body>
        <div class="print-header">
          <h1>Company Details Report</h1>
          <p>Generated on ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}</p>
        </div>
        ${contentClone.outerHTML}
        <div class="print-footer">
          <strong>Accounting Dashboard</strong> - Professional Accounting System © ${new Date().getFullYear()} - Confidential Document
        </div>
      </body>
    </html>
  `);
  
  win.document.close();
  win.focus();
  setTimeout(() => {
    win.print();
  }, 250);
}

// Helper function to get table title based on table ID
function getTableTitle(tableId) {
  const titleMap = {
    'companiesTable': 'Companies List',
    'usersTable': 'Users List',
    'adminsTable': 'Administrators List',
    'membersTable': 'Members List',
    'directorsTable': 'Directors List',
    'queriesTable': 'Client Queries List'
  };
  return titleMap[tableId] || 'Data Report';
}

// ========== CLIENT QUERIES HANDLERS ==========

// Add Q&A Pair Function
let qaPairCount = 1;

function addQAPair() {
  qaPairCount++;
  const container = document.getElementById('qaPairsContainer');
  
  const newPair = document.createElement('div');
  newPair.className = 'qa-pair mb-4';
  newPair.style.cssText = 'background: linear-gradient(135deg, rgba(13, 110, 253, 0.08) 0%, rgba(10, 88, 202, 0.05) 100%); padding: 1.5rem; border-radius: 12px; border: 2px solid rgba(13, 110, 253, 0.3); box-shadow: 0 4px 12px rgba(13, 110, 253, 0.1); animation: slideIn 0.3s ease;';
  
  // Get the appropriate icon based on count
  const icons = ['fa-circle-2', 'fa-circle-3', 'fa-circle-4', 'fa-circle-5', 'fa-circle-6', 'fa-circle-7', 'fa-circle-8', 'fa-circle-9'];
  const iconClass = qaPairCount <= 9 ? icons[qaPairCount - 2] : 'fa-circle';
  
  newPair.innerHTML = `
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="mb-0" style="color: #0d6efd; font-weight: 700; font-size: 1.1rem;">
        <i class="fa-solid ${iconClass} me-2"></i> Q&A Pair #${qaPairCount}
      </h6>
      <button type="button" class="btn btn-sm btn-danger" onclick="removeQAPair(this)" 
        style="padding: 0.4rem 0.8rem; font-size: 0.85rem; border-radius: 6px; font-weight: 600; transition: all 0.3s ease;">
        <i class="fa-solid fa-trash-alt me-1"></i> Remove
      </button>
    </div>
    
    <div class="mb-3">
      <label class="form-label fw-semibold d-flex align-items-center" style="color: #e9ecef; font-size: 0.95rem;">
        <i class="fa-solid fa-circle-question me-2" style="color: #0d6efd;"></i> Question (Q)
      </label>
      <textarea class="form-control qa-question" name="questions[]" rows="3" placeholder="Enter question #${qaPairCount}..."
        style="background-color: #2b3035; border: 2px solid rgba(13, 110, 253, 0.4); color: #fff; padding: 0.75rem 1rem; border-radius: 8px; resize: vertical; font-size: 0.95rem; transition: all 0.3s ease;"></textarea>
    </div>
    
    <div>
      <label class="form-label fw-semibold d-flex align-items-center" style="color: #e9ecef; font-size: 0.95rem;">
        <i class="fa-solid fa-comment-dots me-2" style="color: #28a745;"></i> Answer (A) <span class="badge bg-secondary ms-2" style="font-size: 0.75rem;">Optional</span>
      </label>
      <textarea class="form-control qa-answer" name="answers[]" rows="3" placeholder="Enter answer (optional)..."
        style="background-color: #2b3035; border: 2px solid rgba(40, 167, 69, 0.4); color: #fff; padding: 0.75rem 1rem; border-radius: 8px; resize: vertical; font-size: 0.95rem; transition: all 0.3s ease;"></textarea>
    </div>
  `;
  
  container.appendChild(newPair);
  
  // Scroll to the new pair with smooth animation
  setTimeout(() => {
    newPair.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }, 100);
}

function removeQAPair(button) {
  const pair = button.closest('.qa-pair');
  pair.remove();
  
  // Renumber remaining pairs
  const pairs = document.querySelectorAll('#qaPairsContainer .qa-pair');
  pairs.forEach((pair, index) => {
    const label = pair.querySelector('strong');
    if (label) {
      label.textContent = `${index + 1}. Q&A Pair`;
    }
  });
  qaPairCount = pairs.length;
}

// Auto-check Management Letter (ML) checkbox when Middle or High risk is selected
document.getElementById('query_risk').addEventListener('change', function() {
  const riskLevel = this.value;
  const mlCheckbox = document.getElementById('query_ml');
  
  if (riskLevel === 'Middle' || riskLevel === 'High') {
    mlCheckbox.checked = true;
    // Add visual feedback
    mlCheckbox.parentElement.style.animation = 'pulse 0.5s ease';
    setTimeout(() => {
      mlCheckbox.parentElement.style.animation = '';
    }, 500);
  }
});

// Handle add query form submission
document.getElementById('addQueryForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  // Collect all Q&A pairs
  const questions = Array.from(document.querySelectorAll('.qa-question')).map(q => q.value.trim());
  const answers = Array.from(document.querySelectorAll('.qa-answer')).map(a => a.value.trim());
  
  // Validate at least one question
  if (questions.length === 0 || questions[0] === '') {
    alert('Please enter at least one question!');
    return;
  }
  
  const formData = new FormData(this);
  formData.append('action', 'add_query');
  
  // Add Q&A pairs as JSON
  formData.append('qa_pairs', JSON.stringify(questions.map((q, i) => ({
    question: q,
    answer: answers[i] || ''
  }))));
  
  fetch('query_handler.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('addQueryModal')).hide();
      this.reset();
      loadQueries();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while adding the query.');
  });
});

// Load queries into table
function loadQueries() {
  const formData = new FormData();
  formData.append('action', 'get_queries');
  
  fetch('query_handler.php', {
    method: 'POST',
    body: formData
  })
  .then(response => {
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    // Get the response as text first to check what we're receiving
    return response.text().then(text => {
      console.log('Raw response:', text);
      try {
        return JSON.parse(text);
      } catch (e) {
        console.error('JSON Parse Error:', e);
        console.error('Response was:', text);
        throw new Error('Server returned invalid JSON. Response: ' + text.substring(0, 200));
      }
    });
  })
  .then(data => {
    console.log('Query data received:', data);
    
    if (data.success) {
      const tbody = document.querySelector('#queriesTable tbody');
      tbody.innerHTML = '';
      
      if (data.queries.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="10" class="text-center text-muted py-4">
              <i class="fa-solid fa-inbox fa-3x mb-2" style="opacity: 0.3;"></i>
              <p>No queries found. Click "Add Query" to create one.</p>
            </td>
          </tr>
        `;
        return;
      }
      
      data.queries.forEach((query, index) => {
        const riskBadge = query.risk_level === 'High' ? 'bg-danger' : 
                         query.risk_level === 'Middle' ? 'bg-warning text-dark' : 'bg-success';
        const statusBadge = query.status === 'Pending' ? 'bg-warning text-dark' :
                           query.status === 'In Progress' ? 'bg-info' :
                           query.status === 'Resolved' ? 'bg-success' : 'bg-secondary';
        const mlIcon = query.ml_enabled ? '<i class="fa-solid fa-check text-success"></i>' : '<i class="fa-solid fa-times text-muted"></i>';
        
        // Parse Q&A pairs and get first question for preview
        let firstQuestion = 'N/A';
        let qaCount = 0;
        try {
          const qaPairs = JSON.parse(query.qa_pairs);
          qaCount = qaPairs.length;
          if (qaPairs.length > 0 && qaPairs[0].question) {
            firstQuestion = qaPairs[0].question;
          }
        } catch (e) {
          console.error('Error parsing Q&A pairs:', e);
          firstQuestion = 'Error parsing questions';
        }
        
        const questionPreview = qaCount > 1 ? `${firstQuestion} <span class="badge bg-secondary ms-1">+${qaCount - 1} more</span>` : firstQuestion;
        
        const row = `
          <tr data-query-id="${query.query_id}" onclick="viewQueryDetails(${query.query_id})" style="cursor: pointer;" title="Click to view details">
            <td>${index + 1}</td>
            <td class="fw-semibold">${query.client_name}</td>
            <td>${query.company_name || 'N/A'}</td>
            <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${firstQuestion}">${questionPreview}</td>
            <td><span class="badge bg-primary">${query.query_type}</span></td>
            <td><span class="badge ${riskBadge}">${query.risk_level}</span></td>
            <td class="text-center">${mlIcon}</td>
            <td>${query.query_date}</td>
            <td><span class="badge ${statusBadge}">${query.status}</span></td>
            <td onclick="event.stopPropagation();">
              <button class="btn btn-sm btn-danger" onclick="deleteQuery(${query.query_id})">
                <i class="fa-solid fa-trash"></i>
              </button>
            </td>
          </tr>
        `;
        tbody.innerHTML += row;
      });
      
      // Apply filters if any are active
      applyQueryFilters();
    } else {
      // Show error message in table
      const tbody = document.querySelector('#queriesTable tbody');
      tbody.innerHTML = `
        <tr>
          <td colspan="10" class="text-center text-danger py-4">
            <i class="fa-solid fa-exclamation-triangle fa-3x mb-2"></i>
            <p><strong>Error loading queries:</strong> ${data.message || 'Unknown error'}</p>
            <p class="text-muted">Please check if the client_queries table exists in your database.</p>
          </td>
        </tr>
      `;
      console.error('Failed to load queries:', data.message);
    }
  })
  .catch(error => {
    console.error('Error loading queries:', error);
    const tbody = document.querySelector('#queriesTable tbody');
    tbody.innerHTML = `
      <tr>
        <td colspan="10" class="text-center text-danger py-4">
          <i class="fa-solid fa-exclamation-triangle fa-3x mb-2"></i>
          <p><strong>Network Error:</strong> ${error.message}</p>
          <p class="text-muted">Failed to fetch data from client_queries table. Please check:</p>
          <ul class="text-start" style="max-width: 500px; margin: 0 auto;">
            <li>Database connection is working</li>
            <li>client_queries table exists</li>
            <li>query_handler.php file is accessible</li>
          </ul>
        </td>
      </tr>
    `;
  });
}

// View query details
function viewQueryDetails(queryId) {
  const formData = new FormData();
  formData.append('action', 'get_queries');
  
  fetch('query_handler.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const query = data.queries.find(q => q.query_id == queryId);
      if (query) {
        const riskBadge = query.risk_level === 'High' ? 'bg-danger' : query.risk_level === 'Middle' ? 'bg-warning text-dark' : 'bg-success';
        const statusBadge = query.status === 'Pending' ? 'bg-warning text-dark' : query.status === 'In Progress' ? 'bg-info' : query.status === 'Resolved' ? 'bg-success' : 'bg-secondary';
        
        let detailsHTML = `
          <!-- Client Information Card -->
          <div class="card mb-4" style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.1), rgba(13, 110, 253, 0.05)); border: 2px solid rgba(13, 110, 253, 0.3); border-radius: 12px;">
            <div class="card-header" style="background: rgba(13, 110, 253, 0.2); border-bottom: 2px solid rgba(13, 110, 253, 0.3); border-radius: 10px 10px 0 0;">
              <h5 class="mb-0 text-light"><i class="fa-solid fa-user-circle me-2" style="color: #0d6efd;"></i>Client Information</h5>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="info-item" style="padding: 0.75rem; background: rgba(13, 110, 253, 0.05); border-radius: 8px; border-left: 4px solid #0d6efd;">
                    <small class="d-block mb-1" style="color: #adb5bd;"><i class="fa-solid fa-user me-1" style="color: #0d6efd;"></i>Client Name</small>
                    <strong class="text-light" style="font-size: 1.1rem;">${query.client_name}</strong>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item" style="padding: 0.75rem; background: rgba(13, 110, 253, 0.05); border-radius: 8px; border-left: 4px solid #0d6efd;">
                    <small class="d-block mb-1" style="color: #adb5bd;"><i class="fa-solid fa-building me-1" style="color: #0d6efd;"></i>Company</small>
                    <strong class="text-light" style="font-size: 1.1rem;">${query.company_name || 'N/A'}</strong>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="info-item" style="padding: 0.75rem; background: rgba(13, 110, 253, 0.05); border-radius: 8px; text-align: center;">
                    <small class="d-block mb-1" style="color: #adb5bd;"><i class="fa-solid fa-tag me-1" style="color: #0d6efd;"></i>Type</small>
                    <span class="badge bg-primary" style="font-size: 0.95rem; padding: 0.5rem 1rem;">${query.query_type}</span>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="info-item" style="padding: 0.75rem; background: rgba(13, 110, 253, 0.05); border-radius: 8px; text-align: center;">
                    <small class="d-block mb-1" style="color: #adb5bd;"><i class="fa-solid fa-triangle-exclamation me-1" style="color: #0d6efd;"></i>Risk Level</small>
                    <span class="badge ${riskBadge}" style="font-size: 0.95rem; padding: 0.5rem 1rem;">${query.risk_level}</span>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="info-item" style="padding: 0.75rem; background: rgba(13, 110, 253, 0.05); border-radius: 8px; text-align: center;">
                    <small class="d-block mb-1" style="color: #adb5bd;"><i class="fa-solid fa-calendar me-1" style="color: #0d6efd;"></i>Date</small>
                    <strong class="text-light">${query.query_date}</strong>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="info-item" style="padding: 0.75rem; background: rgba(13, 110, 253, 0.05); border-radius: 8px; text-align: center;">
                    <small class="d-block mb-1" style="color: #adb5bd;"><i class="fa-solid fa-envelope me-1" style="color: #0d6efd;"></i>Management Letter</small>
                    ${query.ml_enabled ? '<i class="fa-solid fa-check-circle text-success" style="font-size: 1.5rem;" title="Enabled"></i>' : '<i class="fa-solid fa-times-circle text-danger" style="font-size: 1.5rem;" title="Disabled"></i>'}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Questions & Answers Card -->
          <div class="card mb-4" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 193, 7, 0.05)); border: 2px solid rgba(255, 193, 7, 0.3); border-radius: 12px;">
            <div class="card-header" style="background: rgba(255, 193, 7, 0.2); border-bottom: 2px solid rgba(255, 193, 7, 0.3); border-radius: 10px 10px 0 0;">
              <h5 class="mb-0 text-light"><i class="fa-solid fa-comments me-2" style="color: #ffc107;"></i>Questions & Answers</h5>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
        `;
        
        // Parse and display all Q&A pairs
        try {
          const qaPairs = JSON.parse(query.qa_pairs);
          qaPairs.forEach((pair, index) => {
            detailsHTML += `
              <div class="qa-item mb-3" style="background: rgba(255, 193, 7, 0.05); padding: 1.25rem; border-radius: 10px; border: 1px solid rgba(255, 193, 7, 0.2);">
                <div class="mb-3">
                  <div class="d-flex align-items-center mb-2">
                    <span class="badge bg-warning text-dark me-2" style="font-size: 0.85rem; padding: 0.4rem 0.6rem;">#${index + 1}</span>
                    <strong style="color: #ffc107; font-size: 1rem;"><i class="fa-solid fa-circle-question me-1"></i>Question</strong>
                  </div>
                  <div style="background: rgba(255, 255, 255, 0.05); padding: 1rem; border-radius: 8px; border-left: 4px solid #ffc107;">
                    <p class="mb-0 text-light" style="line-height: 1.6;">${pair.question || '<em style="color: #adb5bd;">No question</em>'}</p>
                  </div>
                </div>
                <div>
                  <div class="d-flex align-items-center mb-2">
                    <strong style="color: #28a745; font-size: 1rem;"><i class="fa-solid fa-comment-dots me-1"></i>Answer</strong>
                  </div>
                  <div style="background: rgba(40, 167, 69, 0.1); padding: 1rem; border-radius: 8px; border-left: 4px solid #28a745;">
                    <p class="mb-0 text-light" style="line-height: 1.6;">${pair.answer || '<em style="color: #adb5bd;">No answer provided yet</em>'}</p>
                  </div>
                </div>
              </div>
            `;
          });
        } catch (e) {
          detailsHTML += `<div class="alert alert-danger"><i class="fa-solid fa-exclamation-triangle me-2"></i>Error loading Q&A pairs</div>`;
        }
        
        detailsHTML += `
            </div>
          </div>

          <!-- Attachments Card -->
          <div class="card mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.1), rgba(13, 202, 240, 0.05)); border: 2px solid rgba(13, 202, 240, 0.3); border-radius: 12px;">
            <div class="card-header" style="background: rgba(13, 202, 240, 0.2); border-bottom: 2px solid rgba(13, 202, 240, 0.3); border-radius: 10px 10px 0 0;">
              <h5 class="mb-0 text-light"><i class="fa-solid fa-paperclip me-2" style="color: #0dcaf0;"></i>Attachments</h5>
            </div>
            <div class="card-body">
              <div class="row g-3">
        `;
        
        // Photo attachment
        if (query.photo_url) {
          detailsHTML += `
                <div class="col-md-4">
                  <div class="attachment-item" style="background: rgba(13, 202, 240, 0.05); padding: 1rem; border-radius: 10px; border: 1px solid rgba(13, 202, 240, 0.2);">
                    <div class="d-flex align-items-center mb-2">
                      <i class="fa-solid fa-image me-2" style="color: #0dcaf0; font-size: 1.2rem;"></i>
                      <strong class="text-light">Photo</strong>
                    </div>
                    <img src="${query.photo_url}" alt="Query Photo" class="img-fluid" style="border-radius: 8px; border: 2px solid #0dcaf0; max-height: 200px; width: 100%; object-fit: cover; cursor: pointer;" onclick="window.open('${query.photo_url}', '_blank')">
                    <a href="${query.photo_url}" target="_blank" class="btn btn-sm btn-info w-100 mt-2">
                      <i class="fa-solid fa-external-link me-1"></i>Open Full Size
                    </a>
                  </div>
                </div>
          `;
        }
        
        // Voice attachment
        if (query.voice_url) {
          detailsHTML += `
                <div class="col-md-4">
                  <div class="attachment-item" style="background: rgba(13, 202, 240, 0.05); padding: 1rem; border-radius: 10px; border: 1px solid rgba(13, 202, 240, 0.2);">
                    <div class="d-flex align-items-center mb-2">
                      <i class="fa-solid fa-microphone me-2" style="color: #0dcaf0; font-size: 1.2rem;"></i>
                      <strong class="text-light">Voice Recording</strong>
                    </div>
                    <audio controls style="width: 100%; border-radius: 8px;">
                      <source src="${query.voice_url}" type="audio/mpeg">
                      Your browser does not support the audio element.
                    </audio>
                    <a href="${query.voice_url}" download class="btn btn-sm btn-info w-100 mt-2">
                      <i class="fa-solid fa-download me-1"></i>Download
                    </a>
                  </div>
                </div>
          `;
        }
        
        // Document attachment
        if (query.document_url) {
          const docExtension = query.document_url.split('.').pop().toLowerCase();
          const docIcon = docExtension === 'pdf' ? 'fa-file-pdf' : 
                         (docExtension === 'doc' || docExtension === 'docx') ? 'fa-file-word' : 
                         (docExtension === 'xls' || docExtension === 'xlsx') ? 'fa-file-excel' : 'fa-file-alt';
          detailsHTML += `
                <div class="col-md-4">
                  <div class="attachment-item" style="background: rgba(13, 202, 240, 0.05); padding: 1rem; border-radius: 10px; border: 1px solid rgba(13, 202, 240, 0.2);">
                    <div class="d-flex align-items-center mb-2">
                      <i class="fa-solid ${docIcon} me-2" style="color: #0dcaf0; font-size: 1.2rem;"></i>
                      <strong class="text-light">Document</strong>
                    </div>
                    <div class="text-center py-4" style="background: rgba(13, 202, 240, 0.1); border-radius: 8px; border: 2px dashed #0dcaf0;">
                      <i class="fa-solid ${docIcon}" style="font-size: 3rem; color: #0dcaf0; opacity: 0.7;"></i>
                      <p class="mt-2 mb-0" style="color: #adb5bd;">.${docExtension.toUpperCase()} File</p>
                    </div>
                    <div class="d-grid gap-2 mt-2">
                      <a href="${query.document_url}" target="_blank" class="btn btn-sm btn-info">
                        <i class="fa-solid fa-eye me-1"></i>View
                      </a>
                      <a href="${query.document_url}" download class="btn btn-sm btn-outline-info">
                        <i class="fa-solid fa-download me-1"></i>Download
                      </a>
                    </div>
                  </div>
                </div>
          `;
        }
        
        // No attachments message
        if (!query.photo_url && !query.voice_url && !query.document_url) {
          detailsHTML += `
                <div class="col-12">
                  <div class="text-center py-4" style="background: rgba(108, 117, 125, 0.1); border-radius: 8px; border: 2px dashed rgba(108, 117, 125, 0.3);">
                    <i class="fa-solid fa-inbox" style="font-size: 3rem; color: #adb5bd; opacity: 0.5;"></i>
                    <p class="mt-2 mb-0" style="color: #adb5bd;">No attachments available</p>
                  </div>
                </div>
          `;
        }
        
        detailsHTML += `
              </div>
            </div>
          </div>

          <!-- Status Update Card -->
          <div class="card" style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05)); border: 2px solid rgba(40, 167, 69, 0.3); border-radius: 12px;">
            <div class="card-header" style="background: rgba(40, 167, 69, 0.2); border-bottom: 2px solid rgba(40, 167, 69, 0.3); border-radius: 10px 10px 0 0;">
              <h5 class="mb-0 text-light"><i class="fa-solid fa-tasks me-2" style="color: #28a745;"></i>Update Status</h5>
            </div>
            <div class="card-body">
              <div class="row align-items-end">
                <div class="col-md-8">
                  <label class="form-label text-light fw-semibold mb-2">
                    <i class="fa-solid fa-circle-info me-1"></i>Current Status: 
                    <span class="badge ${statusBadge} ms-2" style="font-size: 0.9rem;">${query.status}</span>
                  </label>
                  <select class="form-select form-select-lg" id="updateQueryStatus" style="background-color: #2b3035; border: 2px solid rgba(40, 167, 69, 0.5); color: #fff; border-radius: 8px;">
                    <option value="Pending" ${query.status === 'Pending' ? 'selected' : ''}>⏳ Pending</option>
                    <option value="In Progress" ${query.status === 'In Progress' ? 'selected' : ''}>🔄 In Progress</option>
                    <option value="Resolved" ${query.status === 'Resolved' ? 'selected' : ''}>✅ Resolved</option>
                    <option value="Closed" ${query.status === 'Closed' ? 'selected' : ''}>🔒 Closed</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <button class="btn btn-success btn-lg w-100" onclick="updateQueryStatus(${queryId})" style="border-radius: 8px; font-weight: 600;">
                    <i class="fa-solid fa-save me-2"></i>Update Status
                  </button>
                </div>
              </div>
              <div class="mt-3 p-3" style="background: rgba(40, 167, 69, 0.1); border-radius: 8px; border-left: 4px solid #28a745;">
                <small style="color: #e9ecef;">
                  <i class="fa-solid fa-user me-1" style="color: #28a745;"></i><strong>Created By:</strong> ${query.creator_name || 'N/A'}
                </small>
              </div>
            </div>
          </div>
        `;
        
        // Show in a modal
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = `
          <div class="modal fade" id="queryDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
              <div class="modal-content" style="background: #1a1d29; border: 2px solid rgba(13, 110, 253, 0.3); border-radius: 16px;">
                <div class="modal-header" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); border-bottom: none; border-radius: 14px 14px 0 0; padding: 1.5rem 2rem;">
                  <div>
                    <h4 class="modal-title text-white fw-bold mb-1">
                      <i class="fa-solid fa-file-lines me-2"></i>Query Details
                    </h4>
                    <p class="mb-0 text-white-50" style="font-size: 0.9rem;">
                      <i class="fa-solid fa-hashtag me-1"></i>Query ID: ${query.query_id}
                    </p>
                  </div>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="background: linear-gradient(135deg, #1a1d29 0%, #252a3a 100%); padding: 2rem;">
                  ${detailsHTML}
                </div>
              </div>
            </div>
          </div>
        `;
        document.body.appendChild(tempDiv);
        const modal = new bootstrap.Modal(document.getElementById('queryDetailsModal'));
        modal.show();
        
        document.getElementById('queryDetailsModal').addEventListener('hidden.bs.modal', function() {
          tempDiv.remove();
        });
      }
    }
  });
}

// Update query status
function updateQueryStatus(queryId) {
  const status = document.getElementById('updateQueryStatus').value;
  const formData = new FormData();
  formData.append('action', 'update_status');
  formData.append('query_id', queryId);
  formData.append('status', status);
  
  fetch('query_handler.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('queryDetailsModal')).hide();
      loadQueries();
    } else {
      alert('Error: ' + data.message);
    }
  });
}

// Delete query
function deleteQuery(queryId) {
  if (!confirm('Are you sure you want to delete this query?')) return;
  
  const formData = new FormData();
  formData.append('action', 'delete_query');
  formData.append('query_id', queryId);
  
  fetch('query_handler.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      loadQueries();
    } else {
      alert('Error: ' + data.message);
    }
  });
}

// Apply query filters
function applyQueryFilters() {
  const searchTerm = document.getElementById('searchQueries').value.toLowerCase();
  const typeFilter = document.getElementById('filterQueryType').value;
  const riskFilter = document.getElementById('filterRiskLevel').value;
  const statusFilter = document.getElementById('filterQueryStatus').value;
  
  const rows = document.querySelectorAll('#queriesTable tbody tr');
  
  rows.forEach(row => {
    if (row.cells.length === 1) return; // Skip empty state row
    
    const clientName = row.cells[1].textContent.toLowerCase();
    const company = row.cells[2].textContent.toLowerCase();
    const question = row.cells[3].textContent.toLowerCase();
    const type = row.cells[4].textContent.trim();
    const risk = row.cells[5].textContent.trim();
    const status = row.cells[8].textContent.trim();
    
    const matchesSearch = clientName.includes(searchTerm) || company.includes(searchTerm) || question.includes(searchTerm);
    const matchesType = !typeFilter || type === typeFilter;
    const matchesRisk = !riskFilter || risk === riskFilter;
    const matchesStatus = !statusFilter || status === statusFilter;
    
    row.style.display = (matchesSearch && matchesType && matchesRisk && matchesStatus) ? '' : 'none';
  });
}

// Search queries
document.getElementById('searchQueries').addEventListener('input', applyQueryFilters);
document.getElementById('filterQueryType').addEventListener('change', applyQueryFilters);
document.getElementById('filterRiskLevel').addEventListener('change', applyQueryFilters);
document.getElementById('filterQueryStatus').addEventListener('change', applyQueryFilters);

// Clear query filters
document.getElementById('clearQueryFilters').addEventListener('click', function() {
  document.getElementById('searchQueries').value = '';
  document.getElementById('filterQueryType').value = '';
  document.getElementById('filterRiskLevel').value = '';
  document.getElementById('filterQueryStatus').value = '';
  applyQueryFilters();
});

// Load queries when page shows queries section
const originalShowPage = showPage;
showPage = function(page) {
  originalShowPage(page);
  if (page === 'queries') {
    loadQueries();
  }
  if (page === 'reviews') {
    loadReviews();
  }
};

// ========== MANAGER REVIEW HANDLERS ==========

// Add Review Q&A Pair Function
let reviewQaPairCount = 1;

function addReviewQAPair() {
  reviewQaPairCount++;
  const container = document.getElementById('reviewQaPairsContainer');
  
  const newPair = document.createElement('div');
  newPair.className = 'review-qa-pair mb-4';
  newPair.style.cssText = 'background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(30, 58, 138, 0.05) 100%); padding: 1.5rem; border-radius: 12px; border: 2px solid rgba(59, 130, 246, 0.3); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1); animation: slideIn 0.3s ease;';
  
  const icons = ['fa-circle-2', 'fa-circle-3', 'fa-circle-4', 'fa-circle-5', 'fa-circle-6', 'fa-circle-7', 'fa-circle-8', 'fa-circle-9'];
  const iconClass = reviewQaPairCount <= 9 ? icons[reviewQaPairCount - 2] : 'fa-circle';
  
  newPair.innerHTML = `
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="mb-0" style="color: #3b82f6; font-weight: 700; font-size: 1.1rem;">
        <i class="fa-solid ${iconClass} me-2"></i> Q&A Pair #${reviewQaPairCount}
      </h6>
      <button type="button" class="btn btn-sm btn-danger" onclick="removeReviewQAPair(this)" 
        style="padding: 0.4rem 0.8rem; font-size: 0.85rem; border-radius: 6px; font-weight: 600; transition: all 0.3s ease;">
        <i class="fa-solid fa-trash-alt me-1"></i> Remove
      </button>
    </div>
    
    <div class="mb-3">
      <label class="form-label fw-semibold d-flex align-items-center" style="color: #e9ecef; font-size: 0.95rem;">
        <i class="fa-solid fa-circle-question me-2" style="color: #3b82f6;"></i> Question (Q)
      </label>
      <textarea class="form-control review-qa-question" name="review_questions[]" rows="3" placeholder="Enter question #${reviewQaPairCount}..."
        style="background-color: #2b3035; border: 2px solid rgba(59, 130, 246, 0.4); color: #fff; padding: 0.75rem 1rem; border-radius: 8px; resize: vertical; font-size: 0.95rem; transition: all 0.3s ease;"></textarea>
    </div>
    
    <div>
      <label class="form-label fw-semibold d-flex align-items-center" style="color: #e9ecef; font-size: 0.95rem;">
        <i class="fa-solid fa-comment-dots me-2" style="color: #28a745;"></i> Answer (A) <span class="badge bg-secondary ms-2" style="font-size: 0.75rem;">Optional</span>
      </label>
      <textarea class="form-control review-qa-answer" name="review_answers[]" rows="3" placeholder="Enter answer (optional)..."
        style="background-color: #2b3035; border: 2px solid rgba(40, 167, 69, 0.4); color: #fff; padding: 0.75rem 1rem; border-radius: 8px; resize: vertical; font-size: 0.95rem; transition: all 0.3s ease;"></textarea>
    </div>
  `;
  
  container.appendChild(newPair);
  
  setTimeout(() => {
    newPair.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }, 100);
}

function removeReviewQAPair(button) {
  const pair = button.closest('.review-qa-pair');
  pair.remove();
  
  const pairs = document.querySelectorAll('#reviewQaPairsContainer .review-qa-pair');
  pairs.forEach((pair, index) => {
    const label = pair.querySelector('strong');
    if (label) {
      label.textContent = `${index + 1}. Q&A Pair`;
    }
  });
  reviewQaPairCount = pairs.length;
}

// Auto-check Management Letter (ML) checkbox when Middle or High risk is selected
document.getElementById('review_risk').addEventListener('change', function() {
  const riskLevel = this.value;
  const mlCheckbox = document.getElementById('review_ml');
  
  if (riskLevel === 'Middle' || riskLevel === 'High') {
    mlCheckbox.checked = true;
    mlCheckbox.parentElement.style.animation = 'pulse 0.5s ease';
    setTimeout(() => {
      mlCheckbox.parentElement.style.animation = '';
    }, 500);
  }
});

// Handle add review form submission
document.getElementById('addReviewForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const questions = Array.from(document.querySelectorAll('.review-qa-question')).map(q => q.value.trim());
  const answers = Array.from(document.querySelectorAll('.review-qa-answer')).map(a => a.value.trim());
  
  if (questions.length === 0 || questions[0] === '') {
    alert('Please enter at least one question!');
    return;
  }
  
  const formData = new FormData(this);
  formData.append('action', 'add_review');
  
  formData.append('qa_pairs', JSON.stringify(questions.map((q, i) => ({
    question: q,
    answer: answers[i] || ''
  }))));
  
  fetch('manager_review_handler.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      bootstrap.Modal.getInstance(document.getElementById('addReviewModal')).hide();
      this.reset();
      loadReviews();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while adding the review.');
  });
});

// Load reviews into table
function loadReviews() {
  const formData = new FormData();
  formData.append('action', 'get_reviews');
  
  fetch('manager_review_handler.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.text().then(text => {
    console.log('Raw response:', text);
    try {
      return JSON.parse(text);
    } catch (e) {
      console.error('JSON Parse Error:', e);
      throw new Error('Server returned invalid JSON');
    }
  }))
  .then(data => {
    console.log('Review data received:', data);
    
    if (data.success) {
      const tbody = document.querySelector('#reviewsTable tbody');
      tbody.innerHTML = '';
      
      if (data.reviews.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="10" class="text-center text-muted py-4">
              <i class="fa-solid fa-inbox fa-3x mb-2" style="opacity: 0.3;"></i>
              <p>No reviews found. Click "Add Review" to create one.</p>
            </td>
          </tr>
        `;
        return;
      }
      
      data.reviews.forEach((review, index) => {
        const riskBadge = review.risk_level === 'High' ? 'bg-danger' : 
                         review.risk_level === 'Middle' ? 'bg-warning text-dark' : 'bg-success';
        const statusBadge = review.status === 'Pending' ? 'bg-warning text-dark' :
                           review.status === 'In Progress' ? 'bg-info' :
                           review.status === 'Resolved' ? 'bg-success' : 'bg-secondary';
        const mlIcon = review.ml_enabled ? '<i class="fa-solid fa-check text-success"></i>' : '<i class="fa-solid fa-times text-muted"></i>';
        
        let firstQuestion = 'N/A';
        let qaCount = 0;
        try {
          const qaPairs = JSON.parse(review.qa_pairs);
          qaCount = qaPairs.length;
          if (qaPairs.length > 0 && qaPairs[0].question) {
            firstQuestion = qaPairs[0].question;
          }
        } catch (e) {
          console.error('Error parsing Q&A pairs:', e);
          firstQuestion = 'Error parsing questions';
        }
        
        const questionPreview = qaCount > 1 ? `${firstQuestion} <span class="badge bg-secondary ms-1">+${qaCount - 1} more</span>` : firstQuestion;
        
        const row = `
          <tr data-review-id="${review.review_id}" onclick="viewReviewDetails(${review.review_id})" style="cursor: pointer;" title="Click to view details">
            <td>${index + 1}</td>
            <td class="fw-semibold">${review.manager_name}</td>
            <td>${review.company_name || 'N/A'}</td>
            <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${questionPreview}</td>
            <td><span class="badge bg-primary">${review.review_type}</span></td>
            <td><span class="badge ${riskBadge}">${review.risk_level}</span></td>
            <td class="text-center">${mlIcon}</td>
            <td>${review.review_date}</td>
            <td><span class="badge ${statusBadge}">${review.status}</span></td>
            <td onclick="event.stopPropagation();">
              <button class="btn btn-sm btn-danger" onclick="deleteReview(${review.review_id})" title="Delete">
                <i class="fa-solid fa-trash"></i>
              </button>
            </td>
          </tr>
        `;
        tbody.innerHTML += row;
      });
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Failed to load reviews: ' + error.message);
  });
}

// View review details
function viewReviewDetails(reviewId) {
  const formData = new FormData();
  formData.append('action', 'get_reviews');
  
  fetch('manager_review_handler.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const review = data.reviews.find(r => r.review_id == reviewId);
      if (review) {
        const riskBadge = review.risk_level === 'High' ? 'bg-danger' : review.risk_level === 'Middle' ? 'bg-warning text-dark' : 'bg-success';
        const statusBadge = review.status === 'Pending' ? 'bg-warning text-dark' : review.status === 'In Progress' ? 'bg-info' : review.status === 'Resolved' ? 'bg-success' : 'bg-secondary';
        
        let detailsHTML = `
          <!-- Manager Information Card -->
          <div class="card mb-4" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 2px solid rgba(59, 130, 246, 0.3); border-radius: 12px;">
            <div class="card-header" style="background: rgba(59, 130, 246, 0.2); border-bottom: 2px solid rgba(59, 130, 246, 0.3); border-radius: 10px 10px 0 0;">
              <h5 class="mb-0 text-light"><i class="fa-solid fa-user-tie me-2" style="color: #3b82f6;"></i>Manager Information</h5>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="info-item" style="padding: 0.75rem; background: rgba(59, 130, 246, 0.05); border-radius: 8px; border-left: 4px solid #3b82f6;">
                    <small class="d-block mb-1" style="color: #adb5bd;"><i class="fa-solid fa-user me-1" style="color: #3b82f6;"></i>Manager Name</small>
                    <strong class="text-light" style="font-size: 1.1rem;">${review.manager_name}</strong>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item" style="padding: 0.75rem; background: rgba(59, 130, 246, 0.05); border-radius: 8px; border-left: 4px solid #3b82f6;">
                    <small class="d-block mb-1" style="color: #adb5bd;"><i class="fa-solid fa-building me-1" style="color: #3b82f6;"></i>Company</small>
                    <strong class="text-light" style="font-size: 1.1rem;">${review.company_name || 'N/A'}</strong>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="info-item" style="padding: 0.75rem; background: rgba(59, 130, 246, 0.05); border-radius: 8px; text-align: center;">
                    <small class="d-block mb-1" style="color: #adb5bd;"><i class="fa-solid fa-tag me-1" style="color: #3b82f6;"></i>Type</small>
                    <span class="badge bg-primary" style="font-size: 0.95rem; padding: 0.5rem 1rem;">${review.review_type}</span>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="info-item" style="padding: 0.75rem; background: rgba(59, 130, 246, 0.05); border-radius: 8px; text-align: center;">
                    <small class="d-block mb-1" style="color: #adb5bd;"><i class="fa-solid fa-triangle-exclamation me-1" style="color: #3b82f6;"></i>Risk Level</small>
                    <span class="badge ${riskBadge}" style="font-size: 0.95rem; padding: 0.5rem 1rem;">${review.risk_level}</span>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="info-item" style="padding: 0.75rem; background: rgba(59, 130, 246, 0.05); border-radius: 8px; text-align: center;">
                    <small class="d-block mb-1" style="color: #adb5bd;"><i class="fa-solid fa-calendar me-1" style="color: #3b82f6;"></i>Date</small>
                    <strong class="text-light">${review.review_date}</strong>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="info-item" style="padding: 0.75rem; background: rgba(59, 130, 246, 0.05); border-radius: 8px; text-align: center;">
                    <small class="d-block mb-1" style="color: #adb5bd;"><i class="fa-solid fa-envelope me-1" style="color: #3b82f6;"></i>Management Letter</small>
                    ${review.ml_enabled ? '<i class="fa-solid fa-check-circle text-success" style="font-size: 1.5rem;" title="Enabled"></i>' : '<i class="fa-solid fa-times-circle text-danger" style="font-size: 1.5rem;" title="Disabled"></i>'}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Questions & Answers Card -->
          <div class="card mb-4" style="background: linear-gradient(135deg, rgba(96, 165, 250, 0.1), rgba(96, 165, 250, 0.05)); border: 2px solid rgba(96, 165, 250, 0.3); border-radius: 12px;">
            <div class="card-header" style="background: rgba(96, 165, 250, 0.2); border-bottom: 2px solid rgba(96, 165, 250, 0.3); border-radius: 10px 10px 0 0;">
              <h5 class="mb-0 text-light"><i class="fa-solid fa-comments me-2" style="color: #60a5fa;"></i>Questions & Answers</h5>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
        `;
        
        // Parse and display all Q&A pairs
        try {
          const qaPairs = JSON.parse(review.qa_pairs);
          qaPairs.forEach((pair, index) => {
            detailsHTML += `
              <div class="qa-item mb-3" style="background: rgba(96, 165, 250, 0.05); padding: 1.25rem; border-radius: 10px; border: 1px solid rgba(96, 165, 250, 0.2);">
                <div class="mb-3">
                  <div class="d-flex align-items-center mb-2">
                    <span class="badge bg-primary me-2" style="font-size: 0.85rem; padding: 0.4rem 0.6rem;">#${index + 1}</span>
                    <strong style="color: #60a5fa; font-size: 1rem;"><i class="fa-solid fa-circle-question me-1"></i>Question</strong>
                  </div>
                  <div style="background: rgba(255, 255, 255, 0.05); padding: 1rem; border-radius: 8px; border-left: 4px solid #60a5fa;">
                    <p class="mb-0 text-light" style="line-height: 1.6;">${pair.question || '<em style="color: #adb5bd;">No question</em>'}</p>
                  </div>
                </div>
                <div>
                  <div class="d-flex align-items-center mb-2">
                    <strong style="color: #28a745; font-size: 1rem;"><i class="fa-solid fa-comment-dots me-1"></i>Answer</strong>
                  </div>
                  <div style="background: rgba(40, 167, 69, 0.1); padding: 1rem; border-radius: 8px; border-left: 4px solid #28a745;">
                    <p class="mb-0 text-light" style="line-height: 1.6;">${pair.answer || '<em style="color: #adb5bd;">No answer provided yet</em>'}</p>
                  </div>
                </div>
              </div>
            `;
          });
        } catch (e) {
          detailsHTML += `<div class="alert alert-danger"><i class="fa-solid fa-exclamation-triangle me-2"></i>Error loading Q&A pairs</div>`;
        }
        
        detailsHTML += `
            </div>
          </div>

          <!-- Attachments Card -->
          <div class="card mb-4" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.1), rgba(13, 202, 240, 0.05)); border: 2px solid rgba(13, 202, 240, 0.3); border-radius: 12px;">
            <div class="card-header" style="background: rgba(13, 202, 240, 0.2); border-bottom: 2px solid rgba(13, 202, 240, 0.3); border-radius: 10px 10px 0 0;">
              <h5 class="mb-0 text-light"><i class="fa-solid fa-paperclip me-2" style="color: #0dcaf0;"></i>Attachments</h5>
            </div>
            <div class="card-body">
              <div class="row g-3">
        `;
        
        // Photo attachment
        if (review.photo_url) {
          detailsHTML += `
                <div class="col-md-4">
                  <div class="attachment-item" style="background: rgba(13, 202, 240, 0.05); padding: 1rem; border-radius: 10px; border: 1px solid rgba(13, 202, 240, 0.2);">
                    <div class="d-flex align-items-center mb-2">
                      <i class="fa-solid fa-image me-2" style="color: #0dcaf0; font-size: 1.2rem;"></i>
                      <strong class="text-light">Photo</strong>
                    </div>
                    <img src="${review.photo_url}" alt="Review Photo" class="img-fluid" style="border-radius: 8px; border: 2px solid #0dcaf0; max-height: 200px; width: 100%; object-fit: cover; cursor: pointer;" onclick="window.open('${review.photo_url}', '_blank')">
                    <a href="${review.photo_url}" target="_blank" class="btn btn-sm btn-info w-100 mt-2">
                      <i class="fa-solid fa-external-link me-1"></i>Open Full Size
                    </a>
                  </div>
                </div>
          `;
        }
        
        // Voice attachment
        if (review.voice_url) {
          detailsHTML += `
                <div class="col-md-4">
                  <div class="attachment-item" style="background: rgba(13, 202, 240, 0.05); padding: 1rem; border-radius: 10px; border: 1px solid rgba(13, 202, 240, 0.2);">
                    <div class="d-flex align-items-center mb-2">
                      <i class="fa-solid fa-microphone me-2" style="color: #0dcaf0; font-size: 1.2rem;"></i>
                      <strong class="text-light">Voice Recording</strong>
                    </div>
                    <audio controls style="width: 100%; border-radius: 8px;">
                      <source src="${review.voice_url}" type="audio/mpeg">
                      Your browser does not support the audio element.
                    </audio>
                    <a href="${review.voice_url}" download class="btn btn-sm btn-info w-100 mt-2">
                      <i class="fa-solid fa-download me-1"></i>Download
                    </a>
                  </div>
                </div>
          `;
        }
        
        // Document attachment
        if (review.document_url) {
          const docExtension = review.document_url.split('.').pop().toLowerCase();
          const docIcon = docExtension === 'pdf' ? 'fa-file-pdf' : 
                         (docExtension === 'doc' || docExtension === 'docx') ? 'fa-file-word' : 
                         (docExtension === 'xls' || docExtension === 'xlsx') ? 'fa-file-excel' : 'fa-file-alt';
          detailsHTML += `
                <div class="col-md-4">
                  <div class="attachment-item" style="background: rgba(13, 202, 240, 0.05); padding: 1rem; border-radius: 10px; border: 1px solid rgba(13, 202, 240, 0.2);">
                    <div class="d-flex align-items-center mb-2">
                      <i class="fa-solid ${docIcon} me-2" style="color: #0dcaf0; font-size: 1.2rem;"></i>
                      <strong class="text-light">Document</strong>
                    </div>
                    <div class="text-center py-4" style="background: rgba(13, 202, 240, 0.1); border-radius: 8px; border: 2px dashed #0dcaf0;">
                      <i class="fa-solid ${docIcon}" style="font-size: 3rem; color: #0dcaf0; opacity: 0.7;"></i>
                      <p class="mt-2 mb-0" style="color: #adb5bd;">.${docExtension.toUpperCase()} File</p>
                    </div>
                    <div class="d-grid gap-2 mt-2">
                      <a href="${review.document_url}" target="_blank" class="btn btn-sm btn-info">
                        <i class="fa-solid fa-eye me-1"></i>View
                      </a>
                      <a href="${review.document_url}" download class="btn btn-sm btn-outline-info">
                        <i class="fa-solid fa-download me-1"></i>Download
                      </a>
                    </div>
                  </div>
                </div>
          `;
        }
        
        // If no attachments
        if (!review.photo_url && !review.voice_url && !review.document_url) {
          detailsHTML += `
                <div class="col-12">
                  <div class="text-center py-4" style="color: #adb5bd;">
                    <i class="fa-solid fa-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3 mb-0">No attachments available</p>
                  </div>
                </div>
          `;
        }
        
        detailsHTML += `
              </div>
            </div>
          </div>

          <!-- Status Information Card -->
          <div class="card" style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.1), rgba(108, 117, 125, 0.05)); border: 2px solid rgba(108, 117, 125, 0.3); border-radius: 12px;">
            <div class="card-header" style="background: rgba(108, 117, 125, 0.2); border-bottom: 2px solid rgba(108, 117, 125, 0.3); border-radius: 10px 10px 0 0;">
              <h5 class="mb-0 text-light"><i class="fa-solid fa-info-circle me-2" style="color: #6c757d;"></i>Status Information</h5>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <div class="info-item" style="padding: 0.75rem; background: rgba(108, 117, 125, 0.05); border-radius: 8px; text-align: center;">
                    <small class="d-block mb-1" style="color: #adb5bd;"><i class="fa-solid fa-flag me-1" style="color: #6c757d;"></i>Current Status</small>
                    <span class="badge ${statusBadge}" style="font-size: 1rem; padding: 0.6rem 1.2rem;">${review.status}</span>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="info-item" style="padding: 0.75rem; background: rgba(108, 117, 125, 0.05); border-radius: 8px; text-align: center;">
                    <small class="d-block mb-1" style="color: #adb5bd;"><i class="fa-solid fa-clock me-1" style="color: #6c757d;"></i>Created</small>
                    <strong class="text-light">${review.created_at}</strong>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="info-item" style="padding: 0.75rem; background: rgba(108, 117, 125, 0.05); border-radius: 8px;">
                    <small class="d-block mb-2" style="color: #adb5bd;"><i class="fa-solid fa-edit me-1" style="color: #6c757d;"></i>Update Status</small>
                    <div class="d-flex gap-2">
                      <select class="form-select form-select-sm" id="updateReviewStatus" style="background-color: #2b3035; border: 1px solid rgba(108, 117, 125, 0.4); color: #fff;">
                        <option value="Pending" ${review.status === 'Pending' ? 'selected' : ''}>Pending</option>
                        <option value="In Progress" ${review.status === 'In Progress' ? 'selected' : ''}>In Progress</option>
                        <option value="Resolved" ${review.status === 'Resolved' ? 'selected' : ''}>Resolved</option>
                        <option value="Closed" ${review.status === 'Closed' ? 'selected' : ''}>Closed</option>
                      </select>
                      <button class="btn btn-sm btn-primary" onclick="updateReviewStatus(${review.review_id})" style="white-space: nowrap;">
                        <i class="fa-solid fa-check"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        `;
        
        // Update modal content
        document.getElementById('reviewDetailsContent').innerHTML = detailsHTML;
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('viewReviewModal'));
        modal.show();
      }
    } else {
      alert('Error loading review details: ' + (data.message || 'Unknown error'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error loading review details');
  });
}

// Update review status
function updateReviewStatus(reviewId) {
  const statusSelect = document.getElementById('updateReviewStatus');
  const status = statusSelect.value;
  
  if (!status) {
    alert('Please select a status');
    return;
  }
  
  const formData = new FormData();
  formData.append('action', 'update_status');
  formData.append('review_id', reviewId);
  formData.append('status', status);
  
  fetch('manager_review_handler.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message || 'Status updated successfully');
      
      // Close the modal
      const modal = bootstrap.Modal.getInstance(document.getElementById('viewReviewModal'));
      if (modal) {
        modal.hide();
      }
      
      // Reload reviews to reflect the change
      loadReviews();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while updating the status.');
  });
}

// Delete review
function deleteReview(reviewId) {
  if (!confirm('Are you sure you want to delete this review?')) return;
  
  const formData = new FormData();
  formData.append('action', 'delete_review');
  formData.append('review_id', reviewId);
  
  fetch('manager_review_handler.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(data.message);
      loadReviews();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while deleting the review.');
  });
}

// Apply review filters
function applyReviewFilters() {
  const searchTerm = document.getElementById('searchReviews').value.toLowerCase();
  const typeFilter = document.getElementById('filterReviewType').value;
  const riskFilter = document.getElementById('filterReviewRiskLevel').value;
  const statusFilter = document.getElementById('filterReviewStatus').value;
  
  const rows = document.querySelectorAll('#reviewsTable tbody tr[data-review-id]');
  
  rows.forEach(row => {
    const managerName = row.cells[1].textContent.toLowerCase();
    const company = row.cells[2].textContent.toLowerCase();
    const question = row.cells[3].textContent.toLowerCase();
    const type = row.cells[4].textContent.trim();
    const risk = row.cells[5].textContent.trim();
    const status = row.cells[8].textContent.trim();
    
    const matchesSearch = managerName.includes(searchTerm) || company.includes(searchTerm) || question.includes(searchTerm);
    const matchesType = !typeFilter || type === typeFilter;
    const matchesRisk = !riskFilter || risk === riskFilter;
    const matchesStatus = !statusFilter || status === statusFilter;
    
    if (matchesSearch && matchesType && matchesRisk && matchesStatus) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

// Event listeners for review filters
document.getElementById('searchReviews').addEventListener('input', applyReviewFilters);
document.getElementById('filterReviewType').addEventListener('change', applyReviewFilters);
document.getElementById('filterReviewRiskLevel').addEventListener('change', applyReviewFilters);
document.getElementById('filterReviewStatus').addEventListener('change', applyReviewFilters);

// Clear review filters
document.getElementById('clearReviewFilters').addEventListener('click', function() {
  document.getElementById('searchReviews').value = '';
  document.getElementById('filterReviewType').value = '';
  document.getElementById('filterReviewRiskLevel').value = '';
  document.getElementById('filterReviewStatus').value = '';
  applyReviewFilters();
});

// Update table title map to include reviews
const originalGetTableTitle = getTableTitle;
getTableTitle = function(tableId) {
  if (tableId === 'reviewsTable') return 'Manager Reviews List';
  return originalGetTableTitle(tableId);
};

// ============ MANAGEMENT LETTER FUNCTIONS ============

// Populate company filter dropdown
function populateMLCompanyFilter() {
  fetch('management_letter_handler.php?action=get_companies')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const select = document.getElementById('filterMLCompany');
        select.innerHTML = '<option value="">All Companies</option>';
        data.companies.forEach(company => {
          const option = document.createElement('option');
          option.value = company.company_id;
          option.textContent = company.company_name;
          select.appendChild(option);
        });
      }
    })
    .catch(error => console.error('Error loading companies:', error));
}

// Load management letter data
function loadManagementLetterData() {
  const riskLevels = document.getElementById('filterMLRiskLevel').value;
  const companyId = document.getElementById('filterMLCompany').value;
  
  const params = new URLSearchParams({
    action: 'get_risk_items',
    risk_levels: riskLevels,
    company_id: companyId
  });
  
  fetch('management_letter_handler.php?' + params)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Update summary counts
        document.getElementById('mlHighRiskCount').textContent = data.summary.high_risk_count;
        document.getElementById('mlMiddleRiskCount').textContent = data.summary.middle_risk_count;
        document.getElementById('mlCompaniesCount').textContent = data.summary.companies_count;
        
        // Populate queries table
        populateMLQueriesTable(data.queries);
        
        // Populate reviews table
        populateMLReviewsTable(data.reviews);
      } else {
        alert('Error loading data: ' + (data.message || 'Unknown error'));
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error loading management letter data');
    });
}

// Populate queries table
function populateMLQueriesTable(queries) {
  const tbody = document.getElementById('mlQueriesTableBody');
  
  if (queries.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" class="text-center py-5" style="border: none; color: #94a3b8;">
          <i class="fa-solid fa-inbox fa-3x mb-3" style="opacity: 0.3;"></i>
          <p class="mb-0" style="font-size: 15px;">No risk items found.</p>
        </td>
      </tr>
    `;
    return;
  }
  
  let html = '';
  queries.forEach((query, index) => {
    const riskBadge = query.risk_level === 'High' 
      ? 'style="background: #dc2626; color: white; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 14px;"' 
      : 'style="background: #f59e0b; color: white; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 14px;"';
    const qaPairs = JSON.parse(query.qa_pairs);
    const qaSummary = qaPairs.length > 0 
      ? `<span style="color: #1e40af; font-weight: 600; font-size: 14px;">${qaPairs.length} Question${qaPairs.length > 1 ? 's' : ''}</span>` 
      : '<span style="color: #94a3b8; font-size: 14px;">No Q&A</span>';
    
    // Store data in escaped JSON format for the modal
    const queryData = JSON.stringify(query).replace(/"/g, '&quot;');
    
    html += `
      <tr style="border-bottom: 1px solid #e5e7eb;">
        <td style="padding: 16px 20px; font-weight: 700; color: #1e293b; font-size: 15px;">${index + 1}</td>
        <td style="padding: 16px 20px; color: #1e40af; font-weight: 600; font-size: 15px;">${query.company_name}</td>
        <td style="padding: 16px 20px; color: #334155; font-weight: 500; font-size: 15px;">${query.client_name}</td>
        <td style="padding: 16px 20px;"><span ${riskBadge}>${query.risk_level}</span></td>
        <td style="padding: 16px 20px; color: #334155; font-weight: 500; font-size: 15px;">${query.query_type}</td>
        <td style="padding: 16px 20px; color: #64748b; font-size: 14px;">${query.query_date}</td>
        <td style="padding: 16px 20px;">${qaSummary}</td>
        <td style="padding: 16px 20px;">
          <button class="btn btn-sm btn-primary" onclick='viewMLQueryDetails(${queryData})' style="font-size: 13px; padding: 6px 12px; font-weight: 600;">
            <i class="fa-solid fa-eye me-1"></i>View Details
          </button>
        </td>
      </tr>
    `;
  });
  
  tbody.innerHTML = html;
}

// Populate reviews table
function populateMLReviewsTable(reviews) {
  const tbody = document.getElementById('mlReviewsTableBody');
  
  if (reviews.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" class="text-center py-5" style="border: none; color: #94a3b8;">
          <i class="fa-solid fa-inbox fa-3x mb-3" style="opacity: 0.3;"></i>
          <p class="mb-0" style="font-size: 15px;">No risk items found.</p>
        </td>
      </tr>
    `;
    return;
  }
  
  let html = '';
  reviews.forEach((review, index) => {
    const riskBadge = review.risk_level === 'High' 
      ? 'style="background: #dc2626; color: white; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 14px;"' 
      : 'style="background: #f59e0b; color: white; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 14px;"';
    const qaPairs = JSON.parse(review.qa_pairs);
    const qaSummary = qaPairs.length > 0 
      ? `<span style="color: #065f46; font-weight: 600; font-size: 14px;">${qaPairs.length} Question${qaPairs.length > 1 ? 's' : ''}</span>` 
      : '<span style="color: #94a3b8; font-size: 14px;">No Q&A</span>';
    
    // Store data in escaped JSON format for the modal
    const reviewData = JSON.stringify(review).replace(/"/g, '&quot;');
    
    html += `
      <tr style="border-bottom: 1px solid #e5e7eb;">
        <td style="padding: 16px 20px; font-weight: 700; color: #1e293b; font-size: 15px;">${index + 1}</td>
        <td style="padding: 16px 20px; color: #065f46; font-weight: 600; font-size: 15px;">${review.company_name}</td>
        <td style="padding: 16px 20px; color: #334155; font-weight: 500; font-size: 15px;">${review.manager_name}</td>
        <td style="padding: 16px 20px;"><span ${riskBadge}>${review.risk_level}</span></td>
        <td style="padding: 16px 20px; color: #334155; font-weight: 500; font-size: 15px;">${review.review_type}</td>
        <td style="padding: 16px 20px; color: #64748b; font-size: 14px;">${review.review_date}</td>
        <td style="padding: 16px 20px;">${qaSummary}</td>
        <td style="padding: 16px 20px;">
          <button class="btn btn-sm btn-success" onclick='viewMLReviewDetails(${reviewData})' style="font-size: 13px; padding: 6px 12px; font-weight: 600;">
            <i class="fa-solid fa-eye me-1"></i>View Details
          </button>
        </td>
      </tr>
    `;
  });
  
  tbody.innerHTML = html;
}

// Preview Management Letter in Modal
function previewManagementLetter() {
  const riskLevels = document.getElementById('filterMLRiskLevel').value;
  const companyId = document.getElementById('filterMLCompany').value;
  
  const params = new URLSearchParams({
    action: 'get_letter_html',
    risk_levels: riskLevels,
    company_id: companyId
  });
  
  fetch('management_letter_handler.php?' + params)
    .then(response => response.text())
    .then(html => {
      document.getElementById('managementLetterContent').innerHTML = html;
      const modal = new bootstrap.Modal(document.getElementById('managementLetterModal'));
      modal.show();
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error loading management letter preview');
    });
}

// Print Management Letter
function printManagementLetter() {
  const content = document.getElementById('managementLetterContent').innerHTML;
  const printWindow = window.open('', '', 'width=800,height=600');
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Management Letter</title>
      <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        @media print {
          body { padding: 0; }
        }
      </style>
    </head>
    <body>
      ${content}
    </body>
    </html>
  `);
  printWindow.document.close();
  printWindow.focus();
  setTimeout(() => {
    printWindow.print();
    printWindow.close();
  }, 250);
}

// Download Management Letter as PDF
function downloadManagementLetterPDF() {
  const element = document.getElementById('managementLetterContent');
  const riskLevels = document.getElementById('filterMLRiskLevel').value;
  const companyId = document.getElementById('filterMLCompany').value;
  const companyName = document.getElementById('filterMLCompany').selectedOptions[0]?.text || 'All_Companies';
  
  const opt = {
    margin: [15, 15, 15, 15],
    filename: `Management_Letter_${companyName.replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.pdf`,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2, useCORS: true, letterRendering: true },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
  };
  
  // Check if html2pdf is loaded
  if (typeof html2pdf === 'undefined') {
    alert('PDF library is loading. Please try again in a moment.');
    // Load the library
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
    document.head.appendChild(script);
    return;
  }
  
  html2pdf().set(opt).from(element).save();
}

// View Client Query Details
function viewMLQueryDetails(query) {
  const qaPairs = JSON.parse(query.qa_pairs);
  const riskColor = query.risk_level === 'High' ? '#dc2626' : '#f59e0b';
  
  let qaHtml = '';
  if (qaPairs && qaPairs.length > 0) {
    qaPairs.forEach((qa, i) => {
      qaHtml += `
        <div style="margin-bottom: 20px; padding: 15px; background: #f8fafc; border-left: 4px solid #3b82f6; border-radius: 8px;">
          <div style="margin-bottom: 10px;">
            <strong style="color: #1e40af; font-size: 15px;">
              <i class="fa-solid fa-circle-question me-2"></i>Question ${i + 1}:
            </strong>
            <p style="margin: 8px 0 0 0; color: #1e293b; font-size: 15px; line-height: 1.6;">${qa.question}</p>
          </div>
          <div>
            <strong style="color: #059669; font-size: 15px;">
              <i class="fa-solid fa-circle-check me-2"></i>Answer ${i + 1}:
            </strong>
            <p style="margin: 8px 0 0 0; color: #334155; font-size: 15px; line-height: 1.6;">${qa.answer}</p>
          </div>
        </div>
      `;
    });
  } else {
    qaHtml = '<p style="text-align: center; color: #94a3b8; padding: 40px;">No Q&A pairs available.</p>';
  }
  
  const content = `
    <div style="margin-bottom: 25px;">
      <h5 style="color: #1e293b; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 3px solid #e5e7eb;">
        <i class="fa-solid fa-building me-2" style="color: #3b82f6;"></i>Company Information
      </h5>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
        <div>
          <p style="margin: 8px 0;"><strong style="color: #1e40af;">Company:</strong> <span style="color: #334155;">${query.company_name}</span></p>
          <p style="margin: 8px 0;"><strong style="color: #1e40af;">Client Name:</strong> <span style="color: #334155;">${query.client_name}</span></p>
          <p style="margin: 8px 0;"><strong style="color: #1e40af;">Query Type:</strong> <span style="color: #334155;">${query.query_type}</span></p>
        </div>
        <div>
          <p style="margin: 8px 0;"><strong style="color: #1e40af;">Date:</strong> <span style="color: #334155;">${query.query_date}</span></p>
          <p style="margin: 8px 0;"><strong style="color: #1e40af;">Risk Level:</strong> <span style="background: ${riskColor}; color: white; padding: 4px 12px; border-radius: 15px; font-weight: 700; font-size: 13px;">${query.risk_level}</span></p>
        </div>
      </div>
    </div>
    
    <div>
      <h5 style="color: #1e293b; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 3px solid #e5e7eb;">
        <i class="fa-solid fa-comments me-2" style="color: #3b82f6;"></i>Questions & Answers
      </h5>
      ${qaHtml}
    </div>
  `;
  
  document.getElementById('mlDetailsTitle').textContent = 'Client Query Details';
  document.getElementById('mlDetailsContent').innerHTML = content;
  
  const modal = new bootstrap.Modal(document.getElementById('mlItemDetailsModal'));
  modal.show();
}

// View Manager Review Details
function viewMLReviewDetails(review) {
  const qaPairs = JSON.parse(review.qa_pairs);
  const riskColor = review.risk_level === 'High' ? '#dc2626' : '#f59e0b';
  
  let qaHtml = '';
  if (qaPairs && qaPairs.length > 0) {
    qaPairs.forEach((qa, i) => {
      qaHtml += `
        <div style="margin-bottom: 20px; padding: 15px; background: #f0fdf4; border-left: 4px solid #10b981; border-radius: 8px;">
          <div style="margin-bottom: 10px;">
            <strong style="color: #065f46; font-size: 15px;">
              <i class="fa-solid fa-circle-question me-2"></i>Question ${i + 1}:
            </strong>
            <p style="margin: 8px 0 0 0; color: #1e293b; font-size: 15px; line-height: 1.6;">${qa.question}</p>
          </div>
          <div>
            <strong style="color: #1e40af; font-size: 15px;">
              <i class="fa-solid fa-circle-check me-2"></i>Answer ${i + 1}:
            </strong>
            <p style="margin: 8px 0 0 0; color: #334155; font-size: 15px; line-height: 1.6;">${qa.answer}</p>
          </div>
        </div>
      `;
    });
  } else {
    qaHtml = '<p style="text-align: center; color: #94a3b8; padding: 40px;">No Q&A pairs available.</p>';
  }
  
  const content = `
    <div style="margin-bottom: 25px;">
      <h5 style="color: #1e293b; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 3px solid #e5e7eb;">
        <i class="fa-solid fa-building me-2" style="color: #10b981;"></i>Review Information
      </h5>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
        <div>
          <p style="margin: 8px 0;"><strong style="color: #065f46;">Company:</strong> <span style="color: #334155;">${review.company_name}</span></p>
          <p style="margin: 8px 0;"><strong style="color: #065f46;">Manager Name:</strong> <span style="color: #334155;">${review.manager_name}</span></p>
          <p style="margin: 8px 0;"><strong style="color: #065f46;">Review Type:</strong> <span style="color: #334155;">${review.review_type}</span></p>
        </div>
        <div>
          <p style="margin: 8px 0;"><strong style="color: #065f46;">Date:</strong> <span style="color: #334155;">${review.review_date}</span></p>
          <p style="margin: 8px 0;"><strong style="color: #065f46;">Risk Level:</strong> <span style="background: ${riskColor}; color: white; padding: 4px 12px; border-radius: 15px; font-weight: 700; font-size: 13px;">${review.risk_level}</span></p>
        </div>
      </div>
    </div>
    
    <div>
      <h5 style="color: #1e293b; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 3px solid #e5e7eb;">
        <i class="fa-solid fa-clipboard-check me-2" style="color: #10b981;"></i>Questions & Answers
      </h5>
      ${qaHtml}
    </div>
  `;
  
  document.getElementById('mlDetailsTitle').textContent = 'Manager Review Details';
  document.getElementById('mlDetailsContent').innerHTML = content;
  
  const modal = new bootstrap.Modal(document.getElementById('mlItemDetailsModal'));
  modal.show();
}

// ============ END MANAGEMENT LETTER FUNCTIONS ============

// ============ MSIC CODE AUTOCOMPLETE FUNCTIONALITY ============

// Create autocomplete dropdown container
function createAutocompleteContainer(inputElement) {
  let container = inputElement.nextElementSibling;
  if (!container || !container.classList.contains('msic-autocomplete-list')) {
    container = document.createElement('div');
    container.className = 'msic-autocomplete-list';
    container.style.cssText = `
      position: absolute;
      z-index: 1000;
      background: #1a1d29;
      border: 1px solid #0dcaf0;
      border-radius: 8px;
      max-height: 300px;
      overflow-y: auto;
      width: ${inputElement.offsetWidth}px;
      margin-top: 2px;
      box-shadow: 0 4px 12px rgba(13, 202, 240, 0.3);
      display: none;
    `;
    inputElement.parentElement.style.position = 'relative';
    inputElement.parentElement.insertBefore(container, inputElement.nextSibling);
  }
  return container;
}

// Update nature of business field based on selected MSIC codes
function updateNatureOfBusiness(formPrefix) {
  const natureField = document.getElementById(formPrefix + 'nature_of_business');
  if (!natureField) return;
  
  // Collect all MSIC codes and descriptions
  const msicData = [];
  
  for (let i = 1; i <= 3; i++) {
    const codeInput = document.getElementById(formPrefix + 'msic_code_' + i);
    const descInput = document.getElementById(formPrefix + 'msic_desc_' + i);
    
    if (codeInput && descInput && codeInput.value.trim() && descInput.value.trim()) {
      msicData.push({
        code: codeInput.value.trim(),
        description: descInput.value.trim()
      });
    }
  }
  
  // Update nature of business field with all MSIC codes and descriptions
  if (msicData.length > 0) {
    const natureText = msicData.map((item, index) => 
      `${item.code} - ${item.description}`
    ).join('; ');
    natureField.value = natureText;
  } else {
    natureField.value = '';
  }
}

// Setup MSIC autocomplete for an input field
function setupMSICAutocomplete(inputId, descriptionId, displayId) {
  const input = document.getElementById(inputId);
  const descriptionInput = document.getElementById(descriptionId);
  const displayDiv = document.getElementById(displayId);
  
  if (!input) return;
  
  const container = createAutocompleteContainer(input);
  let searchTimeout;
  
  // Determine which form we're in (add or edit)
  const formPrefix = inputId.startsWith('add_') ? 'add_' : 'edit_';
  
  input.addEventListener('input', function() {
    const value = this.value.trim();
    
    if (value.length === 0) {
      container.style.display = 'none';
      descriptionInput.value = '';
      displayDiv.textContent = '';
      updateNatureOfBusiness(formPrefix);
      return;
    }
    
    // Debounce API calls
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      // Search via API
      fetch(`?action=search_msic&query=${encodeURIComponent(value)}`)
        .then(response => response.json())
        .then(matches => {
          if (matches.length === 0) {
            container.style.display = 'none';
            return;
          }
          
          // Build autocomplete list
          container.innerHTML = '';
          matches.forEach(item => {
            const div = document.createElement('div');
            div.style.cssText = `
              padding: 10px 15px;
              cursor: pointer;
              border-bottom: 1px solid rgba(255, 255, 255, 0.1);
              transition: all 0.2s;
            `;
            
            div.innerHTML = `
              <div style="color: #0dcaf0; font-weight: 600; font-size: 0.95rem;">${item.code}</div>
              <div style="color: #adb5bd; font-size: 0.85rem; margin-top: 3px;">${item.description}</div>
            `;
            
            div.addEventListener('mouseenter', function() {
              this.style.background = 'rgba(13, 202, 240, 0.2)';
            });
            
            div.addEventListener('mouseleave', function() {
              this.style.background = 'transparent';
            });
            
            div.addEventListener('click', function() {
              input.value = item.code;
              descriptionInput.value = item.description;
              displayDiv.textContent = item.description;
              displayDiv.style.color = '#0dcaf0';
              container.style.display = 'none';
              
              // Update nature of business field
              updateNatureOfBusiness(formPrefix);
            });
            
            container.appendChild(div);
          });
          
          container.style.display = 'block';
        })
        .catch(error => {
          console.error('MSIC search error:', error);
          container.style.display = 'none';
        });
    }, 300); // 300ms debounce
  });
  
  // Close autocomplete when clicking outside
  document.addEventListener('click', function(e) {
    if (e.target !== input) {
      container.style.display = 'none';
    }
  });
  
  // Handle keyboard navigation
  input.addEventListener('keydown', function(e) {
    const items = container.querySelectorAll('div[style*="padding"]');
    if (items.length === 0) return;
    
    let currentIndex = -1;
    items.forEach((item, index) => {
      if (item.style.background.includes('rgba(13, 202, 240, 0.2)')) {
        currentIndex = index;
      }
    });
    
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (currentIndex < items.length - 1) {
        if (currentIndex >= 0) items[currentIndex].style.background = 'transparent';
        currentIndex++;
        items[currentIndex].style.background = 'rgba(13, 202, 240, 0.2)';
        items[currentIndex].scrollIntoView({ block: 'nearest' });
      }
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (currentIndex > 0) {
        items[currentIndex].style.background = 'transparent';
        currentIndex--;
        items[currentIndex].style.background = 'rgba(13, 202, 240, 0.2)';
        items[currentIndex].scrollIntoView({ block: 'nearest' });
      }
    } else if (e.key === 'Enter' && currentIndex >= 0) {
      e.preventDefault();
      items[currentIndex].click();
    } else if (e.key === 'Escape') {
      container.style.display = 'none';
    }
  });
}

// Initialize autocomplete for all MSIC fields when document is ready
document.addEventListener('DOMContentLoaded', function() {
  // ===== Time Cost: inject UI (button + modals) =====
  try {
    const currentStaffName = (function(){ try { return <?php echo json_encode($current_user['name'] ?? ($current_user['username'] ?? '')); ?>; } catch(e){ return ''; } })();
    const departments = [
      '10-CoForm','11-InvForm','1-Sorting','2-Filing','3-DataEnt','4-Payroll','5-Admin','6-PreAudit','7-Packing','8-Despatch','9-Software','Z ONLEAVE'
    ];
    const companies = <?php echo json_encode(array_map(function($c){ return ['company_id'=>$c['company_id'], 'company_name'=>$c['company_name']]; }, $companies)); ?>;

    const btn = document.createElement('button');
    btn.id = 'timeCostFab';
    btn.className = 'btn btn-info';
    btn.style.cssText = 'position:fixed; right:22px; bottom:22px; z-index:1050; border-radius:26px; box-shadow:0 8px 20px rgba(13,202,240,.35); font-weight:700;';
    btn.innerHTML = '<i class="fa-solid fa-clock me-1"></i> Time Cost';
    btn.onclick = () => new bootstrap.Modal(document.getElementById('timeCostEntryModal')).show();
    document.body.appendChild(btn);

    const modalsHtml = `
<div class="modal fade" id="timeCostListModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content" style="background:#0b1320;color:#e2e8f0;border:1px solid #0dcaf0;">
      <div class="modal-header" style="border-bottom:1px solid rgba(13,202,240,.25)">
        <h5 class="modal-title"><i class="fa-solid fa-clock me-2"></i>Time Cost - List</h5>
        <div class="ms-auto d-flex gap-2">
          <button type="button" class="btn btn-sm btn-primary" id="tcl_add_btn"><i class="fa-solid fa-plus me-1"></i>Add Entry</button>
          <button type="button" class="btn btn-sm btn-outline-info" id="tcl_open_summary"><i class="fa-solid fa-table me-1"></i>Summary</button>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-md-2"><label class="form-label">From</label><input type="date" id="tcl_from" class="form-control" /></div>
          <div class="col-md-2"><label class="form-label">To</label><input type="date" id="tcl_to" class="form-control" /></div>
          <div class="col-md-3"><label class="form-label">Company</label><select id="tcl_company" class="form-select"><option value="">All</option></select></div>
          <div class="col-md-3"><label class="form-label">Job Classification</label><select id="tcl_dept" class="form-select"><option value="">All</option></select></div>
          <div class="col-md-2"><label class="form-label">Staff</label><input type="text" id="tcl_staff" class="form-control" placeholder="Name" /></div>
        </div>
        <div class="d-flex gap-2 mb-3">
          <button class="btn btn-outline-info" id="tcl_refresh"><i class="fa-solid fa-rotate me-1"></i>Refresh</button>
        </div>
        <div class="table-responsive" style="border:1px solid rgba(13,202,240,.2); border-radius:8px;">
          <table class="table table-sm table-dark mb-0">
            <thead>
              <tr>
                <th>Date</th><th>Type</th><th>Doc No.</th><th>Company</th><th>Staff</th><th>Year</th><th>Scope</th><th>Hours</th><th>Time Cost</th><th>Total Cost</th><th>Description</th><th>Description 2</th><th>Action</th>
              </tr>
            </thead>
            <tbody id="tcl_body"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="timeCostEntryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="background:#0b1320;color:#e2e8f0;border:1px solid #0dcaf0;">
      <div class="modal-header" style="border-bottom:1px solid rgba(13,202,240,.25)">
        <h5 class="modal-title"><i class="fa-solid fa-clock me-2"></i>Add Time Cost Entry</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- Header fields tidy row: Staff, Type, Doc, Date -->
        <div class="row g-3 align-items-end mb-3">
          <div class="col-md-8">
            <label class="form-label">Staff Name</label>
            <div class="input-group">
              <input type="text" id="tc_staff_name" class="form-control" placeholder="e.g. John Tan" />
              <button class="btn btn-outline-info" type="button" id="tc_link_me" title="Link to current user"><i class="fa-solid fa-link"></i></button>
              <button class="btn btn-outline-danger" type="button" id="tc_clear_staff" title="Clear"><i class="fa-solid fa-xmark"></i></button>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Date</label>
            <input type="date" id="tc_entry_date" class="form-control" />
          </div>
        </div>

        <!-- Work items table -->
        <div class="card" style="background:rgba(13,202,240,.05);border:1px solid rgba(13,202,240,.25);border-radius:10px;">
          <div class="card-header d-flex justify-content-between align-items-center" style="background:rgba(13,202,240,.12);border-bottom:1px solid rgba(13,202,240,.25)">
            <h6 class="mb-0 text-info"><i class="fa-solid fa-list-check me-2"></i>Work Items</h6>
            <button class="btn btn-sm btn-primary" id="tc_add_row_btn"><i class="fa-solid fa-plus me-1"></i>Add Row</button>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-dark align-middle mb-0">
                <thead>
                  <tr>
                    <th style="min-width:220px;">Company</th>
                    <th>Work Done</th>
                    <th style="width:110px;">F. Year</th>
                    <th style="width:110px;">Hours</th>
                    <th style="width:130px;">Unit Cost</th>
                    <th style="width:70px;">Actions</th>
                  </tr>
                </thead>
                <tbody id="tc_items_body">
                  <!-- rows injected by JS -->
                </tbody>
              </table>
            </div>
            <div class="p-2 text-muted small" style="border-top:1px solid rgba(13,202,240,.12)">
              Tip: Leave "Unit Cost" empty to auto-use your effective hourly rate. Required fields will prompt if missing.
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer" style="border-top:1px solid rgba(13,202,240,.25)">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="tc_save_btn"><i class="fa-solid fa-save me-1"></i>Save Entry</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="timeCostSummaryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content" style="background:#0b1320;color:#e2e8f0;border:1px solid #0dcaf0;">
      <div class="modal-header" style="border-bottom:1px solid rgba(13,202,240,.25)">
        <h5 class="modal-title"><i class="fa-solid fa-table me-2"></i>Time Cost Summary</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-md-3"><label class="form-label">From</label><input type="date" id="tcs_from" class="form-control" /></div>
          <div class="col-md-3"><label class="form-label">To</label><input type="date" id="tcs_to" class="form-control" /></div>
          <div class="col-md-3"><label class="form-label">Job Classification</label><select id="tcs_dept" class="form-select"></select></div>
          <div class="col-md-3"><label class="form-label">Financial Year</label><input type="number" id="tcs_year" class="form-control" min="2000" max="2099" /></div>
        </div>
        <div class="table-responsive" style="border:1px solid rgba(13,202,240,.2); border-radius:8px;">
          <table class="table table-sm table-dark mb-0">
            <thead>
              <tr>
                <th>Date</th><th>Type</th><th>Doc No.</th><th>Company</th><th>Staff</th><th>Year</th><th>Scope</th><th>In/Out Qty</th><th>Unit </th><th>Total Cost</th><th>B/F Qty</th><th>B/F Cost</th>
              </tr>
            </thead>
            <tbody id="tcs_body"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer" style="border-top:1px solid rgba(13,202,240,.25)">
        <button type="button" class="btn btn-outline-info" id="tcs_refresh"><i class="fa-solid fa-rotate me-1"></i>Refresh</button>
        <div class="ms-auto text-end"><span class="me-3">BF Qty: <strong id="tcs_bf_qty">0</strong></span><span>BF Cost: <strong id="tcs_bf_cost">0.00</strong></span></div>
      </div>
    </div>
  </div>
</div>`;

    const container = document.createElement('div');
    container.innerHTML = modalsHtml;
    document.body.appendChild(container);

    // Prefill defaults
    const today = new Date().toISOString().slice(0,10);
    document.getElementById('tc_entry_date').value = today;
    document.getElementById('tc_staff_name').value = currentStaffName || '';
        document.getElementById('tcs_from').value = today.slice(0,8) + '01';
    document.getElementById('tcs_to').value = today;
    document.getElementById('tcs_year').value = new Date().getFullYear();

    // Populate department selects (summary only)
    const deptSummary = document.getElementById('tcs_dept');
    departments.forEach(d => {
      const o2 = document.createElement('option'); o2.value = d; o2.textContent = d; deptSummary.appendChild(o2);
    });

    // Populate fixed hour options: default 0.00 (disabled), then 0.25..10.00
    // Hours options are now per-row (see addTimeCostRow)

    // Populate company select
    // Company select is now per-row (see addTimeCostRow)

    // Populate list filters
    document.getElementById('tcl_from').value = today.slice(0,8) + '01';
    document.getElementById('tcl_to').value = today;
    const tclDept = document.getElementById('tcl_dept');
    departments.forEach(d => { const o = document.createElement('option'); o.value = d; o.textContent = d; tclDept.appendChild(o); });
    const tclCompany = document.getElementById('tcl_company');
    companies.forEach(c => { const o = document.createElement('option'); o.value = String(c.company_id); o.textContent = c.company_name; tclCompany.appendChild(o); });

    // Wire buttons
    // Summary modal removed; summary now embedded on Time Cost page
    document.getElementById('tcs_refresh').addEventListener('click', loadTimeCostSummary);
    document.getElementById('tc_save_btn').addEventListener('click', submitTimeCostEntry);
    // Add row / clear helpers
    document.getElementById('tc_add_row_btn').addEventListener('click', addTimeCostRow);
    document.getElementById('tc_clear_staff').addEventListener('click', () => { const i=document.getElementById('tc_staff_name'); if(i) i.value=''; });
    // type and doc no controls removed
    document.getElementById('tc_link_me').addEventListener('click', () => { const i=document.getElementById('tc_staff_name'); if(i) i.value=(currentStaffName||''); });
    // Ensure one initial row exists
    addTimeCostRow();

    // Wire list modal controls
    document.getElementById('tcl_add_btn').addEventListener('click', function(){
      new bootstrap.Modal(document.getElementById('timeCostEntryModal')).show();
    });
    document.getElementById('tcl_open_summary').addEventListener('click', function(){
      new bootstrap.Modal(document.getElementById('timeCostSummaryModal')).show();
      loadTimeCostSummary();
    });
    document.getElementById('tcl_refresh').addEventListener('click', loadTimeCostList);

    // Add top-level sidebar link: Time Cost (after "Dashboard") and keep Summary under user context
    try {
      const sidebarLinks = Array.from(document.querySelectorAll('a'));
      const dashboardLink = sidebarLinks.find(a => a.textContent && a.textContent.trim().toLowerCase() === 'dashboard');
      if (dashboardLink) {
        const existingTimeCost = sidebarLinks.find(a => a.textContent && a.textContent.trim().toLowerCase() === 'time cost');
        if (!existingTimeCost) {
          const link = document.createElement('a');
          link.href = '#';
          link.innerHTML = '<i class="fa-solid fa-clock"></i><span>Time Cost</span>';
          // Navigate to the Time Cost page instead of opening the modal
          link.addEventListener('click', function(e){
            e.preventDefault();
            try {
              if (typeof showPage === 'function') {
                showPage('timeCost');
              } else {
                // Fallback: navigate to the index page (will reload)
                window.location.href = window.location.pathname + '?page=timeCost';
              }
              // Attempt to load summary if available
              if (typeof loadTimeCostSummary === 'function') loadTimeCostSummary();
            } catch (err) { console.error('Time Cost nav error', err); }
          });
          dashboardLink.insertAdjacentElement('afterend', link);
        }
      }
    } catch(sidebarErr) { /* ignore */ }

    function submitTimeCostEntry() {
  const entryDate = document.getElementById('tc_entry_date').value;
  const staffName = document.getElementById('tc_staff_name').value.trim();

  if (!staffName) { alert('Please fill Staff Name'); return; }
  if (!entryDate) { alert('Please pick a Date'); return; }

  const rows = Array.from(document.querySelectorAll('#tc_items_body tr'));
  if (rows.length === 0) { alert('Please add at least one Work Item row'); return; }

  // Disable save button to avoid double submit
  const saveBtn = document.getElementById('tc_save_btn');
  const prevHtml = saveBtn.innerHTML; saveBtn.disabled = true; saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

  const makeReq = (row) => {
    const companyId = row.querySelector('.tc-item-company')?.value || '';
    const companyName = (companies.find(c => String(c.company_id) === String(companyId)) || {}).company_name || '';
    const workDesc = row.querySelector('.tc-item-desc')?.value.trim() || '';
    const finYearVal = row.querySelector('.tc-item-year')?.value;
    const finYear = finYearVal ? parseInt(finYearVal, 10) : new Date(entryDate).getFullYear();
    const hours = row.querySelector('.tc-item-hours')?.value;
    const unitCostVal = row.querySelector('.tc-item-rate')?.value.trim();

    if (!companyId) { throw new Error('Company is required in each row'); }
    if (!workDesc) { throw new Error('Work Done is required in each row'); }
    if (!hours) { throw new Error('Hours is required in each row'); }

    const params = new URLSearchParams();
    params.set('action', 'create_entry');
    params.set('entry_date', entryDate);
    // type and doc no removed; backend will use defaults
    params.set('staff_name', staffName);
    params.set('financial_year', String(finYear));
    params.set('company_id', String(companyId));
    params.set('company_name', companyName);
    params.set('department_code', '');
    params.set('hours', String(hours));
    if (unitCostVal !== '') { params.set('unit_cost', unitCostVal); }
    params.set('description', workDesc);
    params.set('description2', '');
    params.set('created_by', String(<?php echo (int)($_SESSION['user_id'] ?? 0); ?>));

    return fetch('time_cost_handler.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params.toString()
    }).then(r => r.json());
  };

  Promise.all(rows.map(makeReq))
    .then(results => {
      const failed = results.find(r => !r.ok);
      if (failed) { throw new Error(failed.message || 'One or more rows failed to save'); }
      const total = results.reduce((acc, r) => acc + (Number(r.data?.total_cost || 0)), 0);
      alert('Saved ' + results.length + ' row(s). Total Cost: ' + total.toFixed(2));
      const modal = bootstrap.Modal.getInstance(document.getElementById('timeCostEntryModal'));
      if (modal) modal.hide();
    })
    .catch(err => { console.error(err); alert(err.message || 'Error saving entries'); })
    .finally(() => { saveBtn.disabled = false; saveBtn.innerHTML = prevHtml; });
}

function addTimeCostRow() {
  const tbody = document.getElementById('tc_items_body');
  if (!tbody) return;
  const tr = document.createElement('tr');

  // Build options
  const companyOpts = companies.map(c => `<option value="${c.company_id}">${c.company_name}</option>`).join('');
  let hoursOpts = '<option value="" disabled selected hidden>0.00</option>';
  for (let q = 1; q <= 40; q++) { const val = (q * 0.25).toFixed(2); hoursOpts += `<option value="${val}">${val}</option>`; }

  tr.innerHTML = `
    <td>
      <select class="form-select tc-item-company">${companyOpts}</select>
    </td>
    <td>
      <input type="text" class="form-control tc-item-desc" placeholder="Work done..." />
    </td>
    <td>
      <input type="number" class="form-control tc-item-year" placeholder="${new Date().getFullYear()}" min="2000" max="2099" />
    </td>
    <td>
      <select class="form-select tc-item-hours">${hoursOpts}</select>
    </td>
    <td>
      <input type="number" step="0.01" class="form-control tc-item-rate" placeholder="auto" />
    </td>
    <td class="text-center">
      <button type="button" class="btn btn-sm btn-outline-danger" title="Remove" onclick="deleteTimeCostRow(this)"><i class="fa-solid fa-trash"></i></button>
    </td>`;
  tbody.appendChild(tr);
}

function deleteTimeCostRow(btn) {
  const tr = btn.closest('tr');
  if (tr) tr.remove();
}

    function loadTimeCostSummary() {
      const params = new URLSearchParams({
        action: 'summary',
        from: document.getElementById('tcs_from').value,
        to: document.getElementById('tcs_to').value,
        department_code: document.getElementById('tcs_dept').value,
        financial_year: document.getElementById('tcs_year').value
      });
      fetch('time_cost_handler.php?' + params.toString())
        .then(r => r.json())
        .then(resp => {
          if (!resp.ok) { alert(resp.message || 'Load failed'); return; }
          const tbody = document.getElementById('tcs_body');
          tbody.innerHTML = '';
          resp.data.rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>${row.entry_date}</td>
              <td>${row.entry_type}</td>
              <td>${row.doc_no ?? ''}</td>
              <td>${row.company_name ?? ''}</td>
              <td>${row.staff_name ?? ''}</td>
              <td>${row.financial_year}</td>
              <td>${row.department_code}</td>
              <td>${Number(row.in_out_qty).toFixed(2)}</td>
              <td>${Number(row.unit_cost).toFixed(2)}</td>
              <td>${Number(row.total_cost).toFixed(2)}</td>
              <td>${Number(row.bf_qty).toFixed(2)}</td>
              <td>${Number(row.bf_cost).toFixed(2)}</td>`;
            tbody.appendChild(tr);
          });
          document.getElementById('tcs_bf_qty').textContent = Number(resp.data.bf_qty).toFixed(2);
          document.getElementById('tcs_bf_cost').textContent = Number(resp.data.bf_cost).toFixed(2);
        })
        .catch(err => { console.error(err); alert('Error loading summary'); });
    }
    function loadTimeCostList() {
      const params = new URLSearchParams({
        action: 'summary',
        from: document.getElementById('tcl_from').value,
        to: document.getElementById('tcl_to').value,
        department_code: document.getElementById('tcl_dept').value,
        company_id: document.getElementById('tcl_company').value,
        staff_name: document.getElementById('tcl_staff').value
      });
      fetch('time_cost_handler.php?' + params.toString())
        .then(r => r.json())
        .then(resp => {
          if (!resp.ok) { alert(resp.message || 'Load failed'); return; }
          const tbody = document.getElementById('tcl_body');
          tbody.innerHTML = '';
          resp.data.rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>${row.entry_date}</td>
              <td>${row.entry_type}</td>
              <td>${row.doc_no ?? ''}</td>
              <td>${row.company_name ?? ''}</td>
              <td>${row.staff_name ?? ''}</td>
              <td>${row.financial_year}</td>
              <td>${row.department_code}</td>
              <td>${Number(row.in_out_qty).toFixed(2)}</td>
              <td>${Number(row.unit_cost).toFixed(2)}</td>
              <td>${Number(row.total_cost).toFixed(2)}</td>
              <td>${row.description ?? ''}</td>
              <td>${row.description2 ?? ''}</td>`;
            tbody.appendChild(tr);
          });
        })
        .catch(err => { console.error(err); alert('Error loading list'); });
    }
    // Expose functions for sidebar link
    window.loadTimeCostSummary = loadTimeCostSummary;
    window.openTimeCostSummary = function(){
      new bootstrap.Modal(document.getElementById('timeCostSummaryModal')).show();
      loadTimeCostSummary();
    };
  } catch (e) { console.error('Time Cost UI error', e); }

  // Add form MSIC autocomplete
  setupMSICAutocomplete('add_msic_code_1', 'add_msic_desc_1', 'add_msic_desc_display_1');
  setupMSICAutocomplete('add_msic_code_2', 'add_msic_desc_2', 'add_msic_desc_display_2');
  setupMSICAutocomplete('add_msic_code_3', 'add_msic_desc_3', 'add_msic_desc_display_3');
  
  // Edit form MSIC autocomplete
  setupMSICAutocomplete('edit_msic_code_1', 'edit_msic_desc_1', 'edit_msic_desc_display_1');
  setupMSICAutocomplete('edit_msic_code_2', 'edit_msic_desc_2', 'edit_msic_desc_display_2');
  setupMSICAutocomplete('edit_msic_code_3', 'edit_msic_desc_3', 'edit_msic_desc_display_3');
});

// Update form submission handlers to combine MSIC codes
document.getElementById('addCompanyForm')?.addEventListener('submit', function(e) {
  // Combine MSIC codes for backward compatibility
  const msic1 = document.getElementById('add_msic_code_1').value.trim();
  const msic2 = document.getElementById('add_msic_code_2').value.trim();
  const msic3 = document.getElementById('add_msic_code_3').value.trim();
  
  const combined = [msic1, msic2, msic3].filter(code => code).join(', ');
  document.getElementById('add_msic_code').value = combined;
});

document.getElementById('editCompanyForm')?.addEventListener('submit', function(e) {
  // Combine MSIC codes for backward compatibility
  const msic1 = document.getElementById('edit_msic_code_1').value.trim();
  const msic2 = document.getElementById('edit_msic_code_2').value.trim();
  const msic3 = document.getElementById('edit_msic_code_3').value.trim();
  
  const combined = [msic1, msic2, msic3].filter(code => code).join(', ');
  document.getElementById('edit_msic_code').value = combined;
});

// ============ END MSIC CODE AUTOCOMPLETE FUNCTIONALITY ============
</script>
</body>
</html>
