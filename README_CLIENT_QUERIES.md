# 🎯 Client Queries Feature - Complete Implementation Guide

## 📋 Overview

The **Client Queries** feature is a comprehensive system for managing client questions, tracking answers, and organizing queries by type, risk level, and status. This feature includes photo/voice attachments and optional Machine Learning integration.

---

## 🚀 Quick Start

### Step 1: Run the Test Script
Open your browser and navigate to:
```
http://localhost/accountdb/accountdb/test_queries_setup.php
```

This will verify:
- ✅ Database connection
- ✅ Table structure
- ✅ Upload directories
- ✅ Required files
- ✅ Sample data

### Step 2: Create Database Table
If the test shows the table doesn't exist, run:

**Option A: Using phpMyAdmin**
1. Open phpMyAdmin
2. Select your `accountdb` database
3. Go to SQL tab
4. Copy and paste the contents of `create_client_queries_table.sql`
5. Click "Go"

**Option B: Using MySQL Command Line**
```bash
mysql -u root -p accountdb < create_client_queries_table.sql
```

### Step 3: Verify Setup
Refresh the test page. All checks should now pass ✅

---

## 📁 Files Structure

```
accountdb/
├── index.php                              # Main application (MODIFIED)
├── query_handler.php                      # Backend API handler (NEW)
├── create_client_queries_table.sql        # Database schema (NEW)
├── test_queries_setup.php                 # Setup verification script (NEW)
├── CLIENT_QUERIES_SETUP.md               # Setup instructions (NEW)
├── README_CLIENT_QUERIES.md              # This file (NEW)
└── uploads/
    └── queries/                           # Query attachments directory (NEW)
        ├── query_photo_*.jpg
        └── query_voice_*.mp3
```

---

## 🎨 User Interface Components

### 1. Sidebar Menu
**Location:** Left sidebar under "CLIENT QUERIES"

- **Add Query** - Opens modal to create new query
- **Manage Queries** - View and manage all queries

### 2. Dashboard Cards
**Location:** Main dashboard

- **Client Queries Card** - Shows total query count
  - Yellow/orange gradient design
  - Click to navigate to Manage Queries page

### 3. Quick Actions
**Location:** Dashboard right panel

- **Add Client Query** - Quick access to create query
- **View All Queries** - Navigate to queries page

---

## 📝 Add Query Modal

### Form Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| **Company** | Dropdown | ✅ Yes | Select company from existing list |
| **Client Name** | Text | ✅ Yes | Name of the person asking |
| **Question (Q)** | Textarea | ✅ Yes | The client's question |
| **Answer (A)** | Textarea | ❌ No | Optional answer (can be added later) |
| **Type** | Dropdown | ✅ Yes | RD / AG / Doc |
| **Risk Level** | Dropdown | ✅ Yes | Low / Middle / High |
| **Date** | Date Picker | ✅ Yes | Query date |
| **ML Enabled** | Checkbox | ❌ No | Enable Machine Learning |
| **Photo** | File Upload | ❌ No | Image attachment (JPG, PNG, etc.) |
| **Voice** | File Upload | ❌ No | Audio recording (MP3, WAV, etc.) |

### Type Options Explained

- **RD** - Research & Development queries
- **AG** - Agreement related queries
- **Doc** - Documentation queries

### Risk Level Guide

- **Low** 🟢 - Minor issues, low priority
- **Middle** 🟡 - Moderate importance
- **High** 🔴 - Critical, requires immediate attention

---

## 📊 Manage Queries Page

### Features

#### Search & Filters
- **Search Bar** - Search by client name, company, or question text
- **Type Filter** - Filter by RD, AG, or Doc
- **Risk Level Filter** - Filter by Low, Middle, or High
- **Status Filter** - Filter by Pending, In Progress, Resolved, or Closed
- **Clear Filters** - Reset all filters

#### Table Columns
1. **No** - Row number
2. **Client Name** - Who asked the question
3. **Company** - Associated company
4. **Question** - Preview of question (hover for full text)
5. **Type** - Query type badge
6. **Risk Level** - Color-coded badge
7. **ML** - ✅ or ❌ indicator
8. **Date** - Query date
9. **Status** - Current status badge
10. **Actions** - View and Delete buttons

#### Export Options
- **Export to PDF** - Download queries as PDF
- **Print** - Print-friendly view

---

## 🔍 View Query Details

Click **View** button to see full query information:

### Details Displayed
- Client Name
- Company
- Type (with badge)
- Risk Level (with badge)
- Date
- Status (with badge)
- ML Enabled (Yes/No)
- Created By (user name)
- **Full Question** - Highlighted in yellow box
- **Full Answer** - Highlighted in green box
- **Photo** - If uploaded, displayed as image
- **Voice Recording** - If uploaded, audio player

### Update Status
Change query status directly from details modal:
- Pending → In Progress → Resolved → Closed

---

## 🔄 Query Status Workflow

```
┌─────────┐
│ Pending │ ← New query created
└────┬────┘
     │
     ▼
┌──────────────┐
│ In Progress  │ ← Being worked on
└──────┬───────┘
       │
       ▼
┌──────────┐
│ Resolved │ ← Answer provided
└────┬─────┘
     │
     ▼
┌────────┐
│ Closed │ ← Query completed
└────────┘
```

---

## 🎨 Color Scheme

