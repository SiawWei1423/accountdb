# ✅ Deployment Checklist - Client Queries Feature

## 🎯 Pre-Deployment Verification

### Phase 1: File Verification ✅

- [ ] **Core Files Present**
  - [ ] `index.php` (modified)
  - [ ] `query_handler.php` (new)
  - [ ] `create_client_queries_table.sql` (new)
  
- [ ] **Documentation Files Present**
  - [ ] `CLIENT_QUERIES_SETUP.md`
  - [ ] `README_CLIENT_QUERIES.md`
  - [ ] `IMPLEMENTATION_SUMMARY.md`
  - [ ] `QUICK_REFERENCE.md`
  - [ ] `DEPLOYMENT_CHECKLIST.md` (this file)
  
- [ ] **Test Files Present**
  - [ ] `test_queries_setup.php`

---

### Phase 2: Database Setup ✅

- [ ] **Database Connection**
  - [ ] MySQL/MariaDB running
  - [ ] Database `accountdb` exists
  - [ ] Connection credentials correct in `db_connection.php`
  
- [ ] **Required Tables Exist**
  - [ ] `companies` table exists
  - [ ] `users` table exists
  - [ ] `admin` table exists (if using admins)
  
- [ ] **Create Client Queries Table**
  ```sql
  -- Run this in phpMyAdmin or MySQL CLI
  SOURCE create_client_queries_table.sql;
  ```
  - [ ] Table `client_queries` created successfully
  - [ ] All 15 columns present
  - [ ] Foreign keys established
  - [ ] Indexes created

- [ ] **Verify Table Structure**
  ```sql
  DESCRIBE client_queries;
  ```
  - [ ] Output shows all columns correctly

---

### Phase 3: Directory Setup ✅

- [ ] **Upload Directory**
  ```bash
  mkdir -p uploads/queries
  ```
  - [ ] Directory `uploads/queries` exists
  
- [ ] **Permissions**
  ```bash
  chmod 777 uploads/queries
  ```
  - [ ] Directory is writable
  - [ ] Web server can create files

- [ ] **Test Write Access**
  ```bash
  touch uploads/queries/test.txt
  rm uploads/queries/test.txt
  ```
  - [ ] File creation successful
  - [ ] File deletion successful

---

### Phase 4: Run Test Script ✅

- [ ] **Access Test Page**
  ```
  http://localhost/accountdb/accountdb/test_queries_setup.php
  ```
  
- [ ] **All Checks Pass**
  - [ ] ✅ Database connection successful
  - [ ] ✅ Table 'client_queries' exists
  - [ ] ✅ Table 'companies' exists
  - [ ] ✅ Table 'users' exists
  - [ ] ✅ Directory 'uploads/queries' exists
  - [ ] ✅ Directory is writable
  - [ ] ✅ All required files present
  - [ ] ✅ Sample data exists (companies/users)

---

## 🧪 Testing Phase

### Phase 5: Functional Testing ✅

#### Test 1: Add Query (Required Fields Only)
- [ ] Log in to application
- [ ] Click "Add Query" in sidebar
- [ ] Fill in required fields:
  - [ ] Select a company
  - [ ] Enter client name
  - [ ] Enter question
  - [ ] Select type (RD/AG/Doc)
  - [ ] Select risk level
  - [ ] Select date
- [ ] Click "Add Query"
- [ ] Success message appears
- [ ] Modal closes
- [ ] Query appears in Manage Queries

#### Test 2: Add Query (All Fields)
- [ ] Open Add Query modal
- [ ] Fill all required fields
- [ ] Enter answer text
- [ ] Check ML checkbox
- [ ] Upload a photo (JPG/PNG)
- [ ] Upload a voice file (MP3)
- [ ] Submit form
- [ ] Files uploaded successfully
- [ ] Query created with all data

#### Test 3: View Query Details
- [ ] Go to Manage Queries
- [ ] Click "View" on a query
- [ ] Modal opens with details
- [ ] All information displayed correctly
- [ ] Photo displays (if uploaded)
- [ ] Audio player works (if uploaded)
- [ ] Status dropdown present

#### Test 4: Update Status
- [ ] Open query details
- [ ] Change status dropdown
- [ ] Click "Update Status"
- [ ] Success message appears
- [ ] Modal closes
- [ ] Table refreshes
- [ ] New status badge shows

#### Test 5: Search Functionality
- [ ] Go to Manage Queries
- [ ] Type in search box
- [ ] Results filter in real-time
- [ ] Search works for:
  - [ ] Client name
  - [ ] Company name
  - [ ] Question text

#### Test 6: Filter by Type
- [ ] Select "RD" from Type filter
- [ ] Only RD queries show
- [ ] Select "AG" from Type filter
- [ ] Only AG queries show
- [ ] Select "Doc" from Type filter
- [ ] Only Doc queries show

#### Test 7: Filter by Risk Level
- [ ] Select "Low" from Risk filter
- [ ] Only Low risk queries show
- [ ] Select "Middle" from Risk filter
- [ ] Only Middle risk queries show
- [ ] Select "High" from Risk filter
- [ ] Only High risk queries show

