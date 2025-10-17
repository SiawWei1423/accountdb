-- =====================================================
-- Simplified MSIC Implementation
-- Adds only ONE column for storing descriptions
-- msic_code (existing) will store multiple codes separated by commas
-- =====================================================

-- Add single description column to store all descriptions
ALTER TABLE company 
ADD COLUMN msic_descriptions TEXT DEFAULT NULL AFTER msic_code;

-- Verify the changes
SHOW COLUMNS FROM company;

-- =====================================================
-- Migration Complete!
-- Now you can store multiple MSIC codes in msic_code field
-- Example: msic_code = "01111, 10101, 46101"
-- Example: msic_descriptions = "Growing of maize|Processing and preserving of meat|Wholesale of agricultural raw materials"
-- =====================================================
