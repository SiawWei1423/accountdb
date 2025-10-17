<?php
/**
 * Database Migration Script
 * Adds MSIC code columns to existing company table
 * Run this file once to update your database
 */

require_once 'db_connection.php';

echo "<h2>MSIC Columns Migration Script</h2>";
echo "<p style='color: green; font-weight: bold;'>✓ No migration needed!</p>";
echo "<p>The <strong>msic_code</strong> column already exists in your company table and can store multiple MSIC codes.</p>";
echo "<p><strong>How it works:</strong></p>";
echo "<ul>";
echo "<li>Store multiple MSIC codes in the existing <code>msic_code</code> field (comma-separated)</li>";
echo "<li>Store the business description in the <code>nature_of_business</code> field</li>";
echo "<li>Example: msic_code = '01111, 10101, 46101'</li>";
echo "<li>Example: nature_of_business = 'Manufacturing and wholesale of agricultural products'</li>";
echo "</ul>";
echo "<p>The autocomplete feature helps you find the correct MSIC codes quickly.</p>";
echo "<p><a href='index.php' style='background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 15px;'>← Go back to application</a></p>";

// Show current table structure
echo "<hr>";
echo "<h3>Current Company Table Structure:</h3>";
echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
echo "<tr><th>Column Name</th><th>Type</th><th>Null</th><th>Default</th></tr>";

$columns_query = "SHOW COLUMNS FROM company";
$columns_result = $conn->query($columns_query);

if ($columns_result) {
    while ($row = $columns_result->fetch_assoc()) {
        $highlight = (strpos($row['Field'], 'msic') !== false) ? "style='background-color: #ffffcc;'" : "";
        echo "<tr $highlight>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
}

echo "</table>";

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>MSIC Migration</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h2 {
            color: #333;
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            background: white;
            margin-top: 10px;
        }
        th {
            background: #0d6efd;
            color: white;
            padding: 10px;
            text-align: left;
        }
        td {
            padding: 8px;
        }
        tr:nth-child(even) {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
</body>
</html>
