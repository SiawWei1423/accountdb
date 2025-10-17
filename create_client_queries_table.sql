-- Create Client Queries Table (WITHOUT Foreign Keys to avoid errors)
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
    document_url VARCHAR(500),
    query_date DATE NOT NULL,
    status ENUM('Pending', 'In Progress', 'Resolved', 'Closed') DEFAULT 'Pending',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company_id (company_id),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