### Primary Colors
- **Main**: Yellow/Orange (#ffc107, #ff9800)
- **Hover**: Lighter yellow (#ffca2c)

### Status Badges
| Status | Color | Bootstrap Class |
|--------|-------|-----------------|
| Pending | Yellow | `bg-warning text-dark` |
| In Progress | Blue | `bg-info` |
| Resolved | Green | `bg-success` |
| Closed | Gray | `bg-secondary` |

### Risk Level Badges
| Risk | Color | Bootstrap Class |
|------|-------|-----------------|
| Low | Green | `bg-success` |
| Middle | Yellow | `bg-warning text-dark` |
| High | Red | `bg-danger` |

### Type Badges
All types use: Blue (`bg-primary`)

---

## 🔧 Backend API (query_handler.php)

### Available Actions

#### 1. Add Query
```php
POST: action=add_query
Parameters:
- company_id
- client_name
- question
- answer (optional)
- query_type
- risk_level
- query_date
- ml_enabled (checkbox)
- photo (file)
- voice (file)
```

#### 2. Get All Queries
```php
POST: action=get_queries
Returns: JSON array of all queries with company and creator info
```

#### 3. Update Status
```php
POST: action=update_status
Parameters:
- query_id
- status
```

#### 4. Update Answer
```php
POST: action=update_answer
Parameters:
- query_id
- answer
```

#### 5. Delete Query
```php
POST: action=delete_query
Parameters:
- query_id
Note: Also deletes associated photo/voice files
```

---

## 🗄️ Database Schema

### Table: `client_queries`

```sql
CREATE TABLE client_queries (
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
);
```

### Relationships
- **companies** - Each query belongs to one company
- **users** - Each query is created by one user

---

## 🔒 Security Features

1. **Session Authentication** - All operations require active user session
2. **SQL Injection Prevention** - Prepared statements used throughout
3. **XSS Protection** - All output escaped with `htmlspecialchars()`
4. **File Upload Validation** - File type and size checks
5. **Secure File Storage** - Files stored outside web root when possible
6. **Foreign Key Constraints** - Data integrity maintained

---

## 🧪 Testing Checklist

### Basic Operations
- [ ] Create a new query with all fields
- [ ] Create a query with only required fields
- [ ] Upload a photo attachment
- [ ] Upload a voice recording
- [ ] View query details
- [ ] Update query status
- [ ] Search for queries
- [ ] Filter by type
- [ ] Filter by risk level
- [ ] Filter by status
- [ ] Export to PDF
- [ ] Print queries
- [ ] Delete a query

### Edge Cases
- [ ] Create query without company
- [ ] Upload very large file
- [ ] Upload invalid file type
- [ ] Search with special characters
- [ ] Multiple filters at once
- [ ] Empty query list display

---

## 🐛 Troubleshooting

### Issue: "Unauthorized access" error
**Solution:** Ensure you're logged in with valid session

### Issue: File upload fails
**Solutions:**
1. Check `uploads/queries` directory exists
2. Verify directory permissions: `chmod 777 uploads/queries`
3. Check PHP `upload_max_filesize` in php.ini
4. Check PHP `post_max_size` in php.ini

### Issue: Queries not loading
**Solutions:**
1. Verify database table exists
2. Check `query_handler.php` is accessible
3. Open browser console for JavaScript errors
4. Check PHP error logs

### Issue: Foreign key constraint fails
**Solutions:**
1. Ensure `companies` table exists and has data
2. Ensure `users` table exists and has data
3. Verify company_id and user_id are valid

### Issue: Search not working
**Solutions:**
1. Clear browser cache
2. Check JavaScript console for errors
3. Verify `searchQueries` input ID exists

---

## 📈 Future Enhancements

### Planned Features
- [ ] Email notifications for new queries
- [ ] Query assignment to specific users
- [ ] Query priority levels (separate from risk)
- [ ] Advanced search with date ranges
- [ ] Query analytics dashboard
- [ ] Bulk operations (bulk delete, bulk status update)
- [ ] Query templates for common questions
- [ ] Integration with actual ML services
- [ ] Query history/audit trail
- [ ] Comments/notes on queries
- [ ] File preview before upload
- [ ] Drag-and-drop file upload
- [ ] Query categories/tags
- [ ] Due dates and reminders
- [ ] Query escalation workflow

### Possible Integrations
- Email systems (SendGrid, Mailgun)
- Chat platforms (Slack, Teams)
- AI/ML services (OpenAI, Google Cloud AI)
- Voice-to-text services
- Document management systems

---

## 📞 Support & Maintenance

### Regular Maintenance Tasks
1. **Weekly**: Review pending queries
2. **Monthly**: Archive closed queries
3. **Quarterly**: Clean up old attachments
4. **Yearly**: Database optimization

### Backup Recommendations
- Daily backup of `client_queries` table
- Weekly backup of `uploads/queries` directory
- Keep backups for at least 90 days

---

## 📄 License & Credits

**Developed for:** AccountDB System  
**Feature:** Client Queries Management  
**Version:** 1.0.0  
**Date:** October 2025

---

## 🎓 Training Resources

### For End Users
1. Watch the demo video (if available)
2. Read the Quick Start guide
3. Practice with test data
4. Review the FAQ section

### For Developers
1. Review the database schema
2. Study the API endpoints
3. Understand the JavaScript handlers
4. Check the security implementations

---

## ✅ Completion Checklist

- [x] Database table created
- [x] Backend API implemented
- [x] Frontend UI designed
- [x] File upload functionality
- [x] Search and filter features
- [x] Export/Print capabilities
- [x] Status workflow
- [x] Security measures
- [x] Documentation
- [x] Test script

---

**🎉 Congratulations! Your Client Queries feature is now fully operational!**

For questions or issues, refer to the troubleshooting section or check the setup test results.
