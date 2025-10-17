<?php
/**
 * Migration Script: Add document_url column to client_queries table
 * Run this file once to update the database schema
 */

require_once 'db_connection.php';

echo "<h2>Database Migration: Adding document_url column</h2>";

// Check if column already exists
$check_sql = "SHOW COLUMNS FROM client_queries LIKE 'document_url'";
$result = $conn->query($check_sql);

if ($result->num_rows > 0) {
    echo "<p style='color: orange;'>✓ Column 'document_url' already exists in client_queries table.</p>";
} else {
    // Add the column
    $alter_sql = "ALTER TABLE client_queries ADD COLUMN document_url VARCHAR(255) NULL AFTER voice_url";
    
    if ($conn->query($alter_sql) === TRUE) {
        echo "<p style='color: green;'>✓ Successfully added 'document_url' column to client_queries table!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding column: " . $conn->error . "</p>";
    }
}

echo "<hr>";
echo "<p><strong>Migration completed!</strong></p>";
echo "<p><a href='index.php'>← Back to Dashboard</a></p>";

$conn->close();
?>