#### Test 8: Filter by Status
- [ ] Select each status
- [ ] Correct queries display
- [ ] Badge colors match status

#### Test 9: Multiple Filters
- [ ] Apply search + type filter
- [ ] Results match both criteria
- [ ] Add risk filter
- [ ] Results match all three
- [ ] Add status filter
- [ ] Results match all four

#### Test 10: Clear Filters
- [ ] Apply multiple filters
- [ ] Click "Clear Filters"
- [ ] All filters reset
- [ ] All queries visible again

#### Test 11: Export to PDF
- [ ] Click "Export to PDF"
- [ ] PDF generation starts
- [ ] PDF downloads successfully
- [ ] PDF contains query data
- [ ] Formatting looks good

#### Test 12: Print
- [ ] Click "Print"
- [ ] Print dialog opens
- [ ] Preview looks correct
- [ ] Can print or save as PDF

#### Test 13: Delete Query
- [ ] Click "Delete" on a query
- [ ] Confirmation dialog appears
- [ ] Confirm deletion
- [ ] Query removed from table
- [ ] Files deleted from server
- [ ] Success message shows

#### Test 14: Dashboard Integration
- [ ] Go to Dashboard
- [ ] Client Queries card visible
- [ ] Count is correct
- [ ] Click card → navigates to Manage Queries
- [ ] Quick action buttons work
- [ ] "Add Client Query" opens modal
- [ ] "View All Queries" navigates to page

---

### Phase 6: Edge Case Testing ✅

#### Edge Case 1: Empty State
- [ ] Delete all queries
- [ ] Go to Manage Queries
- [ ] Empty state message displays
- [ ] Icon and text visible
- [ ] No errors in console

#### Edge Case 2: Large Files
- [ ] Try uploading 10MB+ photo
- [ ] Appropriate error or success
- [ ] Try uploading 20MB+ audio
- [ ] Appropriate error or success

#### Edge Case 3: Invalid Files
- [ ] Try uploading .exe file as photo
- [ ] Upload rejected or error shown
- [ ] Try uploading .txt file as audio
- [ ] Upload rejected or error shown

#### Edge Case 4: Special Characters
- [ ] Enter special chars in client name: `<script>alert('test')</script>`
- [ ] Characters escaped properly
- [ ] No XSS vulnerability
- [ ] Enter SQL injection attempt: `'; DROP TABLE client_queries; --`
- [ ] Query fails safely
- [ ] No SQL injection

#### Edge Case 5: Long Text
- [ ] Enter 5000+ character question
- [ ] Form accepts or shows limit
- [ ] Data saves correctly
- [ ] Display truncates properly

#### Edge Case 6: Concurrent Users
- [ ] Open in two browsers
- [ ] Add query in browser 1
- [ ] Refresh browser 2
- [ ] Query appears in browser 2
- [ ] No conflicts

---

## 🔒 Security Testing

### Phase 7: Security Verification ✅

- [ ] **Authentication**
  - [ ] Cannot access without login
  - [ ] Session timeout works
  - [ ] Logout clears session
  
- [ ] **Authorization**
  - [ ] Users can only see their queries (if applicable)
  - [ ] Admins can see all queries (if applicable)
  
- [ ] **SQL Injection**
  - [ ] All inputs use prepared statements
  - [ ] No direct SQL concatenation
  
- [ ] **XSS Protection**
  - [ ] All output uses htmlspecialchars()
  - [ ] Script tags don't execute
  
- [ ] **File Upload Security**
  - [ ] File type validation works
  - [ ] File size limits enforced
  - [ ] Files stored securely
  - [ ] No executable files accepted
  
- [ ] **CSRF Protection**
  - [ ] Forms use session validation
  - [ ] External POST requests fail

---

## 🌐 Browser Compatibility

### Phase 8: Cross-Browser Testing ✅

- [ ] **Chrome/Edge (Chromium)**
  - [ ] All features work
  - [ ] UI displays correctly
  - [ ] No console errors
  
- [ ] **Firefox**
  - [ ] All features work
  - [ ] UI displays correctly
  - [ ] No console errors
  
- [ ] **Safari** (if available)
  - [ ] All features work
  - [ ] UI displays correctly
  - [ ] No console errors

---

## 📱 Responsive Design

### Phase 9: Device Testing ✅

- [ ] **Desktop (1920x1080)**
  - [ ] Layout looks good
  - [ ] All elements visible
  - [ ] No horizontal scroll
  
- [ ] **Laptop (1366x768)**
  - [ ] Layout adjusts properly
  - [ ] All features accessible
  
- [ ] **Tablet (768x1024)**
  - [ ] Columns stack appropriately
  - [ ] Touch targets adequate
  - [ ] Forms usable
  
- [ ] **Mobile (375x667)**
  - [ ] Single column layout
  - [ ] Buttons large enough
  - [ ] Table scrolls horizontally
  - [ ] Modal fits screen

---

## 📊 Performance Testing

### Phase 10: Performance Verification ✅

- [ ] **Load Time**
  - [ ] Page loads in < 3 seconds
  - [ ] Modal opens instantly
  - [ ] Queries load in < 2 seconds
  
