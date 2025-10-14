# 🔄 Bidirectional Sync Setup

This document explains how to set up and use the bidirectional sync functionality in your QR Attendance System.

## 🎯 What is Bidirectional Sync?

**Bidirectional Sync** allows data to flow in both directions:

- **Website → Python**: Student data (with passwords) syncs from hosting to local `students.json`
- **Python → Website**: Attendance data syncs from local to hosting database

## 🚀 Quick Setup

### Step 1: Configure API Settings

Update your `python/settings.json`:

```json
{
  "settings": {
    "students_api_url": "http://your-domain.com/api/students_sync.php",
    "students_api_key": "your-secret-api-key-123",
    "bidirectional_sync_enabled": true,
    "clear_local_after_sync": false
  }
}
```

### Step 2: Update API Key

1. **In PHP API** (`public/api/students_sync.php`):
   ```php
   $api_key = 'your-secret-api-key-123'; // Change this
   ```

2. **In Python settings** (`python/settings.json`):
   ```json
   "students_api_key": "your-secret-api-key-123"
   ```

### Step 3: Test the Setup

Run the test script:
```bash
python test_bidirectional_sync.py
```

## 🎮 How to Use

### Manual Commands

When running `python app.py`, you can use these commands:

- **`bidirectional_sync`**: Run one-time bidirectional sync
- **`start_bidirectional_sync`**: Start automatic sync in background
- **`sync_students`**: Sync only students from website
- **`status`**: Check sync status

### Example Usage

```bash
# Start the app
python app.py

# In the app, type:
bidirectional_sync          # Run sync once
start_bidirectional_sync   # Start automatic sync
```

## 🔧 Configuration Options

### Settings in `python/settings.json`:

| Setting | Description | Default |
|---------|-------------|---------|
| `students_api_url` | URL of the students sync API | `http://localhost/qr_attendance/public/api/students_sync.php` |
| `students_api_key` | API key for authentication | `your-secret-api-key-123` |
| `bidirectional_sync_enabled` | Enable bidirectional sync | `true` |
| `clear_local_after_sync` | Clear local data after successful sync | `false` |
| `sync_interval_seconds` | How often to sync (in seconds) | `30` |

## 📊 How It Works

### 1. Student Sync (Website → Python)

```
Website Database → students_sync.php API → Python students.json
```

- Fetches all students from website database
- Generates passwords for each student
- Updates local `students.json` file
- Maintains proper JSON structure with metadata

### 2. Attendance Sync (Python → Website)

```
Python attendance.csv → api_attendance.php API → Website Database
```

- Reads local attendance data
- Sends to website API
- Optionally clears local data after successful sync

### 3. Automatic Background Sync

- Runs every 30 seconds (configurable)
- Syncs students from website
- Syncs attendance to website
- Handles errors gracefully
- Continues running in background

## 🛠️ API Endpoints

### Students Sync API (`/api/students_sync.php`)

**GET** `?action=get_students&api_key=your-key`
- Returns all students with generated passwords
- Returns JSON structure compatible with `students.json`

**POST** `?action=add_student&api_key=your-key`
- Adds new student to database
- Body: `{"student_id": "...", "name": "...", "email": "...", ...}`

**POST** `?action=update_student&api_key=your-key`
- Updates existing student
- Body: `{"student_id": "...", "name": "...", "email": "...", ...}`

### Attendance API (`/api/api_attendance.php`)

**POST** with JSON body:
```json
{
  "api_key": "your-key",
  "attendance_data": [
    {
      "student_id": "25-SWT-01",
      "name": "Student Name",
      "timestamp": "2025-01-14 10:00:00",
      "status": "Present"
    }
  ]
}
```

## 🔍 Troubleshooting

### Common Issues

1. **"No internet connection"**
   - Check your internet connection
   - Verify the website URL in settings

2. **"Website not accessible"**
   - Check if the hosting server is running
   - Verify the API endpoints are accessible

3. **"API returned error"**
   - Check the API key matches in both PHP and Python
   - Verify the API endpoints are working

4. **"JSON decode error"**
   - Check if the API is returning valid JSON
   - Test the API endpoints manually

### Debug Steps

1. **Test API endpoints manually**:
   ```bash
   # Test students API
   curl "http://your-domain.com/api/students_sync.php?action=get_students&api_key=your-key"
   
   # Test attendance API
   curl -X POST "http://your-domain.com/api/api_attendance.php" \
        -H "Content-Type: application/json" \
        -d '{"api_key":"your-key","attendance_data":[]}'
   ```

2. **Check Python logs**:
   - Look for error messages in the console
   - Check if files are being created/updated

3. **Verify file permissions**:
   - Make sure Python can write to `students.json`
   - Check if the API can read from the database

## 📈 Monitoring

### Check Sync Status

Use the `status` command in the app to see:
- Connection status
- Last sync time
- Number of students synced
- Number of attendance records synced

### Log Files

The system logs all sync operations to the console. Look for:
- ✅ Success messages
- ❌ Error messages
- 🔄 Sync progress updates

## 🚀 Advanced Usage

### Custom Sync Intervals

```json
{
  "sync_interval_seconds": 60  // Sync every minute
}
```

### Clear Local Data After Sync

```json
{
  "clear_local_after_sync": true  // Clear attendance.csv after sync
}
```

### Multiple API Keys

You can use different API keys for different operations:
- Students API key for student sync
- Attendance API key for attendance sync

## 🎉 Benefits

- ✅ **Real-time sync**: Data stays synchronized between systems
- ✅ **Offline support**: Works even when internet is intermittent
- ✅ **Automatic**: Runs in background without manual intervention
- ✅ **Reliable**: Handles errors gracefully and retries
- ✅ **Secure**: Uses API key authentication
- ✅ **Flexible**: Configurable sync intervals and options

## 📞 Support

If you encounter issues:

1. Check the troubleshooting section above
2. Run the test script: `python test_bidirectional_sync.py`
3. Check the console logs for error messages
4. Verify your API endpoints are working
5. Ensure your settings are configured correctly
