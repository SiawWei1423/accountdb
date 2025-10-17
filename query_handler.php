<?php
// Prevent any output before JSON
ob_start();

session_start();

// Set JSON header before anything else
header('Content-Type: application/json');

// Include database connection
require_once 'db_connection.php';

// Check for connection errors
if (!isset($conn) || $conn->connect_error) {
    ob_end_clean();
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed: ' . ($conn->connect_error ?? 'Unknown error')
    ]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Clear any buffered output
ob_end_clean();

// Wrap everything in try-catch to handle unexpected errors
try {
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];

// Add New Query
if ($action === 'add_query') {
    $company_id = $_POST['company_id'] ?? '';
    $client_name = $_POST['client_name'] ?? '';
    $qa_pairs_json = $_POST['qa_pairs'] ?? '';
    $query_type = $_POST['query_type'] ?? '';
    $risk_level = $_POST['risk_level'] ?? '';
    $query_date = $_POST['query_date'] ?? '';
    $ml_enabled = isset($_POST['ml_enabled']) ? 1 : 0;
    
    // Validate Q&A pairs
    if (empty($qa_pairs_json)) {
        echo json_encode(['success' => false, 'message' => 'Please provide at least one question!']);
        exit;
    }
    
    // Handle file uploads
    $photo_url = null;
    $voice_url = null;
    $document_url = null;
    
    // Upload photo
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photo_name = 'query_photo_' . time() . '_' . rand(1000, 9999) . '.' . pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photo_path = 'uploads/queries/' . $photo_name;
        
        if (!is_dir('uploads/queries')) {
            mkdir('uploads/queries', 0777, true);
        }
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
            $photo_url = $photo_path;
        }
    }
    
    // Upload voice
    if (isset($_FILES['voice']) && $_FILES['voice']['error'] === UPLOAD_ERR_OK) {
        $voice_name = 'query_voice_' . time() . '_' . rand(1000, 9999) . '.' . pathinfo($_FILES['voice']['name'], PATHINFO_EXTENSION);
        $voice_path = 'uploads/queries/' . $voice_name;
        
        if (!is_dir('uploads/queries')) {
            mkdir('uploads/queries', 0777, true);
        }
        
        if (move_uploaded_file($_FILES['voice']['tmp_name'], $voice_path)) {
            $voice_url = $voice_path;
        }
    }
    
    // Upload document
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $document_name = 'query_doc_' . time() . '_' . rand(1000, 9999) . '.' . pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        $document_path = 'uploads/queries/' . $document_name;
        
        if (!is_dir('uploads/queries')) {
            mkdir('uploads/queries', 0777, true);
        }
        
        if (move_uploaded_file($_FILES['document']['tmp_name'], $document_path)) {
            $document_url = $document_path;
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO client_queries (company_id, client_name, qa_pairs, query_type, risk_level, ml_enabled, photo_url, voice_url, document_url, query_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssissssi", $company_id, $client_name, $qa_pairs_json, $query_type, $risk_level, $ml_enabled, $photo_url, $voice_url, $document_url, $query_date, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Query added successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add query: ' . $stmt->error]);
    }
    $stmt->close();
}

// Get All Queries
elseif ($action === 'get_queries') {
    // First check if client_queries table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'client_queries'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Table client_queries does not exist. Please run setup_client_queries_table.php to create it.'
        ]);
        exit;
    }
    
    $sql = "SELECT cq.*, c.company_name, u.full_name as creator_name 
            FROM client_queries cq
            LEFT JOIN company c ON cq.company_id = c.company_id
            LEFT JOIN user u ON cq.created_by = u.user_id
            ORDER BY cq.created_at DESC";
    
    $result = $conn->query($sql);
    $queries = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $queries[] = $row;
        }
        echo json_encode(['success' => true, 'queries' => $queries]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Database query failed: ' . $conn->error
        ]);
    }
}

// Update Query Status
elseif ($action === 'update_status') {
    $query_id = $_POST['query_id'] ?? '';
    $status = $_POST['status'] ?? '';
    
    $stmt = $conn->prepare("UPDATE client_queries SET status = ? WHERE query_id = ?");
    $stmt->bind_param("si", $status, $query_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
    $stmt->close();
}

// Update Query Answer
elseif ($action === 'update_answer') {
    $query_id = $_POST['query_id'] ?? '';
    $answer = $_POST['answer'] ?? '';
    
    $stmt = $conn->prepare("UPDATE client_queries SET answer = ?, status = 'Resolved' WHERE query_id = ?");
    $stmt->bind_param("si", $answer, $query_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Answer updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update answer']);
    }
    $stmt->close();
}

// Delete Query
elseif ($action === 'delete_query') {
    $query_id = $_POST['query_id'] ?? '';
    
    // Get file paths before deleting
    $stmt = $conn->prepare("SELECT photo_url, voice_url, document_url FROM client_queries WHERE query_id = ?");
    $stmt->bind_param("i", $query_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $query = $result->fetch_assoc();
    
    // Delete files if they exist
    if ($query) {
        if ($query['photo_url'] && file_exists($query['photo_url'])) {
            unlink($query['photo_url']);
        }
        if ($query['voice_url'] && file_exists($query['voice_url'])) {
            unlink($query['voice_url']);
        }
        if ($query['document_url'] && file_exists($query['document_url'])) {
            unlink($query['document_url']);
        }
    }
    $stmt->close();
    
    // Delete query record
    $stmt = $conn->prepare("DELETE FROM client_queries WHERE query_id = ?");
    $stmt->bind_param("i", $query_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Query deleted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete query']);
    }
    $stmt->close();
}

else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

} catch (Exception $e) {
    // Catch any unexpected errors and return as JSON
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage(),
        'error_details' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    // Catch PHP 7+ errors
    echo json_encode([
        'success' => false, 
        'message' => 'Fatal error: ' . $e->getMessage(),
        'error_details' => $e->getTraceAsString()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>
