<?php
// Example of setting up a database connection using MySQLi
$host = 'localhost';      // Your database host
$username = 'root';       // Your database username
$password = '';           // Your database password
$database = 'accountdb';  // Your database name

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    // Don't die() here - let the calling script handle the error
    // This is important for JSON API endpoints
    error_log("Database connection failed: " . $conn->connect_error);
    // The connection error will be checked by the calling script
}
?>
