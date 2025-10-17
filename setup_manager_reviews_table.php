<?php
/**
 * AUTO SETUP: Create manager_reviews table
 * Just run this file in your browser to create the table automatically
 */

require_once 'db_connection.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Setup Manager Reviews Table</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #0d6efd; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }
        .info { color: #0c5460; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0; }
        .code { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; font-family: monospace; font-size: 12px; }
        .btn { display: inline-block; padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .btn:hover { background: #0a58ca; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🚀 Setup Manager Reviews Table</h1>";

// Check if table already exists
$check_table = $conn->query("SHOW TABLES LIKE 'manager_reviews'");

if ($check_table->num_rows > 0) {
    echo "<div class='info'>ℹ️ Table 'manager_reviews' already exists. Checking structure...</div>";
    
    // Check if document_url column exists
    $check_column = $conn->query("SHOW COLUMNS FROM manager_reviews LIKE 'document_url'");
    
    if ($check_column->num_rows == 0) {
        echo "<div class='info'>Adding missing 'document_url' column...</div>";
        
        $alter_sql = "ALTER TABLE manager_reviews ADD COLUMN document_url VARCHAR(500) AFTER voice_url";
        
        if ($conn->query($alter_sql) === TRUE) {
            echo "<div class='success'>✅ Successfully added 'document_url' column!</div>";
        } else {
            echo "<div class='error'>❌ Error adding column: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='success'>✅ Column 'document_url' already exists!</div>";
    }
    
    echo "<div class='success'>✅ Table structure is up to date!</div>";
    
} else {
    echo "<div class='info'>Creating new 'manager_reviews' table...</div>";
    
    // First, check if referenced tables exist and get their structure
    $companies_exists = $conn->query("SHOW TABLES LIKE 'company'")->num_rows > 0;
    $users_exists = $conn->query("SHOW TABLES LIKE 'user'")->num_rows > 0;
    
    if (!$companies_exists) {
        echo "<div class='error'>⚠️ Warning: 'company' table doesn't exist. Creating table without foreign key constraint.</div>";
    }
    if (!$users_exists) {
        echo "<div class='error'>⚠️ Warning: 'user' table doesn't exist. Creating table without foreign key constraint.</div>";
    }
    
    // Create the table WITHOUT foreign keys first
    $sql = "CREATE TABLE manager_reviews (
        review_id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        manager_name VARCHAR(255) NOT NULL,
        qa_pairs JSON NOT NULL COMMENT 'Array of {question, answer} objects',
        review_type ENUM('RD', 'AG', 'Doc') NOT NULL,
        risk_level ENUM('Low', 'Middle', 'High') NOT NULL,
        ml_enabled BOOLEAN DEFAULT FALSE,
        photo_url VARCHAR(500),
        voice_url VARCHAR(500),
        document_url VARCHAR(500),
        review_date DATE NOT NULL,
        status ENUM('Pending', 'In Progress', 'Resolved', 'Closed') DEFAULT 'Pending',
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_company_id (company_id),
        INDEX idx_created_by (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql) === TRUE) {
        echo "<div class='success'>✅ Table 'manager_reviews' created successfully!</div>";
        
        // Try to add foreign keys if tables exist
        $fk_added = 0;
        
        if ($companies_exists) {
            $fk_sql = "ALTER TABLE manager_reviews ADD CONSTRAINT fk_manager_review_company FOREIGN KEY (company_id) REFERENCES company(company_id) ON DELETE CASCADE";
            if ($conn->query($fk_sql) === TRUE) {
                echo "<div class='success'>✅ Added foreign key constraint for 'company_id'</div>";
                $fk_added++;
            } else {
                echo "<div class='error'>⚠️ Could not add foreign key for company_id: " . $conn->error . "</div>";
                echo "<div class='info'>💡 Table created successfully but without foreign key. You can add it later.</div>";
            }
        }
        
        if ($users_exists) {
            $fk_sql = "ALTER TABLE manager_reviews ADD CONSTRAINT fk_manager_review_created_by FOREIGN KEY (created_by) REFERENCES user(user_id) ON DELETE CASCADE";
            if ($conn->query($fk_sql) === TRUE) {
                echo "<div class='success'>✅ Added foreign key constraint for 'created_by'</div>";
                $fk_added++;
            } else {
                echo "<div class='error'>⚠️ Could not add foreign key for created_by: " . $conn->error . "</div>";
                echo "<div class='info'>💡 Table created successfully but without foreign key. You can add it later.</div>";
            }
        }
        
        if ($fk_added == 0 && ($companies_exists || $users_exists)) {
            echo "<div class='info'>ℹ️ Table created without foreign keys. This is OK - the table will still work!</div>";
        }
        
    } else {
        echo "<div class='error'>❌ Error creating table: " . $conn->error . "</div>";
    }
}

// Show table structure
echo "<h2>📋 Table Structure</h2>";
$result = $conn->query("DESCRIBE manager_reviews");

if ($result) {
    echo "<table border='1' cellpadding='10' style='width: 100%; border-collapse: collapse;'>";
    echo "<tr style='background: #0d6efd; color: white;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . $row['Field'] . "</strong></td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<div class='error'>❌ Could not retrieve table structure: " . $conn->error . "</div>";
}

// Show SQL code
echo "<h2>📝 SQL Code Used</h2>";
echo "<div class='code'>";
echo htmlspecialchars("CREATE TABLE manager_reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    manager_name VARCHAR(255) NOT NULL,
    qa_pairs JSON NOT NULL COMMENT 'Array of {question, answer} objects',
    review_type ENUM('RD', 'AG', 'Doc') NOT NULL,
    risk_level ENUM('Low', 'Middle', 'High') NOT NULL,
    ml_enabled BOOLEAN DEFAULT FALSE,
    photo_url VARCHAR(500),
    voice_url VARCHAR(500),
    document_url VARCHAR(500),
    review_date DATE NOT NULL,
    status ENUM('Pending', 'In Progress', 'Resolved', 'Closed') DEFAULT 'Pending',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES company(company_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES user(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "</div>";

echo "<h2>✅ Setup Complete!</h2>";
echo "<p>The manager_reviews table is ready to use.</p>";
echo "<a href='index.php' class='btn'>← Back to Dashboard</a>";

echo "</div></body></html>";

$conn->close();
?>
