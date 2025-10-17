<?php
/**
 * Client Queries Setup Test Script
 * Run this file in your browser to verify the setup
 */

require_once 'db_connection.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Client Queries Setup Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #1a1d29; color: #fff; }
        .success { color: #28a745; padding: 10px; margin: 10px 0; background: rgba(40, 167, 69, 0.1); border-left: 4px solid #28a745; }
        .error { color: #dc3545; padding: 10px; margin: 10px 0; background: rgba(220, 53, 69, 0.1); border-left: 4px solid #dc3545; }
        .warning { color: #ffc107; padding: 10px; margin: 10px 0; background: rgba(255, 193, 7, 0.1); border-left: 4px solid #ffc107; }
        .info { color: #17a2b8; padding: 10px; margin: 10px 0; background: rgba(23, 162, 184, 0.1); border-left: 4px solid #17a2b8; }
        h1 { color: #ffc107; }
        h2 { color: #17a2b8; margin-top: 30px; }
        code { background: #2b3035; padding: 2px 6px; border-radius: 4px; color: #ffc107; }
    </style>
</head>
<body>
    <h1>🔍 Client Queries Feature - Setup Test</h1>
    <p>This script will verify that all components are properly configured.</p>
    <hr style='border-color: rgba(255, 193, 7, 0.3);'>
";

$allGood = true;

// Test 1: Database Connection
echo "<h2>1. Database Connection</h2>";
if ($conn->connect_error) {
    echo "<div class='error'>❌ Database connection failed: " . $conn->connect_error . "</div>";
    $allGood = false;
} else {
    echo "<div class='success'>✅ Database connection successful</div>";
}

// Test 2: Check if client_queries table exists
echo "<h2>2. Client Queries Table</h2>";
$tableCheck = $conn->query("SHOW TABLES LIKE 'client_queries'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    echo "<div class='success'>✅ Table 'client_queries' exists</div>";
    
    // Check table structure
    $columns = $conn->query("DESCRIBE client_queries");
    echo "<div class='info'>📋 Table Structure:<br>";
    echo "<ul>";
    while ($col = $columns->fetch_assoc()) {
        echo "<li><code>{$col['Field']}</code> - {$col['Type']}</li>";
    }
    echo "</ul></div>";
} else {
    echo "<div class='error'>❌ Table 'client_queries' does not exist</div>";
    echo "<div class='warning'>⚠️ Please run the SQL script: <code>create_client_queries_table.sql</code></div>";
    $allGood = false;
}

// Test 3: Check required tables (companies, users)
echo "<h2>3. Required Tables</h2>";
$requiredTables = ['companies', 'users'];
foreach ($requiredTables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check && $check->num_rows > 0) {
        echo "<div class='success'>✅ Table '$table' exists</div>";
    } else {
        echo "<div class='error'>❌ Table '$table' does not exist</div>";
        $allGood = false;
    }
}

// Test 4: Check upload directory
echo "<h2>4. Upload Directory</h2>";
$uploadDir = 'uploads/queries';
if (is_dir($uploadDir)) {
    echo "<div class='success'>✅ Directory '$uploadDir' exists</div>";
    
    if (is_writable($uploadDir)) {
        echo "<div class='success'>✅ Directory is writable</div>";
    } else {
        echo "<div class='error'>❌ Directory is not writable</div>";
        echo "<div class='warning'>⚠️ Run: <code>chmod 777 $uploadDir</code></div>";
        $allGood = false;
    }
} else {
    echo "<div class='error'>❌ Directory '$uploadDir' does not exist</div>";
    echo "<div class='warning'>⚠️ Creating directory...</div>";
    
    if (mkdir($uploadDir, 0777, true)) {
        echo "<div class='success'>✅ Directory created successfully</div>";
    } else {
        echo "<div class='error'>❌ Failed to create directory</div>";
        $allGood = false;
    }
}

// Test 5: Check required files
echo "<h2>5. Required Files</h2>";
$requiredFiles = [
    'query_handler.php' => 'Backend handler for query operations',
    'index.php' => 'Main application file',
    'create_client_queries_table.sql' => 'Database table creation script'
];

foreach ($requiredFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<div class='success'>✅ <code>$file</code> - $description</div>";
    } else {
        echo "<div class='error'>❌ <code>$file</code> not found</div>";
        $allGood = false;
    }
}

// Test 6: Check if there are any companies (needed for queries)
echo "<h2>6. Sample Data Check</h2>";
$companiesCheck = $conn->query("SELECT COUNT(*) as count FROM companies");
if ($companiesCheck) {
    $count = $companiesCheck->fetch_assoc()['count'];
    if ($count > 0) {
        echo "<div class='success'>✅ Found $count companies in database</div>";
    } else {
        echo "<div class='warning'>⚠️ No companies found. Add companies before creating queries.</div>";
    }
}

$usersCheck = $conn->query("SELECT COUNT(*) as count FROM users");
if ($usersCheck) {
    $count = $usersCheck->fetch_assoc()['count'];
    if ($count > 0) {
        echo "<div class='success'>✅ Found $count users in database</div>";
    } else {
        echo "<div class='warning'>⚠️ No users found. You need at least one user to create queries.</div>";
    }
}

// Test 7: Test query operations (if table exists)
if ($tableCheck && $tableCheck->num_rows > 0) {
    echo "<h2>7. Query Operations Test</h2>";
    
    // Try to count queries
    $queryCount = $conn->query("SELECT COUNT(*) as count FROM client_queries");
    if ($queryCount) {
        $count = $queryCount->fetch_assoc()['count'];
        echo "<div class='info'>📊 Current queries in database: <strong>$count</strong></div>";
    }
}

// Final Summary
echo "<hr style='border-color: rgba(255, 193, 7, 0.3); margin-top: 40px;'>";
echo "<h2>📊 Setup Summary</h2>";

if ($allGood) {
    echo "<div class='success' style='font-size: 18px; font-weight: bold;'>
        ✅ All checks passed! Client Queries feature is ready to use.
    </div>";
    echo "<div class='info'>
        <h3>Next Steps:</h3>
        <ol>
            <li>Log in to your application</li>
            <li>Click <strong>\"Add Query\"</strong> in the sidebar under <strong>CLIENT QUERIES</strong></li>
            <li>Fill in the form and test creating a query</li>
            <li>Go to <strong>\"Manage Queries\"</strong> to view and manage queries</li>
        </ol>
    </div>";
} else {
    echo "<div class='error' style='font-size: 18px; font-weight: bold;'>
        ❌ Some issues found. Please fix the errors above before using the feature.
    </div>";
}

echo "<hr style='border-color: rgba(255, 193, 7, 0.3); margin-top: 40px;'>";
echo "<div class='info'>
    <h3>📚 Documentation:</h3>
    <ul>
        <li>Setup Guide: <code>CLIENT_QUERIES_SETUP.md</code></li>
        <li>Database Script: <code>create_client_queries_table.sql</code></li>
        <li>Backend Handler: <code>query_handler.php</code></li>
    </ul>
</div>";

echo "<div style='margin-top: 30px; padding: 20px; background: rgba(255, 193, 7, 0.1); border-radius: 8px;'>
    <strong>💡 Tip:</strong> After verifying everything works, you can delete this test file (<code>test_queries_setup.php</code>) for security.
</div>";

echo "</body></html>";

$conn->close();
?>
