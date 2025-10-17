# Client Queries Feature - Setup Instructions

## Overview
The Client Queries feature allows you to manage client questions, answers, and track them with risk levels, types, and ML capabilities.

## Setup Steps

### 1. Create Database Table
Run the SQL script to create the `client_queries` table:

```bash
# Navigate to phpMyAdmin or MySQL command line
# Run the SQL file: create_client_queries_table.sql
```

Or manually execute:
```sql
CREATE TABLE IF NOT EXISTS client_queries (
    query_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    question TEXT NOT NULL,
    answer TEXT,
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
```

### 2. Create Upload Directory
Create the directory for storing query attachments:
```bash
mkdir uploads/queries
chmod 777 uploads/queries
```

### 3. Files Added/Modified

**New Files:**
- `query_handler.php` - Backend handler for all query operations
- `create_client_queries_table.sql` - Database table creation script
- `CLIENT_QUERIES_SETUP.md` - This setup guide

**Modified Files:**
- `index.php` - Added Client Queries section, modal, and JavaScript handlers

## Features Implemented

### 1. Add Query Modal
- **Company Selection** - Link query to a company
- **Client Name** - Name of the client asking the question
- **Question (Q)** - The client's question
- **Answer (A)** - Optional answer field
- **Type** - RD (Research & Development), AG (Agreement), or Doc (Documentation)
- **Risk Level** - Low, Middle, or High
- **Date** - Query date
- **ML Checkbox** - Enable/disable Machine Learning
- **Photo Upload** - Attach relevant images
- **Voice Recording** - Upload voice notes

### 2. Manage Queries Page
- **Search** - Search by client name, company, or question
- **Filters** - Filter by Type, Risk Level, and Status
- **Export** - Export to PDF or Print
- **View Details** - View full query information with photo/voice
- **Update Status** - Change query status (Pending, In Progress, Resolved, Closed)
- **Delete** - Remove queries

### 3. Query Status Workflow
1. **Pending** - New query created
2. **In Progress** - Being worked on
3. **Resolved** - Answer provided
4. **Closed** - Query completed

## Usage

### Adding a Query
1. Click "Add Query" in the sidebar
2. Fill in all required fields (marked with *)
3. Optionally upload photo or voice recording
4. Click "Add Query" button

### Managing Queries
1. Click "Manage Queries" in the sidebar
2. Use filters to find specific queries
3. Click "View" to see full details
4. Update status as needed
5. Delete queries when no longer needed

## Color Scheme
- **Primary Color**: Yellow/Orange (#ffc107, #ff9800)
- **Risk Badges**: 
  - Low: Green
  - Middle: Yellow
  - High: Red
- **Status Badges**:
  - Pending: Yellow
  - In Progress: Blue
  - Resolved: Green
  - Closed: Gray

## Backend Operations

### query_handler.php Actions:
- `add_query` - Create new query
- `get_queries` - Retrieve all queries
- `update_status` - Change query status
- `update_answer` - Add/update answer
- `delete_query` - Remove query and associated files

## Security Features
- Session-based authentication
- File upload validation
- SQL injection prevention (prepared statements)
- XSS protection (htmlspecialchars)
- File deletion on query removal

## Troubleshooting

### Common Issues:

1. **"Unauthorized access" error**
   - Make sure you're logged in
   - Check session is active

2. **File upload fails**
   - Verify `uploads/queries` directory exists
   - Check directory permissions (777)

3. **Queries not loading**
   - Check database table exists
   - Verify `query_handler.php` is accessible
   - Check browser console for errors

4. **Foreign key constraint fails**
   - Ensure `companies` and `users` tables exist
   - Verify company_id and user_id are valid

## Future Enhancements
- Email notifications for new queries
- Query assignment to specific users
- Query priority levels
- Advanced search with date ranges
- Query analytics dashboard
- Bulk operations
- Query templates
- Integration with ML services
