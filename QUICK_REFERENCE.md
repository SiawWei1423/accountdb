# 🚀 Client Queries - Quick Reference Card

## 📋 Setup (One-Time)

```bash
# 1. Test Setup
http://localhost/accountdb/accountdb/test_queries_setup.php

# 2. Create Table (if needed)
mysql -u root -p accountdb < create_client_queries_table.sql

# 3. Create Directory (if needed)
mkdir uploads/queries
chmod 777 uploads/queries
```

---

## 🎯 Quick Access

| Action | Location | Shortcut |
|--------|----------|----------|
| **Add Query** | Sidebar → Add Query | Modal opens |
| **View Queries** | Sidebar → Manage Queries | Full page |
| **Dashboard Card** | Dashboard → Client Queries | Click to view |
| **Quick Action** | Dashboard → Add Client Query | Modal opens |

---

## 📝 Form Fields Reference

### Required Fields ⭐
- Company (dropdown)
- Client Name (text)
- Question (textarea)
- Type (RD / AG / Doc)
- Risk Level (Low / Middle / High)
- Date (date picker)

### Optional Fields
- Answer (textarea)
- ML Enabled (checkbox)
- Photo (image file)
- Voice (audio file)

---

## 🏷️ Type Codes

| Code | Full Name | Use For |
|------|-----------|---------|
| **RD** | Research & Development | Technical queries |
| **AG** | Agreement | Contract/legal queries |
| **Doc** | Documentation | Document-related queries |

---

## ⚠️ Risk Levels

| Level | Color | Priority | Action |
|-------|-------|----------|--------|
| **Low** | 🟢 Green | Normal | Standard response |
| **Middle** | 🟡 Yellow | Moderate | Review soon |
| **High** | 🔴 Red | Urgent | Immediate attention |

---

## 📊 Status Workflow

```
Pending → In Progress → Resolved → Closed
   ↓           ↓            ↓          ↓
  New      Working on    Answered   Complete
```

---

## 🔍 Search & Filter

### Search Bar
- Searches: Client name, Company, Question text
- Real-time filtering

### Filters
1. **Type** - RD, AG, Doc
2. **Risk Level** - Low, Middle, High
3. **Status** - Pending, In Progress, Resolved, Closed

### Clear All
Click "Clear Filters" button to reset

---

## 🎨 Color Guide

| Element | Color | Hex Code |
|---------|-------|----------|
| Primary | Yellow | #ffc107 |
| Secondary | Orange | #ff9800 |
| Success | Green | #28a745 |
| Danger | Red | #dc3545 |
| Info | Blue | #17a2b8 |

---

## 🔧 Common Actions

### View Query Details
```
1. Go to Manage Queries
2. Click "View" button
3. See full details + files
4. Update status if needed
```

### Update Status
```
1. Open query details
2. Select new status from dropdown
3. Click "Update Status"
4. Status saved automatically
```

### Delete Query
```
1. Go to Manage Queries
2. Click "Delete" button (trash icon)
3. Confirm deletion
4. Query + files removed
```

### Export Queries
```
PDF: Click "Export to PDF" button
Print: Click "Print" button
```

---

## 📁 File Uploads

### Supported Photo Formats
- JPG, JPEG, PNG, GIF, BMP, WEBP

### Supported Audio Formats
- MP3, WAV, OGG, M4A

### File Location
```
uploads/queries/query_photo_[timestamp]_[random].[ext]
uploads/queries/query_voice_[timestamp]_[random].[ext]
```

---

## 🐛 Quick Troubleshooting

| Problem | Solution |
|---------|----------|
| Can't add query | Check if logged in |
| File won't upload | Check directory permissions |
| Queries not showing | Run test_queries_setup.php |
| Search not working | Clear browser cache |
| Status won't update | Check database connection |

---

## 📞 Emergency Commands

### Check Table Exists
```sql
SHOW TABLES LIKE 'client_queries';
```

### Count Queries
```sql
SELECT COUNT(*) FROM client_queries;
```

### View Recent Queries
```sql
SELECT * FROM client_queries ORDER BY created_at DESC LIMIT 10;
```

### Fix Permissions
```bash
chmod 777 uploads/queries
```

---

## 🔒 Security Checklist

