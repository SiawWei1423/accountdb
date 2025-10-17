-- Time Cost Feature - Database Schema
-- Run this file in MySQL to create required tables

-- 1) Departments/Scopes used for job classification (e.g., 3-DataEnt, 4-Payroll)
CREATE TABLE IF NOT EXISTS time_departments (
  dept_id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  description VARCHAR(100) NOT NULL,
  parent_code VARCHAR(20) DEFAULT NULL,
  INDEX idx_parent_code (parent_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Effective hourly rates per staff (FIFO-style cost uses rate effective on entry date)
CREATE TABLE IF NOT EXISTS user_hourly_rates (
  rate_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  effective_from DATE NOT NULL,
  hourly_rate DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_effective (user_id, effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Individual time cost entries (core transactions)
CREATE TABLE IF NOT EXISTS time_cost_entries (
  entry_id INT AUTO_INCREMENT PRIMARY KEY,
  entry_date DATE NOT NULL,
  entry_type ENUM('PI','ADJ') DEFAULT 'PI' COMMENT 'PI=Purchase Invoice, ADJ=Manual Adjustment',
  doc_no VARCHAR(50) DEFAULT NULL,
  company_id INT DEFAULT NULL,
  company_name VARCHAR(255) DEFAULT NULL,
  staff_name VARCHAR(255) NOT NULL,
  user_id INT DEFAULT NULL,
  financial_year INT NOT NULL,
  department_code VARCHAR(20) NOT NULL,
  hours DECIMAL(7,2) NOT NULL,
  unit_cost DECIMAL(10,2) NOT NULL,
  total_cost DECIMAL(12,2) NOT NULL,
  description TEXT,
  in_out ENUM('In','Out') DEFAULT 'In',
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_date (entry_date),
  INDEX idx_year (financial_year),
  INDEX idx_dept (department_code),
  INDEX idx_company (company_id),
  CONSTRAINT fk_time_dept_code FOREIGN KEY (department_code) REFERENCES time_departments(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional starter departments matching your screenshot examples
INSERT IGNORE INTO time_departments (code, description, parent_code) VALUES
('10-CoForm', '10-Company Formation', NULL),
('11-InvForm', '11-Invoice Form', NULL),
('1-Sorting', '1-Sorting', NULL),
('2-Filing', '2-Filing', NULL),
('3-DataEnt', '3-Data Entry', NULL),
('4-Payroll', '4-Payroll', NULL),
('5-Admin', '5-Admin', NULL),
('6-PreAudit', '6-PreAudit', NULL),
('7-Packing', '7-Packing', NULL),
('8-Despatch', '8-Despatch', NULL),
('9-Software', '9-Software', NULL),
('Z ONLEAVE', 'On Leave (non-productive)', NULL)
ON DUPLICATE KEY UPDATE description = VALUES(description);




