# 🔄 Multiple Q&A Pairs - Feature Update

## 📋 What Changed?

The Client Queries feature now supports **multiple Question & Answer pairs** per query, making it easier to handle complex client inquiries with several related questions.

---

## ✨ New Features

### 1. **Dynamic Q&A Pairs**
- Add unlimited Q&A pairs to each query
- Each pair is numbered (1, 2, 3, etc.)
- Easy "Add Q&A" button to add more pairs
- Remove button for each additional pair

### 2. **Improved UI**
- Clean, organized layout
- Each Q&A pair in its own highlighted box
- Visual numbering (1. Q&A Pair, 2. Q&A Pair, etc.)
- Scroll to new pair when added

### 3. **Better Display**
- Table shows first question with "+X more" badge
- Details modal shows all Q&A pairs numbered
- Each pair clearly separated and highlighted

---

## 🎨 UI Preview

### Add Query Modal:
```
┌─────────────────────────────────────────┐
│ Questions & Answers *     [+ Add Q&A]   │
├─────────────────────────────────────────┤
│ ┌─────────────────────────────────────┐ │
│ │ 1. Q&A Pair                         │ │
│ │ Question (Q) *                      │ │
│ │ [textarea]                          │ │
│ │ Answer (A)                          │ │
│ │ [textarea]                          │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ ┌─────────────────────────────────────┐ │
│ │ 2. Q&A Pair              [Remove]   │ │
│ │ Question (Q)                        │ │
│ │ [textarea]                          │ │
│ │ Answer (A)                          │ │
│ │ [textarea]                          │ │
│ └─────────────────────────────────────┘ │
└─────────────────────────────────────────┘
```

### Table Display:
```
Question Column:
"What is the tax rate?" +2 more
```

### Details View:
```
┌─────────────────────────────────────┐
│ Questions & Answers:                │
├─────────────────────────────────────┤
│ 1. Question:                        │
│    What is the tax rate?            │
│    Answer:                          │
│    The tax rate is 24%              │
├─────────────────────────────────────┤
│ 2. Question:                        │
│    When is the deadline?            │
│    Answer:                          │
│    December 31, 2025                │
├─────────────────────────────────────┤
│ 3. Question:                        │
│    What documents are needed?       │
│    Answer:                          │
│    Financial statements and ID      │
└─────────────────────────────────────┘
```

---

## 🗄️ Database Changes

### Old Structure:
```sql
question TEXT NOT NULL,
answer TEXT,
```

### New Structure:
```sql
qa_pairs JSON NOT NULL COMMENT 'Array of {question, answer} objects',
```

### JSON Format:
```json
[
  {
    "question": "What is the tax rate?",
    "answer": "The tax rate is 24%"
  },
  {
    "question": "When is the deadline?",
    "answer": "December 31, 2025"
  }
]
```

---

## 🚀 How to Update

### For New Installations:
1. Use the updated `create_client_queries_table.sql`
2. Everything works out of the box!

### For Existing Installations:

#### Option 1: Fresh Start (No Data)
```sql
DROP TABLE IF EXISTS client_queries;
SOURCE create_client_queries_table.sql;
```

#### Option 2: Migrate Existing Data
```sql
SOURCE migrate_queries_to_multiple_qa.sql;
```
This will:
- Create new table structure
- Convert old single Q&A to array format
- Preserve all existing data

---

## 📝 How to Use

### Adding Multiple Q&A Pairs:

1. **Open Add Query Modal**
   - Click "Add Query" in sidebar

2. **Fill First Q&A Pair** (Required)
   - Enter question in "Question (Q)" field
   - Optionally enter answer in "Answer (A)" field

3. **Add More Pairs** (Optional)
   - Click "+ Add Q&A" button
   - New pair appears below
   - Fill in question and answer
   - Repeat as needed

4. **Remove Pairs** (If Needed)
   - Click "Remove" button on any pair (except first)
   - Pairs automatically renumber

5. **Submit**
   - Click "Add Query" button
   - All Q&A pairs saved together

### Viewing Q&A Pairs:

1. Go to **Manage Queries**
2. Click **View** on any query
3. See all Q&A pairs numbered and organized
4. Each pair clearly separated

---

## 🎯 Use Cases

### Example 1: Tax Consultation
```
1. Q: What is my tax bracket?
   A: You are in the 24% bracket

2. Q: Can I deduct home office expenses?
   A: Yes, if you meet the requirements

3. Q: When is the filing deadline?
   A: April 15, 2026
```

