-- =====================================================
-- MSIC Columns Migration Script
-- Adds new MSIC code columns to existing company table
-- Run this in phpMyAdmin or MySQL console
-- =====================================================

-- Add MSIC Code 1 columns
ALTER TABLE company 
ADD COLUMN msic_code_1 VARCHAR(10) DEFAULT NULL AFTER msic_code,
ADD COLUMN msic_desc_1 TEXT DEFAULT NULL AFTER msic_code_1;

-- Add MSIC Code 2 columns
ALTER TABLE company 
ADD COLUMN msic_code_2 VARCHAR(10) DEFAULT NULL AFTER msic_desc_1,
ADD COLUMN msic_desc_2 TEXT DEFAULT NULL AFTER msic_code_2;

-- Add MSIC Code 3 columns
ALTER TABLE company 
ADD COLUMN msic_code_3 VARCHAR(10) DEFAULT NULL AFTER msic_desc_2,
ADD COLUMN msic_desc_3 TEXT DEFAULT NULL AFTER msic_code_3;

-- Verify the changes
SHOW COLUMNS FROM company;

-- =====================================================
-- Migration Complete!
-- You can now use the MSIC autocomplete feature
-- =====================================================
