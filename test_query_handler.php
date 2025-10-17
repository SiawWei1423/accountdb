<?php
/**
 * Test Query Handler - Direct API Test
 * Access this file directly in your browser to test the query_handler.php
 */
session_start();

// Simulate logged-in user (change this to match your actual user_id)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Change this to a valid user ID from your database
    echo "<div style='background: #fff3cd; padding: 10px; margin: 10px; border-left: 4px solid #ffc107;'>";
    echo "⚠️ <strong>Note:</strong> Simulating user_id = 1. Make sure this user exists in your database.";
    echo "</div>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Query Handler Test</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        h2 { color: #0066cc; border-bottom: 2px solid #0066cc; padding-bottom: 10px; }
        .btn {
            background: #0066cc;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin: 5px;
        }
        .btn:hover { background: #0052a3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        #result {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
            white-space: pre-wrap;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 500px;
            overflow-y: auto;
        }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { color: #0066cc; }
        pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🧪 Query Handler API Test</h1>
    
    <div class="test-section">
        <h2>1. Database Connection Test</h2>
        <?php
        require_once 'db_connection.php';
        
        if ($conn->connect_error) {
            echo "<p class='error'>❌ Database connection failed: " . $conn->connect_error . "</p>";
        } else {
            echo "<p class='success'>✅ Database connected successfully</p>";
            
            // Check if client_queries table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'client_queries'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                echo "<p class='success'>✅ Table 'client_queries' exists</p>";
                
                // Count records
                $countResult = $conn->query("SELECT COUNT(*) as total FROM client_queries");
                if ($countResult) {
                    $count = $countResult->fetch_assoc()['total'];
                    echo "<p class='info'>📊 Total queries in database: <strong>$count</strong></p>";
                }
            } else {
                echo "<p class='error'>❌ Table 'client_queries' does NOT exist</p>";
                echo "<p>Run <a href='setup_client_queries_table.php'>setup_client_queries_table.php</a> to create it.</p>";
            }
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>2. API Endpoint Test</h2>
        <p>Test the query_handler.php endpoint directly:</p>
        
        <button class="btn btn-success" onclick="testGetQueries()">
            📥 Test GET Queries
        </button>
        
        <button class="btn" onclick="testRawResponse()">
            🔍 Test Raw Response
        </button>
        
        <button class="btn btn-danger" onclick="clearResult()">
            🗑️ Clear Result
        </button>
        
        <div id="result"></div>
    </div>
    
    <div class="test-section">
        <h2>3. Session Information</h2>
        <pre><?php print_r($_SESSION); ?></pre>
    </div>
    
    <script>
        function clearResult() {
            document.getElementById('result').innerHTML = '';
        }
        
        function testGetQueries() {
            const result = document.getElementById('result');
            result.innerHTML = '<span class="info">⏳ Testing API endpoint...</span>';
            
            const formData = new FormData();
            formData.append('action', 'get_queries');
            
            fetch('query_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                result.innerHTML = '<span class="info">📡 Raw Response:</span>\n' + text + '\n\n';
                
                try {
                    const data = JSON.parse(text);
                    result.innerHTML += '<span class="success">✅ Valid JSON Response</span>\n\n';
                    result.innerHTML += '<span class="info">📋 Parsed Data:</span>\n';
                    result.innerHTML += JSON.stringify(data, null, 2);
                    
                    if (data.success) {
                        result.innerHTML += '\n\n<span class="success">✅ API returned success: true</span>';
                        result.innerHTML += '\n<span class="info">📊 Queries found: ' + (data.queries ? data.queries.length : 0) + '</span>';
                    } else {
                        result.innerHTML += '\n\n<span class="error">❌ API returned success: false</span>';
                        result.innerHTML += '\n<span class="error">Error: ' + (data.message || 'Unknown error') + '</span>';
                    }
                } catch (e) {
                    result.innerHTML += '<span class="error">❌ JSON Parse Error</span>\n';
                    result.innerHTML += '<span class="error">Error: ' + e.message + '</span>\n\n';
                    result.innerHTML += '<span class="info">This means the server returned HTML instead of JSON.</span>\n';
                    result.innerHTML += '<span class="info">Check the raw response above for PHP errors.</span>';
                }
            })
            .catch(error => {
                result.innerHTML = '<span class="error">❌ Network Error</span>\n';
                result.innerHTML += '<span class="error">' + error.message + '</span>';
            });
        }
        
        function testRawResponse() {
            const result = document.getElementById('result');
            result.innerHTML = '<span class="info">⏳ Fetching raw response...</span>';
            
            fetch('query_handler.php?action=get_queries', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_queries'
            })
            .then(response => response.text())
            .then(text => {
                result.innerHTML = '<span class="info">📡 Complete Raw Response:</span>\n\n';
                result.innerHTML += text;
                
                // Check for common issues
                if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                    result.innerHTML += '\n\n<span class="error">⚠️ WARNING: Response contains HTML!</span>';
                }
                if (text.includes('Warning:') || text.includes('Notice:') || text.includes('Fatal error:')) {
                    result.innerHTML += '\n\n<span class="error">⚠️ WARNING: PHP errors detected!</span>';
                }
            })
            .catch(error => {
                result.innerHTML = '<span class="error">❌ Error: ' + error.message + '</span>';
            });
        }
        
        // Auto-run test on page load
        window.addEventListener('load', function() {
            setTimeout(testGetQueries, 500);
        });
    </script>
</body>
</html>
