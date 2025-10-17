<?php
require_once __DIR__ . '/db_connection.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

function json_response($ok, $data = null, $message = '') {
    echo json_encode(['ok' => $ok, 'message' => $message, 'data' => $data]);
    exit;
}

/**
 * Ensure the required tables exist for the Time Cost feature.
 * This avoids manual execution of the create_time_cost_tables.sql file.
 */
function ensure_time_cost_schema(mysqli $conn): void {
    // time_departments
    $conn->query(
        "CREATE TABLE IF NOT EXISTS time_departments (
            dept_id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) NOT NULL UNIQUE,
            description VARCHAR(100) NOT NULL,
            parent_code VARCHAR(20) DEFAULT NULL,
            INDEX idx_parent_code (parent_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // user_hourly_rates
    $conn->query(
        "CREATE TABLE IF NOT EXISTS user_hourly_rates (
            rate_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            effective_from DATE NOT NULL,
            hourly_rate DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_effective (user_id, effective_from)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // time_cost_entries
    $conn->query(
        "CREATE TABLE IF NOT EXISTS time_cost_entries (
            entry_id INT AUTO_INCREMENT PRIMARY KEY,
            entry_date DATE NOT NULL,
            entry_type ENUM('PI','ADJ') DEFAULT 'PI',
            doc_no VARCHAR(50) DEFAULT NULL,
            company_id INT DEFAULT NULL,
            company_name VARCHAR(255) DEFAULT NULL,
            staff_name VARCHAR(255) NOT NULL,
            user_id INT DEFAULT NULL,
            financial_year INT NOT NULL,
            department_code VARCHAR(20) NOT NULL,
            hours DECIMAL(7,2) NOT NULL,
            unit_cost DECIMAL(10,2) NOT NULL,
            total_cost DECIMAL(12,2) NOT NULL,
            description TEXT,
            in_out ENUM('In','Out') DEFAULT 'In',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_date (entry_date),
            INDEX idx_year (financial_year),
            INDEX idx_dept (department_code),
            INDEX idx_company (company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Add FK if not already present (MySQL doesn't support IF NOT EXISTS for FKs; wrap in try/catch via @)
    // Ensure a department exists to satisfy NOT NULL+FK usage when UI omits it
    $seedSql = "INSERT IGNORE INTO time_departments (code, description) VALUES
        ('10-CoForm','10-Company Formation'),
        ('11-InvForm','11-Invoice Form'),
        ('1-Sorting','1-Sorting'),
        ('2-Filing','2-Filing'),
        ('3-DataEnt','3-Data Entry'),
        ('4-Payroll','4-Payroll'),
        ('5-Admin','5-Admin'),
        ('6-PreAudit','6-PreAudit'),
        ('7-Packing','7-Packing'),
        ('8-Despatch','8-Despatch'),
        ('9-Software','9-Software'),
        ('Z ONLEAVE','On Leave (non-productive)')";
    $conn->query($seedSql);

    // Attempt to add FK only if not present
    $fkCheck = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'time_cost_entries' AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
    $hasDeptFK = false;
    if ($fkCheck) {
        while ($row = $fkCheck->fetch_assoc()) {
            if (stripos($row['CONSTRAINT_NAME'], 'dept') !== false || stripos($row['CONSTRAINT_NAME'], 'department') !== false) {
                $hasDeptFK = true; break;
            }
        }
        $fkCheck->close();
    }
    if (!$hasDeptFK) {
        // Add a named FK; ignore if it fails (e.g. already exists under another name)
        @$conn->query("ALTER TABLE time_cost_entries ADD CONSTRAINT fk_time_dept_code FOREIGN KEY (department_code) REFERENCES time_departments(code)");
    }
}

function get_effective_rate(mysqli $conn, $userId, $entryDate) {
    if (!$userId) return null;
    $stmt = $conn->prepare("SELECT hourly_rate FROM user_hourly_rates WHERE user_id = ? AND effective_from <= ? ORDER BY effective_from DESC LIMIT 1");
    $stmt->bind_param('is', $userId, $entryDate);
    $stmt->execute();
    $stmt->bind_result($rate);
    if ($stmt->fetch()) {
        $stmt->close();
        return (float)$rate;
    }
    $stmt->close();
    return null;
}

function create_time_entry(mysqli $conn, $payload) {
    $entryDate = $payload['entry_date'] ?? date('Y-m-d');
    $entryType = $payload['entry_type'] ?? 'PI';
    $docNo = $payload['doc_no'] ?? null;
    $companyId = isset($payload['company_id']) ? (int)$payload['company_id'] : null;
    $companyName = $payload['company_name'] ?? null;
    $staffName = $payload['staff_name'] ?? '';
    $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
    $financialYear = isset($payload['financial_year']) ? (int)$payload['financial_year'] : (int)date('Y');
    $deptCode = trim($payload['department_code'] ?? '');
    $hours = isset($payload['hours']) ? (float)$payload['hours'] : 0.0;
    $unitCost = isset($payload['unit_cost']) ? (float)$payload['unit_cost'] : null;
    $description = $payload['description'] ?? null;
        $inOut = $payload['in_out'] ?? 'In';
    $createdBy = isset($payload['created_by']) ? (int)$payload['created_by'] : 0;

    // Basic validation
    if (!$staffName) {
        json_response(false, null, 'Staff name is required');
    }
    if ($hours <= 0) {
        json_response(false, null, 'Hours must be greater than zero');
    }

    // Default department when UI omits it; use a seeded code that always exists
    if ($deptCode === '') {
        $deptCode = '3-DataEnt';
    }

    if ($unitCost === null) {
        $rate = get_effective_rate($conn, $userId, $entryDate);
        if ($rate === null) {
            json_response(false, null, 'No hourly rate found for user and date; provide unit_cost or set a rate.');
        }
        $unitCost = $rate;
    }

    $totalCost = round($hours * $unitCost, 2);

    $sql = "INSERT INTO time_cost_entries (entry_date, entry_type, doc_no, company_id, company_name, staff_name, user_id, financial_year, department_code, hours, unit_cost, total_cost, description, in_out, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $logEntry = [
            'ts' => date('c'),
            'error' => $conn->error,
            'payload' => $payload,
            'sql' => $sql
        ];
        @file_put_contents(__DIR__ . '/time_cost_errors.log', json_encode($logEntry) . PHP_EOL, FILE_APPEND | LOCK_EX);
        $includeDetail = isset($_GET['debug']) && $_GET['debug'] == '1';
        $msg = 'Insert prepare failed: ' . $conn->error;
        if ($includeDetail) {
            json_response(false, ['sql_error' => $conn->error, 'payload' => $payload], $msg);
        }
        json_response(false, null, $msg);
    }

    $stmt->bind_param(
        'sssissiisdddssi',
        $entryDate,
        $entryType,
        $docNo,
        $companyId,
        $companyName,
        $staffName,
        $userId,
        $financialYear,
        $deptCode,
        $hours,
        $unitCost,
        $totalCost,
        $description,
        $inOut,
        $createdBy
    );
    if (!$stmt->execute()) {
        // Log detailed error + payload to a file for debugging
        $logEntry = [
            'ts' => date('c'),
            'error' => $stmt->error,
            'payload' => $payload,
            'sql' => $sql
        ];
        @file_put_contents(__DIR__ . '/time_cost_errors.log', json_encode($logEntry) . PHP_EOL, FILE_APPEND | LOCK_EX);

        // If debug=1 is present, include the DB error in the JSON response for debugging
        $includeDetail = isset($_GET['debug']) && $_GET['debug'] == '1';
        $msg = 'Insert failed: ' . $stmt->error;
        if ($includeDetail) {
            json_response(false, ['sql_error' => $stmt->error, 'payload' => $payload], $msg);
        }

        json_response(false, null, $msg);
    }
    $id = $stmt->insert_id;
    $stmt->close();
    json_response(true, ['entry_id' => $id, 'total_cost' => $totalCost], 'Entry created');
}

function delete_time_entry(mysqli $conn, $entryId) {
    $entryId = (int)$entryId;
    
    $sql = "DELETE FROM time_cost_entries WHERE entry_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_response(false, null, 'Delete prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $entryId);
    if (!$stmt->execute()) {
        json_response(false, null, 'Delete failed: ' . $stmt->error);
    }
    
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected > 0) {
        json_response(true, null, 'Entry deleted successfully');
    } else {
        json_response(false, null, 'No entry found with the specified ID');
    }
}

function list_summary(mysqli $conn, $params) {
    $from = $params['from'] ?? date('Y-01-01');
    $to = $params['to'] ?? date('Y-m-d');
    $dept = $params['department_code'] ?? null;
    $staff = $params['staff_name'] ?? null;
    $year = isset($params['financial_year']) ? (int)$params['financial_year'] : null;
    $companyId = isset($params['company_id']) ? (int)$params['company_id'] : null;

    $sql = "SELECT entry_id, entry_date, entry_type, doc_no, staff_name, company_name, financial_year, department_code, description, '' AS description2, hours AS in_out_qty, unit_cost, total_cost, in_out FROM time_cost_entries WHERE entry_date BETWEEN ? AND ?";
    $types = 'ss';
    $vals = [$from, $to];
    if ($dept) { $sql .= " AND department_code = ?"; $types .= 's'; $vals[] = $dept; }
    if ($staff) { $sql .= " AND staff_name = ?"; $types .= 's'; $vals[] = $staff; }
    if ($year) { $sql .= " AND financial_year = ?"; $types .= 'i'; $vals[] = $year; }
    if ($companyId) { $sql .= " AND company_id = ?"; $types .= 'i'; $vals[] = $companyId; }
    // When called from the Summary modal (no company_id param), restrict to current user only
    $restrictUser = !isset($params['company_id']) || $params['company_id'] === '';
    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($restrictUser && $currentUserId) { $sql .= " AND user_id = ?"; $types .= 'i'; $vals[] = $currentUserId; }
    $sql .= " ORDER BY entry_date ASC, entry_id ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_response(false, null, 'Query prepare failed: ' . $conn->error);
    }
    // bind_param requires references; build the arg list dynamically
    $bindParams = [];
    $bindParams[] = &$types;
    foreach ($vals as $i => $v) {
        $bindParams[] = &$vals[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    if (!$stmt->execute()) {
        json_response(false, null, 'Query failed: ' . $stmt->error);
    }
    $res = $stmt->get_result();

    $rows = [];
    $balQty = 0.0;
    $balCost = 0.0;
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $qty = (float)$r['in_out_qty'];
            $sign = ($r['in_out'] === 'Out') ? -1.0 : 1.0;
            $balQty += $sign * $qty;
            $balCost += $sign * (float)$r['total_cost'];
            $r['bf_qty'] = round($balQty, 2);
            $r['bf_cost'] = round($balCost, 2);
            $rows[] = $r;
        }
    } else {
        // Fallback when mysqlnd is not available
        $stmt->bind_result(
            $entry_id,
            $entry_date,
            $entry_type,
            $doc_no,
            $staff_name,
            $company_name,
            $financial_year,
            $department_code,
            $description,
            $description2,
            $in_out_qty,
            $unit_cost,
            $total_cost,
            $in_out
        );
        while ($stmt->fetch()) {
            $qty = (float)$in_out_qty;
            $sign = ($in_out === 'Out') ? -1.0 : 1.0;
            $balQty += $sign * $qty;
            $balCost += $sign * (float)$total_cost;
            $rows[] = [
                'entry_id' => (int)$entry_id,
                'entry_date' => $entry_date,
                'entry_type' => $entry_type,
                'doc_no' => $doc_no,
                'staff_name' => $staff_name,
                'company_name' => $company_name,
                'financial_year' => (int)$financial_year,
                'department_code' => $department_code,
                'description' => $description,
                'description2' => $description2,
                'in_out_qty' => (float)$in_out_qty,
                'unit_cost' => (float)$unit_cost,
                'total_cost' => (float)$total_cost,
                'in_out' => $in_out,
                'bf_qty' => round($balQty, 2),
                'bf_cost' => round($balCost, 2)
            ];
        }
    }
    $stmt->close();
    json_response(true, ['from' => $from, 'to' => $to, 'rows' => $rows, 'bf_qty' => round($balQty, 2), 'bf_cost' => round($balCost, 2)]);
}

// Auto-create schema on first use
ensure_time_cost_schema($conn);

$action = $_GET['action'] ?? $_POST['action'] ?? 'ping';

switch ($action) {
    case 'ping':
        json_response(true, ['time' => time()], 'ok');
        break;
    case 'create_entry':
        create_time_entry($conn, $_POST + $_GET);
        break;
    case 'delete_entry':
        if (isset($_POST['entry_id'])) {
            delete_time_entry($conn, $_POST['entry_id']);
        } else {
            json_response(false, null, 'Entry ID is required for deletion');
        }
        break;
    case 'summary':
        list_summary($conn, $_GET + $_POST);
        break;
    default:
        json_response(false, null, 'Unknown action');
}


