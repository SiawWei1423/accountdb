<?php
require_once __DIR__ . '/db_connection.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

function json_response($ok, $data = null, $message = '') {
    echo json_encode(['ok' => $ok, 'message' => $message, 'data' => $data]);
    exit;
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
    $deptCode = $payload['department_code'] ?? '';
    $hours = isset($payload['hours']) ? (float)$payload['hours'] : 0.0;
    $unitCost = isset($payload['unit_cost']) ? (float)$payload['unit_cost'] : null;
    $description = $payload['description'] ?? null;
        $inOut = $payload['in_out'] ?? 'In';
    $createdBy = isset($payload['created_by']) ? (int)$payload['created_by'] : 0;

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

    $sql = "SELECT entry_date, entry_type, doc_no, staff_name, company_name, financial_year, department_code, description, '' AS description2, hours AS in_out_qty, unit_cost, total_cost, in_out FROM time_cost_entries WHERE entry_date BETWEEN ? AND ?";
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