- ✅ Always logged in before accessing
- ✅ Don't share session tokens
- ✅ Validate file types before upload
- ✅ Regular backups of database
- ✅ Keep software updated

---

## 📊 Dashboard Stats

**Client Queries Card Shows:**
- Total number of queries
- Click to view all queries
- Yellow/orange gradient design

**Quick Actions Include:**
- Add Client Query (yellow button)
- View All Queries (dark yellow button)

---

## ⌨️ Keyboard Shortcuts (Future)

| Key | Action |
|-----|--------|
| `Ctrl + Q` | Add Query (planned) |
| `Ctrl + F` | Focus Search (planned) |
| `Esc` | Close Modal (works now) |

---

## 📱 Mobile Tips

- Swipe table horizontally to see all columns
- Tap "View" for full details
- Use filters to narrow results
- Portrait mode recommended for forms

---

## 🎓 Best Practices

1. **Add queries promptly** - Don't let them pile up
2. **Update status regularly** - Keep workflow moving
3. **Use risk levels wisely** - Reserve High for urgent
4. **Add answers when resolved** - Complete the loop
5. **Archive old queries** - Keep system clean

---

## 📈 Performance Tips

- Use filters to reduce visible rows
- Search for specific queries instead of scrolling
- Export old queries and archive them
- Clear browser cache if slow

---

## 🔗 Quick Links

| Resource | File |
|----------|------|
| Setup Guide | CLIENT_QUERIES_SETUP.md |
| Full Manual | README_CLIENT_QUERIES.md |
| Summary | IMPLEMENTATION_SUMMARY.md |
| Test Script | test_queries_setup.php |
| Database | create_client_queries_table.sql |

---

## 📞 Support Resources

### Documentation
1. Read CLIENT_QUERIES_SETUP.md first
2. Check README_CLIENT_QUERIES.md for details
3. Review IMPLEMENTATION_SUMMARY.md for overview

### Testing
- Run test_queries_setup.php anytime
- All checks should pass ✅

### Troubleshooting
- Check browser console (F12)
- Review PHP error logs
- Verify database connection

---

## ✅ Daily Checklist

**Morning:**
- [ ] Check pending queries
- [ ] Review high-risk queries
- [ ] Update in-progress queries

**Afternoon:**
- [ ] Respond to new queries
- [ ] Update statuses
- [ ] Add answers to resolved queries

**Evening:**
- [ ] Close completed queries
- [ ] Export daily report (optional)
- [ ] Plan for tomorrow

---

## 🎯 Quick Stats

**Feature Includes:**
- 10 form fields
- 4 filter options
- 4 status levels
- 3 risk levels
- 3 query types
- 2 file upload types
- 1 awesome system! 🎉

---

## 💡 Pro Tips

1. **Use ML checkbox** for queries that might benefit from automation
2. **Add voice notes** for complex explanations
3. **Attach photos** for visual reference
4. **Update answers** even for closed queries (for future reference)
5. **Export regularly** for backup and reporting

---

## 🏆 Success Metrics

Track these to measure effectiveness:
- Average resolution time
- Queries by risk level
- Queries by type
- Status distribution
- User engagement

---

## 🚨 Red Flags

Watch out for:
- ⚠️ Too many high-risk queries
- ⚠️ Queries stuck in "In Progress"
- ⚠️ Old pending queries
- ⚠️ Unanswered resolved queries
- ⚠️ No queries (system not being used)

---

## 📅 Maintenance Schedule

**Daily:** Review new queries  
**Weekly:** Update old queries  
**Monthly:** Archive closed queries  
**Quarterly:** Database optimization  
**Yearly:** System review

---

## 🎨 UI Elements

### Badges
- **Type:** Blue pill
- **Risk:** Color-coded pill
- **Status:** Color-coded pill
- **ML:** ✅ or ❌ icon

### Buttons
- **View:** Blue info button
- **Delete:** Red danger button
- **Update:** Yellow warning button
- **Export:** Red outline button
- **Print:** Blue outline button

---

## 🔄 Update Frequency

**Real-time:**
- Query creation
- Status updates
- Search results

**On Page Load:**
- Query list
- Statistics
- Filters

**Manual Refresh:**
- Dashboard counts
- Export data

---

**Print this page for quick reference! 📄**

---

**Version:** 1.0.0  
**Last Updated:** October 9, 2025  
**Status:** Active ✅