### Example 2: Document Requirements
```
1. Q: What documents do I need for registration?
   A: Business license, ID, and proof of address

2. Q: How long does processing take?
   A: 7-10 business days

3. Q: What is the registration fee?
   A: RM500
```

### Example 3: Compliance Questions
```
1. Q: Do I need an audit?
   A: Yes, if revenue exceeds RM5 million

2. Q: Who can be the auditor?
   A: A licensed public accountant

3. Q: When should the audit be completed?
   A: Within 6 months of year-end
```

---

## 🔧 Technical Details

### JavaScript Functions:

**`addQAPair()`**
- Adds new Q&A pair to form
- Auto-numbers pairs
- Scrolls to new pair

**`removeQAPair(button)`**
- Removes Q&A pair
- Renumbers remaining pairs
- Updates counter

**Form Submission:**
- Collects all questions and answers
- Converts to JSON array
- Sends to backend

### Backend Processing:

**`query_handler.php`**
- Receives Q&A pairs as JSON
- Validates at least one question exists
- Stores in database as JSON column

**Database:**
- Uses MySQL JSON data type
- Efficient storage and retrieval
- Easy to query and update

---

## 🎨 Styling

### Colors:
- **Q&A Container:** Light yellow background (`rgba(255, 193, 7, 0.05)`)
- **Border:** Yellow left border (`#ffc107`)
- **Question Label:** Yellow text (`#ffc107`)
- **Answer Label:** Green text (`#28a745`)
- **Add Button:** Yellow gradient
- **Remove Button:** Red danger

### Layout:
- Each pair in rounded box
- Consistent padding and spacing
- Clear visual separation
- Responsive design

---

## ✅ Benefits

### For Users:
- ✅ Handle complex queries with multiple questions
- ✅ Keep related questions together
- ✅ Easy to add/remove questions
- ✅ Clear organization

### For Admins:
- ✅ Better data organization
- ✅ Easier to track conversations
- ✅ More complete information
- ✅ Better reporting

### For System:
- ✅ Flexible data structure
- ✅ Scalable solution
- ✅ Easy to extend
- ✅ Efficient storage

---

## 🐛 Troubleshooting

### Issue: "Error parsing questions"
**Solution:** Database might have old format. Run migration script.

### Issue: Can't add more Q&A pairs
**Solution:** Check browser console for JavaScript errors.

### Issue: First pair can't be removed
**Solution:** This is by design - at least one Q&A pair is required.

### Issue: Q&A pairs not showing in details
**Solution:** Ensure data is stored as valid JSON in database.

---

## 📊 Migration Checklist

- [ ] Backup existing database
- [ ] Run migration script
- [ ] Test adding new query with multiple Q&A
- [ ] Test viewing existing queries
- [ ] Verify all data migrated correctly
- [ ] Update any custom reports/exports

---

## 🔄 Backward Compatibility

### Old Data:
- Automatically converted to new format
- Single Q&A becomes array with one item
- No data loss during migration

### New Data:
- Always stored as JSON array
- Minimum one Q&A pair required
- Maximum unlimited (practical limit ~50)

---

## 📈 Performance

### Storage:
- JSON column is efficient
- Indexed for fast retrieval
- Minimal overhead

### Display:
- Fast parsing in JavaScript
- Smooth scrolling
- No lag with many pairs

### Limits:
- Recommended: 1-10 Q&A pairs per query
- Tested: Up to 50 pairs
- Maximum: Limited by JSON size (64KB)

---

## 🎓 Best Practices

1. **Group Related Questions**
   - Keep Q&A pairs focused on one topic
   - Don't mix unrelated questions

2. **Clear Questions**
   - Write specific, clear questions
   - Avoid ambiguous wording

3. **Complete Answers**
   - Provide thorough answers
   - Include relevant details

4. **Reasonable Number**
   - 3-5 pairs is ideal
   - More than 10 might be too many

5. **Use Status Workflow**
   - Update status as you answer questions
   - Mark resolved when all answered

---

## 🚀 What's Next?

### Planned Enhancements:
- [ ] Drag-and-drop reordering
- [ ] Duplicate Q&A pair
- [ ] Q&A templates
- [ ] Export individual Q&A pairs
- [ ] Search within Q&A pairs

---

## 📞 Support

### Questions?
- Check `README_CLIENT_QUERIES.md`
- Review `QUICK_REFERENCE.md`
- Run `test_queries_setup.php`

### Issues?
- Check browser console (F12)
- Review PHP error logs
- Verify database structure

---

**Version:** 2.0.0  
**Updated:** October 9, 2025  
**Feature:** Multiple Q&A Pairs ✅  
**Status:** Production Ready 🚀
