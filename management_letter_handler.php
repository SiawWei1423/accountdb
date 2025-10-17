<?php
session_start();
require_once('db_connection.php');

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Get companies for filter dropdown
if ($action === 'get_companies') {
    $sql = "SELECT company_id, company_name FROM company ORDER BY company_name";
    $result = $conn->query($sql);
    
    $companies = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $companies[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'companies' => $companies]);
    exit;
}

// Get risk items (queries and reviews)
if ($action === 'get_risk_items') {
    $riskLevels = $_GET['risk_levels'] ?? 'Middle,High';
    $companyId = $_GET['company_id'] ?? '';
    
    // Parse risk levels
    $riskArray = array_map('trim', explode(',', $riskLevels));
    
    // Get client queries
    $queriesSql = "SELECT cq.*, c.company_name 
                   FROM client_queries cq 
                   LEFT JOIN company c ON cq.company_id = c.company_id 
                   WHERE 1=1";
    
    // Add risk level filter
    if (!empty($riskArray)) {
        $riskPlaceholders = implode(',', array_fill(0, count($riskArray), '?'));
        $queriesSql .= " AND cq.risk_level IN ($riskPlaceholders)";
    }
    
    // Add company filter
    if (!empty($companyId)) {
        $queriesSql .= " AND cq.company_id = ?";
    }
    
    $queriesSql .= " ORDER BY cq.risk_level DESC, cq.query_date DESC";
    
    $stmt = $conn->prepare($queriesSql);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $conn->error]);
        exit;
    }
    
    // Bind parameters
    if (!empty($riskArray) && !empty($companyId)) {
        $types = str_repeat('s', count($riskArray)) . 'i';
        $params = array_merge($riskArray, [intval($companyId)]);
        $stmt->bind_param($types, ...$params);
    } elseif (!empty($riskArray)) {
        $types = str_repeat('s', count($riskArray));
        $stmt->bind_param($types, ...$riskArray);
    }
    
    $stmt->execute();
    $queriesResult = $stmt->get_result();
    
    $queries = [];
    while ($row = $queriesResult->fetch_assoc()) {
        $queries[] = $row;
    }
    $stmt->close();
    
    // Get manager reviews
    $reviewsSql = "SELECT mr.*, c.company_name 
                   FROM manager_reviews mr 
                   LEFT JOIN company c ON mr.company_id = c.company_id 
                   WHERE 1=1";
    
    // Add risk level filter
    if (!empty($riskArray)) {
        $riskPlaceholders = implode(',', array_fill(0, count($riskArray), '?'));
        $reviewsSql .= " AND mr.risk_level IN ($riskPlaceholders)";
    }
    
    // Add company filter
    if (!empty($companyId)) {
        $reviewsSql .= " AND mr.company_id = ?";
    }
    
    $reviewsSql .= " ORDER BY mr.risk_level DESC, mr.review_date DESC";
    
    $stmt = $conn->prepare($reviewsSql);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Review query preparation failed: ' . $conn->error]);
        exit;
    }
    
    // Bind parameters
    if (!empty($riskArray) && !empty($companyId)) {
        $types = str_repeat('s', count($riskArray)) . 'i';
        $params = array_merge($riskArray, [intval($companyId)]);
        $stmt->bind_param($types, ...$params);
    } elseif (!empty($riskArray)) {
        $types = str_repeat('s', count($riskArray));
        $stmt->bind_param($types, ...$riskArray);
    }
    
    $stmt->execute();
    $reviewsResult = $stmt->get_result();
    
    $reviews = [];
    while ($row = $reviewsResult->fetch_assoc()) {
        $reviews[] = $row;
    }
    $stmt->close();
    
    // Calculate summary
    $highRiskCount = 0;
    $middleRiskCount = 0;
    $companiesSet = [];
    
    foreach ($queries as $query) {
        if ($query['risk_level'] === 'High') $highRiskCount++;
        if ($query['risk_level'] === 'Middle') $middleRiskCount++;
        if (!empty($query['company_id'])) {
            $companiesSet[$query['company_id']] = true;
        }
    }
    
    foreach ($reviews as $review) {
        if ($review['risk_level'] === 'High') $highRiskCount++;
        if ($review['risk_level'] === 'Middle') $middleRiskCount++;
        if (!empty($review['company_id'])) {
            $companiesSet[$review['company_id']] = true;
        }
    }
    
    $summary = [
        'high_risk_count' => $highRiskCount,
        'middle_risk_count' => $middleRiskCount,
        'companies_count' => count($companiesSet)
    ];
    
    echo json_encode([
        'success' => true,
        'queries' => $queries,
        'reviews' => $reviews,
        'summary' => $summary
    ]);
    exit;
}