- [ ] **Large Dataset**
  - [ ] Test with 100+ queries
  - [ ] Search still fast
  - [ ] Filters work quickly
  - [ ] No lag in UI
  
- [ ] **File Upload**
  - [ ] 5MB photo uploads in < 10 seconds
  - [ ] 10MB audio uploads in < 20 seconds
  - [ ] Progress indication (if added)

---

## 📝 Documentation Review

### Phase 11: Documentation Check ✅

- [ ] **Setup Guide**
  - [ ] Steps are clear
  - [ ] Commands are correct
  - [ ] Screenshots helpful (if added)
  
- [ ] **User Manual**
  - [ ] All features documented
  - [ ] Examples provided
  - [ ] Troubleshooting section complete
  
- [ ] **Quick Reference**
  - [ ] Easy to read
  - [ ] Covers common tasks
  - [ ] Printable format
  
- [ ] **API Documentation**
  - [ ] All endpoints listed
  - [ ] Parameters documented
  - [ ] Response formats shown

---

## 🚀 Production Deployment

### Phase 12: Go Live Checklist ✅

- [ ] **Backup**
  - [ ] Database backed up
  - [ ] Files backed up
  - [ ] Backup tested (can restore)
  
- [ ] **Configuration**
  - [ ] Database credentials correct
  - [ ] File paths correct
  - [ ] Error reporting appropriate (off in production)
  
- [ ] **Permissions**
  - [ ] File permissions secure (755 for directories, 644 for files)
  - [ ] Upload directory writable (777 or appropriate)
  
- [ ] **Monitoring**
  - [ ] Error logging enabled
  - [ ] Access logging enabled
  - [ ] Disk space monitored
  
- [ ] **Cleanup**
  - [ ] Remove test_queries_setup.php (or restrict access)
  - [ ] Remove test data
  - [ ] Clear development logs

---

## 👥 User Training

### Phase 13: Training Checklist ✅

- [ ] **Admin Training**
  - [ ] How to add queries
  - [ ] How to manage queries
  - [ ] How to update statuses
  - [ ] How to export data
  
- [ ] **User Training**
  - [ ] Basic navigation
  - [ ] Adding queries
  - [ ] Viewing queries
  - [ ] Understanding statuses
  
- [ ] **Documentation Distribution**
  - [ ] Quick Reference shared
  - [ ] User Manual accessible
  - [ ] Support contact provided

---

## 📈 Post-Deployment

### Phase 14: Monitoring (First Week) ✅

- [ ] **Day 1**
  - [ ] Monitor error logs
  - [ ] Check user feedback
  - [ ] Verify all features working
  
- [ ] **Day 3**
  - [ ] Review usage statistics
  - [ ] Check for performance issues
  - [ ] Address any bugs
  
- [ ] **Day 7**
  - [ ] Collect user feedback
  - [ ] Plan improvements
  - [ ] Update documentation if needed

---

## 🎯 Success Criteria

### All Must Pass ✅

- [ ] Zero critical bugs
- [ ] All tests passing
- [ ] Documentation complete
- [ ] Users trained
- [ ] Backups in place
- [ ] Monitoring active
- [ ] Performance acceptable
- [ ] Security verified

---

## 📞 Support Plan

### Phase 15: Support Setup ✅

- [ ] **Support Channels**
  - [ ] Email support configured
  - [ ] Help desk tickets enabled
  - [ ] Phone support available (if applicable)
  
- [ ] **Response Times**
  - [ ] Critical: < 1 hour
  - [ ] High: < 4 hours
  - [ ] Medium: < 24 hours
  - [ ] Low: < 72 hours
  
- [ ] **Escalation Path**
  - [ ] Level 1: User support
  - [ ] Level 2: Technical support
  - [ ] Level 3: Developer

---

## 🔄 Maintenance Plan

### Ongoing Tasks ✅

- [ ] **Daily**
  - [ ] Check error logs
  - [ ] Monitor disk space
  - [ ] Review new queries
  
- [ ] **Weekly**
  - [ ] Database backup
  - [ ] Performance review
  - [ ] User feedback review
  
- [ ] **Monthly**
  - [ ] Security updates
  - [ ] Feature requests review
  - [ ] Usage statistics report
  
- [ ] **Quarterly**
  - [ ] Full system audit
  - [ ] Documentation update
  - [ ] Training refresh

---

## ✅ Final Sign-Off

### Deployment Approval

**Tested By:** ___________________  
**Date:** ___________________  
**Signature:** ___________________  

**Approved By:** ___________________  
**Date:** ___________________  
**Signature:** ___________________  

---

## 🎉 Deployment Complete!

Once all items are checked:

1. ✅ Mark deployment as complete
2. 📧 Notify all stakeholders
3. 📊 Begin monitoring
4. 🎓 Continue user training
5. 🔄 Plan next iteration

---

**Deployment Status:** ⏳ In Progress  
**Target Go-Live Date:** ___________________  
**Actual Go-Live Date:** ___________________  

---

**Version:** 1.0.0  
**Last Updated:** October 9, 2025  
**Checklist Status:** Ready for Use ✅
