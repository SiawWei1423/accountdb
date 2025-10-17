-- Migration Script: Update client_queries table to support multiple Q&A pairs
-- Run this if you already have the old table structure

-- Step 1: Check if table exists with old structure
-- If you have existing data, backup first!

-- Step 2: Drop the old table (WARNING: This will delete all data!)
-- Only run this if you don't have important data or have backed it up
-- DROP TABLE IF EXISTS client_queries;

-- Step 3: Create new table with JSON support
CREATE TABLE IF NOT EXISTS client_queries (
    query_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    qa_pairs JSON NOT NULL COMMENT 'Array of {question, answer} objects',
    query_type ENUM('RD', 'AG', 'Doc') NOT NULL,
    risk_level ENUM('Low', 'Middle', 'High') NOT NULL,
    ml_enabled BOOLEAN DEFAULT FALSE,
    photo_url VARCHAR(500),
    voice_url VARCHAR(500),
    query_date DATE NOT NULL,
    status ENUM('Pending', 'In Progress', 'Resolved', 'Closed') DEFAULT 'Pending',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Alternative: If you want to migrate existing data
-- Step 1: Create new table with different name
/*
CREATE TABLE client_queries_new (
    query_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    qa_pairs JSON NOT NULL COMMENT 'Array of {question, answer} objects',
    query_type ENUM('RD', 'AG', 'Doc') NOT NULL,
    risk_level ENUM('Low', 'Middle', 'High') NOT NULL,
    ml_enabled BOOLEAN DEFAULT FALSE,
    photo_url VARCHAR(500),
    voice_url VARCHAR(500),
    query_date DATE NOT NULL,
    status ENUM('Pending', 'In Progress', 'Resolved', 'Closed') DEFAULT 'Pending',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 2: Migrate data from old table to new table
INSERT INTO client_queries_new 
    (company_id, client_name, qa_pairs, query_type, risk_level, ml_enabled, photo_url, voice_url, query_date, status, created_by, created_at, updated_at)
SELECT 
    company_id,
    client_name,
    JSON_ARRAY(JSON_OBJECT('question', question, 'answer', IFNULL(answer, ''))),
    query_type,
    risk_level,
    ml_enabled,
    photo_url,
    voice_url,
    query_date,
    status,
    created_by,
    created_at,
    updated_at
FROM client_queries;

-- Step 3: Drop old table and rename new one
DROP TABLE client_queries;
RENAME TABLE client_queries_new TO client_queries;
*/