// Get letter HTML for modal preview
if ($action === 'get_letter_html') {
    $riskLevels = $_GET['risk_levels'] ?? 'Middle,High';
    $companyId = $_GET['company_id'] ?? '';
    
    // Parse risk levels
    $riskArray = explode(',', $riskLevels);
    $riskPlaceholders = implode(',', array_fill(0, count($riskArray), '?'));
    
    // Build company filter
    $companyName = 'All Companies';
    
    if (!empty($companyId)) {
        // Get company name
        $companyStmt = $conn->prepare("SELECT company_name FROM company WHERE company_id = ?");
        $companyStmt->bind_param('i', $companyId);
        $companyStmt->execute();
        $companyResult = $companyStmt->get_result();
        if ($companyRow = $companyResult->fetch_assoc()) {
            $companyName = $companyRow['company_name'];
        }
    }
    
    // Get client queries
    $queriesSql = "SELECT cq.*, c.company_name 
                   FROM client_queries cq 
                   LEFT JOIN company c ON cq.company_id = c.company_id 
                   WHERE cq.risk_level IN ($riskPlaceholders)";
    
    if (!empty($companyId)) {
        $queriesSql .= " AND cq.company_id = ?";
    }
    
    $queriesSql .= " ORDER BY cq.risk_level DESC, cq.query_date DESC";
    
    $stmt = $conn->prepare($queriesSql);
    if (!empty($companyId)) {
        $types = str_repeat('s', count($riskArray)) . 'i';
        $params = array_merge($riskArray, [$companyId]);
        $stmt->bind_param($types, ...$params);
    } else {
        $types = str_repeat('s', count($riskArray));
        $stmt->bind_param($types, ...$riskArray);
    }
    
    $stmt->execute();
    $queriesResult = $stmt->get_result();
    
    $queries = [];
    while ($row = $queriesResult->fetch_assoc()) {
        $queries[] = $row;
    }
    
    // Get manager reviews
    $reviewsSql = "SELECT mr.*, c.company_name 
                   FROM manager_reviews mr 
                   LEFT JOIN company c ON mr.company_id = c.company_id 
                   WHERE mr.risk_level IN ($riskPlaceholders)";
    
    if (!empty($companyId)) {
        $reviewsSql .= " AND mr.company_id = ?";
    }
    
    $reviewsSql .= " ORDER BY mr.risk_level DESC, mr.review_date DESC";
    
    $stmt = $conn->prepare($reviewsSql);
    if (!empty($companyId)) {
        $types = str_repeat('s', count($riskArray)) . 'i';
        $params = array_merge($riskArray, [$companyId]);
        $stmt->bind_param($types, ...$params);
    } else {
        $types = str_repeat('s', count($riskArray));
        $stmt->bind_param($types, ...$riskArray);
    }
    
    $stmt->execute();
    $reviewsResult = $stmt->get_result();
    
    $reviews = [];
    while ($row = $reviewsResult->fetch_assoc()) {
        $reviews[] = $row;
    }
    
    // Calculate summary
    $highCount = 0;
    $middleCount = 0;
    
    foreach ($queries as $q) {
        if ($q['risk_level'] === 'High') $highCount++;
        if ($q['risk_level'] === 'Middle') $middleCount++;
    }
    foreach ($reviews as $r) {
        if ($r['risk_level'] === 'High') $highCount++;
        if ($r['risk_level'] === 'Middle') $middleCount++;
    }
    
    // Generate beautiful HTML content
    ob_start();
    include 'management_letter_template.php';
    $html = ob_get_clean();
    echo $html;
    exit;
}

