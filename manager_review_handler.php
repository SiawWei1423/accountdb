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

// Add New Review
if ($action === 'add_review') {
    $company_id = $_POST['company_id'] ?? '';
    $manager_name = $_POST['manager_name'] ?? '';
    $qa_pairs_json = $_POST['qa_pairs'] ?? '';
    $review_type = $_POST['review_type'] ?? '';
    $risk_level = $_POST['risk_level'] ?? '';
    $review_date = $_POST['review_date'] ?? '';
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
        $photo_name = 'review_photo_' . time() . '_' . rand(1000, 9999) . '.' . pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photo_path = 'uploads/reviews/' . $photo_name;
        
        if (!is_dir('uploads/reviews')) {
            mkdir('uploads/reviews', 0777, true);
        }
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
            $photo_url = $photo_path;
        }
    }
    
    // Upload voice
    if (isset($_FILES['voice']) && $_FILES['voice']['error'] === UPLOAD_ERR_OK) {
        $voice_name = 'review_voice_' . time() . '_' . rand(1000, 9999) . '.' . pathinfo($_FILES['voice']['name'], PATHINFO_EXTENSION);
        $voice_path = 'uploads/reviews/' . $voice_name;
        
        if (!is_dir('uploads/reviews')) {
            mkdir('uploads/reviews', 0777, true);
        }
        
        if (move_uploaded_file($_FILES['voice']['tmp_name'], $voice_path)) {
            $voice_url = $voice_path;
        }
    }
    
    // Upload document
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $document_name = 'review_doc_' . time() . '_' . rand(1000, 9999) . '.' . pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        $document_path = 'uploads/reviews/' . $document_name;
        
        if (!is_dir('uploads/reviews')) {
            mkdir('uploads/reviews', 0777, true);
        }
        
        if (move_uploaded_file($_FILES['document']['tmp_name'], $document_path)) {
            $document_url = $document_path;
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO manager_reviews (company_id, manager_name, qa_pairs, review_type, risk_level, ml_enabled, photo_url, voice_url, document_url, review_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssissssi", $company_id, $manager_name, $qa_pairs_json, $review_type, $risk_level, $ml_enabled, $photo_url, $voice_url, $document_url, $review_date, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Manager review added successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add review: ' . $stmt->error]);
    }
    $stmt->close();
}

// Get All Reviews
elseif ($action === 'get_reviews') {
    // First check if manager_reviews table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'manager_reviews'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Table manager_reviews does not exist. Please run setup_manager_reviews_table.php to create it.'
        ]);
        exit;
    }
    
    $sql = "SELECT mr.*, c.company_name, u.full_name as creator_name 
            FROM manager_reviews mr
            LEFT JOIN company c ON mr.company_id = c.company_id
            LEFT JOIN user u ON mr.created_by = u.user_id
            ORDER BY mr.created_at DESC";
    
    $result = $conn->query($sql);
    $reviews = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
        echo json_encode(['success' => true, 'reviews' => $reviews]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Database query failed: ' . $conn->error
        ]);
    }
}

// Update Review Status
elseif ($action === 'update_status') {
    $review_id = $_POST['review_id'] ?? '';
    $status = $_POST['status'] ?? '';
    
    $stmt = $conn->prepare("UPDATE manager_reviews SET status = ? WHERE review_id = ?");
    $stmt->bind_param("si", $status, $review_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
    $stmt->close();
}

// Delete Review
elseif ($action === 'delete_review') {
    $review_id = $_POST['review_id'] ?? '';
    
    // Get file paths before deleting
    $stmt = $conn->prepare("SELECT photo_url, voice_url, document_url FROM manager_reviews WHERE review_id = ?");
    $stmt->bind_param("i", $review_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $review = $result->fetch_assoc();
    
    // Delete files if they exist
    if ($review) {
        if ($review['photo_url'] && file_exists($review['photo_url'])) {
            unlink($review['photo_url']);
        }
        if ($review['voice_url'] && file_exists($review['voice_url'])) {
            unlink($review['voice_url']);
        }
        if ($review['document_url'] && file_exists($review['document_url'])) {
            unlink($review['document_url']);
        }
    }
    $stmt->close();
    
    // Delete review record
    $stmt = $conn->prepare("DELETE FROM manager_reviews WHERE review_id = ?");
    $stmt->bind_param("i", $review_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Manager review deleted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete review']);
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