// Export PDF (HTML version for printing) - Keep for backward compatibility
if ($action === 'export_pdf') {
    // Redirect to get_letter_html
    $_GET['action'] = 'get_letter_html';
    
    $riskLevels = $_GET['risk_levels'] ?? 'Middle,High';
    $companyId = $_GET['company_id'] ?? '';
    
    // Parse risk levels
    $riskArray = explode(',', $riskLevels);
    $riskPlaceholders = implode(',', array_fill(0, count($riskArray), '?'));
    
    // Build company filter
    $companyName = 'All Companies';
    
    if (!empty($companyId)) {
        // Get company name
        $companyStmt = $conn->prepare("SELECT company_name FROM company WHERE company_id = ?");
        $companyStmt->bind_param('i', $companyId);
        $companyStmt->execute();
        $companyResult = $companyStmt->get_result();
        if ($companyRow = $companyResult->fetch_assoc()) {
            $companyName = $companyRow['company_name'];
        }
    }
    
    // Get client queries
    $queriesSql = "SELECT cq.*, c.company_name 
                   FROM client_queries cq 
                   LEFT JOIN company c ON cq.company_id = c.company_id 
                   WHERE cq.risk_level IN ($riskPlaceholders)";
    
    if (!empty($companyId)) {
        $queriesSql .= " AND cq.company_id = ?";
    }
    
    $queriesSql .= " ORDER BY cq.risk_level DESC, cq.query_date DESC";
    
    $stmt = $conn->prepare($queriesSql);
    if (!empty($companyId)) {
        $types = str_repeat('s', count($riskArray)) . 'i';
        $params = array_merge($riskArray, [$companyId]);
        $stmt->bind_param($types, ...$params);
    } else {
        $types = str_repeat('s', count($riskArray));
        $stmt->bind_param($types, ...$riskArray);
    }
    
    $stmt->execute();
    $queriesResult = $stmt->get_result();
    
    $queries = [];
    while ($row = $queriesResult->fetch_assoc()) {
        $queries[] = $row;
    }
    
    // Get manager reviews
    $reviewsSql = "SELECT mr.*, c.company_name 
                   FROM manager_reviews mr 
                   LEFT JOIN company c ON mr.company_id = c.company_id 
                   WHERE mr.risk_level IN ($riskPlaceholders)";
    
    if (!empty($companyId)) {
        $reviewsSql .= " AND mr.company_id = ?";
    }
    
    $reviewsSql .= " ORDER BY mr.risk_level DESC, mr.review_date DESC";
    
    $stmt = $conn->prepare($reviewsSql);
    if (!empty($companyId)) {
        $types = str_repeat('s', count($riskArray)) . 'i';
        $params = array_merge($riskArray, [$companyId]);
        $stmt->bind_param($types, ...$params);
    } else {
        $types = str_repeat('s', count($riskArray));
        $stmt->bind_param($types, ...$riskArray);
    }
    
    $stmt->execute();
    $reviewsResult = $stmt->get_result();
    
    $reviews = [];
    while ($row = $reviewsResult->fetch_assoc()) {
        $reviews[] = $row;
    }
    
    // Calculate summary
    $highCount = 0;
    $middleCount = 0;
    
    foreach ($queries as $q) {
        if ($q['risk_level'] === 'High') $highCount++;
        if ($q['risk_level'] === 'Middle') $middleCount++;
    }
    foreach ($reviews as $r) {
        if ($r['risk_level'] === 'High') $highCount++;
        if ($r['risk_level'] === 'Middle') $middleCount++;
    }
    
    // Generate HTML document for printing to PDF
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Management Letter - Risk Assessment</title>
    <style>
        @page {
            margin: 2cm;
        }
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin: 10px 0;
        }
        .header h2 {
            color: #34495e;
            font-size: 20px;
            margin: 5px 0;
            font-weight: normal;
        }
        .info-section {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #3498db;
        }
        .info-section p {
            margin: 5px 0;
        }
        .section-title {
            background: #3498db;
            color: white;
            padding: 12px 15px;
            margin: 30px 0 15px 0;
            font-size: 18px;
            font-weight: bold;
        }
        .section-title.reviews {
            background: #2ecc71;
        }
        .risk-item {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            page-break-inside: avoid;
        }
        .risk-item.high {
            border-left: 5px solid #e74c3c;
            background: #ffebee;
        }
        .risk-item.middle {
            border-left: 5px solid #f39c12;
            background: #fff8e1;
        }
        .risk-header {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 10px;
        }
        .risk-header.high {
            color: #c0392b;
        }
        .risk-header.middle {
            color: #d68910;
        }
        .risk-meta {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .qa-pair {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 3px;
        }
        .question {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .answer {
            color: #555;
            margin-left: 15px;
        }
        .summary-box {
            background: #ecf0f1;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .summary-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .conclusion {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border: 2px solid #3498db;
            border-radius: 5px;
        }
        .signature {
            margin-top: 50px;
            page-break-inside: avoid;
        }
        .signature p {
            margin: 5px 0;
        }
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
        .action-buttons {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }
        .print-button, .pdf-button {
            padding: 12px 24px;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            font-weight: bold;
        }
        .print-button {
            background: #3498db;
        }
        .print-button:hover {
            background: #2980b9;
        }
        .pdf-button {
            background: #e74c3c;
        }
        .pdf-button:hover {
            background: #c0392b;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>
    <div class="action-buttons no-print">
        <button class="print-button" onclick="window.print()">🖨️ Print</button>
        <button class="pdf-button" onclick="exportToPDF()">📄 Export to PDF</button>
    </div>
    
    <div id="content-to-export">
    <div class="header">
        <h1>MANAGEMENT LETTER</h1>
        <h2>Risk Assessment Report</h2>
    </div>
    
    <div class="info-section">
        <p><strong>Date:</strong> <?php echo date('F d, Y'); ?></p>
        <p><strong>Company:</strong> <?php echo htmlspecialchars($companyName); ?></p>
        <p><strong>Risk Levels:</strong> <?php echo str_replace(',', ' & ', htmlspecialchars($riskLevels)); ?></p>
    </div>
    
    <p style="margin: 30px 0 10px 0;"><strong>Dear Management,</strong></p>
    
    <p style="text-align: justify;">
        We are writing to bring to your attention certain matters that have been identified during our review process. 
        The following items have been classified as medium or high risk and require your immediate attention and action.
    </p>
    
    <div class="summary-box">
        <h3 style="margin-top: 0;">EXECUTIVE SUMMARY</h3>
        <ul>
            <li><strong>Total High Risk Items:</strong> <?php echo $highCount; ?></li>
            <li><strong>Total Middle Risk Items:</strong> <?php echo $middleCount; ?></li>
            <li><strong>Total Items Requiring Attention:</strong> <?php echo ($highCount + $middleCount); ?></li>
        </ul>
    </div>
    
    <?php if (count($queries) > 0): ?>
    <div class="section-title">SECTION 1: CLIENT QUERIES - RISK ITEMS</div>
    
    <?php foreach ($queries as $index => $query): 
        $riskClass = strtolower($query['risk_level']);
        $qaPairs = json_decode($query['qa_pairs'], true);
    ?>
    <div class="risk-item <?php echo $riskClass; ?>">
        <div class="risk-header <?php echo $riskClass; ?>">
            <?php echo ($index + 1); ?>. <?php echo strtoupper($query['risk_level']); ?> RISK - <?php echo htmlspecialchars($query['company_name']); ?>
        </div>
        <div class="risk-meta">
            <strong>Client:</strong> <?php echo htmlspecialchars($query['client_name']); ?> | 
            <strong>Type:</strong> <?php echo htmlspecialchars($query['query_type']); ?> | 
            <strong>Date:</strong> <?php echo htmlspecialchars($query['query_date']); ?>
        </div>
        
        <?php if ($qaPairs): ?>
            <?php foreach ($qaPairs as $qaIndex => $qa): ?>
            <div class="qa-pair">
                <div class="question">Question <?php echo ($qaIndex + 1); ?>: <?php echo htmlspecialchars($qa['question']); ?></div>
                <div class="answer">Answer: <?php echo htmlspecialchars($qa['answer']); ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (count($reviews) > 0): ?>
    <div class="section-title reviews">SECTION 2: MANAGER REVIEWS - RISK ITEMS</div>
    
    <?php foreach ($reviews as $index => $review): 
        $riskClass = strtolower($review['risk_level']);
        $qaPairs = json_decode($review['qa_pairs'], true);
    ?>
    <div class="risk-item <?php echo $riskClass; ?>">
        <div class="risk-header <?php echo $riskClass; ?>">
            <?php echo ($index + 1); ?>. <?php echo strtoupper($review['risk_level']); ?> RISK - <?php echo htmlspecialchars($review['company_name']); ?>
        </div>
        <div class="risk-meta">
            <strong>Manager:</strong> <?php echo htmlspecialchars($review['manager_name']); ?> | 
            <strong>Type:</strong> <?php echo htmlspecialchars($review['review_type']); ?> | 
            <strong>Date:</strong> <?php echo htmlspecialchars($review['review_date']); ?>
        </div>
        
        <?php if ($qaPairs): ?>
            <?php foreach ($qaPairs as $qaIndex => $qa): ?>
            <div class="qa-pair">
                <div class="question">Question <?php echo ($qaIndex + 1); ?>: <?php echo htmlspecialchars($qa['question']); ?></div>
                <div class="answer">Answer: <?php echo htmlspecialchars($qa['answer']); ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    
    <div class="conclusion">
        <h3 style="margin-top: 0;">RECOMMENDATIONS AND CONCLUSION</h3>
        <p style="text-align: justify;">
            Based on our review, we strongly recommend that management take immediate action to address the high-risk items 
            identified in this letter. The medium-risk items should also be reviewed and appropriate measures implemented to 
            mitigate potential issues.
        </p>
        <p><strong>We recommend the following actions:</strong></p>
        <ol>
            <li>Immediate review and response to all high-risk items</li>
            <li>Development of action plans for medium-risk items</li>
            <li>Regular monitoring and follow-up on all identified risks</li>
            <li>Implementation of preventive measures to avoid similar issues in the future</li>
        </ol>
        <p style="text-align: justify;">
            Should you require any clarification or additional information regarding the matters outlined in this letter, 
            please do not hesitate to contact us.
        </p>
    </div>
    
    <div class="signature">
        <p>Sincerely,</p>
        <p style="margin-top: 50px;">________________________</p>
        <p><strong>Management Team</strong></p>
        <p><?php echo date('F d, Y'); ?></p>
    </div>
    </div><!-- End content-to-export -->
    
    <script>
    function exportToPDF() {
        const element = document.getElementById('content-to-export');
        const opt = {
            margin: [15, 15, 15, 15],
            filename: 'Management_Letter_<?php echo date('Y-m-d'); ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
        };
        
        html2pdf().set(opt).from(element).save();
    }
    </script>
</body>
</html>
    <?php
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
